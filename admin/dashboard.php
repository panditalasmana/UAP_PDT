<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireAdmin();

$page_title = "Dashboard Admin";
$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total motorcycles
$query = "SELECT COUNT(*) as total FROM motorcycles";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_motorcycles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total users
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total rentals
$query = "SELECT COUNT(*) as total FROM rentals";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending rentals
$query = "SELECT COUNT(*) as total FROM rentals WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent rentals
$query = "SELECT r.*, u.full_name, m.name as motorcycle_name 
          FROM rentals r 
          JOIN users u ON r.user_id = u.id 
          JOIN motorcycles m ON r.motorcycle_id = m.id 
          ORDER BY r.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                </ul>
            </div>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4 main-content">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Admin</h1>
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
                                    <h4><?php echo $stats['total_motorcycles']; ?></h4>
                                    <p class="mb-0">Total Motor</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-motorcycle" style="font-size: 2rem;"></i>
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
                                    <h4><?php echo $stats['total_users']; ?></h4>
                                    <p class="mb-0">Total User</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-people" style="font-size: 2rem;"></i>
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
                                    <h4><?php echo $stats['total_rentals']; ?></h4>
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
                                    <h4><?php echo $stats['pending_rentals']; ?></h4>
                                    <p class="mb-0">Menunggu Konfirmasi</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-clock" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Rentals -->
            <div class="card">
                <div class="card-header">
                    <h5>Penyewaan Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_rentals)): ?>
                        <p class="text-muted">Belum ada penyewaan</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Motor</th>
                                        <th>Tanggal Sewa</th>
                                        <th>Total Harga</th>
                                        <th>Status</th>
                                        <th>Pembayaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_rentals as $rental): ?>
                                        <tr>
                                            <td>#<?php echo $rental['id']; ?></td>
                                            <td><?php echo htmlspecialchars($rental['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($rental['motorcycle_name']); ?></td>
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
