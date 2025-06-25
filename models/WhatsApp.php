<?php
// models/WhatsApp.php - מודל שליחת הודעות ווטסאפ

class WhatsApp {
    private $db;
    private $settings;
    
    public function __construct() {
        $this->db = new Database();
        $this->loadSettings();
    }
    
    /**
     * טעינת הגדרות ווטסאפ מבסיס הנתונים
     */
    private function loadSettings() {
        $this->settings = $this->db->fetchOne(
            "SELECT * FROM whatsapp_settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1"
        );
        
        if (!$this->settings) {
            // הגדרות ברירת מחדל
            $this->settings = [
                'api_endpoint' => WHATSAPP_API_ENDPOINT ?? 'https://api.green-api.com',
                'instance_id' => WHATSAPP_INSTANCE_ID ?? '',
                'api_token' => WHATSAPP_API_TOKEN ?? '',
                'is_active' => false
            ];
        }
    }
    
    /**
     * שליחת הודעת טקסט בווטסאפ
     */
    public function sendMessage($phoneNumber, $message, $notificationId = null) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'ווטסאפ לא מוגדר'];
        }
        
        // ניקוי וסידור מספר הטלפון
        $formattedNumber = $this->formatPhoneNumber($phoneNumber);
        
        if (!$formattedNumber) {
            return ['success' => false, 'message' => 'מספר טלפון לא תקין'];
        }
        
        try {
            $url = $this->settings['api_endpoint'] . "/waInstance{$this->settings['instance_id']}/sendMessage/{$this->settings['api_token']}";
            
            $data = [
                'chatId' => $formattedNumber . '@c.us',
                'message' => $message
            ];
            
            $response = $this->makeApiCall($url, $data);
            
            if ($response['success']) {
                // עדכון סטטוס הודעה אם יש notification ID
                if ($notificationId) {
                    $this->updateNotificationStatus($notificationId, 'sent', $response['data']['idMessage'] ?? null);
                }
                
                return [
                    'success' => true, 
                    'message' => 'הודעה נשלחה בהצלחה',
                    'message_id' => $response['data']['idMessage'] ?? null
                ];
            } else {
                return ['success' => false, 'message' => $response['error']];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בשליחת הודעה: ' . $e->getMessage()];
        }
    }
    
    /**
     * שליחת הודעה מתבנית
     */
    public function sendTemplateMessage($phoneNumber, $templateType, $variables = [], $notificationId = null) {
        $template = $this->getTemplate($templateType);
        
        if (!$template) {
            return ['success' => false, 'message' => 'תבנית לא נמצאה'];
        }
        
        // החלפת משתנים בתבנית
        $message = $this->replaceVariables($template['message'], $variables);
        $title = $this->replaceVariables($template['title'], $variables);
        
        // הודעה מלאה עם כותרת
        $fullMessage = "*{$title}*\n\n{$message}";
        
        return $this->sendMessage($phoneNumber, $fullMessage, $notificationId);
    }
    
    /**
     * קבלת תבנית הודעה
     */
    public function getTemplate($templateType) {
        return $this->db->fetchOne(
            "SELECT * FROM whatsapp_templates WHERE template_type = :type AND is_active = 1",
            [':type' => $templateType]
        );
    }
    
    /**
     * החלפת משתנים בתבנית
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }
    
    /**
     * סידור מספר טלפון לפורמט ווטסאפ
     */
    private function formatPhoneNumber($phone) {
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
        
        // בדיקה שמספר ישראלי תקין
        if (strlen($phone) === 12 && substr($phone, 0, 3) === '972') {
            return $phone;
        }
        
        return false;
    }
    
    /**
     * קריאה ל-API
     */
    private function makeApiCall($url, $data) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['idMessage'])) {
            return ['success' => true, 'data' => $data];
        } else {
            return ['success' => false, 'error' => $data['message'] ?? 'שגיאה לא ידועה'];
        }
    }
    
    /**
     * עדכון סטטוס התראה
     */
    private function updateNotificationStatus($notificationId, $status, $externalId = null) {
        $updateData = [
            'delivery_status' => $status,
            'sent_at' => date('Y-m-d H:i:s'),
            'sent_via' => 'whatsapp'
        ];
        
        if ($externalId) {
            $updateData['external_id'] = $externalId;
        }
        
        $this->db->update('notifications', $updateData, 'id = :id', [':id' => $notificationId]);
    }
    
    /**
     * בדיקה אם ווטסאפ מוגדר
     */
    public function isConfigured() {
        return !empty($this->settings['instance_id']) && 
               !empty($this->settings['api_token']) && 
               $this->settings['is_active'];
    }
    
    /**
     * בדיקת סטטוס API
     */
    public function checkApiStatus() {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'API לא מוגדר'];
        }
        
        try {
            $url = $this->settings['api_endpoint'] . "/waInstance{$this->settings['instance_id']}/getStateInstance/{$this->settings['api_token']}";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return [
                    'success' => true, 
                    'status' => $data['stateInstance'] ?? 'unknown',
                    'message' => 'API פעיל'
                ];
            } else {
                return ['success' => false, 'message' => 'שגיאה בחיבור ל-API'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה: ' . $e->getMessage()];
        }
    }
    
    /**
     * עדכון הגדרות ווטסאפ
     */
    public function updateSettings($instanceId, $apiToken, $apiEndpoint = null) {
        try {
            $data = [
                'instance_id' => $instanceId,
                'api_token' => $apiToken,
                'api_endpoint' => $apiEndpoint ?: $this->settings['api_endpoint'],
                'is_active' => 1
            ];
            
            // כיבוי הגדרות קיימות
            $this->db->update('whatsapp_settings', ['is_active' => 0], '1=1', []);
            
            // הוספת הגדרות חדשות
            $this->db->insert('whatsapp_settings', $data);
            
            $this->loadSettings(); // טעינה מחדש
            
            return ['success' => true, 'message' => 'הגדרות ווטסאפ עודכנו בהצלחה'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בעדכון הגדרות'];
        }
    }
    
    /**
     * הודעות למשתמשים ספציפיים
     */
    public function notifyUser($userId, $templateType, $variables = []) {
        $user = $this->db->fetchOne(
            "SELECT first_name, last_name, whatsapp_number, notification_whatsapp 
             FROM users WHERE id = :id",
            [':id' => $userId]
        );
        
        if (!$user || !$user['notification_whatsapp'] || !$user['whatsapp_number']) {
            return ['success' => false, 'message' => 'משתמש לא מוגדר לקבלת הודעות ווטסאפ'];
        }
        
        // הוספת שם המשתמש למשתנים
        $variables['name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        return $this->sendTemplateMessage($user['whatsapp_number'], $templateType, $variables);
    }
    
    /**
     * שליחה לכל המשתמשים עם הגדרת ווטסאפ
     */
    public function broadcastMessage($templateType, $variables = [], $userType = null) {
        $sql = "SELECT id, first_name, last_name, whatsapp_number 
                FROM users 
                WHERE notification_whatsapp = 1 AND whatsapp_number IS NOT NULL AND is_active = 1";
        
        $params = [];
        
        if ($userType) {
            $sql .= " AND user_type = :user_type";
            $params[':user_type'] = $userType;
        }
        
        $users = $this->db->fetchAll($sql, $params);
        $results = [];
        
        foreach ($users as $user) {
            $userVariables = array_merge($variables, [
                'name' => $user['first_name'] . ' ' . $user['last_name']
            ]);
            
            $result = $this->sendTemplateMessage($user['whatsapp_number'], $templateType, $userVariables);
            $results[] = [
                'user_id' => $user['id'],
                'success' => $result['success'],
                'message' => $result['message']
            ];
            
            // המתנה קצרה בין הודעות
            usleep(500000); // 0.5 שניות
        }
        
        return $results;
    }
}
?>