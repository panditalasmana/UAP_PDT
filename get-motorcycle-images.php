<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/image-upload.php';

requireLogin();
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid motorcycle ID']);
    exit();
}

$motorcycle_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $imageUpload = new ImageUpload($db);
    
    $query = "SELECT id, image_path, image_name, image_size, is_primary, alt_text, uploaded_at 
              FROM motorcycle_images 
              WHERE motorcycle_id = ? 
              ORDER BY is_primary DESC, uploaded_at ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$motorcycle_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert is_primary to boolean and add full URLs
    foreach ($images as &$image) {
        $image['is_primary'] = (bool)$image['is_primary'];
        $image['image_url'] = $imageUpload->getImageUrl($image['image_path']);
        $image['thumbnail_url'] = $imageUpload->getThumbnailUrl($image['image_path']);
    }
    
    echo json_encode([
        'success' => true,
        'images' => $images
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading images: ' . $e->getMessage()
    ]);
}
?>
