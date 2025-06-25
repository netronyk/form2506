<?php

// login.php - דף התחברות
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

// אם משתמש כבר מחובר - הפניה ללוח בקרה
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// טיפול בטופס התחברות
if ($_POST) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        redirect('dashboard.php');
    } else {
        $error = $result['message'];
    }
}

// הצגת הודעות flash
$flashMessages = get_flash();
if (isset($flashMessages['success'])) {
    $success = $flashMessages['success'];
}
if (isset($flashMessages['error'])) {
    $error = $flashMessages['error'];
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">נהגים</a>
            <ul class="nav-links">
                <li><a href="index.php">דף ראשי</a></li>
                <li><a href="register.php">הרשמה</a></li>
            </ul>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="container" style="max-width: 400px; margin: 4rem auto; padding: 0 1rem;">
        <div class="card">
            <div class="card-header text-center">
                <h2>התחברות למערכת</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">שם משתמש או אימייל</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            required 
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            placeholder="הכנס שם משתמש או אימייל"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">סיסמה</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            required 
                            placeholder="הכנס סיסמה"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        התחבר
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <p>אין לך חשבון? <a href="register.php" style="color: var(--primary-color);">הרשם כאן</a></p>
                    <p><a href="#" onclick="showForgotPassword()" style="color: var(--dark-gray); font-size: 0.9rem;">שכחת סיסמה?</a></p>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div id="forgotPasswordModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: var(--border-radius); width: 90%; max-width: 400px;">
                <h3 style="margin-bottom: 1rem; color: var(--secondary-color);">שחזור סיסמה</h3>
                <form id="forgotPasswordForm">
                    <div class="form-group">
                        <label for="resetEmail" class="form-label">כתובת אימייל</label>
                        <input type="email" id="resetEmail" name="email" class="form-control" required placeholder="הכנס את כתובת האימייל שלך">
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">שלח קישור</button>
                        <button type="button" class="btn btn-secondary" onclick="closeForgotPassword()">ביטול</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Access for Demo -->
        <div class="card" style="margin-top: 2rem; background: #f8f9fa;">
            <div class="card-body">
                <h4 style="color: var(--secondary-color); margin-bottom: 1rem;">גישה מהירה (דמו)</h4>
                <div style="display: grid; gap: 0.5rem;">
                    <button onclick="loginAs('admin', 'admin123')" class="btn btn-secondary" style="padding: 0.5rem;">התחבר כמנהל מערכת</button>
                    <button onclick="loginAs('vehicle_owner', 'owner123')" class="btn btn-outline" style="padding: 0.5rem;">התחבר כבעל רכב</button>
                    <button onclick="loginAs('customer', 'customer123')" class="btn btn-outline" style="padding: 0.5rem;">התחבר כלקוח</button>
                </div>
                <p style="font-size: 0.8rem; color: var(--dark-gray); margin-top: 0.5rem;">
                    * אפשרויות אלו זמינות רק בגרסת הדמו
                </p>
            </div>
        </div>
    </div>

    <script>
        function showForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'block';
        }

        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }

        function loginAs(type, password) {
            // מילוי אוטומטי של הטופס
            document.getElementById('username').value = type;
            document.getElementById('password').value = password;
            
            // שליחת הטופס
            document.querySelector('form').submit();
        }

        // טיפול בטופס שחזור סיסמה
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('resetEmail').value;
            
            // כאן תוכל להוסיף AJAX call לשרת
            alert('קישור לשחזור סיסמה נשלח לכתובת: ' + email);
            closeForgotPassword();
        });

        // סגירת modal בלחיצה על הרקע
        document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeForgotPassword();
            }
        });
    </script>
</body>
</html>