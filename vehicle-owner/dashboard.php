<?php
// vehicle-owner/dashboard.php - לוח בקרה לבעל רכב (תוקן)
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$db = new Database();

// קבלת סטטיסטיקות (תוקן)
$stats = $db->fetchOne(
    "SELECT 
        COUNT(DISTINCT v.id) as vehicle_count,
        COUNT(DISTINCT q.id) as quote_count,
        COUNT(DISTINCT CASE WHEN q.is_selected = 1 THEN q.id END) as accepted_quotes
     FROM users u
     LEFT JOIN vehicles v ON u.id = v.owner_id AND v.is_active = 1
     LEFT JOIN quotes q ON u.id = q.vehicle_owner_id
     WHERE u.id = :user_id",
    [':user_id' => $currentUser['id']]
);

// הזמנות זמינות (ספירה נפרדת)
$availableOrdersCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM orders WHERE status = 'open_for_quotes'"
)['count'];

$stats['available_orders'] = $availableOrdersCount;

// הזמנות זמינות אחרונות
$recentOrders = $db->fetchAll(
    "SELECT o.*, sc.name as sub_category_name 
     FROM orders o
     LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
     WHERE o.status = 'open_for_quotes' 
     ORDER BY o.created_at DESC 
     LIMIT 5"
);

