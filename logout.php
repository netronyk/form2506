<?php
// logout.php - התנתקות
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$auth->logout();

flash('success', 'התנתקת בהצלחה');
redirect('index.php');
?>