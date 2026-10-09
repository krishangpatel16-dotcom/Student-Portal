<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth_helpers.php';

header('Cache-Control: no-store');
startSecureSession();
try {
    require_once __DIR__ . '/../db.php';
} catch (PDOException $exception) {
    error_log('Event API database connection failed: ' . $exception->getMessage());
    eventApiResponse(['error' => 'The event service is temporarily unavailable. Check the database connection.'], 500);
}

const EVENT_POSTER_LIMIT = 2097152;
const EVENT_POSTER_DIR = __DIR__ . '/../../Images/EventPosters';

function eventApiResponse(array $data, int $status = 200): void
{
    sendJsonResponse($data, $status);
}

function requireEventAdmin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin' || (string) ($_SESSION['student_email'] ?? '') === '') {
        eventApiResponse(['error' => 'Administrator sign-in is required.'], 403);
    }
    if (isSessionExpired()) {
        destroySession();
        eventApiResponse(['error' => 'Your session expired. Sign in again.'], 401);
    }
    refreshSessionLifetime();
}

function storeEventPoster(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new InvalidArgumentException('The poster upload failed. Choose the file again.');
    }

    $size = filesize((string) $file['tmp_name']);
    if ($size === false || $size < 1 || $size > EVENT_POSTER_LIMIT) {
        throw new InvalidArgumentException('Posters must be no larger than 2 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new InvalidArgumentException('Upload a valid JPEG, PNG, or WebP poster.');
    }
    $image = getimagesize((string) $file['tmp_name']);
    if ($image === false || ($image['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('The uploaded file is not a valid image.');
    }

    if (!is_dir(EVENT_POSTER_DIR) && !mkdir(EVENT_POSTER_DIR, 0755, true) && !is_dir(EVENT_POSTER_DIR)) {
        throw new RuntimeException('The poster directory could not be created.');
    }
    $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], EVENT_POSTER_DIR . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The poster could not be saved. Check directory permissions.');
    }
    return $filename;
}

function removeEventPoster(?string $filename): void
{
    if ($filename === null || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $filename)) {
        return;
    }
    $path = EVENT_POSTER_DIR . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Could not remove event poster: ' . $path);
    }
}

function recordEventAudit(PDO $pdo, int $eventId, string $action, string $actor, ?array $before, ?array $after): void
{
    $jsonBefore = $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR);
    $jsonAfter = $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR);

    $eventAudit = $pdo->prepare(
        'INSERT INTO event_audit_logs (event_id, action_type, actor_email, previous_data, current_data)
         VALUES (:event_id, :action_type, :actor, :before_data, :after_data)'
    );
    $eventAudit->execute([
        'event_id' => $eventId,
        'action_type' => $action,
        'actor' => $actor,
        'before_data' => $jsonBefore,
        'after_data' => $jsonAfter,
    ]);

    $audit = $pdo->prepare(
        'INSERT INTO audit_logs (actor_email, action_type, entity_type, entity_id, details)
         VALUES (:actor, :action_type, :entity_type, :entity_id, :details)'
    );
    $audit->execute([
        'actor' => $actor,
        'action_type' => $action,
        'entity_type' => 'event',
        'entity_id' => $eventId,
        'details' => json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR),
    ]);
}

