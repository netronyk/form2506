<?php
// 403.php - דף שגיאת הרשאה
http_response_code(403);
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$isLoggedIn = $auth->isLoggedIn();
$userType = $isLoggedIn ? get_user_type() : null;
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אין הרשאה - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">נהגים</a>
            <ul class="nav-links">
                <li><a href="index.php">דף ראשי</a></li>
                <?php if ($isLoggedIn): ?>
                    <li><a href="dashboard.php">לוח בקרה</a></li>
                    <li><a href="logout.php">התנתקות</a></li>
                <?php else: ?>
                    <li><a href="login.php">התחברות</a></li>
                    <li><a href="register.php">הרשמה</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 4rem; text-align: center;">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-body">
                <div style="font-size: 8rem; color: var(--danger); margin-bottom: 1rem;">🚫</div>
                <h1 style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;">403</h1>
                <h2 style="color: var(--secondary-color); margin-bottom: 1rem;">אין הרשאה</h2>
                
                <?php if (!$isLoggedIn): ?>
                    <p style="font-size: 1.2rem; color: var(--dark-gray); margin-bottom: 2rem;">
                        אתה לא מחובר למערכת.<br>
                        נדרשת התחברות כדי לגשת לעמוד זה.
                    </p>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="login.php" class="btn btn-primary">התחבר למערכת</a>
                        <a href="register.php" class="btn btn-outline">הרשם למערכת</a>
                    </div>
                    
                <?php else: ?>
                    <p style="font-size: 1.2rem; color: var(--dark-gray); margin-bottom: 2rem;">
                        אין לך הרשאה לגשת לעמוד זה.<br>
                        העמוד מיועד למשתמשים מסוג אחר או דורש הרשאות מיוחדות.
                    </p>
                    
                    <div style="margin: 2rem 0; padding: 1rem; background: var(--light-gray); border-radius: var(--border-radius);">
                        <p><strong>אתה מחובר כ:</strong> 
                            <?php 
                            $userTypeLabels = [
                                'admin' => 'מנהל מערכת',
                                'vehicle_owner' => 'בעל רכב',
                                'customer' => 'לקוח'
                            ];
                            echo $userTypeLabels[$userType] ?? $userType;
                            ?>
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="dashboard.php" class="btn btn-primary">לוח הבקרה שלי</a>
                        <a href="index.php" class="btn btn-outline">דף ראשי</a>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 2rem; padding: 1rem; background: var(--light-gray); border-radius: var(--border-radius);">
                    <h4>סוגי המשתמשים במערכת:</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div style="padding: 1rem; background: white; border-radius: 8px;">
                            <strong>🏠 לקוחות</strong><br>
                            <small>יוצרים הזמנות ובוחרים הצעות</small>
                        </div>
                        <div style="padding: 1rem; background: white; border-radius: 8px;">
                            <strong>🚛 בעלי רכב</strong><br>
                            <small>מספקים שירותים ושולחים הצעות</small>
                        </div>
                        <div style="padding: 1rem; background: white; border-radius: 8px;">
                            <strong>⚙️ מנהלי מערכת</strong><br>
                            <small>מנהלים את כל המערכת</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($isLoggedIn && $userType !== 'admin'): ?>
                    <div style="margin-top: 2rem;">
                        <p style="font-size: 0.9rem; color: var(--dark-gray);">
                            חושב שיש כאן שגיאה? 
                            <a href="mailto:<?php echo SITE_EMAIL; ?>" style="color: var(--primary-color);">
                                צור קשר עם התמיכה
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto redirect after login
        <?php if (!$isLoggedIn): ?>
        const currentUrl = encodeURIComponent(window.location.href);
        const loginUrl = 'login.php?redirect=' + currentUrl;
        
        setTimeout(() => {
            if (confirm('האם תרצה להתחבר כעת?')) {
                window.location.href = loginUrl;
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>