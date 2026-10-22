<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this migration from the command line.');
}

require_once __DIR__ . '/db.php';

function readMigrationJson(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Could not read migration input: ' . basename($path));
    }

    $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($records)) {
        throw new RuntimeException('Invalid migration input: ' . basename($path));
    }

    return $records;
}

function nullableNumeric(mixed $value): ?float
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    if (!is_numeric($value)) {
        throw new RuntimeException('A numeric academic value is invalid.');
    }
    return (float) $value;
}

function importStudentResults(PDO $pdo, int $userId, array $results): void
{
    $semesterUpsert = $pdo->prepare(
        'INSERT INTO student_semester_results
            (user_id, semester, cgpa, sgpa, credits_earned, credits_total, academic_progress)
         VALUES (:user_id, :semester, :cgpa, :sgpa, :credits_earned, :credits_total, :academic_progress)
         ON DUPLICATE KEY UPDATE
            cgpa = VALUES(cgpa), sgpa = VALUES(sgpa),
            credits_earned = VALUES(credits_earned), credits_total = VALUES(credits_total),
            academic_progress = VALUES(academic_progress)'
    );
    $resultLookup = $pdo->prepare(
        'SELECT result_id FROM student_semester_results WHERE user_id = :user_id AND semester = :semester'
    );
    $subjectUpsert = $pdo->prepare(
        'INSERT INTO student_subject_results
            (result_id, subject_name, credits, score, grade, internal_score, external_score,
             practical_score, attendance, feedback)
         VALUES
            (:result_id, :subject_name, :credits, :score, :grade, :internal_score, :external_score,
             :practical_score, :attendance, :feedback)
         ON DUPLICATE KEY UPDATE
            credits = VALUES(credits), score = VALUES(score), grade = VALUES(grade),
            internal_score = VALUES(internal_score), external_score = VALUES(external_score),
            practical_score = VALUES(practical_score), attendance = VALUES(attendance),
            feedback = VALUES(feedback)'
    );

    foreach ([1 => 'sem1', 2 => 'sem2', 3 => 'sem3', 4 => 'sem4', 5 => 'sem5', 6 => 'sem6', 7 => 'sem7', 8 => 'sem8'] as $semesterNumber => $semesterKey) {
        $semester = is_array($results[$semesterKey] ?? null) ? $results[$semesterKey] : [];
        $semesterUpsert->execute([
            'user_id' => $userId,
            'semester' => $semesterNumber,
            'cgpa' => nullableNumeric($semester['cgpa'] ?? null),
            'sgpa' => nullableNumeric($semester['sgpa'] ?? null),
            'credits_earned' => nullableNumeric($semester['creditsEarned'] ?? null),
            'credits_total' => nullableNumeric($semester['creditsTotal'] ?? null),
            'academic_progress' => trim((string) ($semester['academicProgress'] ?? '')) ?: null,
        ]);

        $resultLookup->execute(['user_id' => $userId, 'semester' => $semesterNumber]);
        $resultId = (int) $resultLookup->fetchColumn();
        foreach (is_array($semester['subjects'] ?? null) ? $semester['subjects'] : [] as $subject) {
            if (!is_array($subject) || trim((string) ($subject['name'] ?? '')) === '') {
                continue;
            }
            $subjectUpsert->execute([
                'result_id' => $resultId,
                'subject_name' => mb_substr(trim((string) $subject['name']), 0, 160),
                'credits' => nullableNumeric($subject['credits'] ?? null),
                'score' => nullableNumeric($subject['score'] ?? null),
                'grade' => trim((string) ($subject['grade'] ?? '')) ?: null,
                'internal_score' => nullableNumeric($subject['internal'] ?? null),
                'external_score' => nullableNumeric($subject['external'] ?? null),
                'practical_score' => nullableNumeric($subject['practical'] ?? null),
                'attendance' => trim((string) ($subject['attendance'] ?? '')) ?: null,
                'feedback' => trim((string) ($subject['feedback'] ?? '')) ?: null,
            ]);
        }
    }
}

$registrationRecords = readMigrationJson(__DIR__ . '/registrations.json');
$studentRecords = readMigrationJson(__DIR__ . '/../JSON Files/students.json');
$pdo->beginTransaction();

