<?php
// dashboard.php - לוח בקרה מרכזי
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

// בדיקת התחברות
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

$currentUser = $auth->getCurrentUser();
$userType = $currentUser['user_type'];

// הפניה לפנל המתאים לפי סוג משתמש
switch ($userType) {
    case 'admin':
        if (file_exists(__DIR__ . '/admin/dashboard.php')) {
            redirect('admin/dashboard.php');
        } else {
            echo "Admin panel not found";
        }
        break;
    case 'vehicle_owner':
        if (file_exists(__DIR__ . '/vehicle-owner/dashboard.php')) {
            redirect('vehicle-owner/dashboard.php');
        } else {
            echo "Vehicle owner panel not found";
        }
        break;
    case 'customer':
        if (file_exists(__DIR__ . '/customer/dashboard.php')) {
            redirect('customer/dashboard.php');
        } else {
            echo "Customer panel not found";
        }
        break;
    default:
        $auth->logout();
        redirect('login.php');
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container text-center" style="margin-top: 4rem;">
        <div class="spinner"></div>
        <p style="margin-top: 1rem;">מפנה ללוח הבקרה המתאים...</p>
    </div>
</body>
</html>