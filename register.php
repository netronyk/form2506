<?php
// register.php - דף הרשמה
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
$userType = $_GET['type'] ?? 'customer';

// וידוא שסוג המשתמש תקין
if (!in_array($userType, ['customer', 'vehicle_owner'])) {
    $userType = 'customer';
}

// טיפול בטופס הרשמה
if ($_POST) {
    $data = [
        'username' => sanitize_input($_POST['username']),
        'email' => sanitize_input($_POST['email']),
        'password' => $_POST['password'],
        'first_name' => sanitize_input($_POST['first_name']),
        'last_name' => sanitize_input($_POST['last_name']),
        'phone' => sanitize_input($_POST['phone']),
        'user_type' => $_POST['user_type']
    ];
    
    // אימות סיסמה
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = 'הסיסמאות אינן תואמות';
    } else {
        $result = $auth->register($data);
        
        if ($result['success']) {
            flash('success', 'ההרשמה בוצעה בהצלחה! כעת תוכל להתחבר');
            redirect('login.php');
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
    <title>הרשמה - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">נהגים</a>
            <ul class="nav-links">
                <li><a href="index.php">דף ראשי</a></li>
                <li><a href="login.php">התחברות</a></li>
            </ul>
        </div>
    </nav>

    <!-- Registration Form -->
    <div class="container" style="max-width: 500px; margin: 3rem auto; padding: 0 1rem;">
        <!-- User Type Selection -->
        <div class="row" style="margin-bottom: 2rem;">
            <div class="col-6">
                <a href="?type=customer" class="btn <?php echo $userType === 'customer' ? 'btn-primary' : 'btn-outline'; ?>" style="width: 100%;">
                    הרשמה כלקוח
                </a>
            </div>
            <div class="col-6">
                <a href="?type=vehicle_owner" class="btn <?php echo $userType === 'vehicle_owner' ? 'btn-primary' : 'btn-outline'; ?>" style="width: 100%;">
                    הרשמה כבעל רכב
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header text-center">
                <h2>
                    <?php if ($userType === 'customer'): ?>
                        הרשמה כלקוח
                    <?php else: ?>
                        הרשמה כבעל רכב
                    <?php endif; ?>
                </h2>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.8;">
                    <?php if ($userType === 'customer'): ?>
                        למלאי טפסי הזמנה וקבלת הצעות מחיר
                    <?php else: ?>
                        לרישום כלי רכב וקבלת הזמנות (69₪ חודשי לגישה מלאה)
                    <?php endif; ?>
                </p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="user_type" value="<?php echo $userType; ?>">
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="first_name" class="form-label">שם פרטי *</label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    class="form-control" 
                                    required 
                                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                >
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="last_name" class="form-label">שם משפחה *</label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    class="form-control" 
                                    required 
                                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">שם משתמש *</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            required 
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            placeholder="לפחות 3 תווים"
                        >
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">כתובת אימייל *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            required 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">טלפון</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                            placeholder="050-1234567"
                        >
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="password" class="form-label">סיסמה *</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-control" 
                                    required 
                                    placeholder="לפחות 6 תווים"
                                >
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">אימות סיסמה *</label>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    required 
                                    placeholder="הזן סיסמה שוב"
                                >
                            </div>
                        </div>
                    </div>

                    <?php if ($userType === 'vehicle_owner'): ?>
                        <div class="alert alert-warning">
                            <strong>שים לב:</strong> לאחר הרשמה תוכל לרשום את הרכבים שלך ולראות הזמנות. 
                            לקבלת פרטי לקוחות ושליחת הצעות מחיר נדרש תשלום חודשי של 69₪.
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        השלם הרשמה
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <p>כבר יש לך חשבון? <a href="login.php" style="color: var(--primary-color);">התחבר כאן</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // בדיקת התאמת סיסמאות
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = 'var(--danger)';
            } else {
                this.style.borderColor = '';
            }
        });

        // בדיקת חוזק סיסמה
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            
            // הצגת אינדיקטור חוזק (אופציונלי)
        });
    </script>
</body>
</html>