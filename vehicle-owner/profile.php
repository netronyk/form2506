<?php
// vehicle-owner/profile.php - פרופיל בעל רכב
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$message = '';
$error = '';

// טיפול בעדכון פרופיל
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        $result = $auth->updateProfile($_POST);
        if ($result['success']) {
            $message = $result['message'];
            $currentUser = $auth->getCurrentUser(); // רענון נתונים
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['change_password'])) {
        $result = $auth->changePassword($_POST['current_password'], $_POST['new_password']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// קבלת סטטיסטיקות בעל הרכב
$db = new Database();
$stats = $db->fetchOne(
    "SELECT 
        COUNT(DISTINCT v.id) as vehicle_count,
        COUNT(DISTINCT q.id) as quote_count,
        0 as accepted_quotes,
        0 as avg_rating,
        COUNT(DISTINCT r.id) as review_count
     FROM users u
     LEFT JOIN vehicles v ON u.id = v.owner_id AND v.is_active = 1
     LEFT JOIN quotes q ON u.id = q.vehicle_owner_id
     LEFT JOIN reviews r ON v.id = r.vehicle_id
     WHERE u.id = :user_id",
    [':user_id' => $currentUser['id']]
);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הפרופיל שלי - <?php echo SITE_NAME; ?></title>
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
        <h1>הפרופיל שלי</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- סטטיסטיקות בעל רכב -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['vehicle_count'] ?? 0; ?></div>
                <div class="stat-label">רכבים רשומים</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['quote_count'] ?? 0; ?></div>
                <div class="stat-label">הצעות מחיר שנשלחו</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['accepted_quotes'] ?? 0; ?></div>
                <div class="stat-label">הצעות שאושרו</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '-'; ?>
                </div>
                <div class="stat-label">דירוג ממוצע</div>
            </div>
        </div>

        <div class="row">
            <!-- עדכון פרטים אישיים -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3>פרטים אישיים</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">שם פרטי</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">שם משפחה</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">אימייל</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">טלפון</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                עדכן פרטים
                            </button>
                        </form>
                    </div>
                </div>

                <!-- שינוי סיסמה -->
                <div class="card">
                    <div class="card-header">
                        <h3>שינוי סיסמה</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <div class="form-group">
                                <label class="form-label">סיסמה נוכחית</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">סיסמה חדשה</label>
                                <input type="password" name="new_password" id="newPassword" class="form-control" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">אישור סיסמה חדשה</label>
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-secondary">
                                שנה סיסמה
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- עמודה שנייה -->
            <div class="col-6">
                <!-- מידע חשבון ומנוי -->
                <div class="card">
                    <div class="card-header">
                        <h3>מידע חשבון</h3>
                    </div>
                    <div class="card-body">
                        <div class="account-info">
                            <div class="info-row">
                                <strong>שם משתמש:</strong> <?php echo htmlspecialchars($currentUser['username']); ?>
                            </div>
                            <div class="info-row">
                                <strong>סוג חשבון:</strong> בעל רכב
                            </div>
                            <div class="info-row">
                                <strong>תאריך הצטרפות:</strong> <?php echo date('d/m/Y', strtotime($currentUser['created_at'])); ?>
                            </div>
                            <div class="info-row">
                                <strong>סטטוס חשבון:</strong> 
                                <?php if ($currentUser['is_active']): ?>
                                    <span class="status-badge status-closed">פעיל</span>
                                <?php else: ?>
                                    <span class="status-badge status-open">לא פעיל</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- מידע מנוי פרימיום -->
                            <div class="premium-info">
                                <h4>מנוי פרימיום</h4>
                                <?php if ($currentUser['is_premium']): ?>
                                    <div class="premium-active">
                                        <span class="status-badge" style="background: #ffc107; color: #000;">⭐ פרימיום פעיל</span>
                                        <?php if ($currentUser['premium_expires']): ?>
                                            <p><strong>עד:</strong> <?php echo date('d/m/Y', strtotime($currentUser['premium_expires'])); ?></p>
                                            <?php 
                                            $daysLeft = floor((strtotime($currentUser['premium_expires']) - time()) / (60*60*24));
                                            if ($daysLeft <= 7): ?>
                                                <div class="alert alert-warning" style="margin-top: 0.5rem;">
                                                    ⚠️ המנוי יפוג בעוד <?php echo $daysLeft; ?> ימים
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="premium-inactive">
                                        <span class="status-badge" style="background: #6c757d; color: white;">מנוי רגיל</span>
                                        <p>שדרג למנוי פרימיום לגישה מלאה להזמנות</p>
                                        <a href="upgrade.php" class="btn btn-warning btn-sm">שדרג למנוי פרימיום</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- הגדרות התראות -->
                <?php include __DIR__ . '/../includes/notification-settings.php'; ?>
            </div>
        </div>

        <!-- פעילות אחרונה -->
        <div class="card">
            <div class="card-header">
                <h3>פעילות אחרונה</h3>
            </div>
            <div class="card-body">
                <?php
                $recentActivity = $db->fetchAll(
                    "SELECT 'quote' as type, CONCAT('הצעת מחיר על הזמנה #', o.order_number) as title, 
                            q.created_at, 'sent' as status, q.quote_price
                     FROM quotes q 
                     JOIN orders o ON q.order_id = o.id 
                     WHERE q.vehicle_owner_id = :id
                     UNION ALL
                     SELECT 'vehicle' as type, CONCAT('רכב חדש: ', v.vehicle_name) as title, 
                            v.created_at, CASE WHEN v.is_verified THEN 'verified' ELSE 'pending' END as status, NULL as quote_price
                     FROM vehicles v 
                     WHERE v.owner_id = :id2
                     ORDER BY created_at DESC 
                     LIMIT 10",
                    [':id' => $currentUser['id'], ':id2' => $currentUser['id']]
                );
                ?>
                
                <?php if (empty($recentActivity)): ?>
                    <p style="text-align: center; color: var(--dark-gray);">אין פעילות להצגה</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>פעילות</th>
                                <th>תאריך</th>
                                <th>סטטוס</th>
                                <th>פרטים</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <?php if ($activity['type'] === 'quote'): ?>
                                            💰 <?php echo htmlspecialchars($activity['title']); ?>
                                        <?php else: ?>
                                            🚛 <?php echo htmlspecialchars($activity['title']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace('_', '-', $activity['status']); ?>">
                                            <?php 
                                            $statusLabels = [
                                                'pending' => 'ממתין',
                                                'accepted' => 'התקבל',
                                                'rejected' => 'נדחה',
                                                'verified' => 'מאומת',
                                                'sent' => 'נשלח'
                                            ];
                                            echo $statusLabels[$activity['status']] ?? $activity['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($activity['type'] === 'quote' && $activity['quote_price']): ?>
                                            <?php echo number_format($activity['quote_price']); ?>₪
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .account-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .account-info .info-row:last-child {
            border-bottom: none;
        }
        
        .premium-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .premium-info h4 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .premium-active, .premium-inactive {
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
        }
        
        .premium-active {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
        }
        
        .premium-inactive {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
    </style>

    <script>
        // בדיקת התאמת סיסמאות
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.style.borderColor = 'var(--danger)';
            } else {
                this.style.borderColor = '';
            }
        });

        // אימות טופס סיסמה
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('הסיסמאות אינן תואמות');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('הסיסמה חייבת להיות לפחות 6 תווים');
                return false;
            }
        });
    </script>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>