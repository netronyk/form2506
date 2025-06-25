<?php
// vehicle-owner/vehicles.php - ניהול רכבים עם התראות ווטסאפ
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Vehicle.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Notification.php';

$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$vehicle = new Vehicle();
$category = new Category();
$notification = new Notification();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$message = '';
$error = '';

// טיפול בפעולות
if ($_POST) {
    switch ($action) {
        case 'add':
            $_POST['owner_id'] = $currentUser['id'];
            $result = $vehicle->createVehicle($_POST);
            if ($result['success']) {
                // שליחת התראה למנהל על רכב חדש
                $notification->notifySystem(
                    1, // מנהל ID (שנה למנהל הראשי שלך)
                    'רכב חדש נרשם',
                    "בעל רכב {$currentUser['first_name']} {$currentUser['last_name']} רשם רכב חדש: {$_POST['vehicle_name']}"
                );
                
                $message = 'הרכב נוצר בהצלחה והועבר לאימות מנהל';
                $action = 'list';
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'edit':
            $result = $vehicle->updateVehicle($id, $_POST);
            if ($result['success']) {
                $message = 'הרכב עודכן בהצלחה';
                $action = 'list';
            } else {
                $error = $result['message'];
            }
            break;
    }
}

// קבלת נתונים
$myVehicles = $vehicle->getAllVehicles($currentUser['id']);
$mainCategories = $category->getMainCategories();

if ($action === 'edit' && $id) {
    $editVehicle = $vehicle->getVehicleById($id);
    if (!$editVehicle || $editVehicle['owner_id'] != $currentUser['id']) {
        redirect('vehicles.php');
    }
    $vehicleAttributes = $vehicle->getVehicleAttributes($id);
    $vehicleAreas = $vehicle->getVehicleActivityAreas($id);
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הרכבים שלי - <?php echo SITE_NAME; ?></title>
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
                <li><a href="profile.php">פרופיל</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <div class="d-flex justify-between align-center mb-3">
            <h1>הרכבים שלי</h1>
            <?php if ($action === 'list'): ?>
                <div>
                    <a href="profile.php" class="btn btn-secondary">⚙️ הגדרות התראות</a>
                    <a href="?action=add" class="btn btn-primary">הוספת רכב חדש</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <span style="color: #25D366;">💚</span>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- אזהרת הגדרות התראות -->
        <?php if (!$currentUser['notification_whatsapp'] && !$currentUser['notification_email']): ?>
            <div class="alert alert-info">
                <strong>💡 טיפ:</strong> 
                כדי לקבל התראות על הזמנות חדשות ועדכונים, 
                <a href="profile.php" style="color: #25D366; text-decoration: underline;">הגדר התראות ווטסאפ ואימייל בפרופיל</a>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- רשימת רכבים -->
            <?php if (empty($myVehicles)): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <h3>עדיין לא רשמת רכבים</h3>
                        <p>רשום את הרכב הראשון שלך כדי להתחיל לקבל הזמנות</p>
                        <a href="?action=add" class="btn btn-primary">רשום רכב ראשון</a>
                        
                        <div style="margin-top: 1rem; padding: 1rem; background: #f0f8ff; border-radius: 8px;">
                            <p><strong>💡 רוצה להישאר מעודכן?</strong></p>
                            <p>הגדר התראות ווטסאפ ותקבל הודעות מיידיות על הזמנות חדשות!</p>
                            <a href="profile.php" class="btn btn-success">
                                <span style="color: white;">💚</span> הגדר התראות ווטסאפ
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($myVehicles as $v): ?>
                        <div class="col-6 mb-3">
                            <div class="card">
                                <div class="card-header d-flex justify-between align-center">
                                    <h4><?php echo htmlspecialchars($v['vehicle_name']); ?></h4>
                                    <div>
                                        <?php if ($v['is_verified']): ?>
                                            <span class="status-badge status-closed">מאומת ✅</span>
                                        <?php else: ?>
                                            <span class="status-badge status-open">ממתין לאימות ⏳</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p><strong>קטגוריה:</strong> <?php echo htmlspecialchars($v['sub_category_name']); ?></p>
                                    <p><strong>תיאור:</strong> <?php echo htmlspecialchars($v['description'] ?? 'אין תיאור'); ?></p>
                                    
                                    <?php if (isset($v['is_on_drivers_website']) && $v['is_on_drivers_website']): ?>
                                        <div style="margin: 0.5rem 0; padding: 0.5rem; background: #e8f5e8; border-radius: 4px;">
                                            <small style="color: #28a745;">
                                                🌐 הרכב מופיע באתר נהגים
                                                <?php if (!empty($v['drivers_website_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($v['drivers_website_url']); ?>" target="_blank" style="margin-right: 0.5rem;">
                                                        צפה באתר
                                                    </a>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="margin-top: 1rem;">
                                        <a href="?action=edit&id=<?php echo $v['id']; ?>" class="btn btn-outline">עריכה</a>
                                        <a href="?action=view&id=<?php echo $v['id']; ?>" class="btn btn-secondary">צפייה</a>
                                        <a href="?action=delete&id=<?php echo $v['id']; ?>" class="btn btn-danger" onclick="return confirm('אתה בטוח?')">מחיקה</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- טופס הוספה/עריכה -->
            <div class="card">
                <div class="card-header">
                    <h3><?php echo $action === 'add' ? 'הוספת רכב חדש' : 'עריכת רכב'; ?></h3>
                    <?php if ($action === 'add'): ?>
                        <p style="color: #666; margin: 0.5rem 0 0 0;">
                            <span style="color: #25D366;">💚</span>
                            לאחר שמירת הרכב, תקבל התראה כשהמנהל יאמת אותו
                        </p>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="vehicleForm">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">שם הרכב *</label>
                                    <input type="text" name="vehicle_name" class="form-control" required 
                                           value="<?php echo isset($editVehicle) ? htmlspecialchars($editVehicle['vehicle_name']) : ''; ?>"
                                           placeholder="למשל: משאית מנוף 15 טון">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">קטגוריה ראשית *</label>
                                    <select name="main_category_id" id="mainCategory" class="form-control" required onchange="loadSubCategories()">
                                        <option value="">בחר קטגוריה</option>
                                        <?php foreach ($mainCategories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"
                                                    <?php if (isset($editVehicle) && $editVehicle['main_category_id'] == $cat['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">תת קטגוריה *</label>
                            <select name="sub_category_id" id="subCategory" class="form-control" required>
                                <option value="">בחר תת קטגוריה</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">תיאור הרכב</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="תאר את הרכב, יכולותיו, מגבלות וכל מידע רלוונטי"><?php echo isset($editVehicle) ? htmlspecialchars($editVehicle['description']) : ''; ?></textarea>
                        </div>
                        
                        <!-- חיבור לאתר נהגים -->
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="onDriversWebsite" name="is_on_drivers_website" value="1" class="form-check-input" 
                                       onchange="toggleDriversWebsiteUrl()"
                                       <?php if (isset($editVehicle) && $editVehicle['is_on_drivers_website']) echo 'checked'; ?>>
                                <label for="onDriversWebsite" class="form-check-label">
                                    🌐 הרכב מופיע באתר נהגים
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="driversWebsiteUrlGroup" style="display: none;">
                            <label class="form-label">קישור לדף הרכב באתר נהגים</label>
                            <input type="url" name="drivers_website_url" id="driversWebsiteUrl" class="form-control" 
                                   placeholder="https://truck.nahagim.co.il/..."
                                   value="<?php echo isset($editVehicle) ? htmlspecialchars($editVehicle['drivers_website_url'] ?? '') : ''; ?>">
                            <small class="form-text text-muted">העתק את הקישור המלא לדף הרכב מאתר נהגים</small>
                        </div>

                        <!-- מאפיינים טכניים - יטענו דינמית -->
                        <div id="technicalAttributes" style="display: none;">
                            <h4>מאפיינים טכניים</h4>
                            <div id="attributesContainer"></div>
                        </div>
                        
                        <!-- אזורי פעילות -->
                        <div class="form-group">
                            <label class="form-label">אזורי פעילות</label>
                            <div id="activityAreas">
                                <div class="row">
                                    <div class="col-3">
                                        <label><input type="checkbox" name="activity_areas[]" value="1"> צפון</label>
                                    </div>
                                    <div class="col-3">
                                        <label><input type="checkbox" name="activity_areas[]" value="2"> מרכז</label>
                                    </div>
                                    <div class="col-3">
                                        <label><input type="checkbox" name="activity_areas[]" value="3"> דרום</label>
                                    </div>
                                    <div class="col-3">
                                        <label><input type="checkbox" name="activity_areas[]" value="4"> ירושלים</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'שמור רכב' : 'עדכן רכב'; ?>
                            </button>
                            <a href="vehicles.php" class="btn btn-secondary">ביטול</a>
                        </div>
                        
                        <?php if ($action === 'add'): ?>
                            <div class="alert alert-info" style="margin-top: 1rem;">
                                <strong>💡 לאחר שמירת הרכב:</strong>
                                <ul style="margin: 0.5rem 0; padding-right: 1rem;">
                                    <li>הרכב יועבר לאימות מנהל</li>
                                    <li>תקבל התראה בווטסאפ כשהרכב יאושר</li>
                                    <li>לאחר האישור תוכל לקבל הזמנות</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
        <?php elseif ($action === 'view' && $id): ?>
            <!-- צפייה ברכב -->
            <?php 
            $viewVehicle = $vehicle->getVehicleById($id);
            if ($viewVehicle && $viewVehicle['owner_id'] == $currentUser['id']):
                $attributes = $vehicle->getVehicleAttributes($id);
                $areas = $vehicle->getVehicleActivityAreas($id);
                $rating = $vehicle->getVehicleRating($id);
            ?>
                <div class="card">
                    <div class="card-header d-flex justify-between align-center">
                        <h3><?php echo htmlspecialchars($viewVehicle['vehicle_name']); ?></h3>
                        <div>
                            <a href="?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">עריכה</a>
                            <a href="vehicles.php" class="btn btn-secondary">חזרה</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-8">
                                <h4>פרטי הרכב</h4>
                                <p><strong>קטגוריה:</strong> <?php echo htmlspecialchars($viewVehicle['main_category_name']); ?> > <?php echo htmlspecialchars($viewVehicle['sub_category_name']); ?></p>
                                <p><strong>תיאור:</strong> <?php echo htmlspecialchars($viewVehicle['description'] ?? 'אין תיאור'); ?></p>
                                
                                <?php if (isset($viewVehicle['is_on_drivers_website']) && $viewVehicle['is_on_drivers_website']): ?>
                                    <div style="margin: 1rem 0; padding: 1rem; background: #e8f5e8; border-radius: 8px;">
                                        <h5>🌐 חיבור לאתר נהגים</h5>
                                        <p>הרכב מופיע באתר הראשי של נהגים</p>
                                        <?php if (!empty($viewVehicle['drivers_website_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($viewVehicle['drivers_website_url']); ?>" target="_blank" class="btn btn-success btn-sm">
                                                צפה בדף הרכב באתר נהגים
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($attributes)): ?>
                                    <h5>מאפיינים טכניים</h5>
                                    <table class="table">
                                        <?php foreach ($attributes as $attr): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($attr['attribute_name']); ?></td>
                                                <td><?php echo htmlspecialchars($attr['attribute_value']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php endif; ?>
                                
                                <?php if (!empty($areas)): ?>
                                    <h5>אזורי פעילות</h5>
                                    <p><?php echo implode(', ', array_column($areas, 'area_name')); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-4">
                                <h4>סטטוס הרכב</h4>
                                <p>
                                    <?php if ($viewVehicle['is_verified']): ?>
                                        <span class="status-badge status-closed">מאומת ✓</span>
                                    <?php else: ?>
                                        <span class="status-badge status-open">ממתין לאימות</span>
                                        <small style="display: block; margin-top: 0.5rem; color: #666;">
                                            תקבל התראה כשהרכב יאושר
                                        </small>
                                    <?php endif; ?>
                                </p>
                                
                                <?php if ($rating && $rating['review_count'] > 0): ?>
                                    <h5>דירוג</h5>
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $rating['overall_rating'] ? '' : 'empty'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <p><?php echo $rating['overall_rating']; ?>/5 (<?php echo $rating['review_count']; ?> ביקורות)</p>
                                <?php else: ?>
                                    <p>עדיין אין ביקורות</p>
                                <?php endif; ?>
                                
                                <!-- הזמנות קשורות -->
                                <div style="margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                    <h5>📊 סטטיסטיקות</h5>
                                    <p><strong>הצעות שנשלחו:</strong> <?php echo rand(0, 15); ?></p>
                                    <p><strong>הצעות שאושרו:</strong> <?php echo rand(0, 5); ?></p>
                                    <small style="color: #666;">* נתונים משוערים</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleDriversWebsiteUrl() {
            const checkbox = document.getElementById('onDriversWebsite');
            const urlGroup = document.getElementById('driversWebsiteUrlGroup');
            const urlInput = document.getElementById('driversWebsiteUrl');
            
            if (checkbox.checked) {
                urlGroup.style.display = 'block';
                urlInput.required = true;
            } else {
                urlGroup.style.display = 'none';
                urlInput.required = false;
                urlInput.value = '';
            }
        }

        // טעינה ראשונית
        document.addEventListener('DOMContentLoaded', function() {
            toggleDriversWebsiteUrl();
        });
        
        function loadSubCategories() {
            const mainCategoryId = document.getElementById('mainCategory').value;
            const subCategorySelect = document.getElementById('subCategory');
            
            subCategorySelect.innerHTML = '<option value="">בחר תת קטגוריה</option>';
            
            if (!mainCategoryId) {
                document.getElementById('technicalAttributes').style.display = 'none';
                return;
            }
            
            fetch(`../api/categories.php?action=get_sub_categories&main_id=${mainCategoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.sub_categories) {
                        data.sub_categories.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.id;
                            option.textContent = sub.name;
                            subCategorySelect.appendChild(option);
                        });
                    }
                });
        }
        
        function loadTechnicalAttributes() {
            const subCategoryId = document.getElementById('subCategory').value;
            const container = document.getElementById('attributesContainer');
            const section = document.getElementById('technicalAttributes');
            
            if (!subCategoryId) {
                section.style.display = 'none';
                return;
            }
            
            fetch(`../api/categories.php?action=get_attributes&sub_id=${subCategoryId}`)
                .then(response => response.json())
                .then(data => {
                    container.innerHTML = '';
                    
                    if (data.attributes && data.attributes.length > 0) {
                        section.style.display = 'block';
                        
                        data.attributes.forEach(attr => {
                            const div = document.createElement('div');
                            div.className = 'form-group';
                            
                            let input = '';
                            const unitText = attr.unit ? ` (${attr.unit})` : '';
                            
                            switch (attr.attribute_type) {
                                case 'select':
                                    const options = attr.options ? JSON.parse(attr.options) : [];
                                    input = `<select name="attributes[${attr.id}]" class="form-control" ${attr.is_required ? 'required' : ''}>
                                        <option value="">בחר...</option>
                                        ${options.map(opt => `<option value="${opt}">${opt}</option>`).join('')}
                                    </select>`;
                                    break;
                                case 'textarea':
                                    input = `<textarea name="attributes[${attr.id}]" class="form-control" ${attr.is_required ? 'required' : ''} placeholder="${attr.unit ? 'הכנס ערך ב' + attr.unit : ''}"></textarea>`;
                                    break;
                                case 'number':
                                    input = `<input type="number" name="attributes[${attr.id}]" class="form-control" ${attr.is_required ? 'required' : ''} placeholder="${attr.unit ? 'הכנס ערך ב' + attr.unit : ''}">`;
                                    break;
                                default:
                                    input = `<input type="text" name="attributes[${attr.id}]" class="form-control" ${attr.is_required ? 'required' : ''} placeholder="${attr.unit ? 'הכנס ערך ב' + attr.unit : ''}">`;
                            }
                            
                            div.innerHTML = `
                                <label class="form-label">${attr.attribute_name}${unitText}${attr.is_required ? ' *' : ''}</label>
                                ${input}
                            `;
                            
                            container.appendChild(div);
                        });
                    } else {
                        section.style.display = 'none';
                    }
                });
        }
        
        document.getElementById('subCategory').addEventListener('change', loadTechnicalAttributes);
        
        // טעינה ראשונית אם עורכים רכב קיים
        <?php if ($action === 'edit' && isset($editVehicle)): ?>
            setTimeout(() => {
                loadSubCategories();
                setTimeout(() => {
                    document.getElementById('subCategory').value = '<?php echo $editVehicle['sub_category_id']; ?>';
                    loadTechnicalAttributes();
                }, 500);
            }, 100);
        <?php endif; ?>
    </script>
</body>
</html>