<?php
// vehicle-owner/quotes.php - ניהול הצעות מחיר
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$db = new Database();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$message = '';
$error = '';

// טיפול בעדכון הצעה
if ($_POST && $action === 'update') {
    $db->update('quotes', [
        'quote_amount' => $_POST['quote_amount'],
        'quote_description' => $_POST['quote_description']
    ], 'id = :id AND vehicle_owner_id = :owner_id', [
        ':id' => $_POST['quote_id'],
        ':owner_id' => $currentUser['id']
    ]);
    $message = 'הצעת המחיר עודכנה בהצלחה';
}

// קבלת הצעות המחיר שלי
$myQuotes = $db->fetchAll(
    "SELECT q.*, o.order_number, o.work_description, o.work_start_date, o.status as order_status,
            v.vehicle_name, u.first_name, u.last_name, u.phone
     FROM quotes q 
     JOIN orders o ON q.order_id = o.id
     JOIN vehicles v ON q.vehicle_id = v.id
     JOIN users u ON o.customer_id = u.id
     WHERE q.vehicle_owner_id = :owner_id
     ORDER BY q.created_at DESC",
    [':owner_id' => $currentUser['id']]
);

// סטטיסטיקות
$stats = [
    'total' => count($myQuotes),
    'selected' => count(array_filter($myQuotes, fn($q) => $q['is_selected'])),
    'pending' => count(array_filter($myQuotes, fn($q) => !$q['is_selected'] && $q['order_status'] === 'open_for_quotes')),
    'completed' => count(array_filter($myQuotes, fn($q) => $q['order_status'] === 'closed'))
];

