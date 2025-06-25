<?php
// customer/orders.php - הזמנות לקוח (קוד מתוקן)
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Category.php';

$auth = new Auth();
if (!$auth->checkPermission('customer')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$order = new Order();
$category = new Category();

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'list';

$message = '';
$error = '';

// טיפול בפעולות
if ($_POST) {
    switch ($action) {
        case 'select_quote':
            $result = $order->selectQuote($_POST['quote_id']);
            $message = $result['success'] ? 'ההצעה נבחרה בהצלחה' : $result['message'];
            break;
        case 'close_order':
            $order->updateOrderStatus($_POST['order_id'], 'closed');
            $message = 'ההזמנה נסגרה בהצלחה';
            break;
        case 'update_order':
            // טיפול בתמונות חדשות
            $uploadedImages = [];
            if (isset($_FILES['images'])) {
                foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                    if (!empty($tmpName)) {
                        $file = [
                            'name' => $_FILES['images']['name'][$index],
                            'tmp_name' => $tmpName,
                            'size' => $_FILES['images']['size'][$index]
                        ];
                        $imagePath = upload_image($file, 'orders');
                        if ($imagePath) {
                            $uploadedImages[] = $imagePath;
                        }
                    }
                }
            }
            
            $updateData = [
                'customer_type' => $_POST['customer_type'],
                'company_name' => $_POST['company_name'] ?? null,
                'business_number' => $_POST['business_number'] ?? null,
                'work_description' => $_POST['work_description'],
                'start_location' => $_POST['start_location'],
                'end_location' => $_POST['end_location'],
                'work_start_date' => $_POST['work_start_date'],
                'work_start_time' => $_POST['work_start_time'],
                'work_end_date' => $_POST['work_end_date'],
                'work_end_time' => $_POST['work_end_time'],
                'flexibility' => $_POST['flexibility'],
                'flexibility_before' => $_POST['flexibility_before'] ?? null,
                'flexibility_after' => $_POST['flexibility_after'] ?? null,
                'main_category_id' => !empty($_POST['main_category_id']) ? $_POST['main_category_id'] : null,
                'sub_category_id' => !empty($_POST['sub_category_id']) ? $_POST['sub_category_id'] : null,
                'work_types' => $_POST['work_types'] ?? [],
                'max_budget' => !empty($_POST['max_budget']) ? $_POST['max_budget'] : null,
                'budget_type' => $_POST['budget_type'] ?? null,
                'special_requirements' => $_POST['special_requirements'],
                'quote_deadline' => !empty($_POST['quote_deadline']) ? $_POST['quote_deadline'] : null,
                'images' => $uploadedImages
            ];
            
            $result = $order->updateOrder($id, $updateData, $currentUser['id']);
            if ($result['success']) {
                $message = 'ההזמנה עודכנה בהצלחה';
                $action = 'view'; // חזרה לתצוגה
            } else {
                $error = $result['message'];
            }
            break;
    }
}

