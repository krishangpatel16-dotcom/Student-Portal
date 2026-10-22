<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth_helpers.php';

header('Cache-Control: no-store');
startSecureSession();
if (empty($_SESSION['student_email']) || isSessionExpired()) {
    destroySession();
    sendJsonResponse(['error' => 'Sign in to view student records.'], 401);
}
refreshSessionLifetime();

try {
    require_once __DIR__ . '/../db.php';
    $statement = $pdo->query(
        'SELECT s.student_id, s.full_name, c.course_code AS course,
                s.year_of_study, s.city, s.cgpa
         FROM students AS s
         INNER JOIN courses AS c ON c.course_id = s.course_id
         ORDER BY s.full_name'
    );
    sendJsonResponse(['data' => $statement->fetchAll()]);
} catch (PDOException $exception) {
    error_log('Student record list failed: ' . $exception->getMessage());
    sendJsonResponse(['error' => 'Student records are temporarily unavailable.'], 500);
}