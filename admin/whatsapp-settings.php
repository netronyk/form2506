<?php
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/WhatsApp.php';

$auth = new Auth();
if (!$auth->checkPermission('admin')) {
    redirect('../login.php');
}

$whatsapp = new WhatsApp();
$message = '';
$error = '';

// טיפול בעדכון הגדרות
if ($_POST && isset($_POST['update_settings'])) {
    $result = $whatsapp->updateSettings(
        $_POST['instance_id'],
        $_POST['api_token'],
        $_POST['api_endpoint']
    );
    
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// בדיקת סטטוס API
$apiStatus = $whatsapp->checkApiStatus();
?>

<!-- HTML לדף הגדרות ווטסאפ -->
<div class="card">
    <div class="card-header">
        <h3>⚙️ הגדרות ווטסאפ API</h3>
    </div>
    <div class="card-body">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Instance ID</label>
                <input type="text" name="instance_id" class="form-control" required
                       placeholder="1234567890123456789">
            </div>
            
            <div class="form-group">
                <label class="form-label">API Token</label>
                <input type="text" name="api_token" class="form-control" required
                       placeholder="a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6">
            </div>
            
            <div class="form-group">
                <label class="form-label">API Endpoint</label>
                <input type="url" name="api_endpoint" class="form-control" 
                       value="https://api.green-api.com" required>
            </div>
            
            <button type="submit" name="update_settings" class="btn btn-primary">
                שמור הגדרות
            </button>
        </form>
        
        <!-- סטטוס API -->
        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
            <h5>📊 סטטוס API</h5>
            <?php if ($apiStatus['success']): ?>
                <span class="badge badge-success">✅ פעיל</span>
                <p>סטטוס: <?php echo $apiStatus['status']; ?></p>
            <?php else: ?>
                <span class="badge badge-danger">❌ לא פעיל</span>
                <p><?php echo $apiStatus['message']; ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>