try {
    $accountLookup = $pdo->prepare('SELECT user_id FROM user_accounts WHERE email = :email LIMIT 1');
    $accountInsert = $pdo->prepare(
        'INSERT INTO user_accounts
            (full_name, email, mobile, course, year_of_study, gender, role, is_active,
             password_hash, created_at)
         VALUES
            (:full_name, :email, :mobile, :course, :year_of_study, :gender, \'student\', 1,
             :password_hash, :created_at)'
    );
    $profileUpsert = $pdo->prepare(
        'INSERT INTO student_profiles (user_id, date_of_birth, department, current_semester, division, address)
         VALUES (:user_id, :date_of_birth, :department, :current_semester, :division, :address)
         ON DUPLICATE KEY UPDATE
            date_of_birth = VALUES(date_of_birth), department = VALUES(department),
            current_semester = VALUES(current_semester), division = VALUES(division), address = VALUES(address)'
    );

    $importedAccounts = 0;
    foreach ($registrationRecords as $record) {
        if (!is_array($record)) {
            continue;
        }
        $email = strtolower(trim((string) ($record['email'] ?? '')));
        $passwordHash = (string) ($record['password_hash'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $passwordHash === '') {
            continue;
        }

        $accountLookup->execute(['email' => $email]);
        $userId = $accountLookup->fetchColumn();
        if ($userId === false) {
            $accountInsert->execute([
                'full_name' => trim((string) ($record['fullname'] ?? 'Student')),
                'email' => $email,
                'mobile' => trim((string) ($record['mobile'] ?? '')) ?: null,
                'course' => trim((string) ($record['course'] ?? '')) ?: null,
                'year_of_study' => trim((string) ($record['year'] ?? '')) ?: null,
                'gender' => trim((string) ($record['gender'] ?? '')) ?: null,
                'password_hash' => $passwordHash,
                'created_at' => (string) ($record['registered_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $importedAccounts++;
        } else {
            $userId = (int) $userId;
        }

        $dateOfBirth = trim((string) ($record['date_of_birth'] ?? ''));
        if ($dateOfBirth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
            $dateOfBirth = '';
        }
        $profileUpsert->execute([
            'user_id' => $userId,
            'date_of_birth' => $dateOfBirth !== '' ? $dateOfBirth : null,
            'department' => trim((string) ($record['department'] ?? '')) ?: null,
            'current_semester' => trim((string) ($record['semester'] ?? '')) ?: null,
            'division' => trim((string) ($record['division'] ?? '')) ?: null,
            'address' => trim((string) ($record['address'] ?? '')) ?: null,
        ]);
        importStudentResults($pdo, $userId, is_array($record['results'] ?? null) ? $record['results'] : []);
    }

    $courseLookup = $pdo->prepare('SELECT course_id FROM courses WHERE course_code = :course_code LIMIT 1');
    $studentUpsert = $pdo->prepare(
        'INSERT INTO students (full_name, email, course_id, year_of_study, city, cgpa)
         VALUES (:full_name, :email, :course_id, :year_of_study, :city, :cgpa)
         ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name), course_id = VALUES(course_id),
            year_of_study = VALUES(year_of_study), city = VALUES(city), cgpa = VALUES(cgpa)'
    );
    $studentLookup = $pdo->prepare(
        'SELECT student_id FROM students WHERE full_name = :full_name AND course_id = :course_id LIMIT 1'
    );
    $studentUpdate = $pdo->prepare(
        'UPDATE students SET year_of_study = :year_of_study, city = :city, cgpa = :cgpa
         WHERE student_id = :student_id'
    );
    $importedStudentRows = 0;
    foreach ($studentRecords as $record) {
        if (!is_array($record)) {
            continue;
        }
        $courseLookup->execute(['course_code' => trim((string) ($record['course'] ?? ''))]);
        $courseId = $courseLookup->fetchColumn();
        if ($courseId === false || trim((string) ($record['name'] ?? '')) === '') {
            continue;
        }
        $email = trim((string) ($record['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'student-' . (int) ($record['id'] ?? 0) . '@imported.studentportal.local';
        }
        $studentData = [
            'full_name' => trim((string) $record['name']),
            'email' => strtolower($email),
            'course_id' => (int) $courseId,
            'year_of_study' => trim((string) ($record['year'] ?? '')) ?: 'Unknown',
            'city' => trim((string) ($record['city'] ?? '')) ?: 'Unknown',
            'cgpa' => nullableNumeric($record['cgpa'] ?? null) ?? 0,
        ];
        $studentLookup->execute([
            'full_name' => $studentData['full_name'],
            'course_id' => $studentData['course_id'],
        ]);
        $existingStudentId = $studentLookup->fetchColumn();
        if ($existingStudentId !== false) {
            $studentUpdate->execute([
                'year_of_study' => $studentData['year_of_study'],
                'city' => $studentData['city'],
                'cgpa' => $studentData['cgpa'],
                'student_id' => (int) $existingStudentId,
            ]);
        } else {
            $studentUpsert->execute($studentData);
        }
        $importedStudentRows++;
    }

    $pdo->commit();
    printf("Imported accounts: %d\nImported student records: %d\n", $importedAccounts, $importedStudentRows);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'JSON data migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}