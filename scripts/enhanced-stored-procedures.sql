USE naripa_wheels;

-- Drop existing procedures if they exist
DROP PROCEDURE IF EXISTS buatPenyewaan;
DROP PROCEDURE IF EXISTS konfirmasiPenyewaan;
DROP PROCEDURE IF EXISTS batalkanPenyewaan;
DROP PROCEDURE IF EXISTS selesaikanPenyewaan;
DROP PROCEDURE IF EXISTS updateStokMotor;

-- Enhanced Stored Procedure untuk membuat penyewaan dengan transaction
DELIMITER //
CREATE PROCEDURE buatPenyewaan(
    IN p_user_id INT,
    IN p_motorcycle_id INT,
    IN p_rental_date DATE,
    IN p_return_date DATE,
    OUT p_result VARCHAR(255),
    OUT p_rental_id INT
)
BEGIN
    DECLARE v_available_slots INT;
    DECLARE v_price_per_day DECIMAL(10,2);
    DECLARE v_total_days INT;
    DECLARE v_total_price DECIMAL(10,2);
    DECLARE v_motorcycle_name VARCHAR(100);
    DECLARE v_user_name VARCHAR(100);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error: Gagal membuat penyewaan - Terjadi kesalahan database';
        SET p_rental_id = 0;
    END;

    START TRANSACTION;
    
    -- Validasi input
    IF p_rental_date >= p_return_date THEN
        SET p_result = 'Error: Tanggal kembali harus setelah tanggal sewa';
        SET p_rental_id = 0;
        ROLLBACK;
    ELSEIF p_rental_date < CURDATE() THEN
        SET p_result = 'Error: Tanggal sewa tidak boleh di masa lalu';
        SET p_rental_id = 0;
        ROLLBACK;
    ELSE
        -- Cek ketersediaan motor dengan locking
        SELECT available_slots, price_per_day, name 
        INTO v_available_slots, v_price_per_day, v_motorcycle_name
        FROM motorcycles 
        WHERE id = p_motorcycle_id
        FOR UPDATE;
        
        -- Cek nama user
        SELECT full_name INTO v_user_name
        FROM users WHERE id = p_user_id;
        
        IF v_available_slots IS NULL THEN
            SET p_result = 'Error: Motor tidak ditemukan';
            SET p_rental_id = 0;
            ROLLBACK;
        ELSEIF v_available_slots <= 0 THEN
            SET p_result = 'Error: Motor tidak tersedia';
            SET p_rental_id = 0;
            ROLLBACK;
        ELSE
            -- Cek konflik jadwal dengan existing bookings
            IF EXISTS (
                SELECT 1 FROM rentals 
                WHERE motorcycle_id = p_motorcycle_id 
                AND status IN ('pending', 'confirmed')
                AND (
                    (rental_date <= p_rental_date AND return_date > p_rental_date) OR
                    (rental_date < p_return_date AND return_date >= p_return_date) OR
                    (rental_date >= p_rental_date AND return_date <= p_return_date)
                )
            ) THEN
                SET p_result = 'Error: Motor sudah dibooking untuk tanggal tersebut';
                SET p_rental_id = 0;
                ROLLBACK;
            ELSE
                -- Hitung total hari dan harga
                SET v_total_days = DATEDIFF(p_return_date, p_rental_date);
                SET v_total_price = v_total_days * v_price_per_day;
                
                -- Insert rental
                INSERT INTO rentals (user_id, motorcycle_id, rental_date, return_date, total_days, total_price, status, payment_status)
                VALUES (p_user_id, p_motorcycle_id, p_rental_date, p_return_date, v_total_days, v_total_price, 'pending', 'pending');
                
                SET p_rental_id = LAST_INSERT_ID();
                
                -- Update available slots
                UPDATE motorcycles 
                SET available_slots = available_slots - 1 
                WHERE id = p_motorcycle_id;
                
                -- Log activity
                INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
                VALUES (p_rental_id, p_user_id, p_motorcycle_id, 'CREATED', 
                        CONCAT('Penyewaan dibuat oleh ', v_user_name, ' untuk motor ', v_motorcycle_name, 
                               ' dari ', p_rental_date, ' sampai ', p_return_date));
                
                SET p_result = CONCAT('Success: Penyewaan berhasil dibuat dengan ID #', p_rental_id);
                COMMIT;
            END IF;
        END IF;
    END IF;
END //

-- Stored Procedure untuk konfirmasi penyewaan
CREATE PROCEDURE konfirmasiPenyewaan(
    IN p_rental_id INT,
    IN p_admin_id INT,
    OUT p_result VARCHAR(255)
)
BEGIN
    DECLARE v_rental_status VARCHAR(20);
    DECLARE v_payment_status VARCHAR(20);
    DECLARE v_user_name VARCHAR(100);
    DECLARE v_motorcycle_name VARCHAR(100);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error: Gagal mengkonfirmasi penyewaan';
    END;

    START TRANSACTION;
    
    -- Cek status rental
    SELECT r.status, r.payment_status, u.full_name, m.name
    INTO v_rental_status, v_payment_status, v_user_name, v_motorcycle_name
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN motorcycles m ON r.motorcycle_id = m.id
    WHERE r.id = p_rental_id;
    
    IF v_rental_status IS NULL THEN
        SET p_result = 'Error: Penyewaan tidak ditemukan';
        ROLLBACK;
    ELSEIF v_rental_status != 'pending' THEN
        SET p_result = 'Error: Penyewaan sudah dikonfirmasi atau dibatalkan';
        ROLLBACK;
    ELSEIF v_payment_status != 'paid' THEN
        SET p_result = 'Error: Pembayaran belum dikonfirmasi';
        ROLLBACK;
    ELSE
        -- Update status
        UPDATE rentals 
        SET status = 'confirmed', 
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_rental_id;
        
        -- Log activity
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        SELECT p_rental_id, user_id, motorcycle_id, 'CONFIRMED',
               CONCAT('Penyewaan dikonfirmasi oleh admin untuk ', v_user_name, ' - ', v_motorcycle_name)
        FROM rentals WHERE id = p_rental_id;
        
        SET p_result = 'Success: Penyewaan berhasil dikonfirmasi';
        COMMIT;
    END IF;
