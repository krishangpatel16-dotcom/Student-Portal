<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Submit your email and password to sign in.'], 405);
}

$request = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($request)) {
    sendJsonResponse(['error' => 'The sign-in request is invalid.'], 400);
}

$email = trim((string) ($request['email'] ?? ''));
$password = (string) ($request['password'] ?? '');
$remember = !empty($request['remember']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    sendJsonResponse(['error' => 'Enter a valid email address and password.'], 422);
}

$student = null;
$databaseStudentFound = false;

try {
    require_once __DIR__ . '/db.php';

    $statement = $pdo->prepare('SELECT user_id, full_name, email, mobile, course, year_of_study, gender, role, is_active, password_hash FROM user_accounts WHERE email = :email LIMIT 1');
    $statement->execute([':email' => strtolower($email)]);
    $dbStudent = $statement->fetch();

    if (is_array($dbStudent)) {
        $databaseStudentFound = true;
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
} catch (PDOException $error) {
    error_log($error->getMessage());
    sendJsonResponse(['error' => 'Sign-in is temporarily unavailable. Please try again later.'], 500);
}

if ($student === null || (int) ($student['is_active'] ?? 1) !== 1 || !password_verify($password, (string) ($student['password_hash'] ?? ''))) {
    sendJsonResponse(['error' => 'The email address or password is incorrect.'], 401);
}

$role = in_array(strtolower((string) ($student['role'] ?? 'student')), ['student', 'admin'], true)
    ? strtolower((string) ($student['role'] ?? 'student'))
    : 'student';

startSecureSession();
session_regenerate_id(true);
$_SESSION['student_email'] = strtolower($email);
$_SESSION['student_name'] = (string) ($student['fullname'] ?? 'Student');
$_SESSION['role'] = $role;
$_SESSION['last_activity'] = time();
$_SESSION['expires_at'] = time() + 900;

if ($remember && $databaseStudentFound) {
    $token = generateRememberToken();
    $_SESSION['remember_token'] = $token;
    setRememberMeCookie($token, 30);

    try {
        $rememberStatement = $pdo->prepare('UPDATE user_accounts SET remember_token_hash = :remember_token_hash WHERE email = :email');
        $rememberStatement->execute([
            ':remember_token_hash' => hashRememberToken($token),
            ':email' => strtolower($email)
        ]);
    } catch (PDOException $error) {
        error_log($error->getMessage());
    }
}

$redirect = $role === 'admin' ? 'AdminDashboard.php' : 'HomePage.html';
$student['role'] = $role;

sendJsonResponse([
    'student' => studentResponseData($student),
    'redirect' => $redirect
]);
