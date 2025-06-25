<?php
// vehicle-owner/upgrade.php - שדרוג לפרימיום
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    redirect('../login.php');
}

$currentUser = $auth->getCurrentUser();
$db = new Database();

// אם כבר פרימיום - הפניה
if ($auth->isPremium()) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

// טיפול בבקשת שדרוג (דמו - בייצור יהיה אינטגרציה עם מערכת תשלומים)
if ($_POST && isset($_POST['upgrade_action'])) {
    try {
        $months = (int)($_POST['subscription_months'] ?? 12); // ברירת מחדל 12 חודשים
        
        // בדמו - פשוט נפעיל את הפרימיום
        $result = $auth->activatePremium($currentUser['id'], $months);
        
        if ($result['success']) {
            // יצירת רישום תשלום דמו רק אם הטבלה קיימת
            try {
                // בדיקה אם טבלת payments קיימת
                $tableExists = $db->fetchOne("SHOW TABLES LIKE 'payments'");
                
                if ($tableExists) {
                    $db->insert('payments', [
                        'user_id' => $currentUser['id'],
                        'payment_type' => 'subscription',
                        'amount' => SUBSCRIPTION_PRICE * $months,
                        'payment_method' => 'demo',
                        'transaction_id' => 'DEMO_' . uniqid(),
                        'payment_status' => 'completed',
                        'subscription_months' => $months
                    ]);
                }
            } catch (Exception $e) {
                // אם יש בעיה עם טבלת payments, נמשיך בלי לשמור את הרישום
                error_log("Payment table error: " . $e->getMessage());
            }
            
            flash('success', 'מנוי פרימיום הופעל בהצלחה! תוכל כעת לשלוח הצעות מחיר ולראות פרטי לקוחות');
            redirect('dashboard.php');
        } else {
            $error = $result['message'];
        }
    } catch (Exception $e) {
        $error = 'שגיאה במערכת: ' . $e->getMessage();
        error_log("Upgrade error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>שדרוג לפרימיום - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .pricing-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 2rem;
            margin: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .pricing-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(255, 122, 0, 0.2);
            transform: translateY(-5px);
        }
        
        .pricing-card.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #fff8f0 0%, #ffe8d1 100%);
            box-shadow: 0 6px 20px rgba(255, 122, 0, 0.3);
        }
        
        .pricing-card.popular {
            border-color: var(--primary-color);
            position: relative;
        }
        
        .pricing-card.popular::before {
            content: "🏆 הכי פופולרי";
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .price {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 1rem 0;
        }
        
        .price-monthly {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .savings {
            background: #d4edda;
            color: #155724;
            padding: 0.5rem;
            border-radius: 5px;
            font-weight: bold;
            margin: 1rem 0;
        }
        
        .feature-list {
            text-align: right;
            margin: 2rem 0;
        }
        
        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .upgrade-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1.2rem;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upgrade-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
                <li><a href="orders.php">הזמנות זמינות</a></li>
                <li><a href="../logout.php">התנתקות</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 2rem;">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Hero Section -->
        <div class="card" style="background: linear-gradient(135deg, var(--primary-color), #ff9533); color: white; text-align: center; margin-bottom: 2rem;">
            <div class="card-body" style="padding: 3rem 2rem;">
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">🚀 שדרג לפרימיום</h1>
                <p style="font-size: 1.3rem; margin-bottom: 2rem;">קבל גישה מלאה למערכת וההזדמנות להרווח יותר</p>
                <div style="font-size: 2rem; font-weight: bold;">
                    החל מ-<?php echo format_price(SUBSCRIPTION_PRICE); ?> לחודש
                </div>
                <p style="margin-top: 0.5rem; opacity: 0.9;">כולל מע"מ • ביטול בכל עת</p>
            </div>
        </div>

        <!-- תוכניות מחיר -->
        <div class="card">
            <div class="card-header">
                <h3 style="text-align: center;">בחר את התוכנית המתאימה לך</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="upgradeForm">
                    <div class="row">
                        <!-- תוכנית חודשית -->
                        <div class="col-3">
                            <div class="pricing-card" onclick="selectPlan(1, 69)">
                                <h4>חודשי</h4>
                                <div class="price">69₪</div>
                                <div class="price-monthly">לחודש</div>
                                <ul class="feature-list">
                                    <li>✅ גישה לפרטי לקוחות</li>
                                    <li>✅ שליחת הצעות מחיר</li>
                                    <li>✅ התראות מיידיות</li>
                                    <li>✅ תמיכה מועדפת</li>
                                </ul>
                                <button type="button" class="upgrade-btn btn-outline">בחר תוכנית</button>
                            </div>
                        </div>

                        <!-- תוכנית רבעונית -->
                        <div class="col-3">
                            <div class="pricing-card" onclick="selectPlan(3, 199)">
                                <h4>רבעוני</h4>
                                <div class="price">199₪</div>
                                <div class="price-monthly">66.3₪ לחודש</div>
                                <div class="savings">חסכון של 8₪!</div>
                                <ul class="feature-list">
                                    <li>✅ כל יתרונות החודשי</li>
                                    <li>✅ דירוג מועדף</li>
                                    <li>✅ סטטיסטיקות מתקדמות</li>
                                    <li>✅ יציבות 3 חודשים</li>
                                </ul>
                                <button type="button" class="upgrade-btn btn-primary">בחר תוכנית</button>
                            </div>
                        </div>

                        <!-- תוכנית חצי שנתית -->
                        <div class="col-3">
                            <div class="pricing-card" onclick="selectPlan(6, 389)">
                                <h4>חצי שנתי</h4>
                                <div class="price">389₪</div>
                                <div class="price-monthly">64.8₪ לחודש</div>
                                <div class="savings">חסכון של 25₪!</div>
                                <ul class="feature-list">
                                    <li>✅ כל יתרונות הרבעוני</li>
                                    <li>✅ עדיפות בחיפוש</li>
                                    <li>✅ ייעוץ עסקי</li>
                                    <li>✅ הנחות מיוחדות</li>
                                </ul>
                                <button type="button" class="upgrade-btn btn-secondary">בחר תוכנית</button>
                            </div>
                        </div>

                        <!-- תוכנית שנתית -->
                        <div class="col-3">
                            <div class="pricing-card popular" onclick="selectPlan(12, 749)">
                                <h4>שנתי</h4>
                                <div class="price">749₪</div>
                                <div class="price-monthly">62.4₪ לחודש</div>
                                <div class="savings">חסכון של 79₪!</div>
                                <ul class="feature-list">
                                    <li>✅ כל היתרונות</li>
                                    <li>✅ הנחה מקסימלית</li>
                                    <li>✅ ייעוץ אישי</li>
                                    <li>✅ ערך מוסף מיוחד</li>
                                </ul>
                                <button type="button" class="upgrade-btn btn-success">בחר תוכנית</button>
                            </div>
                        </div>
                    </div>

                    <!-- מידע על התוכנית שנבחרה -->
                    <div id="selectedPlanInfo" style="display: none; background: #f8f9fa; padding: 2rem; border-radius: 10px; margin-top: 2rem; text-align: center;">
                        <h4>תוכנית נבחרה: <span id="selectedPlanName"></span></h4>
                        <p>מחיר: <strong id="selectedPlanPrice"></strong></p>
                        <p>ממוצע חודשי: <strong id="selectedPlanMonthly"></strong></p>
                        <div id="selectedPlanSavings" style="color: #28a745; font-weight: bold; margin: 1rem 0;"></div>
                        
                        <input type="hidden" name="subscription_months" id="selectedMonths">
                        <input type="hidden" name="upgrade_action" value="demo_upgrade">
                        
                        <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.2rem;">
                            🚀 הפעל מנוי פרימיום עכשיו
                        </button>
                        
                        <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                            * זהו מצב דמו - המנוי יופעל מיד ללא חיוב אמיתי<br>
                            * במערכת הייצור תהיה אינטגרציה עם מערכת תשלומים
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <!-- יתרונות פרימיום -->
        <div class="row">
            <div class="col-8">
                <div class="card">
                    <div class="card-header">
                        <h3>מה מקבלים חברי פרימיום?</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div style="padding: 1rem; border-right: 4px solid var(--primary-color); margin-bottom: 2rem;">
                                    <h4 style="color: var(--primary-color);">👥 פרטי לקוחות מלאים</h4>
                                    <p>צפה בטלפון, אימייל ופרטי קשר של הלקוחות בכל הזמנה</p>
                                </div>
                                
                                <div style="padding: 1rem; border-right: 4px solid var(--success); margin-bottom: 2rem;">
                                    <h4 style="color: var(--success);">💰 שליחת הצעות מחיר</h4>
                                    <p>שלח הצעות מחיר ישירות דרך המערכת לכל הזמנה רלוונטית</p>
                                </div>
                                
                                <div style="padding: 1rem; border-right: 4px solid var(--secondary-color); margin-bottom: 2rem;">
                                    <h4 style="color: var(--secondary-color);">🔔 התראות מיידיות</h4>
                                    <p>קבל התראה מיד כשיש הזמנה חדשה במחוז שלך</p>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div style="padding: 1rem; border-right: 4px solid var(--warning); margin-bottom: 2rem;">
                                    <h4 style="color: var(--warning);">⭐ דירוג מועדף</h4>
                                    <p>חברי פרימיום מופיעים ראשונים בתוצאות החיפוש</p>
                                </div>
                                
                                <div style="padding: 1rem; border-right: 4px solid var(--danger); margin-bottom: 2rem;">
                                    <h4 style="color: var(--danger);">📊 סטטיסטיקות מתקדמות</h4>
                                    <p>מעקב אחר ביצועים, הצעות שזכו וניתוחי רווחיות</p>
                                </div>
                                
                                <div style="padding: 1rem; border-right: 4px solid #9c27b0; margin-bottom: 2rem;">
                                    <h4 style="color: #9c27b0;">🎯 תמיכה מועדפת</h4>
                                    <p>תמיכה טכנית מועדפת ותגובה מהירה לפניות</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- מה אומרים הלקוחות -->
            <div class="col-4">
                <div class="card">
                    <div class="card-header">
                        <h4>מה אומרים חברי פרימיום?</h4>
                    </div>
                    <div class="card-body">
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem;">
                            <div class="rating">
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                            </div>
                            <p style="margin: 0.5rem 0;">"מאז שעברתי לפרימיום אני מקבל פי 3 יותר הזמנות. השקעה משתלמת!"</p>
                            <small><strong>דני, בעל משאית מנוף</strong></small>
                        </div>
                        
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem;">
                            <div class="rating">
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                            </div>
                            <p style="margin: 0.5rem 0;">"הגישה לפרטי הלקוחות חוסכת לי המון זמן. ממליץ!"</p>
                            <small><strong>רונית, בעלת אוטובוס</strong></small>
                        </div>
                        
                        <div>
                            <div class="rating">
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                                <span class="star">★</span>
                            </div>
                            <p style="margin: 0.5rem 0;">"המערכת שינתה לי את העסק. יותר הזמנות, יותר רווח."</p>
                            <small><strong>אבי, בעל מחפרון</strong></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- חישוב החזר השקעה -->
        <div class="card">
            <div class="card-header">
                <h3>חישוב החזר השקעה</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4 text-center">
                        <div style="padding: 2rem; background: #f8f9fa; border-radius: 8px;">
                            <h4 style="color: var(--primary-color);">השקעה חודשית</h4>
                            <div style="font-size: 2rem; font-weight: bold;"><?php echo format_price(SUBSCRIPTION_PRICE); ?></div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div style="padding: 2rem; background: #e8f5e8; border-radius: 8px;">
                            <h4 style="color: var(--success);">הזמנה נוספת אחת</h4>
                            <div style="font-size: 2rem; font-weight: bold;">₪500+</div>
                            <small>ערך ממוצע</small>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div style="padding: 2rem; background: #fff3e0; border-radius: 8px;">
                            <h4 style="color: var(--warning);">רווח נקי</h4>
                            <div style="font-size: 2rem; font-weight: bold;">₪431+</div>
                            <small>לחודש</small>
                        </div>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 2rem; font-size: 1.1rem;">
                    <strong>רק הזמנה נוספת אחת בחודש מחזירה את ההשקעה ומרוויחה!</strong>
                </p>
            </div>
        </div>
    </div>

    <script>
        function selectPlan(months, price) {
            // הסרת בחירה קודמת
            document.querySelectorAll('.pricing-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // סימון הכרטיס הנבחר
            event.currentTarget.classList.add('selected');
            
            // עדכון המידע
            const planNames = {1: 'חודשי', 3: 'רבעוני', 6: 'חצי שנתי', 12: 'שנתי'};
            const monthlyPrice = price / months;
            const totalSaving = (69 * months) - price;
            
            document.getElementById('selectedPlanName').textContent = planNames[months];
            document.getElementById('selectedPlanPrice').textContent = price + '₪';
            document.getElementById('selectedPlanMonthly').textContent = monthlyPrice.toFixed(1) + '₪ לחודש';
            document.getElementById('selectedMonths').value = months;
            
            // הצגת חיסכון
            const savingsElement = document.getElementById('selectedPlanSavings');
            if (totalSaving > 0) {
                savingsElement.textContent = `חוסך ${totalSaving}₪ לעומת תשלום חודשי!`;
                savingsElement.style.display = 'block';
            } else {
                savingsElement.style.display = 'none';
            }
            
            // הצגת המידע
            document.getElementById('selectedPlanInfo').style.display = 'block';
            
            // גלילה לחלק התחתון
            document.getElementById('selectedPlanInfo').scrollIntoView({ behavior: 'smooth' });
        }
        
        // בחירת התוכנית השנתית כברירת מחדל
        document.addEventListener('DOMContentLoaded', function() {
            const yearlyCard = document.querySelector('.pricing-card.popular');
            if (yearlyCard) {
                yearlyCard.click();
            }
        });
    </script>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>