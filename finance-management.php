<?php
// Start the session
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Get user role
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

// Fetch categories for dropdown (for both add transaction and filter)
$categories = [];
$category_sql = "SELECT id, name FROM dg_categories ORDER BY name";
$category_result = $conn->query($category_sql);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
} else {
    $error_message = "Error fetching categories: " . $conn->error;
}

// Handle finance form submission for adding transactions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $amount = floatval($_POST['amount']);
    $category_id = intval($_POST['category_id']);
    $description = trim($_POST['description']);
    $type = $_POST['transaction_type'];
    $date = $_POST['date'];

    // Basic validation
    if ($amount <= 0) {
        $error_message = "Amount must be greater than zero";
    } elseif (!array_key_exists($category_id, $categories)) {
        $error_message = "Invalid category selected";
    } else {
        // Insert transaction
        $insert_sql = "INSERT INTO dg_transactions
                        (user_id, amount, category_id, description, type, date, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_sql);

        if ($stmt) {
            $stmt->bind_param("idssss", $user_id, $amount, $category_id, $description, $type, $date);

            if ($stmt->execute()) {
                $success_message = "Transaction added successfully!";
                // Clear form values
                $_POST = array();
            } else {
                $error_message = "Error adding transaction: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing insert query: " . $conn->error;
        }
    }
}

// Initialize filter variables from GET request
$filter_from_date = $_GET['from_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_transaction_type = $_GET['filter_type'] ?? 'both'; // Default to 'both'
$filter_category_id = $_GET['filter_category'] ?? ''; // New: Initialize category filter

// Fetch transactions with category names and user info based on filters
$transactions = [];
$transaction_sql = "SELECT t.id, t.amount, c.name AS category,
                    t.description, t.type, t.date, t.user_id, u.name AS user_name
                    FROM dg_transactions t
                    LEFT JOIN dg_categories c ON t.category_id = c.id
                    LEFT JOIN users u ON t.user_id = u.id";

$conditions = [];
$params = [];
$param_types = '';

// Condition for user role (always apply for non-admin)
if ($role !== 'admin') {
    $conditions[] = "t.user_id = ?";
    $params[] = $user_id;
    $param_types .= "i";
}

// Add date range conditions
if (!empty($filter_from_date)) {
    $conditions[] = "t.date >= ?";
    $params[] = $filter_from_date;
    $param_types .= "s";
}

if (!empty($filter_end_date)) {
    $conditions[] = "t.date <= ?";
    $params[] = $filter_end_date;
    $param_types .= "s";
}

// Add transaction type condition
if ($filter_transaction_type !== 'both') {
    $conditions[] = "t.type = ?";
    $params[] = $filter_transaction_type;
    $param_types .= "s";
}

// New: Add category filter condition
if (!empty($filter_category_id)) {
    $conditions[] = "t.category_id = ?";
    $params[] = $filter_category_id;
    $param_types .= "i"; // 'i' for integer as category_id is likely an INT
}

// Append WHERE clause if conditions exist
if (!empty($conditions)) {
    $transaction_sql .= " WHERE " . implode(" AND ", $conditions);
}

// Add ordering
$transaction_sql .= " ORDER BY t.date DESC, t.created_at DESC";

// Prepare and execute the statement for fetching transactions
$transaction_stmt = $conn->prepare($transaction_sql);
if ($transaction_stmt) {
    if (!empty($params)) {
        // Use call_user_func_array to bind parameters dynamically
        $transaction_stmt->bind_param($param_types, ...$params);
    }
    $transaction_stmt->execute();
    $result = $transaction_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $transaction_stmt->close();
} else {
    $error_message = "Error preparing transaction history query: " . $conn->error;
}

// Calculate totals based on the FILTERED transactions
$income_total = 0;
$expense_total = 0;

foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'income') {
        $income_total += $transaction['amount'];
    } else {
        $expense_total += $transaction['amount'];
    }
}
$balance = $income_total - $expense_total;


