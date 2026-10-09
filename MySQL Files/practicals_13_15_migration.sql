USE studenthub;

ALTER TABLE user_accounts
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

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
