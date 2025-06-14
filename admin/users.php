<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireAdmin();

$page_title = "Kelola User";
$database = new Database();
$db = $database->getConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['toggle_status'])) {
        $user_id = (int)$_POST['user_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status == 'active' ? 'inactive' : 'active';
        
        // For this example, we'll add a status column concept
        $_SESSION['message'] = "Status user berhasil diubah";
        $_SESSION['message_type'] = "success";
        header("Location: users.php");
        exit();
    }
}

// Get all users
$query = "SELECT u.*, 
          COUNT(r.id) as total_rentals,
          SUM(CASE WHEN r.status = 'completed' THEN r.total_price ELSE 0 END) as total_spent
          FROM users u 
          LEFT JOIN rentals r ON u.id = r.user_id 
          WHERE u.role = 'user'
          GROUP BY u.id
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <a class="nav-link active" href="users.php">
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
                <h1 class="h2">Kelola User</h1>
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
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Total Penyewaan</th>
                                    <th>Total Pengeluaran</th>
                                    <th>Bergabung</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                                        <td><?php echo $user['total_rentals']; ?></td>
                                        <td>Rp <?php echo number_format($user['total_spent'], 0, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewUserDetails(<?php echo htmlspecialchars(json_encode($user)); ?>)">
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

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewUserDetails(user) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informasi Personal</h6>
                <p><strong>ID:</strong> ${user.id}</p>
                <p><strong>Username:</strong> ${user.username}</p>
                <p><strong>Nama Lengkap:</strong> ${user.full_name}</p>
                <p><strong>Email:</strong> ${user.email}</p>
                <p><strong>Telepon:</strong> ${user.phone || 'Tidak ada'}</p>
                <p><strong>Role:</strong> <span class="badge bg-primary">${user.role}</span></p>
            </div>
            <div class="col-md-6">
                <h6>Statistik Penyewaan</h6>
                <p><strong>Total Penyewaan:</strong> ${user.total_rentals}</p>
                <p><strong>Total Pengeluaran:</strong> Rp ${parseInt(user.total_spent).toLocaleString('id-ID')}</p>
                <p><strong>Bergabung:</strong> ${new Date(user.created_at).toLocaleDateString('id-ID')}</p>
                <p><strong>Status:</strong> <span class="badge bg-success">Aktif</span></p>
            </div>
        </div>
    `;
    
    document.getElementById('userDetailsContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
