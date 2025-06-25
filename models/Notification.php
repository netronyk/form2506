<?php
// models/Notification.php - מודל ניהול התראות עם תמיכה בווטסאפ

class Notification {
    private $db;
    private $whatsapp;
    
    public function __construct() {
        $this->db = new Database();
        
        // טעינת מודל ווטסאפ רק אם הקבצים קיימים
        if (file_exists(__DIR__ . '/WhatsApp.php')) {
            require_once __DIR__ . '/WhatsApp.php';
            $this->whatsapp = new WhatsApp();
        }
    }
    
    /**
     * יצירת התראה חדשה עם שליחה אוטומטית
     */
    public function createNotification($data) {
        try {
            $notificationId = $this->db->insert('notifications', [
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'related_order_id' => $data['related_order_id'] ?? null,
                'created_by' => $data['created_by'] ?? null
            ]);
            
            // שליחה אוטומטית לפי הגדרות המשתמש
            $this->sendNotificationToUser($data['user_id'], $notificationId, $data);
            
            return ['success' => true, 'notification_id' => $notificationId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה ביצירת התראה'];
        }
    }
    
    /**
     * שליחת התראה למשתמש לפי הגדרותיו
     */
    private function sendNotificationToUser($userId, $notificationId, $notificationData) {
        $user = $this->db->fetchOne(
            "SELECT first_name, last_name, email, phone, whatsapp_number,
                    notification_email, notification_sms, notification_whatsapp
             FROM users WHERE id = :id",
            [':id' => $userId]
        );
        
        if (!$user) return;
        
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        
        // שליחה בווטסאפ
        if ($user['notification_whatsapp'] && $user['whatsapp_number'] && $this->whatsapp) {
            $this->sendWhatsAppNotification($user, $notificationData, $notificationId);
        }
        
        // שליחה באימייל
        if ($user['notification_email'] && $user['email']) {
            $this->sendEmailNotification($user, $notificationData, $notificationId);
        }
        
        // שליחה ב-SMS
        if ($user['notification_sms'] && $user['phone']) {
            $this->sendSMSNotification($user, $notificationData, $notificationId);
        }
    }
    
    /**
     * שליחת התראה בווטסאפ
     */
    private function sendWhatsAppNotification($user, $data, $notificationId) {
        if (!$this->whatsapp) return;
        
        $templateType = $this->getWhatsAppTemplateType($data['type']);
        
        if (!$templateType) return;
        
        $variables = [
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'message' => $data['message']
        ];
        
        // הוספת משתנים ספציפיים לפי סוג ההתראה
        $variables = array_merge($variables, $this->getTemplateVariables($data));
        
        $result = $this->whatsapp->sendTemplateMessage(
            $user['whatsapp_number'], 
            $templateType, 
            $variables, 
            $notificationId
        );
        
        if ($result['success']) {
            $this->updateNotificationSentStatus($notificationId, 'whatsapp', 'sent');
        }
    }
    
    /**
     * שליחת התראה באימייל
     */
    private function sendEmailNotification($user, $data, $notificationId) {
        // כאן תוכל להוסיף לוגיקת שליחת אימייל
        // $this->sendEmail($user['email'], $data['title'], $data['message']);
        $this->updateNotificationSentStatus($notificationId, 'email', 'sent');
    }
    
    /**
     * שליחת התראה ב-SMS
     */
    private function sendSMSNotification($user, $data, $notificationId) {
        // כאן תוכל להוסיף לוגיקת שליחת SMS
        // $this->sendSMS($user['phone'], $data['message']);
        $this->updateNotificationSentStatus($notificationId, 'sms', 'sent');
    }
    
    /**
     * עדכון סטטוס שליחה
     */
    private function updateNotificationSentStatus($notificationId, $sentVia, $status) {
        $this->db->update('notifications', [
            'sent_via' => $sentVia,
            'delivery_status' => $status,
            'sent_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $notificationId]);
    }
    
    /**
     * קביעת סוג תבנית ווטסאפ לפי סוג התראה
     */
    private function getWhatsAppTemplateType($notificationType) {
        $mapping = [
            'order_created' => 'order_created',
            'quote_received' => 'quote_received',
            'admin_update' => 'order_updated',
            'subscription_expiring' => 'subscription_expiring',
            'system' => 'system_message'
        ];
        
        return $mapping[$notificationType] ?? 'system_message';
    }
    
    /**
     * קבלת משתנים לתבנית
     */
    private function getTemplateVariables($data) {
        $variables = [];
        
        // אם יש הזמנה קשורה
        if (isset($data['related_order_id'])) {
            $order = $this->db->fetchOne(
                "SELECT order_number, description FROM orders WHERE id = :id",
                [':id' => $data['related_order_id']]
            );
            
            if ($order) {
                $variables['order_number'] = $order['order_number'];
                $variables['order_details'] = $order['description'];
                $variables['order_link'] = SITE_URL . '/customer/orders.php?id=' . $data['related_order_id'];
            }
        }
        
        return $variables;
    }
    
    /**
     * קבלת התראות למשתמש
     */
    public function getUserNotifications($userId, $limit = 20, $unreadOnly = false) {
        $sql = "SELECT n.*, o.order_number, u.first_name as admin_name
                FROM notifications n
                LEFT JOIN orders o ON n.related_order_id = o.id
                LEFT JOIN users u ON n.created_by = u.id
                WHERE n.user_id = :user_id";
        
        $params = [':user_id' => $userId];
        
        if ($unreadOnly) {
            $sql .= " AND n.is_read = 0";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT :limit";
        
        return $this->db->fetchAll($sql, array_merge($params, [':limit' => $limit]));
    }
    
    /**
     * סימון התראה כנקראה
     */
    public function markAsRead($notificationId, $userId) {
        return $this->db->update(
            'notifications',
            ['is_read' => 1],
            'id = :id AND user_id = :user_id',
            [':id' => $notificationId, ':user_id' => $userId]
        );
    }
    
    /**
     * סימון כל ההתראות כנקראו
     */
    public function markAllAsRead($userId) {
        return $this->db->update(
            'notifications',
            ['is_read' => 1],
            'user_id = :user_id AND is_read = 0',
            [':user_id' => $userId]
        );
    }
    
    /**
     * ספירת התראות לא נקראו
     */
    public function getUnreadCount($userId) {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0",
            [':user_id' => $userId]
        );
        
        return $result['count'] ?? 0;
    }
    
    /**
     * מחיקת התראה
     */
    public function deleteNotification($notificationId, $userId) {
        return $this->db->delete(
            'notifications',
            'id = :id AND user_id = :user_id',
            [':id' => $notificationId, ':user_id' => $userId]
        );
    }
    
    /**
     * התראה על הזמנה חדשה
     */
    public function notifyNewOrder($orderId, $customerId) {
        $order = $this->db->fetchOne(
            "SELECT order_number, description FROM orders WHERE id = :id",
            [':id' => $orderId]
        );
        
        if (!$order) return false;
        
        return $this->createNotification([
            'user_id' => $customerId,
            'type' => 'order_created',
            'title' => 'הזמנה חדשה נוצרה',
            'message' => "הזמנתך #{$order['order_number']} נוצרה בהצלחה ופתוחה להצעות מחיר.",
            'related_order_id' => $orderId
        ]);
    }
    
    /**
     * התראה על עדכון הזמנה על ידי מנהל
     */
    public function notifyOrderUpdatedByAdmin($orderId, $customerId, $adminId, $updateMessage = null) {
        $order = $this->db->fetchOne(
            "SELECT order_number FROM orders WHERE id = :id",
            [':id' => $orderId]
        );
        
        if (!$order) return false;
        
        $message = $updateMessage ?: "הזמנה #{$order['order_number']} עודכנה על ידי מנהל המערכת.";
        
        return $this->createNotification([
            'user_id' => $customerId,
            'type' => 'admin_update',
            'title' => 'הזמנה עודכנה על ידי מנהל המערכת',
            'message' => $message,
            'related_order_id' => $orderId,
            'created_by' => $adminId
        ]);
    }
    
    /**
     * התראה על הצעת מחיר חדשה
     */
    public function notifyNewQuote($orderId, $customerId, $vehicleOwnerId = null, $quotePrice = null) {
        $order = $this->db->fetchOne(
            "SELECT order_number FROM orders WHERE id = :id",
            [':id' => $orderId]
        );
        
        if (!$order) return false;
        
        $message = "התקבלה הצעת מחיר חדשה עבור הזמנה #{$order['order_number']}.";
        
        if ($quotePrice) {
            $message .= " מחיר: " . number_format($quotePrice) . "₪";
        }
        
        return $this->createNotification([
            'user_id' => $customerId,
            'type' => 'quote_received',
            'title' => 'התקבלה הצעת מחיר חדשה',
            'message' => $message,
            'related_order_id' => $orderId
        ]);
    }
    
    /**
     * התראה על מנוי שפג
     */
    public function notifySubscriptionExpiring($userId, $daysLeft, $expiryDate) {
        return $this->createNotification([
            'user_id' => $userId,
            'type' => 'subscription_expiring',
            'title' => 'המנוי שלך יפוג בקרוב',
            'message' => "המנוי הפרימיום שלך יפוג בעוד {$daysLeft} ימים ({$expiryDate}). לחידוש המנוי גש להגדרות החשבון."
        ]);
    }
    
    /**
     * התראת מערכת כללית
     */
    public function notifySystem($userId, $title, $message) {
        return $this->createNotification([
            'user_id' => $userId,
            'type' => 'system',
            'title' => $title,
            'message' => $message
        ]);
    }
    
    /**
     * שליחת התראה לכל המשתמשים מסוג מסוים
     */
    public function broadcastToUserType($userType, $title, $message, $templateType = 'system_message') {
        $users = $this->db->fetchAll(
            "SELECT id FROM users WHERE user_type = :type AND is_active = 1",
            [':type' => $userType]
        );
        
        $results = [];
        
        foreach ($users as $user) {
            $result = $this->createNotification([
                'user_id' => $user['id'],
                'type' => 'system',
                'title' => $title,
                'message' => $message
            ]);
            
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * סטטיסטיקות התראות
     */
    public function getNotificationStats($userId = null) {
        $whereClause = $userId ? "WHERE user_id = :user_id" : "";
        $params = $userId ? [':user_id' => $userId] : [];
        
        $stats = [];
        
        // סה"כ התראות
        $stats['total'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications {$whereClause}",
            $params
        )['count'];
        
        // התראות לא נקראו
        $stats['unread'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications {$whereClause}" . 
            ($userId ? " AND is_read = 0" : " WHERE is_read = 0"),
            $params
        )['count'];
        
        // התראות שנשלחו בווטסאפ
        $stats['whatsapp_sent'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications {$whereClause}" . 
            ($userId ? " AND sent_via = 'whatsapp'" : " WHERE sent_via = 'whatsapp'"),
            $params
        )['count'];
        
        // התראות מהשבוע האחרון
        $stats['this_week'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM notifications {$whereClause}" . 
            ($userId ? " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : " WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
            $params
        )['count'];
        
        return $stats;
    }
}
?>