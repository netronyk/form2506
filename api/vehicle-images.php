<?php
// api/vehicle-images.php - API לניהול תמונות רכבים
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/Vehicle.php';

// בדיקת הרשאות
$auth = new Auth();
if (!$auth->checkPermission('vehicle_owner')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
    exit();
}

$currentUser = $auth->getCurrentUser();
$vehicle = new Vehicle();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'delete':
            $imageId = $_POST['image_id'] ?? null;
            if (!$imageId) {
                throw new Exception('Missing image_id parameter');
            }
            
            $result = $vehicle->deleteVehicleImage($imageId, $currentUser['id']);
            echo json_encode($result);
            break;
            
        case 'set_primary':
            $imageId = $_POST['image_id'] ?? null;
            if (!$imageId) {
                throw new Exception('Missing image_id parameter');
            }
            
            $result = $vehicle->setPrimaryImage($imageId, $currentUser['id']);
            echo json_encode($result);
            break;
            
        case 'upload':
            if (!isset($_FILES['image'])) {
                throw new Exception('No image file uploaded');
            }
            
            $vehicleId = $_POST['vehicle_id'] ?? null;
            if (!$vehicleId) {
                throw new Exception('Missing vehicle_id parameter');
            }
            
            $result = $vehicle->uploadVehicleImage($vehicleId, $_FILES['image'], $currentUser['id']);
            echo json_encode($result);
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