<?php
// admin/users.php - ניהול משתמשים עם עריכה
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/User.php';

$auth = new Auth();
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$user = new User();
$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'all';
$id = $_GET['id'] ?? null;

$message = '';
$error = '';

// קבלת פרטי משתמש לעריכה
$editUser = null;
if ($action === 'edit' && $id) {
    $editUser = $user->getUserById($id);
    if (!$editUser) {
        $error = 'משתמש לא נמצא';
        $action = 'list';
    }
}

// טיפול בפעולות
if ($_POST) {
    switch ($action) {
        case 'add':
            $result = $user->createUser($_POST);
            if ($result['success']) {
                $message = 'המשתמש נוצר בהצלחה';
                $action = 'list';
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'edit':
            $result = $user->updateUser($id, $_POST);
            if ($result['success']) {
                $message = 'המשתמש עודכן בהצלחה';
                $action = 'list';
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'reset_password':
            $userId = $_POST['user_id'];
            $targetUser = $user->getUserById($userId);
            if ($targetUser) {
                $result = $user->resetPassword($targetUser['email']);
                if ($result['success']) {
                    $message = 'סיסמה זמנית נוצרה: <strong>' . $result['temp_password'] . '</strong><br>העתק את הסיסמה ושלח למשתמש';
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = 'משתמש לא נמצא';
            }
            break;
            
        case 'manage_subscription':
            $userId = $_POST['user_id'];
            $action_type = $_POST['subscription_action'];
            $months = (int)($_POST['months'] ?? 1);
            
            switch ($action_type) {
                case 'activate':
                    $result = $user->activatePremium($userId, $months);
                    break;
                case 'extend':
                    $result = $user->activatePremium($userId, $months);
                    break;
                case 'cancel':
                    $result = $user->deactivatePremium($userId);
                    break;
                default:
                    $result = ['success' => false, 'message' => 'פעולה לא מזוהה'];
            }
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'toggle_active':
            $userId = $_POST['user_id'];
            $isActive = $_POST['is_active'];
            $result = $user->updateUser($userId, ['is_active' => $isActive]);
            $message = $result['success'] ? 'סטטוס המשתמש עודכן' : $result['message'];
            break;
    }
}

// קבלת משתמשים
$users = [];
switch ($type) {
    case 'vehicle_owner':
        $users = $user->getVehicleOwners();
        break;
    case 'customer':
        $users = $user->getCustomers();
        break;
    default:
        $users = $user->getAllUsers();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים - <?php echo SITE_NAME; ?></title>
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
        .form-control:focus {
            outline: none;
            border-color: #FF7A00;
            box-shadow: 0 0 0 2px rgba(255, 122, 0, 0.2);
        }
        .form-control[readonly] {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .text-muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .mt-3 {
            margin-top: 1rem;
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
        .alert-danger {
            background: #fde8e8;
            color: #9b1c1c;
            border: 1px solid #f87171;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 4px;
            text-align: center;
        }
        select.form-control {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
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
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <h1>ניהול משתמשים</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Filter Tabs -->
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                        <a href="?type=all" class="btn <?php echo $type === 'all' ? 'btn-primary' : 'btn-outline'; ?>">
                            כל המשתמשים
                        </a>
                        <a href="?type=vehicle_owner" class="btn <?php echo $type === 'vehicle_owner' ? 'btn-primary' : 'btn-outline'; ?>">
                            בעלי רכב
                        </a>
                        <a href="?type=customer" class="btn <?php echo $type === 'customer' ? 'btn-primary' : 'btn-outline'; ?>">
                            לקוחות
                        </a>
                        <button onclick="showAddUserModal()" class="btn btn-success">הוספת משתמש</button>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>שם</th>
                                <th>אימייל</th>
                                <th>סוג</th>
                                <th>סטטוס</th>
                                <th>פרימיום</th>
                                <th>תאריך הצטרפות</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php 
                                        $types = [
                                            'admin' => 'מנהל',
                                            'vehicle_owner' => 'בעל רכב', 
                                            'customer' => 'לקוח'
                                        ];
                                        echo $types[$u['user_type']] ?? $u['user_type'];
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" name="action" value="toggle_active" 
                                                    class="btn <?php echo $u['is_active'] ? 'btn-success' : 'btn-danger'; ?>" 
                                                    style="padding: 0.25rem 0.5rem;">
                                                <?php echo $u['is_active'] ? 'פעיל' : 'לא פעיל'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($u['user_type'] === 'vehicle_owner'): ?>
                                            <div style="text-align: center;">
                                                <?php if ($u['is_premium']): ?>
                                                    <span class="badge" style="background: #ffc107; color: #000; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                                        ⭐ פרימיום
                                                    </span>
                                                    <?php if ($u['premium_expires']): ?>
                                                        <small style="display: block; color: #666; margin-top: 0.25rem;">
                                                            עד: <?php echo date('d/m/Y', strtotime($u['premium_expires'])); ?>
                                                            <?php 
                                                            $daysLeft = floor((strtotime($u['premium_expires']) - time()) / (60*60*24));
                                                            if ($daysLeft <= 7 && $daysLeft >= 0) {
                                                                echo " <span style='color: #dc3545;'>({$daysLeft} ימים)</span>";
                                                            }
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #6c757d; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                                                        רגיל
                                                    </span>
                                                <?php endif; ?>
                                                <br>
                                                <button onclick="showSubscriptionModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>', <?php echo $u['is_premium'] ? 'true' : 'false'; ?>, '<?php echo $u['premium_expires'] ?? ''; ?>')" 
                                                        class="btn btn-primary" style="padding: 0.2rem 0.4rem; font-size: 0.8rem; margin-top: 0.25rem;">
                                                    ניהול מנוי
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">לא רלוונטי</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">עריכה</a>
                                        <?php if ($u['user_type'] === 'vehicle_owner'): ?>
                                            <a href="../vehicle-owner/vehicles.php?owner_id=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;">רכבים</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($action === 'add'): ?>
            <!-- Add User Form -->
            <div class="card">
                <div class="card-header">
                    <h3>הוספת משתמש חדש</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם פרטי *</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם משפחה *</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם משתמש *</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">אימייל *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">טלפון</label>
                                    <input type="tel" name="phone" class="form-control">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">סוג משתמש *</label>
                                    <select name="user_type" class="form-control" required>
                                        <option value="customer">לקוח</option>
                                        <option value="vehicle_owner">בעל רכב</option>
                                        <option value="admin">מנהל</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">סיסמה *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">שמור</button>
                            <a href="?action=list" class="btn btn-secondary">ביטול</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'edit' && $editUser): ?>
            <!-- Edit User Form -->
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h3>עריכת משתמש: <?php echo htmlspecialchars($editUser['first_name'] . ' ' . $editUser['last_name']); ?></h3>
                    <a href="?action=list" class="btn btn-secondary">חזרה לרשימה</a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם פרטי *</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($editUser['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם משפחה *</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($editUser['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם משתמש</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($editUser['username']); ?>" readonly
                                           style="background: #f8f9fa;">
                                    <small class="text-muted">שם משתמש לא ניתן לשינוי</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">אימייל *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">טלפון</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">סוג משתמש *</label>
                                    <select name="user_type" class="form-control" required>
                                        <option value="customer" <?php echo $editUser['user_type'] === 'customer' ? 'selected' : ''; ?>>לקוח</option>
                                        <option value="vehicle_owner" <?php echo $editUser['user_type'] === 'vehicle_owner' ? 'selected' : ''; ?>>בעל רכב</option>
                                        <option value="admin" <?php echo $editUser['user_type'] === 'admin' ? 'selected' : ''; ?>>מנהל</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">סטטוס חשבון</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" <?php echo $editUser['is_active'] ? 'selected' : ''; ?>>פעיל</option>
                                        <option value="0" <?php echo !$editUser['is_active'] ? 'selected' : ''; ?>>לא פעיל</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <?php if ($editUser['user_type'] === 'vehicle_owner'): ?>
                                    <div class="form-group">
                                        <label class="form-label">מנוי פרימיום</label>
                                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px;">
                                            <strong>סטטוס:</strong> 
                                            <?php if ($editUser['is_premium']): ?>
                                                <span style="color: #28a745;">פרימיום פעיל</span>
                                                <?php if ($editUser['premium_expires']): ?>
                                                    <br><small>עד: <?php echo date('d/m/Y', strtotime($editUser['premium_expires'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #6c757d;">מנוי רגיל</span>
                                            <?php endif; ?>
                                            <br><small>השתמש בכפתורים ברשימה לשינוי מנוי</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- מידע נוסף -->
                        <div class="row">
                            <div class="col-12">
                                <div style="background: #e9ecef; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                                    <h5>מידע נוסף</h5>
                                    <div class="row">
                                        <div class="col-4">
                                            <strong>תאריך הצטרפות:</strong><br>
                                            <?php echo date('d/m/Y H:i', strtotime($editUser['created_at'])); ?>
                                        </div>
                                        <div class="col-4">
                                            <strong>עדכון אחרון:</strong><br>
                                            <?php echo isset($editUser['updated_at']) && $editUser['updated_at'] ? date('d/m/Y H:i', strtotime($editUser['updated_at'])) : 'לא עודכן'; ?>
                                        </div>
                                        <div class="col-4">
                                            <strong>ID משתמש:</strong><br>
                                            #<?php echo $editUser['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">עדכן משתמש</button>
                            <a href="?action=list" class="btn btn-secondary">ביטול</a>
                            <button type="button" onclick="showResetPasswordModal(<?php echo $editUser['id']; ?>)" 
                                    class="btn btn-warning">איפוס סיסמה</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>הוספת משתמש מהיר</h3>
            <div style="display: grid; gap: 1rem;">
                <a href="?action=add&type=customer" class="btn btn-primary">הוספת לקוח</a>
                <a href="?action=add&type=vehicle_owner" class="btn btn-secondary">הוספת בעל רכב</a>
                <a href="?action=add&type=admin" class="btn btn-outline">הוספת מנהל</a>
            </div>
            <button onclick="closeAddUserModal()" class="btn btn-secondary mt-3" style="width: 100%;">ביטול</button>
        </div>
    </div>

    <!-- Subscription Management Modal -->
    <div id="subscriptionModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 id="subscriptionModalTitle">ניהול מנוי פרימיום</h3>
                <button onclick="closeSubscriptionModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">×</button>
            </div>
            
            <div id="subscriptionUserInfo" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
                <!-- מידע על המשתמש יוכנס כאן דרך JavaScript -->
            </div>
            
            <form method="POST" id="subscriptionForm">
                <input type="hidden" name="user_id" id="subscriptionUserId">
                
                <div class="form-group">
                    <label class="form-label">בחר פעולה</label>
                    <select name="subscription_action" id="subscriptionAction" class="form-control" onchange="toggleSubscriptionOptions()" required>
                        <option value="">בחר פעולה</option>
                        <option value="activate">הפעלת מנוי פרימיום</option>
                        <option value="extend">הארכת מנוי קיים</option>
                        <option value="cancel">ביטול מנוי פרימיום</option>
                    </select>
                </div>
                
                <div id="monthsSelection" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">משך המנוי</label>
                        <select name="months" class="form-control">
    <option value="1">חודש אחד - 69₪</option>
    <option value="3">3 חודשים - 207₪ (69₪ × 3)</option>
    <option value="6">6 חודשים - 389₪ (חסכון של 25₪)</option>
    <option value="12" selected>12 חודשים - 749₪ (חסכון של 79₪)</option>
</select>
                    </div>
                    
                    <div id="expiryPreview" style="background: #e3f2fd; padding: 1rem; border-radius: 5px; margin-top: 1rem;">
                        <strong>תאריך תפוגה חדש:</strong> <span id="newExpiryDate"></span>
                    </div>
                </div>
                
                <div id="cancelWarning" style="display: none; background: #fff3cd; border: 1px solid #ffeaa7; padding: 1rem; border-radius: 5px; margin: 1rem 0;">
                    <strong>⚠️ אזהרה:</strong> פעולה זו תבטל מיידית את המנוי הפרימיום. המשתמש יחזור למנוי רגיל.
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                    <button type="submit" name="action" value="manage_subscription" class="btn btn-primary" id="subscriptionSubmitBtn">
                        בצע פעולה
                    </button>
                    <button type="button" onclick="closeSubscriptionModal()" class="btn btn-secondary">
                        ביטול
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>איפוס סיסמה</h3>
            <p>האם אתה בטוח שברצונך לאפס את הסיסמה של המשתמש?</p>
            <p><strong>סיסמה זמנית תוצג כאן ותישלח למשתמש באימייל.</strong></p>
            
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="user_id" id="resetUserId">
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button type="submit" name="action" value="reset_password" class="btn btn-warning">אפס סיסמה</button>
                    <button type="button" onclick="closeResetPasswordModal()" class="btn btn-secondary">ביטול</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }
        
        function showResetPasswordModal(userId) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }
        
        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
        }
        
        function showSubscriptionModal(userId, userName, isPremium, expiryDate) {
            document.getElementById('subscriptionUserId').value = userId;
            document.getElementById('subscriptionModalTitle').textContent = 'ניהול מנוי פרימיום - ' + userName;
            
            // הצגת מידע על המשתמש
            let userInfo = '<h5>' + userName + '</h5>';
            if (isPremium && expiryDate) {
                const expiry = new Date(expiryDate);
                const today = new Date();
                const daysLeft = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
                
                userInfo += '<p><strong>סטטוס נוכחי:</strong> <span style="color: #28a745;">מנוי פרימיום פעיל</span></p>';
                userInfo += '<p><strong>תפוגה:</strong> ' + expiry.toLocaleDateString('he-IL');
                
                if (daysLeft > 0) {
                    userInfo += ' <span style="color: #666;">(' + daysLeft + ' ימים נותרו)</span>';
                } else if (daysLeft === 0) {
                    userInfo += ' <span style="color: #dc3545;">(פג היום)</span>';
                } else {
                    userInfo += ' <span style="color: #dc3545;">(פג לפני ' + Math.abs(daysLeft) + ' ימים)</span>';
                }
                userInfo += '</p>';
            } else {
                userInfo += '<p><strong>סטטוס נוכחי:</strong> <span style="color: #6c757d;">מנוי רגיל</span></p>';
            }
            
            document.getElementById('subscriptionUserInfo').innerHTML = userInfo;
            document.getElementById('subscriptionModal').style.display = 'block';
            
            // איפוס הטופס
            document.getElementById('subscriptionAction').value = '';
            document.getElementById('monthsSelection').style.display = 'none';
            document.getElementById('cancelWarning').style.display = 'none';
        }
        
        function closeSubscriptionModal() {
            document.getElementById('subscriptionModal').style.display = 'none';
        }
        
        function toggleSubscriptionOptions() {
            const action = document.getElementById('subscriptionAction').value;
            const monthsSelection = document.getElementById('monthsSelection');
            const cancelWarning = document.getElementById('cancelWarning');
            const submitBtn = document.getElementById('subscriptionSubmitBtn');
            
            // איפוס תצוגה
            monthsSelection.style.display = 'none';
            cancelWarning.style.display = 'none';
            
            switch (action) {
                case 'activate':
                    monthsSelection.style.display = 'block';
                    submitBtn.textContent = 'הפעל מנוי פרימיום';
                    submitBtn.className = 'btn btn-success';
                    updateExpiryPreview();
                    break;
                case 'extend':
                    monthsSelection.style.display = 'block';
                    submitBtn.textContent = 'הארך מנוי';
                    submitBtn.className = 'btn btn-primary';
                    updateExpiryPreview();
                    break;
                case 'cancel':
                    cancelWarning.style.display = 'block';
                    submitBtn.textContent = 'בטל מנוי פרימיום';
                    submitBtn.className = 'btn btn-danger';
                    break;
                default:
                    submitBtn.textContent = 'בצע פעולה';
                    submitBtn.className = 'btn btn-primary';
            }
        }
        
        function updateExpiryPreview() {
            const monthsSelect = document.querySelector('select[name="months"]');
            const months = parseInt(monthsSelect.value);
            const previewElement = document.getElementById('newExpiryDate');
            
            if (months) {
                const newDate = new Date();
                newDate.setMonth(newDate.getMonth() + months);
                previewElement.textContent = newDate.toLocaleDateString('he-IL');
            }
        }
        
        // עדכון תאריך תפוגה כאשר משנים את כמות החודשים
        document.addEventListener('DOMContentLoaded', function() {
            const monthsSelect = document.querySelector('select[name="months"]');
            if (monthsSelect) {
                monthsSelect.addEventListener('change', updateExpiryPreview);
            }
        });
        
        // Close modals on background click
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddUserModal();
            }
        });
        
        document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetPasswordModal();
            }
        });
        
        document.getElementById('subscriptionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSubscriptionModal();
            }
        });
    </script>
</body>
</html>