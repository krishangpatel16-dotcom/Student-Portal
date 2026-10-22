USE studenthub;

-- Practical 12: stored procedure with input validation and database errors.
DROP PROCEDURE IF EXISTS register_student_for_open_event;
DELIMITER //
CREATE PROCEDURE register_student_for_open_event(
    IN p_student_id INT UNSIGNED,
    IN p_event_id INT UNSIGNED
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM students
        WHERE student_id = p_student_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Student does not exist';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM events
        WHERE event_id = p_event_id
          AND event_status = 'Open'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Event does not exist or is not open';
    END IF;

    INSERT INTO registrations (student_id, event_id)
    VALUES (p_student_id, p_event_id);
END//
DELIMITER ;

-- Example call (uses an open event and student from seed.sql):
-- CALL register_student_for_open_event(1, 11);