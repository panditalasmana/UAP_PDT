<?php
// Database monitoring script - can be run via cron job
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check database health
function checkDatabaseHealth($db) {
    $health_report = [];
    
    try {
        // Check table sizes
        $query = "SELECT 
                    table_name,
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
                  FROM information_schema.tables 
                  WHERE table_schema = 'naripa_wheels'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $health_report['table_sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check for overdue rentals
        $query = "SELECT COUNT(*) as count FROM rentals WHERE status = 'confirmed' AND return_date < CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $health_report['overdue_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Check for pending payments > 24 hours
        $query = "SELECT COUNT(*) as count FROM rentals WHERE payment_status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $health_report['old_pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Check backup status
        $query = "SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $health_report['last_backup'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check event scheduler status
        $query = "SHOW VARIABLES LIKE 'event_scheduler'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $health_report['event_scheduler'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $health_report['status'] = 'healthy';
        $health_report['timestamp'] = date('Y-m-d H:i:s');
        
    } catch (Exception $e) {
        $health_report['status'] = 'error';
        $health_report['error'] = $e->getMessage();
        $health_report['timestamp'] = date('Y-m-d H:i:s');
    }
    
    return $health_report;
}

// Run health check
$health_report = checkDatabaseHealth($db);

// Log health check
try {
    $query = "INSERT INTO rental_history (rental_id, user_id, motorcycle_id, action, details) VALUES (NULL, NULL, NULL, 'HEALTH_CHECK', ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([json_encode($health_report)]);
} catch (Exception $e) {
    error_log("Failed to log health check: " . $e->getMessage());
}

// Output for cron job logging
echo "Database Health Check - " . $health_report['timestamp'] . "\n";
echo "Status: " . $health_report['status'] . "\n";
if (isset($health_report['overdue_rentals'])) {
    echo "Overdue Rentals: " . $health_report['overdue_rentals'] . "\n";
}
if (isset($health_report['old_pending_payments'])) {
    echo "Old Pending Payments: " . $health_report['old_pending_payments'] . "\n";
}
if (isset($health_report['last_backup'])) {
    echo "Last Backup: " . $health_report['last_backup']['created_at'] . " (" . $health_report['last_backup']['status'] . ")\n";
}
echo "Event Scheduler: " . ($health_report['event_scheduler']['Value'] ?? 'Unknown') . "\n";
echo "---\n";
?>
