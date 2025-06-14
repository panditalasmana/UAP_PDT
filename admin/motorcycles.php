<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireAdmin();

$page_title = "Kelola Motor";
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_motorcycle'])) {
        $name = trim($_POST['name']);
        $brand = trim($_POST['brand']);
        $type = trim($_POST['type']);
        $price_per_day = (float)$_POST['price_per_day'];
        $total_slots = (int)$_POST['total_slots'];
        $description = trim($_POST['description']);
        
        $query = "INSERT INTO motorcycles (name, brand, type, price_per_day, available_slots, total_slots, description) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$name, $brand, $type, $price_per_day, $total_slots, $total_slots, $description])) {
            $_SESSION['message'] = "Motor berhasil ditambahkan";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal menambahkan motor";
            $_SESSION['message_type'] = "danger";
        }
        header("Location: motorcycles.php");
        exit();
    }
    
    if (isset($_POST['update_motorcycle'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $brand = trim($_POST['brand']);
        $type = trim($_POST['type']);
        $price_per_day = (float)$_POST['price_per_day'];
        $total_slots = (int)$_POST['total_slots'];
        $description = trim($_POST['description']);
        
        $query = "UPDATE motorcycles SET name = ?, brand = ?, type = ?, price_per_day = ?, total_slots = ?, description = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$name, $brand, $type, $price_per_day, $total_slots, $description, $id])) {
            $_SESSION['message'] = "Motor berhasil diupdate";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal mengupdate motor";
            $_SESSION['message_type'] = "danger";
        }
        header("Location: motorcycles.php");
        exit();
    }
    
    if (isset($_POST['delete_motorcycle'])) {
        $id = (int)$_POST['id'];
        
        // Check if motorcycle has active rentals
        $query = "SELECT COUNT(*) as count FROM rentals WHERE motorcycle_id = ? AND status IN ('pending', 'confirmed')";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $_SESSION['message'] = "Tidak dapat menghapus motor yang sedang disewa";
            $_SESSION['message_type'] = "danger";
        } else {
            $query = "DELETE FROM motorcycles WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$id])) {
                $_SESSION['message'] = "Motor berhasil dihapus";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Gagal menghapus motor";
                $_SESSION['message_type'] = "danger";
            }
        }
        header("Location: motorcycles.php");
        exit();
    }
}

// Get all motorcycles
$query = "SELECT * FROM motorcycles ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$motorcycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <a class="nav-link active" href="motorcycles.php">
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
            <div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
                <h1 class="h2">Kelola Motor</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMotorcycleModal">
                    <i class="bi bi-plus-circle"></i> Tambah Motor
                </button>
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
                                    <th>Nama</th>
                                    <th>Brand</th>
                                    <th>Type</th>
                                    <th>Harga/Hari</th>
                                    <th>Tersedia</th>
                                    <th>Total</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($motorcycles as $motorcycle): ?>
                                    <tr>
                                        <td><?php echo $motorcycle['id']; ?></td>
                                        <td><?php echo htmlspecialchars($motorcycle['name']); ?></td>
                                        <td><?php echo htmlspecialchars($motorcycle['brand']); ?></td>
                                        <td><?php echo htmlspecialchars($motorcycle['type']); ?></td>
                                        <td>Rp <?php echo number_format($motorcycle['price_per_day'], 0, ',', '.'); ?></td>
                                        <td><?php echo $motorcycle['available_slots']; ?></td>
                                        <td><?php echo $motorcycle['total_slots']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="editMotorcycle(<?php echo htmlspecialchars(json_encode($motorcycle)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus motor ini?')">
                                                <input type="hidden" name="id" value="<?php echo $motorcycle['id']; ?>">
                                                <button type="submit" name="delete_motorcycle" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

<!-- Add Motorcycle Modal -->
<div class="modal fade" id="addMotorcycleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Motor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Motor</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="brand" name="brand" required>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="">Pilih Type</option>
                            <option value="Matic">Matic</option>
                            <option value="Manual">Manual</option>
                            <option value="Sport">Sport</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="price_per_day" class="form-label">Harga per Hari</label>
                        <input type="number" class="form-control" id="price_per_day" name="price_per_day" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_slots" class="form-label">Jumlah Unit</label>
                        <input type="number" class="form-control" id="total_slots" name="total_slots" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_motorcycle" class="btn btn-primary">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Motorcycle Modal -->
<div class="modal fade" id="editMotorcycleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Motor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Nama Motor</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="edit_brand" name="brand" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Type</label>
                        <select class="form-control" id="edit_type" name="type" required>
                            <option value="">Pilih Type</option>
                            <option value="Matic">Matic</option>
                            <option value="Manual">Manual</option>
                            <option value="Sport">Sport</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_price_per_day" class="form-label">Harga per Hari</label>
                        <input type="number" class="form-control" id="edit_price_per_day" name="price_per_day" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_slots" class="form-label">Jumlah Unit</label>
                        <input type="number" class="form-control" id="edit_total_slots" name="total_slots" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_motorcycle" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMotorcycle(motorcycle) {
    document.getElementById('edit_id').value = motorcycle.id;
    document.getElementById('edit_name').value = motorcycle.name;
    document.getElementById('edit_brand').value = motorcycle.brand;
    document.getElementById('edit_type').value = motorcycle.type;
    document.getElementById('edit_price_per_day').value = motorcycle.price_per_day;
    document.getElementById('edit_total_slots').value = motorcycle.total_slots;
    document.getElementById('edit_description').value = motorcycle.description;
    
    const editModal = new bootstrap.Modal(document.getElementById('editMotorcycleModal'));
    editModal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
