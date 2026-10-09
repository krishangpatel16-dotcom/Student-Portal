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
    header('Location: ../LoginHome.html');
    exit;
}

refreshSessionLifetime();

const MAX_POSTER_BYTES = 2 * 1024 * 1024;
const POSTER_DIRECTORY = __DIR__ . '/../Images/EventPosters';

$databaseError = false;
try {
    require_once __DIR__ . '/db.php';
} catch (PDOException $exception) {
    error_log('Event management database connection failed: ' . $exception->getMessage());
    $databaseError = true;
}

function eventEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eventRedirectWithMessage(string $message, string $type): void
{
    $_SESSION['event_flash'] = ['message' => $message, 'type' => $type];
    header('Location: event_management.php');
    exit;
}

function validateAndStorePoster(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The poster upload failed. Please choose the file again.');
    }
    if (!isset($file['tmp_name'], $file['size']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('The uploaded poster could not be verified.');
    }
    $actualSize = filesize((string) $file['tmp_name']);
    if ($actualSize === false || $actualSize <= 0 || $actualSize > MAX_POSTER_BYTES) {
        throw new RuntimeException('Poster files must be no larger than 2 MB.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file((string) $file['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Upload a valid JPEG, PNG, or WebP poster image.');
    }

    $imageInfo = getimagesize((string) $file['tmp_name']);
    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mimeType) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    if (!is_dir(POSTER_DIRECTORY) && !mkdir(POSTER_DIRECTORY, 0755, true) && !is_dir(POSTER_DIRECTORY)) {
        throw new RuntimeException('The poster upload directory could not be created.');
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file((string) $file['tmp_name'], POSTER_DIRECTORY . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The poster could not be saved. Check the upload directory permissions.');
    }

    return $filename;
}

function removePoster(?string $filename): void
{
    if ($filename === null || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $filename)) {
        return;
    }

    $path = POSTER_DIRECTORY . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Could not remove event poster: ' . $path);
    }
}

function eventAudit(PDO $pdo, int $eventId, string $action, string $actorEmail, ?array $previous, ?array $current): void
{
    $statement = $pdo->prepare(
        'INSERT INTO event_audit_logs (event_id, action_type, actor_email, previous_data, current_data)
         VALUES (:event_id, :action_type, :actor_email, :previous_data, :current_data)'
    );
    $statement->execute([
        'event_id' => $eventId,
        'action_type' => $action,
        'actor_email' => $actorEmail,
        'previous_data' => $previous === null ? null : json_encode($previous, JSON_THROW_ON_ERROR),
        'current_data' => $current === null ? null : json_encode($current, JSON_THROW_ON_ERROR),
    ]);
    $auditStatement = $pdo->prepare(
        'INSERT INTO audit_logs (actor_email, action_type, entity_type, entity_id, details)
         VALUES (:actor_email, :action_type, :entity_type, :entity_id, :details)'
    );
    $auditStatement->execute([
        'actor_email' => $actorEmail,
        'action_type' => $action,
        'entity_type' => 'event',
        'entity_id' => $eventId,
        'details' => json_encode(['before' => $previous, 'after' => $current], JSON_THROW_ON_ERROR),
    ]);
}

