<?php
echo "<h2>Path Verification Test</h2>";

// Test current directory detection
$currentPath = $_SERVER['REQUEST_URI'];
echo "<p><strong>Current Path:</strong> " . $currentPath . "</p>";

// Test path detection logic
$isInAdmin = strpos($currentPath, '/admin/') !== false;
$isInUser = strpos($currentPath, '/user/') !== false;

echo "<p><strong>Is in Admin:</strong> " . ($isInAdmin ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Is in User:</strong> " . ($isInUser ? 'Yes' : 'No') . "</p>";

// Test absolute paths
echo "<h3>Absolute Paths (These should always work):</h3>";
echo "<ul>";
echo "<li><a href='/index.php'>Home (/index.php)</a></li>";
echo "<li><a href='/login.php'>Login (/login.php)</a></li>";
echo "<li><a href='/register.php'>Register (/register.php)</a></li>";
echo "<li><a href='/logout.php'>Logout (/logout.php)</a></li>";
echo "<li><a href='/admin/dashboard.php'>Admin Dashboard (/admin/dashboard.php)</a></li>";
echo "<li><a href='/user/dashboard.php'>User Dashboard (/user/dashboard.php)</a></li>";
echo "</ul>";

// Test relative paths for comparison
echo "<h3>Relative Paths (These might break depending on current directory):</h3>";
echo "<ul>";
echo "<li><a href='index.php'>Home (index.php)</a></li>";
echo "<li><a href='../logout.php'>Logout (../logout.php)</a></li>";
echo "<li><a href='logout.php'>Logout (logout.php)</a></li>";
echo "</ul>";

echo "<p><strong>Note:</strong> Only the absolute paths (starting with /) should work consistently from any directory.</p>";
?>