if ($action === 'view' && $id) {
    $orderDetails = $order->getOrderById($id);
    if (!$orderDetails || $orderDetails['customer_id'] != $currentUser['id']) {
        redirect('orders.php');
    }
} elseif ($action === 'edit' && $id) {
    $orderDetails = $order->getOrderById($id);
    if (!$orderDetails || $orderDetails['customer_id'] != $currentUser['id']) {
        redirect('orders.php');
    }
    
    // בדיקה אם ניתן לערוך
    if (!$order->canEditOrder($id, $currentUser['id'])) {
        $error = 'לא ניתן לערוך הזמנה זו - יש הצעות מחיר או שההזמנה כבר לא פתוחה';
        $action = 'view';
    } else {
        $mainCategories = $category->getMainCategories();
        // טעינת הנתונים הקיימים לעריכה
        if ($orderDetails['main_category_id']) {
            $existingWorkTypes = $category->getWorkTypes($orderDetails['main_category_id']);
            $existingSubCategories = $category->getSubCategories($orderDetails['main_category_id']);
        }
    }
} else {
    $myOrders = $order->getAllOrders($currentUser['id']);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ההזמנות שלי - <?php echo SITE_NAME; ?></title>
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
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <div class="d-flex justify-between align-center mb-3">
            <h1>ההזמנות שלי</h1>
            <a href="new-order.php" class="btn btn-primary">הזמנה חדשה</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <?php if (empty($myOrders)): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <h3>עדיין לא יצרת הזמנות</h3>
                        <p>צור את ההזמנה הראשונה שלך</p>
                        <a href="new-order.php" class="btn btn-primary">צור הזמנה</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>מספר הזמנה</th>
                                    <th>תיאור</th>
                                    <th>תאריך</th>
                                    <th>סטטוס</th>
                                    <th>הצעות</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myOrders as $ord): ?>
                                    <tr>
                                        <td>#<?php echo $ord['order_number']; ?></td>
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
                                        <td>
                                            <?php if ($ord['quote_count'] > 0): ?>
                                                <span class="status-badge" style="background: var(--success); color: white;">
                                                    <?php echo $ord['quote_count']; ?> הצעות
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--dark-gray);">אין הצעות</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $ord['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">צפייה</a>
                                            <?php if ($ord['status'] === 'open_for_quotes' && $ord['quote_count'] == 0): ?>
                                                <a href="?action=edit&id=<?php echo $ord['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">עריכה</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'view' && isset($orderDetails)): ?>
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h3>הזמנה #<?php echo $orderDetails['order_number']; ?></h3>
                    <div>
                        <?php if ($order->canEditOrder($orderDetails['id'], $currentUser['id'])): ?>
                            <a href="?action=edit&id=<?php echo $orderDetails['id']; ?>" class="btn btn-secondary">ערוך הזמנה</a>
                        <?php endif; ?>
                        <a href="orders.php" class="btn btn-outline">חזרה</a>
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
                            
                            <?php if (!empty($orderDetails['work_types'])): ?>
                                <p><strong>סוגי עבודה:</strong> 
                                    <?php echo implode(', ', array_column($orderDetails['work_types'], 'work_name')); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($orderDetails['images'])): ?>
                                <h5>תמונות</h5>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php foreach ($orderDetails['images'] as $img): ?>
                                        <img src="../uploads/<?php echo $img['image_path']; ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-4">
                            <h4>סטטוס</h4>
                            <span class="status-badge status-<?php echo str_replace('_', '-', $orderDetails['status']); ?>">
                                <?php 
                                $statusLabels = [
                                    'open_for_quotes' => 'פתוח להצעות',
                                    'in_negotiation' => 'במשא ומתן', 
                                    'closed' => 'סגור'
                                ];
                                echo $statusLabels[$orderDetails['status']];
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- הצעות מחיר -->
            <div class="card">
                <div class="card-header">
                    <h3>הצעות מחיר (<?php echo count($orderDetails['quotes']); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($orderDetails['quotes'])): ?>
                        <p style="text-align: center; color: var(--dark-gray);">עדיין לא התקבלו הצעות מחיר</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($orderDetails['quotes'] as $quote): ?>
                                <div class="col-6 mb-3">
                                    <div class="card <?php echo $quote['is_selected'] ? 'border-success' : ''; ?>">
                                        <div class="card-header d-flex justify-between align-center">
                                            <h5><?php echo htmlspecialchars($quote['vehicle_name']); ?></h5>
                                            <strong style="color: var(--primary-color);"><?php echo format_price($quote['quote_amount']); ?></strong>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>בעל הרכב:</strong> <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?></p>
                                            <p><strong>סוג רכב:</strong> <?php echo htmlspecialchars($quote['sub_category_name']); ?></p>
                                            <?php if ($quote['quote_description']): ?>
                                                <p><strong>הערות:</strong> <?php echo htmlspecialchars($quote['quote_description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ($quote['is_selected']): ?>
                                                <span class="status-badge status-closed">נבחר ✓</span>
                                                <p><strong>טלפון:</strong> <?php echo htmlspecialchars($quote['phone']); ?></p>
                                                <p><strong>אימייל:</strong> <?php echo htmlspecialchars($quote['email']); ?></p>
                                            <?php elseif ($orderDetails['status'] === 'open_for_quotes'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                                                    <button type="submit" name="action" value="select_quote" class="btn btn-success" onclick="return confirm('אתה בטוח שברצונך לבחור הצעה זו?')">
                                                        בחר הצעה
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($orderDetails['status'] === 'in_negotiation'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>סיום הזמנה</h3>
                    </div>
                    <div class="card-body text-center">
                        <p>האם העבודה הושלמה?</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?php echo $orderDetails['id']; ?>">
                            <button type="submit" name="action" value="close_order" class="btn btn-success" onclick="return confirm('האם העבודה הושלמה בהצלחה?')">
                                סגור הזמנה
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'edit' && isset($orderDetails) && isset($mainCategories)): ?>
            <!-- טופס עריכה -->
            <div class="card">
                <div class="card-header">
                    <h3>עריכת הזמנה #<?php echo $orderDetails['order_number']; ?></h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>שים לב:</strong> ניתן לערוך הזמנה רק כל עוד לא התקבלו הצעות מחיר
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="editOrderForm">
                        <!-- פרטי הלקוח -->
                        <div class="card" style="margin-bottom: 1rem;">
                            <div class="card-header">
                                <h4>פרטי הלקוח</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">סוג לקוח *</label>
                                            <select name="customer_type" id="customerType" class="form-control" required onchange="toggleBusinessFields()">
                                                <option value="private" <?php echo $orderDetails['customer_type'] === 'private' ? 'selected' : ''; ?>>פרטי</option>
                                                <option value="business" <?php echo $orderDetails['customer_type'] === 'business' ? 'selected' : ''; ?>>עסקי</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">איש קשר</label>
                                            <input type="text" class="form-control" readonly 
                                                   value="<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="businessFields" style="display: <?php echo $orderDetails['customer_type'] === 'business' ? 'block' : 'none'; ?>;">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label">שם החברה *</label>
                                                <input type="text" name="company_name" id="companyName" class="form-control" 
                                                       value="<?php echo htmlspecialchars($orderDetails['company_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label">מספר ח.פ/ע.מ *</label>
                                                <input type="text" name="business_number" id="businessNumber" class="form-control" 
                                                       value="<?php echo htmlspecialchars($orderDetails['business_number'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- פרטי ההזמנה -->
                        <div class="card" style="margin-bottom: 1rem;">
                            <div class="card-header">
                                <h4>פרטי ההזמנה</h4>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">תיאור העבודה *</label>
                                    <textarea name="work_description" class="form-control" rows="4" required><?php echo htmlspecialchars($orderDetails['work_description']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">מיקום תחילת העבודה *</label>
                                            <input type="text" name="start_location" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($orderDetails['start_location']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">מיקום סיום העבודה</label>
                                            <input type="text" name="end_location" class="form-control" 
                                                   value="<?php echo htmlspecialchars($orderDetails['end_location'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- סוג כלי הרכב הנדרש -->
                        <div class="card" style="margin-bottom: 1rem;">
                            <div class="card-header">
                                <h4>סוג כלי הרכב והעבודה הנדרשת</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label class="form-label">קטגוריה ראשית</label>
                                            <select name="main_category_id" id="mainCategory" class="form-control" onchange="loadWorkTypesAndVehicles()">
                                                <option value="">בחר קטגוריה</option>
                                                <?php foreach ($mainCategories as $cat): ?>
                                                    <option value="<?php echo $cat['id']; ?>" <?php echo $orderDetails['main_category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- סוגי עבודות -->
                                <div id="workTypesSection" style="display: <?php echo $orderDetails['main_category_id'] ? 'block' : 'none'; ?>;">
                                    <div class="form-group">
                                        <label class="form-label">סוג העבודה הנדרשת (ניתן לבחור מספר אפשרויות)</label>
                                        <div id="workTypesContainer" class="checkbox-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                            <?php if (isset($existingWorkTypes) && !empty($existingWorkTypes)): ?>
                                                <?php foreach ($existingWorkTypes as $work): ?>
                                                    <div>
                                                        <label style="display: flex; align-items: center; font-weight: normal; margin-bottom: 5px;">
                                                            <input type="checkbox" name="work_types[]" value="<?php echo $work['id']; ?>" 
                                                                   <?php echo in_array($work['id'], array_column($orderDetails['work_types'], 'id')) ? 'checked' : ''; ?> 
                                                                   style="margin-left: 8px;">
                                                            <?php echo htmlspecialchars($work['work_name']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- סוגי כלי רכב -->
                                <div id="vehicleTypesSection" style="display: <?php echo $orderDetails['main_category_id'] ? 'block' : 'none'; ?>;">
                                    <div class="form-group">
                                        <label class="form-label">סוג כלי הרכב הנדרש</label>
                                        <select name="sub_category_id" id="subCategory" class="form-control">
                                            <option value="">בחר סוג כלי רכב</option>
                                            <?php if (isset($existingSubCategories) && !empty($existingSubCategories)): ?>
                                                <?php foreach ($existingSubCategories as $sub): ?>
                                                    <option value="<?php echo $sub['id']; ?>" <?php echo $orderDetails['sub_category_id'] == $sub['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($sub['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">דרישות מיוחדות</label>
                                    <textarea name="special_requirements" class="form-control" rows="3"><?php echo htmlspecialchars($orderDetails['special_requirements'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- זמנים ותאריכים -->
                        <div class="card" style="margin-bottom: 1rem;">
                            <div class="card-header">
                                <h4>זמנים ותאריכים</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label class="form-label">תאריך תחילת העבודה *</label>
                                            <input type="date" name="work_start_date" class="form-control" required 
                                                   value="<?php echo $orderDetails['work_start_date']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label class="form-label">שעת התחלה</label>
                                            <input type="time" name="work_start_time" class="form-control" 
                                                   value="<?php echo $orderDetails['work_start_time'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label class="form-label">תאריך סיום</label>
                                            <input type="date" name="work_end_date" class="form-control" 
                                                   value="<?php echo $orderDetails['work_end_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group">
                                            <label class="form-label">שעת סיום</label>
                                            <input type="time" name="work_end_time" class="form-control" 
                                                   value="<?php echo $orderDetails['work_end_time'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">גמישות בזמנים</label>
                                            <select name="flexibility" id="flexibilitySelect" class="form-control" onchange="toggleFlexibilityFields()">
                                                <option value="none" <?php echo $orderDetails['flexibility'] === 'none' ? 'selected' : ''; ?>>ללא גמישות</option>
                                                <option value="hours" <?php echo $orderDetails['flexibility'] === 'hours' ? 'selected' : ''; ?>>גמישות של מספר שעות</option>
                                                <option value="days" <?php echo $orderDetails['flexibility'] === 'days' ? 'selected' : ''; ?>>גמישות של מספר ימים</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">מועד אחרון לקבלת הצעות</label>
                                            <input type="datetime-local" name="quote_deadline" class="form-control" 
                                                   value="<?php echo $orderDetails['quote_deadline'] ? date('Y-m-d\TH:i', strtotime($orderDetails['quote_deadline'])) : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- שדות גמישות -->
                                <div id="flexibilityDetails" style="display: <?php echo $orderDetails['flexibility'] !== 'none' ? 'block' : 'none'; ?>;">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label" id="flexibilityBeforeLabel">כמות <?php echo $orderDetails['flexibility'] === 'days' ? 'ימים' : 'שעות'; ?> לפני התאריך</label>
                                                <input type="number" name="flexibility_before" id="flexibilityBefore" class="form-control" min="1" 
                                                       value="<?php echo $orderDetails['flexibility_before'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label" id="flexibilityAfterLabel">כמות <?php echo $orderDetails['flexibility'] === 'days' ? 'ימים' : 'שעות'; ?> אחרי התאריך</label>
                                                <input type="number" name="flexibility_after" id="flexibilityAfter" class="form-control" min="1" 
                                                       value="<?php echo $orderDetails['flexibility_after'] ?? ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- תמונות ומחיר -->
                        <div class="card" style="margin-bottom: 1rem;">
                            <div class="card-header">
                                <h4>תמונות ומחיר</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="form-group">
                                            <label class="form-label">תמונות קיימות</label>
                                            <?php if (!empty($orderDetails['images'])): ?>
                                                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 1rem;">
                                                    <?php foreach ($orderDetails['images'] as $img): ?>
                                                        <img src="../uploads/<?php echo $img['image_path']; ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px;">
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <label class="form-label">הוסף תמונות חדשות</label>
                                            <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                                            <small style="color: var(--dark-gray);">תמונות חדשות יתווספו לקיימות</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-group">
                                            <label class="form-label">תקציב מקסימלי</label>
                                            <select name="budget_type" id="budgetType" class="form-control" onchange="toggleBudgetFields()">
                                                <option value="" <?php echo empty($orderDetails['budget_type']) ? 'selected' : ''; ?>>ללא הגבלה</option>
                                                <option value="total" <?php echo $orderDetails['budget_type'] === 'total' ? 'selected' : ''; ?>>מחיר לכל העבודה</option>
                                                <option value="hourly" <?php echo $orderDetails['budget_type'] === 'hourly' ? 'selected' : ''; ?>>מחיר לפי שעה</option>
                                                <option value="daily" <?php echo $orderDetails['budget_type'] === 'daily' ? 'selected' : ''; ?>>מחיר לפי יום</option>
                                            </select>
                                        </div>
                                        
                                        <div id="budgetAmount" style="display: <?php echo !empty($orderDetails['budget_type']) ? 'block' : 'none'; ?>;">
                                            <div class="form-group">
                                                <label class="form-label" id="budgetLabel">סכום מקסימלי (₪)</label>
                                                <input type="number" name="max_budget" class="form-control" 
                                                       value="<?php echo $orderDetails['max_budget'] ?? ''; ?>">
                                                <small class="form-text text-muted">המחיר בש"ח כולל מע"מ</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <button type="submit" name="action" value="update_order" class="btn btn-primary" style="padding: 1rem 2rem;">
                                עדכן הזמנה
                            </button>
                            <a href="?action=view&id=<?php echo $orderDetails['id']; ?>" class="btn btn-secondary" style="padding: 1rem 2rem;">
                                ביטול
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // קבלת סוגי עבודות נבחרים - מתוקן
        <?php if ($action === 'edit' && isset($orderDetails)): ?>
            const selectedWorkTypes = [<?php echo implode(',', array_column($orderDetails['work_types'] ?? [], 'id')); ?>];
            const hasMainCategory = <?php echo $orderDetails['main_category_id'] ? 'true' : 'false'; ?>;
            const selectedMainCategoryId = <?php echo $orderDetails['main_category_id'] ?? 'null'; ?>;
            const selectedSubCategoryId = <?php echo $orderDetails['sub_category_id'] ?? 'null'; ?>;
        <?php else: ?>
            const selectedWorkTypes = [];
            const hasMainCategory = false;
            const selectedMainCategoryId = null;
            const selectedSubCategoryId = null;
        <?php endif; ?>
        
        function toggleBusinessFields() {
            const customerType = document.getElementById('customerType').value;
            const businessFields = document.getElementById('businessFields');
            const companyName = document.getElementById('companyName');
            const businessNumber = document.getElementById('businessNumber');
            
            if (customerType === 'business') {
                businessFields.style.display = 'block';
                companyName.required = true;
                businessNumber.required = true;
            } else {
                businessFields.style.display = 'none';
                companyName.required = false;
                businessNumber.required = false;
            }
        }

        function loadWorkTypesAndVehicles() {
            const mainCategoryId = document.getElementById('mainCategory').value;
            const workTypesSection = document.getElementById('workTypesSection');
            const vehicleTypesSection = document.getElementById('vehicleTypesSection');
            const workTypesContainer = document.getElementById('workTypesContainer');
            const subCategorySelect = document.getElementById('subCategory');
            
            if (!mainCategoryId) {
                workTypesSection.style.display = 'none';
                vehicleTypesSection.style.display = 'none';
                workTypesContainer.innerHTML = '';
                subCategorySelect.innerHTML = '<option value="">בחר סוג כלי רכב</option>';
                return;
            }
            
            fetch(`../api/categories.php?action=get_sub_categories&main_id=${mainCategoryId}`)
                .then(response => response.json())
                .then(data => {
                    // טעינת סוגי עבודות
                    if (data.work_types && data.work_types.length > 0) {
                        workTypesSection.style.display = 'block';
                        workTypesContainer.innerHTML = '';
                        data.work_types.forEach(work => {
                            const div = document.createElement('div');
                            const isChecked = selectedWorkTypes.includes(work.id) ? 'checked' : '';
                            div.innerHTML = `
                                <label style="display: flex; align-items: center; font-weight: normal; margin-bottom: 5px;">
                                    <input type="checkbox" name="work_types[]" value="${work.id}" ${isChecked} style="margin-left: 8px;">
                                    ${work.work_name}
                                </label>
                            `;
                            workTypesContainer.appendChild(div);
                        });
                    }
                    
                    // טעינת סוגי כלי רכב
                    if (data.sub_categories && data.sub_categories.length > 0) {
                        vehicleTypesSection.style.display = 'block';
                        subCategorySelect.innerHTML = '<option value="">בחר סוג כלי רכב</option>';
                        data.sub_categories.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = sub.name;
                            if (sub.id == selectedSubCategoryId) {
                                option.selected = true;
                            }
                            subCategorySelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('שגיאה בטעינת הנתונים:', error);
                });
        }

        function toggleFlexibilityFields() {
            const flexibilitySelect = document.getElementById('flexibilitySelect');
            const flexibilityDetails = document.getElementById('flexibilityDetails');
            const beforeLabel = document.getElementById('flexibilityBeforeLabel');
            const afterLabel = document.getElementById('flexibilityAfterLabel');
            const beforeInput = document.getElementById('flexibilityBefore');
            const afterInput = document.getElementById('flexibilityAfter');
            
            const selectedValue = flexibilitySelect.value;
            
            if (selectedValue === 'none') {
                flexibilityDetails.style.display = 'none';
                beforeInput.required = false;
                afterInput.required = false;
            } else {
                flexibilityDetails.style.display = 'block';
                beforeInput.required = true;
                afterInput.required = true;
                
                if (selectedValue === 'hours') {
                    beforeLabel.textContent = 'כמות שעות לפני התאריך';
                    afterLabel.textContent = 'כמות שעות אחרי התאריך';
                } else if (selectedValue === 'days') {
                    beforeLabel.textContent = 'כמות ימים לפני התאריך';
                    afterLabel.textContent = 'כמות ימים אחרי התאריך';
                }
            }
        }

        function toggleBudgetFields() {
            const budgetType = document.getElementById('budgetType').value;
            const budgetAmount = document.getElementById('budgetAmount');
            const budgetLabel = document.getElementById('budgetLabel');
            
            if (budgetType === '') {
                budgetAmount.style.display = 'none';
            } else {
                budgetAmount.style.display = 'block';
                
                switch(budgetType) {
                    case 'total':
                        budgetLabel.textContent = 'מחיר מקסימלי לכל העבודה (₪)';
                        break;
                    case 'hourly':
                        budgetLabel.textContent = 'מחיר מקסימלי לשעה (₪)';
                        break;
                    case 'daily':
                        budgetLabel.textContent = 'מחיר מקסימלי ליום (₪)';
                        break;
                }
            }
        }

        // טעינה ראשונית - מתוקן
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('customerType')) {
                toggleBusinessFields();
                toggleFlexibilityFields();
                toggleBudgetFields();
                
                // אם יש קטגוריה ראשית נבחרת, אין צורך לטעון מחדש
                // הנתונים כבר נטענו ב-PHP
                if (!hasMainCategory) {
                    // רק אם אין קטגוריה נבחרת נטען מחדש
                    const mainCategory = document.getElementById('mainCategory');
                    if (mainCategory && mainCategory.value) {
                        loadWorkTypesAndVehicles();
                    }
                }
            }
        });
    </script>
</body>
</html>