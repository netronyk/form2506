<?php
// admin/settings.php - הגדרות מערכת
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$db = new Database();
$message = '';
$error = '';

// טיפול בעדכון הגדרות
if ($_POST) {
    foreach ($_POST as $key => $value) {
        if ($key !== 'action') {
            $db->query(
                "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                 VALUES (:key, :value, :user_id) 
                 ON DUPLICATE KEY UPDATE setting_value = :value2, updated_by = :user_id2",
                [
                    ':key' => $key,
                    ':value' => $value,
                    ':value2' => $value,
                    ':user_id' => $_SESSION['user_id'],
                    ':user_id2' => $_SESSION['user_id']
                ]
            );
        }
    }
    $message = 'ההגדרות נשמרו בהצלחה';
}

// קבלת הגדרות נוכחיות
$settings = [];
$settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settingsData as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// הגדרות ברירת מחדל
$defaultSettings = [
    'site_title' => 'נהגים - מערכת הזמנות',
    'admin_email' => 'admin@nahagim.co.il',
    'subscription_price' => '69.00',
    'max_order_images' => '10',
    'order_expiry_days' => '30',
    'auto_close_orders' => '1',
    'email_notifications' => '1',
    'sms_notifications' => '0',
    'maintenance_mode' => '0'
];

foreach ($defaultSettings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הגדרות מערכת - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">נהגים - מנהל</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">לוח בקרה</a></li>
                <li><a href="users.php">משתמשים</a></li>
                <li><a href="categories.php">קטגוריות</a></li>
                <li><a href="orders.php">הזמנות</a></li>
                <li><a href="settings.php">הגדרות</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <h1>הגדרות מערכת</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- הגדרות כלליות -->
            <div class="card">
                <div class="card-header">
                    <h3>הגדרות כלליות</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">כותרת האתר</label>
                                <input type="text" name="site_title" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['site_title']); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">אימייל מנהל המערכת</label>
                                <input type="email" name="admin_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- הגדרות מנויים -->
            <div class="card">
                <div class="card-header">
                    <h3>הגדרות מנויים ותשלומים</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">מחיר מנוי חודשי (₪)</label>
                                <input type="number" name="subscription_price" class="form-control" step="0.01"
                                       value="<?php echo htmlspecialchars($settings['subscription_price']); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">תוקף הזמנה (ימים)</label>
                                <input type="number" name="order_expiry_days" class="form-control"
                                       value="<?php echo htmlspecialchars($settings['order_expiry_days']); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">מספר תמונות מקסימלי בהזמנה</label>
                                <input type="number" name="max_order_images" class="form-control"
                                       value="<?php echo htmlspecialchars($settings['max_order_images']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- הגדרות התראות -->
            <div class="card">
                <div class="card-header">
                    <h3>הגדרות התראות</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">התראות אימייל</label>
                                <select name="email_notifications" class="form-control">
                                    <option value="1" <?php echo $settings['email_notifications'] ? 'selected' : ''; ?>>פעיל</option>
                                    <option value="0" <?php echo !$settings['email_notifications'] ? 'selected' : ''; ?>>לא פעיל</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">התראות SMS</label>
                                <select name="sms_notifications" class="form-control">
                                    <option value="1" <?php echo $settings['sms_notifications'] ? 'selected' : ''; ?>>פעיל</option>
                                    <option value="0" <?php echo !$settings['sms_notifications'] ? 'selected' : ''; ?>>לא פעיל</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- הגדרות מערכת -->
            <div class="card">
                <div class="card-header">
                    <h3>הגדרות מערכת</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">סגירה אוטומטית של הזמנות ישנות</label>
                                <select name="auto_close_orders" class="form-control">
                                    <option value="1" <?php echo $settings['auto_close_orders'] ? 'selected' : ''; ?>>פעיל</option>
                                    <option value="0" <?php echo !$settings['auto_close_orders'] ? 'selected' : ''; ?>>לא פעיל</option>
                                </select>
                                <small>סוגר אוטומטית הזמנות שעברו את תוקף הזמן</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">מצב תחזוקה</label>
                                <select name="maintenance_mode" class="form-control">
                                    <option value="1" <?php echo $settings['maintenance_mode'] ? 'selected' : ''; ?>>פעיל</option>
                                    <option value="0" <?php echo !$settings['maintenance_mode'] ? 'selected' : ''; ?>>לא פעיל</option>
                                </select>
                                <small>חוסם גישה למשתמשים רגילים</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- כפתור שמירה -->
            <div class="text-center">
                <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem;">
                    שמור הגדרות
                </button>
            </div>
        </form>

        <!-- מידע מערכת -->
        <div class="card">
            <div class="card-header">
                <h3>מידע מערכת</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <strong>גרסת PHP:</strong><br>
                        <?php echo PHP_VERSION; ?>
                    </div>
                    <div class="col-3">
                        <strong>גרסת MySQL:</strong><br>
                        <?php 
                        $version = $db->fetchOne("SELECT VERSION() as version");
                        echo $version['version'];
                        ?>
                    </div>
                    <div class="col-3">
                        <strong>זיכרון פנוי:</strong><br>
                        <?php echo ini_get('memory_limit'); ?>
                    </div>
                    <div class="col-3">
                        <strong>מקום פנוי:</strong><br>
                        <?php echo round(disk_free_space('.') / 1024 / 1024 / 1024, 2); ?> GB
                    </div>
                </div>
                
                <div class="row" style="margin-top: 1rem;">
                    <div class="col-3">
                        <strong>שרת:</strong><br>
                        <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </div>
                    <div class="col-3">
                        <strong>מצב debug:</strong><br>
                        <?php echo ini_get('display_errors') ? 'פעיל' : 'לא פעיל'; ?>
                    </div>
                    <div class="col-3">
                        <strong>הגבלת העלאה:</strong><br>
                        <?php echo ini_get('upload_max_filesize'); ?>
                    </div>
                    <div class="col-3">
                        <strong>זמן ביצוע מקסימלי:</strong><br>
                        <?php echo ini_get('max_execution_time'); ?> שניות
                    </div>
                </div>
            </div>
        </div>

        <!-- פעולות מערכת -->
        <div class="card">
            <div class="card-header">
                <h3>פעולות מערכת</h3>
            </div>
            <div class="card-body text-center">
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button onclick="clearCache()" class="btn btn-outline">נקה מטמון</button>
                    <button onclick="exportData()" class="btn btn-secondary">ייצוא נתונים</button>
                    <button onclick="if(confirm('פעולה זו תמחק את כל הנתונים! אתה בטוח?')) alert('פונקציה לא מוטמעת עדיין')" class="btn btn-danger">איפוס מערכת</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clearCache() {
            if (confirm('לנקות את המטמון?')) {
                fetch('../api/system.php?action=clear_cache', {method: 'POST'})
                    .then(() => alert('המטמון נוקה'))
                    .catch(() => alert('שגיאה בניקוי המטמון'));
            }
        }
        
        function exportData() {
            if (confirm('לייצא את כל נתוני המערכת?')) {
                window.location.href = '../api/system.php?action=export_data';
            }
        }
    </script>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>