$flash = $_SESSION['event_flash'] ?? null;
unset($_SESSION['event_flash']);
$message = is_array($flash) ? (string) ($flash['message'] ?? '') : '';
$messageType = is_array($flash) && ($flash['type'] ?? '') === 'error' ? 'error' : 'success';
if ($databaseError) {
    $message = 'Could not connect to the event database. Check MySQL and the database settings.';
    $messageType = 'error';
}
$csrfToken = (string) ($_SESSION['event_csrf'] ?? '');
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['event_csrf'] = $csrfToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$databaseError) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        eventRedirectWithMessage('Your form session expired. Reload the page and try again.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    $actorEmail = (string) ($_SESSION['student_email'] ?? '');
    $uploadedFilename = null;

    try {
        if ($action === 'save_event') {
            $eventId = filter_var($_POST['event_id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $title = trim((string) ($_POST['title'] ?? ''));
            $eventDate = trim((string) ($_POST['event_date'] ?? ''));
            $venue = trim((string) ($_POST['venue'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $status = (string) ($_POST['event_status'] ?? '');

            if ($eventId === false || $title === '' || strlen($title) > 160 || $venue === '' || strlen($venue) > 160 || $category === '' || strlen($category) > 60) {
                throw new RuntimeException('Enter a title, venue, and category within the allowed lengths.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
            if ($date === false || $date->format('Y-m-d') !== $eventDate) {
                throw new RuntimeException('Choose a valid event date.');
            }
            if (!in_array($status, ['Open', 'Closed'], true)) {
                throw new RuntimeException('Choose a valid event status.');
            }

            $existing = null;
            if ($eventId > 0) {
                $existingStatement = $pdo->prepare(
                    'SELECT event_id, title, event_date, venue, category, poster_path, event_status
                     FROM events WHERE event_id = :event_id'
                );
                $existingStatement->execute(['event_id' => $eventId]);
                $existing = $existingStatement->fetch();
                if (!is_array($existing)) {
                    throw new RuntimeException('The event being edited no longer exists.');
                }
            }

            $uploadedFilename = validateAndStorePoster($_FILES['poster'] ?? []);
            $posterPath = $uploadedFilename ?? ($existing['poster_path'] ?? null);
            $current = [
                'title' => $title,
                'event_date' => $eventDate,
                'venue' => $venue,
                'category' => $category,
                'event_status' => $status,
                'poster_path' => $posterPath,
            ];

            $pdo->beginTransaction();
            if ($eventId > 0) {
                $update = $pdo->prepare(
                    'UPDATE events
                     SET title = :title, event_date = :event_date, venue = :venue, category = :category,
                         event_status = :event_status, poster_path = :poster_path
                     WHERE event_id = :event_id'
                );
                $update->execute($current + ['event_id' => $eventId]);
                eventAudit($pdo, $eventId, 'UPDATE', $actorEmail, $existing, $current);
                $successMessage = 'Event updated successfully.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO events (title, event_date, venue, category, event_status, poster_path)
                     VALUES (:title, :event_date, :venue, :category, :event_status, :poster_path)'
                );
                $insert->execute($current);
                $eventId = (int) $pdo->lastInsertId();
                eventAudit($pdo, $eventId, 'CREATE', $actorEmail, null, $current);
                $successMessage = 'Event created successfully.';
            }
            $pdo->commit();

            if ($uploadedFilename !== null && is_array($existing)) {
                removePoster(is_string($existing['poster_path'] ?? null) ? $existing['poster_path'] : null);
            }
            eventRedirectWithMessage($successMessage, 'success');
        }

        if ($action === 'delete_event') {
            $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($eventId === false) {
                throw new RuntimeException('Choose a valid event to delete.');
            }

            $select = $pdo->prepare(
                'SELECT event_id, title, event_date, venue, category, poster_path, event_status
                 FROM events WHERE event_id = :event_id'
            );
            $select->execute(['event_id' => $eventId]);
            $existing = $select->fetch();
            if (!is_array($existing)) {
                throw new RuntimeException('The event was not found or has already been deleted.');
            }

            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM events WHERE event_id = :event_id');
            $delete->execute(['event_id' => $eventId]);
            eventAudit($pdo, $eventId, 'DELETE', $actorEmail, $existing, null);
            $pdo->commit();

            removePoster(is_string($existing['poster_path'] ?? null) ? $existing['poster_path'] : null);
            eventRedirectWithMessage('Event deleted successfully.', 'success');
        }

        throw new RuntimeException('The requested event action is not supported.');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        removePoster($uploadedFilename);
        error_log('Event management database operation failed: ' . $exception->getMessage());
        eventRedirectWithMessage('The event could not be saved. Check the database schema and try again.', 'error');
    } catch (JsonException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        removePoster($uploadedFilename);
        error_log('Event audit data could not be encoded: ' . $exception->getMessage());
        eventRedirectWithMessage('The event audit record could not be saved. Try again.', 'error');
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        removePoster($uploadedFilename);
        eventRedirectWithMessage($exception->getMessage(), 'error');
    }
}

$editEvent = null;
if (isset($_GET['edit']) && !$databaseError) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId !== false) {
        try {
            $statement = $pdo->prepare(
                'SELECT event_id, title, event_date, venue, category, poster_path, event_status
                 FROM events WHERE event_id = :event_id'
            );
            $statement->execute(['event_id' => $editId]);
            $editEvent = $statement->fetch() ?: null;
        } catch (PDOException $exception) {
            error_log('Event edit lookup failed: ' . $exception->getMessage());
            $message = 'The event could not be loaded for editing.';
            $messageType = 'error';
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$events = [];
$categories = [];

if (!$databaseError) {
    try {
        $categoriesStatement = $pdo->query('SELECT DISTINCT category FROM events ORDER BY category');
        $categories = $categoriesStatement->fetchAll(PDO::FETCH_COLUMN);

        $where = [];
        $parameters = [];
        if ($search !== '') {
            $where[] = '(title LIKE :search_title OR venue LIKE :search_venue OR category LIKE :search_category)';
            $searchPattern = '%' . $search . '%';
            $parameters['search_title'] = $searchPattern;
            $parameters['search_venue'] = $searchPattern;
            $parameters['search_category'] = $searchPattern;
        }
        if ($categoryFilter !== '') {
            $where[] = 'category = :category';
            $parameters['category'] = $categoryFilter;
        }
        if (in_array($statusFilter, ['Open', 'Closed'], true)) {
            $where[] = 'event_status = :event_status';
            $parameters['event_status'] = $statusFilter;
        } else {
            $statusFilter = '';
        }

        $query = 'SELECT event_id, title, event_date, venue, category, poster_path, event_status FROM events';
        if ($where !== []) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY event_date DESC, event_id DESC';
        $eventsStatement = $pdo->prepare($query);
        $eventsStatement->execute($parameters);
        $events = $eventsStatement->fetchAll();
    } catch (PDOException $exception) {
        error_log('Event management listing failed: ' . $exception->getMessage());
        $databaseError = true;
        $message = 'Could not load event records. Import the event management schema and check the MySQL connection.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management</title>
    <link rel="stylesheet" href="../CSS%20Files/EventManagement.css">
    <script src="../JavaScript%20Files/EventManagement.js" defer></script>
</head>
<body data-events-api="api/events.php">
    <main class="event-page">
        <header class="page-header">
            <div>
                <p class="eyebrow">Admin tools</p>
                <h1>Event Management</h1>
                <p class="page-description">Create campus events, manage availability, and upload validated posters.</p>
            </div>
            <a class="button button-muted" href="../AdminDashboard.php">Back to dashboard</a>
        </header>

        <?php if ($message !== ''): ?>
            <div class="notice <?php echo eventEscape($messageType); ?>" role="status" data-event-notice><?php echo eventEscape($message); ?></div>
        <?php endif; ?>

        <?php if (!$databaseError): ?>
            <section class="panel" aria-labelledby="event-form-title">
                <div class="panel-heading">
                    <h2 id="event-form-title"><?php echo $editEvent ? 'Edit event' : 'Add an event'; ?></h2>
                    <span class="hint">Poster: JPEG, PNG or WebP · up to 2 MB</span>
                </div>
                <form class="event-form" id="eventForm" method="post" enctype="multipart/form-data" action="event_management.php">
                    <input type="hidden" name="csrf_token" value="<?php echo eventEscape($csrfToken); ?>">
                    <input type="hidden" name="action" value="save_event">
                    <input type="hidden" name="event_id" value="<?php echo (int) ($editEvent['event_id'] ?? 0); ?>">

                    <div class="form-grid">
                        <label class="field">
                            <span>Event title</span>
                            <input type="text" name="title" maxlength="160" required value="<?php echo eventEscape((string) ($editEvent['title'] ?? '')); ?>">
                        </label>
                        <label class="field">
                            <span>Date</span>
                            <input type="date" name="event_date" required value="<?php echo eventEscape((string) ($editEvent['event_date'] ?? '')); ?>">
                        </label>
                        <label class="field">
                            <span>Venue</span>
                            <input type="text" name="venue" maxlength="160" required value="<?php echo eventEscape((string) ($editEvent['venue'] ?? '')); ?>">
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <input type="text" name="category" maxlength="60" list="event-categories" required value="<?php echo eventEscape((string) ($editEvent['category'] ?? '')); ?>">
                            <datalist id="event-categories">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo eventEscape((string) $category); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                        <label class="field">
                            <span>Status</span>
                            <select name="event_status" required>
                                <option value="Open" <?php echo (($editEvent['event_status'] ?? 'Open') === 'Open') ? 'selected' : ''; ?>>Open</option>
                                <option value="Closed" <?php echo (($editEvent['event_status'] ?? '') === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </label>
                        <label class="field poster-field">
                            <span>Poster image <?php echo $editEvent ? '(optional replacement)' : '(optional)'; ?></span>
                            <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" data-poster-input>
                            <small data-poster-status>Choose an image no larger than 2 MB.</small>
                        </label>
                    </div>

                    <?php if (!empty($editEvent['poster_path'])): ?>
                        <div class="current-poster">
                            <span>Current poster</span>
                            <img src="../Images/EventPosters/<?php echo rawurlencode((string) $editEvent['poster_path']); ?>" alt="Current poster for <?php echo eventEscape((string) $editEvent['title']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="poster-preview" data-poster-preview hidden>
                        <span>Selected poster preview</span>
                        <img alt="Preview of selected event poster">
                    </div>

                    <div class="form-actions">
                        <button class="button button-muted" type="button" data-cancel-edit <?php echo $editEvent ? '' : 'hidden'; ?>>Cancel edit</button>
                        <button class="button button-primary" type="submit"><?php echo $editEvent ? 'Save changes' : 'Create event'; ?></button>
                    </div>
                </form>
            </section>

            <section class="panel" aria-labelledby="event-list-title">
                <div class="panel-heading">
                    <div>
                        <h2 id="event-list-title">Events</h2>
                        <p class="hint" id="eventCount"><?php echo count($events); ?> matching records</p>
                    </div>
                </div>
                <form class="filters" id="eventFilters" method="get" action="event_management.php">
                    <label class="field">
                        <span>Search</span>
                        <input type="search" name="q" value="<?php echo eventEscape($search); ?>" placeholder="Title, venue or category">
                    </label>
                    <label class="field">
                        <span>Category</span>
                        <select name="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo eventEscape((string) $category); ?>" <?php echo ($categoryFilter === $category) ? 'selected' : ''; ?>><?php echo eventEscape((string) $category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Status</span>
                        <select name="status">
                            <option value="">All statuses</option>
                            <option value="Open" <?php echo $statusFilter === 'Open' ? 'selected' : ''; ?>>Open</option>
                            <option value="Closed" <?php echo $statusFilter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit">Apply filters</button>
                    <a class="button button-muted" href="event_management.php" data-reset-filters>Reset</a>
                </form>

                <p class="hint" id="eventsLoading" role="status" aria-live="polite" hidden>Loading events…</p>
                <p class="empty-state" data-events-empty hidden>No events match these filters. Create an event above to get started.</p>
                <div class="event-list" id="eventList" aria-live="polite"></div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
