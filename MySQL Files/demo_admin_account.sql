USE studenthub;

INSERT INTO user_accounts
    (full_name, email, role, is_active, password_hash)
VALUES
    (
        'Dashboard Demo Admin',
        'demo.admin@studentportal.local',
        'admin',
        1,
        '$2y$10$uWsSTvUgSKxZcoVl.Tq53umHFPHFtZlxWnWYiYt8sN9YqJDh4LHBK'
    )
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    role = 'admin',
    is_active = 1,
    password_hash = VALUES(password_hash);
