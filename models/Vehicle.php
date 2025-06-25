<?php
// models/Vehicle.php - מודל רכבים מעודכן עם תמיכה בתמונות

class Vehicle {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * קבלת רכבים
     */
    public function getAllVehicles($ownerId = null) {
        $sql = "SELECT v.*, sc.name as sub_category_name, mc.name as main_category_name, mc.id as main_category_id,
                       u.first_name, u.last_name, u.is_premium,
                       vi.image_path as primary_image
                FROM vehicles v 
                JOIN sub_categories sc ON v.sub_category_id = sc.id
                JOIN main_categories mc ON sc.main_category_id = mc.id
                JOIN users u ON v.owner_id = u.id
                LEFT JOIN vehicle_images vi ON v.id = vi.vehicle_id AND vi.is_primary = 1
                WHERE v.is_active = 1";
        $params = [];
        
        if ($ownerId) {
            $sql .= " AND v.owner_id = :owner_id";
            $params[':owner_id'] = $ownerId;
        }
        
        $sql .= " ORDER BY v.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getVehicleById($id) {
        $vehicle = $this->db->fetchOne(
            "SELECT v.*, sc.name as sub_category_name, mc.name as main_category_name, mc.id as main_category_id,
                    u.first_name, u.last_name, u.phone, u.email
             FROM vehicles v 
             JOIN sub_categories sc ON v.sub_category_id = sc.id
             JOIN main_categories mc ON sc.main_category_id = mc.id
             JOIN users u ON v.owner_id = u.id
             WHERE v.id = :id AND v.is_active = 1",
            [':id' => $id]
        );
        
        // הוספת נתונים נוספים לרכב
        if ($vehicle) {
            $vehicle['work_types'] = $this->getVehicleWorkTypes($id);
            $vehicle['images'] = $this->getVehicleImages($id);
        }
        
        return $vehicle;
    }
    
    /**
     * יצירת רכב עם תמונות
     */
    public function createVehicle($data) {
        try {
            $this->db->beginTransaction();
            
            $vehicleData = [
                'owner_id' => $data['owner_id'],
                'sub_category_id' => $data['sub_category_id'],
                'vehicle_name' => $data['vehicle_name'],
                'description' => $data['description'] ?? null
            ];
            
            // הוספת חיבור לאתר נהגים אם קיים
            if (isset($data['is_on_drivers_website'])) {
                $vehicleData['is_on_drivers_website'] = 1;
                $vehicleData['drivers_website_url'] = $data['drivers_website_url'] ?? null;
            }
            
            $vehicleId = $this->db->insert('vehicles', $vehicleData);
            
            // הוספת מאפיינים טכניים
            if (isset($data['attributes']) && is_array($data['attributes'])) {
                foreach ($data['attributes'] as $attributeId => $value) {
                    if (!empty($value)) {
                        $this->db->insert('vehicle_attributes', [
                            'vehicle_id' => $vehicleId,
                            'attribute_id' => $attributeId,
                            'attribute_value' => $value
                        ]);
                    }
                }
            }
            
            // הוספת אזורי פעילות
            if (isset($data['activity_areas']) && is_array($data['activity_areas'])) {
                foreach ($data['activity_areas'] as $areaId) {
                    $this->db->insert('vehicle_activity_areas', [
                        'vehicle_id' => $vehicleId,
                        'area_id' => $areaId
                    ]);
                }
            }
            
            // הוספת סוגי עבודות
            if (isset($data['work_types']) && is_array($data['work_types'])) {
                foreach ($data['work_types'] as $workTypeId) {
                    $this->db->insert('vehicle_work_types', [
                        'vehicle_id' => $vehicleId,
                        'work_type_id' => $workTypeId
                    ]);
                }
            }
            
            // הוספת תמונות - חדש!
            if (isset($data['images']) && is_array($data['images'])) {
                $this->saveVehicleImages($vehicleId, $data['images']);
            }
            
            $this->db->commit();
            return ['success' => true, 'vehicle_id' => $vehicleId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Vehicle creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'שגיאה ביצירת הרכב: ' . $e->getMessage()];
        }
    }
    
