<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireAdmin();

$page_title = "Laporan";
$database = new Database();
$db = $database->getConnection();

// Get date range from form or default to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Revenue report
$query = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_rentals,
            SUM(total_price) as total_revenue
          FROM rentals 
          WHERE DATE(created_at) BETWEEN ? AND ? 
          AND status != 'cancelled'
          GROUP BY DATE(created_at)
          ORDER BY date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$daily_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly summary
$query = "SELECT 
            COUNT(*) as total_rentals,
            SUM(total_price) as total_revenue,
            AVG(total_price) as avg_revenue
          FROM rentals 
          WHERE DATE(created_at) BETWEEN ? AND ? 
          AND status != 'cancelled'";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Popular motorcycles
$query = "SELECT 
            m.name,
            m.brand,
            COUNT(r.id) as rental_count,
            SUM(r.total_price) as total_revenue
          FROM motorcycles m
          LEFT JOIN rentals r ON m.id = r.motorcycle_id 
          AND DATE(r.created_at) BETWEEN ? AND ?
          AND r.status != 'cancelled'
          GROUP BY m.id
          ORDER BY rental_count DESC";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$popular_motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rental history from cancellations
$query = "SELECT * FROM rental_history WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$rental_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <a class="nav-link active" href="reports.php">
                            <i class="bi bi-graph-up"></i> Laporan
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 main-content">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Laporan</h1>
            </div>

            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="reports.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h4><?php echo $summary['total_rentals'] ?: 0; ?></h4>
                            <p class="mb-0">Total Penyewaan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h4>Rp <?php echo number_format($summary['total_revenue'] ?: 0, 0, ',', '.'); ?></h4>
                            <p class="mb-0">Total Pendapatan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h4>Rp <?php echo number_format($summary['avg_revenue'] ?: 0, 0, ',', '.'); ?></h4>
                            <p class="mb-0">Rata-rata Pendapatan</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Daily Report -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>Laporan Harian</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Total Penyewaan</th>
                                            <th>Total Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($daily_reports)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">Tidak ada data</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($daily_reports as $report): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y', strtotime($report['date'])); ?></td>
                                                    <td><?php echo $report['total_rentals']; ?></td>
                                                    <td>Rp <?php echo number_format($report['total_revenue'], 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Popular Motorcycles -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Motor Populer</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($popular_motorcycles)): ?>
                                <p class="text-muted">Tidak ada data</p>
                            <?php else: ?>
                                <?php foreach ($popular_motorcycles as $motorcycle): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($motorcycle['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($motorcycle['brand']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary"><?php echo $motorcycle['rental_count']; ?></span><br>
                                            <small>Rp <?php echo number_format($motorcycle['total_revenue'] ?: 0, 0, ',', '.'); ?></small>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rental History -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Riwayat Aktivitas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Rental ID</th>
                                    <th>User ID</th>
                                    <th>Motor ID</th>
                                    <th>Aksi</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rental_history)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Tidak ada riwayat</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rental_history as $history): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($history['created_at'])); ?></td>
                                            <td>#<?php echo $history['rental_id']; ?></td>
                                            <td><?php echo $history['user_id']; ?></td>
                                            <td><?php echo $history['motorcycle_id']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $history['action'] == 'CANCELLED' ? 'warning' : 'danger'; ?>">
                                                    <?php echo $history['action']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($history['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
