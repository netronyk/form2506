<?php
// api/categories.php - API לקטגוריות
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Category.php';

$action = $_GET['action'] ?? '';
$category = new Category();

try {
    switch ($action) {
        case 'get_sub_categories':
            $mainId = $_GET['main_id'] ?? null;
            if (!$mainId) {
                throw new Exception('Missing main_id parameter');
            }
            
            $subCategories = $category->getSubCategories($mainId);
            $workTypes = $category->getWorkTypes($mainId);
            
            echo json_encode([
                'success' => true,
                'sub_categories' => $subCategories,
                'work_types' => $workTypes
            ]);
            break;
            
        case 'get_attributes':
            $subId = $_GET['sub_id'] ?? null;
            if (!$subId) {
                throw new Exception('Missing sub_id parameter');
            }
            
            $attributes = $category->getTechnicalAttributes($subId);
            
            echo json_encode([
                'success' => true,
                'attributes' => $attributes
            ]);
            break;
            
        case 'get_main_categories':
            $mainCategories = $category->getMainCategories();
            
            echo json_encode([
                'success' => true,
                'main_categories' => $mainCategories
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>