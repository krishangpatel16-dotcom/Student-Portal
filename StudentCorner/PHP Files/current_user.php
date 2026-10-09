<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

startSecureSession();

if (isset($_SESSION['role']) && isSessionExpired()) {
    destroySession();
    sendJsonResponse(['error' => 'Your session expired. Please sign in again.'], 401);
}

$email = (string) ($_SESSION['student_email'] ?? '');
if ($email === '' && isset($_COOKIE['student_remember']) && $_COOKIE['student_remember'] !== '') {
    $rememberToken = (string) $_COOKIE['student_remember'];
    $rememberHash = hashRememberToken($rememberToken);

    try {
        $statement = $pdo->prepare('SELECT user_id, full_name, email, mobile, course, year_of_study, gender, role, password_hash FROM user_accounts WHERE remember_token_hash = :remember_hash LIMIT 1');
        $statement->execute([':remember_hash' => $rememberHash]);
        $user = $statement->fetch();

        if (is_array($user)) {
            $_SESSION['student_email'] = strtolower((string) ($user['email'] ?? ''));
            $_SESSION['student_name'] = (string) ($user['full_name'] ?? 'Student');
            $_SESSION['role'] = in_array(strtolower((string) ($user['role'] ?? 'student')), ['student', 'admin'], true)
                ? strtolower((string) ($user['role'] ?? 'student'))
                : 'student';
            $_SESSION['last_activity'] = time();
            $_SESSION['expires_at'] = time() + 900;
            $email = (string) $_SESSION['student_email'];
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
    }
}

if ($email === '') {
    destroySession();
    sendJsonResponse(['error' => 'Sign in to view your account.'], 401);
}

$student = null;

try {
    $statement = $pdo->prepare(
        'SELECT user_id, full_name, email, mobile, course, year_of_study, gender, role, is_active, password_hash
         FROM user_accounts WHERE email = :email LIMIT 1'
    );
    $statement->execute([':email' => strtolower($email)]);
    $dbStudent = $statement->fetch();

    if (is_array($dbStudent)) {
        $student = [
            'fullname' => $dbStudent['full_name'] ?? '',
            'email' => $dbStudent['email'] ?? '',
            'mobile' => $dbStudent['mobile'] ?? '',
            'course' => $dbStudent['course'] ?? '',
            'year' => $dbStudent['year_of_study'] ?? '',
            'gender' => $dbStudent['gender'] ?? '',
            'role' => $dbStudent['role'] ?? 'student',
            'is_active' => (int) ($dbStudent['is_active'] ?? 1),
            'student_id' => $dbStudent['user_id'] ?? '',
            'password_hash' => $dbStudent['password_hash'] ?? '',
        ];
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
}

if ($student !== null && (int) ($student['is_active'] ?? 1) !== 1) {
    destroySession();
    sendJsonResponse(['error' => 'Your account is inactive. Contact an administrator.'], 401);
}

if ($student === null) {
    try {
        $student = findStudentByEmail(loadStudentRegistrations(), $email);
        if (is_array($student) && !isset($student['role'])) {
            $student['role'] = 'student';
        }
    } catch (RuntimeException $error) {
        error_log($error->getMessage());
        sendJsonResponse(['error' => 'Your account information is temporarily unavailable.'], 500);
    }
}

if ($student === null) {
    unset($_SESSION['student_email']);
    destroySession();
    sendJsonResponse(['error' => 'Your account could not be found. Please sign in again.'], 401);
}

$_SESSION['student_email'] = strtolower($email);
$_SESSION['student_name'] = (string) ($student['fullname'] ?? 'Student');
$_SESSION['role'] = in_array(strtolower((string) ($student['role'] ?? 'student')), ['student', 'admin'], true)
    ? strtolower((string) ($student['role'] ?? 'student'))
    : 'student';
refreshSessionLifetime();

sendJsonResponse(['student' => studentResponseData($student + ['role' => $_SESSION['role']])]);
