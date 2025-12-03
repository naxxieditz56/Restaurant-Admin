<?php
// Helper Functions

function getSetting($key) {
    global $pdo;
    
    static $settings = [];
    
    if (empty($settings)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    return $settings[$key] ?? '';
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

function generate_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function send_email($to, $subject, $message, $headers = []) {
    $default_headers = [
        'From' => getSetting('contact_email'),
        'Reply-To' => getSetting('contact_email'),
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $headers = array_merge($default_headers, $headers);
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= "$key: $value\r\n";
    }
    
    return mail($to, $subject, $message, $header_string);
}

function resize_image($file_path, $max_width, $max_height) {
    list($width, $height, $type) = getimagesize($file_path);
    
    if ($width <= $max_width && $height <= $max_height) {
        return true;
    }
    
    $ratio = $width / $height;
    
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($file_path);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($file_path);
            break;
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($file_path);
            break;
        default:
            return false;
    }
    
    $dst = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($dst, $file_path, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($dst, $file_path, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($dst, $file_path);
            break;
    }
    
    imagedestroy($src);
    imagedestroy($dst);
    
    return true;
}

function backup_database() {
    global $pdo;
    
    $backup_dir = UPLOAD_PATH . 'backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Get all tables
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $output = '';
    foreach ($tables as $table) {
        // Drop table if exists
        $output .= "DROP TABLE IF EXISTS `$table`;\n\n";
        
        // Create table
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $output .= $row[1] . ";\n\n";
        
        // Insert data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keys = array_map(function($key) {
                return "`$key`";
            }, array_keys($row));
            
            $values = array_map(function($value) use ($pdo) {
                if (is_null($value)) {
                    return 'NULL';
                } else {
                    return $pdo->quote($value);
                }
            }, array_values($row));
            
            $output .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
        }
        $output .= "\n\n";
    }
    
    // Write to file
    file_put_contents($backup_file, $output);
    
    // Compress
    $zip_file = str_replace('.sql', '.zip', $backup_file);
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($backup_file, basename($backup_file));
        $zip->close();
        unlink($backup_file);
        return $zip_file;
    }
    
    return $backup_file;
}

function check_permission($required_role) {
    $role_hierarchy = [
        'super_admin' => 3,
        'admin' => 2,
        'editor' => 1
    ];
    
    if (!isset($role_hierarchy[$_SESSION['admin_role']]) || 
        !isset($role_hierarchy[$required_role]) ||
        $role_hierarchy[$_SESSION['admin_role']] < $role_hierarchy[$required_role]) {
        return false;
    }
    
    return true;
}

function get_browser_info() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $browser_name = 'Unknown';
    $platform = 'Unknown';
    
    // Platform
    if (preg_match('/linux/i', $user_agent)) {
        $platform = 'Linux';
    } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
        $platform = 'Mac';
    } elseif (preg_match('/windows|win32/i', $user_agent)) {
        $platform = 'Windows';
    }
    
    // Browser
    if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
        $browser_name = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser_name = 'Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        $browser_name = 'Chrome';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser_name = 'Safari';
    } elseif (preg_match('/Opera/i', $user_agent)) {
        $browser_name = 'Opera';
    } elseif (preg_match('/Netscape/i', $user_agent)) {
        $browser_name = 'Netscape';
    }
    
    return [
        'browser' => $browser_name,
        'platform' => $platform,
        'user_agent' => $user_agent
    ];
}
?>
