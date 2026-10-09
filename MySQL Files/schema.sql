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
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    year_of_study VARCHAR(20) NOT NULL,
    gender VARCHAR(20) NOT NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    password_hash VARCHAR(255) NOT NULL,
    remember_token_hash VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_accounts_email (email)
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
