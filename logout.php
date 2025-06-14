<?php
require_once 'config/session.php';
session_unset();
session_destroy();

// Redirect ke halaman utama (root)
header("Location: /index.php");
exit();
?>