END //

-- Stored Procedure untuk membatalkan penyewaan
CREATE PROCEDURE batalkanPenyewaan(
    IN p_rental_id INT,
    IN p_user_id INT,
    IN p_reason TEXT,
    OUT p_result VARCHAR(255)
)
BEGIN
    DECLARE v_rental_status VARCHAR(20);
    DECLARE v_rental_user_id INT;
    DECLARE v_motorcycle_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error: Gagal membatalkan penyewaan';
    END;

    START TRANSACTION;
    
    -- Cek rental
    SELECT status, user_id, motorcycle_id
    INTO v_rental_status, v_rental_user_id, v_motorcycle_id
    FROM rentals 
    WHERE id = p_rental_id;
    
    IF v_rental_status IS NULL THEN
        SET p_result = 'Error: Penyewaan tidak ditemukan';
        ROLLBACK;
    ELSEIF v_rental_user_id != p_user_id THEN
        SET p_result = 'Error: Anda tidak memiliki akses untuk membatalkan penyewaan ini';
        ROLLBACK;
    ELSEIF v_rental_status NOT IN ('pending', 'confirmed') THEN
        SET p_result = 'Error: Penyewaan tidak dapat dibatalkan';
        ROLLBACK;
    ELSE
        -- Update status
        UPDATE rentals 
        SET status = 'cancelled',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_rental_id;
        
        -- Kembalikan slot
        UPDATE motorcycles 
        SET available_slots = available_slots + 1 
        WHERE id = v_motorcycle_id;
        
        -- Log activity
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (p_rental_id, p_user_id, v_motorcycle_id, 'CANCELLED', 
                CONCAT('Penyewaan dibatalkan oleh user. Alasan: ', IFNULL(p_reason, 'Tidak ada alasan')));
        
        SET p_result = 'Success: Penyewaan berhasil dibatalkan';
        COMMIT;
    END IF;
END //

-- Stored Procedure untuk menyelesaikan penyewaan
CREATE PROCEDURE selesaikanPenyewaan(
    IN p_rental_id INT,
    IN p_admin_id INT,
    OUT p_result VARCHAR(255)
)
BEGIN
    DECLARE v_rental_status VARCHAR(20);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error: Gagal menyelesaikan penyewaan';
    END;

    START TRANSACTION;
    
    SELECT status INTO v_rental_status
    FROM rentals WHERE id = p_rental_id;
    
    IF v_rental_status IS NULL THEN
        SET p_result = 'Error: Penyewaan tidak ditemukan';
        ROLLBACK;
    ELSEIF v_rental_status != 'confirmed' THEN
        SET p_result = 'Error: Penyewaan belum dikonfirmasi';
        ROLLBACK;
    ELSE
        UPDATE rentals 
        SET status = 'completed',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = p_rental_id;
        
        -- Log activity
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        SELECT p_rental_id, user_id, motorcycle_id, 'COMPLETED',
               'Penyewaan diselesaikan oleh admin'
        FROM rentals WHERE id = p_rental_id;
        
        SET p_result = 'Success: Penyewaan berhasil diselesaikan';
        COMMIT;
    END IF;
END //

-- Stored Procedure untuk update stok motor
CREATE PROCEDURE updateStokMotor(
    IN p_motorcycle_id INT,
    IN p_new_total_slots INT,
    OUT p_result VARCHAR(255)
)
BEGIN
    DECLARE v_current_available INT;
    DECLARE v_current_total INT;
    DECLARE v_active_rentals INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error: Gagal mengupdate stok motor';
    END;

    START TRANSACTION;
    
    -- Cek motor dan hitung rental aktif
    SELECT available_slots, total_slots INTO v_current_available, v_current_total
    FROM motorcycles WHERE id = p_motorcycle_id;
    
    SELECT COUNT(*) INTO v_active_rentals
    FROM rentals 
    WHERE motorcycle_id = p_motorcycle_id 
    AND status IN ('pending', 'confirmed');
    
    IF v_current_total IS NULL THEN
        SET p_result = 'Error: Motor tidak ditemukan';
        ROLLBACK;
    ELSEIF p_new_total_slots < v_active_rentals THEN
        SET p_result = CONCAT('Error: Tidak dapat mengurangi stok. Ada ', v_active_rentals, ' penyewaan aktif');
        ROLLBACK;
    ELSE
        -- Update stok
        UPDATE motorcycles 
        SET total_slots = p_new_total_slots,
            available_slots = p_new_total_slots - v_active_rentals
        WHERE id = p_motorcycle_id;
        
        SET p_result = 'Success: Stok motor berhasil diupdate';
        COMMIT;
    END IF;
END //
DELIMITER ;
