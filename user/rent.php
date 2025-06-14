<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title = "Sewa Motor";
$database = new Database();
$db = $database->getConnection();

$motorcycle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get motorcycle details
$query = "SELECT * FROM motorcycles WHERE id = ? AND available_slots > 0";
$stmt = $db->prepare($query);
$stmt->execute([$motorcycle_id]);
$motorcycle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$motorcycle) {
    $_SESSION['message'] = "Motor tidak ditemukan atau tidak tersedia";
    $_SESSION['message_type'] = "danger";
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rental_date = $_POST['rental_date'];
    $return_date = $_POST['return_date'];
    
    $errors = [];
    
    if (empty($rental_date)) $errors[] = "Tanggal sewa harus diisi";
    if (empty($return_date)) $errors[] = "Tanggal kembali harus diisi";
    
    if (empty($errors)) {
        // Check availability using function
        $query = "SELECT cekKetersediaan(?, ?, ?) as available";
        $stmt = $db->prepare($query);
        $stmt->execute([$motorcycle_id, $rental_date, $return_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['available'] == 1) {
            // Use stored procedure to create rental
            $query = "CALL buatPenyewaan(?, ?, ?, ?, @result)";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $motorcycle_id, $rental_date, $return_date]);
            
            // Get result
            $stmt = $db->query("SELECT @result as result");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (strpos($result['result'], 'Success') === 0) {
                $_SESSION['message'] = "Penyewaan berhasil dibuat! Silakan lakukan pembayaran.";
                $_SESSION['message_type'] = "success";
                header("Location: my-rentals.php");
                exit();
            } else {
                $errors[] = $result['result'];
            }
        } else {
            $errors[] = "Motor tidak tersedia untuk tanggal yang dipilih";
        }
    }
}

include '../includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Sewa Motor: <?php echo htmlspecialchars($motorcycle['name']); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="rentalForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rental_date" class="form-label">Tanggal Sewa</label>
                                    <input type="date" class="form-control" id="rental_date" name="rental_date" 
                                           min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo isset($_POST['rental_date']) ? $_POST['rental_date'] : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="return_date" class="form-label">Tanggal Kembali</label>
                                    <input type="date" class="form-control" id="return_date" name="return_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                           value="<?php echo isset($_POST['return_date']) ? $_POST['return_date'] : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Total Hari</label>
                            <input type="text" class="form-control" id="total_days" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Total Harga</label>
                            <input type="text" class="form-control" id="total_price" readonly>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-calendar-check"></i> Konfirmasi Penyewaan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Detail Motor</h5>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($motorcycle['name']); ?></h6>
                    <p class="mb-2"><strong>Brand:</strong> <?php echo htmlspecialchars($motorcycle['brand']); ?></p>
                    <p class="mb-2"><strong>Type:</strong> <?php echo htmlspecialchars($motorcycle['type']); ?></p>
                    <p class="mb-2"><strong>Harga per hari:</strong> Rp <?php echo number_format($motorcycle['price_per_day'], 0, ',', '.'); ?></p>
                    <p class="mb-2"><strong>Tersedia:</strong> <?php echo $motorcycle['available_slots']; ?> unit</p>
                    <p class="mb-0"><?php echo htmlspecialchars($motorcycle['description']); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const pricePerDay = <?php echo $motorcycle['price_per_day']; ?>;

function calculateTotal() {
    const rentalDate = document.getElementById('rental_date').value;
    const returnDate = document.getElementById('return_date').value;
    
    if (rentalDate && returnDate) {
        const startDate = new Date(rentalDate);
        const endDate = new Date(returnDate);
        const timeDiff = endDate.getTime() - startDate.getTime();
        const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
        
        if (daysDiff > 0) {
            const totalPrice = daysDiff * pricePerDay;
            document.getElementById('total_days').value = daysDiff + ' hari';
            document.getElementById('total_price').value = 'Rp ' + totalPrice.toLocaleString('id-ID');
        } else {
            document.getElementById('total_days').value = '';
            document.getElementById('total_price').value = '';
        }
    }
}

document.getElementById('rental_date').addEventListener('change', calculateTotal);
document.getElementById('return_date').addEventListener('change', calculateTotal);

// Set minimum return date when rental date changes
document.getElementById('rental_date').addEventListener('change', function() {
    const rentalDate = new Date(this.value);
    const minReturnDate = new Date(rentalDate);
    minReturnDate.setDate(minReturnDate.getDate() + 1);
    document.getElementById('return_date').min = minReturnDate.toISOString().split('T')[0];
});
</script>

<?php include '../includes/footer.php'; ?>
