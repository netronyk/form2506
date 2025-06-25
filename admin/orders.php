<?php
// admin/orders.php - ניהול הזמנות מנהל עם התראות ווטסאפ
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Notification.php';

$auth = new Auth();
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$order = new Order();
$notification = new Notification();
$db = new Database();

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'list';
$status = $_GET['status'] ?? null;

$message = '';
$error = '';

// טיפול בפעולות
if ($_POST) {
    switch ($action) {
        case 'update_status':
            $orderId = $_POST['order_id'];
            $newStatus = $_POST['status'];
            $updateMessage = $_POST['update_message'] ?? null;
            
            $result = $order->updateOrderStatus($orderId, $newStatus);
            
            if ($result['success']) {
                // קבלת פרטי ההזמנה והלקוח
                $orderData = $order->getOrderById($orderId);
                
                if ($orderData) {
                    // שליחת התראה ללקוח על עדכון הסטטוס
                    $customMessage = $updateMessage ?: "סטטוס הזמנה #{$orderData['order_number']} עודכן ל: " . 
                        ($newStatus === 'open_for_quotes' ? 'פתוח להצעות' : 
                         ($newStatus === 'in_negotiation' ? 'במשא ומתן' : 'סגור'));
                    
                    $notification->notifyOrderUpdatedByAdmin(
                        $orderId, 
                        $orderData['customer_id'], 
                        $_SESSION['user_id'],
                        $customMessage
                    );
                }
                
                $message = 'סטטוס ההזמנה עודכן והלקוח קיבל התראה';
            } else {
                $error = 'שגיאה בעדכון סטטוס ההזמנה';
            }
            break;
            
        case 'update_order':
            $orderId = $_POST['order_id'];
            $updateMessage = $_POST['update_message'];
            
            // עדכון ההזמנה (כאן תוכל להוסיף לוגיקה לעדכון פרטי ההזמנה)
            $orderData = $order->getOrderById($orderId);
            
            if ($orderData) {
                // שליחת התראה ללקוח על עדכון כללי
                $notification->notifyOrderUpdatedByAdmin(
                    $orderId, 
                    $orderData['customer_id'], 
                    $_SESSION['user_id'],
                    $updateMessage
                );
                
                $message = 'ההזמנה עודכנה והלקוח קיבל התראה';
            } else {
                $error = 'הזמנה לא נמצאה';
            }
            break;
            
        case 'resolve_dispute':
            $db->update('order_disputes', [
                'status' => 'resolved',
                'admin_response' => $_POST['admin_response'],
                'resolved_at' => date('Y-m-d H:i:s')
            ], 'id = :id', [':id' => $_POST['dispute_id']]);
            $message = 'התלונה טופלה';
            break;
            
        case 'send_message':
            $orderId = $_POST['order_id'];
            $messageText = $_POST['message'];
            $orderData = $order->getOrderById($orderId);
            
            if ($orderData) {
                // שליחת הודעה ישירה ללקוח
                $notification->notifySystem(
                    $orderData['customer_id'],
                    'הודעה ממנהל המערכת',
                    $messageText
                );
                
                $message = 'ההודעה נשלחה ללקוח';
            }
            break;
    }
}

if ($action === 'view' && $id) {
    $orderDetails = $order->getOrderById($id);
    if (!$orderDetails) {
        redirect('orders.php');
    }
} else {
    $orders = $order->getAllOrders(null, $status);
    
    // יצירת סטטיסטיקות ידנית
    $stats = [
        'total' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
        'open' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'open_for_quotes'")['count'],
        'negotiation' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'in_negotiation'")['count'],
        'closed' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'closed'")['count']
    ];
}

