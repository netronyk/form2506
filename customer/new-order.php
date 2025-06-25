<?php
// customer/new-order.php - טופס הזמנה חדשה
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Order.php';

$auth = new Auth();
if (!$auth->checkPermission('customer')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$category = new Category();
$order = new Order();

$mainCategories = $category->getMainCategories();
$workTypes = [];
$subCategories = [];

$message = '';
$error = '';

// טיפול בשליחת טופס
if ($_POST) {
    $uploadedImages = [];
    
    // טיפול בתמונות
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
    
    $orderData = [
        'customer_id' => $currentUser['id'],
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
    
    $result = $order->createOrder($orderData);
    
    if ($result['success']) {
        flash('success', 'ההזמנה נוצרה בהצלחה! מספר הזמנה: ' . $result['order_number']);
        redirect('orders.php');
    } else {
        $error = $result['message'];
    }
    if ($result['success']) {
    $orderId = $result['order_id'];
    
    // שליחת התראת ווטסאפ ללקוח
    require_once __DIR__ . '/../models/Notification.php';
    $notification = new Notification();
    $notification->notifyNewOrder($orderId, $_SESSION['user_id']);
    
    $message = 'הזמנה נוצרה בהצלחה ונשלחה להצעות מחיר';
    redirect('orders.php?success=' . urlencode($message));
}
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הזמנה חדשה - <?php echo SITE_NAME; ?></title>
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
        <h1>יצירת הזמנה חדשה</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="orderForm">
            <!-- פרטי הלקוח -->
            <div class="card">
                <div class="card-header">
                    <h3>פרטי הלקוח</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">סוג לקוח *</label>
                                <select name="customer_type" id="customerType" class="form-control" required onchange="toggleBusinessFields()">
                                    <option value="private">פרטי</option>
                                    <option value="business">עסקי</option>
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
                    
                    <!-- שדות עסקיים -->
                    <div id="businessFields" style="display: none;">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם החברה *</label>
                                    <input type="text" name="company_name" id="companyName" class="form-control" 
                                           placeholder="הכנס שם החברה">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">מספר ח.פ/ע.מ *</label>
                                    <input type="text" name="business_number" id="businessNumber" class="form-control" 
                                           placeholder="512345678" pattern="[0-9]{9}" maxlength="9">
                                    <small class="form-text text-muted">9 ספרות ללא מקפים</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- פרטי ההזמנה -->
            <div class="card">
                <div class="card-header">
                    <h3>פרטי ההזמנה</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">תיאור העבודה *</label>
                        <textarea name="work_description" class="form-control" rows="4" required 
                                  placeholder="תאר את העבודה הנדרשת בפירוט - סוג ההובלה, משקל, גודל וכו'"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">מיקום תחילת העבודה *</label>
                                <input type="text" name="start_location" class="form-control" required 
                                       placeholder="כתובת מלאה כולל עיר">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">מיקום סיום העבודה</label>
                                <input type="text" name="end_location" class="form-control" 
                                       placeholder="כתובת יעד (אם רלוונטי)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- סוג כלי הרכב הנדרש -->
            <div class="card">
                <div class="card-header">
                    <h3>סוג כלי הרכב והעבודה הנדרשת</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">קטגוריה ראשית</label>
                                <select name="main_category_id" id="mainCategory" class="form-control" onchange="loadWorkTypesAndVehicles()">
                                    <option value="">בחר קטגוריה</option>
                                    <?php foreach ($mainCategories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- סוגי עבודות -->
                    <div id="workTypesSection" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">סוג העבודה הנדרשת (ניתן לבחור מספר אפשרויות)</label>
                            <div id="workTypesContainer" class="checkbox-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                <!-- סוגי עבודות יטענו כאן דינמית -->
                            </div>
                        </div>
                    </div>

                    <!-- סוגי כלי רכב -->
                    <div id="vehicleTypesSection" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">סוג כלי הרכב הנדרש (למי שיודע בדיוק מה הוא צריך)</label>
                            <select name="sub_category_id" id="subCategory" class="form-control">
                                <option value="">בחר סוג כלי רכב</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">דרישות מיוחדות</label>
                        <textarea name="special_requirements" class="form-control" rows="3" 
                                  placeholder="פרט דרישות מיוחדות, מגבלות גישה, דרישות ביטוח וכו'"></textarea>
                    </div>
                </div>
            </div>

            <!-- זמנים ותאריכים -->
            <div class="card">
                <div class="card-header">
                    <h3>זמנים ותאריכים</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">תאריך תחילת העבודה *</label>
                                <input type="date" name="work_start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">שעת התחלה</label>
                                <input type="time" name="work_start_time" class="form-control">
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">תאריך סיום (אם רלוונטי)</label>
                                <input type="date" name="work_end_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="form-group">
                                <label class="form-label">שעת סיום</label>
                                <input type="time" name="work_end_time" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">גמישות בזמנים</label>
                                <select name="flexibility" id="flexibilitySelect" class="form-control" onchange="toggleFlexibilityFields()">
                                    <option value="none">ללא גמישות</option>
                                    <option value="hours">גמישות של מספר שעות</option>
                                    <option value="days">גמישות של מספר ימים</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">מועד אחרון לקבלת הצעות</label>
                                <input type="datetime-local" name="quote_deadline" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- שדות גמישות -->
                    <div id="flexibilityDetails" style="display: none;">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label" id="flexibilityBeforeLabel">כמות שעות לפני התאריך</label>
                                    <input type="number" name="flexibility_before" id="flexibilityBefore" class="form-control" min="1" placeholder="הכנס מספר">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label" id="flexibilityAfterLabel">כמות שעות אחרי התאריך</label>
                                    <input type="number" name="flexibility_after" id="flexibilityAfter" class="form-control" min="1" placeholder="הכנס מספר">
                                </div>
                            </div>
                        </div>
                        <small class="form-text text-muted" id="flexibilityHelp">
                            לדוגמא: אם תכניסו 2 שעות לפני ו-3 שעות אחרי, ניתן יהיה לבצע את העבודה 2 שעות לפני הזמן המבוקש עד 3 שעות אחריו
                        </small>
                    </div>
                </div>
            </div>

            <!-- תמונות ומחיר -->
            <div class="card">
                <div class="card-header">
                    <h3>תמונות ומחיר</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div class="form-group">
                                <label class="form-label">תמונות של מיקום הביצוע (עד 10 תמונות)</label>
                                
                                <!-- כפתור הוספת תמונות -->
                                <div style="margin-bottom: 1rem;">
                                    <button type="button" id="addImageBtn" class="btn btn-outline" onclick="triggerFileInput()">
                                        📷 הוסף תמונה
                                    </button>
                                    <input type="file" id="imageInput" name="images[]" accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
                                </div>
                                
                                <!-- תצוגת התמונות -->
                                <div id="imagePreviewContainer" style="display: flex; flex-wrap: wrap; gap: 10px; min-height: 60px; border: 1px dashed #ddd; padding: 10px; border-radius: 5px;">
                                    <div id="emptyState" style="color: #999; text-align: center; width: 100%; padding: 20px;">
                                        לחץ על "הוסף תמונה" להעלאת תמונות
                                    </div>
                                </div>
                                
                                <small style="color: var(--dark-gray);">תמונות יעזרו לבעלי הרכב להבין את הדרישות ולתת הצעת מחיר מדויקת</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">תקציב מקסימלי</label>
                                <select name="budget_type" id="budgetType" class="form-control" onchange="toggleBudgetFields()">
                                    <option value="">ללא הגבלה</option>
                                    <option value="total">מחיר לכל העבודה</option>
                                    <option value="hourly">מחיר לפי שעה</option>
                                    <option value="daily">מחיר לפי יום</option>
                                </select>
                            </div>
                            
                            <div id="budgetAmount" style="display: none;">
                                <div class="form-group">
                                    <label class="form-label" id="budgetLabel">סכום מקסימלי (₪)</label>
                                    <input type="number" name="max_budget" class="form-control" placeholder="הכנס סכום">
                                    <small class="form-text text-muted">המחיר בש"ח כולל מע"מ</small>
                                </div>
                            </div>
                            
                            <small style="color: var(--dark-gray);">לא חובה - יעזור לקבל הצעות מתאימות</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- כפתורי פעולה -->
            <div class="card">
                <div class="card-body text-center">
                    <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem;">
                        שלח הזמנה ✈️
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary" style="padding: 1rem 3rem; margin-right: 1rem;">
                        ביטול
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        // שדות עסקיים
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
                companyName.value = '';
                businessNumber.value = '';
            }
        }

        // סוגי עבודות וכלי רכב
        function loadWorkTypesAndVehicles() {
            const mainCategoryId = document.getElementById('mainCategory').value;
            const workTypesSection = document.getElementById('workTypesSection');
            const vehicleTypesSection = document.getElementById('vehicleTypesSection');
            const workTypesContainer = document.getElementById('workTypesContainer');
            const subCategorySelect = document.getElementById('subCategory');
            
            // איפוס והסתרה
            workTypesSection.style.display = 'none';
            vehicleTypesSection.style.display = 'none';
            workTypesContainer.innerHTML = '';
            subCategorySelect.innerHTML = '<option value="">בחר סוג כלי רכב</option>';
            
            if (!mainCategoryId) return;
            
            // טעינת סוגי עבודות וכלי רכב
            fetch(`../api/categories.php?action=get_sub_categories&main_id=${mainCategoryId}`)
                .then(response => response.json())
                .then(data => {
                    // טעינת סוגי עבודות
                    if (data.work_types && data.work_types.length > 0) {
                        workTypesSection.style.display = 'block';
                        data.work_types.forEach(work => {
                            const div = document.createElement('div');
                            div.innerHTML = `
                                <label style="display: flex; align-items: center; font-weight: normal; margin-bottom: 5px;">
                                    <input type="checkbox" name="work_types[]" value="${work.id}" style="margin-left: 8px;">
                                    ${work.work_name}
                                </label>
                            `;
                            workTypesContainer.appendChild(div);
                        });
                    }
                    
                    // טעינת סוגי כלי רכב
                    if (data.sub_categories && data.sub_categories.length > 0) {
                        vehicleTypesSection.style.display = 'block';
                        data.sub_categories.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = sub.name;
                            subCategorySelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // גמישות זמנים
        function toggleFlexibilityFields() {
            const flexibilitySelect = document.getElementById('flexibilitySelect');
            const flexibilityDetails = document.getElementById('flexibilityDetails');
            const beforeLabel = document.getElementById('flexibilityBeforeLabel');
            const afterLabel = document.getElementById('flexibilityAfterLabel');
            const beforeInput = document.getElementById('flexibilityBefore');
            const afterInput = document.getElementById('flexibilityAfter');
            const helpText = document.getElementById('flexibilityHelp');
            
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
                    helpText.textContent = 'לדוגמא: אם תכניסו 2 שעות לפני ו-3 שעות אחרי, ניתן יהיה לבצע את העבודה 2 שעות לפני הזמן המבוקש עד 3 שעות אחריו';
                } else if (selectedValue === 'days') {
                    beforeLabel.textContent = 'כמות ימים לפני התאריך';
                    afterLabel.textContent = 'כמות ימים אחרי התאריך';
                    helpText.textContent = 'לדוגמא: אם תכניסו 1 יום לפני ו-2 ימים אחרי, ניתן יהיה לבצע את העבודה יום לפני התאריך המבוקש עד יומיים אחריו';
                }
            }
        }

        // שדות תקציב
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

        // תמונות
        let imageFiles = [];
        let imageCounter = 0;

        function triggerFileInput() {
            if (imageFiles.length >= 10) {
                alert('ניתן להעלות עד 10 תמונות');
                return;
            }
            document.getElementById('imageInput').click();
        }

        function handleImageUpload(event) {
            const files = Array.from(event.target.files);
            
            files.forEach(file => {
                if (imageFiles.length >= 10) {
                    alert('ניתן להעלות עד 10 תמונות');
                    return;
                }
                
                if (file.type.startsWith('image/')) {
                    const imageId = 'img_' + (++imageCounter);
                    imageFiles.push({id: imageId, file: file});
                    displayImagePreview(file, imageId);
                }
            });
            
            updateEmptyState();
            updateFileInput();
            event.target.value = '';
        }

        function displayImagePreview(file, imageId) {
            const container = document.getElementById('imagePreviewContainer');
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imageDiv = document.createElement('div');
                imageDiv.style.cssText = 'position: relative; display: inline-block;';
                imageDiv.id = imageId;
                
                imageDiv.innerHTML = `
                    <img src="${e.target.result}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd;">
                    <button type="button" onclick="removeImage('${imageId}')" 
                            style="position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer;">
                        ×
                    </button>
                `;
                
                container.appendChild(imageDiv);
            };
            
            reader.readAsDataURL(file);
        }

        function removeImage(imageId) {
            imageFiles = imageFiles.filter(img => img.id !== imageId);
            document.getElementById(imageId).remove();
            updateEmptyState();
            updateFileInput();
        }

        function updateEmptyState() {
            const emptyState = document.getElementById('emptyState');
            if (imageFiles.length === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        function updateFileInput() {
            const container = document.getElementById('imagePreviewContainer');
            
            const existingInputs = container.querySelectorAll('input[type="file"]');
            existingInputs.forEach(input => input.remove());
            
            imageFiles.forEach((imageObj, index) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.name = 'images[]';
                input.style.display = 'none';
                input.files = createFileList([imageObj.file]);
                container.appendChild(input);
            });
        }

        function createFileList(files) {
            const dt = new DataTransfer();
            files.forEach(file => dt.items.add(file));
            return dt.files;
        }

        // טעינה ראשונית
        document.addEventListener('DOMContentLoaded', function() {
            toggleBusinessFields();
            toggleFlexibilityFields();
            toggleBudgetFields();
            updateEmptyState();
            
            // הגדרת תאריך מינימלי להיום
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="work_start_date"]').min = today;
            document.querySelector('input[name="work_end_date"]').min = today;
        });
        // הוסף בסוף הסקריפט ב-new-order.php
function validateQuoteDeadline() {
    const workStartDate = document.querySelector('input[name="work_start_date"]').value;
    const quoteDeadline = document.querySelector('input[name="quote_deadline"]').value;
    
    if (workStartDate && quoteDeadline) {
        const startDate = new Date(workStartDate);
        const deadlineDate = new Date(quoteDeadline);
        
        if (deadlineDate >= startDate) {
            alert('מועד אחרון לקבלת הצעות חייב להיות לפני תאריך תחילת העבודה');
            document.querySelector('input[name="quote_deadline"]').value = '';
        }
    }
}

// הוסף event listeners
document.querySelector('input[name="work_start_date"]').addEventListener('change', validateQuoteDeadline);
document.querySelector('input[name="quote_deadline"]').addEventListener('change', validateQuoteDeadline);
    </script>
    
</body>
</html>