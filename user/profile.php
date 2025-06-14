<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title = "Profil";
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        $errors = [];
        
        if (empty($full_name)) $errors[] = "Nama lengkap harus diisi";
        if (empty($email)) $errors[] = "Email harus diisi";
        
        // Check if email is already used by another user
        if (empty($errors)) {
            $query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$email, $user_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email sudah digunakan oleh user lain";
            }
        }
        
        if (empty($errors)) {
            $query = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$full_name, $email, $phone, $user_id])) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['message'] = "Profil berhasil diupdate";
                $_SESSION['message_type'] = "success";
            } else {
                $errors[] = "Gagal mengupdate profil";
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        if (empty($current_password)) $errors[] = "Password saat ini harus diisi";
        if (empty($new_password)) $errors[] = "Password baru harus diisi";
        if ($new_password !== $confirm_password) $errors[] = "Konfirmasi password tidak cocok";
        
        if (empty($errors)) {
            // Verify current password
            $query = "SELECT password FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Password saat ini salah";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $_SESSION['message'] = "Password berhasil diubah";
                    $_SESSION['message_type'] = "success";
                } else {
                    $errors[] = "Gagal mengubah password";
                }
            }
        }
    }
}

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

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
                        <a class="nav-link active" href="profile.php">
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
                <h1 class="h2">Profil Saya</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Informasi Profil</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                    <small class="text-muted">Username tidak dapat diubah</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Nomor Telepon</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Bergabung Sejak</label>
                                    <input type="text" class="form-control" value="<?php echo date('d/m/Y', strtotime($user['created_at'])); ?>" readonly>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Profil
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Ubah Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Password Saat Ini</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="bi bi-key"></i> Ubah Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Account Info -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6>Informasi Akun</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Role:</strong> <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span></p>
                            <p><strong>Status:</strong> <span class="badge bg-success">Aktif</span></p>
                            <p><strong>ID User:</strong> #<?php echo $user['id']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