// תלונות פתוחות
$disputes = $db->fetchAll(
    "SELECT od.*, o.order_number, u.first_name, u.last_name 
     FROM order_disputes od 
     JOIN orders o ON od.order_id = o.id 
     JOIN users u ON od.reporter_id = u.id 
     WHERE od.status = 'open' 
     ORDER BY od.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול הזמנות - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        .whatsapp-icon {
            color: #25D366;
            font-size: 1.2rem;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">נהגים - מנהל</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">לוח בקרה</a></li>
                <li><a href="users.php">משתמשים</a></li>
                <li><a href="categories.php">קטגוריות</a></li>
                <li><a href="orders.php">הזמנות</a></li>
                <li><a href="subscriptions.php">מנויים</a></li>
                <li><a href="whatsapp-settings.php">הגדרות ווטסאפ</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <h1>ניהול הזמנות</h1>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <span class="whatsapp-icon">💚</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- סטטיסטיקות -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">סה"כ הזמנות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['open']; ?></div>
                    <div class="stat-label">פתוח להצעות</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['negotiation']; ?></div>
                    <div class="stat-label">במשא ומתן</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['closed']; ?></div>
                    <div class="stat-label">סגור</div>
                </div>
            </div>

            <!-- פילטרים -->
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                        <a href="?" class="btn <?php echo !$status ? 'btn-primary' : 'btn-outline'; ?>">הכל</a>
                        <a href="?status=open_for_quotes" class="btn <?php echo $status === 'open_for_quotes' ? 'btn-primary' : 'btn-outline'; ?>">פתוח להצעות</a>
                        <a href="?status=in_negotiation" class="btn <?php echo $status === 'in_negotiation' ? 'btn-primary' : 'btn-outline'; ?>">במשא ומתן</a>
                        <a href="?status=closed" class="btn <?php echo $status === 'closed' ? 'btn-primary' : 'btn-outline'; ?>">סגור</a>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>מספר הזמנה</th>
                                <th>לקוח</th>
                                <th>תיאור</th>
                                <th>תאריך</th>
                                <th>סטטוס</th>
                                <th>הצעות</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td>#<?php echo $ord['order_number']; ?></td>
                                    <td><?php echo htmlspecialchars($ord['first_name'] . ' ' . $ord['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($ord['work_description'], 0, 50)) . '...'; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($ord['work_start_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace('_', '-', $ord['status']); ?>">
                                            <?php 
                                            $statusLabels = [
                                                'open_for_quotes' => 'פתוח להצעות',
                                                'in_negotiation' => 'במשא ומתן',
                                                'closed' => 'סגור'
                                            ];
                                            echo $statusLabels[$ord['status']];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $ord['quote_count'] ?? 0; ?></td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $ord['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">צפייה</a>
                                        <button onclick="showMessageModal(<?php echo $ord['id']; ?>, '<?php echo htmlspecialchars($ord['first_name'] . ' ' . $ord['last_name']); ?>')" 
                                                class="btn btn-success" style="padding: 0.25rem 0.5rem; margin-right: 0.25rem;">
                                            <span class="whatsapp-icon">💚</span> הודעה
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- תלונות פתוחות -->
            <?php if (!empty($disputes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>תלונות פתוחות</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($disputes as $dispute): ?>
                            <div style="border-bottom: 1px solid #eee; padding: 1rem 0;">
                                <div class="d-flex justify-between align-center">
                                    <div>
                                        <h5>הזמנה #<?php echo $dispute['order_number']; ?></h5>
                                        <p><strong>מדווח:</strong> <?php echo htmlspecialchars($dispute['first_name'] . ' ' . $dispute['last_name']); ?> (<?php echo $dispute['reporter_type']; ?>)</p>
                                        <p><?php echo htmlspecialchars($dispute['dispute_description']); ?></p>
                                    </div>
                                    <div>
                                        <button onclick="showResolveModal(<?php echo $dispute['id']; ?>)" class="btn btn-primary">טפל בתלונה</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'view' && isset($orderDetails)): ?>
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h3>הזמנה #<?php echo $orderDetails['order_number']; ?></h3>
                    <div>
                        <button onclick="showMessageModal(<?php echo $orderDetails['id']; ?>, '<?php echo htmlspecialchars($orderDetails['first_name'] . ' ' . $orderDetails['last_name']); ?>')" 
                                class="btn btn-success">
                            <span class="whatsapp-icon">💚</span> שלח הודעה
                        </button>
                        <a href="orders.php" class="btn btn-secondary">חזרה</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h4>פרטי ההזמנה</h4>
                            <p><strong>תיאור:</strong> <?php echo htmlspecialchars($orderDetails['work_description']); ?></p>
                            <p><strong>מיקום התחלה:</strong> <?php echo htmlspecialchars($orderDetails['start_location']); ?></p>
                            <?php if ($orderDetails['end_location']): ?>
                                <p><strong>מיקום סיום:</strong> <?php echo htmlspecialchars($orderDetails['end_location']); ?></p>
                            <?php endif; ?>
                            <p><strong>תאריך:</strong> <?php echo date('d/m/Y', strtotime($orderDetails['work_start_date'])); ?></p>
                            
                            <h4>פרטי לקוח</h4>
                            <p><strong>שם:</strong> <?php echo htmlspecialchars($orderDetails['first_name'] . ' ' . $orderDetails['last_name']); ?></p>
                            <p><strong>טלפון:</strong> <?php echo htmlspecialchars($orderDetails['phone'] ?? ''); ?></p>
                            <p><strong>אימייל:</strong> <?php echo htmlspecialchars($orderDetails['email']); ?></p>
                        </div>
                        
                        <div class="col-4">
                            <h4>ניהול הזמנה</h4>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $orderDetails['id']; ?>">
                                <div class="form-group">
                                    <label class="form-label">סטטוס</label>
                                    <select name="status" class="form-control">
                                        <option value="open_for_quotes" <?php echo $orderDetails['status'] === 'open_for_quotes' ? 'selected' : ''; ?>>פתוח להצעות</option>
                                        <option value="in_negotiation" <?php echo $orderDetails['status'] === 'in_negotiation' ? 'selected' : ''; ?>>במשא ומתן</option>
                                        <option value="closed" <?php echo $orderDetails['status'] === 'closed' ? 'selected' : ''; ?>>סגור</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">הודעה ללקוח (אופציונלי)</label>
                                    <textarea name="update_message" class="form-control" rows="3" 
                                              placeholder="הכנס הודעה מותאמת אישית ללקוח על העדכון"></textarea>
                                    <small class="text-muted">
                                        <span class="whatsapp-icon">💚</span>
                                        ההודעה תישלח ללקוח בווטסאפ ובאימייל
                                    </small>
                                </div>
                                <button type="submit" name="action" value="update_status" class="btn btn-primary">
                                    עדכן ושלח התראה
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- הצעות מחיר -->
            <div class="card">
                <div class="card-header">
                    <h3>הצעות מחיר (<?php echo count($orderDetails['quotes'] ?? []); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($orderDetails['quotes'])): ?>
                        <p>אין הצעות מחיר</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>בעל רכב</th>
                                    <th>רכב</th>
                                    <th>מחיר</th>
                                    <th>תאריך</th>
                                    <th>סטטוס</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderDetails['quotes'] as $quote): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($quote['vehicle_name']); ?></td>
                                        <td><?php echo number_format($quote['quote_amount'], 2); ?> ₪</td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($quote['created_at'])); ?></td>
                                        <td>
                                            <?php if ($quote['is_selected']): ?>
                                                <span class="status-badge status-closed">נבחר</span>
                                            <?php else: ?>
                                                <span class="status-badge status-open">ממתין</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Send Message Modal -->
    <div id="messageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>💚 שליחת הודעה ללקוח</h3>
            <div id="messageCustomerName" style="background: #f0f8ff; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem;"></div>
            <form method="POST">
                <input type="hidden" name="order_id" id="messageOrderId">
                <div class="form-group">
                    <label class="form-label">הודעה</label>
                    <textarea name="message" class="form-control" rows="5" required 
                              placeholder="כתוב הודעה אישית ללקוח..."></textarea>
                    <small class="text-muted">ההודעה תישלח ללקוח בווטסאפ, SMS ואימייל לפי הגדרותיו</small>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="action" value="send_message" class="btn btn-success">
                        <span class="whatsapp-icon">💚</span> שלח הודעה
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeMessageModal()">ביטול</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resolve Dispute Modal -->
    <div id="resolveModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>טיפול בתלונה</h3>
            <form method="POST">
                <input type="hidden" name="dispute_id" id="disputeId">
                <div class="form-group">
                    <label class="form-label">תגובת מנהל</label>
                    <textarea name="admin_response" class="form-control" rows="4" required></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="action" value="resolve_dispute" class="btn btn-primary">סגור תלונה</button>
                    <button type="button" class="btn btn-secondary" onclick="closeResolveModal()">ביטול</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showMessageModal(orderId, customerName) {
            document.getElementById('messageOrderId').value = orderId;
            document.getElementById('messageCustomerName').innerHTML = '<strong>לקוח:</strong> ' + customerName;
            document.getElementById('messageModal').style.display = 'block';
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        function showResolveModal(disputeId) {
            document.getElementById('disputeId').value = disputeId;
            document.getElementById('resolveModal').style.display = 'block';
        }
        
        function closeResolveModal() {
            document.getElementById('resolveModal').style.display = 'none';
        }
        
        // Close modals on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'messageModal') closeMessageModal();
                    if (this.id === 'resolveModal') closeResolveModal();
                }
            });
        });
    </script>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>