USE naripa_wheels;

-- Stored Procedure untuk membuat penyewaan
DELIMITER //
CREATE PROCEDURE buatPenyewaan(
    IN p_user_id INT,
    IN p_motorcycle_id INT,
    IN p_rental_date DATE,
    IN p_return_date DATE,
    OUT p_result VARCHAR(255)
)
BEGIN
    DECLARE v_available_slots INT;
    DECLARE v_price_per_day DECIMAL(10,2);
    DECLARE v_total_days INT;
    DECLARE v_total_price DECIMAL(10,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'Error: Gagal membuat penyewaan';
    END;

    START TRANSACTION;
    
    -- Validasi tanggal
    IF p_rental_date >= p_return_date THEN
        SET p_result = 'Error: Tanggal kembali harus setelah tanggal sewa';
        ROLLBACK;
    ELSE
        -- Cek ketersediaan
        SELECT available_slots, price_per_day 
        INTO v_available_slots, v_price_per_day
        FROM motorcycles 
        WHERE id = p_motorcycle_id;
        
        IF v_available_slots > 0 THEN
            -- Hitung total hari dan harga
            SET v_total_days = DATEDIFF(p_return_date, p_rental_date);
            SET v_total_price = v_total_days * v_price_per_day;
            
            -- Insert rental
            INSERT INTO rentals (user_id, motorcycle_id, rental_date, return_date, total_days, total_price)
            VALUES (p_user_id, p_motorcycle_id, p_rental_date, p_return_date, v_total_days, v_total_price);
            
            -- Update available slots
            UPDATE motorcycles 
            SET available_slots = available_slots - 1 
            WHERE id = p_motorcycle_id;
            
            SET p_result = 'Success: Penyewaan berhasil dibuat';
            COMMIT;
        ELSE
            SET p_result = 'Error: Motor tidak tersedia';
            ROLLBACK;
        END IF;
    END IF;
END //
DELIMITER ;

-- Function untuk cek ketersediaan
DELIMITER //
CREATE FUNCTION cekKetersediaan(p_motorcycle_id INT, p_rental_date DATE, p_return_date DATE)
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_available INT DEFAULT 0;
    DECLARE v_conflicting_rentals INT DEFAULT 0;
    
    -- Cek slot tersedia
    SELECT available_slots INTO v_available
    FROM motorcycles 
    WHERE id = p_motorcycle_id;
    
    -- Cek konflik jadwal
    SELECT COUNT(*) INTO v_conflicting_rentals
    FROM rentals 
    WHERE motorcycle_id = p_motorcycle_id 
    AND status IN ('confirmed', 'pending')
    AND (
        (rental_date <= p_rental_date AND return_date > p_rental_date) OR
        (rental_date < p_return_date AND return_date >= p_return_date) OR
        (rental_date >= p_rental_date AND return_date <= p_return_date)
    );
    
    IF v_available > 0 AND v_conflicting_rentals = 0 THEN
        RETURN 1;
    ELSE
        RETURN 0;
    END IF;
END //
DELIMITER ;