// ההצעות האחרונות שלי (תוקן)
$myQuotes = $db->fetchAll(
    "SELECT q.*, o.order_number, o.work_description as order_desc
     FROM quotes q
     JOIN orders o ON q.order_id = o.id
     WHERE q.vehicle_owner_id = :user_id
     ORDER BY q.created_at DESC
     LIMIT 5",
    [':user_id' => $currentUser['id']]
);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">נהגים - בעל רכב</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">לוח בקרה</a></li>
                <li><a href="vehicles.php">הרכבים שלי</a></li>
                <li><a href="orders.php">הזמנות זמינות</a></li>
                <li><a href="quotes.php">ההצעות שלי</a></li>
                <li><a href="profile.php">פרופיל</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <!-- כותרת וברכה -->
        <div class="welcome-section">
            <h1>שלום <?php echo htmlspecialchars($currentUser['first_name']); ?>! 👋</h1>
            <p>ברוך הבא למערכת ניהול ההזמנות שלך</p>
            
            <!-- אזהרת מנוי אם רלוונטי -->
            <?php if (!$currentUser['is_premium']): ?>
                <div class="alert alert-warning">
                    <strong>💎 שדרג למנוי פרימיום</strong> לגישה מלאה לכל ההזמנות ולתכונות מתקדמות
                    <a href="upgrade.php" class="btn btn-warning btn-sm" style="margin-right: 1rem;">שדרג עכשיו</a>
                </div>
            <?php elseif ($currentUser['premium_expires']): ?>
                <?php 
                $daysLeft = floor((strtotime($currentUser['premium_expires']) - time()) / (60*60*24));
                if ($daysLeft <= 7): ?>
                    <div class="alert alert-info">
                        <strong>⏰ תזכורת:</strong> המנוי הפרימיום שלך יפוג בעוד <?php echo $daysLeft; ?> ימים
                        <a href="profile.php" class="btn btn-primary btn-sm" style="margin-right: 1rem;">חדש מנוי</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- הודעה על הגדרת התראות -->
            <?php if (!$currentUser['notification_whatsapp'] && !$currentUser['notification_email']): ?>
                <div class="alert alert-info">
                    <strong>💚 טיפ:</strong> 
                    <a href="profile.php" style="color: #25D366; text-decoration: underline;">הגדר התראות ווטסאפ</a> 
                    כדי לקבל עדכונים מיידיים על הזמנות חדשות!
                </div>
            <?php endif; ?>
        </div>

        <!-- סטטיסטיקות -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['vehicle_count'] ?? 0; ?></div>
                <div class="stat-label">רכבים רשומים</div>
                <a href="vehicles.php" class="stat-link">נהל רכבים</a>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['quote_count'] ?? 0; ?></div>
                <div class="stat-label">הצעות שנשלחו</div>
                <a href="quotes.php" class="stat-link">צפה בהצעות</a>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['accepted_quotes'] ?? 0; ?></div>
                <div class="stat-label">הצעות שאושרו</div>
                <a href="quotes.php" class="stat-link">עבודות שלי</a>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['available_orders'] ?? 0; ?></div>
                <div class="stat-label">הזמנות זמינות</div>
                <a href="orders.php" class="stat-link">חפש הזמנות</a>
            </div>
        </div>

        <div class="row">
            <!-- הזמנות זמינות -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header d-flex justify-between align-center">
                        <h3>🚛 הזמנות זמינות</h3>
                        <a href="orders.php" class="btn btn-outline">צפה בכל ההזמנות</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentOrders)): ?>
                            <div style="text-align: center; padding: 2rem;">
                                <p style="color: #666;">אין הזמנות זמינות כרגע</p>
                                <div style="margin-top: 1rem; padding: 1rem; background: #f0f8ff; border-radius: 8px;">
                                    <p><strong>💡 בינתיים תוכל:</strong></p>
                                    <ul style="text-align: right; margin: 0.5rem 0;">
                                        <li>לוודא שהרכבים שלך מאומתים</li>
                                        <li>להגדיר התראות ווטסאפ</li>
                                        <li>לעדכן את הפרופיל שלך</li>
                                    </ul>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="order-item" style="border-bottom: 1px solid #eee; padding: 1rem 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <h5><?php echo htmlspecialchars($order['work_description'] ?? 'הזמנה חדשה'); ?></h5>
                                            <p style="color: #666; margin: 0.25rem 0;">
                                                📍 <?php echo htmlspecialchars($order['start_location'] ?? 'מיקום לא צוין'); ?>
                                                <?php if (!empty($order['end_location'])): ?>
                                                    → <?php echo htmlspecialchars($order['end_location']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <small style="color: #999;">
                                                🗓️ <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
                                            </small>
                                        </div>
                                        <a href="orders.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                            שלח הצעה
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ההצעות שלי -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header d-flex justify-between align-center">
                        <h3>💰 ההצעות שלי</h3>
                        <a href="quotes.php" class="btn btn-outline">צפה בכל ההצעות</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($myQuotes)): ?>
                            <div style="text-align: center; padding: 2rem;">
                                <p style="color: #666;">עדיין לא שלחת הצעות מחיר</p>
                                <div style="margin-top: 1rem; padding: 1rem; background: #e8f5e8; border-radius: 8px;">
                                    <p><strong>🎯 התחל עכשיו!</strong></p>
                                    <p>חפש הזמנות מתאימות ושלח הצעות מחיר</p>
                                    <a href="orders.php" class="btn btn-success btn-sm">חפש הזמנות</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($myQuotes as $quote): ?>
                                <div class="quote-item" style="border-bottom: 1px solid #eee; padding: 1rem 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <h5>הזמנה #<?php echo htmlspecialchars($quote['order_number']); ?></h5>
                                            <p style="color: #666; margin: 0.25rem 0;">
                                                <?php echo htmlspecialchars(substr($quote['order_desc'] ?? '', 0, 50)); ?>
                                                <?php if (strlen($quote['order_desc'] ?? '') > 50) echo '...'; ?>
                                            </p>
                                            <div style="display: flex; gap: 1rem; margin-top: 0.5rem; align-items: center;">
                                                <strong style="color: #28a745;">
                                                    💵 <?php echo number_format($quote['quote_amount'] ?? 0); ?>₪
                                                </strong>
                                                <span class="status-badge <?php echo $quote['is_selected'] ? 'status-closed' : 'status-open'; ?>">
                                                    <?php echo $quote['is_selected'] ? 'התקבל ✅' : 'ממתין לתשובה ⏳'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- פעולות מהירות -->
        <div class="card">
            <div class="card-header">
                <h3>⚡ פעולות מהירות</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="vehicles.php?action=add" class="quick-action-card">
                        <div class="icon">🚛</div>
                        <h4>רשום רכב חדש</h4>
                        <p>הוסף רכב למערכת</p>
                    </a>
                    
                    <a href="orders.php" class="quick-action-card">
                        <div class="icon">🔍</div>
                        <h4>חפש הזמנות</h4>
                        <p>מצא הזמנות מתאימות</p>
                    </a>
                    
                    <a href="profile.php" class="quick-action-card whatsapp-action">
                        <div class="icon">💚</div>
                        <h4>הגדר התראות ווטסאפ</h4>
                        <p>קבל עדכונים מיידיים</p>
                    </a>
                    
                    <?php if (!$currentUser['is_premium']): ?>
                    <a href="upgrade.php" class="quick-action-card premium">
                        <div class="icon">💎</div>
                        <h4>שדרג למנוי פרימיום</h4>
                        <p>גישה מלאה לכל התכונות</p>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .welcome-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .quick-action-card {
            display: block;
            padding: 1.5rem;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        
        .quick-action-card:hover {
            border-color: #FF7A00;
            box-shadow: 0 4px 12px rgba(255, 122, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .quick-action-card.premium {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-color: #ffc107;
        }
        
        .quick-action-card.whatsapp-action {
            background: linear-gradient(135deg, #f0fff4 0%, #e8f5e8 100%);
            border-color: #25D366;
        }
        
        .quick-action-card .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .quick-action-card h4 {
            margin: 0.5rem 0;
            color: #333;
        }
        
        .quick-action-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-link {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #FF7A00;
            text-decoration: none;
        }
        
        .stat-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-open {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-closed {
            background: #d1eddc;
            color: #155724;
        }
    </style>

    <script src="../assets/js/main.js"></script>
</body>
</html>