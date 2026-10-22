USE studenthub;

-- Practical 11: joins, grouping, aggregate functions, and a reusable view.

-- List students with their course details.
SELECT s.student_id, s.full_name, s.email, c.course_code, c.course_name,
       s.year_of_study, s.city, s.cgpa
FROM students AS s
INNER JOIN courses AS c ON c.course_id = s.course_id
ORDER BY c.course_code, s.full_name;

-- Summarize enrollment and academic performance by course.
SELECT c.course_code, c.course_name,
       COUNT(s.student_id) AS student_count,
       ROUND(AVG(s.cgpa), 2) AS average_cgpa,
       MAX(s.cgpa) AS highest_cgpa
FROM courses AS c
LEFT JOIN students AS s ON s.course_id = c.course_id
GROUP BY c.course_id, c.course_code, c.course_name
ORDER BY c.course_code;

-- List event registrations, including the student and course.
SELECT e.title AS event_title, e.event_date, e.event_status,
       s.full_name AS student_name, c.course_code, r.registered_at
FROM registrations AS r
INNER JOIN students AS s ON s.student_id = r.student_id
INNER JOIN courses AS c ON c.course_id = s.course_id
INNER JOIN events AS e ON e.event_id = r.event_id
ORDER BY e.event_date, s.full_name;

-- Create a reusable student/course summary view.
CREATE OR REPLACE VIEW vw_student_course_summary AS
SELECT s.student_id, s.full_name, s.email, c.course_code, c.course_name,
       s.year_of_study, s.city, s.cgpa
FROM students AS s
INNER JOIN courses AS c ON c.course_id = s.course_id;

SELECT *
FROM vw_student_course_summary
ORDER BY course_code, full_name;