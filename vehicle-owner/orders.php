<?php
// vehicle-owner/orders.php - הזמנות זמינות לבעל רכב (תוקן מלאה)
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Vehicle.php';
require_once __DIR__ . '/../models/Notification.php';

$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$order = new Order();
$vehicle = new Vehicle();
$notification = new Notification();
$db = new Database();

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'list';

$message = '';
$error = '';
$isPremium = $auth->isPremium();

// טיפול בשליחת הצעת מחיר
if ($_POST && isset($_POST['send_quote'])) {
    if (!$isPremium) {
        $error = 'נדרש מנוי פרימיום לשליחת הצעות מחיר';
    } else {
        try {
            $_POST['vehicle_owner_id'] = $currentUser['id'];
            $result = $order->addQuote($_POST);
            
            if ($result['success']) {
                // קבלת פרטי ההזמנה ללקוח להתראה
                $orderDetails = $order->getOrderById($_POST['order_id']);
                if ($orderDetails) {
                    // שליחת התראה ללקוח על הצעת מחיר חדשה
                    $notification->notifyNewQuote(
                        $_POST['order_id'], 
                        $orderDetails['customer_id'], 
                        $currentUser['id'], 
                        $_POST['quote_amount']
                    );
                }
                
                $message = 'הצעת המחיר נשלחה בהצלחה והלקוח קיבל התראה!';
                // ניקוי הטופס
                unset($_POST);
                $action = 'view';
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = 'שגיאה בשליחת ההצעה: ' . $e->getMessage();
        }
    }
}

// טיפול בעדכון הצעה קיימת
if ($_POST && isset($_POST['update_quote'])) {
    if (!$isPremium) {
        $error = 'נדרש מנוי פרימיום';
    } else {
        try {
            $db->update('quotes', [
                'quote_amount' => $_POST['quote_amount'],
                'quote_description' => $_POST['quote_description']
            ], 'id = :id AND vehicle_owner_id = :owner_id', [
                ':id' => $_POST['quote_id'],
                ':owner_id' => $currentUser['id']
            ]);
            
            $message = 'הצעת המחיר עודכנה בהצלחה';
        } catch (Exception $e) {
            $error = 'שגיאה בעדכון ההצעה';
        }
    }
}

// קבלת נתונים
if ($action === 'view' && $id) {
    $orderDetails = $order->getOrderById($id);
    if (!$orderDetails) {
        redirect('orders.php');
    }
    
    // בדיקה אם כבר נתן הצעה לאותה הזמנה
    $myExistingQuote = null;
    if ($isPremium) {
        $myExistingQuote = $db->fetchOne(
            "SELECT * FROM quotes WHERE order_id = :order_id AND vehicle_owner_id = :owner_id",
            [':order_id' => $id, ':owner_id' => $currentUser['id']]
        );
    }
} else {
    // קבלת הזמנות זמינות עם סינון
    $filterCategory = $_GET['category'] ?? null;
    $filterLocation = $_GET['location'] ?? null;
    $filterDateFrom = $_GET['date_from'] ?? null;
    
    $sql = "SELECT o.*, u.first_name, u.last_name, u.phone, u.email,
                   mc.name as main_category_name, sc.name as sub_category_name,
                   COUNT(q.id) as quote_count
            FROM orders o 
            JOIN users u ON o.customer_id = u.id
            LEFT JOIN main_categories mc ON o.main_category_id = mc.id
            LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
            LEFT JOIN quotes q ON o.id = q.order_id
            WHERE o.status = 'open_for_quotes'";
    
    $params = [];
    
    if ($filterCategory) {
        $sql .= " AND o.main_category_id = :category";
        $params[':category'] = $filterCategory;
    }
    
    if ($filterLocation) {
        $sql .= " AND (o.start_location LIKE :location OR o.end_location LIKE :location)";
        $params[':location'] = "%{$filterLocation}%";
    }
    
    if ($filterDateFrom) {
        $sql .= " AND o.work_start_date >= :date_from";
        $params[':date_from'] = $filterDateFrom;
    }
    
    $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
    
    $availableOrders = $db->fetchAll($sql, $params);
    
    // קבלת קטגוריות לפילטר
    $mainCategories = $db->fetchAll("SELECT * FROM main_categories WHERE is_active = 1 ORDER BY name");
}

$myVehicles = $vehicle->getAllVehicles($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הזמנות זמינות - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .quote-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #28a745;
            margin-top: 1rem;
        }
        
        .order-card {
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .order-card:hover {
            border-color: #FF7A00;
            box-shadow: 0 4px 12px rgba(255, 122, 0, 0.1);
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .existing-quote {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .notification-badge {
            position: relative;
            display: inline-block;
        }
        
        .notification-badge::after {
            content: '🔔';
            position: absolute;
            top: -5px;
            left: -5px;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">נהגים - בעל רכב</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">לוח בקרה</a></li>
                <li><a href="vehicles.php">הרכבים שלי</a></li>
                <li><a href="orders.php" class="active">הזמנות זמינות</a></li>
                <li><a href="quotes.php">ההצעות שלי</a></li>
                <li><a href="profile.php">פרופיל</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <div class="d-flex justify-between align-center mb-3">
            <h1>הזמנות זמינות</h1>
            <?php if ($action === 'view'): ?>
                <a href="orders.php" class="btn btn-secondary">חזרה לרשימה</a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <span class="notification-badge">✅</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$isPremium): ?>
            <div class="alert alert-warning">
                <strong>💎 שים לב:</strong> אתה יכול לראות הזמנות אבל לשלוח הצעות מחיר נדרש מנוי פרימיום.
                <a href="upgrade.php" class="btn btn-warning btn-sm" style="margin-right: 1rem;">שדרג לפרימיום</a>
            </div>
        <?php endif; ?>

        <?php if (empty($myVehicles) && $isPremium): ?>
            <div class="alert alert-info">
                <strong>🚛 אין לך רכבים רשומים:</strong> כדי לשלוח הצעות מחיר תחילה עליך לרשום רכב.
                <a href="vehicles.php?action=add" class="btn btn-primary btn-sm" style="margin-right: 1rem;">רשום רכב</a>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- פילטרים -->
            <div class="filter-section">
                <h4>🔍 חיפוש וסינון הזמנות</h4>
                <form method="GET" class="row">
                    <div class="col-3">
                        <select name="category" class="form-control">
                            <option value="">כל הקטגוריות</option>
                            <?php foreach ($mainCategories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($filterCategory == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <input type="text" name="location" class="form-control" placeholder="מיקום..." 
                               value="<?php echo htmlspecialchars($filterLocation ?? ''); ?>">
                    </div>
                    <div class="col-3">
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($filterDateFrom ?? ''); ?>">
                    </div>
                    <div class="col-3">
                        <button type="submit" class="btn btn-primary">חפש</button>
                        <a href="orders.php" class="btn btn-outline">נקה</a>
                    </div>
                </form>
            </div>

            <?php if (empty($availableOrders)): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <h3>🔍 לא נמצאו הזמנות זמינות</h3>
                        <p>נסה לשנות את החיפוש או חזור מאוחר יותר</p>
                        <?php if ($filterCategory || $filterLocation || $filterDateFrom): ?>
                            <a href="orders.php" class="btn btn-primary">הצג את כל ההזמנות</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($availableOrders as $ord): ?>
                        <div class="col-6 mb-3">
                            <div class="card order-card">
                                <div class="card-header d-flex justify-between align-center">
                                    <h4>#<?php echo $ord['order_number']; ?></h4>
                                    <div>
                                        <span class="status-badge status-open">פתוח להצעות</span>
                                        <?php if ($ord['quote_count'] > 0): ?>
                                            <small style="color: #666;">(<?php echo $ord['quote_count']; ?> הצעות)</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p><strong>📝 תיאור:</strong> <?php echo htmlspecialchars(substr($ord['work_description'], 0, 100)) . (strlen($ord['work_description']) > 100 ? '...' : ''); ?></p>
                                    <p><strong>📍 מיקום:</strong> <?php echo htmlspecialchars($ord['start_location']); ?>
                                        <?php if ($ord['end_location']): ?>
                                            → <?php echo htmlspecialchars($ord['end_location']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>🗓️ תאריך:</strong> <?php echo date('d/m/Y', strtotime($ord['work_start_date'])); ?>
                                        <?php if ($ord['work_start_time']): ?>
                                            בשעה <?php echo date('H:i', strtotime($ord['work_start_time'])); ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if ($ord['main_category_name']): ?>
                                        <p><strong>🏷️ קטגוריה:</strong> <?php echo htmlspecialchars($ord['main_category_name']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($isPremium): ?>
                                        <div style="background: #e8f5e8; padding: 0.5rem; border-radius: 4px; margin: 0.5rem 0;">
                                            <p><strong>👤 לקוח:</strong> <?php echo htmlspecialchars($ord['first_name'] . ' ' . $ord['last_name']); ?></p>
                                            <p><strong>📞 טלפון:</strong> <a href="tel:<?php echo $ord['phone']; ?>"><?php echo htmlspecialchars($ord['phone']); ?></a></p>
                                        </div>
                                    <?php else: ?>
                                        <div style="background: #fff3cd; padding: 0.5rem; border-radius: 4px; margin: 0.5rem 0;">
                                            <p style="color: #856404; margin: 0;"><strong>💎 שדרג לפרימיום</strong> לראות פרטי לקוח</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                                        <a href="?action=view&id=<?php echo $ord['id']; ?>" class="btn btn-primary">צפייה מלאה</a>
                                        <?php if ($isPremium && !empty($myVehicles)): ?>
                                            <a href="?action=view&id=<?php echo $ord['id']; ?>&quick_quote=1" class="btn btn-success">💰 שלח הצעה</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'view' && isset($orderDetails)): ?>
            <!-- צפייה מפורטת בהזמנה -->
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h3>הזמנה #<?php echo $orderDetails['order_number']; ?></h3>
                    <div>
                        <span class="status-badge status-<?php echo str_replace('_', '-', $orderDetails['status']); ?>">
                            <?php 
                            $statusLabels = [
                                'open_for_quotes' => 'פתוח להצעות',
                                'in_negotiation' => 'במשא ומתן',
                                'closed' => 'סגור'
                            ];
                            echo $statusLabels[$orderDetails['status']] ?? $orderDetails['status'];
                            ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h4>📋 פרטי ההזמנה</h4>
                            <p><strong>תיאור מלא:</strong><br><?php echo nl2br(htmlspecialchars($orderDetails['work_description'])); ?></p>
                            <p><strong>📍 מיקום התחלה:</strong> <?php echo htmlspecialchars($orderDetails['start_location']); ?></p>
                            <?php if ($orderDetails['end_location']): ?>
                                <p><strong>📍 מיקום סיום:</strong> <?php echo htmlspecialchars($orderDetails['end_location']); ?></p>
                            <?php endif; ?>
                            <p><strong>🗓️ תאריך ביצוע:</strong> <?php echo date('d/m/Y', strtotime($orderDetails['work_start_date'])); ?>
                                <?php if ($orderDetails['work_start_time']): ?>
                                    בשעה <?php echo date('H:i', strtotime($orderDetails['work_start_time'])); ?>
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($orderDetails['special_requirements']): ?>
                                <p><strong>⚠️ דרישות מיוחדות:</strong><br><?php echo nl2br(htmlspecialchars($orderDetails['special_requirements'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($orderDetails['max_budget']): ?>
                                <p><strong>💰 תקציב מקסימלי:</strong> <?php echo format_price($orderDetails['max_budget']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($orderDetails['images'])): ?>
                                <h5>📷 תמונות</h5>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php foreach ($orderDetails['images'] as $img): ?>
                                        <img src="../uploads/<?php echo $img['image_path']; ?>" 
                                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; cursor: pointer;" 
                                             onclick="openImageModal(this.src)">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-4">
                            <h4>👤 פרטי לקוח</h4>
                            <?php if ($isPremium): ?>
                                <div style="background: #e8f5e8; padding: 1rem; border-radius: 8px;">
                                    <p><strong>שם:</strong> <?php echo htmlspecialchars($orderDetails['first_name'] . ' ' . $orderDetails['last_name']); ?></p>
                                    <p><strong>📞 טלפון:</strong> <a href="tel:<?php echo $orderDetails['phone']; ?>"><?php echo htmlspecialchars($orderDetails['phone']); ?></a></p>
                                    <p><strong>📧 אימייל:</strong> <a href="mailto:<?php echo $orderDetails['email']; ?>"><?php echo htmlspecialchars($orderDetails['email']); ?></a></p>
                                    <?php if ($orderDetails['customer_type'] === 'business' && $orderDetails['company_name']): ?>
                                        <p><strong>🏢 חברה:</strong> <?php echo htmlspecialchars($orderDetails['company_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center;">
                                    <p>🔒 פרטי לקוח זמינים למנויי פרימיום</p>
                                    <a href="upgrade.php" class="btn btn-warning">💎 שדרג עכשיו</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- טיפול בהצעה קיימת או חדשה -->
            <?php if ($isPremium && $orderDetails['status'] === 'open_for_quotes' && !empty($myVehicles)): ?>
                <?php if ($myExistingQuote): ?>
                    <!-- עדכון הצעה קיימת -->
                    <div class="existing-quote">
                        <h4>✏️ ההצעה שלך (עדכון)</h4>
                        <p><strong>מחיר נוכחי:</strong> <?php echo format_price($myExistingQuote['quote_amount']); ?></p>
                        <p><strong>הערות:</strong> <?php echo htmlspecialchars($myExistingQuote['quote_description'] ?? 'אין הערות'); ?></p>
                        
                        <form method="POST">
                            <input type="hidden" name="quote_id" value="<?php echo $myExistingQuote['id']; ?>">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">מחיר מעודכן (₪) *</label>
                                        <input type="number" name="quote_amount" class="form-control" required 
                                               value="<?php echo $myExistingQuote['quote_amount']; ?>" step="0.01" min="1">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">הערות נוספות</label>
                                        <textarea name="quote_description" class="form-control" rows="3"><?php echo htmlspecialchars($myExistingQuote['quote_description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_quote" class="btn btn-primary">🔄 עדכן הצעה</button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- שליחת הצעה חדשה -->
                    <div class="quote-form">
                        <h4>💰 שלח הצעת מחיר</h4>
                        <p style="color: #28a745; margin-bottom: 1rem;">
                            <span class="notification-badge">🔔</span>
                            הלקוח יקבל התראה מיידית על ההצעה שלך!
                        </p>
                        
                        <form method="POST" id="quoteForm">
                            <input type="hidden" name="order_id" value="<?php echo $orderDetails['id']; ?>">
                            
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">בחר רכב *</label>
                                        <select name="vehicle_id" class="form-control" required>
                                            <option value="">בחר רכב</option>
                                            <?php foreach ($myVehicles as $v): ?>
                                                <option value="<?php echo $v['id']; ?>">
                                                    <?php echo htmlspecialchars($v['vehicle_name'] . ' (' . $v['sub_category_name'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">מחיר (₪) *</label>
                                        <input type="number" name="quote_amount" class="form-control" required 
                                               placeholder="הכנס מחיר" step="0.01" min="1">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label class="form-label">תקף עד (אופציונלי)</label>
                                        <input type="date" name="valid_until" class="form-control" 
                                               min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">פירוט השירות והערות</label>
                                <textarea name="quote_description" class="form-control" rows="4" 
                                          placeholder="פרט על השירות: מה כלול במחיר, זמני ביצוע, הערות מיוחדות וכו'"></textarea>
                            </div>
                            
                            <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                <h5>📋 טיפים להצעה מנצחת:</h5>
                                <ul style="margin: 0.5rem 0;">
                                    <li>פרט מה כלול במחיר (דלק, סובלים, ציוד נוסף)</li>
                                    <li>ציין זמני הגעה וביצוע צפויים</li>
                                    <li>הוסף ערך מוסף (ניסיון, ביטוח, אמינות)</li>
                                    <li>היה זמין לשאלות ותיאומים</li>
                                </ul>
                            </div>
                            
                            <button type="submit" name="send_quote" class="btn btn-success btn-lg">
                                🚀 שלח הצעת מחיר (+ התראה ללקוח)
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- הצעות קיימות של אחרים -->
            <?php if (!empty($orderDetails['quotes'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>💰 הצעות מחיר קיימות (<?php echo count($orderDetails['quotes']); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>בעל רכב</th>
                                    <th>רכב</th>
                                    <th>מחיר</th>
                                    <th>תאריך הצעה</th>
                                    <th>סטטוס</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderDetails['quotes'] as $quote): ?>
                                    <tr<?php echo ($quote['vehicle_owner_id'] == $currentUser['id']) ? ' style="background: #e8f5e8;"' : ''; ?>>
                                        <td>
                                            <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?>
                                            <?php if ($quote['vehicle_owner_id'] == $currentUser['id']): ?>
                                                <span style="color: #28a745; font-weight: bold;">(זה אני!)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($quote['vehicle_name']); ?></td>
                                        <td style="font-weight: bold;"><?php echo format_price($quote['quote_amount']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($quote['created_at'])); ?></td>
                                        <td>
                                            <?php if ($quote['is_selected']): ?>
                                                <span class="status-badge status-closed">נבחר ✅</span>
                                            <?php else: ?>
                                                <span class="status-badge status-open">ממתין</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <img id="modalImage" style="max-width: 90vw; max-height: 90vh; border-radius: 8px;">
            <button onclick="closeImageModal()" style="position: absolute; top: 10px; left: 10px; background: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">×</button>
        </div>
    </div>

    <script>
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'block';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }
        
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // אם יש quick_quote בURL, גלול לטופס ההצעה
        if (window.location.search.includes('quick_quote=1')) {
            setTimeout(() => {
                const quoteForm = document.querySelector('.quote-form');
                if (quoteForm) {
                    quoteForm.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        }

        // אימות טופס הצעה
        document.getElementById('quoteForm')?.addEventListener('submit', function(e) {
            const amount = document.querySelector('input[name="quote_amount"]').value;
            const vehicle = document.querySelector('select[name="vehicle_id"]').value;
            
            if (!vehicle) {
                alert('יש לבחור רכב');
                e.preventDefault();
                return;
            }
            
            if (!amount || amount <= 0) {
                alert('יש להכניס מחיר תקין');
                e.preventDefault();
                return;
            }
            
            if (!confirm(`האם אתה בטוח שברצונך לשלוח הצעת מחיר של ${amount}₪? הלקוח יקבל התראה מיידית!`)) {
                e.preventDefault();
            }
        });
    </script>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>