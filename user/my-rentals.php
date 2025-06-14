<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title = "Penyewaan Saya";
$database = new Database();
$db = $database->getConnection();

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $rental_id = (int)$_POST['rental_id'];
    
    $query = "UPDATE rentals SET payment_status = 'paid' WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$rental_id, $_SESSION['user_id']])) {
        $_SESSION['message'] = "Pembayaran berhasil dikonfirmasi";
        $_SESSION['message_type'] = "success";
    }
    header("Location: my-rentals.php");
    exit();
}

// Handle rental cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_rental'])) {
    $rental_id = (int)$_POST['rental_id'];
    
    $query = "UPDATE rentals SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$rental_id, $_SESSION['user_id']])) {
        $_SESSION['message'] = "Penyewaan berhasil dibatalkan";
        $_SESSION['message_type'] = "info";
    }
    header("Location: my-rentals.php");
    exit();
}

// Get user's rentals
$query = "SELECT r.*, m.name as motorcycle_name, m.brand, m.type 
          FROM rentals r 
          JOIN motorcycles m ON r.motorcycle_id = m.id 
          WHERE r.user_id = ? 
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <a class="nav-link active" href="my-rentals.php">
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
            <div class="container my-5">
                <h2>Penyewaan Saya</h2>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <?php if (empty($rentals)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x" style="font-size: 4rem; color: #6c757d;"></i>
                        <h3 class="mt-3 text-muted">Belum ada penyewaan</h3>
                        <p class="text-muted">Mulai sewa motor sekarang!</p>
                        <a href="index.php" class="btn btn-primary">Lihat Motor Tersedia</a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($rentals as $rental): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($rental['motorcycle_name']); ?></h6>
                                        <div>
                                            <?php
                                            $status_class = [
                                                'pending' => 'warning',
                                                'confirmed' => 'success',
                                                'completed' => 'info',
                                                'cancelled' => 'danger'
                                            ];
                                            $payment_class = [
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'refunded' => 'info'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$rental['status']]; ?> status-badge">
                                                <?php echo ucfirst($rental['status']); ?>
                                            </span>
                                            <span class="badge bg-<?php echo $payment_class[$rental['payment_status']]; ?> status-badge">
                                                <?php echo ucfirst($rental['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <strong>Brand:</strong> <?php echo htmlspecialchars($rental['brand']); ?><br>
                                            <strong>Type:</strong> <?php echo htmlspecialchars($rental['type']); ?><br>
                                            <strong>Tanggal Sewa:</strong> <?php echo date('d/m/Y', strtotime($rental['rental_date'])); ?><br>
                                            <strong>Tanggal Kembali:</strong> <?php echo date('d/m/Y', strtotime($rental['return_date'])); ?><br>
                                            <strong>Total Hari:</strong> <?php echo $rental['total_days']; ?> hari<br>
                                            <strong>Total Harga:</strong> Rp <?php echo number_format($rental['total_price'], 0, ',', '.'); ?>
                                        </p>
                                        
                                        <div class="d-flex gap-2">
                                            <?php if ($rental['status'] == 'pending' && $rental['payment_status'] == 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                    <button type="submit" name="confirm_payment" class="btn btn-success btn-sm">
                                                        <i class="bi bi-credit-card"></i> Bayar
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin membatalkan penyewaan ini?')">
                                                    <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                    <button type="submit" name="cancel_rental" class="btn btn-danger btn-sm">
                                                        <i class="bi bi-x-circle"></i> Batal
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer text-muted">
                                        <small>Dibuat: <?php echo date('d/m/Y H:i', strtotime($rental['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
