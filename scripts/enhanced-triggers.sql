USE naripa_wheels;

-- Drop existing triggers
DROP TRIGGER IF EXISTS log_rental_cancellation;
DROP TRIGGER IF EXISTS log_rental_deletion;
DROP TRIGGER IF EXISTS update_motorcycle_slots_on_rental;
DROP TRIGGER IF EXISTS validate_rental_dates;
DROP TRIGGER IF EXISTS log_user_changes;
DROP TRIGGER IF EXISTS log_motorcycle_changes;
DROP TRIGGER IF EXISTS auto_update_rental_status;

-- Trigger untuk validasi tanggal rental sebelum insert
DELIMITER //
CREATE TRIGGER validate_rental_dates
BEFORE INSERT ON rentals
FOR EACH ROW
BEGIN
    IF NEW.rental_date >= NEW.return_date THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tanggal kembali harus setelah tanggal sewa';
    END IF;
    
    IF NEW.rental_date < CURDATE() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tanggal sewa tidak boleh di masa lalu';
    END IF;
    
    -- Set default values
    IF NEW.total_days IS NULL THEN
        SET NEW.total_days = DATEDIFF(NEW.return_date, NEW.rental_date);
    END IF;
    
    IF NEW.status IS NULL THEN
        SET NEW.status = 'pending';
    END IF;
    
    IF NEW.payment_status IS NULL THEN
        SET NEW.payment_status = 'pending';
    END IF;
END //

-- Enhanced trigger untuk log pembatalan booking
CREATE TRIGGER log_rental_cancellation
AFTER UPDATE ON rentals
FOR EACH ROW
BEGIN
    DECLARE v_user_name VARCHAR(100);
    DECLARE v_motorcycle_name VARCHAR(100);
    
    IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
        -- Get user and motorcycle names
        SELECT u.full_name, m.name INTO v_user_name, v_motorcycle_name
        FROM users u, motorcycles m
        WHERE u.id = NEW.user_id AND m.id = NEW.motorcycle_id;
        
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NEW.id, NEW.user_id, NEW.motorcycle_id, 'CANCELLED', 
                CONCAT('Rental dibatalkan untuk ', v_user_name, ' - Motor: ', v_motorcycle_name, 
                       '. Periode: ', OLD.rental_date, ' sampai ', OLD.return_date,
                       '. Total refund: Rp ', FORMAT(OLD.total_price, 0)));
        
        -- Kembalikan slot yang tersedia
        UPDATE motorcycles 
        SET available_slots = available_slots + 1 
        WHERE id = NEW.motorcycle_id;
    END IF;
    
    -- Log status changes
    IF OLD.status != NEW.status THEN
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NEW.id, NEW.user_id, NEW.motorcycle_id, 'STATUS_CHANGED', 
                CONCAT('Status berubah dari ', OLD.status, ' ke ', NEW.status));
    END IF;
    
    -- Log payment status changes
    IF OLD.payment_status != NEW.payment_status THEN
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NEW.id, NEW.user_id, NEW.motorcycle_id, 'PAYMENT_STATUS_CHANGED', 
                CONCAT('Status pembayaran berubah dari ', OLD.payment_status, ' ke ', NEW.payment_status));
    END IF;
END //

-- Enhanced trigger untuk log penghapusan booking
CREATE TRIGGER log_rental_deletion
BEFORE DELETE ON rentals
FOR EACH ROW
BEGIN
    DECLARE v_user_name VARCHAR(100);
    DECLARE v_motorcycle_name VARCHAR(100);
    
    -- Get user and motorcycle names
    SELECT u.full_name, m.name INTO v_user_name, v_motorcycle_name
    FROM users u, motorcycles m
    WHERE u.id = OLD.user_id AND m.id = OLD.motorcycle_id;
    
    INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
    VALUES (OLD.id, OLD.user_id, OLD.motorcycle_id, 'DELETED', 
            CONCAT('Rental dihapus untuk ', v_user_name, ' - Motor: ', v_motorcycle_name,
                   '. Periode: ', OLD.rental_date, ' sampai ', OLD.return_date,
                   '. Status terakhir: ', OLD.status, ', Payment: ', OLD.payment_status));
    
    -- Kembalikan slot jika status bukan cancelled
    IF OLD.status != 'cancelled' THEN
        UPDATE motorcycles 
        SET available_slots = available_slots + 1 
        WHERE id = OLD.motorcycle_id;
    END IF;
END //

-- Trigger untuk log perubahan data user
CREATE TRIGGER log_user_changes
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE v_changes TEXT DEFAULT '';
    
    IF OLD.full_name != NEW.full_name THEN
        SET v_changes = CONCAT(v_changes, 'Nama: ', OLD.full_name, ' -> ', NEW.full_name, '; ');
    END IF;
    
    IF OLD.email != NEW.email THEN
        SET v_changes = CONCAT(v_changes, 'Email: ', OLD.email, ' -> ', NEW.email, '; ');
    END IF;
    
    IF OLD.phone != NEW.phone THEN
        SET v_changes = CONCAT(v_changes, 'Phone: ', IFNULL(OLD.phone, 'NULL'), ' -> ', IFNULL(NEW.phone, 'NULL'), '; ');
    END IF;
    
    IF v_changes != '' THEN
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NULL, NEW.id, NULL, 'USER_UPDATED', 
                CONCAT('Data user diupdate: ', v_changes));
    END IF;
END //

-- Trigger untuk log perubahan data motor
CREATE TRIGGER log_motorcycle_changes
AFTER UPDATE ON motorcycles
FOR EACH ROW
BEGIN
    DECLARE v_changes TEXT DEFAULT '';
    
    IF OLD.name != NEW.name THEN
        SET v_changes = CONCAT(v_changes, 'Nama: ', OLD.name, ' -> ', NEW.name, '; ');
    END IF;
    
    IF OLD.price_per_day != NEW.price_per_day THEN
        SET v_changes = CONCAT(v_changes, 'Harga: ', OLD.price_per_day, ' -> ', NEW.price_per_day, '; ');
    END IF;
    
    IF OLD.total_slots != NEW.total_slots THEN
        SET v_changes = CONCAT(v_changes, 'Total Slot: ', OLD.total_slots, ' -> ', NEW.total_slots, '; ');
    END IF;
    
    IF OLD.available_slots != NEW.available_slots THEN
        SET v_changes = CONCAT(v_changes, 'Available Slot: ', OLD.available_slots, ' -> ', NEW.available_slots, '; ');
    END IF;
    
    IF v_changes != '' THEN
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NULL, NULL, NEW.id, 'MOTORCYCLE_UPDATED', 
                CONCAT('Data motor diupdate: ', v_changes));
    END IF;
END //

-- Trigger untuk auto update status rental yang overdue
CREATE TRIGGER auto_update_rental_status
BEFORE UPDATE ON rentals
FOR EACH ROW
BEGIN
    -- Auto mark as overdue if return date has passed
    IF NEW.status = 'confirmed' AND NEW.return_date < CURDATE() THEN
        SET NEW.status = 'overdue';
        
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NEW.id, NEW.user_id, NEW.motorcycle_id, 'AUTO_OVERDUE', 
                CONCAT('Rental otomatis ditandai overdue. Tanggal kembali: ', NEW.return_date));
    END IF;
END //
DELIMITER ;
