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
$actorEmail = (string) $_SESSION['student_email'];

if ($action === 'create_admin') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || strlen($fullName) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        dashboardApiResponse(['error' => 'Enter a valid administrator name and email address.'], 422);
    }
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@$!%*?&])[A-Za-z0-9@$!%*?&]{8,64}$/', $password)) {
        dashboardApiResponse(['error' => 'Password must be 8–64 characters with uppercase, lowercase, a number, and a supported symbol.'], 422);
    }
    if (!hash_equals($password, $confirmPassword)) {
        dashboardApiResponse(['error' => 'The administrator passwords do not match.'], 422);
    }

    try {
        $pdo->beginTransaction();
        $duplicateCheck = $pdo->prepare('SELECT user_id FROM user_accounts WHERE email = :email LIMIT 1');
        $duplicateCheck->execute(['email' => $email]);
        if ($duplicateCheck->fetchColumn() !== false) {
            $pdo->rollBack();
            dashboardApiResponse(['error' => 'An account with this email already exists.'], 409);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare(
            "INSERT INTO user_accounts
                (full_name, email, mobile, course, year_of_study, gender, role, is_active, password_hash)
             VALUES
                (:full_name, :email, NULL, NULL, NULL, NULL, 'admin', 1, :password_hash)"
        );
        $insert->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
        $adminId = (int) $pdo->lastInsertId();

        $audit = $pdo->prepare(
            'INSERT INTO audit_logs (actor_email, action_type, entity_type, entity_id, details)
             VALUES (:actor, :action_type, :entity, :entity_id, :details)'
        );
        $audit->execute([
            'actor' => $actorEmail,
            'action_type' => 'ADMIN_CREATED',
            'entity' => 'user_account',
            'entity_id' => $adminId,
            'details' => json_encode([
                'full_name' => $fullName,
                'email' => $email,
                'role' => 'admin',
            ], JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();

        dashboardApiResponse([
            'message' => 'Administrator account created successfully. They can sign in from the portal login page.',
            'admin' => [
                'userId' => $adminId,
                'fullName' => $fullName,
                'email' => $email,
                'role' => 'admin',
            ],
        ], 201);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            dashboardApiResponse(['error' => 'An account with this email already exists.'], 409);
        }
        error_log('Administrator account creation failed: ' . $exception->getMessage());
        dashboardApiResponse(['error' => 'The administrator account could not be created. Check the database and try again.'], 500);
    } catch (JsonException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Administrator audit details could not be encoded: ' . $exception->getMessage());
        dashboardApiResponse(['error' => 'The administrator audit entry could not be saved.'], 500);
    }
}

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
        'actor' => $actorEmail,
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
