USE studenthub;

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS poster_path VARCHAR(255) NULL AFTER category,
    ADD COLUMN IF NOT EXISTS event_status ENUM('Open', 'Closed') NOT NULL DEFAULT 'Open' AFTER poster_path,
    ADD KEY IF NOT EXISTS idx_events_status_date (event_status, event_date);

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
