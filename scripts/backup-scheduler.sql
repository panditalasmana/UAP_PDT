-- Task Scheduler untuk backup otomatis
-- Untuk MySQL Event Scheduler
SET GLOBAL event_scheduler = ON;

DELIMITER //
CREATE EVENT IF NOT EXISTS daily_backup
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE, '23:59:00')
DO
BEGIN
    -- Backup rentals table
    SET @backup_query = CONCAT(
        'SELECT * FROM rentals WHERE DATE(created_at) = CURDATE() ',
        'INTO OUTFILE "/var/backups/naripa_wheels/rentals_backup_', 
        DATE_FORMAT(NOW(), '%Y%m%d'), '.csv" ',
        'FIELDS TERMINATED BY "," ENCLOSED BY "\\"" LINES TERMINATED BY "\\n"'
    );
    
    PREPARE stmt FROM @backup_query;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END //
DELIMITER ;
