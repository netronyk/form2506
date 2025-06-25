<?php
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Notification.php';

$db = new Database();
$notification = new Notification();

// מציאת מנויים שיפגו בעוד 7 ימים
$expiringUsers = $db->fetchAll(
    "SELECT id, first_name, last_name, premium_expires, 
            DATEDIFF(premium_expires, CURDATE()) as days_left
     FROM users 
     WHERE user_type = 'vehicle_owner' 
     AND is_premium = 1 
     AND premium_expires BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     AND notification_whatsapp = 1"
);

foreach ($expiringUsers as $user) {
    $notification->notifySubscriptionExpiring(
        $user['id'],
        $user['days_left'],
        date('d/m/Y', strtotime($user['premium_expires']))
    );
}

echo "נשלחו " . count($expiringUsers) . " תזכורות מנוי\n";
?>