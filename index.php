<?php
// index.php - דף ראשי
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/Order.php';

$auth = new Auth();

// בדיקה אם משתמש מחובר - הפניה ללוח בקרה
if ($auth->isLoggedIn()) {
    redirect('dashboard.php');
}

// קבלת הזמנות פתוחות (8 אחרונות)
$order = new Order();
$openOrders = $order->getAllOrders(null, 'open_for_quotes');
$openOrders = array_slice($openOrders, 0, 8);

// סטטיסטיקות
$stats = $order->getOrdersStats();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">נהגים</a>
            <ul class="nav-links">
                <li><a href="login.php">התחברות</a></li>
                <li><a href="register.php">הרשמה</a></li>
                <li><a href="#about">אודות</a></li>
                <li><a href="#contact">צור קשר</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" style="background: linear-gradient(135deg, var(--primary-color), #ff9533); padding: 4rem 0; color: white; text-align: center;">
        <div class="container">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">מערכת הזמנות כלי רכב תפעוליים</h1>
            <p style="font-size: 1.3rem; margin-bottom: 2rem;">מקשרים בין בעלי כלי רכב תפעוליים ללקוחות בצורה מהירה ויעילה</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="register.php?type=customer" class="btn btn-outline" style="border-color: white; color: white;">אני מעוניין לפתוח הזמנה חדשה</a>
                <a href="register.php?type=vehicle_owner" class="btn" style="background: white; color: var(--primary-color);">אני בעל רכב תפעולי ומעוניין להצטרף למאגר כלי הרכב התפעוליים</a>
            </div>
        </div>
    </section>

    <!-- הזמנות זמינות עכשיו -->
    <?php if (!empty($openOrders)): ?>
    <section style="padding: 3rem 0; background: #f8f9fa;">
        <div class="container">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="color: var(--primary-color); font-size: 2.5rem; margin-bottom: 0.5rem;">🔥 הזמנות זמינות עכשיו</h2>
                <p style="font-size: 1.1rem; color: var(--dark-gray);">הירשם כבעל רכב לראות פרטים מלאים: תקציב, פרטי קשר ותיאור מפורט</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>הזמנה</th>
                                <th>סוג עבודה</th>
                                <th>מיקום התחלה</th>
                                <th>מיקום סיום</th>
                                <th>תאריך</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openOrders as $ord): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo $ord['order_number']; ?></strong>
                                        <br><small style="color: var(--dark-gray);"><?php echo date('d/m/Y', strtotime($ord['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ord['main_category_name'] ?? 'כללי'); ?></strong>
                                        <br><small><?php echo htmlspecialchars(substr($ord['work_description'], 0, 40)) . '...'; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $location = explode(',', $ord['start_location']);
                                        echo htmlspecialchars(trim(end($location))); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($ord['end_location']): ?>
                                            <?php 
                                            $endLocation = explode(',', $ord['end_location']);
                                            echo htmlspecialchars(trim(end($endLocation))); 
                                            ?>
                                        <?php else: ?>
                                            <span style="color: var(--dark-gray);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($ord['work_start_date'])); ?>
                                        <?php if ($ord['work_start_time']): ?>
                                            <br><small><?php echo date('H:i', strtotime($ord['work_start_time'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <span class="status-badge status-open">פתוח להצעות</span>
                                            <a href="register.php?type=vehicle_owner" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                שלח הצעה
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <p style="margin-bottom: 1rem; font-size: 1.1rem;"><strong>רוצה לראות עוד הזמנות?</strong></p>
                <a href="register.php?type=vehicle_owner" class="btn btn-primary" style="padding: 1rem 2rem;">
                    הירשם חינם כבעל רכב ↗️
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <section style="padding: 4rem 0;">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 3rem; color: var(--secondary-color);">איך זה עובד?</h2>
            
            <div class="row">
                <div class="col-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">🚚</div>
                            <h3>בעלי כלי רכב תפעוליים</h3>
                            <p>בעלי כלי רכב תפעוליים רושמים את הרכבים שלהם במערכת עם כל הפרטים הטכניים</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">📝</div>
                            <h3>לקוחות מזמינים</h3>
                            <p>לקוחות ממלאים טופס הזמנה עם הדרישות שלהם וקבלים הצעות מחיר ממגוון בעלי רכב</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">🤝</div>
                            <h3>התאמה מושלמת</h3>
                            <p>המערכת מתאימה את הכלי המתאים ביותר לצרכים של הלקוח לפי מיקום וסוג העבודה</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section style="padding: 4rem 0; background: var(--light-gray);">
        <div class="container">
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_orders'] ?? 500; ?>+</div>
                    <div class="stat-label">הזמנות במערכת</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['open_orders'] ?? 0; ?></div>
                    <div class="stat-label">הזמנות פתוחות עכשיו</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['closed_orders'] ?? 450; ?>+</div>
                    <div class="stat-label">הזמנות הושלמו</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">זמינות המערכת</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Vehicle Categories -->
    <section style="padding: 4rem 0;">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 3rem; color: var(--secondary-color);">סוגי כלי רכב במערכת</h2>
            
            <div class="row">
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <h4 style="color: var(--primary-color);">משאיות ורכב מסחרי</h4>
                            <ul style="margin: 1rem 0;">
                                <li>משאיות עם מנוף</li>
                                <li>משאיות מכולה</li>
                                <li>טנדרים</li>
                                <li>משאיות פסולת</li>
                                <li>משאיות מיכלית</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <h4 style="color: var(--primary-color);">ציוד מכני הנדסי</h4>
                            <ul style="margin: 1rem 0;">
                                <li>מחפרונים</li>
                                <li>טרקטורים</li>
                                <li>מעמיסים</li>
                                <li>מלגזות</li>
                                <li>במות הרמה</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <h4 style="color: var(--primary-color);">רכבי הסעה</h4>
                            <ul style="margin: 1rem 0;">
                                <li>אוטובוסים</li>
                                <li>מניבוסים</li>
                                <li>רכבי תיירות</li>
                                <li>הסעות עובדים</li>
                                <li>הסעות נכים</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <h4 style="color: var(--primary-color);">שירותים מיוחדים</h4>
                            <ul style="margin: 1rem 0;">
                                <li>גרר וחילוץ</li>
                                <li>רכבי חירום</li>
                                <li>נגררים להשכרה</li>
                                <li>שירותי דרך</li>
                                <li>ציוד מתמחה</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="background: var(--secondary-color); padding: 4rem 0; color: white; text-align: center;">
        <div class="container">
            <h2 style="margin-bottom: 1rem;">מוכנים להתחיל?</h2>
            <p style="font-size: 1.2rem; margin-bottom: 2rem;">הצטרפו אלינו עוד היום ותתחילו לקבל/לתת הזמנות</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="register.php?type=customer" class="btn btn-primary">הרשמה כלקוח</a>
                <a href="register.php?type=vehicle_owner" class="btn btn-outline" style="border-color: white; color: white;">הרשמה כבעל רכב</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: #333; color: white; padding: 2rem 0; text-align: center;">
        <div class="container">
            <p>&copy; 2025 נהגים - מערכת הזמנות כלי רכב תפעוליים. כל הזכויות שמורות.</p>
            <p style="margin-top: 0.5rem;">
                <a href="mailto:<?php echo SITE_EMAIL; ?>" style="color: var(--primary-color);"><?php echo SITE_EMAIL; ?></a> |
                <a href="tel:03-1234567" style="color: var(--primary-color);">03-1234567</a>
            </p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>