if ($action === 'edit' && $id) {
    $editQuote = $db->fetchOne(
        "SELECT q.*, o.order_number, o.work_description 
         FROM quotes q 
         JOIN orders o ON q.order_id = o.id 
         WHERE q.id = :id AND q.vehicle_owner_id = :owner_id",
        [':id' => $id, ':owner_id' => $currentUser['id']]
    );
    
    if (!$editQuote) {
        redirect('quotes.php');
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ההצעות שלי - <?php echo SITE_NAME; ?></title>
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
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <h1>ההצעות שלי</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- סטטיסטיקות -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">סה"כ הצעות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['selected']; ?></div>
                    <div class="stat-label">הצעות שנבחרו</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">ממתינות לתשובה</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">הושלמו</div>
                </div>
            </div>

            <?php if (empty($myQuotes)): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <h3>עדיין לא שלחת הצעות מחיר</h3>
                        <p>עבור להזמנות הזמינות כדי לשלוח הצעות מחיר</p>
                        <a href="orders.php" class="btn btn-primary">צפה בהזמנות זמינות</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>הזמנה</th>
                                    <th>לקוח</th>
                                    <th>רכב</th>
                                    <th>המחיר שלי</th>
                                    <th>תאריך הצעה</th>
                                    <th>סטטוס</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myQuotes as $quote): ?>
                                    <tr>
                                        <td>#<?php echo $quote['order_number']; ?></td>
                                        <td><?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($quote['vehicle_name']); ?></td>
                                        <td style="font-weight: bold; color: var(--primary-color);">
                                            <?php echo format_price($quote['quote_amount']); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($quote['created_at'])); ?></td>
                                        <td>
                                            <?php if ($quote['is_selected']): ?>
                                                <span class="status-badge status-closed">נבחרה ✓</span>
                                            <?php elseif ($quote['order_status'] === 'closed'): ?>
                                                <span class="status-badge status-open">לא נבחרה</span>
                                            <?php else: ?>
                                                <span class="status-badge status-negotiation">ממתינה</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $quote['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">צפייה</a>
                                            <?php if ($quote['order_status'] === 'open_for_quotes'): ?>
                                                <a href="?action=edit&id=<?php echo $quote['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">עריכה</a>
                                            <?php endif; ?>
                                            <?php if ($quote['is_selected']): ?>
                                                <a href="tel:<?php echo $quote['phone']; ?>" class="btn btn-success" style="padding: 0.25rem 0.5rem;">📞 צור קשר</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'edit' && isset($editQuote)): ?>
            <div class="card">
                <div class="card-header">
                    <h3>עריכת הצעת מחיר - הזמנה #<?php echo $editQuote['order_number']; ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h4>פרטי ההזמנה</h4>
                            <p><?php echo htmlspecialchars($editQuote['work_description']); ?></p>
                        </div>
                        <div class="col-6">
                            <form method="POST" action="?action=update">
                                <input type="hidden" name="quote_id" value="<?php echo $editQuote['id']; ?>">
                                
                                <div class="form-group">
                                    <label class="form-label">מחיר חדש (₪) *</label>
                                    <input type="number" name="quote_amount" class="form-control" required 
                                           value="<?php echo $editQuote['quote_amount']; ?>" step="0.01">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">הערות</label>
                                    <textarea name="quote_description" class="form-control" rows="3"><?php echo htmlspecialchars($editQuote['quote_description']); ?></textarea>
                                </div>
                                
                                <div style="display: flex; gap: 1rem;">
                                    <button type="submit" class="btn btn-primary">עדכן הצעה</button>
                                    <a href="quotes.php" class="btn btn-secondary">ביטול</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'view' && $id): ?>
            <?php 
            $viewQuote = $db->fetchOne(
                "SELECT q.*, o.order_number, o.work_description, o.start_location, o.work_start_date, o.status as order_status,
                        v.vehicle_name, u.first_name, u.last_name, u.phone, u.email
                 FROM quotes q 
                 JOIN orders o ON q.order_id = o.id
                 JOIN vehicles v ON q.vehicle_id = v.id
                 JOIN users u ON o.customer_id = u.id
                 WHERE q.id = :id AND q.vehicle_owner_id = :owner_id",
                [':id' => $id, ':owner_id' => $currentUser['id']]
            );
            
            if ($viewQuote):
            ?>
                <div class="card">
                    <div class="card-header d-flex justify-between align-center">
                        <h3>הצעת מחיר - הזמנה #<?php echo $viewQuote['order_number']; ?></h3>
                        <a href="quotes.php" class="btn btn-secondary">חזרה</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h4>פרטי ההזמנה</h4>
                                <p><strong>תיאור:</strong> <?php echo htmlspecialchars($viewQuote['work_description']); ?></p>
                                <p><strong>מיקום:</strong> <?php echo htmlspecialchars($viewQuote['start_location']); ?></p>
                                <p><strong>תאריך:</strong> <?php echo date('d/m/Y', strtotime($viewQuote['work_start_date'])); ?></p>
                                
                                <h4>פרטי הלקוח</h4>
                                <p><strong>שם:</strong> <?php echo htmlspecialchars($viewQuote['first_name'] . ' ' . $viewQuote['last_name']); ?></p>
                                <?php if ($viewQuote['is_selected']): ?>
                                    <p><strong>טלפון:</strong> <a href="tel:<?php echo $viewQuote['phone']; ?>"><?php echo htmlspecialchars($viewQuote['phone']); ?></a></p>
                                    <p><strong>אימייל:</strong> <a href="mailto:<?php echo $viewQuote['email']; ?>"><?php echo htmlspecialchars($viewQuote['email']); ?></a></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-6">
                                <h4>פרטי ההצעה שלי</h4>
                                <p><strong>רכב:</strong> <?php echo htmlspecialchars($viewQuote['vehicle_name']); ?></p>
                                <p><strong>המחיר שלי:</strong> <span style="font-size: 1.5rem; color: var(--primary-color); font-weight: bold;"><?php echo format_price($viewQuote['quote_amount']); ?></span></p>
                                
                                <?php if ($viewQuote['quote_description']): ?>
                                    <p><strong>הערות:</strong> <?php echo htmlspecialchars($viewQuote['quote_description']); ?></p>
                                <?php endif; ?>
                                
                                <p><strong>תאריך שליחה:</strong> <?php echo date('d/m/Y H:i', strtotime($viewQuote['created_at'])); ?></p>
                                
                                <div style="margin-top: 2rem;">
                                    <?php if ($viewQuote['is_selected']): ?>
                                        <div class="alert alert-success text-center">
                                            <h4>🎉 ההצעה שלך נבחרה!</h4>
                                            <p>צור קשר עם הלקוח לתיאום העבודה</p>
                                            <a href="tel:<?php echo $viewQuote['phone']; ?>" class="btn btn-success">📞 התקשר ללקוח</a>
                                        </div>
                                    <?php elseif ($viewQuote['order_status'] === 'closed'): ?>
                                        <div class="alert alert-warning text-center">
                                            <h4>ההזמנה נסגרה</h4>
                                            <p>הלקוח בחר הצעה אחרת</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning text-center">
                                            <h4>ממתין לתשובת הלקוח</h4>
                                            <a href="?action=edit&id=<?php echo $viewQuote['id']; ?>" class="btn btn-outline">עדכן הצעה</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>