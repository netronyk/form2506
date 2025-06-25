<?php
// 404.php - דף שגיאה 404
http_response_code(404);
require_once __DIR__ . '/config/settings.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דף לא נמצא - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">נהגים</a>
            <ul class="nav-links">
                <li><a href="index.php">דף ראשי</a></li>
                <li><a href="login.php">התחברות</a></li>
                <li><a href="register.php">הרשמה</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 4rem; text-align: center;">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-body">
                <div style="font-size: 8rem; color: var(--primary-color); margin-bottom: 1rem;">🚚</div>
                <h1 style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;">404</h1>
                <h2 style="color: var(--secondary-color); margin-bottom: 1rem;">הדף לא נמצא</h2>
                <p style="font-size: 1.2rem; color: var(--dark-gray); margin-bottom: 2rem;">
                    הדף שחיפשת לא קיים או הועבר למיקום אחר.<br>
                    ייתכן שהקישור שלח או שהוזן כתובת שגויה.
                </p>
                
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php" class="btn btn-primary">חזרה לדף הראשי</a>
                    <a href="javascript:history.back()" class="btn btn-outline">חזרה לדף הקודם</a>
                </div>
                
                <div style="margin-top: 2rem; padding: 1rem; background: var(--light-gray); border-radius: var(--border-radius);">
                    <h4>מה אפשר לעשות?</h4>
                    <ul style="text-align: right; margin: 1rem 0;">
                        <li>בדוק את הכתובת בשורת הכתובות</li>
                        <li>חפש את מה שאתה מחפש מהדף הראשי</li>
                        <li>צור קשר איתנו אם אתה חושב שיש כאן בעיה</li>
                    </ul>
                    
                    <div style="margin-top: 1rem;">
                        <a href="mailto:<?php echo SITE_EMAIL; ?>" style="color: var(--primary-color);">
                            📧 <?php echo SITE_EMAIL; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Log 404 for analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'page_not_found', {
                'page_location': window.location.href,
                'page_referrer': document.referrer
            });
        }
    </script>
</body>
</html>