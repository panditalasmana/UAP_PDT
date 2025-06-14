<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireAdmin();

$page_title = "Kelola Penyewaan";
$database = new Database();
$db = $database->getConnection();

// Handle rental status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $rental_id = (int)$_POST['rental_id'];
        $status = $_POST['status'];
        
        $query = "UPDATE rentals SET status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$status, $rental_id])) {
            $_SESSION['message'] = "Status penyewaan berhasil diupdate";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal mengupdate status";
            $_SESSION['message_type'] = "danger";
        }
        header("Location: rentals.php");
        exit();
    }
}

// Get all rentals with user and motorcycle info
$query = "SELECT r.*, u.full_name, u.email, u.phone, m.name as motorcycle_name, m.brand, m.type 
          FROM rentals r 
          JOIN users u ON r.user_id = u.id 
          JOIN motorcycles m ON r.motorcycle_id = m.id 
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
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
                        <a class="nav-link" href="motorcycles.php">
                            <i class="bi bi-motorcycle"></i> Kelola Motor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rentals.php">
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
                <h1 class="h2">Kelola Penyewaan</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Motor</th>
                                    <th>Tanggal Sewa</th>
                                    <th>Tanggal Kembali</th>
                                    <th>Total Harga</th>
                                    <th>Status</th>
                                    <th>Pembayaran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rentals as $rental): ?>
                                    <tr>
                                        <td>#<?php echo $rental['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rental['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($rental['email']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rental['motorcycle_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($rental['brand'] . ' - ' . $rental['type']); ?></small>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($rental['rental_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rental['return_date'])); ?></td>
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
                                        <td>
                                            <?php if ($rental['status'] == 'pending' && $rental['payment_status'] == 'paid'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check-circle"></i> Konfirmasi
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($rental['status'] == 'confirmed'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-info">
                                                        <i class="bi bi-flag"></i> Selesai
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewRentalDetails(<?php echo htmlspecialchars(json_encode($rental)); ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
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

<!-- Rental Details Modal -->
<div class="modal fade" id="rentalDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Penyewaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rentalDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewRentalDetails(rental) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informasi Customer</h6>
                <p><strong>Nama:</strong> ${rental.full_name}</p>
                <p><strong>Email:</strong> ${rental.email}</p>
                <p><strong>Telepon:</strong> ${rental.phone || 'Tidak ada'}</p>
            </div>
            <div class="col-md-6">
                <h6>Informasi Motor</h6>
                <p><strong>Motor:</strong> ${rental.motorcycle_name}</p>
                <p><strong>Brand:</strong> ${rental.brand}</p>
                <p><strong>Type:</strong> ${rental.type}</p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <h6>Detail Penyewaan</h6>
                <p><strong>Tanggal Sewa:</strong> ${new Date(rental.rental_date).toLocaleDateString('id-ID')}</p>
                <p><strong>Tanggal Kembali:</strong> ${new Date(rental.return_date).toLocaleDateString('id-ID')}</p>
                <p><strong>Total Hari:</strong> ${rental.total_days} hari</p>
                <p><strong>Total Harga:</strong> Rp ${parseInt(rental.total_price).toLocaleString('id-ID')}</p>
            </div>
            <div class="col-md-6">
                <h6>Status</h6>
                <p><strong>Status Penyewaan:</strong> <span class="badge bg-primary">${rental.status}</span></p>
                <p><strong>Status Pembayaran:</strong> <span class="badge bg-success">${rental.payment_status}</span></p>
                <p><strong>Dibuat:</strong> ${new Date(rental.created_at).toLocaleString('id-ID')}</p>
            </div>
        </div>
    `;
    
    document.getElementById('rentalDetailsContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('rentalDetailsModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
