<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';

startSecureSession();
if (isSessionExpired()) {
    destroySession();
    header('Location: ../LoginHome.html');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Location: ../HomePage.html');
    exit;
}
refreshSessionLifetime();

$registrations = [];
$error = '';

try {
    require_once __DIR__ . '/db.php';
    $statement = $pdo->query('SELECT full_name AS fullname, email, mobile, course, year_of_study AS year, gender, created_at AS registered_at FROM user_accounts ORDER BY created_at DESC');
    $registrations = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $registrations = [];

    $dataFile = __DIR__ . '/registrations.json';
    if (file_exists($dataFile)) {
        $savedData = file_get_contents($dataFile);
        $decoded = json_decode($savedData ?: '[]', true);
        if (is_array($decoded)) {
            $registrations = $decoded;
        } else {
            $error = 'The registration file could not be read.';
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
    <title>Saved Registrations</title>
    <link rel="stylesheet" href="../CSS%20Files/Register.css">
    <style>
        .records-table { border-collapse: collapse; margin: 1rem 0; width: 100%; }
        .records-table th, .records-table td { border-bottom: 1px solid rgba(255,255,255,.16); padding: .7rem .5rem; text-align: left; }
        .records-table th { color: #93c5fd; }
        .table-wrap { overflow-x: auto; }
        .record-message { color: #cbd5e1; line-height: 1.6; margin: 1rem 0; }
        .result-link { color: #93c5fd; display: inline-block; margin: .5rem .75rem 0 0; }
    </style>
</head>
<body>
    <main class="register-page">
        <section class="register-intro">
            <a class="brand" href="../HomePage.html"><span class="brand-mark">SP</span><span>Student Portal</span></a>
            <div class="intro-copy">
                <p class="eyebrow">Student Portal</p>
                <h1>Saved Registrations</h1>
                <p class="intro-text">Registration records stored in the secured student database or backed up in JSON.</p>
            </div>
            <div class="intro-footer"><span class="footer-line"></span><span>Learn. Grow. Achieve.</span></div>
        </section>
        <section class="register-panel">
            <div class="register-card">
                <p class="eyebrow">Registration data</p>
                <h1 class="register-title">Records (<?php echo count($registrations); ?>)</h1>
                <?php if ($error): ?>
                    <p class="record-message"><?php echo escapeText($error); ?></p>
                <?php elseif (count($registrations) === 0): ?>
                    <p class="record-message">No registrations have been saved yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="records-table">
                            <thead>
                                <tr><th>Name</th><th>Email</th><th>Mobile</th><th>Course</th><th>Year</th><th>Gender</th><th>Registered</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $registration): ?>
                                    <tr>
                                        <td><?php echo escapeText($registration['fullname'] ?? ''); ?></td>
                                        <td><?php echo escapeText($registration['email'] ?? ''); ?></td>
                                        <td><?php echo escapeText($registration['mobile'] ?? ''); ?></td>
                                        <td><?php echo escapeText($registration['course'] ?? ''); ?></td>
                                        <td><?php echo escapeText($registration['year'] ?? ''); ?></td>
                                        <td><?php echo escapeText($registration['gender'] ?? ''); ?></td>
                                        <td><?php echo escapeText($registration['registered_at'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <a class="result-link" href="../Register.html">Back to registration</a>
                <a class="result-link" href="../HomePage.html">Go to portal home</a>
            </div>
        </section>
    </main>
</body>
</html>