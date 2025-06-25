<?php
// api/system.php - API פעולות מערכת
header('Content-Type: application/json');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$action = $_GET['action'] ?? '';

// בדיקת הרשאות מנהל
if (!$auth->checkPermission('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'אין הרשאה']);
    exit;
}

$db = new Database();

try {
    switch ($action) {
        case 'clear_cache':
            clearSystemCache();
            echo json_encode(['success' => true, 'message' => 'המטמון נוקה']);
            break;
            
        case 'export_data':
            exportSystemData();
            break;
            
        case 'get_stats':
            $stats = getSystemStats($db);
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'backup_database':
            $backup = backupDatabase($db);
            echo json_encode($backup);
            break;
            
        case 'cleanup_old_data':
            $cleanup = cleanupOldData($db);
            echo json_encode($cleanup);
            break;
            
        default:
            throw new Exception('פעולה לא מוכרת');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function clearSystemCache() {
    // ניקוי קבצי מטמון זמניים
    $cacheDir = __DIR__ . '/../cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // ניקוי sessions ישנים
    $sessionDir = session_save_path();
    if ($sessionDir && is_dir($sessionDir)) {
        $files = glob($sessionDir . '/sess_*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $now - 3600) {
                unlink($file);
            }
        }
    }
}

function exportSystemData() {
    $db = new Database();
    
    // הגדרת headers לקובץ CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nahagim_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // רשימת טבלאות לייצוא
    $tables = ['users', 'orders', 'quotes', 'vehicles', 'reviews'];
    
    foreach ($tables as $table) {
        fputcsv($output, ["=== $table ==="]);
        
        // קבלת עמודות
        $columns = $db->fetchAll("SHOW COLUMNS FROM $table");
        $headers = array_column($columns, 'Field');
        fputcsv($output, $headers);
        
        // קבלת נתונים
        $data = $db->fetchAll("SELECT * FROM $table");
        foreach ($data as $row) {
            fputcsv($output, array_values($row));
        }
        
        fputcsv($output, []);
    }
    
    fclose($output);
    exit;
}

function getSystemStats($db) {
    return [
        'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
        'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
        'total_vehicles' => $db->fetchOne("SELECT COUNT(*) as count FROM vehicles")['count'],
        'total_quotes' => $db->fetchOne("SELECT COUNT(*) as count FROM quotes")['count'],
        'premium_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_premium = 1")['count'],
        'active_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status != 'closed'")['count'],
        'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")['count'],
        'database_size' => getDatabaseSize($db)
    ];
}

function getDatabaseSize($db) {
    $result = $db->fetchOne(
        "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
         FROM information_schema.tables 
         WHERE table_schema = DATABASE()"
    );
    return $result['size_mb'] . ' MB';
}

function backupDatabase($db) {
    $backupDir = __DIR__ . '/../backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    try {
        // קבלת רשימת טבלאות
        $tables = $db->fetchAll("SHOW TABLES");
        $tableColumn = array_keys($tables[0])[0];
        
        $backup = "-- Nahagim System Backup\n";
        $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $tableName = $table[$tableColumn];
            
            // יצירת טבלה
            $createTable = $db->fetchOne("SHOW CREATE TABLE `$tableName`");
            $backup .= "\n-- Table: $tableName\n";
            $backup .= "DROP TABLE IF EXISTS `$tableName`;\n";
            $backup .= $createTable['Create Table'] . ";\n\n";
            
            // נתוני הטבלה
            $rows = $db->fetchAll("SELECT * FROM `$tableName`");
            if (!empty($rows)) {
                $columns = implode('`, `', array_keys($rows[0]));
                $backup .= "INSERT INTO `$tableName` (`$columns`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escapedRow = array_map(function($value) use ($db) {
                        return $value === null ? 'NULL' : $db->pdo->quote($value);
                    }, array_values($row));
                    $values[] = '(' . implode(', ', $escapedRow) . ')';
                }
                $backup .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        file_put_contents($filepath, $backup);
        
        return [
            'success' => true,
            'message' => 'גיבוי נוצר בהצלחה',
            'filename' => $filename,
            'size' => round(filesize($filepath) / 1024, 2) . ' KB'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'שגיאה ביצירת גיבוי: ' . $e->getMessage()
        ];
    }
}

function cleanupOldData($db) {
    $cleaned = 0;
    
    try {
        // מחיקת הזמנות ישנות (מעל 6 חודשים) ללא הצעות
        $result = $db->query(
            "DELETE FROM orders 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) 
             AND status = 'closed' 
             AND id NOT IN (SELECT DISTINCT order_id FROM quotes)"
        );
        $cleaned += $result->rowCount();
        
        // מחיקת תמונות של הזמנות שנמחקו
        $orphanImages = $db->fetchAll(
            "SELECT image_path FROM order_images oi 
             WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.id = oi.order_id)"
        );
        
        foreach ($orphanImages as $image) {
            $imagePath = __DIR__ . '/../uploads/' . $image['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        $db->query("DELETE FROM order_images WHERE order_id NOT IN (SELECT id FROM orders)");
        $cleaned += count($orphanImages);
        
        // מחיקת סשנים ישנים מ-DB (אם משתמשים ב-DB sessions)
        $db->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        return [
            'success' => true,
            'message' => "נוקו $cleaned פריטים ישנים",
            'cleaned_items' => $cleaned
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'שגיאה בניקוי: ' . $e->getMessage()
        ];
    }
}
?>