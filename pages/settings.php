<?php
require_once '../includes/config.php';
requireLogin();

// Only super admin can access settings
if ($_SESSION['admin_role'] !== 'super_admin') {
    header('Location: ../index.php');
    exit();
}

// Get all settings
$stmt = $pdo->query("SELECT * FROM settings ORDER BY category, setting_key");
$settings_raw = $stmt->fetchAll();

// Group settings by category
$settings = [];
foreach ($settings_raw as $setting) {
    $settings[$setting['category']][] = $setting;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: settings.php');
        exit();
    }
    
    // Update settings
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            $setting_value = is_array($value) ? json_encode($value) : trim($value);
            
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$setting_value, $setting_key]);
        }
    }
    
    // Handle file uploads
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === 0) {
        $upload = uploadFile($_FILES['site_logo'], 'site');
        if ($upload['success']) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_logo'");
            $stmt->execute([$upload['file_path']]);
        }
    }
    
    logActivity('update_settings', 'Updated website settings');
    $_SESSION['success'] = 'Settings updated successfully.';
    
    header('Location: settings.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Jack Fry's Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-body">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h1>Settings</h1>
                <p>Configure website settings and preferences</p>
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
        
        <!-- Settings Form -->
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- General Settings -->
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> General Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="setting_site_title">Site Title</label>
                                <input type="text" class="form-control" id="setting_site_title" 
                                       name="setting_site_title" 
                                       value="<?php echo getSetting('site_title'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="setting_site_tagline">Site Tagline</label>
                                <input type="text" class="form-control" id="setting_site_tagline" 
                                       name="setting_site_tagline" 
                                       value="<?php echo getSetting('site_tagline'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="site_logo">Site Logo</label>
                            <div class="file-upload" onclick="document.getElementById('site_logo').click()">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">
                                    <h4>Upload Logo</h4>
                                    <p>Click to upload or drag and drop</p>
                                    <p>Recommended: 300x100px, PNG with transparent background</p>
                                </div>
                                <input type="file" id="site_logo" name="site_logo" accept="image/*" 
                                       onchange="previewImage(this, 'logoPreview')">
                            </div>
                            
                            <?php $logo = getSetting('site_logo'); ?>
                            <?php if ($logo): ?>
                                <div id="logoPreview" class="mt-3">
                                    <img src="<?php echo SITE_URL . $logo; ?>" alt="Current Logo" 
                                         class="preview-image">
                                    <p class="text-muted mt-2">Current logo</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="setting_meta_description">Meta Description</label>
                            <textarea class="form-control" id="setting_meta_description" 
                                      name="setting_meta_description" rows="3"><?php echo getSetting('meta_description'); ?></textarea>
                            <small class="form-text">Brief description for search engines (150-160 characters)</small>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required" for="setting_contact_email">Contact Email</label>
                                <input type="email" class="form-control" id="setting_contact_email" 
                                       name="setting_contact_email" 
                                       value="<?php echo getSetting('contact_email'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label required" for="setting_contact_phone">Contact Phone</label>
                                <input type="tel" class="form-control" id="setting_contact_phone" 
                                       name="setting_contact_phone" 
                                       value="<?php echo getSetting('contact_phone'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required" for="setting_contact_address">Address</label>
                            <textarea class="form-control" id="setting_contact_address" 
                                      name="setting_contact_address" rows="2" required><?php echo getSetting('contact_address'); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Opening Hours -->
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Opening Hours</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $hours = json_decode(getSetting('opening_hours'), true) ?: [];
                        ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="lunch_mon_fri">Lunch (Mon-Fri)</label>
                                <input type="text" class="form-control" id="lunch_mon_fri" 
                                       name="setting_opening_hours[lunch][mon_fri]" 
                                       value="<?php echo $hours['lunch']['mon_fri'] ?? '11:00 AM - 2:30 PM'; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="dinner_mon_thu">Dinner (Mon-Thu)</label>
                                <input type="text" class="form-control" id="dinner_mon_thu" 
                                       name="setting_opening_hours[dinner][mon_thu]" 
                                       value="<?php echo $hours['dinner']['mon_thu'] ?? '5:30 PM - 10:00 PM'; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="dinner_fri_sat">Dinner (Fri & Sat)</label>
                                <input type="text" class="form-control" id="dinner_fri_sat" 
                                       name="setting_opening_hours[dinner][fri_sat]" 
                                       value="<?php echo $hours['dinner']['fri_sat'] ?? '5:30 PM - 11:00 PM'; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="dinner_sun">Dinner (Sunday)</label>
                                <input type="text" class="form-control" id="dinner_sun" 
                                       name="setting_opening_hours[dinner][sun]" 
                                       value="<?php echo $hours['dinner']['sun'] ?? '5:30 PM - 10:00 PM'; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Social Media -->
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-share-alt"></i> Social Media</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $social = json_decode(getSetting('social_media'), true) ?: [];
                        ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="facebook_url">Facebook URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-facebook"></i>
                                    </span>
                                    <input type="url" class="form-control" id="facebook_url" 
                                           name="setting_social_media[facebook]" 
                                           value="<?php echo $social['facebook'] ?? ''; ?>" 
                                           placeholder="https://facebook.com/yourpage">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="instagram_url">Instagram URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-instagram"></i>
                                    </span>
                                    <input type="url" class="form-control" id="instagram_url" 
                                           name="setting_social_media[instagram]" 
                                           value="<?php echo $social['instagram'] ?? ''; ?>" 
                                           placeholder="https://instagram.com/yourpage">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="twitter_url">Twitter URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-twitter"></i>
                                    </span>
                                    <input type="url" class="form-control" id="twitter_url" 
                                           name="setting_social_media[twitter]" 
                                           value="<?php echo $social['twitter'] ?? ''; ?>" 
                                           placeholder="https://twitter.com/yourpage">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="tripadvisor_url">TripAdvisor URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-tripadvisor"></i>
                                    </span>
                                    <input type="url" class="form-control" id="tripadvisor_url" 
                                           name="setting_social_media[tripadvisor]" 
                                           value="<?php echo $social['tripadvisor'] ?? ''; ?>" 
                                           placeholder="https://tripadvisor.com/yourpage">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SEO Settings -->
                <div class="content-card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-search"></i> SEO Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label" for="setting_google_analytics">Google Analytics ID</label>
                            <input type="text" class="form-control" id="setting_google_analytics" 
                                   name="setting_google_analytics" 
                                   value="<?php echo getSetting('google_analytics'); ?>" 
                                   placeholder="UA-XXXXXXXXX-X">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="setting_google_maps_api">Google Maps API Key</label>
                            <input type="text" class="form-control" id="setting_google_maps_api" 
                                   name="setting_google_maps_api" 
                                   value="<?php echo getSetting('google_maps_api'); ?>">
                            <small class="form-text">Required for interactive map on contact page</small>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="text-right mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Helper function to get setting value
        function getSetting(key) {
            const settings = <?php echo json_encode($settings_raw); ?>;
            const setting = settings.find(s => s.setting_key === key);
            return setting ? setting.setting_value : '';
        }
        
        // Image preview
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById(previewId);
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = previewId;
                        preview.className = 'mt-3';
                        input.parentNode.appendChild(preview);
                    }
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="preview-image">
                        <p class="text-muted mt-2">New logo preview</p>
                    `;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Character counter for meta description
        const metaDesc = document.getElementById('setting_meta_description');
        if (metaDesc) {
            const counter = document.createElement('small');
            counter.className = 'form-text text-right d-block';
            metaDesc.parentNode.appendChild(counter);
            
            function updateCounter() {
                const length = metaDesc.value.length;
                counter.textContent = `${length} characters (150-160 recommended)`;
                counter.style.color = length < 150 || length > 160 ? '#ef4444' : '#10b981';
            }
            
            metaDesc.addEventListener('input', updateCounter);
            updateCounter();
        }
    </script>
</body>
</html>
