<?php
declare(strict_types=1);

function sendJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    if (!isset($_SESSION['role'])) {
        $_SESSION['role'] = 'student';
    }
}

function refreshSessionLifetime(int $timeoutSeconds = 900): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['last_activity'] = time();
    $_SESSION['expires_at'] = time() + $timeoutSeconds;
}

function isSessionExpired(int $timeoutSeconds = 900): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }

    $lastActivity = $_SESSION['last_activity'] ?? time();
    return (time() - (int) $lastActivity) > $timeoutSeconds;
}

function clearRememberMeCookie(): void
{
    if (isset($_COOKIE['student_remember'])) {
        unset($_COOKIE['student_remember']);
    }

    setcookie('student_remember', '', time() - 3600, '/', '', false, true);
}

function setRememberMeCookie(string $token, int $days = 30): void
{
    $expiry = time() + ($days * 24 * 60 * 60);
    setcookie('student_remember', $token, $expiry, '/', '', false, true);
}

function generateRememberToken(): string
{
    return bin2hex(random_bytes(32));
}

function hashRememberToken(string $token): string
{
    return hash('sha256', $token);
}

function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookieParameters['path'],
            $cookieParameters['domain'],
            $cookieParameters['secure'],
            $cookieParameters['httponly']
        );
    }

    clearRememberMeCookie();
    session_destroy();
}

function studentResponseData(array $student): array
{
    $studentData = [];
    foreach ([
        'fullname',
        'email',
        'mobile',
        'course',
        'year',
        'gender',
        'student_id',
        'date_of_birth',
        'department',
        'semester',
        'division',
        'address',
        'role'
    ] as $optionalField) {
        $studentData[$optionalField] = (string) ($student[$optionalField] ?? (in_array($optionalField, ['role'], true) ? 'student' : ''));
    }

    $emptySemesterResults = [
        'cgpa' => '',
        'sgpa' => '',
        'creditsEarned' => '',
        'creditsTotal' => '',
        'academicProgress' => '',
        'subjects' => []
    ];
    $savedResults = is_array($student['results'] ?? null) ? $student['results'] : [];
    $studentData['results'] = [];
    foreach (['sem1', 'sem2', 'sem3', 'sem4', 'sem5', 'sem6', 'sem7', 'sem8'] as $semester) {
        $studentData['results'][$semester] = array_merge(
            $emptySemesterResults,
            is_array($savedResults[$semester] ?? null) ? $savedResults[$semester] : []
        );
    }

    return $studentData;
}
