<?php
// config/settings.php - הגדרות כלליות של המערכת

// הגדרות אתר
define('SITE_NAME', 'נהגים - מערכת הזמנות');
define('SITE_URL', 'https://nahagim.co.il/form');
define('SITE_EMAIL', 'info@nahagim.co.il');

// נתיבים
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// הגדרות מערכת
define('SESSION_TIMEOUT', 3600); // שעה בשניות
define('MAX_LOGIN_ATTEMPTS', 5);
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// הגדרות מנוי
define('SUBSCRIPTION_PRICE', 69.00);
define('SUBSCRIPTION_CURRENCY', 'ILS');

// הגדרות אימייל
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

// הגדרות צבעים (לפי הלוגו)
define('PRIMARY_COLOR', '#FF7A00');
define('SECONDARY_COLOR', '#1B365D');
define('SUCCESS_COLOR', '#28a745');
define('WARNING_COLOR', '#ffc107');
define('DANGER_COLOR', '#dc3545');

// הגדרות מפתחות
define('ENCRYPTION_KEY', 'your_encryption_key_here_32_chars');
define('JWT_SECRET', 'your_jwt_secret_key_here');

// timezone
date_default_timezone_set('Asia/Jerusalem');

// autoloader פשוט
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/models/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// הפעלת sessions
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// פונקציות עזר
function redirect($url) {
    header("Location: $url");
    exit();
}

function flash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash($type = null) {
    if ($type) {
        $message = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_type() {
    return $_SESSION['user_type'] ?? null;
}

function check_permission($required_type) {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
    
    $user_type = get_user_type();
    
    if ($required_type === 'admin' && $user_type !== 'admin') {
        redirect('/dashboard.php');
    }
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_order_number() {
    return 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function format_price($amount) {
    return number_format($amount, 2) . ' ₪';
}

function upload_image($file, $folder = 'general') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    $upload_dir = UPLOADS_PATH . '/' . $folder . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_IMAGE_TYPES)) {
        return false;
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return false;
    }
    
    $filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $folder . '/' . $filename;
    }
    
    return false;
}
// הגדרות ווטסאפ (Green API)
define('WHATSAPP_API_ENDPOINT', 'https://api.green-api.com');
define('WHATSAPP_INSTANCE_ID', '7105266167'); // הזן את ה-Instance ID שלך
define('WHATSAPP_API_TOKEN','f0df15f091dd41d184ec41e692c2b3661fda2bc669f34f94bc'); // הזן את ה-API Token שלך

// הגדרות התראות
define('NOTIFICATION_RETRY_ATTEMPTS', 3);
define('NOTIFICATION_RETRY_DELAY', 5); // דקות

// פונקציות עזר לווטסאפ
function format_whatsapp_number($phone) {
    // הסרת כל התווים שאינם ספרות
    $phone = preg_replace('/\D/', '', $phone);
    
    // אם המספר מתחיל ב-0, החלף ל-972
    if (substr($phone, 0, 1) === '0') {
        $phone = '972' . substr($phone, 1);
    }
    // אם המספר לא מתחיל ב-972, הוסף
    elseif (substr($phone, 0, 3) !== '972') {
        $phone = '972' . $phone;
    }
    
    return $phone;
}

function is_valid_israeli_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    // בדיקה שמתחיל בקידומת ישראלית תקינה
    $validPrefixes = ['050', '052', '053', '054', '055', '058'];
    
    if (strlen($phone) === 10) {
        $prefix = substr($phone, 0, 3);
        return in_array($prefix, $validPrefixes);
    }
    
    return false;
}

function send_notification_to_user($userId, $type, $title, $message, $relatedOrderId = null) {
    require_once ROOT_PATH . '/models/Notification.php';
    
    $notification = new Notification();
    return $notification->createNotification([
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'related_order_id' => $relatedOrderId
    ]);
}

// שליחת התראה מהירה בווטסאפ
function quick_whatsapp_notify($phoneNumber, $message) {
    require_once ROOT_PATH . '/models/WhatsApp.php';
    
    $whatsapp = new WhatsApp();
    return $whatsapp->sendMessage($phoneNumber, $message);
}
?>