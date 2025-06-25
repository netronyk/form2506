<?php
// admin/subscriptions.php - סטטיסטיקות מנויים
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/User.php';

$auth = new Auth();
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$user = new User();
$db = new Database();

// קבלת סטטיסטיקות מנויים
$stats = $user->getSubscriptionStats();

// בעלי רכב שהמנוי שלהם יפוג בקרוב
$expiringSoon = $db->fetchAll(
    "SELECT id, first_name, last_name, email, premium_expires,
            DATEDIFF(premium_expires, CURDATE()) as days_left
     FROM users 
     WHERE user_type = 'vehicle_owner' AND is_premium = 1 
     AND premium_expires BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY premium_expires ASC"
);

// מנויים שפגו
$expiredSubscriptions = $db->fetchAll(
    "SELECT id, first_name, last_name, email, premium_expires,
            DATEDIFF(CURDATE(), premium_expires) as days_expired
     FROM users 
     WHERE user_type = 'vehicle_owner' AND is_premium = 1 AND premium_expires < CURDATE()
     ORDER BY premium_expires DESC
     LIMIT 20"
);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>סטטיסטיקות מנויים - <?php echo SITE_NAME; ?></title>
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
                <li><a href="subscriptions.php">מנויים</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <h1>סטטיסטיקות מנויים פרימיום</h1>

        <!-- סטטיסטיקות ראשיות -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_premium']; ?></div>
                <div class="stat-label">מנויים פעילים</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['expiring_soon']; ?></div>
                <div class="stat-label">יפגו השבוע</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['expired']; ?></div>
                <div class="stat-label">מנויים שפגו</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['monthly_revenue']); ?>₪</div>
                <div class="stat-label">הכנסה משוערת</div>
            </div>
        </div>

        <!-- מנויים שיפגו בקרוב -->
        <?php if (!empty($expiringSoon)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>מנויים שיפגו ב-30 הימים הקרובים (<?php echo count($expiringSoon); ?>)</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>שם</th>
                                <th>אימייל</th>
                                <th>תאריך תפוגה</th>
                                <th>ימים נותרו</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiringSoon as $sub): ?>
                                <tr style="<?php echo $sub['days_left'] <= 7 ? 'background-color: #fff3cd;' : ''; ?>">
                                    <td><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($sub['premium_expires'])); ?></td>
                                    <td>
                                        <span style="color: <?php echo $sub['days_left'] <= 7 ? '#dc3545' : '#6c757d'; ?>;">
                                            <?php echo $sub['days_left']; ?> ימים
                                        </span>
                                    </td>
                                    <td>
                                        <a href="users.php?action=edit&id=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">ניהול</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- מנויים שפגו -->
        <?php if (!empty($expiredSubscriptions)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>מנויים שפגו (20 האחרונים)</h3>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>שם</th>
                                <th>אימייל</th>
                                <th>תאריך תפוגה</th>
                                <th>פג לפני</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiredSubscriptions as $sub): ?>
                                <tr style="background-color: #f8d7da;">
                                    <td><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($sub['premium_expires'])); ?></td>
                                    <td>
                                        <span style="color: #dc3545;">
                                            <?php echo $sub['days_expired']; ?> ימים
                                        </span>
                                    </td>
                                    <td>
                                        <a href="users.php?action=edit&id=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">חידוש</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- מחירון מנויים פרימיום עדכני -->
        <div class="card">
            <div class="card-header">
                <h3>מחירון מנויים פרימיום</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <div style="text-align: center; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                            <h4>חודשי</h4>
                            <div style="font-size: 2rem; color: #FF7A00; font-weight: bold;">69₪</div>
                            <p>לחודש</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="text-align: center; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                            <h4>רבעוני</h4>
                            <div style="font-size: 2rem; color: #FF7A00; font-weight: bold;">199₪</div>
                            <p>3 חודשים <small>(חסכון 8₪)</small></p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="text-align: center; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                            <h4>חצי שנתי</h4>
                            <div style="font-size: 2rem; color: #FF7A00; font-weight: bold;">389₪</div>
                            <p>6 חודשים <small>(חסכון 25₪)</small></p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div style="text-align: center; padding: 1rem; border: 2px solid #FF7A00; border-radius: 5px; background: #fff8f0;">
                            <h4>שנתי 🏆</h4>
                            <div style="font-size: 2rem; color: #FF7A00; font-weight: bold;">749₪</div>
                            <p>12 חודשים <small>(חסכון 79₪)</small></p>
                        </div>
                    </div>
                </div>
                
                <!-- תוספת: נתונים לניהול -->
                <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <h5>💡 נתונים לניהול</h5>
                    <div class="row">
                        <div class="col-4 text-center">
                            <div style="padding: 1rem; background: white; border-radius: 8px;">
                                <h4 style="color: var(--primary-color);">החזר השקעה</h4>
                                <p>מנוי שנתי מייצר חסכון של <strong>79₪</strong><br>
                                   (11.5% הנחה לעומת מנוי חודשי)</p>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div style="padding: 1rem; background: white; border-radius: 8px;">
                                <h4 style="color: var(--primary-color);">המלצה לבעלי רכב</h4>
                                <p>מנוי 6-12 חודשים<br>
                                   מספק יציבות ועלות נמוכה יותר</p>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div style="padding: 1rem; background: white; border-radius: 8px;">
                                <h4 style="color: var(--primary-color);">ערך לקוח ממוצע</h4>
                                <p>מנוי שנתי: <strong>749₪</strong><br>
                                   LTV לקוח פעיל: <strong>~1,500₪</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>