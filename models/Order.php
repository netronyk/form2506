<?php
// models/Order.php - מודל הזמנות - מתוקן

class Order {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * יצירת הזמנה חדשה
     */
    public function createOrder($data) {
        try {
            $orderNumber = $this->generateOrderNumber();
            
            $orderId = $this->db->insert('orders', [
                'customer_id' => $data['customer_id'],
                'order_number' => $orderNumber,
                'customer_type' => $data['customer_type'],
                'company_name' => $data['company_name'] ?? null,
                'business_number' => $data['business_number'] ?? null,
                'work_description' => $data['work_description'],
                'start_location' => $data['start_location'],
                'end_location' => $data['end_location'],
                'work_start_date' => $data['work_start_date'],
                'work_start_time' => $data['work_start_time'],
                'work_end_date' => $data['work_end_date'],
                'work_end_time' => $data['work_end_time'],
                'flexibility' => $data['flexibility'],
                'flexibility_before' => $data['flexibility_before'] ?? null,
                'flexibility_after' => $data['flexibility_after'] ?? null,
                'main_category_id' => $data['main_category_id'],
                'sub_category_id' => $data['sub_category_id'],
                'max_budget' => $data['max_budget'],
                'budget_type' => $data['budget_type'] ?? null,
                'special_requirements' => $data['special_requirements'],
                'quote_deadline' => $data['quote_deadline']
            ]);
            
            // הוספת סוגי עבודות שנבחרו
            if (!empty($data['work_types']) && is_array($data['work_types'])) {
                foreach ($data['work_types'] as $workTypeId) {
                    $this->db->insert('order_work_types', [
                        'order_id' => $orderId,
                        'work_type_id' => $workTypeId
                    ]);
                }
            }
            
            // הוספת תמונות
            if (!empty($data['images'])) {
                foreach ($data['images'] as $imagePath) {
                    $this->db->insert('order_images', [
                        'order_id' => $orderId,
                        'image_path' => $imagePath
                    ]);
                }
            }
            
            return ['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה ביצירת ההזמנה'];
        }
    }
    
