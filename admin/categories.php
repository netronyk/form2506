<?php
// admin/categories.php - ניהול קטגוריות
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Category.php';

$auth = new Auth();
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$category = new Category();
$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'main';
$id = $_GET['id'] ?? null;

$message = '';
$error = '';

// API endpoint לטעינת תתי קטגוריות
if ($action === 'get_sub_categories' && isset($_GET['main_id'])) {
    header('Content-Type: application/json');
    $subs = $category->getSubCategories($_GET['main_id']);
    echo json_encode($subs);
    exit;
}

// טיפול בפעולות
if ($_POST) {
    switch ($action) {
        case 'add_main':
            $result = $category->createMainCategory($_POST);
            if ($result) {
                $message = 'קטגוריה ראשית נוצרה בהצלחה';
                $action = 'list';
            } else {
                $error = 'שגיאה ביצירת הקטגוריה';
            }
            break;
            
        case 'update_main':
            $updateData = [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'sort_order' => $_POST['sort_order'] ?? 0
            ];
            $result = $category->updateMainCategory($id, $updateData);
            if ($result) {
                $message = 'קטגוריה עודכנה בהצלחה';
                $action = 'list';
            } else {
                $error = 'שגיאה בעדכון הקטגוריה';
            }
            break;
            
        case 'add_sub':
            $result = $category->createSubCategory($_POST);
            if ($result) {
                $message = 'תת קטגוריה נוצרה בהצלחה';
                $action = 'sub_categories';
            } else {
                $error = 'שגיאה ביצירת תת הקטגוריה';
            }
            break;
            
        case 'update_sub':
            $updateData = [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'main_category_id' => $_POST['main_category_id'],
                'sort_order' => $_POST['sort_order'] ?? 0
            ];
            $result = $category->updateSubCategory($id, $updateData);
            if ($result) {
                $message = 'תת קטגוריה עודכנה בהצלחה';
                $action = 'sub_categories';
            } else {
                $error = 'שגיאה בעדכון תת הקטגוריה';
            }
            break;
            
        case 'add_attribute':
            $result = $category->createTechnicalAttribute($_POST);
            if ($result) {
                $message = 'מאפיין טכני נוצר בהצלחה';
                $action = 'attributes';
                $_GET['sub_id'] = $_POST['sub_category_id']; // לחזור לעמוד המאפיינים
            } else {
                $error = 'שגיאה ביצירת המאפיין';
            }
            break;
            
        case 'delete_attr':
            $result = $category->deleteTechnicalAttribute($id);
            if ($result) {
                $message = 'מאפיין נמחק בהצלחה';
                $action = 'attributes';
                $_GET['sub_id'] = $_GET['sub_id'] ?? null; // לחזור לעמוד המאפיינים
            } else {
                $error = 'שגיאה במחיקת המאפיין';
            }
            break;
    }
}

// קבלת נתונים
$mainCategories = $category->getMainCategories();
$subCategories = $category->getSubCategories();

// קבלת נתונים לעריכה
$editCategory = null;
$editSubCategory = null;
if ($action === 'edit' && $id) {
    $editCategory = $category->getMainCategoryById($id);
}
if ($action === 'edit_sub' && $id) {
    $editSubCategory = $category->getSubCategoryById($id);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול קטגוריות - <?php echo SITE_NAME; ?></title>
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
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 4px;
        }
        .sort-icon {
            font-size: 0.8rem;
            margin-left: 0.25rem;
            color: #666;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .icon {
            font-size: 0.9rem;
            margin-left: 0.25rem;
        }
        #resultsInfo {
            color: #666;
            font-size: 0.9rem;
            padding: 0.5rem 0;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
    </style>
