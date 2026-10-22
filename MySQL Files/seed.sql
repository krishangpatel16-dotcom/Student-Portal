USE studenthub;

INSERT INTO courses (course_id, course_code, course_name) VALUES
(1, 'BCA', 'Bachelor of Computer Applications'),
(2, 'BSc', 'Bachelor of Science'),
(3, 'BTech', 'Bachelor of Technology'),
(4, 'MBA', 'Master of Business Administration')
ON DUPLICATE KEY UPDATE
    course_code = VALUES(course_code),
    course_name = VALUES(course_name);

INSERT INTO user_accounts (user_id, full_name, email, mobile, course, year_of_study, gender, role, is_active, password_hash, remember_token_hash) VALUES
(1, 'Portal Administrator', 'admin@studentportal.local', '9876543210', 'BCA', 'Admin', 'Other', 'admin', 1, '$2y$10$UwtyDePguSjO0xsSfUdFEOem6S6YqgEaTcyolUh/YtfikE4bAYToS', NULL),
(2, 'Demo Student', 'student@studentportal.local', '9123456789', 'BCA', '2nd Year', 'Male', 'student', 1, '$2y$10$Bfw/mGPpQTz3WKNJs1Kud.p1AGEzzRmvq5ULWJ39czITqi/uNqIFe', NULL)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    mobile = VALUES(mobile),
    course = VALUES(course),
    year_of_study = VALUES(year_of_study),
    gender = VALUES(gender),
    role = VALUES(role),
    is_active = VALUES(is_active),
    password_hash = VALUES(password_hash);

INSERT INTO students (student_id, full_name, email, course_id, year_of_study, city, cgpa) VALUES
(1, 'Aarav Shah', 'aarav.shah@example.edu', 1, '2nd Year', 'Ahmedabad', 8.90),
(2, 'Meera Patel', 'meera.patel@example.edu', 2, '1st Year', 'Surat', 8.40),
(3, 'Rohan Verma', 'rohan.verma@example.edu', 3, '3rd Year', 'Rajkot', 9.10),
(4, 'Isha Desai', 'isha.desai@example.edu', 4, '1st Year', 'Vadodara', 8.70),
(5, 'Karan Joshi', 'karan.joshi@example.edu', 1, '3rd Year', 'Gandhinagar', 8.60),
(6, 'Priya Nair', 'priya.nair@example.edu', 2, '2nd Year', 'Bhavnagar', 9.00),
(7, 'Dev Mehta', 'dev.mehta@example.edu', 3, '4th Year', 'Ahmedabad', 8.80),
(8, 'Neha Shah', 'neha.shah@example.edu', 4, '2nd Year', 'Surat', 8.50),
(9, 'Harsh Trivedi', 'harsh.trivedi@example.edu', 1, '1st Year', 'Rajkot', 7.90),
(10, 'Sonal Gupta', 'sonal.gupta@example.edu', 2, '3rd Year', 'Vadodara', 9.20),
(11, 'Yash Patel', 'yash.patel@example.edu', 3, '2nd Year', 'Bhavnagar', 8.30),
(12, 'Anjali Rao', 'anjali.rao@example.edu', 4, '3rd Year', 'Gandhinagar', 8.90),
(14, 'Mansi Solanki', 'mansi.solanki@example.edu', 2, '1st Year', 'Rajkot', 8.70),
(15, 'Vivek Soni', 'vivek.soni@example.edu', 3, '3rd Year', 'Surat', 9.30)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    course_id = VALUES(course_id),
    year_of_study = VALUES(year_of_study),
    city = VALUES(city),
    cgpa = VALUES(cgpa);

INSERT INTO events (event_id, title, event_date, venue, category, event_status) VALUES
(1, 'Orientation Program', '2026-08-10', 'Main Auditorium', 'Campus', 'Closed'),
(2, 'Sports Day', '2026-08-18', 'Sports Ground', 'Sports', 'Closed'),
(3, 'Coding Competition', '2026-08-25', 'Computer Lab', 'Tech', 'Closed'),
(4, 'Teacher''s Day', '2026-09-05', 'Seminar Hall', 'Campus', 'Closed'),
(5, 'Technical Workshop', '2026-09-15', 'Block A', 'Workshop', 'Closed'),
(6, 'Career Guidance Seminar', '2026-09-22', 'Lecture Hall 2', 'Career', 'Closed'),
(7, 'Hackathon Bootcamp', '2026-09-28', 'Innovation Lab', 'Tech', 'Closed'),
(8, 'Cultural Fest', '2026-10-02', 'Open Stage', 'Cultural', 'Closed'),
(9, 'Guest Lecture', '2026-10-10', 'Conference Room', 'Academic', 'Open'),
(10, 'Robotics Club Meetup', '2026-10-18', 'Workshop Room', 'Tech', 'Open'),
(11, 'Inter-College Debate', '2026-10-24', 'Auditorium', 'Campus', 'Open'),
(12, 'Health Awareness Drive', '2026-11-01', 'Campus Plaza', 'Health', 'Open'),
(13, 'Alumni Interaction', '2026-11-08', 'Main Hall', 'Career', 'Open'),
(14, 'Science Expo', '2026-11-15', 'Science Block', 'Academic', 'Open'),
(15, 'Annual Fest', '2026-11-25', 'Campus Ground', 'Cultural', 'Open')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    event_date = VALUES(event_date),
    venue = VALUES(venue),
    category = VALUES(category),
    event_status = VALUES(event_status);

INSERT INTO registrations (student_id, event_id) VALUES
(1, 9),
(3, 10),
(6, 12)
ON DUPLICATE KEY UPDATE registered_at = registered_at;