    /**
     * עדכון הזמנה קיימת
     */
    public function updateOrder($orderId, $data, $customerId) {
        try {
            // בדיקה שההזמנה שייכת ללקוח ופתוחה לעריכה
            $order = $this->db->fetchOne(
                "SELECT * FROM orders WHERE id = :id AND customer_id = :customer_id AND status = 'open_for_quotes'",
                [':id' => $orderId, ':customer_id' => $customerId]
            );
            
            if (!$order) {
                return ['success' => false, 'message' => 'לא ניתן לערוך הזמנה זו'];
            }
            
            // עדכון פרטי ההזמנה
            $this->db->update('orders', [
                'customer_type' => $data['customer_type'],
                'company_name' => $data['company_name'] ?? null,
                'business_number' => $data['business_number'] ?? null,
                'work_description' => $data['work_description'],
                'start_location' => $data['start_location'],
                'end_location' => $data['end_location'],
                'work_start_date' => $data['work_start_date'],
                'work_start_time' => $data['work_start_time'],
                'work_end_date' => $data['work_end_date'],
                'work_end_time' => $data['work_end_time'],
                'flexibility' => $data['flexibility'],
                'flexibility_before' => $data['flexibility_before'] ?? null,
                'flexibility_after' => $data['flexibility_after'] ?? null,
                'main_category_id' => $data['main_category_id'],
                'sub_category_id' => $data['sub_category_id'],
                'max_budget' => $data['max_budget'],
                'budget_type' => $data['budget_type'] ?? null,
                'special_requirements' => $data['special_requirements'],
                'quote_deadline' => $data['quote_deadline']
            ], 'id = :id', [':id' => $orderId]);
            
            // עדכון סוגי עבודות
            if (isset($data['work_types'])) {
                $this->db->delete('order_work_types', 'order_id = :id', [':id' => $orderId]);
                if (!empty($data['work_types']) && is_array($data['work_types'])) {
                    foreach ($data['work_types'] as $workTypeId) {
                        $this->db->insert('order_work_types', [
                            'order_id' => $orderId,
                            'work_type_id' => $workTypeId
                        ]);
                    }
                }
            }
            
            // עדכון תמונות (אם צורפו חדשות)
            if (!empty($data['images'])) {
                foreach ($data['images'] as $imagePath) {
                    $this->db->insert('order_images', [
                        'order_id' => $orderId,
                        'image_path' => $imagePath
                    ]);
                }
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בעדכון ההזמנה'];
        }
    }
    
    /**
     * בדיקה אם ניתן לערוך הזמנה
     */
    public function canEditOrder($orderId, $customerId) {
        $order = $this->db->fetchOne(
            "SELECT status, (SELECT COUNT(*) FROM quotes WHERE order_id = orders.id) as quote_count 
             FROM orders WHERE id = :id AND customer_id = :customer_id",
            [':id' => $orderId, ':customer_id' => $customerId]
        );
        
        return $order && $order['status'] === 'open_for_quotes' && $order['quote_count'] == 0;
    }
    
    /**
     * קבלת כל ההזמנות
     */
    public function getAllOrders($customerId = null, $status = null) {
        $sql = "SELECT o.*, u.first_name, u.last_name, u.phone, u.email,
                       mc.name as main_category_name, sc.name as sub_category_name,
                       COUNT(q.id) as quote_count
                FROM orders o 
                JOIN users u ON o.customer_id = u.id
                LEFT JOIN main_categories mc ON o.main_category_id = mc.id
                LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
                LEFT JOIN quotes q ON o.id = q.order_id
                WHERE 1=1";
        
        $params = [];
        
        if ($customerId) {
            $sql .= " AND o.customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        if ($status) {
            $sql .= " AND o.status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * קבלת הזמנה לפי ID
     */
    public function getOrderById($id) {
        $order = $this->db->fetchOne(
            "SELECT o.*, u.first_name, u.last_name, u.phone, u.email,
                    mc.name as main_category_name, sc.name as sub_category_name
             FROM orders o 
             JOIN users u ON o.customer_id = u.id
             LEFT JOIN main_categories mc ON o.main_category_id = mc.id
             LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
             WHERE o.id = :id",
            [':id' => $id]
        );
        
        if ($order) {
            // הוספת תמונות
            $order['images'] = $this->db->fetchAll(
                "SELECT * FROM order_images WHERE order_id = :id ORDER BY sort_order",
                [':id' => $id]
            );
            
            // הוספת הצעות מחיר
            $order['quotes'] = $this->db->fetchAll(
                "SELECT q.*, v.vehicle_name, u.first_name, u.last_name, u.phone, u.email,
                        sc.name as sub_category_name
                 FROM quotes q 
                 JOIN vehicles v ON q.vehicle_id = v.id
                 JOIN users u ON q.vehicle_owner_id = u.id
                 JOIN sub_categories sc ON v.sub_category_id = sc.id
                 WHERE q.order_id = :id
                 ORDER BY q.created_at DESC",
                [':id' => $id]
            );
            
            // הוספת סוגי עבודות
            $order['work_types'] = $this->getOrderWorkTypes($id);
        }
        
        return $order;
    }
    
    /**
     * קבלת סוגי העבודות של הזמנה
     */
    public function getOrderWorkTypes($orderId) {
        return $this->db->fetchAll(
            "SELECT wt.* FROM work_types wt 
             JOIN order_work_types owt ON wt.id = owt.work_type_id 
             WHERE owt.order_id = :order_id",
            [':order_id' => $orderId]
        );
    }
    
    /**
     * עדכון סטטוס הזמנה
     */
    public function updateOrderStatus($orderId, $status) {
        return $this->db->update('orders', 
            ['status' => $status], 
            'id = :id', 
            [':id' => $orderId]
        );
    }
    
    /**
     * הוספת הצעת מחיר
     */
    public function addQuote($data) {
        try {
            // בדיקה שעדיין לא נתן הצעה לאותה הזמנה
            $existing = $this->db->fetchOne(
                "SELECT id FROM quotes WHERE order_id = :order_id AND vehicle_id = :vehicle_id",
                [':order_id' => $data['order_id'], ':vehicle_id' => $data['vehicle_id']]
            );
            
            if ($existing) {
                return ['success' => false, 'message' => 'כבר נתת הצעה לאותה הזמנה'];
            }
            
            $quoteId = $this->db->insert('quotes', [
                'order_id' => $data['order_id'],
                'vehicle_id' => $data['vehicle_id'],
                'vehicle_owner_id' => $data['vehicle_owner_id'],
                'quote_amount' => $data['quote_amount'],
                'quote_description' => $data['quote_description'] ?? null
            ]);
            
            return ['success' => true, 'quote_id' => $quoteId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בשליחת ההצעה'];
        }
    }
    
    /**
     * בחירת הצעת מחיר
     */
    public function selectQuote($quoteId) {
        try {
            // קבלת פרטי ההצעה
            $quote = $this->db->fetchOne(
                "SELECT * FROM quotes WHERE id = :id",
                [':id' => $quoteId]
            );
            
            if (!$quote) {
                return ['success' => false, 'message' => 'הצעה לא נמצאה'];
            }
            
            // עדכון ההצעה הנבחרת
            $this->db->update('quotes', 
                ['is_selected' => 1], 
                'id = :id', 
                [':id' => $quoteId]
            );
            
            // עדכון סטטוס ההזמנה
            $this->updateOrderStatus($quote['order_id'], 'in_negotiation');
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בבחירת ההצעה'];
        }
    }
    
    /**
     * יצירת מספר הזמנה ייחודי
     */
    private function generateOrderNumber() {
        $prefix = 'ORD';
        $timestamp = date('Ymd');
        $random = sprintf('%04d', rand(1000, 9999));
        return $prefix . $timestamp . $random;
    }
    
    /**
     * חיפוש הזמנות
     */
    public function searchOrders($criteria) {
        $sql = "SELECT o.*, u.first_name, u.last_name,
                       mc.name as main_category_name, sc.name as sub_category_name
                FROM orders o 
                JOIN users u ON o.customer_id = u.id
                LEFT JOIN main_categories mc ON o.main_category_id = mc.id
                LEFT JOIN sub_categories sc ON o.sub_category_id = sc.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($criteria['status'])) {
            $sql .= " AND o.status = :status";
            $params[':status'] = $criteria['status'];
        }
        
        if (!empty($criteria['main_category_id'])) {
            $sql .= " AND o.main_category_id = :main_category_id";
            $params[':main_category_id'] = $criteria['main_category_id'];
        }
        
        if (!empty($criteria['date_from'])) {
            $sql .= " AND o.work_start_date >= :date_from";
            $params[':date_from'] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $sql .= " AND o.work_start_date <= :date_to";
            $params[':date_to'] = $criteria['date_to'];
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * מחיקת הזמנה
     */
    public function deleteOrder($id) {
        try {
            // מחיקת תמונות
            $this->db->delete('order_images', 'order_id = :id', [':id' => $id]);
            
            // מחיקת הצעות מחיר
            $this->db->delete('quotes', 'order_id = :id', [':id' => $id]);
            
            // מחיקת סוגי עבודות
            $this->db->delete('order_work_types', 'order_id = :id', [':id' => $id]);
            
            // מחיקת ההזמנה
            $this->db->delete('orders', 'id = :id', [':id' => $id]);
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה במחיקת ההזמנה'];
        }
    }
    
    /**
     * סטטיסטיקות הזמנות ללקוח
     */
    public function getOrderStats($customerId = null) {
        $whereClause = $customerId ? "WHERE customer_id = :customer_id" : "";
        $params = $customerId ? [':customer_id' => $customerId] : [];
        
        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'open_for_quotes' THEN 1 END) as open_orders,
                COUNT(CASE WHEN status = 'in_negotiation' THEN 1 END) as negotiation_orders,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_orders
             FROM orders $whereClause",
            $params
        );
    }
    
    /**
     * סטטיסטיקות הזמנות למנהל - מתוקן!
     */
    public function getOrdersStats() {
        $result = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'open_for_quotes' THEN 1 END) as open,
                COUNT(CASE WHEN status = 'in_negotiation' THEN 1 END) as negotiation,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed
             FROM orders"
        );
        
        return [
            'total' => $result['total'] ?? 0,
            'open' => $result['open'] ?? 0,
            'negotiation' => $result['negotiation'] ?? 0,
            'closed' => $result['closed'] ?? 0
        ];
    }
    /**
 * עדכון הזמנה על ידי מנהל - ללא מגבלות
 */
public function updateOrderByAdmin($orderId, $data, $adminId) {
    try {
        // קבלת פרטי ההזמנה (בלי בדיקות הרשאה)
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE id = :id",
            [':id' => $orderId]
        );
        
        if (!$order) {
            return ['success' => false, 'message' => 'הזמנה לא נמצאה'];
        }
        
        // עדכון פרטי ההזמנה
        $this->db->update('orders', [
            'customer_type' => $data['customer_type'],
            'company_name' => $data['company_name'] ?? null,
            'business_number' => $data['business_number'] ?? null,
            'work_description' => $data['work_description'],
            'start_location' => $data['start_location'],
            'end_location' => $data['end_location'],
            'work_start_date' => $data['work_start_date'],
            'work_start_time' => $data['work_start_time'],
            'work_end_date' => $data['work_end_date'],
            'work_end_time' => $data['work_end_time'],
            'flexibility' => $data['flexibility'],
            'flexibility_before' => $data['flexibility_before'] ?? null,
            'flexibility_after' => $data['flexibility_after'] ?? null,
            'main_category_id' => $data['main_category_id'],
            'sub_category_id' => $data['sub_category_id'],
            'max_budget' => $data['max_budget'],
            'budget_type' => $data['budget_type'] ?? null,
            'special_requirements' => $data['special_requirements'],
            'quote_deadline' => $data['quote_deadline']
        ], 'id = :id', [':id' => $orderId]);
        
        // עדכון סוגי עבודות
        if (isset($data['work_types'])) {
            $this->db->delete('order_work_types', 'order_id = :id', [':id' => $orderId]);
            if (!empty($data['work_types']) && is_array($data['work_types'])) {
                foreach ($data['work_types'] as $workTypeId) {
                    $this->db->insert('order_work_types', [
                        'order_id' => $orderId,
                        'work_type_id' => $workTypeId
                    ]);
                }
            }
        }
        
        // עדכון תמונות (אם צורפו חדשות)
        if (!empty($data['images'])) {
            foreach ($data['images'] as $imagePath) {
                $this->db->insert('order_images', [
                    'order_id' => $orderId,
                    'image_path' => $imagePath
                ]);
            }
        }
        
        // עדכון שדה updated_at
        $this->db->update('orders', [
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $orderId]);
        
        return ['success' => true, 'admin_id' => $adminId];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'שגיאה בעדכון ההזמנה'];
    }
}
}
?>