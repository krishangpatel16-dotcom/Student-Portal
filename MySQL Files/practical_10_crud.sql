USE studenthub;

-- Practical 10: student CRUD operations inside a transaction.
-- The generated test row is deleted and the transaction is rolled back.
SET @practical10_email = CONCAT('practical10-', UUID(), '@example.edu');

START TRANSACTION;

INSERT INTO students (full_name, email, course_id, year_of_study, city, cgpa)
SELECT 'Practical 10 Test Student', @practical10_email, course_id, '1st Year', 'Test City', 8.50
FROM courses
WHERE course_code = 'BCA'
LIMIT 1;

SELECT student_id, full_name, email, year_of_study, city, cgpa
FROM students
WHERE email = @practical10_email;

UPDATE students
SET city = 'Updated Test City', cgpa = 8.75
WHERE email = @practical10_email;

SELECT student_id, full_name, email, year_of_study, city, cgpa
FROM students
WHERE email = @practical10_email;

DELETE FROM students
WHERE email = @practical10_email;

SELECT COUNT(*) AS test_rows_remaining
FROM students
WHERE email = @practical10_email;

ROLLBACK;