USE naripa_wheels;

-- Trigger untuk log pembatalan booking
DELIMITER //
CREATE TRIGGER log_rental_cancellation
AFTER UPDATE ON rentals
FOR EACH ROW
BEGIN
    IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NEW.id, NEW.user_id, NEW.motorcycle_id, 'CANCELLED', 
                CONCAT('Rental cancelled. Original dates: ', OLD.rental_date, ' to ', OLD.return_date));
        
        -- Kembalikan slot yang tersedia
        UPDATE motorcycles 
        SET available_slots = available_slots + 1 
        WHERE id = NEW.motorcycle_id;
    END IF;
END //
DELIMITER ;

-- Trigger untuk log penghapusan booking
DELIMITER //
CREATE TRIGGER log_rental_deletion
BEFORE DELETE ON rentals
FOR EACH ROW
BEGIN
    INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
    VALUES (OLD.id, OLD.user_id, OLD.motorcycle_id, 'DELETED', 
            CONCAT('Rental deleted. Dates: ', OLD.rental_date, ' to ', OLD.return_date));
    
    -- Kembalikan slot jika status bukan cancelled
    IF OLD.status != 'cancelled' THEN
        UPDATE motorcycles 
        SET available_slots = available_slots + 1 
        WHERE id = OLD.motorcycle_id;
    END IF;
END //
DELIMITER ;
DELIMITER ;
