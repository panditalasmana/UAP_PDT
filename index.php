<?php
require_once 'config/session.php';
require_once 'config/database.php';

$page_title = "Beranda";
$database = new Database();
$db = $database->getConnection();

// Get available motorcycles
$query = "SELECT * FROM motorcycles WHERE available_slots > 0 ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container my-5">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="jumbotron bg-primary text-white p-5 rounded mb-5">
                <h1 class="display-4">Selamat Datang di NARIPA WHEELS</h1>
                <p class="lead">Sistem manajemen penyewaan motor yang mudah dan terpercaya</p>
                <?php if (!isLoggedIn()): ?>
                    <a class="btn btn-light btn-lg" href="register.php">Daftar Sekarang</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h2 class="mb-4">Motor Tersedia</h2>
    <div class="row">
        <?php foreach ($motorcycles as $motorcycle): ?>
            <div class="col-md-4 mb-4">
                <div class="card motorcycle-card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($motorcycle['name']); ?></h5>
                        <p class="card-text">
                            <strong>Brand:</strong> <?php echo htmlspecialchars($motorcycle['brand']); ?><br>
                            <strong>Type:</strong> <?php echo htmlspecialchars($motorcycle['type']); ?><br>
                            <strong>Harga:</strong> Rp <?php echo number_format($motorcycle['price_per_day'], 0, ',', '.'); ?>/hari
                        </p>
                        <p class="card-text"><?php echo htmlspecialchars($motorcycle['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-success">
                                <?php echo $motorcycle['available_slots']; ?> tersedia
                            </span>
                            <?php if (isLoggedIn()): ?>
                                <a href="user/rent.php?id=<?php echo $motorcycle['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-calendar-plus"></i> Sewa
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline-primary">Login untuk Sewa</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($motorcycles)): ?>
        <div class="text-center py-5">
            <i class="bi bi-motorcycle" style="font-size: 4rem; color: #6c757d;"></i>
            <h3 class="mt-3 text-muted">Tidak ada motor tersedia</h3>
            <p class="text-muted">Silakan cek kembali nanti</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
