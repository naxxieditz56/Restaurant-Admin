<?php
require_once '../includes/config.php';
requireLogin();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: reservations.php');
        exit();
    }
    
    $reservation_id = (int)$_POST['reservation_id'];
    $new_status = $_POST['status'];
    $table_number = $_POST['table_number'] ?? null;
    
    try {
        $stmt = $pdo->prepare("UPDATE reservations SET status = ?, table_number = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $table_number, $reservation_id]);
        
        logActivity('update_reservation', "Updated reservation #$reservation_id to $new_status");
        $_SESSION['success'] = 'Reservation updated successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error updating reservation: ' . $e->getMessage();
    }
    
    header('Location: reservations.php');
    exit();
}

// Handle manual reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reservation'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: reservations.php');
        exit();
    }
    
    $customer_name = sanitize($_POST['customer_name']);
    $customer_email = sanitize($_POST['customer_email']);
    $customer_phone = sanitize($_POST['customer_phone']);
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];
    $party_size = (int)$_POST['party_size'];
    $special_requests = sanitize($_POST['special_requests']);
    $status = $_POST['status'];
    
    // Generate reservation code
    $reservation_code = 'JF' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    
    try {
        $stmt = $pdo->prepare("INSERT INTO reservations 
            (reservation_code, customer_name, customer_email, customer_phone, reservation_date, 
             reservation_time, party_size, special_requests, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $reservation_code,
            $customer_name,
            $customer_email,
            $customer_phone,
            $reservation_date,
            $reservation_time,
            $party_size,
            $special_requests,
            $status
        ]);
        
        logActivity('add_reservation', "Added manual reservation for $customer_name");
        $_SESSION['success'] = 'Reservation added successfully.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error adding reservation: ' . $e->getMessage();
    }
    
    header('Location: reservations.php');
    exit();
}

// Get reservations
$query = "SELECT * FROM reservations";
$params = [];

if ($status) {
    $query .= " WHERE status = ?";
    $params[] = $status;
}

$query .= " ORDER BY reservation_date DESC, reservation_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'seated' THEN 1 ELSE 0 END) as seated,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM reservations");
$stats = $stmt->fetch();

// Get today's reservations
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_date = ? ORDER BY reservation_time");
$stmt->execute([$today]);
$today_reservations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations | Jack Fry's Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="admin-body">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h1>Reservations</h1>
                <p>Manage customer reservations and bookings</p>
            </div>
            <div class="top-bar-right">
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="showModal('addReservationModal')">
                        <i class="fas fa-calendar-plus"></i> Add Reservation
                    </button>
                    <a href="reservations.php?export=today" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export Today's
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Reservations</p>
                </div>
                <div class="stat-icon" style="background: #3b82f6;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-icon" style="background: #f59e0b;">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3><?php echo $stats['confirmed']; ?></h3>
                    <p>Confirmed</p>
                </div>
                <div class="stat-icon" style="background: #10b981;">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <h3><?php echo count($today_reservations); ?></h3>
                    <p>Today's Reservations</p>
                </div>
                <div class="stat-icon" style="background: #8b5cf6;">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>
        
        <!-- Today's Reservations -->
        <div class="content-card mb-4">
            <div class="card-header">
                <h3>Today's Reservations (<?php echo date('F j, Y'); ?>)</h3>
                <a href="reservations.php?status=confirmed" class="btn-link">View All Confirmed</a>
            </div>
            <div class="card-body">
                <?php if (empty($today_reservations)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No reservations for today</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Customer</th>
                                    <th>Party Size</th>
                                    <th>Status</th>
                                    <th>Table</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_reservations as $res): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo $res['customer_name']; ?></strong><br>
                                        <small><?php echo $res['customer_phone']; ?></small>
                                    </td>
                                    <td><?php echo $res['party_size']; ?> people</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $res['status']; ?>">
                                            <?php echo ucfirst($res['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($res['table_number']): ?>
                                            <span class="badge">Table <?php echo $res['table_number']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" title="Update Status" 
                                                    onclick="updateReservation(<?php echo $res['id']; ?>)">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                            <a href="reservations.php?action=view&id=<?php echo $res['id']; ?>" 
                                               class="btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- All Reservations -->
        <div class="content-card">
            <div class="card-header">
                <h3>All Reservations</h3>
                <div class="btn-group">
                    <a href="reservations.php" class="btn btn-sm <?php echo !$status ? 'btn-primary' : 'btn-outline'; ?>">All</a>
                    <a href="reservations.php?status=pending" class="btn btn-sm <?php echo $status == 'pending' ? 'btn-primary' : 'btn-outline'; ?>">Pending</a>
                    <a href="reservations.php?status=confirmed" class="btn btn-sm <?php echo $status == 'confirmed' ? 'btn-primary' : 'btn-outline'; ?>">Confirmed</a>
                    <a href="reservations.php?status=seated" class="btn btn-sm <?php echo $status == 'seated' ? 'btn-primary' : 'btn-outline'; ?>">Seated</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Customer</th>
                                <th>Date & Time</th>
                                <th>Party Size</th>
                                <th>Status</th>
                                <th>Table</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $res['reservation_code']; ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo $res['customer_name']; ?></strong><br>
                                    <small><?php echo $res['customer_email']; ?></small><br>
                                    <small><?php echo $res['customer_phone']; ?></small>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($res['reservation_time'])); ?></small>
                                </td>
                                <td><?php echo $res['party_size']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $res['status']; ?>">
                                        <?php echo ucfirst($res['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($res['table_number']): ?>
                                        <span class="badge">Table <?php echo $res['table_number']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d', strtotime($res['created_at'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($res['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon" title="Update Status" 
                                                onclick="updateReservation(<?php echo $res['id']; ?>)">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <a href="reservations.php?action=view&id=<?php echo $res['id']; ?>" 
                                           class="btn-icon" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="reservations.php?action=delete&id=<?php echo $res['id']; ?>" 
                                           class="btn-icon" title="Delete" 
                                           onclick="return confirm('Are you sure you want to delete this reservation?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Reservation Modal -->
    <div class="modal" id="addReservationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Reservation</h3>
                <button class="modal-close" onclick="hideModal('addReservationModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_reservation">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required" for="customer_name">Customer Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required" for="customer_phone">Phone Number</label>
                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="customer_email">Email Address</label>
                            <input type="email" class="form-control" id="customer_email" name="customer_email">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required" for="party_size">Party Size</label>
                            <select class="form-control" id="party_size" name="party_size" required>
                                <option value="">Select</option>
                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> person<?php echo $i > 1 ? 's' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required" for="reservation_date">Date</label>
                            <input type="date" class="form-control" id="reservation_date" name="reservation_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required" for="reservation_time">Time</label>
                            <select class="form-control" id="reservation_time" name="reservation_time" required>
                                <option value="">Select Time</option>
                                <?php
                                // Generate time slots
                                $start = strtotime('17:00'); // 5:00 PM
                                $end = strtotime('22:00');   // 10:00 PM
                                $interval = 30 * 60; // 30 minutes in seconds
                                
                                for ($time = $start; $time <= $end; $time += $interval) {
                                    echo '<option value="' . date('H:i:s', $time) . '">' . date('g:i A', $time) . '</option>';
