<?php
// Start the session
session_start();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$is_edit_mode = false;
$service_data = null;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Handle form submission for add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = isset($_POST['service_id']) ? $_POST['service_id'] : null;
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $duration = trim($_POST['duration']);

    // Validate inputs
    if (empty($service_name) || empty($price) || empty($duration)) {
        $error_message = "Service Name, price and duration are required fields.";
    } else {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/services/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['service_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            // Validate image file
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($file_extension), $allowed_types)) {
                $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
            } elseif ($_FILES['service_image']['size'] > 5000000) { // 5MB limit
                $error_message = "File size must be less than 5MB.";
            } elseif (move_uploaded_file($_FILES['service_image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
                
                // Delete old image if updating
                if ($service_id && !empty($_POST['existing_image'])) {
                    if (file_exists($_POST['existing_image'])) {
                        unlink($_POST['existing_image']);
                    }
                }
            } else {
                $error_message = "Error uploading image.";
            }
        } elseif ($service_id && !empty($_POST['existing_image'])) {
            $image_path = $_POST['existing_image'];
        }
        
        if (empty($error_message)) {
            if ($service_id) {
                // Update existing service
                if (!empty($image_path)) {
                    $sql = "UPDATE services SET service_name = ?, description = ?, price = ?, duration = ?, image_path = ? WHERE id = ? AND user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssssii", $service_name, $description, $price, $duration, $image_path, $service_id, $user_id);
                } else {
                    $sql = "UPDATE services SET service_name = ?, description = ?, price = ?, duration = ? WHERE id = ? AND user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssii", $service_name, $description, $price, $duration, $service_id, $user_id);
                }
            } else {
                // Add new service
                $sql = "INSERT INTO services (user_id, service_name, description, price, duration, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssss", $user_id, $service_name, $description, $price, $duration, $image_path);
            }

            if ($stmt->execute()) {
                $success_message = $service_id ? "Service updated successfully!" : "Service added successfully!";
            } else {
                $error_message = "Error saving service: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $service_id = $_GET['edit'];
    $sql = "SELECT * FROM services WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $service_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $service_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($service_data) {
        $is_edit_mode = true;
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $service_id = $_GET['delete'];
    
    // First get the image path to delete the file
    $sql = "SELECT image_path FROM services WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $service_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($image_path);
    $stmt->fetch();
    $stmt->close();
    
    // Delete the service
    $sql = "DELETE FROM services WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $service_id, $user_id);
    
    if ($stmt->execute()) {
        // Delete the image file if it exists
        if (!empty($image_path) && file_exists($image_path)) {
            unlink($image_path);
        }
        $success_message = "Service deleted successfully!";
    } else {
        $error_message = "Error deleting service: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all services for the current user
$sql = "SELECT * FROM services WHERE user_id = ? ORDER BY service_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Services Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Services</h4>
                            </div>
                            <div class="card-body">
                                <h4 class="card-title"><?php echo $is_edit_mode ? 'Edit Service' : 'Add New Service'; ?></h4>
                                <form id="serviceForm" method="POST" action="services.php" enctype="multipart/form-data">
                                    <input type="hidden" name="service_id" value="<?php echo $is_edit_mode ? $service_data['id'] : ''; ?>">
                                    <input type="hidden" name="existing_image" value="<?php echo $is_edit_mode && !empty($service_data['image_path']) ? $service_data['image_path'] : ''; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="service_name" class="form-label">Service Name *</label>
                                        <input type="text" class="form-control" id="service_name" name="service_name" required 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($service_data['service_name']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                            echo $is_edit_mode ? htmlspecialchars($service_data['description']) : ''; ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label">Price *</label>
                                            <input type="number" step="0.01" class="form-control" id="price" name="price" required 
                                                value="<?php echo $is_edit_mode ? $service_data['price'] : ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="duration" class="form-label">Duration (minutes) *</label>
                                            <input type="number" class="form-control" id="duration" name="duration" required 
                                                value="<?php echo $is_edit_mode ? $service_data['duration'] : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="service_image" class="form-label">Service Image</label>
                                        <input type="file" class="form-control" id="service_image" name="service_image" accept="image/*">
                                        <?php if ($is_edit_mode && !empty($service_data['image_path'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo $service_data['image_path']; ?>" alt="Service Image" style="max-width: 200px; max-height: 200px;">
                                                <?php if (!empty($service_data['image_path'])): ?>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                                        <label class="form-check-label" for="remove_image">Remove current image</label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Service</button>
                                    <?php if ($is_edit_mode): ?>
                                        <a href="services.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">Your Services</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($services)): ?>
                                    <p>No services found. Add your first service above.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Image</th>
                                                    <th>Name</th>
                                                    <th>Description</th>
                                                    <th>Price</th>
                                                    <th>Duration</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($services as $service): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if (!empty($service['image_path'])): ?>
                                                                <img src="<?php echo $service['image_path']; ?>" alt="Service Image" style="max-width: 50px; max-height: 50px;">
                                                            <?php else: ?>
                                                                <span class="text-muted">No image</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($service['description']); ?></td>
                                                        <td>₹<?php echo number_format($service['price'], 2); ?></td>
                                                        <td><?php echo $service['duration']; ?> mins</td>
                                                        <td>
                                                            <a href="services.php?edit=<?php echo $service['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                            <a href="services.php?delete=<?php echo $service['id']; ?>" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this service?')">Delete</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            // Form validation
            $("#serviceForm").validate({
                rules: {
                    service_name: "required",
                    price: {
                        required: true,
                        number: true,
                        min: 0.01
                    },
                    duration: {
                        required: true,
                        digits: true,
                        min: 1
                    },
                    service_image: {
                        accept: "image/*",
                        filesize: 5000000 // 5MB
                    }
                },
                messages: {
                    service_name: "Please enter service name",
                    price: {
                        required: "Please enter price",
                        number: "Please enter a valid number",
                        min: "Price must be at least 0.01"
                    },
                    duration: {
                        required: "Please enter duration",
                        digits: "Please enter a whole number",
                        min: "Duration must be at least 1 minute"
                    },
                    service_image: {
                        accept: "Please upload a valid image file (JPG, PNG, GIF)",
                        filesize: "File size must be less than 5MB"
                    }
                }
            });
            
            // Custom validation for file size
            $.validator.addMethod('filesize', function(value, element, param) {
                return this.optional(element) || (element.files[0].size <= param);
            }, 'File size must be less than {0} bytes');
        });
    </script>
</body>
</html>