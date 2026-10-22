USE studenthub;

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