    /**
     * עדכון רכב עם תמונות
     */
    public function updateVehicle($id, $data) {
        try {
            $this->db->beginTransaction();
            
            $updateData = [];
            if (isset($data['vehicle_name'])) $updateData['vehicle_name'] = $data['vehicle_name'];
            if (isset($data['description'])) $updateData['description'] = $data['description'];
            if (isset($data['sub_category_id'])) $updateData['sub_category_id'] = $data['sub_category_id'];
            
            // עדכון חיבור לאתר נהגים
            if (isset($data['is_on_drivers_website'])) {
                $updateData['is_on_drivers_website'] = 1;
                $updateData['drivers_website_url'] = $data['drivers_website_url'] ?? null;
            } else {
                $updateData['is_on_drivers_website'] = 0;
                $updateData['drivers_website_url'] = null;
            }
            
            $this->db->update('vehicles', $updateData, 'id = :id', [':id' => $id]);
            
            // עדכון מאפיינים
            if (isset($data['attributes'])) {
                $this->db->delete('vehicle_attributes', 'vehicle_id = :id', [':id' => $id]);
                foreach ($data['attributes'] as $attributeId => $value) {
                    if (!empty($value)) {
                        $this->db->insert('vehicle_attributes', [
                            'vehicle_id' => $id,
                            'attribute_id' => $attributeId,
                            'attribute_value' => $value
                        ]);
                    }
                }
            }
            
            // עדכון אזורי פעילות
            if (isset($data['activity_areas'])) {
                $this->db->delete('vehicle_activity_areas', 'vehicle_id = :id', [':id' => $id]);
                foreach ($data['activity_areas'] as $areaId) {
                    $this->db->insert('vehicle_activity_areas', [
                        'vehicle_id' => $id,
                        'area_id' => $areaId
                    ]);
                }
            }
            
            // עדכון סוגי עבודות
            if (isset($data['work_types'])) {
                $this->db->delete('vehicle_work_types', 'vehicle_id = :id', [':id' => $id]);
                foreach ($data['work_types'] as $workTypeId) {
                    $this->db->insert('vehicle_work_types', [
                        'vehicle_id' => $id,
                        'work_type_id' => $workTypeId
                    ]);
                }
            }
            
            // עדכון תמונות - חדש!
            if (isset($data['images']) && is_array($data['images'])) {
                $this->saveVehicleImages($id, $data['images']);
            }
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Vehicle update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'שגיאה בעדכון הרכב: ' . $e->getMessage()];
        }
    }
    
    /**
     * שמירת תמונות רכב - חדש!
     */
    private function saveVehicleImages($vehicleId, $images) {
        foreach ($images as $index => $imagePath) {
            if (!empty($imagePath)) {
                $isPrimary = ($index === 0) ? 1 : 0; // התמונה הראשונה היא הראשית
                
                $this->db->insert('vehicle_images', [
                    'vehicle_id' => $vehicleId,
                    'image_path' => $imagePath,
                    'sort_order' => $index,
                    'is_primary' => $isPrimary
                ]);
                
                // עדכון התמונה הראשית ברכב
                if ($isPrimary) {
                    $this->db->update('vehicles', 
                        ['primary_image' => $imagePath], 
                        'id = :id', 
                        [':id' => $vehicleId]
                    );
                }
            }
        }
    }
    
    /**
     * קבלת תמונות רכב - חדש!
     */
    public function getVehicleImages($vehicleId) {
        return $this->db->fetchAll(
            "SELECT * FROM vehicle_images 
             WHERE vehicle_id = :vehicle_id 
             ORDER BY is_primary DESC, sort_order ASC",
            [':vehicle_id' => $vehicleId]
        );
    }
    
    /**
     * מחיקת תמונה - חדש!
     */
    public function deleteVehicleImage($imageId, $vehicleOwnerId) {
        try {
            // קבלת פרטי התמונה
            $image = $this->db->fetchOne(
                "SELECT vi.*, v.owner_id 
                 FROM vehicle_images vi 
                 JOIN vehicles v ON vi.vehicle_id = v.id 
                 WHERE vi.id = :image_id",
                [':image_id' => $imageId]
            );
            
            if (!$image || $image['owner_id'] != $vehicleOwnerId) {
                return ['success' => false, 'message' => 'אין הרשאה למחוק תמונה זו'];
            }
            
            // מחיקת הקובץ מהדיסק
            $fullPath = UPLOADS_PATH . '/' . $image['image_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // מחיקת הרשומה מהמסד נתונים
            $this->db->delete('vehicle_images', 'id = :id', [':id' => $imageId]);
            
            // אם זו הייתה התמונה הראשית, עדכן את התמונה הראשית
            if ($image['is_primary']) {
                $this->updatePrimaryImage($image['vehicle_id']);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Delete vehicle image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'שגיאה במחיקת התמונה'];
        }
    }
    
    /**
     * עדכון התמונה הראשית - חדש!
     */
    private function updatePrimaryImage($vehicleId) {
        // מציאת התמונה הבאה בתור
        $nextImage = $this->db->fetchOne(
            "SELECT * FROM vehicle_images 
             WHERE vehicle_id = :vehicle_id 
             ORDER BY sort_order ASC 
             LIMIT 1",
            [':vehicle_id' => $vehicleId]
        );
        
        if ($nextImage) {
            // הפיכתה לראשית
            $this->db->update('vehicle_images', 
                ['is_primary' => 1], 
                'id = :id', 
                [':id' => $nextImage['id']]
            );
            
            // עדכון הרכב
            $this->db->update('vehicles', 
                ['primary_image' => $nextImage['image_path']], 
                'id = :id', 
                [':id' => $vehicleId]
            );
        } else {
            // אין תמונות נוספות
            $this->db->update('vehicles', 
                ['primary_image' => null], 
                'id = :id', 
                [':id' => $vehicleId]
            );
        }
    }
    
    /**
     * הגדרת תמונה ראשית - חדש!
     */
    public function setPrimaryImage($imageId, $vehicleOwnerId) {
        try {
            // בדיקת הרשאות
            $image = $this->db->fetchOne(
                "SELECT vi.*, v.owner_id 
                 FROM vehicle_images vi 
                 JOIN vehicles v ON vi.vehicle_id = v.id 
                 WHERE vi.id = :image_id",
                [':image_id' => $imageId]
            );
            
            if (!$image || $image['owner_id'] != $vehicleOwnerId) {
                return ['success' => false, 'message' => 'אין הרשאה'];
            }
            
            // איפוס כל התמונות הראשיות של הרכב
            $this->db->update('vehicle_images', 
                ['is_primary' => 0], 
                'vehicle_id = :vehicle_id', 
                [':vehicle_id' => $image['vehicle_id']]
            );
            
            // הגדרת התמונה החדשה כראשית
            $this->db->update('vehicle_images', 
                ['is_primary' => 1], 
                'id = :id', 
                [':id' => $imageId]
            );
            
            // עדכון הרכב
            $this->db->update('vehicles', 
                ['primary_image' => $image['image_path']], 
                'id = :id', 
                [':id' => $image['vehicle_id']]
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Set primary image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'שגיאה בעדכון התמונה הראשית'];
        }
    }
    
    /**
     * קבלת סוגי עבודות של רכב
     */
    public function getVehicleWorkTypes($vehicleId) {
        return $this->db->fetchAll(
            "SELECT wt.* FROM work_types wt 
             JOIN vehicle_work_types vwt ON wt.id = vwt.work_type_id 
             WHERE vwt.vehicle_id = :vehicle_id
             ORDER BY wt.work_name",
            [':vehicle_id' => $vehicleId]
        );
    }
    
    /**
     * קבלת מאפיינים של רכב
     */
    public function getVehicleAttributes($vehicleId) {
        return $this->db->fetchAll(
            "SELECT va.*, ta.attribute_name, ta.attribute_type 
             FROM vehicle_attributes va 
             JOIN technical_attributes ta ON va.attribute_id = ta.id 
             WHERE va.vehicle_id = :vehicle_id",
            [':vehicle_id' => $vehicleId]
        );
    }
    
    /**
     * קבלת אזורי פעילות של רכב
     */
    public function getVehicleActivityAreas($vehicleId) {
        return $this->db->fetchAll(
            "SELECT aa.* FROM activity_areas aa 
             JOIN vehicle_activity_areas vaa ON aa.id = vaa.area_id 
             WHERE vaa.vehicle_id = :vehicle_id",
            [':vehicle_id' => $vehicleId]
        );
    }
    
    /**
     * חיפוש רכבים מתקדם עם תמונות
     */
    public function searchVehicles($criteria) {
        $sql = "SELECT DISTINCT v.*, sc.name as sub_category_name, mc.name as main_category_name,
                       u.first_name, u.last_name, u.is_premium,
                       vi.image_path as primary_image
                FROM vehicles v 
                JOIN sub_categories sc ON v.sub_category_id = sc.id
                JOIN main_categories mc ON sc.main_category_id = mc.id
                JOIN users u ON v.owner_id = u.id
                LEFT JOIN vehicle_activity_areas vaa ON v.id = vaa.vehicle_id
                LEFT JOIN vehicle_work_types vwt ON v.id = vwt.vehicle_id
                LEFT JOIN vehicle_images vi ON v.id = vi.vehicle_id AND vi.is_primary = 1
                WHERE v.is_active = 1 AND v.is_verified = 1";
        
        $params = [];
        
        if (!empty($criteria['main_category_id'])) {
            $sql .= " AND mc.id = :main_category_id";
            $params[':main_category_id'] = $criteria['main_category_id'];
        }
        
        if (!empty($criteria['sub_category_id'])) {
            $sql .= " AND sc.id = :sub_category_id";
            $params[':sub_category_id'] = $criteria['sub_category_id'];
        }
        
        if (!empty($criteria['work_type_id'])) {
            $sql .= " AND vwt.work_type_id = :work_type_id";
            $params[':work_type_id'] = $criteria['work_type_id'];
        }
        
        if (!empty($criteria['activity_area_id'])) {
            $sql .= " AND vaa.area_id = :area_id";
            $params[':area_id'] = $criteria['activity_area_id'];
        }
        
        if (isset($criteria['premium_only']) && $criteria['premium_only']) {
            $sql .= " AND u.is_premium = 1";
        }
        
        $sql .= " ORDER BY u.is_premium DESC, v.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * קבלת ביקורות רכב
     */
    public function getVehicleReviews($vehicleId) {
        return $this->db->fetchAll(
            "SELECT r.*, u.first_name, u.last_name 
             FROM reviews r 
             JOIN users u ON r.reviewer_id = u.id 
             WHERE r.vehicle_id = :vehicle_id AND r.is_published = 1 
             ORDER BY r.created_at DESC",
            [':vehicle_id' => $vehicleId]
        );
    }
    
    /**
     * חישוב דירוג ממוצע
     */
    public function getVehicleRating($vehicleId) {
        $result = $this->db->fetchOne(
            "SELECT 
                AVG(quality_rating) as avg_quality,
                AVG(service_rating) as avg_service, 
                AVG(price_rating) as avg_price,
                AVG(reliability_rating) as avg_reliability,
                AVG(availability_rating) as avg_availability,
                COUNT(*) as review_count
             FROM reviews 
             WHERE vehicle_id = :vehicle_id AND is_published = 1",
            [':vehicle_id' => $vehicleId]
        );
        
        if ($result && $result['review_count'] > 0) {
            $result['overall_rating'] = round(
                ($result['avg_quality'] + $result['avg_service'] + $result['avg_price'] + 
                 $result['avg_reliability'] + $result['avg_availability']) / 5, 1
            );
        }
        
        return $result;
    }
    
    /**
     * מחיקת רכב
     */
    public function deleteVehicle($id) {
        return $this->db->update('vehicles', ['is_active' => 0], 'id = :id', [':id' => $id]);
    }
    
    /**
     * אימות רכב על ידי מנהל
     */
    public function verifyVehicle($id, $verified = true) {
        return $this->db->update('vehicles', ['is_verified' => $verified ? 1 : 0], 'id = :id', [':id' => $id]);
    }
}
?>