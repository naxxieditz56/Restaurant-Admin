<?php
require_once 'includes/config.php';
requireLogin();

// Get statistics for dashboard
$stats = [];

// Total Reservations
$stmt = $pdo->query("SELECT COUNT(*) as total FROM reservations");
$stats['total_reservations'] = $stmt->fetch()['total'];

// Pending Reservations
$stmt = $pdo->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'");
$stats['pending_reservations'] = $stmt->fetch()['total'];

// Total Menu Items
$stmt = $pdo->query("SELECT COUNT(*) as total FROM menu_items WHERE active = 1");
$stats['total_menu_items'] = $stmt->fetch()['total'];

// Total Testimonials
$stmt = $pdo->query("SELECT COUNT(*) as total FROM testimonials WHERE approved = 1");
$stats['total_testimonials'] = $stmt->fetch()['total'];

// Recent Reservations
$stmt = $pdo->prepare("SELECT * FROM reservations ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_reservations = $stmt->fetchAll();

// Recent Activity
$stmt = $pdo->prepare("SELECT al.*, au.username, au.full_name 
                       FROM activity_log al 
                       LEFT JOIN admin_users au ON al.user_id = au.id 
                       ORDER BY al.created_at DESC LIMIT 10");
$stmt->execute();
$recent_activity = $stmt->fetchAll();

// Reservations by status for chart
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM reservations GROUP BY status");
$reservations_by_status = $stmt->fetchAll();

// Menu items by category for chart
$stmt = $pdo->query("SELECT c.name, COUNT(mi.id) as count 
                     FROM menu_categories c 
                     LEFT JOIN menu_items mi ON c.id = mi.category_id 
                     WHERE mi.active = 1 
                     GROUP BY c.id");
$menu_by_category = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Jack Fry's Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
    <!-- Sidebar -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['admin_name']; ?>!</p>
            </div>
            <div class="top-bar-right">
                <div class="quick-stats">
                    <div class="stat">
                        <i class="fas fa-calendar-check"></i>
                        <span><?php echo $stats['total_reservations']; ?> Reservations</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $stats['pending_reservations']; ?> Pending</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #4f46e5;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_reservations']; ?></h3>
                    <p>Total Reservations</p>
                </div>
                <a href="pages/reservations.php" class="stat-link">View All</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #10b981;">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_menu_items']; ?></h3>
                    <p>Menu Items</p>
                </div>
                <a href="pages/menu-management.php" class="stat-link">Manage</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f59e0b;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_testimonials']; ?></h3>
                    <p>Testimonials</p>
                </div>
                <a href="pages/testimonials.php" class="stat-link">Review</a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #ef4444;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_reservations']; ?></h3>
                    <p>Pending Actions</p>
                </div>
                <a href="pages/reservations.php?status=pending" class="stat-link">Take Action</a>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Recent Reservations -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Reservations</h3>
                    <a href="pages/reservations.php" class="btn-link">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Date & Time</th>
                                    <th>Party Size</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reservations as $reservation): ?>
                                <tr>
                                    <td>#<?php echo $reservation['reservation_code']; ?></td>
                                    <td>
                                        <strong><?php echo $reservation['customer_name']; ?></strong><br>
                                        <small><?php echo $reservation['customer_email']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?><br>
                                        <small><?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?></small>
                                    </td>
                                    <td><?php echo $reservation['party_size']; ?> people</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $reservation['status']; ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="pages/reservations.php?action=view&id=<?php echo $reservation['id']; ?>" 
                                               class="btn-icon" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="pages/reservations.php?action=edit&id=<?php echo $reservation['id']; ?>" 
                                               class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
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
            
            <!-- Recent Activity -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo $activity['full_name'] ?? 'System'; ?></strong> 
                                   <?php echo $activity['action']; ?></p>
                                <small><?php echo time_ago($activity['created_at']); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Reservations Overview</h3>
                </div>
                <div class="card-body">
                    <div id="reservationsChart" style="height: 250px;"></div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="pages/menu-management.php?action=add" class="quick-action">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add Menu Item</span>
                        </a>
                        <a href="pages/reservations.php?action=add" class="quick-action">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Add Reservation</span>
                        </a>
                        <a href="pages/gallery.php?action=upload" class="quick-action">
                            <i class="fas fa-image"></i>
                            <span>Upload Photos</span>
                        </a>
                        <a href="pages/testimonials.php?action=add" class="quick-action">
                            <i class="fas fa-comment-medical"></i>
                            <span>Add Testimonial</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Reservations Chart
        const reservationsData = {
            series: [{
                name: 'Reservations',
                data: [
                    <?php echo array_sum(array_column(array_filter($reservations_by_status, fn($s) => $s['status'] === 'pending'), 'count')); ?>,
                    <?php echo array_sum(array_column(array_filter($reservations_by_status, fn($s) => $s['status'] === 'confirmed'), 'count')); ?>,
                    <?php echo array_sum(array_column(array_filter($reservations_by_status, fn($s) => $s['status'] === 'completed'), 'count')); ?>,
                    <?php echo array_sum(array_column(array_filter($reservations_by_status, fn($s) => $s['status'] === 'cancelled'), 'count')); ?>
                ]
            }],
            chart: {
                type: 'bar',
                height: 250,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: false,
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
            },
            colors: ['#f59e0b', '#10b981', '#3b82f6', '#ef4444']
        };
        
        const chart = new ApexCharts(document.querySelector("#reservationsChart"), reservationsData);
        chart.render();
        
        // Time ago function
        function time_ago(date) {
            const seconds = Math.floor((new Date() - new Date(date)) / 1000);
            let interval = Math.floor(seconds / 31536000);
            
            if (interval > 1) return interval + " years ago";
            interval = Math.floor(seconds / 2592000);
            if (interval > 1) return interval + " months ago";
            interval = Math.floor(seconds / 86400);
            if (interval > 1) return interval + " days ago";
            interval = Math.floor(seconds / 3600);
            if (interval > 1) return interval + " hours ago";
            interval = Math.floor(seconds / 60);
            if (interval > 1) return interval + " minutes ago";
            return Math.floor(seconds) + " seconds ago";
        }
    </script>
</body>
</html>
