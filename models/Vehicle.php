<?php
// models/Vehicle.php - מודל רכבים

class Vehicle {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * קבלת רכבים
     */
    public function getAllVehicles($ownerId = null) {
        $sql = "SELECT v.*, sc.name as sub_category_name, mc.name as main_category_name,
                       u.first_name, u.last_name, u.is_premium
                FROM vehicles v 
                JOIN sub_categories sc ON v.sub_category_id = sc.id
                JOIN main_categories mc ON sc.main_category_id = mc.id
                JOIN users u ON v.owner_id = u.id
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
        return $this->db->fetchOne(
            "SELECT v.*, sc.name as sub_category_name, mc.name as main_category_name,
                    u.first_name, u.last_name, u.phone, u.email
             FROM vehicles v 
             JOIN sub_categories sc ON v.sub_category_id = sc.id
             JOIN main_categories mc ON sc.main_category_id = mc.id
             JOIN users u ON v.owner_id = u.id
             WHERE v.id = :id AND v.is_active = 1",
            [':id' => $id]
        );
    }
    
    /**
     * יצירת רכב
     */
    public function createVehicle($data) {
        try {
            $vehicleId = $this->db->insert('vehicles', [
                'owner_id' => $data['owner_id'],
                'sub_category_id' => $data['sub_category_id'],
                'vehicle_name' => $data['vehicle_name'],
                'description' => $data['description'] ?? null
            ]);
            
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
            
            return ['success' => true, 'vehicle_id' => $vehicleId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה ביצירת הרכב'];
        }
    }
    
    /**
     * עדכון רכב
     */
    public function updateVehicle($id, $data) {
        try {
            $updateData = [];
            if (isset($data['vehicle_name'])) $updateData['vehicle_name'] = $data['vehicle_name'];
            if (isset($data['description'])) $updateData['description'] = $data['description'];
            if (isset($data['sub_category_id'])) $updateData['sub_category_id'] = $data['sub_category_id'];
            
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
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'שגיאה בעדכון הרכב'];
        }
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
     * חיפוש רכבים
     */
    public function searchVehicles($criteria) {
        $sql = "SELECT DISTINCT v.*, sc.name as sub_category_name, mc.name as main_category_name,
                       u.first_name, u.last_name, u.is_premium
                FROM vehicles v 
                JOIN sub_categories sc ON v.sub_category_id = sc.id
                JOIN main_categories mc ON sc.main_category_id = mc.id
                JOIN users u ON v.owner_id = u.id
                LEFT JOIN vehicle_activity_areas vaa ON v.id = vaa.vehicle_id
                WHERE v.is_active = 1";
        
        $params = [];
        
        if (!empty($criteria['main_category_id'])) {
            $sql .= " AND mc.id = :main_category_id";
            $params[':main_category_id'] = $criteria['main_category_id'];
        }
        
        if (!empty($criteria['sub_category_id'])) {
            $sql .= " AND sc.id = :sub_category_id";
            $params[':sub_category_id'] = $criteria['sub_category_id'];
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