<?php
// customer/profile.php - פרופיל לקוח עם הגדרות התראות ווטסאפ
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkPermission('customer')) {
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
            <a href="../index.php" class="logo">נהגים - לקוח</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">לוח בקרה</a></li>
                <li><a href="new-order.php">הזמנה חדשה</a></li>
                <li><a href="orders.php">ההזמנות שלי</a></li>
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
                <!-- מידע חשבון -->
                <div class="card">
                    <div class="card-header">
                        <h3>מידע חשבון</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>שם משתמש:</strong> <?php echo htmlspecialchars($currentUser['username']); ?></p>
                        <p><strong>סוג חשבון:</strong> לקוח</p>
                        <p><strong>תאריך הצטרפות:</strong> <?php echo date('d/m/Y', strtotime($currentUser['created_at'])); ?></p>
                        <p><strong>סטטוס:</strong> 
                            <?php if ($currentUser['is_active']): ?>
                                <span class="status-badge status-closed">פעיל</span>
                            <?php else: ?>
                                <span class="status-badge status-open">לא פעיל</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- הגדרות התראות ווטסאפ -->
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
                $db = new Database();
                $recentActivity = $db->fetchAll(
                    "SELECT 'order' as type, order_number as title, created_at, status
                     FROM orders WHERE customer_id = :id
                     UNION ALL
                     SELECT 'review' as type, CONCAT('ביקורת על הזמנה #', o.order_number) as title, r.created_at, 'completed' as status
                     FROM reviews r 
                     JOIN orders o ON r.order_id = o.id 
                     WHERE r.reviewer_id = :id2
                     ORDER BY created_at DESC 
                     LIMIT 10",
                    [':id' => $currentUser['id'], ':id2' => $currentUser['id']]
                );
                ?>
                
                <?php if (empty($recentActivity)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <p style="color: var(--dark-gray);">אין פעילות להצגה</p>
                        <div style="margin-top: 1rem; padding: 1rem; background: #f0f8ff; border-radius: 8px;">
                            <p><strong>💡 התחל עם ההזמנה הראשונה שלך!</strong></p>
                            <p>צור הזמנה חדשה ותקבל הצעות מחיר מבעלי רכב</p>
                            <a href="new-order.php" class="btn btn-primary">צור הזמנה חדשה</a>
                        </div>
                        
                        <?php if (!$currentUser['notification_whatsapp']): ?>
                            <div style="margin-top: 1rem; padding: 1rem; background: #e8f5e8; border-radius: 8px;">
                                <p><strong>💚 הפעל התראות ווטסאפ</strong></p>
                                <p>קבל עדכונים מיידיים על הצעות מחיר חדשות!</p>
                                <button onclick="document.querySelector('#whatsappToggle').scrollIntoView(); document.querySelector('#whatsappToggle').focus();" 
                                        class="btn btn-success">
                                    הגדר התראות ווטסאפ
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>פעילות</th>
                                <th>תאריך</th>
                                <th>סטטוס</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <?php if ($activity['type'] === 'order'): ?>
                                            📝 הזמנה <?php echo htmlspecialchars($activity['title']); ?>
                                        <?php else: ?>
                                            ⭐ <?php echo htmlspecialchars($activity['title']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace('_', '-', $activity['status']); ?>">
                                            <?php 
                                            $statusLabels = [
                                                'open_for_quotes' => 'פתוח להצעות',
                                                'in_negotiation' => 'במשא ומתן',
                                                'closed' => 'סגור',
                                                'completed' => 'הושלם'
                                            ];
                                            echo $statusLabels[$activity['status']] ?? $activity['status'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- טיפים לשימוש במערכת -->
        <div class="card">
            <div class="card-header">
                <h3>💡 טיפים לשימוש במערכת</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📝</div>
                            <h5>כתוב תיאור מפורט</h5>
                            <p style="font-size: 0.9rem; color: #666;">
                                תיאור מדויק של העבודה יביא הצעות מחיר מדויקות יותר
                            </p>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">💚</div>
                            <h5>הפעל התראות ווטסאפ</h5>
                            <p style="font-size: 0.9rem; color: #666;">
                                קבל עדכונים מיידיים על הצעות מחיר חדשות
                            </p>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="text-align: center; padding: 1rem;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚡</div>
                            <h5>השווה הצעות</h5>
                            <p style="font-size: 0.9rem; color: #666;">
                                בדוק מספר הצעות לפני בחירת בעל הרכב
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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