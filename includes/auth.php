<?php
// includes/auth.php - מערכת התחברות ואימות

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * התחברות משתמש
     */
    public function login($username, $password) {
        try {
           
            
            // בדיקת נתונים
            if (empty($username) || empty($password)) {
                echo "ERROR: Empty fields<br>";
                echo "</div>";
                return ['success' => false, 'message' => 'נא למלא את כל השדות'];
            }
            
            // חיפוש משתמש
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
                [$username, $username]
            );
            
            echo "User found: " . ($user ? 'YES' : 'NO') . "<br>";
            
            if (!$user) {
                echo "ERROR: User not found or not active<br>";
                echo "</div>";
                return ['success' => false, 'message' => 'שם משתמש או סיסמה שגויים'];
            }
            
            echo "Found user: " . htmlspecialchars($user['username']) . " (" . $user['user_type'] . ")<br>";
            echo "Stored hash: " . substr($user['password'], 0, 20) . "...<br>";
            
            // בדיקת סיסמה
            $passwordCheck = password_verify($password, $user['password']);
            echo "Password verify result: " . ($passwordCheck ? 'YES' : 'NO') . "<br>";
            
            if (!$passwordCheck) {
                echo "ERROR: Password verification failed<br>";
                echo "Trying with plain text comparison: " . ($password === $user['password'] ? 'MATCH' : 'NO MATCH') . "<br>";
                echo "</div>";
                return ['success' => false, 'message' => 'שם משתמש או סיסמה שגויים'];
            }
            
            // יצירת session
            $this->createSession($user);
            echo "Session created successfully<br>";
            echo "</div>";
            
            return ['success' => true, 'message' => 'התחברת בהצלחה', 'user' => $user];
            
        } catch (Exception $e) {
            
            return ['success' => false, 'message' => 'שגיאה במערכת: ' . $e->getMessage()];
        }
    }
    
    /**
     * רישום משתמש חדש
     */
    public function register($data) {
        try {
            // Validation
            $validation = $this->validateRegistration($data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // בדיקת קיום משתמש
            $exists = $this->db->fetchOne(
                "SELECT id FROM users WHERE username = :username OR email = :email",
                [':username' => $data['username'], ':email' => $data['email']]
            );
            
            if ($exists) {
                return ['success' => false, 'message' => 'שם משתמש או אימייל כבר קיימים'];
            }
            
            // יצירת משתמש
            $userData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'user_type' => $data['user_type'] ?? 'customer',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'created_by' => $_SESSION['user_id'] ?? null
            ];
            
            $userId = $this->db->insert('users', $userData);
            
            if ($userId) {
                return ['success' => true, 'message' => 'המשתמש נוצר בהצלחה', 'user_id' => $userId];
            }
            
            return ['success' => false, 'message' => 'שגיאה ביצירת המשתמש'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה במערכת'];
        }
    }
    
    /**
     * אימות רישום
     */
    private function validateRegistration($data) {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return ['valid' => false, 'message' => 'נא למלא את כל השדות הנדרשים'];
        }
        
        if (strlen($data['username']) < 3) {
            return ['valid' => false, 'message' => 'שם משתמש חייב להיות לפחות 3 תווים'];
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'כתובת אימייל לא תקינה'];
        }
        
        if (strlen($data['password']) < 6) {
            return ['valid' => false, 'message' => 'סיסמה חייבת להיות לפחות 6 תווים'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * יצירת session למשתמש
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['is_premium'] = $user['is_premium'];
        $_SESSION['login_time'] = time();
    }
    
    /**
     * התנתקות
     */
    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }
    
    /**
     * בדיקת הרשאות
     */
    public function checkPermission($required_type = null) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if ($required_type && $_SESSION['user_type'] !== $required_type && $_SESSION['user_type'] !== 'admin') {
            return false;
        }
        
        return true;
    }
    
    /**
     * בדיקה אם משתמש מחובר
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * קבלת נתוני משתמש נוכחי
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = :id",
            [':id' => $_SESSION['user_id']]
        );
    }
    
    /**
     * עדכון פרטי משתמש
     */
    public function updateProfile($data) {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'נדרשת התחברות'];
        }
        
        try {
            $updateData = [];
            
            if (!empty($data['first_name'])) {
                $updateData['first_name'] = $data['first_name'];
            }
            
            if (!empty($data['last_name'])) {
                $updateData['last_name'] = $data['last_name'];
            }
            
            if (!empty($data['phone'])) {
                $updateData['phone'] = $data['phone'];
            }
            
            if (!empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'message' => 'כתובת אימייל לא תקינה'];
                }
                $updateData['email'] = $data['email'];
            }
            
            if (empty($updateData)) {
                return ['success' => false, 'message' => 'לא נמצאו נתונים לעדכון'];
            }
            
            $this->db->update('users', $updateData, 'id = :id', [':id' => $_SESSION['user_id']]);
            
            return ['success' => true, 'message' => 'הפרטים עודכנו בהצלחה'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בעדכון הפרטים'];
        }
    }
    
    /**
     * שינוי סיסמה
     */
    public function changePassword($currentPassword, $newPassword) {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'message' => 'נדרשת התחברות'];
        }
        
        try {
            $user = $this->getCurrentUser();
            
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'סיסמה נוכחית שגויה'];
            }
            
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'סיסמה חדשה חייבת להיות לפחות 6 תווים'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $this->db->update(
                'users',
                ['password' => $hashedPassword],
                'id = :id',
                [':id' => $_SESSION['user_id']]
            );
            
            return ['success' => true, 'message' => 'הסיסמה שונתה בהצלחה'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בשינוי הסיסמה'];
        }
    }
    
    /**
     * בדיקת מנוי פרימיום
     */
    public function isPremium() {
        if (!$this->isLoggedIn() || $_SESSION['user_type'] !== 'vehicle_owner') {
            return false;
        }
        
        $user = $this->getCurrentUser();
        
        if (!$user['is_premium']) {
            return false;
        }
        
        // בדיקת תאריך תפוגה
        if ($user['premium_expires'] && $user['premium_expires'] < date('Y-m-d')) {
            // עדכון סטטוס מנוי פג תוקף
            $this->db->update(
                'users',
                ['is_premium' => 0],
                'id = :id',
                [':id' => $_SESSION['user_id']]
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * הפעלת מנוי פרימיום
     */
    public function activatePremium($userId, $months = 1) {
        try {
            $expiryDate = date('Y-m-d', strtotime("+{$months} months"));
            
            $this->db->update(
                'users',
                [
                    'is_premium' => 1,
                    'premium_expires' => $expiryDate
                ],
                'id = :id',
                [':id' => $userId]
            );
            
            return ['success' => true, 'message' => 'מנוי פרימיום הופעל בהצלחה'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בהפעלת המנוי'];
        }
    }
}
?>