<?php
   // Start the session
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   session_start();
   
   // Include the database connection file
   require 'db_connection.php'; // Ensure this file correctly sets up $conn
   
   // Check if the user is logged in
   if (!isset($_SESSION['user_id'])) {
       header("Location: login.php");
       exit();
   }
   
   $user_id = $_SESSION['user_id'];
   $success_message = '';
   $error_message = '';
   
   
   // Get user role for menu inclusion
   $role_sql = "SELECT role FROM users WHERE id = ?";
   $role_stmt = $conn->prepare($role_sql);
   if ($role_stmt) {
       $role_stmt->bind_param("i", $user_id);
       $role_stmt->execute();
       $role_stmt->bind_result($role);
       $role_stmt->fetch();
       $role_stmt->close();
   } else {
       $error_message = "Error preparing role query: " . $conn->error;
       $role = ''; // Default or handle error
   }
   
   // Initialize variables for form pre-filling (for edit mode)
   $edit_category_id = null;
   $edit_name = '';
   $edit_type = '';
   $edit_description = '';
   $edit_is_active = 1; // Default to active for new or edited items
   
   // --- Handle DELETE Request ---
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
       $category_id_to_delete = intval($_POST['category_id_to_delete']);
   
       if ($category_id_to_delete > 0) {
           $delete_sql = "DELETE FROM dg_categories WHERE id = ?";
           $stmt = $conn->prepare($delete_sql);
           if ($stmt) {
               $stmt->bind_param("i", $category_id_to_delete);
               if ($stmt->execute()) {
                   $success_message = "Category deleted successfully!";
               } else {
                   $error_message = "Error deleting category: " . $stmt->error;
               }
               $stmt->close();
           } else {
               $error_message = "Error preparing delete query: " . $conn->error;
           }
       } else {
           $error_message = "Invalid category ID for deletion.";
       }
   }
   
   // --- Handle ADD/UPDATE Category Form Submission ---
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_category']) || isset($_POST['update_category']))) {
       $name = trim($_POST['name']);
       $type = $_POST['type'];
       $description = trim($_POST['description']);
       $is_active = isset($_POST['is_active']) ? 1 : 0;
   
       // Determine if it's an update or add operation
       $is_update = isset($_POST['update_category']) && isset($_POST['category_id_to_edit']) && intval($_POST['category_id_to_edit']) > 0;
       if ($is_update) {
           $edit_category_id = intval($_POST['category_id_to_edit']);
       }
   
       // Basic validation
       if (empty($name)) {
           $error_message = "Category Name is required.";
       } elseif (empty($type)) {
           $error_message = "Category Type is required.";
       } else {
           // Check if category name already exists (case-insensitive)
           $check_sql = "SELECT COUNT(*) FROM dg_categories WHERE LOWER(name) = LOWER(?) " . ($is_update ? "AND id != ?" : "");
           $check_stmt = $conn->prepare($check_sql);
           if ($check_stmt) {
               if ($is_update) {
                   $check_stmt->bind_param("si", $name, $edit_category_id);
               } else {
                   $check_stmt->bind_param("s", $name);
               }
               $check_stmt->execute();
               $check_stmt->bind_result($count);
               $check_stmt->fetch();
               $check_stmt->close();
   
               if ($count > 0) {
                   $error_message = "Category with this name already exists.";
               } else {
                   if ($is_update) {
                       // Update existing category
                       $update_sql = "UPDATE dg_categories SET name = ?, type = ?, description = ?, is_active = ? WHERE id = ?";
                       $stmt = $conn->prepare($update_sql);
                       if ($stmt) {
                           // FIX: Changed bind_param from "ssiii" to "sssii" to correctly bind description as string
                           $stmt->bind_param("sssii", $name, $type, $description, $is_active, $edit_category_id);
                           if ($stmt->execute()) {
                               $success_message = "Category '{$name}' updated successfully!";
                               // Reset for add mode after update
                               $_POST = array(); // Clear post data
                               $edit_category_id = null; // Exit edit mode
                               header("Location: ac_category.php?success=" . urlencode($success_message)); // Redirect to clear GET/POST
                               exit();
                           } else {
                               $error_message = "Error updating category: " . $stmt->error;
                           }
                           $stmt->close();
                       } else {
                           $error_message = "Error preparing update query: " . $conn->error;
                       }
                   } else {
                       // Insert new category
                       $insert_sql = "INSERT INTO dg_categories (name, type, description, is_active, created_at) VALUES (?, ?, ?, ?, NOW())";
                       $stmt = $conn->prepare($insert_sql);
   
                       if ($stmt) {
                           $stmt->bind_param("sssi", $name, $type, $description, $is_active);
   
                           if ($stmt->execute()) {
                               $success_message = "Category '{$name}' added successfully!";
                               // Clear form values after successful submission
                               $_POST = array();
                           } else {
                               $error_message = "Error adding category: " . $stmt->error;
                           }
                           $stmt->close();
                       } else {
                           $error_message = "Error preparing insert category query: " . $conn->error;
                       }
                   }
               }
           } else {
               $error_message = "Error preparing category existence check query: " . $conn->error;
           }
       }
   }
   
   // --- Handle GET Request for Editing (Populate form) ---
   if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_id'])) {
       $edit_category_id = intval($_GET['edit_id']);
       if ($edit_category_id > 0) {
           $fetch_edit_sql = "SELECT name, type, description, is_active FROM dg_categories WHERE id = ?";
           $fetch_edit_stmt = $conn->prepare($fetch_edit_sql);
           if ($fetch_edit_stmt) {
               $fetch_edit_stmt->bind_param("i", $edit_category_id);
               $fetch_edit_stmt->execute();
               $fetch_edit_stmt->bind_result($edit_name, $edit_type, $edit_description, $edit_is_active);
               $fetch_edit_stmt->fetch();
               $fetch_edit_stmt->close();
               // If nothing found, reset edit mode
               if (empty($edit_name) && empty($edit_type)) {
                   $edit_category_id = null;
                   $error_message = "Category not found for editing.";
               }
           } else {
               $error_message = "Error preparing fetch for edit query: " . $conn->error;
           }
       } else {
           $error_message = "Invalid category ID for editing.";
       }
   }
   
   // Handle messages from redirect
   if (isset($_GET['success'])) {
       $success_message = htmlspecialchars($_GET['success']);
   }
   if (isset($_GET['error'])) {
       $error_message = htmlspecialchars($_GET['error']);
   }
   
   // --- Fetch Existing Categories for Display ---
   $categories_list = [];
   $fetch_categories_sql = "SELECT id, name, type, description, is_active FROM dg_categories ORDER BY name ASC";
   $fetch_categories_result = $conn->query($fetch_categories_sql);
   if ($fetch_categories_result) {
       while ($row = $fetch_categories_result->fetch_assoc()) {
           $categories_list[] = $row;
       }
   } else {
       $error_message = "Error fetching category list: " . $conn->error;
   }
   
   // Fetch user name
   // This block was moved from the end to ensure $user_name is available for toolbar/menu.
   $sql = "SELECT name FROM users WHERE id = ?";
   $stmt = $conn->prepare($sql);
   if ($stmt) {
       $stmt->bind_param("i", $user_id);
       $stmt->execute();
       $stmt->bind_result($user_name);
       $stmt->fetch();
       $stmt->close();
   } else {
       $error_message .= " Error fetching user name: " . $conn->error; // Appended to existing error
       $user_name = 'User'; // Default
   }
   
   
   // Close the database connection (moved to ensure all database operations are done)
   $conn->close();
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8" />
      <title>Manage Categories</title>
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

      <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
      <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
      <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
      <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
      <script src="assets/js/config.js"></script>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script src="assets/js/jquery.validate.min.js"></script>
      <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .card-header h4, .card-header h5 {
                font-size: 1.2rem;
            }
            
            .form-row-spacing .col-md-6 {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                border: 1px solid #dee2e6;
                font-size: 0.875rem;
            }
            
            .table thead {
                display: none;
            }
            
            .table tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                background: #fff;
            }
            
            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 5px;
                border: none;
                border-bottom: 1px solid #f8f9fa;
            }
            
            .table tbody td:last-child {
                border-bottom: none;
            }
            
            .table tbody td::before {
                content: attr(data-label);
                font-weight: bold;
                text-align: left;
                margin-right: 10px;
                flex: 0 0 40%;
            }
            
            .mobile-hidden {
                display: none;
            }
            
            .btn-mobile {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .btn-group-mobile {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .action-buttons {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
            }
            
            .action-buttons .btn {
                flex: 1;
                min-width: 70px;
                text-align: center;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-body {
                padding: 15px 10px;
            }
            
            .table tbody td {
                flex-direction: column;
                align-items: flex-start;
                padding: 8px 0;
            }
            
            .table tbody td::before {
                margin-bottom: 5px;
                flex: 0 0 100%;
            }
            
            .badge {
                font-size: 0.7rem;
            }
            
            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }
        
        /* Enhanced mobile styles */
        .mobile-card-view {
            display: none;
        }
        
        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }
            
            .mobile-card-view {
                display: block;
            }
            
            .category-card {
                border: 1px solid #e9ecef;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 15px;
                background: #fff;
            }
            
            .category-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f8f9fa;
            }
            
            .category-name {
                font-weight: bold;
                font-size: 1.1rem;
                color: #495057;
            }
            
            .category-type {
                font-size: 0.8rem;
            }
            
            .category-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 15px;
            }
            
            .category-detail {
                display: flex;
                flex-direction: column;
            }
            
            .detail-label {
                font-size: 0.8rem;
                color: #6c757d;
                margin-bottom: 2px;
            }
            
            .detail-value {
                font-size: 0.9rem;
                font-weight: 500;
            }
            
            .category-description {
                grid-column: 1 / -1;
                padding: 10px 0;
                border-top: 1px solid #f8f9fa;
            }
            
            .category-actions {
                display: flex;
                gap: 10px;
                justify-content: space-between;
            }
            
            .category-actions .btn {
                flex: 1;
            }
        }
        
        /* Form improvements for mobile */
        .form-check {
            padding-left: 0;
        }
        
        .form-check-input {
            margin-right: 8px;
        }
        .scroll-to-top.show {
          bottom: 15px;
        }
      </style>
   </head>
   <body>
      <div class="wrapper">
         <?php include 'toolbar.php'; ?>
         <?php
            // Include the appropriate menu based on user role
            if ($role === 'admin') {
                include 'admin_menu.php';
            } else {
                include 'menu.php';
            }
            ?>
         <div class="page-content">
            <div class="container">
               <div class="row">
                  <div class="col-xl-12">
                     <div class="card">
                        <div class="card-header">
                           <h4 class="card-title">Manage Categories</h4>
                        </div>
                        <div class="card-body">
                           <?php if (!empty($success_message)): ?>
                           <div class="alert alert-success"><?php echo $success_message; ?></div>
                           <?php endif; ?>
                           <?php if (!empty($error_message)): ?>
                           <div class="alert alert-danger"><?php echo $error_message; ?></div>
                           <?php endif; ?>
                           <div class="row">
                              <div class="col-md-12 mb-4">
                                 <div class="card">
                                    <div class="card-header">
                                       <h5 class="card-title"><?php echo ($edit_category_id ? 'Edit Category' : 'Add New Category'); ?></h5>
                                    </div>
                                    <div class="card-body">
                                       <form id="categoryForm" method="POST" action="ac_category.php">
                                          <?php if ($edit_category_id): ?>
                                          <input type="hidden" name="category_id_to_edit" value="<?php echo htmlspecialchars($edit_category_id); ?>">
                                          <?php endif; ?>
                                          <div class="row form-row-spacing">
                                             <div class="col-md-6 mb-3">
                                                <label for="name" class="form-label">Category Name</label>
                                                <input type="text" class="form-control" id="name" name="name" required
                                                   value="<?php echo htmlspecialchars($edit_category_id ? $edit_name : (isset($_POST['name']) ? $_POST['name'] : '')); ?>">
                                             </div>
                                             <div class="col-md-6 mb-3">
                                                <label for="type" class="form-label">Type</label>
                                                <select class="form-select" id="type" name="type" required>
                                                   <option value="">Select Type</option>
                                                   <option value="income" <?php echo (($edit_category_id && $edit_type == 'income') || (isset($_POST['type']) && $_POST['type'] == 'income')) ? 'selected' : ''; ?>>Income</option>
                                                   <option value="expense" <?php echo (($edit_category_id && $edit_type == 'expense') || (isset($_POST['type']) && $_POST['type'] == 'expense')) ? 'selected' : ''; ?>>Expense</option>
                                                </select>
                                             </div>
                                          </div>
                                          <div class="col-md-12 mb-3">
                                             <label for="description" class="form-label">Description</label>
                                             <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($edit_category_id ? $edit_description : (isset($_POST['description']) ? $_POST['description'] : '')); ?></textarea>
                                          </div>
                                          <div class="mb-3 form-check">
                                             <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                                                <?php echo (($edit_category_id && $edit_is_active) || (isset($_POST['is_active']) && $_POST['is_active'] == 1)) ? 'checked' : ''; ?>>
                                             <label class="form-check-label" for="is_active">Is Active</label>
                                          </div>
                                          <div class="action-buttons">
                                              <?php if ($edit_category_id): ?>
                                              <button type="submit" name="update_category" class="btn btn-warning btn-mobile">Update Category</button>
                                              <a href="ac_category.php" class="btn btn-secondary btn-mobile">Cancel Edit</a>
                                              <?php else: ?>
                                              <button type="submit" name="add_category" class="btn btn-primary btn-mobile">Add Category</button>
                                              <?php endif; ?>
                                          </div>
                                       </form>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-12">
                                 <div class="card">
                                    <div class="card-header">
                                       <h5 class="card-title">Existing Categories</h5>
                                    </div>
                                    <div class="card-body">
                                       <!-- Desktop Table View -->
                                       <div class="table-responsive desktop-table">
                                          <table class="table table-striped">
                                             <thead>
                                                <tr>
                                                   <th>ID</th>
                                                   <th>Name</th>
                                                   <th>Type</th>
                                                   <th>Description</th>
                                                   <th>Active</th>
                                                   <th>Actions</th>
                                                </tr>
                                             </thead>
                                             <tbody>
                                                <?php if (!empty($categories_list)): ?>
                                                <?php foreach ($categories_list as $category): ?>
                                                <tr>
                                                   <td><?php echo htmlspecialchars($category['id']); ?></td>
                                                   <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                   <td>
                                                      <span class="badge bg-<?php echo $category['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                      <?php echo ucfirst($category['type']); ?>
                                                      </span>
                                                   </td>
                                                   <td><?php echo htmlspecialchars($category['description']); ?></td>
                                                   <td>
                                                      <?php if ($category['is_active']): ?>
                                                      <span class="badge bg-success">Yes</span>
                                                      <?php else: ?>
                                                      <span class="badge bg-secondary">No</span>
                                                      <?php endif; ?>
                                                   </td>
                                                   <td>
                                                      <div class="action-buttons">
                                                         <a href="ac_category.php?edit_id=<?php echo htmlspecialchars($category['id']); ?>" class="btn btn-sm btn-info">Edit</a>
                                                         <form method="POST" action="ac_category.php" style="display:inline;" onsubmit="return confirmDelete();">
                                                            <input type="hidden" name="category_id_to_delete" value="<?php echo htmlspecialchars($category['id']); ?>">
                                                            <button type="submit" name="delete_category" class="btn btn-sm btn-danger">Delete</button>
                                                         </form>
                                                      </div>
                                                   </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php else: ?>
                                                <tr>
                                                   <td colspan="6" class="text-center">No categories found.</td>
                                                </tr>
                                                <?php endif; ?>
                                             </tbody>
                                          </table>
                                       </div>

                                       <!-- Mobile Card View -->
                                       <div class="mobile-card-view">
                                           <?php if (!empty($categories_list)): ?>
                                               <?php foreach ($categories_list as $category): ?>
                                                   <div class="category-card">
                                                       <div class="category-header">
                                                           <span class="category-name"><?php echo htmlspecialchars($category['name']); ?></span>
                                                           <span class="category-type badge bg-<?php echo $category['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                               <?php echo ucfirst($category['type']); ?>
                                                           </span>
                                                       </div>
                                                       <div class="category-details">
                                                           <div class="category-detail">
                                                               <span class="detail-label">ID</span>
                                                               <span class="detail-value"><?php echo htmlspecialchars($category['id']); ?></span>
                                                           </div>
                                                           <div class="category-detail">
                                                               <span class="detail-label">Status</span>
                                                               <span class="detail-value">
                                                                   <?php if ($category['is_active']): ?>
                                                                   <span class="badge bg-success">Active</span>
                                                                   <?php else: ?>
                                                                   <span class="badge bg-secondary">Inactive</span>
                                                                   <?php endif; ?>
                                                               </span>
                                                           </div>
                                                           <div class="category-description">
                                                               <span class="detail-label">Description</span>
                                                               <span class="detail-value"><?php echo htmlspecialchars($category['description']); ?></span>
                                                           </div>
                                                       </div>
                                                       <div class="category-actions">
                                                           <a href="ac_category.php?edit_id=<?php echo htmlspecialchars($category['id']); ?>" class="btn btn-sm btn-info">Edit</a>
                                                           <form method="POST" action="ac_category.php" style="display:inline; flex: 1;" onsubmit="return confirmDelete();">
                                                               <input type="hidden" name="category_id_to_delete" value="<?php echo htmlspecialchars($category['id']); ?>">
                                                               <button type="submit" name="delete_category" class="btn btn-sm btn-danger w-100">Delete</button>
                                                           </form>
                                                       </div>
                                                   </div>
                                               <?php endforeach; ?>
                                           <?php else: ?>
                                               <div class="text-center p-4">
                                                   <p>No categories found.</p>
                                               </div>
                                           <?php endif; ?>
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
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
             // Form validation for adding/editing a category
             $("#categoryForm").validate({
                 rules: {
                     name: {
                         required: true,
                         minlength: 2
                     },
                     type: {
                         required: true
                     }
                 },
                 messages: {
                     name: {
                         required: "Please enter a category name",
                         minlength: "Category name must be at least 2 characters long"
                     },
                     type: {
                         required: "Please select a category type"
                     }
                 },
                 errorElement: 'div',
                 errorPlacement: function(error, element) {
                     error.addClass('invalid-feedback');
                     element.closest('.mb-3').append(error);
                 },
                 highlight: function(element, errorClass, validClass) {
                     $(element).addClass('is-invalid').removeClass('is-valid');
                 },
                 unhighlight: function(element, errorClass, validClass) {
                     $(element).removeClass('is-invalid').addClass('is-valid');
                 }
             });

             // Enhance mobile form experience
             $('input, select, textarea').on('focus', function() {
                 $(this).addClass('focus');
             }).on('blur', function() {
                 $(this).removeClass('focus');
             });

             // Auto-hide alerts after 5 seconds
             setTimeout(function() {
                 $('.alert').fadeOut('slow');
             }, 5000);

             // Improve touch experience for buttons
             $('.btn').on('touchstart', function() {
                 $(this).addClass('btn-touch');
             }).on('touchend', function() {
                 $(this).removeClass('btn-touch');
             });
         });
         
         // Confirmation dialog for delete
         function confirmDelete() {
             return confirm("Are you sure you want to delete this category? This action cannot be undone.");
         }
      </script>
   </body>
</html>