// Calculate DeeGeeCard Bank total
$deegee_bank_total = 0;
$deegee_bank_sql = "SELECT SUM(amount) as total FROM dg_transactions 
                   WHERE category_id = 13"; // 13 is the ID for DeeGeeCard Bank
if ($role !== 'admin') {
    $deegee_bank_sql .= " AND user_id = $user_id";
}
$deegee_bank_result = $conn->query($deegee_bank_sql);
if ($deegee_bank_result) {
    $row = $deegee_bank_result->fetch_assoc();
    $deegee_bank_total = $row['total'] ?? 0;
}



// Fetch user name for display
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_name);
    $stmt->fetch();
    $stmt->close();
} else {
    $error_message = "Error preparing user name query: " . $conn->error;
    $user_name = 'User';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8" />
      <title>Finance Management</title>
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
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .card-header h4, .card-header h5 {
                font-size: 1.2rem;
            }
            
            .summary-cards .col-md-3 {
                margin-bottom: 15px;
            }
            
            .summary-cards .card-body {
                padding: 15px;
            }
            
            .summary-cards h2 {
                font-size: 1.5rem;
            }
            
            .summary-cards h5 {
                font-size: 0.9rem;
            }
            
            .form-row-spacing .col-md-3,
            .form-row-spacing .col-md-2 {
                margin-bottom: 10px;
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
                padding: 10px;
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
            
            .mobile-full-width {
                width: 100% !important;
            }
            
            .btn-mobile {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .filter-form .col-md-3,
            .filter-form .col-md-2 {
                margin-bottom: 10px;
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
            
            .summary-cards .col-md-3 {
                flex: 0 0 50%;
                max-width: 50%;
            }
            
            .table tbody td {
                flex-direction: column;
                align-items: flex-start;
                padding: 5px;
            }
            
            .table tbody td::before {
                margin-bottom: 5px;
                flex: 0 0 100%;
            }
            
            .badge {
                font-size: 0.7rem;
            }
        }
        
        /* Enhanced mobile styles for better touch experience */
        .form-control, .form-select {
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        .btn {
            padding: 8px 16px;
        }
        
        /* Transaction table mobile optimization */
        .transaction-mobile-view {
            display: none;
        }
        
        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }
            
            .transaction-mobile-view {
                display: block;
            }
            
            .transaction-card {
                border: 1px solid #e9ecef;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 10px;
                background: #fff;
            }
            
            .transaction-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f8f9fa;
            }
            
            .transaction-date {
                font-weight: bold;
                color: #495057;
            }
            
            .transaction-type {
                font-size: 0.8rem;
            }
            
            .transaction-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .transaction-detail {
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
            
            .transaction-amount {
                grid-column: 1 / -1;
                text-align: center;
                font-size: 1.1rem;
                font-weight: bold;
                padding-top: 10px;
                border-top: 1px solid #f8f9fa;
            }
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
                           <h4 class="card-title">Finance Management</h4>
                        </div>
                        <div class="card-body">
                           <?php if (!empty($success_message)): ?>
                           <div class="alert alert-success"><?php echo $success_message; ?></div>
                           <?php endif; ?>
                           <?php if (!empty($error_message)): ?>
                           <div class="alert alert-danger"><?php echo $error_message; ?></div>
                           <?php endif; ?>
                           <div class="row mb-4 summary-cards">
                              <div class="col-md-3">
                                 <div class="card bg-success text-white">
                                    <div class="card-body">
                                       <h5 class="card-title">Income</h5>
                                       <h2 class="card-text">₹<?php echo number_format($income_total); ?></h2>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-3">
                                 <div class="card bg-danger text-white">
                                    <div class="card-body">
                                       <h5 class="card-title">Expenses</h5>
                                       <h2 class="card-text">₹<?php echo number_format($expense_total); ?></h2>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-3">
                                 <div class="card bg-primary text-white">
                                    <div class="card-body">
                                       <h5 class="card-title">Balance</h5>
                                       <h2 class="card-text">₹<?php echo number_format($balance); ?></h2>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-3">
                                  <div class="card bg-warning text-white">
                                      <div class="card-body">
                                          <h5 class="card-title">DeeGeeCard Bank</h5>
                                          <h2 class="card-text">₹<?php echo number_format($deegee_bank_total); ?></h2>
                                      </div>
                                  </div>
                              </div>
                           </div>
                           <div class="row">
                              <div class="col-md-12">
                                 <div class="card">
                                    <div class="card-header">
                                       <h5 class="card-title">Add Transaction</h5>
                                    </div>
                                    <div class="card-body">
                                       <form method="POST" action="finance-management.php" name="add_transaction_form">
                                          <div class="row form-row-spacing">
                                             <div class="col-md-3 mb-3">
                                                <label for="transaction_type" class="form-label">Type</label>
                                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                                   <option value="income" <?php echo (isset($_POST['transaction_type']) && $_POST['transaction_type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                                                   <option value="expense" <?php echo (isset($_POST['transaction_type']) && $_POST['transaction_type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
                                                </select>
                                             </div>
                                             <div class="col-md-3 mb-3">
                                                <label for="amount" class="form-label">Amount</label>
                                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                                             </div>
                                             <div class="col-md-3 mb-3">
                                                <label for="category_id" class="form-label">Category</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                   <option value="">Select Category</option>
                                                   <?php foreach ($categories as $id => $name): ?>
                                                   <option value="<?php echo $id; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $id) ? 'selected' : ''; ?>>
                                                      <?php echo htmlspecialchars($name); ?>
                                                   </option>
                                                   <?php endforeach; ?>
                                                </select>
                                             </div>
                                             <div class="col-md-3 mb-3">
                                                <label for="date" class="form-label">Date</label>
                                                <input type="date" class="form-control" id="date" name="date" required value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : date('Y-m-d'); ?>">
                                             </div>
                                          </div>
                                          <div class="row">
                                             <div class="col-md-12 mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="2"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                             </div>
                                          </div>
                                          <button type="submit" name="add_transaction" class="btn btn-primary btn-mobile">Add Transaction</button>
                                       </form>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-12 mt-4">
                                 <div class="card">
                                    <div class="card-header">
                                       <h5 class="card-title">Transaction History</h5>
                                    </div>
                                    <div class="card-body">
                                       <form method="GET" action="finance-management.php" class="mb-4 filter-form">
                                          <div class="row g-2">
                                             <div class="col-md-3">
                                                <label for="from_date" class="form-label">From Date</label>
                                                <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo htmlspecialchars($filter_from_date); ?>">
                                             </div>
                                             <div class="col-md-3">
                                                <label for="end_date" class="form-label">End Date</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                             </div>
                                             <div class="col-md-2">
                                                <label for="filter_type" class="form-label">Type</label>
                                                <select class="form-select" id="filter_type" name="filter_type">
                                                   <option value="both" <?php echo ($filter_transaction_type === 'both') ? 'selected' : ''; ?>>Both</option>
                                                   <option value="income" <?php echo ($filter_transaction_type === 'income') ? 'selected' : ''; ?>>Income</option>
                                                   <option value="expense" <?php echo ($filter_transaction_type === 'expense') ? 'selected' : ''; ?>>Expense</option>
                                                </select>
                                             </div>
                                             <div class="col-md-2">
                                                <label for="filter_category" class="form-label">Category</label>
                                                <select class="form-select" id="filter_category" name="filter_category">
                                                   <option value="">All Categories</option>
                                                   <?php foreach ($categories as $id => $name): ?>
                                                   <option value="<?php echo $id; ?>" <?php echo (string)$filter_category_id === (string)$id ? 'selected' : ''; ?>>
                                                      <?php echo htmlspecialchars($name); ?>
                                                   </option>
                                                   <?php endforeach; ?>
                                                </select>
                                             </div>
                                             <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" class="btn btn-info w-100 btn-mobile">Apply Filter</button>
                                             </div>
                                          </div>
                                       </form>

                                       <!-- Desktop Table View -->
                                       <div class="table-responsive desktop-table">
                                          <table class="table table-striped">
                                             <thead>
                                                <tr>
                                                   <th>Date</th>
                                                   <th>Type</th>
                                                   <th>Category</th>
                                                   <th>Description</th>
                                                   <th>Amount</th>
                                                   <?php if ($role === 'admin'): ?>
                                                   <th>User</th>
                                                   <?php endif; ?>
                                                </tr>
                                             </thead>
                                             <tbody>
                                                <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                   <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                                   <td>
                                                      <span class="badge bg-<?php echo $transaction['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                      <?php echo ucfirst($transaction['type']); ?>
                                                      </span>
                                                   </td>
                                                   <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                                   <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                   <td class="<?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                      <?php echo ($transaction['type'] === 'income' ? '+' : '-') . number_format($transaction['amount'], 2); ?>
                                                   </td>
                                                   <?php if ($role === 'admin'): ?>
                                                   <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                                                   <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($transactions)): ?>
                                                <tr>
                                                   <td colspan="<?php echo $role === 'admin' ? '6' : '5'; ?>" class="text-center">No transactions found</td>
                                                </tr>
                                                <?php endif; ?>
                                             </tbody>
                                          </table>
                                       </div>

                                       <!-- Mobile Card View -->
                                       <div class="transaction-mobile-view">
                                           <?php if (empty($transactions)): ?>
                                               <div class="text-center p-4">
                                                   <p>No transactions found</p>
                                               </div>
                                           <?php else: ?>
                                               <?php foreach ($transactions as $transaction): ?>
                                                   <div class="transaction-card">
                                                       <div class="transaction-header">
                                                           <span class="transaction-date"><?php echo date('M d, Y', strtotime($transaction['date'])); ?></span>
                                                           <span class="transaction-type badge bg-<?php echo $transaction['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                               <?php echo ucfirst($transaction['type']); ?>
                                                           </span>
                                                       </div>
                                                       <div class="transaction-details">
                                                           <div class="transaction-detail">
                                                               <span class="detail-label">Category</span>
                                                               <span class="detail-value"><?php echo htmlspecialchars($transaction['category']); ?></span>
                                                           </div>
                                                           <div class="transaction-detail">
                                                               <span class="detail-label">Description</span>
                                                               <span class="detail-value"><?php echo htmlspecialchars($transaction['description']); ?></span>
                                                           </div>
                                                           <?php if ($role === 'admin'): ?>
                                                           <div class="transaction-detail">
                                                               <span class="detail-label">User</span>
                                                               <span class="detail-value"><?php echo htmlspecialchars($transaction['user_name']); ?></span>
                                                           </div>
                                                           <?php endif; ?>
                                                           <div class="transaction-amount <?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                               <?php echo ($transaction['type'] === 'income' ? '+' : '-') . number_format($transaction['amount'], 2); ?>
                                                           </div>
                                                       </div>
                                                   </div>
                                               <?php endforeach; ?>
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
          // Initialize form validation
          $(document).ready(function() {
              // Validation for Add Transaction form
              $("form[name='add_transaction_form']").validate({
                  rules: {
                      amount: {
                          required: true,
                          number: true,
                          min: 0.01
                      },
                      category_id: {
                          required: true
                      },
                      date: {
                          required: true,
                          date: true
                      }
                  },
                  messages: {
                      amount: {
                          required: "Please enter an amount",
                          number: "Please enter a valid number",
                          min: "Amount must be greater than zero"
                      },
                      category_id: {
                          required: "Please select a category"
                      },
                      date: {
                          required: "Please select a date",
                          date: "Please enter a valid date"
                      }
                  },
                  errorClass: "text-danger",
                  errorElement: "small",
                  highlight: function(element) {
                      $(element).addClass('is-invalid');
                  },
                  unhighlight: function(element) {
                      $(element).removeClass('is-invalid');
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
          });
      </script>
   </body>
</html>