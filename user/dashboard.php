<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title = "Dashboard User";
$database = new Database();
$db = $database->getConnection();

// Get user statistics
$user_id = $_SESSION['user_id'];

// Total rentals
$query = "SELECT COUNT(*) as total FROM rentals WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$total_rentals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active rentals
$query = "SELECT COUNT(*) as total FROM rentals WHERE user_id = ? AND status IN ('pending', 'confirmed')";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$active_rentals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Completed rentals
$query = "SELECT COUNT(*) as total FROM rentals WHERE user_id = ? AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$completed_rentals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total spent
$query = "SELECT SUM(total_price) as total FROM rentals WHERE user_id = ? AND status != 'cancelled'";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$total_spent = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Recent rentals
$query = "SELECT r.*, m.name as motorcycle_name, m.brand, m.type 
          FROM rentals r 
          JOIN motorcycles m ON r.motorcycle_id = m.id 
          WHERE r.user_id = ? 
          ORDER BY r.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$recent_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Available motorcycles
$query = "SELECT * FROM motorcycles WHERE available_slots > 0 ORDER BY created_at DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$available_motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-md-block sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="rental-history.php">
                            <i class="bi bi-clock-history"></i> Riwayat
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 main-content">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard User</h1>
                <p class="text-muted">Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $total_rentals; ?></h4>
                                    <p class="mb-0">Total Penyewaan</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-calendar-check" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $active_rentals; ?></h4>
                                    <p class="mb-0">Penyewaan Aktif</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-clock" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $completed_rentals; ?></h4>
                                    <p class="mb-0">Selesai</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4>Rp <?php echo number_format($total_spent, 0, ',', '.'); ?></h4>
                                    <p class="mb-0">Total Pengeluaran</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-wallet2" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Rentals -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Penyewaan Terbaru</h5>
                            <a href="my-rentals.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_rentals)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-calendar-x" style="font-size: 3rem; color: #6c757d;"></i>
                                    <h5 class="mt-3 text-muted">Belum ada penyewaan</h5>
                                    <p class="text-muted">Mulai sewa motor sekarang!</p>
                                    <a href="../index.php" class="btn btn-primary">Lihat Motor Tersedia</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Motor</th>
                                                <th>Tanggal Sewa</th>
                                                <th>Total Harga</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_rentals as $rental): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($rental['motorcycle_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($rental['brand'] . ' - ' . $rental['type']); ?></small>
                                                    </td>
                                                    <td><?php echo date('d/m/Y', strtotime($rental['rental_date'])); ?></td>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Aksi Cepat</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="../index.php" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Cari Motor
                                </a>
                                <a href="my-rentals.php" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-check"></i> Lihat Penyewaan
                                </a>
                                <a href="profile.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-person"></i> Edit Profil
                                </a>
                                <a href="rental-history.php" class="btn btn-outline-info">
                                    <i class="bi bi-clock-history"></i> Riwayat
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Available Motorcycles Preview -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6>Motor Tersedia</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach (array_slice($available_motorcycles, 0, 3) as $motorcycle): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($motorcycle['name']); ?></strong><br>
                                        <small class="text-muted">Rp <?php echo number_format($motorcycle['price_per_day'], 0, ',', '.'); ?>/hari</small>
                                    </div>
                                    <a href="rent.php?id=<?php echo $motorcycle['id']; ?>" class="btn btn-sm btn-primary">Sewa</a>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="../index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
