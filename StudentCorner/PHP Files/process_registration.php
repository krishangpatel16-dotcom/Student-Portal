<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errors[] = 'Please submit the registration form first.';
} else {
    $fullname = trim(strip_tags($_POST['fullname'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');
    $course = trim((string) ($_POST['course'] ?? ''));
    $year = trim((string) ($_POST['year'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+){1,4}$/', $fullname)) {
        $errors[] = 'Enter a valid full name with at least two words.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors[] = 'Enter a valid 10-digit mobile number starting with 6 to 9.';
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@$!%*?&])[A-Za-z0-9@$!%*?&]{8,}$/', $password)) {
        $errors[] = 'Password must have 8 characters, uppercase and lowercase letters, a number, and a symbol.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'The passwords do not match.';
    }

    if (!in_array($course, ['BCA', 'BSc', 'BTech', 'MBA'], true)) {
        $errors[] = 'Select a valid course.';
    }

    if (!in_array($year, ['1st Year', '2nd Year', '3rd Year', '4th Year'], true)) {
        $errors[] = 'Select a valid year.';
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Choose a gender option.';
    }

    if (!isset($_POST['terms'])) {
        $errors[] = 'Accept the terms and conditions to continue.';
    }

    if (count($errors) === 0) {
        $dataFile = __DIR__ . '/registrations.json';
        $registrations = [];

        if (file_exists($dataFile)) {
            $savedData = file_get_contents($dataFile);
            if ($savedData !== false) {
                $decoded = json_decode($savedData, true);
                if (is_array($decoded)) {
                    $registrations = $decoded;
                } else {
                    $errors[] = 'The registration file could not be read.';
                }
            } else {
                $errors[] = 'The registration file could not be read.';
            }
        }

        if (count($errors) === 0) {
            foreach ($registrations as $registration) {
                if (strtolower((string) ($registration['email'] ?? '')) === strtolower($email)) {
                    $errors[] = 'An account with this email address is already registered.';
                    break;
                }
            }
        }

        if (count($errors) === 0) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $registeredAt = date('Y-m-d H:i:s');
            $dbSaved = false;

            try {
                $checkQuery = 'SELECT 1 FROM user_accounts WHERE email = :email LIMIT 1';
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([':email' => strtolower($email)]);

                if ($checkStmt->fetchColumn() !== false) {
                    $errors[] = 'An account with this email address is already registered.';
                } else {
                    $insertQuery = 'INSERT INTO user_accounts (full_name, email, mobile, course, year_of_study, gender, role, password_hash, remember_token_hash, created_at) VALUES (:full_name, :email, :mobile, :course, :year_of_study, :gender, :role, :password_hash, NULL, :created_at)';
                    $insertStmt = $pdo->prepare($insertQuery);
                    $insertStmt->execute([
                        ':full_name' => $fullname,
                        ':email' => strtolower($email),
                        ':mobile' => $mobile,
                        ':course' => $course,
                        ':year_of_study' => $year,
                        ':gender' => $gender,
                        ':role' => 'student',
                        ':password_hash' => $passwordHash,
                        ':created_at' => $registeredAt,
                    ]);
                    $dbSaved = true;
                }
            } catch (PDOException $exception) {
                error_log('Database registration failed: ' . $exception->getMessage());
                $dbSaved = false;
            }

            if (count($errors) === 0) {
                $registrations[] = [
                    'fullname' => $fullname,
                    'email' => $email,
                    'mobile' => $mobile,
                    'course' => $course,
                    'year' => $year,
                    'gender' => $gender,
                    'role' => 'student',
                    'student_id' => '',
                    'date_of_birth' => '',
                    'department' => '',
                    'semester' => '',
                    'division' => '',
                    'address' => '',
                    'results' => [
                        'sem1' => [
                            'cgpa' => '',
                            'sgpa' => '',
                            'creditsEarned' => '',
                            'creditsTotal' => '',
                            'academicProgress' => '',
                            'subjects' => []
                        ],
                        'sem2' => [
                            'cgpa' => '',
                            'sgpa' => '',
                            'creditsEarned' => '',
                            'creditsTotal' => '',
                            'academicProgress' => '',
                            'subjects' => []
                        ],
                        'sem3' => [
                            'cgpa' => '',
                            'sgpa' => '',
                            'creditsEarned' => '',
                            'creditsTotal' => '',
                            'academicProgress' => '',
                            'subjects' => []
                        ]
                    ],
                    'password_hash' => $passwordHash,
                    'registered_at' => $registeredAt,
                ];

                $savedData = json_encode($registrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($savedData === false || file_put_contents($dataFile, $savedData, LOCK_EX) === false) {
                    $errors[] = 'The registration could not be saved. Check the folder permissions.';
                }
            }

            if (count($errors) === 0) {
                session_start();
                session_regenerate_id(true);
                $_SESSION['student_email'] = strtolower($email);
                $safeName = urlencode($fullname);
                header('Location: ../HomePage.html?name=' . $safeName);
                exit;
            }
        }
    }
}

function escapeText($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Status</title>
    <link rel="stylesheet" href="../CSS%20Files/Register.css">
    <style>
        .status-message { line-height: 1.6; margin-bottom: 1rem; }
        .status-message.success { color: #86efac; }
        .status-message.error { color: #fca5a5; }
        .result-link { color: #93c5fd; display: inline-block; margin: 0.5rem 0.75rem 0 0; }
    </style>
</head>
<body>
    <main class="register-page">
        <section class="register-intro">
            <a class="brand" href="../HomePage.html"><span class="brand-mark">SP</span><span>Student Portal</span></a>
            <div class="intro-copy">
                <p class="eyebrow">Student Portal</p>
                <h1>Registration</h1>
                <p class="intro-text">Your registration details are processed and saved on the server.</p>
            </div>
            <div class="intro-footer"><span class="footer-line"></span><span>Learn. Grow. Achieve.</span></div>
        </section>
        <section class="register-panel">
            <div class="register-card">
                <p class="eyebrow">Form status</p>
                <h1 class="register-title"><?php echo $message ? 'Thank you!' : 'Please check your form'; ?></h1>
                <?php if ($message): ?>
                    <p class="status-message success"><?php echo escapeText($message); ?></p>
                <?php else: ?>
                    <div class="status-message error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo escapeText($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="result-link" href="../Register.html">Back to registration</a>
                <a class="result-link" href="view_registrations.php">View saved registrations</a>
                <a class="result-link" href="../HomePage.html">Go to portal home</a>
            </div>
        </section>
    </main>
</body>
</html>