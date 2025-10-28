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

// Set header to UTF-8 to handle special characters
header('Content-Type: text/html; charset=utf-8');

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Set connection charset to UTF-8
$conn->set_charset("utf8mb4");

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (customer_name LIKE '%$search%' OR 
                          customer_phone LIKE '%$search%' OR 
                          delivery_address LIKE '%$search%')";
}

// Pagination setup
$limit = 5000; // records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Count total unique customers from both tables
$count_sql = "SELECT (SELECT COUNT(*) FROM (
                SELECT customer_name, customer_phone, delivery_address
                FROM orders
                WHERE user_id = $user_id $search_condition
                GROUP BY customer_name, customer_phone, delivery_address
              ) AS orders_customers) + 
              (SELECT COUNT(*) FROM customer_data 
               WHERE user_id = $user_id $search_condition) AS total";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? (int)$row['total'] : 0;
$total_pages = ceil($total_records / $limit);

/**
 * Clean and validate customer names
 */
function cleanCustomerName($name) {
    // Trim whitespace
    $name = trim($name);
    
    // Remove any non-printable characters
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    
    // Replace common problematic patterns
    $name = str_replace(['#ERROR!', '""', "''", '***', '---', '...', ',,,'], '', $name);
    
    // If name is empty after cleaning, set to "Unknown"
    if (empty($name) || ctype_punct($name)) {
        return 'Unknown';
    }
    
    return $name;
}

// Fetch paginated customer data from both tables with UNION
$customer_data = [];
$sql = "(SELECT customer_name, customer_phone, delivery_address, 'order' as source, MAX(created_at) as updated_at
        FROM orders 
        WHERE user_id = $user_id $search_condition
        GROUP BY customer_name, customer_phone, delivery_address)
        
        UNION
        
        (SELECT customer_name, customer_phone, delivery_address, 'customer_data' as source, updated_at
         FROM customer_data 
         WHERE user_id = $user_id $search_condition)
         
        ORDER BY updated_at DESC
        LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Clean and validate customer names
        $row['customer_name'] = cleanCustomerName($row['customer_name']);
        $customer_data[] = $row;
    }
}

// Fetch user's profile URL from profile_url_details table
$profile_url = '';
$profile_sql = "SELECT profile_url FROM profile_url_details WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_stmt->bind_result($profile_url);
$profile_stmt->fetch();
$profile_stmt->close();

