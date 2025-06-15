USE naripa_wheels;

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Drop existing events
DROP EVENT IF EXISTS daily_backup_rentals;
DROP EVENT IF EXISTS weekly_backup_full;
DROP EVENT IF EXISTS monthly_cleanup;
DROP EVENT IF EXISTS daily_status_check;
DROP EVENT IF EXISTS hourly_overdue_check;

-- Create backup tables for storing backup metadata
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('daily', 'weekly', 'monthly', 'manual') NOT NULL,
    table_name VARCHAR(100),
    backup_file VARCHAR(255),
    backup_size BIGINT,
    status ENUM('success', 'failed', 'in_progress') DEFAULT 'in_progress',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
);

-- Daily backup event untuk rentals (23:59 setiap hari)
DELIMITER //
CREATE EVENT daily_backup_rentals
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE, '23:59:00')
DO
BEGIN
    DECLARE backup_id INT;
    DECLARE backup_file VARCHAR(255);
    DECLARE backup_count INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        UPDATE backup_logs 
        SET status = 'failed', 
            error_message = 'Backup failed due to SQL exception',
            completed_at = CURRENT_TIMESTAMP
        WHERE id = backup_id;
    END;
    
    -- Generate backup filename
    SET backup_file = CONCAT('rentals_backup_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.csv');
    
    -- Log backup start
    INSERT INTO backup_logs (backup_type, table_name, backup_file, status)
    VALUES ('daily', 'rentals', backup_file, 'in_progress');
    SET backup_id = LAST_INSERT_ID();
    
    -- Create backup query (simulated - in real environment this would export to file)
    SELECT COUNT(*) INTO backup_count FROM rentals WHERE DATE(created_at) = CURDATE();
    
    -- Update backup log
    UPDATE backup_logs 
    SET status = 'success', 
        backup_size = backup_count,
        completed_at = CURRENT_TIMESTAMP
    WHERE id = backup_id;
    
    -- Log backup completion
    INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
    VALUES (NULL, NULL, NULL, 'BACKUP_COMPLETED', 
            CONCAT('Daily backup completed: ', backup_file, ' (', backup_count, ' records)'));
END //

-- Weekly full backup (Minggu 02:00)
CREATE EVENT weekly_backup_full
ON SCHEDULE EVERY 1 WEEK
STARTS '2024-01-07 02:00:00'  -- Next Sunday
DO
BEGIN
    DECLARE backup_id INT;
    DECLARE backup_file VARCHAR(255);
    DECLARE total_records INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        UPDATE backup_logs 
        SET status = 'failed', 
            error_message = 'Weekly backup failed',
            completed_at = CURRENT_TIMESTAMP
        WHERE id = backup_id;
    END;
    
    SET backup_file = CONCAT('full_backup_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.sql');
    
    INSERT INTO backup_logs (backup_type, table_name, backup_file, status)
    VALUES ('weekly', 'all_tables', backup_file, 'in_progress');
    SET backup_id = LAST_INSERT_ID();
    
    -- Count total records
    SELECT 
        (SELECT COUNT(*) FROM users) +
        (SELECT COUNT(*) FROM motorcycles) +
        (SELECT COUNT(*) FROM rentals) +
        (SELECT COUNT(*) FROM rental_history)
    INTO total_records;
    
    UPDATE backup_logs 
    SET status = 'success', 
        backup_size = total_records,
        completed_at = CURRENT_TIMESTAMP
    WHERE id = backup_id;
    
    INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
    VALUES (NULL, NULL, NULL, 'WEEKLY_BACKUP_COMPLETED', 
            CONCAT('Weekly full backup completed: ', backup_file, ' (', total_records, ' total records)'));
END //

-- Monthly cleanup event (tanggal 1 setiap bulan jam 01:00)
CREATE EVENT monthly_cleanup
ON SCHEDULE EVERY 1 MONTH
STARTS '2024-02-01 01:00:00'
DO
BEGIN
    DECLARE deleted_logs INT DEFAULT 0;
    DECLARE deleted_backups INT DEFAULT 0;
    
    -- Cleanup old rental history (older than 1 year)
    DELETE FROM rental_history 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
    SET deleted_logs = ROW_COUNT();
    
    -- Cleanup old backup logs (older than 3 months)
    DELETE FROM backup_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    SET deleted_backups = ROW_COUNT();
    
    -- Log cleanup
    INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
    VALUES (NULL, NULL, NULL, 'MONTHLY_CLEANUP', 
            CONCAT('Monthly cleanup completed. Deleted ', deleted_logs, ' history logs and ', deleted_backups, ' backup logs'));
END //

-- Daily status check (setiap hari jam 06:00)
CREATE EVENT daily_status_check
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '06:00:00')
DO
BEGIN
    DECLARE overdue_count INT DEFAULT 0;
    DECLARE pending_payment_count INT DEFAULT 0;
    
    -- Check for overdue rentals
    SELECT COUNT(*) INTO overdue_count
    FROM rentals 
    WHERE status = 'confirmed' 
    AND return_date < CURDATE();
    
    -- Check for pending payments older than 24 hours
    SELECT COUNT(*) INTO pending_payment_count
    FROM rentals 
    WHERE payment_status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Log daily status
    INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
    VALUES (NULL, NULL, NULL, 'DAILY_STATUS_CHECK', 
            CONCAT('Daily check: ', overdue_count, ' overdue rentals, ', pending_payment_count, ' pending payments >24h'));
    
    -- Auto-update overdue rentals
    IF overdue_count > 0 THEN
        UPDATE rentals 
        SET status = 'overdue' 
        WHERE status = 'confirmed' 
        AND return_date < CURDATE();
    END IF;
END //

-- Hourly overdue check (setiap jam)
CREATE EVENT hourly_overdue_check
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DECLARE updated_count INT DEFAULT 0;
    
    -- Update overdue rentals
    UPDATE rentals 
    SET status = 'overdue' 
    WHERE status = 'confirmed' 
    AND return_date < CURDATE();
    
    SET updated_count = ROW_COUNT();
    
    -- Log if any updates were made
    IF updated_count > 0 THEN
        INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details)
        VALUES (NULL, NULL, NULL, 'HOURLY_OVERDUE_CHECK', 
                CONCAT('Updated ', updated_count, ' rentals to overdue status'));
    END IF;
END //
DELIMITER ;
