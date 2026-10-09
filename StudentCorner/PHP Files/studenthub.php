<?php
declare(strict_types=1);

session_start();

function escapeText(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_SESSION['studenthub_csrf'])) {
    $_SESSION['studenthub_csrf'] = bin2hex(random_bytes(32));
}

$statusMessage = '';
$statusType = 'success';
$students = [];
$events = [];
$registrations = [];
$databaseError = false;

try {
    require_once __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals($_SESSION['studenthub_csrf'], $submittedToken)) {
            $statusMessage = 'Your form session expired. Reload the page and try again.';
            $statusType = 'error';
        } else {
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($studentId === false || $eventId === false) {
                $statusMessage = 'Choose a valid student and event.';
                $statusType = 'error';
            } else {
                $studentCheck = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :student_id');
                $studentCheck->execute(['student_id' => $studentId]);
                $eventCheck = $pdo->prepare("SELECT event_id FROM events WHERE event_id = :event_id AND event_status = 'Open'");
                $eventCheck->execute(['event_id' => $eventId]);

                if (!$studentCheck->fetch() || !$eventCheck->fetch()) {
                    $statusMessage = 'The selected student or event is no longer available.';
                    $statusType = 'error';
                } else {
                    $insertRegistration = $pdo->prepare(
                        'INSERT INTO registrations (student_id, event_id) VALUES (:student_id, :event_id)'
                    );

                    try {
                        $insertRegistration->execute([
                            'student_id' => $studentId,
                            'event_id' => $eventId,
                        ]);
                        $statusMessage = 'Event registration saved successfully.';
                    } catch (PDOException $exception) {
                        if ($exception->getCode() !== '23000') {
                            throw $exception;
                        }

                        $statusMessage = 'This student is already registered for that event.';
                        $statusType = 'error';
                    }
                }
            }
        }
    }

    $studentsStatement = $pdo->prepare(
        'SELECT s.student_id, s.full_name, c.course_code AS course, s.year_of_study
         FROM students AS s
         INNER JOIN courses AS c ON c.course_id = s.course_id
         ORDER BY s.full_name'
    );
    $studentsStatement->execute();
    $students = $studentsStatement->fetchAll();

    $eventsStatement = $pdo->prepare(
        "SELECT event_id, title, event_date, venue, category FROM events WHERE event_status = 'Open' ORDER BY event_date, title"
    );
    $eventsStatement->execute();
    $events = $eventsStatement->fetchAll();

    $registrationsStatement = $pdo->prepare(
        'SELECT s.full_name, c.course_code AS course, e.title, e.event_date, r.registered_at
         FROM registrations AS r
         INNER JOIN students AS s ON s.student_id = r.student_id
         INNER JOIN courses AS c ON c.course_id = s.course_id
         INNER JOIN events AS e ON e.event_id = r.event_id
         ORDER BY r.registered_at DESC, s.full_name'
    );
    $registrationsStatement->execute();
    $registrations = $registrationsStatement->fetchAll();
} catch (PDOException $exception) {
    $databaseError = true;
    $statusMessage = 'Could not load StudentHub data. Import the SQL setup files and check the MySQL connection settings.';
    $statusType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Events &amp; Registrations</title>
    <link rel="stylesheet" href="../CSS%20Files/HomePage.css">
    <link rel="stylesheet" href="../CSS%20Files/Sidebar.css">
    <link rel="stylesheet" href="../CSS%20Files/StudentHub.css">
</head>
<body>
    <div class="portal-layout">
        <aside class="sidebar" aria-label="Sidebar navigation">
            <a class="logo studenthub-logo" href="../HomePage.html">
                <span class="logo-icon" aria-hidden="true">🎓</span>
                <span class="logo-text"><span class="logo-main">Student</span><span class="logo-sub">Portal</span></span>
            </a>
            <nav class="side-menu" aria-label="Main navigation">
                <a href="../HomePage.html" class="menu-item"><span class="menu-icon"><img src="../Images/Icons/house.svg" alt=""></span><span>Home</span></a>
                <a href="../Events.html" class="menu-item"><span class="menu-icon"><img src="../Images/Icons/sparkles.svg" alt=""></span><span>Events</span></a>
                <a href="studenthub.php" class="menu-item active" aria-current="page"><span class="menu-icon"><img src="../Images/Icons/layout-dashboard.svg" alt=""></span><span>StudentHub DB</span></a>
                <a href="../LoginHome.html" class="menu-item logout"><span class="logout-icon"><img src="../Images/Icons/log-out.svg" alt=""></span><span>Log out</span></a>
            </nav>
        </aside>

        <main class="main-panel studenthub-main">
            <header class="studenthub-heading">
                <p class="studenthub-eyebrow">MYSQL • PDO • PREPARED STATEMENTS</p>
                <h1>StudentHub</h1>
                <p>Explore campus events and manage student registrations in one place.</p>
            </header>

            <?php if ($statusMessage !== ''): ?>
                <div class="studenthub-status <?php echo escapeText($statusType); ?>" role="status">
                    <?php echo escapeText($statusMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($databaseError): ?>
                <section class="studenthub-panel setup-panel">
                    <h2>Connect the database</h2>
                    <ol>
                        <li>In phpMyAdmin, import <code>MySQL Files/schema.sql</code>, then <code>MySQL Files/seed.sql</code>.</li>
                        <li>Set <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASSWORD</code> if your local MySQL settings differ.</li>
                        <li>Reload this page after MySQL is running.</li>
                    </ol>
                </section>
            <?php else: ?>
                <div class="studenthub-stats" aria-label="Database totals">
                    <article class="stat-card"><span>Students</span><strong><?php echo count($students); ?></strong></article>
                    <article class="stat-card"><span>Events</span><strong><?php echo count($events); ?></strong></article>
                    <article class="stat-card"><span>Registrations</span><strong><?php echo count($registrations); ?></strong></article>
                </div>

                <section class="studenthub-panel" aria-labelledby="register-event-heading">
                    <div class="panel-heading">
                        <div><p class="studenthub-eyebrow">Campus activities</p><h2 id="register-event-heading">Register a student</h2></div>
                        <span class="panel-badge">Prepared insert</span>
                    </div>
                    <?php if (!$students || !$events): ?>
                        <p class="empty-message">Add at least one student and one event using the SQL seed file before registering.</p>
                    <?php else: ?>
                        <form class="registration-form" method="post" action="studenthub.php">
                            <input type="hidden" name="csrf_token" value="<?php echo escapeText($_SESSION['studenthub_csrf']); ?>">
                            <div class="form-field">
                                <label for="student_id">Student</label>
                                <select id="student_id" name="student_id" required>
                                    <option value="">Select a student</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo (int) $student['student_id']; ?>">
                                            <?php echo escapeText($student['full_name'] . ' · ' . $student['course'] . ', ' . $student['year_of_study']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="event_id">Event</label>
                                <select id="event_id" name="event_id" required>
                                    <option value="">Select an event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo (int) $event['event_id']; ?>">
                                            <?php echo escapeText($event['title'] . ' · ' . $event['event_date']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="studenthub-button">Save registration <span aria-hidden="true">→</span></button>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="studenthub-panel" aria-labelledby="events-heading">
                    <div class="panel-heading"><div><p class="studenthub-eyebrow">What’s happening</p><h2 id="events-heading">Events</h2></div><span class="panel-badge">MySQL table</span></div>
                    <?php if (!$events): ?>
                        <p class="empty-message">No events are available yet.</p>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="studenthub-table">
                                <thead><tr><th>Event</th><th>Date</th><th>Category</th><th>Venue</th></tr></thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><?php echo escapeText($event['title']); ?></td>
                                            <td><?php echo escapeText($event['event_date']); ?></td>
                                            <td><span class="category-tag"><?php echo escapeText($event['category']); ?></span></td>
                                            <td><?php echo escapeText($event['venue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="studenthub-panel" aria-labelledby="registrations-heading">
                    <div class="panel-heading"><div><p class="studenthub-eyebrow">Student sign-ups</p><h2 id="registrations-heading">Registrations</h2></div><span class="panel-badge">Joined records</span></div>
                    <?php if (!$registrations): ?>
                        <p class="empty-message">No event registrations yet. Use the form above to add one.</p>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="studenthub-table">
                                <thead><tr><th>Student</th><th>Course</th><th>Event</th><th>Event date</th><th>Registered at</th></tr></thead>
                                <tbody>
                                    <?php foreach ($registrations as $registration): ?>
                                        <tr>
                                            <td><?php echo escapeText($registration['full_name']); ?></td>
                                            <td><?php echo escapeText($registration['course']); ?></td>
                                            <td><?php echo escapeText($registration['title']); ?></td>
                                            <td><?php echo escapeText($registration['event_date']); ?></td>
                                            <td><?php echo escapeText($registration['registered_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
