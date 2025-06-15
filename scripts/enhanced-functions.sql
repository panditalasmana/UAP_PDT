USE naripa_wheels;

-- Drop existing functions
DROP FUNCTION IF EXISTS cekKetersediaan;
DROP FUNCTION IF EXISTS hitungTotalHarga;
DROP FUNCTION IF EXISTS getStatusPenyewaan;
DROP FUNCTION IF EXISTS hitungPendapatanBulan;
DROP FUNCTION IF EXISTS cekKonflikJadwal;

-- Enhanced Function untuk cek ketersediaan
DELIMITER //
CREATE FUNCTION cekKetersediaan(
    p_motorcycle_id INT, 
    p_rental_date DATE, 
    p_return_date DATE
)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_available_slots INT DEFAULT 0;
    DECLARE v_total_slots INT DEFAULT 0;
    DECLARE v_conflicting_rentals INT DEFAULT 0;
    DECLARE v_price_per_day DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_days INT DEFAULT 0;
    DECLARE v_total_price DECIMAL(10,2) DEFAULT 0;
    DECLARE v_result JSON;
    
    -- Cek slot tersedia dan harga
    SELECT available_slots, total_slots, price_per_day 
    INTO v_available_slots, v_total_slots, v_price_per_day
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
    
    -- Hitung total hari dan harga
    SET v_total_days = DATEDIFF(p_return_date, p_rental_date);
    SET v_total_price = v_total_days * v_price_per_day;
    
    -- Buat JSON result
    SET v_result = JSON_OBJECT(
        'available', IF(v_available_slots > 0 AND v_conflicting_rentals = 0, 1, 0),
        'available_slots', IFNULL(v_available_slots, 0),
        'total_slots', IFNULL(v_total_slots, 0),
        'conflicting_rentals', v_conflicting_rentals,
        'total_days', v_total_days,
        'price_per_day', IFNULL(v_price_per_day, 0),
        'total_price', v_total_price,
        'message', CASE 
            WHEN v_available_slots IS NULL THEN 'Motor tidak ditemukan'
            WHEN v_available_slots <= 0 THEN 'Motor tidak tersedia'
            WHEN v_conflicting_rentals > 0 THEN 'Jadwal bentrok dengan booking lain'
            ELSE 'Motor tersedia'
        END
    );
    
    RETURN v_result;
END //

-- Function untuk hitung total harga dengan diskon
CREATE FUNCTION hitungTotalHarga(
    p_motorcycle_id INT,
    p_total_days INT,
    p_user_id INT
)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_price_per_day DECIMAL(10,2);
    DECLARE v_base_price DECIMAL(10,2);
    DECLARE v_discount_percent DECIMAL(5,2) DEFAULT 0;
    DECLARE v_discount_amount DECIMAL(10,2) DEFAULT 0;
    DECLARE v_final_price DECIMAL(10,2);
    DECLARE v_user_total_rentals INT;
    DECLARE v_result JSON;
    
    -- Get price per day
    SELECT price_per_day INTO v_price_per_day
    FROM motorcycles WHERE id = p_motorcycle_id;
    
    -- Calculate base price
    SET v_base_price = v_price_per_day * p_total_days;
    
    -- Get user's total completed rentals for loyalty discount
    SELECT COUNT(*) INTO v_user_total_rentals
    FROM rentals 
    WHERE user_id = p_user_id AND status = 'completed';
    
    -- Calculate discount based on loyalty and duration
    SET v_discount_percent = CASE
        WHEN v_user_total_rentals >= 10 THEN 15  -- 15% for 10+ rentals
        WHEN v_user_total_rentals >= 5 THEN 10   -- 10% for 5+ rentals
        WHEN v_user_total_rentals >= 2 THEN 5    -- 5% for 2+ rentals
        ELSE 0
    END;
    
    -- Additional discount for long term rental
    IF p_total_days >= 7 THEN
        SET v_discount_percent = v_discount_percent + 5;  -- Additional 5% for weekly rental
    END IF;
    
    IF p_total_days >= 30 THEN
        SET v_discount_percent = v_discount_percent + 10; -- Additional 10% for monthly rental
    END IF;
    
    -- Calculate final price
    SET v_discount_amount = v_base_price * (v_discount_percent / 100);
    SET v_final_price = v_base_price - v_discount_amount;
    
    SET v_result = JSON_OBJECT(
        'base_price', v_base_price,
        'discount_percent', v_discount_percent,
        'discount_amount', v_discount_amount,
        'final_price', v_final_price,
        'price_per_day', v_price_per_day,
        'total_days', p_total_days,
        'user_loyalty_level', CASE
            WHEN v_user_total_rentals >= 10 THEN 'Platinum'
            WHEN v_user_total_rentals >= 5 THEN 'Gold'
            WHEN v_user_total_rentals >= 2 THEN 'Silver'
            ELSE 'Bronze'
        END
    );
    
    RETURN v_result;
END //

-- Function untuk get status penyewaan detail
CREATE FUNCTION getStatusPenyewaan(p_rental_id INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_result JSON;
    
    SELECT JSON_OBJECT(
        'rental_id', r.id,
        'status', r.status,
        'payment_status', r.payment_status,
        'user_name', u.full_name,
        'motorcycle_name', m.name,
        'rental_date', r.rental_date,
        'return_date', r.return_date,
        'total_days', r.total_days,
        'total_price', r.total_price,
        'created_at', r.created_at,
        'days_until_rental', DATEDIFF(r.rental_date, CURDATE()),
        'is_overdue', IF(r.status = 'confirmed' AND r.return_date < CURDATE(), 1, 0),
        'can_cancel', IF(r.status IN ('pending') AND r.rental_date > CURDATE(), 1, 0)
    ) INTO v_result
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN motorcycles m ON r.motorcycle_id = m.id
    WHERE r.id = p_rental_id;
    
    RETURN IFNULL(v_result, JSON_OBJECT('error', 'Rental not found'));
END //

-- Function untuk hitung pendapatan bulan
CREATE FUNCTION hitungPendapatanBulan(p_year INT, p_month INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_total_revenue DECIMAL(12,2) DEFAULT 0;
    DECLARE v_total_rentals INT DEFAULT 0;
    DECLARE v_avg_revenue DECIMAL(10,2) DEFAULT 0;
    DECLARE v_completed_rentals INT DEFAULT 0;
    DECLARE v_cancelled_rentals INT DEFAULT 0;
    DECLARE v_result JSON;
    
    SELECT 
        IFNULL(SUM(CASE WHEN status != 'cancelled' THEN total_price ELSE 0 END), 0),
        COUNT(*),
        COUNT(CASE WHEN status = 'completed' THEN 1 END),
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END)
    INTO v_total_revenue, v_total_rentals, v_completed_rentals, v_cancelled_rentals
    FROM rentals 
    WHERE YEAR(created_at) = p_year 
    AND MONTH(created_at) = p_month;
    
    SET v_avg_revenue = IF(v_completed_rentals > 0, v_total_revenue / v_completed_rentals, 0);
    
    SET v_result = JSON_OBJECT(
        'year', p_year,
        'month', p_month,
        'total_revenue', v_total_revenue,
        'total_rentals', v_total_rentals,
        'completed_rentals', v_completed_rentals,
        'cancelled_rentals', v_cancelled_rentals,
        'average_revenue', v_avg_revenue,
        'success_rate', IF(v_total_rentals > 0, (v_completed_rentals / v_total_rentals) * 100, 0)
    );
    
    RETURN v_result;
END //
DELIMITER ;
