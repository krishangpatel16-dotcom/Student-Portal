<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';

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
        require_once __DIR__ . '/db.php';

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
    require_once __DIR__ . '/db.php';

    $statement = $pdo->prepare(
        'SELECT ua.user_id, ua.full_name, ua.email, ua.mobile, ua.course, ua.year_of_study,
            ua.gender, ua.role, ua.is_active, ua.password_hash,
                sp.student_number, sp.date_of_birth, sp.department, sp.current_semester, sp.division, sp.address
         FROM user_accounts AS ua
         LEFT JOIN student_profiles AS sp ON sp.user_id = ua.user_id
         WHERE ua.email = :email LIMIT 1'
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
            'student_id' => $dbStudent['student_number'] ?? '',
            'account_id' => $dbStudent['user_id'] ?? '',
            'password_hash' => $dbStudent['password_hash'] ?? '',
            'date_of_birth' => $dbStudent['date_of_birth'] ?? '',
            'department' => $dbStudent['department'] ?? '',
            'semester' => $dbStudent['current_semester'] ?? '',
            'division' => $dbStudent['division'] ?? '',
            'address' => $dbStudent['address'] ?? '',
        ];
        $semesterStatement = $pdo->prepare(
            'SELECT result_id, semester, cgpa, sgpa, credits_earned, credits_total, academic_progress
             FROM student_semester_results WHERE user_id = :user_id ORDER BY semester'
        );
        $semesterStatement->execute(['user_id' => (int) $dbStudent['user_id']]);
        $subjectStatement = $pdo->prepare(
            'SELECT subject_name, credits, score, grade, internal_score, external_score,
                    practical_score, attendance, feedback
             FROM student_subject_results WHERE result_id = :result_id ORDER BY subject_result_id'
        );
        $student['results'] = [];
        foreach ($semesterStatement->fetchAll() as $semesterRow) {
            $semesterKey = 'sem' . (int) $semesterRow['semester'];
            $subjectStatement->execute(['result_id' => (int) $semesterRow['result_id']]);
            $subjects = array_map(static function (array $subject): array {
                return [
                    'name' => (string) $subject['subject_name'],
                    'credits' => $subject['credits'] === null ? '' : (string) $subject['credits'],
                    'score' => $subject['score'] === null ? '' : (string) $subject['score'],
                    'grade' => (string) ($subject['grade'] ?? ''),
                    'internal' => $subject['internal_score'] === null ? '' : (string) $subject['internal_score'],
                    'external' => $subject['external_score'] === null ? '' : (string) $subject['external_score'],
                    'practical' => $subject['practical_score'] === null ? '' : (string) $subject['practical_score'],
                    'attendance' => (string) ($subject['attendance'] ?? ''),
                    'feedback' => (string) ($subject['feedback'] ?? ''),
                ];
            }, $subjectStatement->fetchAll());
            $student['results'][$semesterKey] = [
                'cgpa' => $semesterRow['cgpa'] === null ? '' : (string) $semesterRow['cgpa'],
                'sgpa' => $semesterRow['sgpa'] === null ? '' : (string) $semesterRow['sgpa'],
                'creditsEarned' => $semesterRow['credits_earned'] === null ? '' : (string) $semesterRow['credits_earned'],
                'creditsTotal' => $semesterRow['credits_total'] === null ? '' : (string) $semesterRow['credits_total'],
                'academicProgress' => (string) ($semesterRow['academic_progress'] ?? ''),
                'subjects' => $subjects,
            ];
        }
    }
} catch (PDOException $exception) {
    error_log('Current user lookup failed: ' . $exception->getMessage());
    sendJsonResponse(['error' => 'Your account information is temporarily unavailable.'], 500);
}

if ($student !== null && (int) ($student['is_active'] ?? 1) !== 1) {
    destroySession();
    sendJsonResponse(['error' => 'Your account is inactive. Contact an administrator.'], 401);
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
