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
$message = '';
$message_type = 'success';

// Define the upload directory
$upload_dir = 'uploads/banners/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch existing discount for this user (only one allowed)
$discount = null;
$sql = "SELECT id, min_cart_value, discount_in_percent, discount_in_flat, image_path
        FROM discount
        WHERE user_id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $discount = $result->fetch_assoc();
}
$stmt->close();

// Function to delete an image file
function deleteImage($imagePath) {
    if ($imagePath && file_exists($imagePath)) {
        unlink($imagePath);
        return true;
    }
    return false;
}

// Process form submission for adding/editing discount
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_discount'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $min_cart_value = (float)$_POST['min_cart_value'];
        $discount_in_percent = !empty($_POST['discount_in_percent']) ? (float)$_POST['discount_in_percent'] : NULL;
        $discount_in_flat = !empty($_POST['discount_in_flat']) ? (float)$_POST['discount_in_flat'] : NULL;
        $current_image_path = $discount ? $discount['image_path'] : NULL; // Get current image path from DB

        // Handle image upload
        $image_path = $current_image_path; // Default to current image path
        if (isset($_FILES['offer_banner']) && $_FILES['offer_banner']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['offer_banner']['name'];
            $file_tmp = $_FILES['offer_banner']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid('banner_', true) . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            // Validate file type
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($file_ext, $allowed_ext)) {
                $errors[] = "Invalid image file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            }

            // Validate file size (e.g., max 5MB)
            if ($_FILES['offer_banner']['size'] > 5 * 1024 * 1024) {
                $errors[] = "Image file size must not exceed 5MB.";
            }

            if (empty($errors)) {
                if (move_uploaded_file($file_tmp, $destination)) {
                    // Delete old image if a new one is uploaded
                    if ($current_image_path && file_exists($current_image_path)) {
                        deleteImage($current_image_path);
                    }
                    $image_path = $destination;
                } else {
                    $errors[] = "Failed to upload image.";
                }
            }
        } elseif (isset($_POST['delete_current_banner']) && $_POST['delete_current_banner'] === 'yes') {
            // User explicitly requested to delete the current banner
            if ($current_image_path && file_exists($current_image_path)) {
                deleteImage($current_image_path);
            }
            $image_path = NULL; // Set image_path to NULL in DB
        }

        // Validate input
        $errors = [];
        if ($min_cart_value <= 0) {
            $errors[] = "Minimum cart value must be greater than 0.";
        }
        
        if ($discount_in_percent === NULL && $discount_in_flat === NULL) {
            $errors[] = "You must specify either a percentage discount or a flat discount.";
        } elseif ($discount_in_percent !== NULL && $discount_in_flat !== NULL) {
            $errors[] = "Please select only one discount type (percentage OR flat).";
        }
        
        if ($discount_in_percent !== NULL && ($discount_in_percent <= 0 || $discount_in_percent > 100)) {
            $errors[] = "Percentage discount must be between 0 and 100.";
        }
        
        if ($discount_in_flat !== NULL && $discount_in_flat <= 0) {
            $errors[] = "Flat discount must be greater than 0.";
        }
        
        if (empty($errors)) {
            if ($id > 0) {
                // Update existing discount
                $sql = "UPDATE discount SET
                        min_cart_value = ?,
                        discount_in_percent = ?,
                        discount_in_flat = ?,
                        image_path = ?,
                        updated_at = NOW()
                        WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dddsii", $min_cart_value, $discount_in_percent, $discount_in_flat, $image_path, $id, $user_id);
            } else {
                // Check if discount already exists
                if ($discount !== null) {
                    $message = "You can only have one discount condition. Please edit the existing one.";
                    $message_type = "danger";
                } else {
                    // Insert new discount
                    $sql = "INSERT INTO discount
                            (user_id, min_cart_value, discount_in_percent, discount_in_flat, image_path, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iddds", $user_id, $min_cart_value, $discount_in_percent, $discount_in_flat, $image_path);
                }
            }
            
            if (isset($stmt) && $stmt->execute()) {
                $message = "Discount saved successfully!";
                // Refresh the page
                header("Location: discount.php");
                exit();
            } elseif (!isset($stmt)) {
                // Error message already set above
            } else {
                $message = "Error saving discount: " . $conn->error;
                $message_type = "danger";
            }
            
            if (isset($stmt)) {
                $stmt->close();
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        }
    } elseif (isset($_POST['delete_discount'])) {
        $id = (int)$_POST['id'];
        // Fetch image path before deleting the discount record
        $sql = "SELECT image_path FROM discount WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->bind_result($image_to_delete);
        $stmt->fetch();
        $stmt->close();

        $sql = "DELETE FROM discount WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            // Delete the associated image file
            deleteImage($image_to_delete);
            $message = "Discount deleted successfully!";
            // Refresh the page
            header("Location: discount.php");
            exit();
        } else {
            $message = "Error deleting discount: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Discount Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA Meta Tags -->
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
</head>


<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php'; // default menu for other roles
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Discount Management</h4>
                                <button class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                                    <?php echo $discount ? 'Edit Discount' : 'Add Discount'; ?>
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$discount): ?>
                                    <div class="alert alert-info">
                                        No discount configured yet. Click "Add Discount" to create one.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Min Cart Value</th>
                                                    <th>Discount</th>
                                                    <th>Offer Banner</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>₹<?php echo number_format($discount['min_cart_value'], 2); ?></td>
                                                    <td>
                                                        <?php
                                                        if ($discount['discount_in_percent'] !== NULL) {
                                                            echo number_format($discount['discount_in_percent'], 2) . '%';
                                                        } else {
                                                            echo '₹' . number_format($discount['discount_in_flat'], 2);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($discount['image_path']): ?>
                                                            <img src="<?php echo htmlspecialchars($discount['image_path']); ?>" alt="Offer Banner" style="max-width: 100px; max-height: 100px;">
                                                        <?php else: ?>
                                                            No Banner
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning edit-discount"
                                                                data-id="<?php echo $discount['id']; ?>"
                                                                data-min-cart="<?php echo $discount['min_cart_value']; ?>"
                                                                data-percent="<?php echo $discount['discount_in_percent']; ?>"
                                                                data-flat="<?php echo $discount['discount_in_flat']; ?>"
                                                                data-image-path="<?php echo htmlspecialchars($discount['image_path']); ?>">
                                                                Edit
                                                        </button>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="id" value="<?php echo $discount['id']; ?>">
                                                            <button type="submit" name="delete_discount" class="btn btn-sm btn-danger"
                                                                    onclick="return confirm('Are you sure you want to delete this discount?');">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
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

    <div class="modal fade" id="addDiscountModal" tabindex="-1" aria-labelledby="addDiscountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDiscountModalLabel"><?php echo $discount ? 'Edit Discount' : 'Add Discount'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="discount.php" id="discountForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="discountId" value="<?php echo $discount ? $discount['id'] : 0; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="offer_banner" class="form-label">Offer Banner</label>
                            <input type="file" class="form-control" id="offer_banner" name="offer_banner" accept="image/*">
                            <small class="form-text text-muted">Upload an image for your offer banner (JPG, JPEG, PNG, GIF, Max 5MB).</small>
                            <div id="currentBannerDisplay" class="mt-2">
                                <?php if ($discount && $discount['image_path']): ?>
                                    <p>Current Banner:</p>
                                    <img src="<?php echo htmlspecialchars($discount['image_path']); ?>" alt="Current Offer Banner" style="max-width: 150px; max-height: 150px;">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="deleteCurrentBanner" name="delete_current_banner" value="yes">
                                        <label class="form-check-label" for="deleteCurrentBanner">Delete current banner</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="min_cart_value" class="form-label">Minimum Cart Value (₹)</label>
                            <input type="number" class="form-control" id="min_cart_value"
                                   name="min_cart_value" step="0.01" min="0.01"
                                   value="<?php echo $discount ? $discount['min_cart_value'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Discount Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="discount_type"
                                       id="percentDiscount" value="percent"
                                       <?php echo ($discount && $discount['discount_in_percent'] !== NULL) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="percentDiscount">
                                    Percentage Discount
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="discount_type"
                                       id="flatDiscount" value="flat"
                                       <?php echo ($discount && $discount['discount_in_flat'] !== NULL) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="flatDiscount">
                                    Flat Discount
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="percentDiscountGroup" style="<?php echo ($discount && $discount['discount_in_flat'] !== NULL) ? 'display:none;' : ''; ?>">
                            <label for="discount_in_percent" class="form-label">Discount Percentage (%)</label>
                            <input type="number" class="form-control" id="discount_in_percent"
                                   name="discount_in_percent" step="0.01" min="0" max="100"
                                   value="<?php echo $discount ? $discount['discount_in_percent'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3" id="flatDiscountGroup" style="<?php echo ($discount && $discount['discount_in_flat'] !== NULL) ? '' : 'display:none;'; ?>">
                            <label for="discount_in_flat" class="form-label">Flat Discount Amount (₹)</label>
                            <input type="number" class="form-control" id="discount_in_flat"
                                   name="discount_in_flat" step="0.01" min="0.01"
                                   value="<?php echo $discount ? $discount['discount_in_flat'] : ''; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_discount" class="btn btn-primary">Save Discount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            // Function to toggle discount type fields
            function toggleDiscountFields() {
                if ($('input[name="discount_type"]:checked').val() === 'percent') {
                    $('#percentDiscountGroup').show();
                    $('#flatDiscountGroup').hide();
                    $('#discount_in_percent').attr('required', 'required');
                    $('#discount_in_flat').removeAttr('required');
                } else {
                    $('#percentDiscountGroup').hide();
                    $('#flatDiscountGroup').show();
                    $('#discount_in_percent').removeAttr('required');
                    $('#discount_in_flat').attr('required', 'required');
                }
            }

            // Initial call to set correct fields on page load/modal open
            toggleDiscountFields();

            // Toggle between percentage and flat discount fields
            $('input[name="discount_type"]').change(function() {
                toggleDiscountFields();
            });
            
            // Edit discount button handler
            $('.edit-discount').click(function() {
                var id = $(this).data('id');
                var minCart = $(this).data('min-cart');
                var percent = $(this).data('percent');
                var flat = $(this).data('flat');
                var imagePath = $(this).data('image-path'); // Get image path

                $('#discountId').val(id);
                $('#min_cart_value').val(minCart);
                
                if (percent !== null && percent !== '') {
                    $('input[name="discount_type"][value="percent"]').prop('checked', true);
                    $('#discount_in_percent').val(percent);
                } else {
                    $('input[name="discount_type"][value="flat"]').prop('checked', true);
                    $('#discount_in_flat').val(flat);
                }
                toggleDiscountFields(); // Call to adjust visibility based on loaded data

                // Display current banner and delete option in modal
                $('#currentBannerDisplay').empty(); // Clear previous display
                if (imagePath) {
                    $('#currentBannerDisplay').html(
                        '<p>Current Banner:</p>' +
                        '<img src="' + imagePath + '" alt="Current Offer Banner" style="max-width: 150px; max-height: 150px;">' +
                        '<div class="form-check mt-1">' +
                        '<input class="form-check-input" type="checkbox" id="deleteCurrentBanner" name="delete_current_banner" value="yes">' +
                        '<label class="form-check-label" for="deleteCurrentBanner">Delete current banner</label>' +
                        '</div>'
                    );
                }

                $('#addDiscountModalLabel').text('Edit Discount');
                $('#addDiscountModal').modal('show');
            });

            // When "Add Discount" button is clicked, clear the form and reset modal title
            $('[data-bs-target="#addDiscountModal"]').click(function() {
                $('#discountForm')[0].reset(); // Reset form fields
                $('#discountId').val(0); // Set ID to 0 for new discount
                $('input[name="discount_type"][value="percent"]').prop('checked', true); // Default to percent
                toggleDiscountFields(); // Adjust fields for new entry
                $('#currentBannerDisplay').empty(); // Clear banner display for new entry
                $('#addDiscountModalLabel').text('Add Discount');
            });
            
            // Form validation
            $('#discountForm').validate({
                rules: {
                    min_cart_value: {
                        required: true,
                        min: 0.01
                    },
                    discount_in_percent: {
                        required: function() {
                            return $('input[name="discount_type"]:checked').val() === 'percent';
                        },
                        min: 0,
                        max: 100
                    },
                    discount_in_flat: {
                        required: function() {
                            return $('input[name="discount_type"]:checked').val() === 'flat';
                        },
                        min: 0.01
                    },
                    offer_banner: {
                        extension: "jpg|jpeg|png|gif" // Client-side validation for file type
                    }
                },
                messages: {
                    min_cart_value: {
                        required: "Please enter minimum cart value",
                        min: "Minimum cart value must be greater than 0"
                    },
                    discount_in_percent: {
                        required: "Please enter discount percentage",
                        min: "Discount percentage cannot be negative",
                        max: "Discount percentage cannot exceed 100%"
                    },
                    discount_in_flat: {
                        required: "Please enter flat discount amount",
                        min: "Flat discount must be greater than 0"
                    },
                    offer_banner: {
                        extension: "Only JPG, JPEG, PNG, GIF files are allowed."
                    }
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    if (element.attr("name") == "offer_banner") {
                        error.insertAfter(element);
                    } else {
                        element.closest('.mb-3').append(error);
                    }
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid').removeClass('is-valid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                }
            });
        });
    </script>
</body>
</html>