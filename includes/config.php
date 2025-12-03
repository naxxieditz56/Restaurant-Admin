<?php
session_start();
ob_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jackfrys_admin');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site Configuration
define('SITE_NAME', 'Jack Fry\'s Admin');
define('SITE_URL', 'http://localhost/jackfrys-admin');
define('ADMIN_PATH', '/admin');

// File Upload Configuration
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/admin/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Timezone
date_default_timezone_set('America/New_York');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Function to log activity
function logActivity($action, $description = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['admin_id'] ?? null,
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Security Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// File Upload Function
function uploadFile($file, $directory = '') {
    $targetDir = UPLOAD_PATH . $directory;
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($file['name']);
    $targetFile = $targetDir . '/' . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Validate file
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File is too large.'];
    }
    
    // Allow certain file formats
    if (!in_array($fileType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return [
            'success' => true,
            'file_name' => $fileName,
            'file_path' => '/uploads/' . $directory . '/' . $fileName
        ];
    } else {
        return ['success' => false, 'message' => 'Sorry, there was an error uploading your file.'];
    }
}
?>
