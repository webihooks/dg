<?php
// room-amenities.php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
date_default_timezone_set('Asia/Kolkata');

require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

require_once 'session_check.php';
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'room_amenities_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    // Create amenities table if it doesn't exist
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS `room_amenities_$user_id` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `icon` VARCHAR(50) DEFAULT 'mdi:star',
            `category` ENUM('room', 'bathroom', 'entertainment', 'kitchen', 'safety', 'accessibility', 'other') DEFAULT 'room',
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`),
            KEY `category` (`category`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) === FALSE) {
        $error_message = "Error creating amenities table: " . $conn->error;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_amenity'])) {
        // Add new amenity
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $category = $_POST['category'];
        
        if (!empty($name)) {
            $insert_sql = "INSERT INTO room_amenities_$user_id (name, description, icon, category) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssss", $name, $description, $icon, $category);
            
            if ($stmt->execute()) {
                $success_message = "Amenity added successfully!";
            } else {
                $error_message = "Error adding amenity: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Amenity name is required!";
        }
    }
    elseif (isset($_POST['update_amenity'])) {
        // Update amenity
        $amenity_id = $_POST['amenity_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $category = $_POST['category'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $update_sql = "UPDATE room_amenities_$user_id SET name = ?, description = ?, icon = ?, category = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssii", $name, $description, $icon, $category, $is_active, $amenity_id);
        
        if ($stmt->execute()) {
            $success_message = "Amenity updated successfully!";
        } else {
            $error_message = "Error updating amenity: " . $stmt->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_amenity'])) {
        // Delete amenity
        $amenity_id = $_POST['amenity_id'];
        
        $delete_sql = "DELETE FROM room_amenities_$user_id WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $amenity_id);
        
        if ($stmt->execute()) {
            $success_message = "Amenity deleted successfully!";
        } else {
            $error_message = "Error deleting amenity: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all amenities
$amenities_sql = "SELECT * FROM room_amenities_$user_id ORDER BY category, name";
$amenities_result = $conn->query($amenities_sql);
$amenities = [];
if ($amenities_result) {
    $amenities = $amenities_result->fetch_all(MYSQLI_ASSOC);
}

// Get amenity categories for filter
$categories = ['room', 'bathroom', 'entertainment', 'kitchen', 'safety', 'accessibility', 'other'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Amenities Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .amenity-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .amenity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .amenity-icon {
            font-size: 24px;
            margin-right: 10px;
            color: #667eea;
        }
        .category-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .category-room { background-color: #e3f2fd; color: #1976d2; }
        .category-bathroom { background-color: #e8f5e8; color: #388e3c; }
        .category-entertainment { background-color: #f3e5f5; color: #7b1fa2; }
        .category-kitchen { background-color: #fff3e0; color: #f57c00; }
        .category-safety { background-color: #ffebee; color: #d32f2f; }
        .category-accessibility { background-color: #e0f2f1; color: #00796b; }
        .category-other { background-color: #f5f5f5; color: #616161; }
        .amenity-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .amenity-card:hover .amenity-actions {
            opacity: 1;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state iconify-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="room-dashboard.php">Room Management</a></li>
                                    <li class="breadcrumb-item active">Room Amenities</li>
                                </ol>
                            </div>
                            <h4 class="page-title">
                                <iconify-icon icon="mdi:star-circle" class="me-2"></iconify-icon>
                                Room Amenities Management
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <iconify-icon icon="mdi:check-circle" class="me-2"></iconify-icon>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <iconify-icon icon="mdi:alert-circle" class="me-2"></iconify-icon>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Add Amenity Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <iconify-icon icon="mdi:plus-circle" class="me-2"></iconify-icon>
                                    Add New Amenity
                                </h5>
                                
                                <form method="POST" id="amenityForm">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Amenity Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required 
                                               placeholder="e.g., Air Conditioning, Free WiFi">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="3" placeholder="Optional description"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="icon" class="form-label">Icon</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <iconify-icon id="iconPreview" icon="mdi:star"></iconify-icon>
                                            </span>
                                            <input type="text" class="form-control" id="icon" name="icon" 
                                                   value="mdi:star" placeholder="mdi:icon-name">
                                        </div>
                                        <small class="form-text text-muted">
                                            Use Material Design Icons (e.g., mdi:wifi, mdi:air-conditioner)
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="room">Room Amenities</option>
                                            <option value="bathroom">Bathroom</option>
                                            <option value="entertainment">Entertainment</option>
                                            <option value="kitchen">Kitchen</option>
                                            <option value="safety">Safety & Security</option>
                                            <option value="accessibility">Accessibility</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="add_amenity" class="btn btn-primary">
                                            <iconify-icon icon="mdi:plus" class="me-1"></iconify-icon>
                                            Add Amenity
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Amenities Overview</h5>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="p-3">
                                            <h3 class="text-primary"><?php echo count($amenities); ?></h3>
                                            <small class="text-muted">Total Amenities</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3">
                                            <h3 class="text-success"><?php echo count(array_filter($amenities, function($a) { return $a['is_active']; })); ?></h3>
                                            <small class="text-muted">Active Amenities</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amenities List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">
                                        <iconify-icon icon="mdi:format-list-bulleted" class="me-2"></iconify-icon>
                                        All Amenities
                                    </h5>
                                    <div class="d-flex gap-2">
                                        <select class="form-select form-select-sm" id="categoryFilter" style="width: auto;">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat; ?>">
                                                    <?php echo ucfirst($cat); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                                            <iconify-icon icon="mdi:refresh"></iconify-icon>
                                        </button>
                                    </div>
                                </div>

                                <?php if (empty($amenities)): ?>
                                    <div class="empty-state">
                                        <iconify-icon icon="mdi:star-off"></iconify-icon>
                                        <h5>No Amenities Added Yet</h5>
                                        <p class="text-muted">Start by adding your first room amenity using the form on the left.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row" id="amenitiesList">
                                        <?php foreach ($amenities as $amenity): ?>
                                            <div class="col-md-6 mb-3 amenity-item" data-category="<?php echo $amenity['category']; ?>">
                                                <div class="card amenity-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div class="d-flex align-items-center">
                                                                <iconify-icon icon="<?php echo $amenity['icon']; ?>" class="amenity-icon"></iconify-icon>
                                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($amenity['name']); ?></h6>
                                                            </div>
                                                            <div class="amenity-actions">
                                                                <div class="btn-group btn-group-sm">
                                                                    <button class="btn btn-outline-primary edit-amenity" 
                                                                            data-id="<?php echo $amenity['id']; ?>"
                                                                            data-name="<?php echo htmlspecialchars($amenity['name']); ?>"
                                                                            data-description="<?php echo htmlspecialchars($amenity['description']); ?>"
                                                                            data-icon="<?php echo $amenity['icon']; ?>"
                                                                            data-category="<?php echo $amenity['category']; ?>"
                                                                            data-active="<?php echo $amenity['is_active']; ?>">
                                                                        <iconify-icon icon="mdi:pencil"></iconify-icon>
                                                                    </button>
                                                                    <button class="btn btn-outline-danger delete-amenity" 
                                                                            data-id="<?php echo $amenity['id']; ?>"
                                                                            data-name="<?php echo htmlspecialchars($amenity['name']); ?>">
                                                                        <iconify-icon icon="mdi:delete"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if (!empty($amenity['description'])): ?>
                                                            <p class="card-text text-muted small mb-2">
                                                                <?php echo htmlspecialchars($amenity['description']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="category-badge category-<?php echo $amenity['category']; ?>">
                                                                <?php echo ucfirst($amenity['category']); ?>
                                                            </span>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input status-toggle" 
                                                                       type="checkbox" 
                                                                       data-id="<?php echo $amenity['id']; ?>"
                                                                       <?php echo $amenity['is_active'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label small">
                                                                    <?php echo $amenity['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Edit Amenity Modal -->
    <div class="modal fade" id="editAmenityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Amenity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editAmenityForm">
                    <div class="modal-body">
                        <input type="hidden" name="amenity_id" id="edit_amenity_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Amenity Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_icon" class="form-label">Icon</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <iconify-icon id="editIconPreview" icon="mdi:star"></iconify-icon>
                                </span>
                                <input type="text" class="form-control" id="edit_icon" name="icon">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_category" name="category" required>
                                <option value="room">Room Amenities</option>
                                <option value="bathroom">Bathroom</option>
                                <option value="entertainment">Entertainment</option>
                                <option value="kitchen">Kitchen</option>
                                <option value="safety">Safety & Security</option>
                                <option value="accessibility">Accessibility</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="edit_is_active">
                                    Active Amenity
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_amenity" class="btn btn-primary">Update Amenity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    $(document).ready(function() {
        // Icon preview
        $('#icon').on('input', function() {
            $('#iconPreview').attr('icon', $(this).val());
        });

        $('#edit_icon').on('input', function() {
            $('#editIconPreview').attr('icon', $(this).val());
        });

        // Category filter
        $('#categoryFilter').change(function() {
            const category = $(this).val();
            $('.amenity-item').show();
            if (category) {
                $('.amenity-item').not('[data-category="' + category + '"]').hide();
            }
        });

        // Refresh button
        $('#refreshBtn').click(function() {
            window.location.reload();
        });

        // Edit amenity
        $('.edit-amenity').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const icon = $(this).data('icon');
            const category = $(this).data('category');
            const active = $(this).data('active');

            $('#edit_amenity_id').val(id);
            $('#edit_name').val(name);
            $('#edit_description').val(description);
            $('#edit_icon').val(icon);
            $('#edit_category').val(category);
            $('#edit_is_active').prop('checked', active == 1);
            $('#editIconPreview').attr('icon', icon);

            $('#editAmenityModal').modal('show');
        });

        // Delete amenity
        $('.delete-amenity').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');

            Swal.fire({
                title: 'Delete Amenity?',
                text: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    const amenityId = document.createElement('input');
                    amenityId.type = 'hidden';
                    amenityId.name = 'amenity_id';
                    amenityId.value = id;

                    const deleteBtn = document.createElement('input');
                    deleteBtn.type = 'hidden';
                    deleteBtn.name = 'delete_amenity';
                    deleteBtn.value = '1';

                    form.appendChild(amenityId);
                    form.appendChild(deleteBtn);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });

        // Quick status toggle
        $('.status-toggle').change(function() {
            const id = $(this).data('id');
            const isActive = $(this).is(':checked') ? 1 : 0;
            
            // Update the label immediately
            $(this).siblings('label').text(isActive ? 'Active' : 'Inactive');
            
            // Send AJAX request to update status
            $.post('update_amenity_status.php', {
                amenity_id: id,
                is_active: isActive,
                user_id: <?php echo $user_id; ?>
            }, function(response) {
                if (!response.success) {
                    // Revert if failed
                    const toggle = $('.status-toggle[data-id="' + id + '"]');
                    toggle.prop('checked', !isActive);
                    toggle.siblings('label').text(!isActive ? 'Active' : 'Inactive');
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update amenity status'
                    });
                }
            }, 'json').fail(function() {
                // Revert on failure
                const toggle = $('.status-toggle[data-id="' + id + '"]');
                toggle.prop('checked', !isActive);
                toggle.siblings('label').text(!isActive ? 'Active' : 'Inactive');
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Network error occurred'
                });
            });
        });

        // Form validation
        $('#amenityForm').validate({
            rules: {
                name: {
                    required: true,
                    minlength: 2
                }
            },
            messages: {
                name: {
                    required: "Please enter amenity name",
                    minlength: "Amenity name must be at least 2 characters long"
                }
            },
            errorElement: "div",
            errorPlacement: function(error, element) {
                error.addClass("invalid-feedback");
                error.insertAfter(element);
            },
            highlight: function(element, errorClass, validClass) {
                $(element).addClass("is-invalid").removeClass("is-valid");
            },
            unhighlight: function(element, errorClass, validClass) {
                $(element).addClass("is-valid").removeClass("is-invalid");
            }
        });
    });
    </script>
</body>
</html>