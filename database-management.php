<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireAdmin();

$page_title = "Database Management";
$database = new Database();
$db = $database->getConnection();

// Handle manual backup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['manual_backup'])) {
    try {
        $backup_type = $_POST['backup_type'];
        $backup_file = 'manual_backup_' . date('Ymd_His') . '.sql';
        
        // Insert backup log
        $query = "INSERT INTO backup_logs (backup_type, table_name, backup_file, status) VALUES (?, 'manual', ?, 'success')";
        $stmt = $db->prepare($query);
        $stmt->execute(['manual', $backup_file]);
        
        $_SESSION['message'] = "Manual backup berhasil dibuat: " . $backup_file;
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['message'] = "Gagal membuat backup: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    header("Location: database-management.php");
    exit();
}

// Handle procedure execution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_procedure'])) {
    try {
        $procedure_name = $_POST['procedure_name'];
        
        switch ($procedure_name) {
            case 'buatPenyewaan':
                $query = "CALL buatPenyewaan(1, 1, CURDATE() + INTERVAL 1 DAY, CURDATE() + INTERVAL 3 DAY, @result, @rental_id)";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $stmt = $db->query("SELECT @result as result, @rental_id as rental_id");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['message'] = "Procedure result: " . $result['result'] . " (Rental ID: " . $result['rental_id'] . ")";
                $_SESSION['message_type'] = "info";
                break;
                
            case 'updateStokMotor':
                $query = "CALL updateStokMotor(1, 10, @result)";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $stmt = $db->query("SELECT @result as result");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $_SESSION['message'] = "Procedure result: " . $result['result'];
                $_SESSION['message_type'] = "info";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error executing procedure: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    header("Location: database-management.php");
    exit();
}

// Get backup logs
$query = "SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute();
$backup_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent rental history
$query = "SELECT * FROM rental_history WHERE action IN ('BACKUP_COMPLETED', 'WEEKLY_BACKUP_COMPLETED', 'MONTHLY_CLEANUP', 'DAILY_STATUS_CHECK') ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$system_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Test functions
$availability_test = null;
$pricing_test = null;
$revenue_test = null;

try {
    // Test cekKetersediaan function
    $query = "SELECT cekKetersediaan(1, CURDATE() + INTERVAL 1 DAY, CURDATE() + INTERVAL 3 DAY) as result";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $availability_test = json_decode($stmt->fetch(PDO::FETCH_ASSOC)['result'], true);
    
    // Test hitungTotalHarga function
    $query = "SELECT hitungTotalHarga(1, 3, 1) as result";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pricing_test = json_decode($stmt->fetch(PDO::FETCH_ASSOC)['result'], true);
    
    // Test hitungPendapatanBulan function
    $query = "SELECT hitungPendapatanBulan(YEAR(CURDATE()), MONTH(CURDATE())) as result";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $revenue_test = json_decode($stmt->fetch(PDO::FETCH_ASSOC)['result'], true);
} catch (Exception $e) {
    // Handle function test errors
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-md-block sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="motorcycles.php">
                            <i class="bi bi-motorcycle"></i> Kelola Motor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rentals.php">
                            <i class="bi bi-calendar-check"></i> Kelola Penyewaan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Kelola User
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-graph-up"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="database-management.php">
                            <i class="bi bi-database"></i> Database Management
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 main-content">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Database Management</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Manual Backup -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Manual Backup</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="backup_type" class="form-label">Backup Type</label>
                                    <select class="form-select" id="backup_type" name="backup_type" required>
                                        <option value="manual">Manual Full Backup</option>
                                        <option value="rentals_only">Rentals Only</option>
                                        <option value="users_only">Users Only</option>
                                    </select>
                                </div>
                                <button type="submit" name="manual_backup" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Create Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Test Procedures -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Test Stored Procedures</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="procedure_name" class="form-label">Procedure</label>
                                    <select class="form-select" id="procedure_name" name="procedure_name" required>
                                        <option value="buatPenyewaan">Test buatPenyewaan</option>
                                        <option value="updateStokMotor">Test updateStokMotor</option>
                                    </select>
                                </div>
                                <button type="submit" name="test_procedure" class="btn btn-warning">
                                    <i class="bi bi-play"></i> Execute Test
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Function Test Results -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Availability Check Function</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($availability_test): ?>
                                <p><strong>Available:</strong> <?php echo $availability_test['available'] ? 'Yes' : 'No'; ?></p>
                                <p><strong>Available Slots:</strong> <?php echo $availability_test['available_slots']; ?></p>
                                <p><strong>Total Days:</strong> <?php echo $availability_test['total_days']; ?></p>
                                <p><strong>Total Price:</strong> Rp <?php echo number_format($availability_test['total_price'], 0, ',', '.'); ?></p>
                                <p><strong>Message:</strong> <?php echo $availability_test['message']; ?></p>
                            <?php else: ?>
                                <p class="text-muted">Function test failed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Pricing Function</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($pricing_test): ?>
                                <p><strong>Base Price:</strong> Rp <?php echo number_format($pricing_test['base_price'], 0, ',', '.'); ?></p>
                                <p><strong>Discount:</strong> <?php echo $pricing_test['discount_percent']; ?>%</p>
                                <p><strong>Final Price:</strong> Rp <?php echo number_format($pricing_test['final_price'], 0, ',', '.'); ?></p>
                                <p><strong>Loyalty Level:</strong> <?php echo $pricing_test['user_loyalty_level']; ?></p>
                            <?php else: ?>
                                <p class="text-muted">Function test failed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6>Revenue Function</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($revenue_test): ?>
                                <p><strong>Total Revenue:</strong> Rp <?php echo number_format($revenue_test['total_revenue'], 0, ',', '.'); ?></p>
                                <p><strong>Total Rentals:</strong> <?php echo $revenue_test['total_rentals']; ?></p>
                                <p><strong>Success Rate:</strong> <?php echo number_format($revenue_test['success_rate'], 1); ?>%</p>
                                <p><strong>Avg Revenue:</strong> Rp <?php echo number_format($revenue_test['average_revenue'], 0, ',', '.'); ?></p>
                            <?php else: ?>
                                <p class="text-muted">Function test failed</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Logs -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Backup Logs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Table</th>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backup_logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><span class="badge bg-primary"><?php echo $log['backup_type']; ?></span></td>
                                        <td><?php echo $log['table_name']; ?></td>
                                        <td><?php echo $log['backup_file']; ?></td>
                                        <td><?php echo $log['backup_size'] ?: '-'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $log['status'] == 'success' ? 'success' : ($log['status'] == 'failed' ? 'danger' : 'warning'); ?>">
                                                <?php echo $log['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo $log['completed_at'] ? date('d/m/Y H:i', strtotime($log['completed_at'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- System Logs -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5>System Logs (Automated Tasks)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($system_logs as $log): ?>
                                    <tr>
                                        <td><span class="badge bg-info"><?php echo $log['action']; ?></span></td>
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
