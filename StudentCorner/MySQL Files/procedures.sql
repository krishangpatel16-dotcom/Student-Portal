USE studenthub;

DROP PROCEDURE IF EXISTS register_student_for_event;
DELIMITER //
CREATE PROCEDURE register_student_for_event(
    IN p_student_id INT UNSIGNED,
    IN p_event_id INT UNSIGNED
)
BEGIN
    INSERT INTO registrations (student_id, event_id)
    VALUES (p_student_id, p_event_id);
END//
DELIMITER ;
