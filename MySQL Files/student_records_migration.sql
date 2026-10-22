USE studenthub;

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

ALTER TABLE student_profiles
    ADD COLUMN IF NOT EXISTS student_number VARCHAR(30) NULL AFTER user_id;

CREATE UNIQUE INDEX IF NOT EXISTS uq_student_profiles_number
    ON student_profiles (student_number);

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
    CONSTRAINT chk_student_result_semester CHECK (semester BETWEEN 1 AND 3),
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

ALTER TABLE student_semester_results
    DROP CONSTRAINT IF EXISTS chk_student_result_semester;

ALTER TABLE student_semester_results
    ADD CONSTRAINT chk_student_result_semester CHECK (semester BETWEEN 1 AND 8);