// If no profile URL found or it's incomplete, construct the full URL
if (empty($profile_url)) {
    $profile_url = "https://deegeecard.com";
} else if (!preg_match("/^https?:\/\//i", $profile_url)) {
    // If the URL doesn't start with http:// or https://, add the domain
    $profile_url = "https://deegeecard.com/" . ltrim($profile_url, '/');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Customer Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .table-responsive table {
                min-width: 600px; /* Allow horizontal scrolling on small screens */
            }
            
            .card-header .float-end {
                float: none !important;
                margin-top: 10px;
                text-align: center;
            }
            
            .card-header h4 {
                text-align: center;
            }
            
            .search-form {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .search-form .d-flex {
                flex-direction: column;
            }
            
            .search-form input {
                margin-bottom: 10px;
                margin-right: 0 !important;
            }
            
            .search-form .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .phone-buttons {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .phone-buttons .btn {
                font-size: 0.8rem;
                padding: 5px 8px;
            }
            
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .pagination .page-item {
                margin-bottom: 5px;
            }
            
            .customer-card {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                padding: 15px;
                margin-bottom: 15px;
                background-color: #fff;
            }
            
            .customer-card .card-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                border-bottom: 1px solid #f8f9fa;
                padding-bottom: 8px;
            }
            
            .customer-card .card-row:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .customer-card .label {
                font-weight: 600;
                color: #495057;
                min-width: 120px;
            }
            
            .customer-card .value {
                flex: 1;
                text-align: right;
            }
            
            .customer-card .badge {
                font-size: 0.75rem;
            }
            
            .whatsapp-share {
                width: 100%;
                margin-top: 5px;
            }
        }
        
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .phone-buttons .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .phone-buttons .btn {
                margin-bottom: 5px;
                border-radius: 0.375rem !important;
            }
        }
        
        /* Ensure table is scrollable on mobile */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Hide table on mobile, show cards */
        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }
            
            .mobile-cards {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-cards {
                display: none;
            }
            
            .desktop-table {
                display: block;
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
        <?php if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php';
        } ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Customer Data</h4>
                                <div class="float-end">
                                    <a href="import_customer_data.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-upload me-1"></i> Import Data
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="search-form">
                                            <form method="GET" class="d-flex">
                                                <input type="text" name="search" class="form-control me-2"
                                                       placeholder="Search by name, phone, or address"
                                                       value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-primary">Search</button>
                                                <?php if (!empty($search)): ?>
                                                    <a href="customer_data.php" class="btn btn-secondary ms-2">Clear</a>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($customer_data)): ?>
                                    <div class="alert alert-info">
                                        No customer data found.
                                    </div>
                                <?php else: ?>
                                    <!-- Desktop Table View -->
                                    <div class="desktop-table">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Sr. No.</th>
                                                        <th>Customer Name</th>
                                                        <th>Action</th>
                                                        <th>
                                                            Phone Number
                                                            <br>
                                                            <div class="btn-group mt-1 phone-buttons">
                                                                <button class="btn btn-sm btn-outline-primary copy-page-phones" 
                                                                        title="Copy all phone numbers from this page">
                                                                    <i class="fas fa-copy me-1"></i> Copy Page
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-secondary copy-all-phones" 
                                                                        title="Copy all phone numbers from all pages">
                                                                    <i class="fas fa-copy me-1"></i> Copy All
                                                                </button>
                                                            </div>
                                                        </th>
                                                        
                                                        <th>Delivery Address</th>
                                                        <th>Source</th>
                                                        <th style="display: none;">Last Updated</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $sr_no = $offset + 1;
                                                    foreach ($customer_data as $customer): 
                                                        $phone = htmlspecialchars($customer['customer_phone'], ENT_QUOTES, 'UTF-8');
                                                        $name = htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8');
                                                        
                                                        // Create WhatsApp share message
                                                        $greeting = "Hello";
                                                        if (!empty($name) && $name !== 'Unknown' && $name !== 'No Name') {
                                                            $greeting .= " " . $name;
                                                        } else {
                                                            // Remove "No Name" from the greeting
                                                            $greeting = "Hello";
                                                        }
                                                        
                                                        $message = $greeting . "! 🍴 We're Now Online! 🎉\nEnjoy exclusive discounts & offers on all your favourite dishes.\nOrder your cravings in just a click!\n\nOrder Now: " . $profile_url;
                                                        $share_text = urlencode($message);
                                                    ?>
                                                        <tr>
                                                            <td><?php echo $sr_no; ?></td>
                                                            <td><?php echo $name; ?></td>

                                                            <td>
                                                            <?php if (!empty($phone) && $phone !== 'N/A'): ?>
                                                            <a href="https://wa.me/+91<?php echo $phone; ?>?text=<?php echo $share_text; ?>" 
                                                            target="_blank" class="btn btn-sm btn-success whatsapp-share" 
                                                            title="Share via WhatsApp">
                                                            <span class="nav-icon">
                                                            <iconify-icon icon="ic:sharp-whatsapp"></iconify-icon>
                                                            </span> Share
                                                            </a>
                                                            <?php endif; ?>
                                                            </td>

                                                            <td class="phone-number"><?php echo $phone; ?></td>
                                                            
                                                            <td>
                                                                <?php 
                                                                $address = trim($customer['delivery_address']);
                                                                echo empty($address) || strtoupper($address) === 'NA' 
                                                                    ? 'N/A' 
                                                                    : htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $customer['source'] === 'order' ? 'primary' : 'success'; ?>">
                                                                    <?php echo ucfirst($customer['source']); ?>
                                                                </span>
                                                            </td>
                                                            <td style="display: none;">
                                                                <?php 
                                                                if (isset($customer['updated_at'])) {
                                                                    echo date('d M Y H:i', strtotime($customer['updated_at']));
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                        <?php $sr_no++; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile Card View -->
                                    <div class="mobile-cards">
                                        <div class="phone-buttons mb-3">
                                            <div class="btn-group w-100">
                                                <button class="btn btn-sm btn-outline-primary copy-page-phones" 
                                                        title="Copy all phone numbers from this page">
                                                    <i class="fas fa-copy me-1"></i> Copy Page Numbers
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary copy-all-phones" 
                                                        title="Copy all phone numbers from all pages">
                                                    <i class="fas fa-copy me-1"></i> Copy All Numbers
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                        $sr_no = $offset + 1;
                                        foreach ($customer_data as $customer): 
                                            $phone = htmlspecialchars($customer['customer_phone'], ENT_QUOTES, 'UTF-8');
                                            $name = htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8');
                                            
                                            // Create WhatsApp share message
                                            $greeting = "Hello";
                                            if (!empty($name) && $name !== 'Unknown' && $name !== 'No Name') {
                                                $greeting .= " " . $name;
                                            } else {
                                                // Remove "No Name" from the greeting
                                                $greeting = "Hello";
                                            }
                                            
                                            $message = $greeting . "! 🍴 We're Now Online! 🎉\nEnjoy exclusive discounts & offers on all your favourite dishes.\nOrder your cravings in just a click!\n\nOrder Now: " . $profile_url;
                                            $share_text = urlencode($message);
                                        ?>
                                            <div class="customer-card">
                                                <div class="card-row">
                                                    <span class="label">Sr. No.</span>
                                                    <span class="value"><?php echo $sr_no; ?></span>
                                                </div>
                                                <div class="card-row">
                                                    <span class="label">Customer Name</span>
                                                    <span class="value"><?php echo $name; ?></span>
                                                </div>
                                                <div class="card-row">
                                                    <span class="label">Phone Number</span>
                                                    <span class="value"><?php echo $phone; ?></span>
                                                </div>
                                                <div class="card-row">
                                                    <span class="label">Delivery Address</span>
                                                    <span class="value">
                                                        <?php 
                                                        $address = trim($customer['delivery_address']);
                                                        echo empty($address) || strtoupper($address) === 'NA' 
                                                            ? 'N/A' 
                                                            : htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="card-row">
                                                    <span class="label">Source</span>
                                                    <span class="value">
                                                        <span class="badge bg-<?php echo $customer['source'] === 'order' ? 'primary' : 'success'; ?>">
                                                            <?php echo ucfirst($customer['source']); ?>
                                                        </span>
                                                    </span>
                                                </div>
                                                <?php if (!empty($phone) && $phone !== 'N/A'): ?>
                                                <div class="card-row">
                                                    <span class="label">Action</span>
                                                    <span class="value">
                                                        <a href="https://wa.me/+91<?php echo $phone; ?>?text=<?php echo $share_text; ?>" 
                                                        target="_blank" class="btn btn-sm btn-success whatsapp-share" 
                                                        title="Share via WhatsApp">
                                                        <span class="nav-icon">
                                                        <iconify-icon icon="ic:sharp-whatsapp"></iconify-icon>
                                                        </span> Share via WhatsApp
                                                        </a>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php $sr_no++; ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Pagination -->
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mt-1">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                        Previous
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                        Next
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
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
        // Auto-submit on Enter
        $(document).ready(function () {
            $('input[name="search"]').keypress(function (e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                    return false;
                }
            });
            
            // Copy page phone numbers
            $('.copy-page-phones').click(function() {
                let phoneNumbers = [];
                
                // Check if we're on mobile or desktop view
                if ($('.mobile-cards').is(':visible')) {
                    // Mobile view - get from cards
                    $('.customer-card').each(function() {
                        const phone = $(this).find('.card-row:nth-child(3) .value').text().trim();
                        if (phone && phone !== 'N/A') {
                            phoneNumbers.push(phone);
                        }
                    });
                } else {
                    // Desktop view - get from table
                    $('table tbody tr').each(function() {
                        const phone = $(this).find('td:eq(3)').text().trim();
                        if (phone && phone !== 'N/A') {
                            phoneNumbers.push(phone);
                        }
                    });
                }
                
                if (phoneNumbers.length > 0) {
                    const textToCopy = phoneNumbers.join('\n');
                    copyToClipboard(textToCopy);
                    alert('Copied ' + phoneNumbers.length + ' phone numbers from this page!');
                } else {
                    alert('No phone numbers found on this page.');
                }
            });
            
            // Copy all phone numbers (requires AJAX to fetch all records)
            $('.copy-all-phones').click(function() {
                // Show loading indicator
                const originalText = $(this).html();
                $(this).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
                $(this).prop('disabled', true);
                
                // Fetch all phone numbers via AJAX
                $.ajax({
                    url: 'get_all_phones.php',
                    type: 'GET',
                    data: {
                        search: '<?php echo isset($search) ? addslashes($search) : ''; ?>'
                    },
                    success: function(response) {
                        $('.copy-all-phones').html(originalText);
                        $('.copy-all-phones').prop('disabled', false);
                        
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (data.success && data.phones && data.phones.length > 0) {
                                const textToCopy = data.phones.join('\n');
                                copyToClipboard(textToCopy);
                                alert('Copied ' + data.phones.length + ' phone numbers from all pages!');
                            } else {
                                alert('No phone numbers found. ' + (data.message || ''));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', response);
                            alert('Error processing response. Please check console for details.');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.copy-all-phones').html(originalText);
                        $('.copy-all-phones').prop('disabled', false);
                        console.error('AJAX Error:', status, error);
                        alert('Error fetching phone numbers. Please try again. Error: ' + error);
                    }
                });
            });
            
            // Helper function to copy text to clipboard
            function copyToClipboard(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
        });
    </script>
</body>
</html>