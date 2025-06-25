<?php
// customer/quotes.php - הצעות מחיר ללקוח
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Order.php';

$auth = new Auth();
if (!$auth->checkPermission('customer')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$db = new Database();
$order = new Order();

$message = '';
$error = '';

// טיפול בבחירת הצעה
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'select_quote') {
    $result = $order->selectQuote($_POST['quote_id']);
    $message = $result['success'] ? 'ההצעה נבחרה בהצלחה' : $result['message'];
}

// סינונים
$statusFilter = $_GET['status'] ?? '';
$orderFilter = $_GET['order_id'] ?? '';

// בניית שאילתת SQL לקבלת הצעות
$sql = "SELECT 
            q.id as quote_id,
            q.quote_amount,
            q.quote_description,
            q.is_selected,
            q.created_at as quote_date,
            o.id as order_id,
            o.order_number,
            o.work_description,
            o.status as order_status,
            o.work_start_date,
            o.start_location,
            o.end_location,
            v.vehicle_name,
            v.description as vehicle_description,
            sc.name as vehicle_type,
            mc.name as main_category_name,
            u.first_name,
            u.last_name,
            u.phone,
            u.email,
            u.is_premium
        FROM quotes q
        JOIN orders o ON q.order_id = o.id
        JOIN vehicles v ON q.vehicle_id = v.id
        JOIN sub_categories sc ON v.sub_category_id = sc.id
        JOIN main_categories mc ON sc.main_category_id = mc.id
        JOIN users u ON q.vehicle_owner_id = u.id
        WHERE o.customer_id = :customer_id";

$params = [':customer_id' => $currentUser['id']];

// הוספת סינונים
if ($statusFilter) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $statusFilter;
}

if ($orderFilter) {
    $sql .= " AND o.id = :order_id";
    $params[':order_id'] = $orderFilter;
}

$sql .= " ORDER BY q.created_at DESC";

$quotes = $db->fetchAll($sql, $params);

// קבלת רשימת הזמנות לסינון
$myOrders = $db->fetchAll(
    "SELECT id, order_number, work_description FROM orders WHERE customer_id = :id ORDER BY created_at DESC",
    [':id' => $currentUser['id']]
);

