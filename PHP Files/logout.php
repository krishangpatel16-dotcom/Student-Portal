<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';

startSecureSession();
$email = strtolower((string) ($_SESSION['student_email'] ?? ''));

if ($email !== '') {
    try {
        require_once __DIR__ . '/db.php';

        $statement = $pdo->prepare('UPDATE user_accounts SET remember_token_hash = NULL WHERE email = :email');
        $statement->execute([':email' => $email]);
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
    }
}

destroySession();
$expired = ($_GET['reason'] ?? '') === 'timeout';
header('Location: ../LoginHome.html' . ($expired ? '?expired=1' : ''));
exit;
