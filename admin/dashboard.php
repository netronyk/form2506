<?php
// admin/dashboard.php - מתוקן
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();

// בדיקת הרשאות מנהל
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$db = new Database();

// קבלת סטטיסטיקות משתמשים - מתוקן!
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'vehicle_owners' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE user_type = 'vehicle_owner'")['count'],
    'customers' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE user_type = 'customer'")['count'],
    'premium_owners' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE user_type = 'vehicle_owner' AND is_premium = 1")['count']
];

// הזמנות אחרונות
$recentOrders = $db->fetchAll(
    "SELECT o.*, u.first_name, u.last_name 
     FROM orders o 
     JOIN users u ON o.customer_id = u.id 
     ORDER BY o.created_at DESC 
     LIMIT 5"
);

// בעלי רכב חדשים
$newOwners = $db->fetchAll(
    "SELECT * FROM users 
     WHERE user_type = 'vehicle_owner' 
     ORDER BY created_at DESC 
     LIMIT 5"
);

$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה מנהל - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header -->
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
        <!-- Welcome Message -->
        <div class="card">
            <div class="card-body">
                <h1 style="color: var(--secondary-color);">שלום <?php echo htmlspecialchars($currentUser['first_name']); ?></h1>
                <p>ברוך הבא לפנל ניהול מערכת נהגים</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">סה"כ משתמשים</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['vehicle_owners']; ?></div>
                <div class="stat-label">בעלי רכב</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['customers']; ?></div>
                <div class="stat-label">לקוחות</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['premium_owners']; ?></div>
                <div class="stat-label">בעלי רכב פרימיום</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3>פעולות מהירות</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <a href="users.php?action=add&type=vehicle_owner" class="btn btn-primary" style="width: 100%;">
                            הוספת בעל רכב
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="users.php?action=add&type=customer" class="btn btn-outline" style="width: 100%;">
                            הוספת לקוח
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="categories.php?action=add" class="btn btn-secondary" style="width: 100%;">
                            הוספת קטגוריה
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="settings.php" class="btn btn-outline" style="width: 100%;">
                            הגדרות מערכת
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header d-flex justify-between align-center">
                        <h3>הזמנות אחרונות</h3>
                        <a href="orders.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                            צפייה בכל ההזמנות
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders)): ?>
                            <p style="text-align: center; color: var(--dark-gray);">אין הזמנות עדיין</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>מספר הזמנה</th>
                                        <th>לקוח</th>
                                        <th>סטטוס</th>
                                        <th>תאריך</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_number']; ?></td>
                                            <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo str_replace('_', '-', $order['status']); ?>">
                                                    <?php 
                                                    $statusLabels = [
                                                        'open_for_quotes' => 'פתוח להצעות',
                                                        'in_negotiation' => 'במשא ומתן',
                                                        'closed' => 'סגור'
                                                    ];
                                                    echo $statusLabels[$order['status']] ?? $order['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- New Vehicle Owners -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header d-flex justify-between align-center">
                        <h3>בעלי רכב חדשים</h3>
                        <a href="users.php?type=vehicle_owner" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                            צפייה בכל בעלי הרכב
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($newOwners)): ?>
                            <p style="text-align: center; color: var(--dark-gray);">אין בעלי רכב חדשים</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>שם</th>
                                        <th>אימייל</th>
                                        <th>סטטוס</th>
                                        <th>תאריך הצטרפות</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($newOwners as $owner): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($owner['email']); ?></td>
                                            <td>
                                                <?php if ($owner['is_premium']): ?>
                                                    <span class="status-badge" style="background: var(--success); color: white;">פרימיום</span>
                                                <?php else: ?>
                                                    <span class="status-badge" style="background: var(--warning); color: black;">רגיל</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($owner['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="card">
            <div class="card-header">
                <h3>מידע מערכת</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <strong>גרסת PHP:</strong> <?php echo PHP_VERSION; ?>
                    </div>
                    <div class="col-3">
                        <strong>שרת:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                    </div>
                    <div class="col-3">
                        <strong>זיכרון פנוי:</strong> <?php echo ini_get('memory_limit'); ?>
                    </div>
                    <div class="col-3">
                        <strong>גרסת מערכת:</strong> 1.0.0
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>