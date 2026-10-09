<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

startSecureSession();
$email = strtolower((string) ($_SESSION['student_email'] ?? ''));

if ($email !== '') {
    try {
        $statement = $pdo->prepare('UPDATE user_accounts SET remember_token_hash = NULL WHERE email = :email');
        $statement->execute([':email' => $email]);
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
    }
}

destroySession();
header('Location: ../LoginHome.html');
exit;
