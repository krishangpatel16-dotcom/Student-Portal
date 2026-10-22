<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth_helpers.php';

header('Cache-Control: no-store');
startSecureSession();

function feedbackResponse(array $data, int $status = 200): void
{
    sendJsonResponse($data, $status);
}

function requireFeedbackUser(PDO $pdo): array
{
    $email = strtolower(trim((string) ($_SESSION['student_email'] ?? '')));
    if ($email === '' || isSessionExpired()) {
        destroySession();
        feedbackResponse(['error' => 'Sign in to submit feedback.'], 401);
    }

    refreshSessionLifetime();
    $statement = $pdo->prepare(
        'SELECT user_id, full_name, email FROM user_accounts WHERE email = :email AND is_active = 1 LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    if (!is_array($user)) {
        destroySession();
        feedbackResponse(['error' => 'Your account could not be verified. Sign in again.'], 401);
    }

    return $user;
}

try {
    require_once __DIR__ . '/../db.php';
    $user = requireFeedbackUser($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['feedback_csrf'])) {
            $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
        }
        feedbackResponse([
            'name' => (string) $user['full_name'],
            'email' => (string) $user['email'],
            'csrfToken' => (string) $_SESSION['feedback_csrf'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        feedbackResponse(['error' => 'Use GET to load the feedback form or POST to submit feedback.'], 405);
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['feedback_csrf']) || !hash_equals((string) $_SESSION['feedback_csrf'], $token)) {
        feedbackResponse(['error' => 'Your form session expired. Reload and try again.'], 419);
    }

    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($subject === '' || strlen($subject) > 160 || $message === '' || strlen($message) > 5000) {
        feedbackResponse(['error' => 'Enter a subject and a message no longer than 5,000 characters.'], 422);
    }

    $insert = $pdo->prepare(
        'INSERT INTO feedback_submissions (user_id, name, email, subject, message)
         VALUES (:user_id, :name, :email, :subject, :message)'
    );
    $insert->execute([
        'user_id' => (int) $user['user_id'],
        'name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'subject' => $subject,
        'message' => $message,
    ]);

    feedbackResponse([
        'message' => 'Thank you. Your feedback has been saved.',
        'name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
    ], 201);
} catch (PDOException $exception) {
    error_log('Feedback submission failed: ' . $exception->getMessage());
    feedbackResponse(['error' => 'Feedback could not be saved. Check the database connection and try again.'], 500);
}