<?php
// customer/dashboard.php - פנל לקוח עם התראות - מעודכן
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Notification.php';

$auth = new Auth();
if (!$auth->checkPermission('customer')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$db = new Database();
$notification = new Notification();

// טיפול בסימון התראה כנקראה
if (isset($_GET['mark_read']) && isset($_GET['notification_id'])) {
    $notification->markAsRead($_GET['notification_id'], $currentUser['id']);
    redirect('dashboard.php');
}

// טיפול בסימון כל ההתראות כנקראו
if (isset($_POST['mark_all_read'])) {
    $notification->markAllAsRead($currentUser['id']);
    redirect('dashboard.php');
}

// סטטיסטיקות
$myOrders = $db->fetchAll(
    "SELECT * FROM orders WHERE customer_id = :id ORDER BY created_at DESC",
    [':id' => $currentUser['id']]
);

$activeOrders = array_filter($myOrders, function($order) {
    return $order['status'] !== 'closed';
});

$totalQuotes = $db->fetchOne(
    "SELECT COUNT(*) as count FROM quotes q 
     JOIN orders o ON q.order_id = o.id 
     WHERE o.customer_id = :id",
    [':id' => $currentUser['id']]
)['count'];

// הזמנות אחרונות עם הצעות
$ordersWithQuotes = $db->fetchAll(
    "SELECT o.*, COUNT(q.id) as quote_count
     FROM orders o 
     LEFT JOIN quotes q ON o.id = q.order_id
     WHERE o.customer_id = :id
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 5",
    [':id' => $currentUser['id']]
);

// התראות
$notifications = $notification->getUserNotifications($currentUser['id'], 10);
$unreadCount = $notification->getUnreadCount($currentUser['id']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה לקוח - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notification-item {
            border-bottom: 1px solid #eee;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-unread {
            background-color: #e3f2fd;
            border-left: 4px solid var(--primary-color);
        }
        .notification-read {
            opacity: 0.7;
        }
        .notification-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.5rem;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
           .quotes-cell {
        text-align: center;
        padding: 0.5rem;
    }
    
    .quotes-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .quotes-indicator.has-quotes {
        background: linear-gradient(135deg, #4caf50, #66bb6a);
        color: white;
        box-shadow: 0 2px 6px rgba(76, 175, 80, 0.3);
    }
    
    .quotes-indicator.no-quotes {
        background: #f8f9fa;
        color: #6c757d;
        border: 1px solid #e9ecef;
    }
    
    .quotes-indicator:hover {
        transform: translateY(-1px);
    }
    
    .quotes-indicator.has-quotes:hover {
        box-shadow: 0 4px 10px rgba(76, 175, 80, 0.4);
    }
    
    .quotes-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .quotes-dot.active {
        background: #fff;
        opacity: 0.9;
    }
    
    .quotes-dot.waiting {
        background: #ffc107;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    <!-- CSS מתקדם לטבלת ההזמנות -->
<style>
    /* שיפור עיצוב הטבלה הכללי */
    .orders-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .orders-table thead {
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: white;
    }
    
    .orders-table th {
        padding: 1rem 0.8rem;
        font-weight: 600;
        text-align: center;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        border: none;
        position: relative;
    }
    
    .orders-table th:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 0;
        top: 25%;
        height: 50%;
        width: 1px;
        background: rgba(255,255,255,0.2);
    }
    
    .orders-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #f0f4f8;
    }
    
    .orders-table tbody tr:hover {
        background: linear-gradient(90deg, #f8fafc, #f1f5f9);
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .orders-table td {
        padding: 1rem 0.8rem;
        text-align: center;
        vertical-align: middle;
        border: none;
        transition: all 0.2s ease;
    }
    
    /* עיצוב מספר ההזמנה */
    .order-number {
        font-weight: 700;
        color: #2563eb;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        display: inline-block;
        min-width: 80px;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    
    /* עיצוב התיאור */
    .order-description {
        max-width: 200px;
        font-size: 0.9rem;
        color: #374151;
        line-height: 1.4;
        text-align: right;
        padding-right: 1rem;
    }
    
    /* עיצוב הסטטוס המשופר */
    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        min-width: 120px;
        justify-content: center;
    }
    
    .status-indicator.open-for-quotes {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }
    
    .status-indicator.in-negotiation {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    .status-indicator.closed {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }
    
    .status-indicator:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(255,255,255,0.9);
        animation: status-pulse 2s infinite;
    }
    
    @keyframes status-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    /* עיצוב ההצעות המשופר */
    .quotes-cell {
        text-align: center;
        padding: 0.5rem;
        position: relative;
    }
    
    .quotes-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        min-width: 100px;
        justify-content: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .quotes-indicator.has-quotes {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 3px 6px rgba(5, 150, 105, 0.3);
    }
    
    .quotes-indicator.has-quotes::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        animation: shimmer 3s infinite;
    }
    
    @keyframes shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    .quotes-indicator.no-quotes {
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        color: #6b7280;
        border: 2px dashed #d1d5db;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .quotes-indicator:hover {
        transform: translateY(-2px) scale(1.02);
    }
    
    .quotes-indicator.has-quotes:hover {
        box-shadow: 0 6px 12px rgba(5, 150, 105, 0.4);
    }
    
    .quotes-indicator.no-quotes:hover {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border-color: #f59e0b;
    }
    
    .quotes-icon {
        font-size: 1rem;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
    }
    
    .quotes-count {
        font-weight: 800;
        font-size: 0.9rem;
    }
    
    /* עיצוב התאריך */
    .date-cell {
        color: #6b7280;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    /* עיצוב כפתורי הפעולות */
    .action-btn {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        text-decoration: none;
    }
    
    .action-btn::before {
        content: '👁️';
        font-size: 0.7rem;
    }
    
    /* אנימציה לטעינה */
    .orders-table tbody tr {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    .orders-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
    .orders-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
    .orders-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
    .orders-table tbody tr:nth-child(4) { animation-delay: 0.4s; }
    .orders-table tbody tr:nth-child(5) { animation-delay: 0.5s; }
    
    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Tooltip */
    .quotes-indicator[data-tooltip] {
        position: relative;
    }
    
    .quotes-indicator[data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        background: #1f2937;
        color: white;
        padding: 0.5rem 0.8rem;
        border-radius: 6px;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 100;
        opacity: 1;
        animation: tooltipFade 0.2s ease;
    }
    
    @keyframes tooltipFade {
        from { opacity: 0; transform: translateX(-50%) translateY(5px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
     @media (max-width: 1200px) {
        .row {
            flex-direction: column !important;
        }
        
        .row > div:first-child {
            margin-bottom: 1rem;
        }
    }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">נהגים - לקוח</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">לוח בקרה <?php if($unreadCount > 0): ?><span class="notification-badge"><?php echo $unreadCount; ?></span><?php endif; ?></a></li>
                <li><a href="new-order.php">הזמנה חדשה</a></li>
                <li><a href="orders.php">ההזמנות שלי</a></li>
                <li><a href="profile.php">פרופיל</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <!-- Welcome -->
        <div class="card">
            <div class="card-body">
                <h1>שלום <?php echo htmlspecialchars($currentUser['first_name']); ?></h1>
                <p>ברוך הבא לפנל הלקוח - כאן תוכל לנהל את ההזמנות שלך</p>
            </div>
        </div>

        <!-- התראות -->
        <?php if (!empty($notifications)): ?>
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>התראות <?php if($unreadCount > 0): ?><span class="notification-badge"><?php echo $unreadCount; ?></span><?php endif; ?></h3>
                <?php if($unreadCount > 0): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn btn-outline" style="padding: 0.5rem 1rem;">סמן הכל כנקרא</button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'notification-read' : 'notification-unread'; ?>">
                        <div class="d-flex justify-between align-center">
                            <div style="flex: 1;">
                                <h5 style="margin-bottom: 0.5rem; font-size: 1rem;">
                                    <?php echo htmlspecialchars($notif['title']); ?>
                                    <?php if (!$notif['is_read']): ?><span style="color: var(--primary-color); font-weight: bold;"> (חדש)</span><?php endif; ?>
                                </h5>
                                <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </p>
                                <small style="color: var(--dark-gray);">
                                    <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                    <?php if ($notif['order_number']): ?>
                                        - הזמנה #<?php echo $notif['order_number']; ?>
                                    <?php endif; ?>
                                    <?php if ($notif['admin_name']): ?>
                                        - על ידי <?php echo htmlspecialchars($notif['admin_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div style="margin-right: 1rem;">
                                <?php if ($notif['related_order_id']): ?>
                                    <a href="orders.php?action=view&id=<?php echo $notif['related_order_id']; ?><?php echo !$notif['is_read'] ? '&mark_read=1&notification_id=' . $notif['id'] : ''; ?>" 
                                       class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                        צפה בהזמנה
                                    </a>
                                <?php elseif (!$notif['is_read']): ?>
                                    <a href="?mark_read=1&notification_id=<?php echo $notif['id']; ?>" 
                                       class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                        סמן כנקרא
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($myOrders); ?></div>
                <div class="stat-label">סה"כ הזמנות</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($activeOrders); ?></div>
                <div class="stat-label">הזמנות פעילות</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalQuotes; ?></div>
                <div class="stat-label">הצעות שקיבלתי</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($myOrders) - count($activeOrders); ?></div>
                <div class="stat-label">הזמנות שהושלמו</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3>פעולות מהירות</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <a href="new-order.php" class="btn btn-primary" style="width: 100%;">
                            הזמנה חדשה
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="orders.php" class="btn btn-secondary" style="width: 100%;">
                            ההזמנות שלי
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="quotes.php" class="btn btn-outline" style="width: 100%;">
                            הצעות שקיבלתי
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="profile.php" class="btn btn-outline" style="width: 100%;">
                            עדכון פרופיל
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" style="display: flex; gap: 1rem; align-items: flex-start;">
    <!-- Recent Orders -->
    <div style="flex: 2; min-width: 0;">
        <div class="card">
            <div class="card-header d-flex justify-between align-center">
                <h3>ההזמנות האחרונות</h3>
                <a href="orders.php" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                    צפייה בכל ההזמנות
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($ordersWithQuotes)): ?>
                    <div class="text-center">
                        <p>עדיין לא יצרת הזמנות</p>
                        <a href="new-order.php" class="btn btn-primary">צור הזמנה ראשונה</a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.06); font-size: 0.9rem;">
                            <thead style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white;">
                                <tr>
                                    <th style="padding: 0.8rem 0.6rem; font-weight: 600; text-align: center; font-size: 0.85rem; border: none;">מספר</th>
                                    <th style="padding: 0.8rem 0.6rem; font-weight: 600; text-align: center; font-size: 0.85rem; border: none;">תיאור</th>
                                    <th style="padding: 0.8rem 0.6rem; font-weight: 600; text-align: center; font-size: 0.85rem; border: none;">סטטוס</th>
                                    <th style="padding: 0.8rem 0.6rem; font-weight: 600; text-align: center; font-size: 0.85rem; border: none;">הצעות</th>
                                    <th style="padding: 0.8rem 0.6rem; font-weight: 600; text-align: center; font-size: 0.85rem; border: none;">תאריך</th>
                                    <th style="padding: 0.8rem 0.6rem; font-weight: 600; text-align: center; font-size: 0.85rem; border: none;">פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordersWithQuotes as $order): ?>
                                    <tr style="border-bottom: 1px solid #f0f4f8; transition: background-color 0.2s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                        <td style="padding: 0.8rem 0.6rem; text-align: center; border: none;">
                                            <span style="font-weight: 700; color: #2563eb; background: #dbeafe; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.8rem; display: inline-block; min-width: 60px;">
                                                #<?php echo $order['order_number']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.8rem 0.6rem; text-align: right; border: none; max-width: 150px;">
                                            <div style="font-size: 0.85rem; color: #374151; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($order['work_description']); ?>">
                                                <?php echo htmlspecialchars(substr($order['work_description'], 0, 35)) . '...'; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.8rem 0.6rem; text-align: center; border: none;">
                                            <?php
                                            $statusStyles = [
                                                'open_for_quotes' => 'background: #dcfce7; color: #166534;',
                                                'in_negotiation' => 'background: #fef3c7; color: #92400e;',
                                                'closed' => 'background: #f3f4f6; color: #374151;'
                                            ];
                                            $statusLabels = [
                                                'open_for_quotes' => 'פתוח',
                                                'in_negotiation' => 'משא ומתן',
                                                'closed' => 'הושלם'
                                            ];
                                            ?>
                                            <span style="<?php echo $statusStyles[$order['status']]; ?> padding: 0.3rem 0.7rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; display: inline-block; min-width: 70px;">
                                                <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.8rem 0.6rem; text-align: center; border: none;">
                                            <?php if ($order['quote_count'] > 0): ?>
                                                <span style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; padding: 0.3rem 0.7rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; min-width: 70px; justify-content: center; box-shadow: 0 1px 3px rgba(34, 197, 94, 0.2); transition: transform 0.2s ease;" 
                                                      onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'"
                                                      title="קיבלת <?php echo $order['quote_count']; ?> הצעות מחיר">
                                                    <span style="font-size: 0.9rem;">✅</span>
                                                    <?php echo $order['quote_count']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #fef3c7; color: #92400e; border: 1px dashed #fbbf24; padding: 0.3rem 0.7rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; min-width: 70px; justify-content: center; transition: transform 0.2s ease;" 
                                                      onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'"
                                                      title="ממתין להצעות מחיר">
                                                    <span style="font-size: 0.9rem;">⏳</span>
                                                    ממתין
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.8rem 0.6rem; text-align: center; border: none; color: #6b7280; font-size: 0.8rem; font-weight: 500;">
                                            <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td style="padding: 0.8rem 0.6rem; text-align: center; border: none;">
                                            <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                               style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.3rem 0.7rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.2);"
                                               onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 6px rgba(59, 130, 246, 0.3)'"
                                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 3px rgba(59, 130, 246, 0.2)'">
                                                צפייה
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Help & Tips -->
    <div style="flex: 1; min-width: 300px;">
        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-header">
                <h3>עזרה וטיפים</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 1rem;">
                    <h5>איך זה עובד?</h5>
                    <ol style="font-size: 0.9rem;">
                        <li>צור הזמנה חדשה</li>
                        <li>חכה להצעות מחיר מבעלי רכב</li>
                        <li>בחר את ההצעה הטובה ביותר</li>
                        <li>בצע את העבודה</li>
                        <li>דרג את בעל הרכב</li>
                    </ol>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <h5>טיפים לקבלת הצעות טובות:</h5>
                    <ul style="font-size: 0.9rem;">
                        <li>כתוב תיאור מפורט של העבודה</li>
                        <li>הוסף תמונות של האזור</li>
                        <li>ציין את התאריכים בבירור</li>
                        <li>הוסף מידע על גישה וחניה</li>
                    </ul>
                </div>

                <a href="new-order.php" class="btn btn-primary" style="width: 100%;">
                    צור הזמנה חדשה
                </a>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="card">
            <div class="card-header">
                <h3>צריך עזרה?</h3>
            </div>
            <div class="card-body text-center">
                <p>צוות התמיכה שלנו כאן לעזור</p>
                <div style="margin: 1rem 0;">
                    <a href="tel:03-1234567" class="btn btn-outline" style="width: 100%; margin-bottom: 0.5rem;">
                        📞 03-1234567
                    </a>
                    <a href="mailto:<?php echo SITE_EMAIL; ?>" class="btn btn-outline" style="width: 100%;">
                        ✉️ שלח אימייל
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>