<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title = "Riwayat Penyewaan";
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query with filters
$where_conditions = ["r.user_id = ?"];
$params = [$user_id];

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if (!empty($start_date)) {
    $where_conditions[] = "r.rental_date >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $where_conditions[] = "r.rental_date <= ?";
    $params[] = $end_date;
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT r.*, m.name as motorcycle_name, m.brand, m.type 
          FROM rentals r 
          JOIN motorcycles m ON r.motorcycle_id = m.id 
          WHERE $where_clause
          ORDER BY r.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for the filtered results
$stats_query = "SELECT 
                  COUNT(*) as total_rentals,
                  SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as total_spent,
                  COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_rentals,
                  COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_rentals
                FROM rentals r 
                WHERE $where_clause";

$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

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
                        <a class="nav-link" href="../index.php">
                            <i class="bi bi-house"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-rentals.php">
                            <i class="bi bi-calendar-check"></i> Penyewaan Saya
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rental-history.php">
                            <i class="bi bi-clock-history"></i> Riwayat
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 main-content">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Riwayat Penyewaan</h1>
            </div>

            <!-- Filter Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Semua Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="rental-history.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h4><?php echo $stats['total_rentals']; ?></h4>
                            <p class="mb-0">Total Penyewaan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h4><?php echo $stats['completed_rentals']; ?></h4>
                            <p class="mb-0">Selesai</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h4><?php echo $stats['cancelled_rentals']; ?></h4>
                            <p class="mb-0">Dibatalkan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h4>Rp <?php echo number_format($stats['total_spent'], 0, ',', '.'); ?></h4>
                            <p class="mb-0">Total Pengeluaran</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rental History Table -->
            <div class="card">
                <div class="card-header">
                    <h5>Riwayat Penyewaan</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($rentals)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clock-history" style="font-size: 4rem; color: #6c757d;"></i>
                            <h3 class="mt-3 text-muted">Tidak ada riwayat</h3>
                            <p class="text-muted">Belum ada penyewaan yang sesuai dengan filter</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Motor</th>
                                        <th>Tanggal Sewa</th>
                                        <th>Tanggal Kembali</th>
                                        <th>Total Hari</th>
                                        <th>Total Harga</th>
                                        <th>Status</th>
                                        <th>Pembayaran</th>
                                        <th>Dibuat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rentals as $rental): ?>
                                        <tr>
                                            <td>#<?php echo $rental['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($rental['motorcycle_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($rental['brand'] . ' - ' . $rental['type']); ?></small>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($rental['rental_date'])); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($rental['return_date'])); ?></td>
                                            <td><?php echo $rental['total_days']; ?> hari</td>
                                            <td>Rp <?php echo number_format($rental['total_price'], 0, ',', '.'); ?></td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'confirmed' => 'success',
                                                    'completed' => 'info',
                                                    'cancelled' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $status_class[$rental['status']]; ?>">
                                                    <?php echo ucfirst($rental['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $payment_class = [
                                                    'pending' => 'warning',
                                                    'paid' => 'success',
                                                    'refunded' => 'info'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $payment_class[$rental['payment_status']]; ?>">
                                                    <?php echo ucfirst($rental['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($rental['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
