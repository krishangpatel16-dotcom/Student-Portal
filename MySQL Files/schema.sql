CREATE DATABASE IF NOT EXISTS studenthub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub;

CREATE TABLE IF NOT EXISTS courses (
    course_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_code VARCHAR(20) NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (course_id),
    UNIQUE KEY uq_courses_code (course_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    course_id SMALLINT UNSIGNED NOT NULL,
    year_of_study VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    cgpa DECIMAL(3, 2) NOT NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_course (course_id),
    CONSTRAINT chk_students_cgpa CHECK (cgpa >= 0 AND cgpa <= 10),
    CONSTRAINT fk_students_course
        FOREIGN KEY (course_id) REFERENCES courses (course_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_accounts (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    mobile VARCHAR(15) NULL DEFAULT NULL,
    course VARCHAR(50) NULL DEFAULT NULL,
    year_of_study VARCHAR(20) NULL DEFAULT NULL,
    gender VARCHAR(20) NULL DEFAULT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    password_hash VARCHAR(255) NOT NULL,
    remember_token_hash VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_accounts_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id INT UNSIGNED NOT NULL,
    student_number VARCHAR(30) NULL,
    date_of_birth DATE NULL,
    department VARCHAR(100) NULL,
    current_semester VARCHAR(30) NULL,
    division VARCHAR(30) NULL,
    address TEXT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_student_profiles_number (student_number),
    CONSTRAINT fk_student_profiles_user
        FOREIGN KEY (user_id) REFERENCES user_accounts (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_semester_results (
    result_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    cgpa DECIMAL(4, 2) NULL,
    sgpa DECIMAL(4, 2) NULL,
    credits_earned DECIMAL(6, 2) NULL,
    credits_total DECIMAL(6, 2) NULL,
    academic_progress VARCHAR(120) NULL,
    PRIMARY KEY (result_id),
    UNIQUE KEY uq_student_semester (user_id, semester),
    CONSTRAINT chk_student_result_semester CHECK (semester BETWEEN 1 AND 8),
    CONSTRAINT fk_student_results_user
        FOREIGN KEY (user_id) REFERENCES user_accounts (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_subject_results (
    subject_result_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    result_id BIGINT UNSIGNED NOT NULL,
    subject_name VARCHAR(160) NOT NULL,
    credits DECIMAL(5, 2) NULL,
    score DECIMAL(5, 2) NULL,
    grade VARCHAR(10) NULL,
    internal_score DECIMAL(5, 2) NULL,
    external_score DECIMAL(5, 2) NULL,
    practical_score DECIMAL(5, 2) NULL,
    attendance VARCHAR(30) NULL,
    feedback TEXT NULL,
    PRIMARY KEY (subject_result_id),
    UNIQUE KEY uq_result_subject (result_id, subject_name),
    CONSTRAINT fk_subject_results_semester
        FOREIGN KEY (result_id) REFERENCES student_semester_results (result_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(160) NOT NULL,
    category VARCHAR(60) NOT NULL,
    poster_path VARCHAR(255) NULL,
    event_status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open',
    PRIMARY KEY (event_id),
    KEY idx_events_date (event_date),
    KEY idx_events_status_date (event_status, event_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_audit_logs (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT UNSIGNED NOT NULL,
    action_type ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
    actor_email VARCHAR(254) NOT NULL,
    previous_data LONGTEXT NULL,
    current_data LONGTEXT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_event_audit_event (event_id),
    KEY idx_event_audit_changed_at (changed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_email VARCHAR(254) NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback_submissions (
    feedback_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    subject VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (feedback_id),
    KEY idx_feedback_submitted_at (submitted_at),
    KEY idx_feedback_user (user_id),
    CONSTRAINT fk_feedback_user
        FOREIGN KEY (user_id) REFERENCES user_accounts (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_registration_student_event (student_id, event_id),
    KEY idx_registrations_event (event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