// סטטיסטיקות
$totalQuotes = count($quotes);
$selectedQuotes = count(array_filter($quotes, function($q) { return $q['is_selected']; }));
$pendingQuotes = count(array_filter($quotes, function($q) { return !$q['is_selected'] && $q['order_status'] === 'open_for_quotes'; }));
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הצעות שקיבלתי - <?php echo SITE_NAME; ?></title>
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
                <li><a href="quotes.php">הצעות שקיבלתי</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <div class="d-flex justify-between align-center mb-3">
            <h1>הצעות המחיר שקיבלתי</h1>
            <a href="new-order.php" class="btn btn-primary">הזמנה חדשה</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- סטטיסטיקות -->
        <div class="dashboard-stats" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 1.5rem;">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalQuotes; ?></div>
                <div class="stat-label">סה"כ הצעות</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $selectedQuotes; ?></div>
                <div class="stat-label">הצעות שנבחרו</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendingQuotes; ?></div>
                <div class="stat-label">הצעות ממתינות</div>
            </div>
        </div>

        <!-- סינונים -->
        <div class="card">
            <div class="card-header">
                <h3>סינון הצעות</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">סטטוס הזמנה</label>
                            <select name="status" class="form-control">
                                <option value="">כל הסטטוסים</option>
                                <option value="open_for_quotes" <?php echo $statusFilter === 'open_for_quotes' ? 'selected' : ''; ?>>פתוח להצעות</option>
                                <option value="in_negotiation" <?php echo $statusFilter === 'in_negotiation' ? 'selected' : ''; ?>>במשא ומתן</option>
                                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>סגור</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">הזמנה ספציפית</label>
                            <select name="order_id" class="form-control">
                                <option value="">כל ההזמנות</option>
                                <?php foreach ($myOrders as $myOrder): ?>
                                    <option value="<?php echo $myOrder['id']; ?>" <?php echo $orderFilter == $myOrder['id'] ? 'selected' : ''; ?>>
                                        #<?php echo $myOrder['order_number']; ?> - <?php echo htmlspecialchars(substr($myOrder['work_description'], 0, 40)); ?>...
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">סנן</button>
                                <a href="quotes.php" class="btn btn-outline">נקה</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- רשימת הצעות -->
        <?php if (empty($quotes)): ?>
            <div class="card text-center">
                <div class="card-body">
                    <h3>עדיין לא קיבלת הצעות מחיר</h3>
                    <p>לאחר שתיצור הזמנה, בעלי רכבים יוכלו לשלוח לך הצעות מחיר</p>
                    <a href="new-order.php" class="btn btn-primary">צור הזמנה ראשונה</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3>הצעות המחיר (<?php echo count($quotes); ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($quotes as $quote): ?>
                            <div class="col-6 mb-3">
                                <div class="card <?php echo $quote['is_selected'] ? 'border-success' : ''; ?>">
                                    <div class="card-header d-flex justify-between align-center">
                                        <div>
                                            <h5><?php echo htmlspecialchars($quote['vehicle_name']); ?></h5>
                                            <small>הזמנה #<?php echo $quote['order_number']; ?></small>
                                        </div>
                                        <div class="text-left">
                                            <strong style="color: var(--primary-color); font-size: 1.2rem;">
                                                <?php echo format_price($quote['quote_amount']); ?>
                                            </strong>
                                            <?php if ($quote['is_premium']): ?>
                                                <div><small style="color: gold;">★ פרימיום</small></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- פרטי בעל הרכב -->
                                        <div style="margin-bottom: 1rem;">
                                            <p><strong>בעל הרכב:</strong> <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?></p>
                                            <p><strong>סוג רכב:</strong> <?php echo htmlspecialchars($quote['main_category_name'] . ' → ' . $quote['vehicle_type']); ?></p>
                                        </div>
                                        
                                        <!-- פרטי ההזמנה -->
                                        <div style="margin-bottom: 1rem; padding: 0.5rem; background: #f8f9fa; border-radius: 5px;">
                                            <small>
                                                <strong>עבודה:</strong> <?php echo htmlspecialchars(substr($quote['work_description'], 0, 100)); ?>...<br>
                                                <strong>תאריך:</strong> <?php echo date('d/m/Y', strtotime($quote['work_start_date'])); ?><br>
                                                <strong>מקום:</strong> <?php echo htmlspecialchars($quote['start_location']); ?>
                                                <?php if ($quote['end_location']): ?>
                                                    → <?php echo htmlspecialchars($quote['end_location']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <!-- תיאור ההצעה -->
                                        <?php if ($quote['quote_description']): ?>
                                            <div style="margin-bottom: 1rem;">
                                                <strong>הערות מבעל הרכב:</strong><br>
                                                <em><?php echo htmlspecialchars($quote['quote_description']); ?></em>
                                            </div>
                                        <?php endif; ?>

                                        <!-- סטטוס והצעה -->
                                        <div style="margin-bottom: 1rem;">
                                            <span class="status-badge status-<?php echo str_replace('_', '-', $quote['order_status']); ?>">
                                                <?php 
                                                $statusLabels = [
                                                    'open_for_quotes' => 'פתוח להצעות',
                                                    'in_negotiation' => 'במשא ומתן',
                                                    'closed' => 'סגור'
                                                ];
                                                echo $statusLabels[$quote['order_status']];
                                                ?>
                                            </span>
                                            
                                            <?php if ($quote['is_selected']): ?>
                                                <span class="status-badge" style="background: var(--success); color: white; margin-right: 0.5rem;">
                                                    נבחר ✓
                                                </span>
                                            <?php endif; ?>
                                            
                                            <small style="display: block; margin-top: 0.5rem; color: var(--dark-gray);">
                                                הצעה התקבלה: <?php echo date('d/m/Y H:i', strtotime($quote['quote_date'])); ?>
                                            </small>
                                        </div>

                                        <!-- פעולות -->
                                        <div class="d-flex justify-between align-center">
                                            <div>
                                                <a href="orders.php?action=view&id=<?php echo $quote['order_id']; ?>" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                                                    צפה בהזמנה
                                                </a>
                                            </div>
                                            
                                            <div>
                                                <?php if ($quote['is_selected']): ?>
                                                    <!-- אם ההצעה נבחרה - הצג פרטי קשר -->
                                                    <div style="text-align: left;">
                                                        <small><strong>טלפון:</strong> <?php echo htmlspecialchars($quote['phone']); ?></small><br>
                                                        <small><strong>אימייל:</strong> <?php echo htmlspecialchars($quote['email']); ?></small>
                                                    </div>
                                                <?php elseif ($quote['order_status'] === 'open_for_quotes'): ?>
                                                    <!-- אם ההזמנה עדיין פתוחה - אפשר לבחור -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="quote_id" value="<?php echo $quote['quote_id']; ?>">
                                                        <button type="submit" name="action" value="select_quote" 
                                                                class="btn btn-success" style="padding: 0.5rem 1rem;"
                                                                onclick="return confirm('האם אתה בטוח שברצונך לבחור הצעה זו?')">
                                                            בחר הצעה
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- ההזמנה לא פתוחה יותר -->
                                                    <small style="color: var(--dark-gray);">לא ניתן לבחור</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- טיפים -->
        <div class="card">
            <div class="card-header">
                <h3>טיפים לבחירת הצעה טובה</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <h5>בדוק המחיר</h5>
                        <ul style="font-size: 0.9rem;">
                            <li>השווה מחירים בין הצעות שונות</li>
                            <li>בדוק אם המחיר כולל הכל או יש תוספות</li>
                            <li>שים לב לסוג התמחור (כולל/שעתי/יומי)</li>
                        </ul>
                    </div>
                    <div class="col-4">
                        <h5>איכות השירות</h5>
                        <ul style="font-size: 0.9rem;">
                            <li>עדיפות לבעלי רכב פרימיום ★</li>
                            <li>קרא את ההערות והתיאור</li>
                            <li>בדוק דירוגים וביקורות</li>
                        </ul>
                    </div>
                    <div class="col-4">
                        <h5>מהירות התגובה</h5>
                        <ul style="font-size: 0.9rem;">
                            <li>בעלי רכב שמגיבים מהר בדרך כלל אמינים יותר</li>
                            <li>שים לב לזמן שלקח להם לשלוח הצעה</li>
                            <li>בדוק זמינות לתאריכים הרצויים</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>