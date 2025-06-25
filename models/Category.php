<?php
// models/Category.php - מודל קטגוריות

class Category {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * קטגוריות ראשיות
     */
    public function getMainCategories() {
        return $this->db->fetchAll(
            "SELECT * FROM main_categories WHERE is_active = 1 ORDER BY sort_order, name"
        );
    }
    
    public function getMainCategoryById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM main_categories WHERE id = :id",
            [':id' => $id]
        );
    }
    
    public function createMainCategory($data) {
        return $this->db->insert('main_categories', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0
        ]);
    }
    
    /**
     * תתי קטגוריות
     */
    public function getSubCategories($mainCategoryId = null) {
        $sql = "SELECT sc.*, mc.name as main_category_name 
                FROM sub_categories sc 
                JOIN main_categories mc ON sc.main_category_id = mc.id 
                WHERE sc.is_active = 1";
        $params = [];
        
        if ($mainCategoryId) {
            $sql .= " AND sc.main_category_id = :main_id";
            $params[':main_id'] = $mainCategoryId;
        }
        
        $sql .= " ORDER BY sc.sort_order, sc.name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getSubCategoryById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM sub_categories WHERE id = :id", 
            [':id' => $id]
        );
    }
    
    public function getSubCategoryNameById($id) {
        $result = $this->db->fetchOne(
            "SELECT sc.name as sub_name, mc.name as main_name 
             FROM sub_categories sc 
             JOIN main_categories mc ON sc.main_category_id = mc.id 
             WHERE sc.id = :id",
            [':id' => $id]
        );
        return $result ? $result['main_name'] . ' → ' . $result['sub_name'] : 'לא נמצא';
    }
    
    public function createSubCategory($data) {
        return $this->db->insert('sub_categories', [
            'main_category_id' => $data['main_category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0
        ]);
    }
    
    /**
     * מאפיינים טכניים
     */
    public function getTechnicalAttributes($subCategoryId) {
        return $this->db->fetchAll(
            "SELECT * FROM technical_attributes 
             WHERE sub_category_id = :sub_id 
             ORDER BY sort_order, attribute_name",
            [':sub_id' => $subCategoryId]
        );
    }
    
    public function createTechnicalAttribute($data) {
        return $this->db->insert('technical_attributes', [
            'sub_category_id' => $data['sub_category_id'],
            'attribute_name' => $data['attribute_name'],
            'attribute_type' => $data['attribute_type'],
            'is_required' => $data['is_required'] ?? 0,
            'options' => $data['options'] ?? null,
            'unit' => $data['unit'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0
        ]);
    }
    
    /**
     * עדכון/מחיקה
     */
    public function updateMainCategory($id, $data) {
        return $this->db->update('main_categories', $data, 'id = :id', [':id' => $id]);
    }
    
    public function updateSubCategory($id, $data) {
        return $this->db->update('sub_categories', $data, 'id = :id', [':id' => $id]);
    }
    
    public function deleteMainCategory($id) {
        return $this->db->update('main_categories', ['is_active' => 0], 'id = :id', [':id' => $id]);
    }
    
    public function deleteSubCategory($id) {
        return $this->db->update('sub_categories', ['is_active' => 0], 'id = :id', [':id' => $id]);
    }
    /**
 * סוגי עבודות
 */
public function getWorkTypes($mainCategoryId = null) {
    $sql = "SELECT wt.*, mc.name as main_category_name 
            FROM work_types wt 
            JOIN main_categories mc ON wt.main_category_id = mc.id 
            WHERE wt.is_active = 1";
    $params = [];
    
    if ($mainCategoryId) {
        $sql .= " AND wt.main_category_id = :main_id";
        $params[':main_id'] = $mainCategoryId;
    }
    
    $sql .= " ORDER BY wt.sort_order, wt.work_name";
    
    return $this->db->fetchAll($sql, $params);
}

public function getWorkTypeById($id) {
    return $this->db->fetchOne(
        "SELECT * FROM work_types WHERE id = :id", 
        [':id' => $id]
    );
}

public function createWorkType($data) {
    return $this->db->insert('work_types', [
        'main_category_id' => $data['main_category_id'],
        'work_name' => $data['work_name'],
        'description' => $data['description'] ?? null,
        'sort_order' => $data['sort_order'] ?? 0
    ]);
}
}

?>