<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';

startSecureSession();

if (isSessionExpired()) {
    destroySession();
    header('Location: ../LoginHome.html');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../LoginHome.html');
    exit;
}
refreshSessionLifetime();

if (empty($_SESSION['student_management_csrf'])) {
    $_SESSION['student_management_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['student_management_csrf'];

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'studenthub';

$conn = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
if (!$conn) {
    die('Database connection failed: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8'));
}
mysqli_set_charset($conn, 'utf8mb4');

function bindPreparedStatement(mysqli_stmt $statement, string $types, array $params): void
{
    if ($params === []) {
        return;
    }

    $statement->bind_param($types, ...$params);
}

function escapeText($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function recordStudentAudit(mysqli $connection, string $actor, string $action, int $studentId, array $details): bool
{
    try {
        $statement = $connection->prepare(
            'INSERT INTO audit_logs (actor_email, action_type, entity_type, entity_id, details)
             VALUES (?, ?, ?, ?, ?)'
        );
        $entityType = 'student';
        $jsonDetails = json_encode($details, JSON_THROW_ON_ERROR);
        $statement->bind_param('sssis', $actor, $action, $entityType, $studentId, $jsonDetails);
        $statement->execute();
        $statement->close();
        return true;
    } catch (Throwable $exception) {
        error_log('Student management audit write failed: ' . $exception->getMessage());
        return false;
    }
}

$message = '';
$messageType = 'success';
$editStudent = null;
$formMode = 'create';
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$courseFilter = trim((string) ($_GET['course'] ?? ''));
$yearFilter = trim((string) ($_GET['year'] ?? ''));

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT student_id, full_name, email, course_id, year_of_study, city, cgpa FROM students WHERE student_id = ? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editStudent = $result->fetch_assoc();
    $stmt->close();
    if ($editStudent !== null) {
        $formMode = 'edit';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $message = 'Your form session expired. Reload the page and try again.';
        $messageType = 'error';
        $action = '';
    }

    if ($action === 'save_student') {
        $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $yearOfStudy = trim((string) ($_POST['year_of_study'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $cgpa = trim((string) ($_POST['cgpa'] ?? ''));

        if ($fullName === '' || $email === '' || $courseId <= 0 || $yearOfStudy === '' || $city === '' || $cgpa === '') {
            $message = 'Please complete all student fields before saving.';
            $messageType = 'error';
        } else {
            $cgpaValue = (float) $cgpa;
            if ($cgpaValue < 0 || $cgpaValue > 10) {
                $message = 'CGPA must be between 0 and 10.';
                $messageType = 'error';
            } else {
                $existingStmt = $conn->prepare('SELECT student_id FROM students WHERE email = ? AND student_id != ? LIMIT 1');
                $existingStmt->bind_param('si', $email, $studentId);
                $existingStmt->execute();
                $existingResult = $existingStmt->get_result();
                if ($existingResult->fetch_assoc() !== null) {
                    $message = 'A student with this email already exists.';
                    $messageType = 'error';
                } else {
                    if ($studentId > 0) {
                        $stmt = $conn->prepare('UPDATE students SET full_name = ?, email = ?, course_id = ?, year_of_study = ?, city = ?, cgpa = ? WHERE student_id = ?');
                        $stmt->bind_param('ssissdi', $fullName, $email, $courseId, $yearOfStudy, $city, $cgpaValue, $studentId);
                        $stmt->execute();
                        $message = 'Student details updated successfully.';
                        $auditSaved = recordStudentAudit(
                            $conn,
                            (string) ($_SESSION['student_email'] ?? ''),
                            'STUDENT_UPDATED',
                            $studentId,
                            ['full_name' => $fullName, 'email' => $email]
                        );
                    } else {
                        $stmt = $conn->prepare('INSERT INTO students (full_name, email, course_id, year_of_study, city, cgpa) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->bind_param('ssissd', $fullName, $email, $courseId, $yearOfStudy, $city, $cgpaValue);
                        $stmt->execute();
                        $studentId = (int) $conn->insert_id;
                        $message = 'Student record created successfully.';
                        $auditSaved = recordStudentAudit(
                            $conn,
                            (string) ($_SESSION['student_email'] ?? ''),
                            'STUDENT_CREATED',
                            $studentId,
                            ['full_name' => $fullName, 'email' => $email]
                        );
                    }
                    if (!$auditSaved) {
                        $message .= ' The student record was saved, but the audit log could not be written.';
                        $messageType = 'error';
                    } else {
                        $messageType = 'success';
                    }
                    $formMode = 'create';
                    $editStudent = null;
                }
                $existingStmt->close();
            }
        }
    }

    if ($action === 'delete_student') {
        $deleteId = (int) ($_POST['student_id'] ?? 0);
        if ($deleteId > 0) {
            $stmt = $conn->prepare('DELETE FROM students WHERE student_id = ?');
            $stmt->bind_param('i', $deleteId);
            $stmt->execute();
            if ($stmt->affected_rows > 0 && recordStudentAudit(
                $conn,
                (string) ($_SESSION['student_email'] ?? ''),
                'STUDENT_DELETED',
                $deleteId,
                []
            )) {
                $message = 'Student record deleted successfully.';
                $messageType = 'success';
            } elseif ($stmt->affected_rows > 0) {
                $message = 'The student was deleted, but the audit log could not be written.';
                $messageType = 'error';
            } else {
                $message = 'The student record was not found.';
                $messageType = 'error';
            }
        } else {
            $message = 'No student was selected for deletion.';
            $messageType = 'error';
        }
    }
}

$coursesStmt = $conn->prepare('SELECT course_id, course_code, course_name FROM courses ORDER BY course_code ASC');
$coursesStmt->execute();
$coursesResult = $coursesStmt->get_result();
$courses = $coursesResult->fetch_all(MYSQLI_ASSOC);
$coursesStmt->close();

$conditions = []; $params = []; $types = '';
if ($searchTerm !== '') {
    $conditions[] = '(s.full_name LIKE ? OR s.email LIKE ? OR c.course_name LIKE ? OR c.course_code LIKE ?)';
    $searchLike = '%' . $searchTerm . '%';
    $params[] = $searchLike; $params[] = $searchLike; $params[] = $searchLike; $params[] = $searchLike;
    $types .= 'ssss';
}
if ($courseFilter !== '') {
    $conditions[] = 's.course_id = ?';
    $params[] = (int) $courseFilter;
    $types .= 'i';
}
if ($yearFilter !== '') {
    $conditions[] = 's.year_of_study = ?';
    $params[] = $yearFilter;
    $types .= 's';
}

$query = 'SELECT s.student_id, s.full_name, s.email, s.course_id, s.year_of_study, s.city, s.cgpa, c.course_code, c.course_name FROM students s INNER JOIN courses c ON c.course_id = s.course_id';
if ($conditions !== []) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}
$query .= ' ORDER BY s.full_name ASC';

$studentQuery = $conn->prepare($query);
if ($params !== []) {
    bindPreparedStatement($studentQuery, $types, $params);
}
$studentQuery->execute();
$studentResult = $studentQuery->get_result();
$students = $studentResult->fetch_all(MYSQLI_ASSOC);
$studentQuery->close();

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <link rel="stylesheet" href="../CSS Files/StudentManagement.css">
</head>
<body>
    <div class="page-shell">
        <div class="topbar">
            <h1 class="page-title">Student Management</h1>
            <div class="top-actions">
                <a class="btn secondary" href="../AdminDashboard.php">Back to Dashboard</a>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="status <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo escapeText($message); ?></div>
        <?php endif; ?>

        <section class="panel student-form-panel">
            <div class="panel-head"><?php echo $formMode === 'edit' ? 'Edit Student' : 'Add New Student'; ?></div>
            <div class="panel-body">
                <form method="POST" action="student_management.php">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_student">
                    <?php if ($formMode === 'edit' && $editStudent !== null): ?>
                        <input type="hidden" name="student_id" value="<?php echo (int) $editStudent['student_id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="student_id" value="0">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="field">
                            <label for="full_name">Full Name</label>
                            <input id="full_name" name="full_name" type="text" value="<?php echo escapeText($editStudent['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="<?php echo escapeText($editStudent['email'] ?? ''); ?>" required>
                        </div>

                        <div class="field">
                            <label for="course_id">Course</label>
                            <select id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo (int) $course['course_id']; ?>" <?php echo (($editStudent['course_id'] ?? '') == $course['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo escapeText($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="year_of_study">Year</label>
                            <select id="year_of_study" name="year_of_study" required>
                                <option value="">Select Year</option>
                                <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $yearOption): ?>
                                    <option value="<?php echo escapeText($yearOption); ?>" <?php echo (($editStudent['year_of_study'] ?? '') === $yearOption ? 'selected' : ''); ?>>
                                        <?php echo escapeText($yearOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="city">City</label>
                            <input id="city" name="city" type="text" value="<?php echo escapeText($editStudent['city'] ?? ''); ?>" required>
                        </div>

                        <div class="field">
                            <label for="cgpa">CGPA</label>
                            <input id="cgpa" name="cgpa" type="number" min="0" max="10" step="0.01" value="<?php echo escapeText($editStudent['cgpa'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="action-row">
                        <?php if ($formMode === 'edit' && $editStudent !== null): ?>
                            <a class="btn secondary" href="student_management.php">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn primary"><?php echo $formMode === 'edit' ? 'Update Student' : 'Add Student'; ?></button>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">Search & Filter Student Records</div>
            <div class="panel-body">
                <form method="GET" action="student_management.php" class="toolbar">
                    <div class="search-box field">
                        <label for="q">Search</label>
                        <input id="q" name="q" type="text" value="<?php echo escapeText($searchTerm); ?>" placeholder="Name, email or course">
                    </div>

                    <div class="field">
                        <label for="course">Course Filter</label>
                        <select id="course" name="course">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int) $course['course_id']; ?>" <?php echo ($courseFilter === (string) $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo escapeText($course['course_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="year">Year Filter</label>
                        <select id="year" name="year">
                            <option value="">All Years</option>
                            <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $yearOption): ?>
                                <option value="<?php echo escapeText($yearOption); ?>" <?php echo ($yearFilter === $yearOption) ? 'selected' : ''; ?>>
                                    <?php echo escapeText($yearOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn primary">Apply Filters</button>
                    <a href="student_management.php" class="btn secondary">Reset</a>
                </form>

                <?php if (count($students) === 0): ?>
                    <div class="empty-state">No student records matched the current criteria.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Course</th>
                                    <th>Year</th>
                                    <th>City</th>
                                    <th>CGPA</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo (int) $student['student_id']; ?></td>
                                        <td><?php echo escapeText($student['full_name']); ?></td>
                                        <td><?php echo escapeText($student['email']); ?></td>
                                        <td><?php echo escapeText($student['course_code'] . ' - ' . $student['course_name']); ?></td>
                                        <td><?php echo escapeText($student['year_of_study']); ?></td>
                                        <td><?php echo escapeText($student['city']); ?></td>
                                        <td><?php echo number_format((float) $student['cgpa'], 2); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="tiny-btn" href="student_management.php?edit=<?php echo (int) $student['student_id']; ?>">Edit</a>
                                                <form method="POST" action="student_management.php" data-delete-form>
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete_student">
                                                    <input type="hidden" name="student_id" value="<?php echo (int) $student['student_id']; ?>">
                                                    <button type="submit" class="tiny-btn danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script src="../JavaScript Files/StudentManagement.js" defer></script>
</body>
</html>
