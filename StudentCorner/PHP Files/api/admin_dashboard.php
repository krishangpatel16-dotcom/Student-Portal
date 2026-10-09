<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth_helpers.php';

header('Cache-Control: no-store');
startSecureSession();

function dashboardApiResponse(array $data, int $status = 200): void
{
    sendJsonResponse($data, $status);
}

function requireDashboardAdmin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin' || (string) ($_SESSION['student_email'] ?? '') === '') {
        dashboardApiResponse(['error' => 'Administrator sign-in is required.'], 403);
    }
    if (isSessionExpired()) {
        destroySession();
        dashboardApiResponse(['error' => 'Your session expired. Sign in again.'], 401);
    }
    refreshSessionLifetime();
}

requireDashboardAdmin();
try {
    require_once __DIR__ . '/../db.php';
} catch (PDOException $exception) {
    error_log('Admin dashboard database connection failed: ' . $exception->getMessage());
    dashboardApiResponse(['error' => 'Dashboard data is unavailable. Check the database connection.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['admin_dashboard_csrf'])) {
        $_SESSION['admin_dashboard_csrf'] = bin2hex(random_bytes(32));
    }

    try {
        $stats = [
            'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
            'activeStudentAccounts' => (int) $pdo->query("SELECT COUNT(*) FROM user_accounts WHERE role = 'student' AND is_active = 1")->fetchColumn(),
            'events' => (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn(),
            'registrations' => (int) $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn(),
        ];

        $recentStatement = $pdo->query(
            'SELECT s.full_name, c.course_code, e.title AS event_title, r.registered_at
             FROM registrations AS r
             INNER JOIN students AS s ON s.student_id = r.student_id
             INNER JOIN courses AS c ON c.course_id = s.course_id
             INNER JOIN events AS e ON e.event_id = r.event_id
             ORDER BY r.registered_at DESC
             LIMIT 8'
        );

        $auditStatement = $pdo->query(
            'SELECT actor_email, action_type, entity_type, entity_id, details, created_at
             FROM audit_logs ORDER BY created_at DESC LIMIT 8'
        );

        $usersStatement = $pdo->query(
            "SELECT user_id, full_name, email, is_active
             FROM user_accounts WHERE role = 'student' ORDER BY full_name LIMIT 50"
        );

        dashboardApiResponse([
            'stats' => $stats,
            'recentRegistrations' => $recentStatement->fetchAll(),
            'auditLogs' => $auditStatement->fetchAll(),
            'studentAccounts' => $usersStatement->fetchAll(),
            'csrfToken' => (string) $_SESSION['admin_dashboard_csrf'],
        ]);
    } catch (PDOException $exception) {
        error_log('Admin dashboard data request failed: ' . $exception->getMessage());
        dashboardApiResponse(['error' => 'Dashboard data is unavailable. Check the schema migration and database connection.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    dashboardApiResponse(['error' => 'Use GET to load dashboard data or POST to update an account status.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || !isset($_SESSION['admin_dashboard_csrf']) || !hash_equals((string) $_SESSION['admin_dashboard_csrf'], $token)) {
    dashboardApiResponse(['error' => 'Your dashboard form token expired. Reload and try again.'], 419);
}

$action = (string) ($_POST['action'] ?? '');
if ($action !== 'set_account_status') {
    dashboardApiResponse(['error' => 'Choose a supported dashboard action.'], 422);
}

$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$activeValue = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
if ($userId === false || $activeValue === false) {
    dashboardApiResponse(['error' => 'The student account or status value is invalid.'], 422);
}

try {
    $pdo->beginTransaction();
    $accountStatement = $pdo->prepare(
        "SELECT user_id, full_name, email, is_active FROM user_accounts WHERE user_id = :user_id AND role = 'student' FOR UPDATE"
    );
    $accountStatement->execute(['user_id' => $userId]);
    $account = $accountStatement->fetch();
    if (!is_array($account)) {
        $pdo->rollBack();
        dashboardApiResponse(['error' => 'The student account was not found.'], 404);
    }

    $isActive = (int) $activeValue;
    $update = $pdo->prepare('UPDATE user_accounts SET is_active = :is_active WHERE user_id = :user_id');
    $update->execute(['is_active' => $isActive, 'user_id' => $userId]);

    $audit = $pdo->prepare(
        'INSERT INTO audit_logs (actor_email, action_type, entity_type, entity_id, details)
         VALUES (:actor, :action_type, :entity, :entity_id, :details)'
    );
    $audit->execute([
        'actor' => (string) $_SESSION['student_email'],
        'action_type' => $isActive === 1 ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
        'entity' => 'user_account',
        'entity_id' => (int) $userId,
        'details' => json_encode([
            'name' => (string) $account['full_name'],
            'email' => (string) $account['email'],
            'previous_status' => (int) $account['is_active'],
            'current_status' => $isActive,
        ], JSON_THROW_ON_ERROR),
    ]);
    $pdo->commit();

    dashboardApiResponse([
        'message' => (string) $account['full_name'] . ($isActive ? ' account activated.' : ' account deactivated.'),
        'user' => [
            'userId' => (int) $account['user_id'],
            'isActive' => $isActive === 1,
        ],
    ]);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin account status change failed: ' . $exception->getMessage());
    dashboardApiResponse(['error' => 'The account status could not be changed. Check the database and try again.'], 500);
} catch (JsonException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin audit details could not be encoded: ' . $exception->getMessage());
    dashboardApiResponse(['error' => 'The account status audit entry could not be saved.'], 500);
}
