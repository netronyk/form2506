<?php
// models/User.php - מודל משתמשים

class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * קבלת כל המשתמשים
     */
    public function getAllUsers($type = null, $limit = null) {
        $sql = "SELECT id, username, email, user_type, first_name, last_name, phone, 
                       is_active, is_premium, premium_expires, created_at 
                FROM users";
        $params = [];
        
        if ($type) {
            $sql .= " WHERE user_type = :type";
            $params[':type'] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * קבלת משתמש לפי ID
     */
    public function getUserById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = :id",
            [':id' => $id]
        );
    }
    
    /**
     * יצירת משתמש חדש
     */
    public function createUser($data) {
        // Validation
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'חסרים שדות נדרשים'];
        }
        
        // בדיקת קיום
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
            'user_type' => $data['user_type'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        try {
            $userId = $this->db->insert('users', $userData);
            return ['success' => true, 'message' => 'המשתמש נוצר בהצלחה', 'user_id' => $userId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה ביצירת המשתמש'];
        }
    }
    
    /**
     * עדכון משתמש
     */
    public function updateUser($id, $data) {
        $updateData = [];
        
        if (isset($data['first_name'])) $updateData['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $updateData['last_name'] = $data['last_name'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        if (isset($data['user_type'])) $updateData['user_type'] = $data['user_type'];
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => 'לא נמצאו נתונים לעדכון'];
        }
        
        try {
            $this->db->update('users', $updateData, 'id = :id', [':id' => $id]);
            return ['success' => true, 'message' => 'המשתמש עודכן בהצלחה'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בעדכון המשתמש'];
        }
    }
    
    /**
     * מחיקת משתמש (השבתה)
     */
    public function deleteUser($id) {
        try {
            $this->db->update('users', ['is_active' => 0], 'id = :id', [':id' => $id]);
            return ['success' => true, 'message' => 'המשתמש הושבת בהצלחה'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה במחיקת המשתמש'];
        }
    }
    
    /**
     * קבלת בעלי רכב
     */
    public function getVehicleOwners() {
        return $this->db->fetchAll(
            "SELECT u.*, 
                    COUNT(v.id) as vehicle_count,
                    COUNT(q.id) as quote_count
             FROM users u 
             LEFT JOIN vehicles v ON u.id = v.owner_id 
             LEFT JOIN quotes q ON u.id = q.vehicle_owner_id 
             WHERE u.user_type = 'vehicle_owner' AND u.is_active = 1
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
    }
    
    /**
     * קבלת לקוחות
     */
    public function getCustomers() {
        return $this->db->fetchAll(
            "SELECT u.*, 
                    COUNT(o.id) as order_count
             FROM users u 
             LEFT JOIN orders o ON u.id = o.customer_id 
             WHERE u.user_type = 'customer' AND u.is_active = 1
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
    }
    
    /**
     * הפעלת מנוי פרימיום - גרסה מתוקנת עם לוגיקת הארכה חכמה
     */
    public function activatePremium($userId, $months = 1) {
        $user = $this->getUserById($userId);
        if (!$user || $user['user_type'] !== 'vehicle_owner') {
            return ['success' => false, 'message' => 'משתמש לא נמצא או אינו בעל רכב'];
        }
        
        // קביעת תאריך התחלה להארכה
        $startDate = date('Y-m-d');
        
        // אם יש מנוי פעיל שעדיין לא פג, התחל מתאריך התפוגה הקיים
        if ($user['is_premium'] && $user['premium_expires'] && $user['premium_expires'] > date('Y-m-d')) {
            $startDate = $user['premium_expires'];
        }
        
        // חישוב תאריך תפוגה חדש
        $newExpiry = date('Y-m-d', strtotime($startDate . " +{$months} months"));
        
        try {
            $this->db->update(
                'users',
                [
                    'is_premium' => 1,
                    'premium_expires' => $newExpiry
                ],
                'id = :id',
                [':id' => $userId]
            );
            
            $actionType = $user['is_premium'] ? 'הורחב' : 'הופעל';
            return [
                'success' => true, 
                'message' => "מנוי פרימיום {$actionType} בהצלחה עד תאריך " . date('d/m/Y', strtotime($newExpiry))
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בהפעלת המנוי'];
        }
    }
    
    /**
     * ביטול מנוי פרימיום
     */
    public function deactivatePremium($userId) {
        try {
            $this->db->update(
                'users',
                ['is_premium' => 0],
                'id = :id',
                [':id' => $userId]
            );
            
            return ['success' => true, 'message' => 'מנוי פרימיום בוטל בהצלחה'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בביטול המנוי'];
        }
    }
    
    /**
     * קבלת סטטיסטיקות משתמשים
     */
    public function getUserStats() {
        $stats = [];
        
        // סה"כ משתמשים
        $stats['total_users'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE is_active = 1"
        )['count'];
        
        // בעלי רכב
        $stats['vehicle_owners'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE user_type = 'vehicle_owner' AND is_active = 1"
        )['count'];
        
        // לקוחות
        $stats['customers'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE user_type = 'customer' AND is_active = 1"
        )['count'];
        
        // בעלי רכב פרימיום
        $stats['premium_owners'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE user_type = 'vehicle_owner' AND is_premium = 1 AND is_active = 1"
        )['count'];
        
        // משתמשים חדשים השבוע
        $stats['new_this_week'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['count'];
        
        return $stats;
    }
    
    /**
     * חיפוש משתמשים
     */
    public function searchUsers($query, $type = null) {
        $sql = "SELECT id, username, email, user_type, first_name, last_name, phone, is_active, is_premium
                FROM users 
                WHERE (first_name LIKE :query OR last_name LIKE :query OR email LIKE :query OR username LIKE :query)";
        
        $params = [':query' => "%{$query}%"];
        
        if ($type) {
            $sql .= " AND user_type = :type";
            $params[':type'] = $type;
        }
        
        $sql .= " ORDER BY first_name, last_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * שחזור סיסמה
     */
    public function resetPassword($email) {
        $user = $this->db->fetchOne(
            "SELECT id, email, first_name FROM users WHERE email = :email AND is_active = 1",
            [':email' => $email]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'לא נמצא משתמש עם כתובת אימייל זו'];
        }
        
        // יצירת סיסמה זמנית
        $tempPassword = $this->generateTempPassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        
        try {
            $this->db->update(
                'users',
                ['password' => $hashedPassword],
                'id = :id',
                [':id' => $user['id']]
            );
            
            // כאן תוכל להוסיף שליחת אימייל
            // $this->sendPasswordResetEmail($user, $tempPassword);
            
            return [
                'success' => true, 
                'message' => 'סיסמה זמנית נוצרה בהצלחה',
                'temp_password' => $tempPassword // רק לפיתוח - בייצור שלח באימייל
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בשחזור הסיסמה'];
        }
    }
    
    /**
     * יצירת סיסמה זמנית
     */
    private function generateTempPassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
    
    /**
     * קבלת סטטיסטיקות מנויים
     */
    public function getSubscriptionStats() {
        $stats = [];
        
        // מנויים פעילים
        $stats['active_premium'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users 
             WHERE user_type = 'vehicle_owner' AND is_premium = 1 
             AND (premium_expires IS NULL OR premium_expires >= CURDATE())"
        )['count'];
        
        // מנויים שיפגו השבוע
        $stats['expiring_soon'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users 
             WHERE user_type = 'vehicle_owner' AND is_premium = 1 
             AND premium_expires BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        )['count'];
        
        // מנויים שפגו
        $stats['expired'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM users 
             WHERE user_type = 'vehicle_owner' AND is_premium = 1 AND premium_expires < CURDATE()"
        )['count'];
        
        // הכנסה חודשית משוערת (על בסיס 69₪ למנוי פעיל)
        $stats['monthly_revenue'] = $stats['active_premium'] * SUBSCRIPTION_PRICE;
        
        return $stats;
    }
}
?>