function eventResponseRow(array $event): array
{
    $filename = (string) ($event['poster_path'] ?? '');
    return [
        'eventId' => (int) $event['event_id'],
        'title' => (string) $event['title'],
        'eventDate' => (string) $event['event_date'],
        'venue' => (string) $event['venue'],
        'category' => (string) $event['category'],
        'status' => (string) $event['event_status'],
        'posterUrl' => $filename !== '' ? '../Images/EventPosters/' . rawurlencode($filename) : null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $admin = ($_SESSION['role'] ?? '') === 'admin' && (string) ($_SESSION['student_email'] ?? '') !== '';
    if ($admin) {
        if (isSessionExpired()) {
            destroySession();
            eventApiResponse(['error' => 'Your session expired. Sign in again.'], 401);
        }
        refreshSessionLifetime();
    }

    try {
        $search = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $status = (string) ($_GET['status'] ?? '');
        if (strlen($search) > 100 || strlen($category) > 60) {
            eventApiResponse(['error' => 'Search text or category exceeds the allowed length.'], 422);
        }
        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = '(title LIKE :title_search OR venue LIKE :venue_search OR category LIKE :category_search)';
            $pattern = '%' . $search . '%';
            $parameters['title_search'] = $pattern;
            $parameters['venue_search'] = $pattern;
            $parameters['category_search'] = $pattern;
        }
        if ($category !== '') {
            $where[] = 'category = :category';
            $parameters['category'] = $category;
        }
        if (!$admin) {
            $where[] = "event_status = 'Open'";
        } elseif (in_array($status, ['Open', 'Closed'], true)) {
            $where[] = 'event_status = :status';
            $parameters['status'] = $status;
        }

        $sql = 'SELECT event_id, title, event_date, venue, category, poster_path, event_status FROM events';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY event_date DESC, event_id DESC LIMIT 200';
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        $events = array_map('eventResponseRow', $statement->fetchAll());

        $response = ['events' => $events];
        if ($admin) {
            if (empty($_SESSION['event_api_csrf'])) {
                $_SESSION['event_api_csrf'] = bin2hex(random_bytes(32));
            }
            $response['csrfToken'] = (string) $_SESSION['event_api_csrf'];
        }
        eventApiResponse($response);
    } catch (PDOException $exception) {
        error_log('Event API list failed: ' . $exception->getMessage());
        eventApiResponse(['error' => 'Events are temporarily unavailable. Verify the event schema and database connection.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    eventApiResponse(['error' => 'Use GET to list events or POST to modify an event.'], 405);
}

requireEventAdmin();
$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!is_string($submittedToken) || !isset($_SESSION['event_api_csrf']) || !hash_equals((string) $_SESSION['event_api_csrf'], $submittedToken)) {
    eventApiResponse(['error' => 'Your request token expired. Reload the event manager and try again.'], 419);
}

$action = (string) ($_POST['action'] ?? '');
$actor = (string) $_SESSION['student_email'];
$uploadedPoster = null;

try {
    if ($action === 'delete') {
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($eventId === false) {
            eventApiResponse(['error' => 'Select a valid event to delete.'], 422);
        }
        $find = $pdo->prepare('SELECT event_id, title, event_date, venue, category, poster_path, event_status FROM events WHERE event_id = :id');
        $find->execute(['id' => $eventId]);
        $before = $find->fetch();
        if (!is_array($before)) {
            eventApiResponse(['error' => 'The event was not found.'], 404);
        }

        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM events WHERE event_id = :id');
        $delete->execute(['id' => $eventId]);
        recordEventAudit($pdo, (int) $eventId, 'DELETE', $actor, $before, null);
        $pdo->commit();
        removeEventPoster(is_string($before['poster_path'] ?? null) ? $before['poster_path'] : null);
        eventApiResponse(['message' => 'Event deleted successfully.']);
    }

    if ($action !== 'create' && $action !== 'update') {
        eventApiResponse(['error' => 'Choose a valid event action.'], 422);
    }

    $eventId = filter_var($_POST['event_id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $title = trim((string) ($_POST['title'] ?? ''));
    $date = trim((string) ($_POST['event_date'] ?? ''));
    $venue = trim((string) ($_POST['venue'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $status = (string) ($_POST['event_status'] ?? '');

    if ($eventId === false || ($action === 'create' && $eventId !== 0) || ($action === 'update' && $eventId < 1)) {
        eventApiResponse(['error' => 'The event identifier is invalid.'], 422);
    }
    if ($title === '' || strlen($title) > 160 || $venue === '' || strlen($venue) > 160 || $category === '' || strlen($category) > 60) {
        eventApiResponse(['error' => 'Enter a title, venue, and category within the allowed lengths.'], 422);
    }
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
        eventApiResponse(['error' => 'Choose a valid event date.'], 422);
    }
    if (!in_array($status, ['Open', 'Closed'], true)) {
        eventApiResponse(['error' => 'Choose Open or Closed for the event status.'], 422);
    }

    $before = null;
    if ($action === 'update') {
        $find = $pdo->prepare('SELECT event_id, title, event_date, venue, category, poster_path, event_status FROM events WHERE event_id = :id');
        $find->execute(['id' => $eventId]);
        $before = $find->fetch();
        if (!is_array($before)) {
            eventApiResponse(['error' => 'The event was not found.'], 404);
        }
    }

    $uploadedPoster = storeEventPoster($_FILES['poster'] ?? []);
    $posterPath = $uploadedPoster ?? ($before['poster_path'] ?? null);
    $after = [
        'title' => $title,
        'event_date' => $date,
        'venue' => $venue,
        'category' => $category,
        'poster_path' => $posterPath,
        'event_status' => $status,
    ];

    $pdo->beginTransaction();
    if ($action === 'create') {
        $insert = $pdo->prepare(
            'INSERT INTO events (title, event_date, venue, category, poster_path, event_status)
             VALUES (:title, :event_date, :venue, :category, :poster_path, :event_status)'
        );
        $insert->execute($after);
        $eventId = (int) $pdo->lastInsertId();
        recordEventAudit($pdo, $eventId, 'CREATE', $actor, null, $after);
    } else {
        $update = $pdo->prepare(
            'UPDATE events SET title = :title, event_date = :event_date, venue = :venue,
             category = :category, poster_path = :poster_path, event_status = :event_status
             WHERE event_id = :event_id'
        );
        $update->execute($after + ['event_id' => $eventId]);
        recordEventAudit($pdo, (int) $eventId, 'UPDATE', $actor, $before, $after);
    }
    $pdo->commit();

    if ($uploadedPoster !== null && is_array($before)) {
        removeEventPoster(is_string($before['poster_path'] ?? null) ? $before['poster_path'] : null);
    }
    $after['event_id'] = $eventId;
    $after['event_status'] = $status;
    eventApiResponse([
        'message' => $action === 'create' ? 'Event created successfully.' : 'Event updated successfully.',
        'event' => eventResponseRow($after),
    ], $action === 'create' ? 201 : 200);
} catch (InvalidArgumentException $exception) {
    removeEventPoster($uploadedPoster);
    eventApiResponse(['error' => $exception->getMessage()], 422);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    removeEventPoster($uploadedPoster);
    error_log('Event API mutation failed: ' . $exception->getMessage());
    eventApiResponse(['error' => 'The event could not be saved. Verify the migration and try again.'], 500);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    removeEventPoster($uploadedPoster);
    error_log('Event API processing failed: ' . $exception->getMessage());
    eventApiResponse(['error' => 'The event request failed. Try again or contact the administrator.'], 500);
}