</head>
<body>
    <!-- Header -->
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
        <h1>ניהול קטגוריות ומאפיינים</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="card">
            <div class="card-body">
                <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                    <a href="?action=list" class="btn <?php echo $action === 'list' ? 'btn-primary' : 'btn-outline'; ?>">
                        קטגוריות ראשיות
                    </a>
                    <a href="?action=sub_categories" class="btn <?php echo $action === 'sub_categories' ? 'btn-primary' : 'btn-outline'; ?>">
                        תתי קטגוריות
                    </a>
                    <a href="?action=attributes" class="btn <?php echo $action === 'attributes' ? 'btn-primary' : 'btn-outline'; ?>">
                        מאפיינים טכניים
                    </a>
                </div>

                <?php if ($action === 'list'): ?>
                    <!-- Main Categories -->
                    <div class="d-flex justify-between align-center mb-3">
                        <h3>קטגוריות ראשיות</h3>
                        <button onclick="showAddMainModal()" class="btn btn-primary">הוספת קטגוריה ראשית</button>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>שם</th>
                                <th>תיאור</th>
                                <th>סדר</th>
                                <th>תתי קטגוריות</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mainCategories as $cat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><?php echo htmlspecialchars($cat['description'] ?? ''); ?></td>
                                    <td><?php echo $cat['sort_order']; ?></td>
                                    <td>
                                        <?php 
                                        $subCount = count(array_filter($subCategories, function($sub) use ($cat) {
                                            return $sub['main_category_id'] == $cat['id'];
                                        }));
                                        echo $subCount;
                                        ?>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">עריכה</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php elseif ($action === 'edit' && $editCategory): ?>
                    <!-- Edit Main Category -->
                    <h3>עריכת קטגוריה ראשית</h3>
                    <form method="POST" action="?action=update_main&id=<?php echo $editCategory['id']; ?>">
                        <div class="form-group">
                            <label class="form-label">שם הקטגוריה</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editCategory['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">תיאור</label>
                            <textarea name="description" class="form-control"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">סדר תצוגה</label>
                            <input type="number" name="sort_order" class="form-control" value="<?php echo $editCategory['sort_order']; ?>">
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">עדכן</button>
                            <a href="?action=list" class="btn btn-secondary">ביטול</a>
                        </div>
                    </form>

                <?php elseif ($action === 'sub_categories'): ?>
                    <!-- Sub Categories with Filters -->
                    <div class="d-flex justify-between align-center mb-3">
                        <h3>תתי קטגוריות</h3>
                        <button onclick="showAddSubModal()" class="btn btn-primary">הוספת תת קטגוריה</button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-4">
                                    <label class="form-label">סנן לפי קטגוריה ראשית:</label>
                                    <select id="categoryFilter" class="form-control" onchange="filterSubCategories()">
                                        <option value="">כל הקטגוריות</option>
                                        <?php foreach ($mainCategories as $main): ?>
                                            <option value="<?php echo $main['id']; ?>" <?php echo ($_GET['main_id'] ?? '') == $main['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($main['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">חיפוש לפי שם:</label>
                                    <input type="text" id="searchInput" class="form-control" placeholder="הקלד שם תת קטגוריה" onkeyup="searchSubCategories()">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">מספר תוצאות:</label>
                                    <select id="limitFilter" class="form-control" onchange="limitResults()">
                                        <option value="10">10</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="all">הכל</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Info -->
                    <div id="resultsInfo" class="mb-3">
                        <span id="resultsCount">0</span> תוצאות מתוך <?php echo count($subCategories); ?>
                    </div>
                    
                    <!-- Sub Categories Table -->
                    <div id="subCategoriesContainer">
                        <?php 
                        $selectedMainId = $_GET['main_id'] ?? null;
                        $filteredSubs = $selectedMainId ? 
                            array_filter($subCategories, function($sub) use ($selectedMainId) {
                                return $sub['main_category_id'] == $selectedMainId;
                            }) : $subCategories;
                        ?>
                        
                        <?php if (empty($filteredSubs) && $selectedMainId): ?>
                            <div class="alert alert-info text-center">
                                אין תתי קטגוריות לקטגוריה הנבחרת
                            </div>
                        <?php elseif (empty($filteredSubs)): ?>
                            <div class="alert alert-info text-center">
                                בחר קטגוריה ראשית כדי לראות את תתי הקטגוריות
                            </div>
                        <?php else: ?>
                            <table class="table" id="subCategoriesTable">
                                <thead>
                                    <tr>
                                        <th onclick="sortTable(0)" style="cursor: pointer;">
                                            שם <i class="sort-icon">↕</i>
                                        </th>
                                        <th onclick="sortTable(1)" style="cursor: pointer;">
                                            קטגוריה ראשית <i class="sort-icon">↕</i>
                                        </th>
                                        <th>תיאור</th>
                                        <th>מאפיינים</th>
                                        <th>פעולות</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredSubs as $sub): ?>
                                        <tr class="sub-category-row" data-main-id="<?php echo $sub['main_category_id']; ?>" data-name="<?php echo strtolower($sub['name']); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($sub['name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: #FF7A00; color: white;">
                                                    <?php echo htmlspecialchars($sub['main_category_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: #666; font-size: 0.9rem;">
                                                    <?php echo htmlspecialchars(substr($sub['description'] ?? '', 0, 50)) . (strlen($sub['description'] ?? '') > 50 ? '...' : ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <a href="?action=attributes&sub_id=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">
                                                        רשימה
                                                    </a>
                                                    <button onclick="showAddAttributeModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['main_category_name'] . ' → ' . $sub['name']); ?>')" 
                                                            class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">
                                                        + הוסף
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="?action=edit_sub&id=<?php echo $sub['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">
                                                    עריכה
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                <?php elseif ($action === 'edit_sub' && $editSubCategory): ?>
                    <!-- Edit Sub Category -->
                    <h3>עריכת תת קטגוריה</h3>
                    <form method="POST" action="?action=update_sub&id=<?php echo $editSubCategory['id']; ?>">
                        <div class="form-group">
                            <label class="form-label">קטגוריה ראשית</label>
                            <select name="main_category_id" class="form-control" required>
                                <?php foreach ($mainCategories as $main): ?>
                                    <option value="<?php echo $main['id']; ?>" <?php echo $main['id'] == $editSubCategory['main_category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($main['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">שם תת הקטגוריה</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editSubCategory['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">תיאור</label>
                            <textarea name="description" class="form-control"><?php echo htmlspecialchars($editSubCategory['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">סדר תצוגה</label>
                            <input type="number" name="sort_order" class="form-control" value="<?php echo $editSubCategory['sort_order']; ?>">
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">עדכן</button>
                            <a href="?action=sub_categories" class="btn btn-secondary">ביטול</a>
                        </div>
                    </form>

                <?php elseif ($action === 'attributes'): ?>
                    <!-- Technical Attributes with Filters -->
                    <?php 
                    $subId = $_GET['sub_id'] ?? null;
                    $mainId = $_GET['main_id'] ?? null;
                    $attributes = $subId ? $category->getTechnicalAttributes($subId) : [];
                    $subCategoryName = $subId ? $category->getSubCategoryNameById($subId) : '';
                    ?>
                    
                    <div class="d-flex justify-between align-center mb-3">
                        <h3>מאפיינים טכניים</h3>
                        <?php if ($subId): ?>
                            <button onclick="showAddAttributeModal(<?php echo $subId; ?>, '<?php echo htmlspecialchars($subCategoryName); ?>')" class="btn btn-primary">הוספת מאפיין</button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Category Selection Filters -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <label class="form-label">בחר קטגוריה ראשית:</label>
                                    <select id="mainCategorySelect" class="form-control" onchange="loadSubCategories()">
                                        <option value="">בחר קטגוריה ראשית</option>
                                        <?php foreach ($mainCategories as $main): ?>
                                            <option value="<?php echo $main['id']; ?>" <?php echo $mainId == $main['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($main['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">בחר תת קטגוריה:</label>
                                    <select id="subCategorySelect" class="form-control" onchange="loadAttributes()" disabled>
                                        <option value="">תחילה בחר קטגוריה ראשית</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$subId): ?>
                        <div class="alert alert-info text-center">
                            <h4>בחר תת קטגוריה כדי לנהל מאפיינים</h4>
                            <p>השתמש במסננים למעלה כדי לבחור קטגוריה ותת קטגוריה</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>מאפיינים עבור:</strong> <?php echo htmlspecialchars($subCategoryName); ?>
                        </div>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>שם המאפיין</th>
                                    <th>סוג שדה</th>
                                    <th>יחידה</th>
                                    <th>נדרש</th>
                                    <th>אפשרויות</th>
                                    <th>סדר</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attributes)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #666; padding: 2rem;">
                                            <h5>אין מאפיינים עדיין</h5>
                                            <p>השתמש בכפתור "הוספת מאפיין" כדי להוסיף מאפיין ראשון</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attributes as $attr): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($attr['attribute_name']); ?></strong></td>
                                            <td>
                                                <span class="badge" style="background: #f8f9fa; color: #333;">
                                                    <?php 
                                                    $types = [
                                                        'text' => 'טקסט',
                                                        'number' => 'מספר',
                                                        'select' => 'רשימה',
                                                        'checkbox' => 'סימון',
                                                        'range' => 'טווח'
                                                    ];
                                                    echo $types[$attr['attribute_type']] ?? $attr['attribute_type'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($attr['unit'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($attr['is_required']): ?>
                                                    <span style="color: #dc3545;">✓ חובה</span>
                                                <?php else: ?>
                                                    <span style="color: #666;">אופציונלי</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.9rem; color: #666;">
                                                    <?php 
                                                    $options = $attr['options'] ?? '';
                                                    echo $options ? htmlspecialchars(substr($options, 0, 30)) . (strlen($options) > 30 ? '...' : '') : '-';
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: #e9ecef; color: #495057;">
                                                    <?php echo $attr['sort_order']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <a href="?action=edit_attr&id=<?php echo $attr['id']; ?>&sub_id=<?php echo $subId; ?>" 
                                                       class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">
                                                        עריכה
                                                    </a>
                                                    <a href="?action=delete_attr&id=<?php echo $attr['id']; ?>&sub_id=<?php echo $subId; ?>" 
                                                       class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;" 
                                                       onclick="return confirm('אתה בטוח שרוצה למחוק את המאפיין \'<?php echo htmlspecialchars($attr['attribute_name']); ?>\'?')">
                                                        מחק
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Main Category Modal -->
    <div id="addMainModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>הוספת קטגוריה ראשית</h3>
            <form method="POST" action="?action=add_main">
                <div class="form-group">
                    <label class="form-label">שם הקטגוריה</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">תיאור</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">סדר תצוגה</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">שמור</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addMainModal')">ביטול</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Sub Category Modal -->
    <div id="addSubModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>הוספת תת קטגוריה</h3>
            <form method="POST" action="?action=add_sub">
                <div class="form-group">
                    <label class="form-label">קטגוריה ראשית</label>
                    <select name="main_category_id" class="form-control" required>
                        <option value="">בחר קטגוריה ראשית</option>
                        <?php foreach ($mainCategories as $main): ?>
                            <option value="<?php echo $main['id']; ?>">
                                <?php echo htmlspecialchars($main['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">שם תת הקטגוריה</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">תיאור</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">סדר תצוגה</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">שמור</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addSubModal')">ביטול</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Technical Attribute Modal -->
    <div id="addAttributeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>הוספת מאפיין טכני</h3>
            
            <div class="alert alert-info" id="subCategoryInfo" style="display: none;">
                <strong>מאפיין עבור:</strong> <span id="subCategoryName"></span>
            </div>
            
            <form method="POST" action="?action=add_attribute">
                <input type="hidden" name="sub_category_id" id="modal_sub_category_id">
                
                <div class="form-group">
                    <label class="form-label">שם המאפיין</label>
                    <input type="text" name="attribute_name" class="form-control" required 
                           placeholder="לדוגמה: גובה מנוף, משקל הרמה, סוג המנוף">
                </div>
                
                <div class="form-group">
                    <label class="form-label">סוג השדה</label>
                    <select name="attribute_type" class="form-control" required onchange="toggleOptions(this)">
                        <option value="">בחר סוג שדה</option>
                        <option value="text">טקסט חופשי</option>
                        <option value="number">מספר</option>
                        <option value="select">רשימה נפתחת</option>
                        <option value="checkbox">תיבת סימון</option>
                        <option value="range">טווח ערכים (מין-מקס)</option>
                    </select>
                </div>
                
                <div class="form-group" id="optionsGroup" style="display: none;">
                    <label class="form-label">אפשרויות (מופרדות בפסיקים)</label>
                    <textarea name="options" class="form-control" rows="3" 
                              placeholder="קטן, בינוני, גדול או 5 טון, 10 טון, 15 טון"></textarea>
                    <small style="color: #666;">הכנס אפשרויות מופרדות בפסיקים</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">יחידת מידה (אופציונלי)</label>
                    <input type="text" name="unit" class="form-control" 
                           placeholder="מטר, טון, ק״ג, שעות">
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_required" value="1">
                        שדה חובה
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-label">סדר תצוגה</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">שמור מאפיין</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addAttributeModal')">ביטול</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSort = { column: -1, direction: 'asc' };
        
        function filterSubCategories() {
            const selectedMainId = document.getElementById('categoryFilter').value;
            if (selectedMainId) {
                window.location.href = '?action=sub_categories&main_id=' + selectedMainId;
            } else {
                window.location.href = '?action=sub_categories';
            }
        }
        
        function searchSubCategories() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.sub-category-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                if (name.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateResultsCount(visibleCount);
            limitResults();
        }
        
        function limitResults() {
            const limit = document.getElementById('limitFilter').value;
            const visibleRows = document.querySelectorAll('.sub-category-row[style=""], .sub-category-row:not([style])');
            
            if (limit === 'all') return;
            
            visibleRows.forEach((row, index) => {
                if (index >= parseInt(limit)) {
                    row.style.display = 'none';
                }
            });
        }
        
        function updateResultsCount(count) {
            document.getElementById('resultsCount').textContent = count;
        }
        
        function sortTable(columnIndex) {
            const table = document.getElementById('subCategoriesTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            if (currentSort.column === columnIndex) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
            }
            currentSort.column = columnIndex;
            
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].textContent.trim();
                const bText = b.cells[columnIndex].textContent.trim();
                
                if (currentSort.direction === 'asc') {
                    return aText.localeCompare(bText, 'he');
                } else {
                    return bText.localeCompare(aText, 'he');
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
            
            document.querySelectorAll('.sort-icon').forEach(icon => {
                icon.textContent = '↕';
            });
            
            const currentIcon = table.querySelector(`th:nth-child(${columnIndex + 1}) .sort-icon`);
            currentIcon.textContent = currentSort.direction === 'asc' ? '↑' : '↓';
        }
        
        function showAddMainModal() {
            document.getElementById('addMainModal').style.display = 'block';
        }
        
        function showAddSubModal() {
            document.getElementById('addSubModal').style.display = 'block';
        }
        
        function showAddAttributeModal(subCategoryId, subCategoryName) {
            document.getElementById('modal_sub_category_id').value = subCategoryId;
            document.getElementById('subCategoryName').textContent = subCategoryName || '';
            document.getElementById('subCategoryInfo').style.display = 'block';
            document.getElementById('addAttributeModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function toggleOptions(selectElement) {
            const optionsGroup = document.getElementById('optionsGroup');
            const needsOptions = ['select', 'checkbox'].includes(selectElement.value);
            optionsGroup.style.display = needsOptions ? 'block' : 'none';
            
            const textarea = optionsGroup.querySelector('textarea');
            const placeholders = {
                'select': 'קטן, בינוני, גדול',
                'checkbox': 'GPS, מזגן, רדיו'
            };
            
            if (textarea && placeholders[selectElement.value]) {
                textarea.placeholder = placeholders[selectElement.value];
            }
        }
        
        // פונקציות מסנן מאפיינים
        function loadSubCategories() {
            const mainId = document.getElementById('mainCategorySelect').value;
            const subSelect = document.getElementById('subCategorySelect');
            
            subSelect.innerHTML = '<option value="">טוען...</option>';
            subSelect.disabled = true;
            
            if (!mainId) {
                subSelect.innerHTML = '<option value="">תחילה בחר קטגוריה ראשית</option>';
                return;
            }
            
            // טעינת תתי קטגוריות
            fetch(`?action=get_sub_categories&main_id=${mainId}`)
                .then(response => response.json())
                .then(data => {
                    subSelect.innerHTML = '<option value="">בחר תת קטגוריה</option>';
                    data.forEach(sub => {
                        subSelect.innerHTML += `<option value="${sub.id}">${sub.name}</option>`;
                    });
                    subSelect.disabled = false;
                })
                .catch(error => {
                    subSelect.innerHTML = '<option value="">שגיאה בטעינה</option>';
                });
        }
        
        function loadAttributes() {
            const subId = document.getElementById('subCategorySelect').value;
            if (subId) {
                window.location.href = `?action=attributes&sub_id=${subId}`;
            }
        }
        
        // אתחול הדף
        document.addEventListener('DOMContentLoaded', function() {
            const visibleRows = document.querySelectorAll('.sub-category-row').length;
            updateResultsCount(visibleRows);
            
            // אם יש main_id ב-URL, טען תתי קטגוריות
            const urlParams = new URLSearchParams(window.location.search);
            const mainId = urlParams.get('main_id');
            if (mainId) {
                document.getElementById('mainCategorySelect').value = mainId;
                loadSubCategories();
            }
        });
    </script>
</body>
</html>