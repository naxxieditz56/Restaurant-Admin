<?php
require_once '../includes/config.php';
requireLogin();

// Check permissions
if ($_SESSION['admin_role'] === 'editor') {
    header('Location: ../index.php');
    exit();
}

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM menu_categories ORDER BY display_order");
$categories = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: menu-management.php');
        exit();
    }
    
    if ($_POST['action'] === 'add_category') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $display_order = (int)$_POST['display_order'];
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO menu_categories (name, slug, description, display_order) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $description, $display_order]);
            
            logActivity('add_category', "Added menu category: $name");
            $_SESSION['success'] = 'Category added successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error adding category: ' . $e->getMessage();
        }
    }
    elseif ($_POST['action'] === 'add_item') {
        $category_id = (int)$_POST['category_id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = (float)$_POST['price'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $display_order = (int)$_POST['display_order'];
        $active = isset($_POST['active']) ? 1 : 0;
        $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
        
        // Handle dietary tags
        $dietary_tags = [];
        if (isset($_POST['dietary_tags'])) {
            $dietary_tags = $_POST['dietary_tags'];
        }
        
        // Handle image upload
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload = uploadFile($_FILES['image'], 'menu');
            if ($upload['success']) {
                $image = $upload['file_path'];
            }
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO menu_items 
                (category_id, name, slug, description, price, featured, dietary_tags, image, display_order, active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $category_id,
                $name,
                $slug,
                $description,
                $price,
                $featured,
                json_encode($dietary_tags),
                $image,
                $display_order,
                $active
            ]);
            
            logActivity('add_menu_item', "Added menu item: $name");
            $_SESSION['success'] = 'Menu item added successfully.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error adding menu item: ' . $e->getMessage();
        }
    }
    
    header('Location: menu-management.php');
    exit();
}

// Handle delete action
if ($action === 'delete' && $id > 0) {
    $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('delete_menu_item', "Deleted menu item ID: $id");
    $_SESSION['success'] = 'Menu item deleted successfully.';
    
    header('Location: menu-management.php');
    exit();
}

// Get all menu items with category names
$stmt = $pdo->query("
    SELECT mi.*, mc.name as category_name 
    FROM menu_items mi 
    LEFT JOIN menu_categories mc ON mi.category_id = mc.id 
    ORDER BY mc.display_order, mi.display_order
");
$menu_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management | Jack Fry's Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="admin-body">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h1>Menu Management</h1>
                <p>Manage your restaurant's menu items and categories</p>
            </div>
            <div class="top-bar-right">
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="showModal('addCategoryModal')">
                        <i class="fas fa-folder-plus"></i> Add Category
                    </button>
                    <button class="btn btn-success" onclick="showModal('addItemModal')">
                        <i class="fas fa-utensils"></i> Add Menu Item
                    </button>
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
        
        <!-- Tabs -->
        <div class="tabs">
            <ul class="tab-list">
                <li class="tab-item">
                    <a href="#menu-items" class="tab-link active">Menu Items</a>
                </li>
                <li class="tab-item">
                    <a href="#categories" class="tab-link">Categories</a>
                </li>
                <li class="tab-item">
                    <a href="#preview" class="tab-link">Preview</a>
                </li>
            </ul>
        </div>
        
        <!-- Menu Items Tab -->
        <div id="menu-items" class="tab-pane active">
            <div class="content-card">
                <div class="card-header">
                    <h3>All Menu Items</h3>
                    <div class="form-group" style="margin: 0; width: 200px;">
                        <select class="form-control" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table" id="menuTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Featured</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_items as $item): ?>
                                <tr data-category="<?php echo $item['category_id']; ?>">
                                    <td><?php echo $item['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($item['image']): ?>
                                                <img src="<?php echo SITE_URL . $item['image']; ?>" 
                                                     alt="<?php echo $item['name']; ?>" 
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo $item['name']; ?></strong><br>
                                                <small class="text-muted"><?php echo substr($item['description'], 0, 50); ?>...</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $item['category_name']; ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <?php if ($item['featured']): ?>
                                            <span class="badge badge-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['active']): ?>
                                            <span class="status-badge status-confirmed">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-cancelled">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="menu-management.php?action=edit&id=<?php echo $item['id']; ?>" 
                                               class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="menu-management.php?action=delete&id=<?php echo $item['id']; ?>" 
                                               class="btn-icon" title="Delete" 
                                               onclick="return confirm('Are you sure you want to delete this item?')">
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
        
        <!-- Categories Tab -->
        <div id="categories" class="tab-pane">
            <div class="content-card">
                <div class="card-header">
                    <h3>Menu Categories</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Order</th>
                                    <th>Items</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menu_items WHERE category_id = ?");
                                    $stmt->execute([$category['id']]);
                                    $item_count = $stmt->fetch()['count'];
                                ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <strong><?php echo $category['name']; ?></strong><br>
                                        <small class="text-muted"><?php echo $category['slug']; ?></small>
                                    </td>
                                    <td><?php echo $category['description'] ?: '—'; ?></td>
                                    <td><?php echo $category['display_order']; ?></td>
                                    <td><?php echo $item_count; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" title="Edit" 
                                                    onclick="editCategory(<?php echo $category['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" title="Delete" 
                                                    onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
        
        <!-- Preview Tab -->
        <div id="preview" class="tab-pane">
            <div class="content-card">
                <div class="card-header">
                    <h3>Menu Preview</h3>
                    <a href="<?php echo SITE_URL; ?>/menu.html" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> View Live Menu
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($categories as $category): 
                            $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE category_id = ? AND active = 1 ORDER BY display_order");
                            $stmt->execute([$category['id']]);
                            $items = $stmt->fetchAll();
                        ?>
                        <?php if (!empty($items)): ?>
                        <div class="col-md-6 mb-4">
                            <h4 class="mb-3" style="color: #8B0000; border-bottom: 2px solid #D4AF37; padding-bottom: 5px;">
                                <?php echo $category['name']; ?>
                            </h4>
                            <?php foreach ($items as $item): ?>
                            <div class="menu-preview-item mb-3 p-3" style="border: 1px solid #e0d6c9; border-radius: 8px;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-0" style="color: #333;"><?php echo $item['name']; ?></h5>
                                    <span class="text-danger fw-bold">$<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                <p class="text-muted mb-2"><?php echo $item['description']; ?></p>
                                <?php if ($item['dietary_tags']): 
                                    $tags = json_decode($item['dietary_tags'], true);
                                    if (is_array($tags) && !empty($tags)): ?>
                                    <div class="dietary-tags">
                                        <?php foreach ($tags as $tag): ?>
                                        <span class="badge" style="background: #f3f4f6; color: #666; font-size: 0.75rem;">
                                            <?php echo ucfirst($tag); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div class="modal" id="addCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Menu Category</h3>
                <button class="modal-close" onclick="hideModal('addCategoryModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_category">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label class="form-label required" for="category_name">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="category_description">Description</label>
                        <textarea class="form-control" id="category_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="category_order">Display Order</label>
                        <input type="number" class="form-control" id="category_order" name="display_order" value="0" min="0">
                        <small class="form-text">Lower numbers appear first</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('addCategoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Menu Item Modal -->
    <div class="modal" id="addItemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Menu Item</h3>
                <button class="modal-close" onclick="hideModal('addItemModal')">&times;</button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                
