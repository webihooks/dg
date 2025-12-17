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

// Fetch user details including role
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Set connection charset to UTF-8
$conn->set_charset("utf8mb4");

// **UPDATED: Get user phone for WhatsApp message**
$user_phone = $phone;

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

// **UPDATED: Determine user filter based on role**
$user_filter = '';
if ($role !== 'admin') {
    // For non-admin users, only show their own data
    $user_filter = "user_id = $user_id";
} else {
    // For admin users, show data from admin and sales people
    // First, get all user IDs with role 'admin' or 'sales_person' (adjust role name if different)
    $user_ids = [];
    $user_query = "SELECT id FROM users WHERE role IN ('admin', 'sales_person')";
    $user_result = $conn->query($user_query);
    if ($user_result && $user_result->num_rows > 0) {
        while ($user_row = $user_result->fetch_assoc()) {
            $user_ids[] = $user_row['id'];
        }
    }
    
    if (!empty($user_ids)) {
        $user_ids_str = implode(',', $user_ids);
        $user_filter = "user_id IN ($user_ids_str)";
    } else {
        $user_filter = "1=1"; // Show all if no users found (shouldn't happen)
    }
}

// **UPDATED: Count total unique customers with role-based filtering**
$count_sql = "SELECT (SELECT COUNT(*) FROM (
                SELECT customer_name, customer_phone, delivery_address
                FROM orders
                WHERE $user_filter $search_condition
                GROUP BY customer_name, customer_phone, delivery_address
              ) AS orders_customers) + 
              (SELECT COUNT(*) FROM customer_data 
               WHERE $user_filter $search_condition) AS total";
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

// **UPDATED: Fetch paginated customer data with role-based filtering**
$customer_data = [];
$sql = "(SELECT customer_name, customer_phone, delivery_address, 'order' as source, MAX(created_at) as updated_at, user_id
        FROM orders 
        WHERE $user_filter $search_condition
        GROUP BY customer_name, customer_phone, delivery_address, user_id)
        
        UNION
        
        (SELECT customer_name, customer_phone, delivery_address, 'customer_data' as source, updated_at, user_id
         FROM customer_data 
         WHERE $user_filter $search_condition)
         
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
    
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    <style>
        @media (max-width:768px){.table-responsive table{min-width:600px}.card-header .float-end{float:none!important;margin-top:10px;text-align:center}.card-header h4{text-align:center}.search-form{width:100%;margin-bottom:15px}.search-form .d-flex{flex-direction:column}.search-form input{margin-bottom:10px;margin-right:0!important}.search-form .btn{width:100%;margin-bottom:5px}.phone-buttons{display:flex;flex-direction:column;gap:5px}.phone-buttons .btn{font-size:.8rem;padding:5px 8px}.pagination{flex-wrap:wrap;justify-content:center}.pagination .page-item{margin-bottom:5px}.customer-card{border:1px solid #dee2e6;border-radius:.375rem;padding:15px;margin-bottom:15px;background-color:#fff}.customer-card .card-row{display:flex;justify-content:space-between;margin-bottom:8px;border-bottom:1px solid #f8f9fa;padding-bottom:8px}.customer-card .card-row:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}.customer-card .label{font-weight:600;color:#495057;min-width:120px}.customer-card .value{flex:1;text-align:right}.customer-card .badge{font-size:.75rem}.whatsapp-share{width:100%;margin-top:5px}}@media (max-width:576px){.container-fluid{padding-left:10px;padding-right:10px}.card-body{padding:15px}.phone-buttons .btn-group{flex-direction:column;width:100%}.phone-buttons .btn{margin-bottom:5px;border-radius:.375rem!important}}.table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}@media (max-width:768px){.desktop-table{display:none}.mobile-cards{display:block}}@media (min-width:769px){.mobile-cards{display:none}.desktop-table{display:block}}.scroll-to-top.show{bottom:15px}
        /* Add style for user info badge */
        .user-badge {font-size: 0.7rem; margin-left: 5px; vertical-align: middle;}
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php 
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'room') {
            include 'room_management_menu.php';
        } else {
            include 'menu.php';
        } 
        ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Customer Data 
                                    <?php if ($role === 'admin'): ?>
                                        <small class="text-muted">(Showing admin & sales data)</small>
                                    <?php endif; ?>
                                </h4>
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
                                                        <?php if ($role === 'admin'): ?>
                                                        <th>Added By</th>
                                                        <?php endif; ?>
                                                        <th style="display: none;">Last Updated</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $sr_no = $offset + 1;
                                                    foreach ($customer_data as $customer): 
                                                        $phone = htmlspecialchars($customer['customer_phone'], ENT_QUOTES, 'UTF-8');
                                                        $name = htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8');
                                                        
                                                        // Get user info if admin
                                                        $added_by = '';
                                                        if ($role === 'admin' && isset($customer['user_id'])) {
                                                            // In a real scenario, you might want to fetch user names in a batch
                                                            // For now, we'll just show the user ID
                                                            $added_by = 'User #' . $customer['user_id'];
                                                        }
                                                        
                                                        // **UPDATED: Generate WhatsApp message based on user role**
                                                        $whatsapp_message = '';
                                                        if ($role === 'user') {
                                                            // For regular users (restaurant owners)
                                                            $greeting = "Hello";
                                                            if (!empty($name) && $name !== 'Unknown' && $name !== 'No Name') {
                                                                $greeting .= " " . $name;
                                                            } else {
                                                                $greeting = "Hello";
                                                            }
                                                            $whatsapp_message = $greeting . "! 🍴 We're Now Online! 🎉\nEnjoy exclusive discounts & offers on all your favourite dishes.\nOrder your cravings in just a click!\n\nOrder Now: " . $profile_url;
                                                        } else {
                                                            // For admin and sales_person - use the marketing message from toolbar.php
                                                            // Base contact info - fixed contacts (Inayat and Sagar)
                                                            $base_contact_info = "Inayat Shaikh – 9819411026\nSagar Pawar – 9004998995";
                                                            
                                                            // Determine contact info based on user role
                                                            $contact_info = $base_contact_info;
                                                            
                                                            if ($role === 'sales_person') {
                                                                // For sales person: add their contact info before base contacts
                                                                if (!empty($user_phone) && !empty($user_name)) {
                                                                    $contact_info = $user_name . " – " . $user_phone . "\n" . $base_contact_info;
                                                                }
                                                            }
                                                            // For admin: use only base contacts (no admin contact added)
                                                            
                                                            $greeting = !empty($name) && $name !== 'Unknown' && $name !== 'No Name' 
                                                                ? "*Hello " . $name . "*" 
                                                                : "*Hello*";
                                                            
                                                            // Use English message (same as in toolbar.php)
                                                            $whatsapp_message = $greeting . "\nTired of losing your profits to S.w.i.g.g.y / Z.o.m.a.t.o commissions? 💸\n\nIntroducing DeeGeeCard – Your own branded food ordering system with ZERO commission, forever!\n\n🚀 Launch your own Ordering Website + Android App + Admin App in just 60 mins!\n\nHere's what you get:\n\n✅ *Your Own Ordering Website (Personalized Domain):* Just like S.w.i.g.g.y / Z.o.m.a.t.o – but branded for your restaurant, with zero commissions.\n\n✅ *Admin Management App for Desktop & Mobile:* Accept/reject orders, update menu & prices instantly from your phone.\n\n✅ *1000 Personalized Scan-to-Order QR Cards + 8 QR Table Standees!:* Let customers order instantly for delivery or straight from their dining table. Turn every card and standee into your own self-ordering station — boosting reorders, speed, and convenience!\n\n✅ *KOT & Bill Printing:* Generate kitchen order tickets and bills in just one click. 🧾\n\n✅ *Full Store Control:* Set store timings, delivery charges, GST, discounts, coupon codes, and menu categories easily. ⚙️\n\n✅ *Bulk WhatsApp Marketing Panel:* Get 10,000 FREE credits to send offers & updates to your customers directly. 📢\n\n✅ *Direct Payments:* Receive UPI/card payments instantly in your account — 0% platform fee.\n\n✅ *Reply to Reviews Instantly:* Respond to customer reviews directly via WhatsApp in one click. 💬\n\n💡 *Free Integrations:* Google, Instagram, Facebook, YouTube & Maps – make your restaurant easily discoverable.\n\n🔥 *Stop paying commissions. Start keeping 100% of your profits.*\n*Your restaurant's digital revolution starts TODAY!*\n\nAll this for just ₹9,999/year (No Hidden Costs)\n\n📞 Call us NOW:\n" . $contact_info . "\n\n🌐 https://www.deegeecard.com\n\n📧 support@deegeecard.com\n\n🌟 Empowering Restaurants. Eliminating Commissions.";
                                                        }
                                                        
                                                        $share_text = urlencode($whatsapp_message);
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

                                                            <td class="phone-number"> 
<a class="open_in_browser" target="_blank" href="tel:<?php echo $phone; ?>">
    <?php echo $phone; ?>
</a>

                                                            </td>
                                                            
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
                                                            <?php if ($role === 'admin'): ?>
                                                            <td>
                                                                <span class="badge bg-info user-badge">
                                                                    <?php echo $added_by; ?>
                                                                </span>
                                                            </td>
                                                            <?php endif; ?>
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
                                            
                                            // Get user info if admin
                                            $added_by = '';
                                            if ($role === 'admin' && isset($customer['user_id'])) {
                                                $added_by = 'User #' . $customer['user_id'];
                                            }
                                            
                                            // **UPDATED: Generate WhatsApp message based on user role**
                                            $whatsapp_message = '';
                                            if ($role === 'user') {
                                                // For regular users (restaurant owners)
                                                $greeting = "Hello";
                                                if (!empty($name) && $name !== 'Unknown' && $name !== 'No Name') {
                                                    $greeting .= " " . $name;
                                                } else {
                                                    $greeting = "Hello";
                                                }
                                                $whatsapp_message = $greeting . "! 🍴 We're Now Online! 🎉\nEnjoy exclusive discounts & offers on all your favourite dishes.\nOrder your cravings in just a click!\n\nOrder Now: " . $profile_url;
                                            } else {
                                                // For admin and sales_person - use the marketing message from toolbar.php
                                                // Base contact info - fixed contacts (Inayat and Sagar)
                                                $base_contact_info = "Inayat Shaikh – 9819411026\nSagar Pawar – 9004998995";
                                                
                                                // Determine contact info based on user role
                                                $contact_info = $base_contact_info;
                                                
                                                if ($role === 'sales_person') {
                                                    // For sales person: add their contact info before base contacts
                                                    if (!empty($user_phone) && !empty($user_name)) {
                                                        $contact_info = $user_name . " – " . $user_phone . "\n" . $base_contact_info;
                                                    }
                                                }
                                                // For admin: use only base contacts (no admin contact added)
                                                
                                                $greeting = !empty($name) && $name !== 'Unknown' && $name !== 'No Name' 
                                                    ? "*Hello " . $name . "*" 
                                                    : "*Hello*";
                                                
                                                // Use English message (same as in toolbar.php)
                                                $whatsapp_message = $greeting . "\nTired of losing your profits to S.w.i.g.g.y / Z.o.m.a.t.o commissions? 💸\n\nIntroducing DeeGeeCard – Your own branded food ordering system with ZERO commission, forever!\n\n🚀 Launch your own Ordering Website + Android App + Admin App in just 60 mins!\n\nHere's what you get:\n\n✅ *Your Own Ordering Website (Personalized Domain):* Just like S.w.i.g.g.y / Z.o.m.a.t.o – but branded for your restaurant, with zero commissions.\n\n✅ *Admin Management App for Desktop & Mobile:* Accept/reject orders, update menu & prices instantly from your phone.\n\n✅ *1000 Personalized Scan-to-Order QR Cards + 8 QR Table Standees!:* Let customers order instantly for delivery or straight from their dining table. Turn every card and standee into your own self-ordering station — boosting reorders, speed, and convenience!\n\n✅ *KOT & Bill Printing:* Generate kitchen order tickets and bills in just one click. 🧾\n\n✅ *Full Store Control:* Set store timings, delivery charges, GST, discounts, coupon codes, and menu categories easily. ⚙️\n\n✅ *Bulk WhatsApp Marketing Panel:* Get 10,000 FREE credits to send offers & updates to your customers directly. 📢\n\n✅ *Direct Payments:* Receive UPI/card payments instantly in your account — 0% platform fee.\n\n✅ *Reply to Reviews Instantly:* Respond to customer reviews directly via WhatsApp in one click. 💬\n\n💡 *Free Integrations:* Google, Instagram, Facebook, YouTube & Maps – make your restaurant easily discoverable.\n\n🔥 *Stop paying commissions. Start keeping 100% of your profits.*\n*Your restaurant's digital revolution starts TODAY!*\n\nAll this for just ₹9,999/year (No Hidden Costs)\n\n📞 Call us NOW:\n" . $contact_info . "\n\n🌐 https://www.deegeecard.com\n\n📧 support@deegeecard.com\n\n🌟 Empowering Restaurants. Eliminating Commissions.";
                                            }
                                            
                                            $share_text = urlencode($whatsapp_message);
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
                                                    <span class="value">
<a class="open_in_browser" target="_blank" href="tel:<?php echo $phone; ?>">
    <?php echo $phone; ?>
</a>
                                                    </span>
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
                                                <?php if ($role === 'admin'): ?>
                                                <div class="card-row">
                                                    <span class="label">Added By</span>
                                                    <span class="value">
                                                        <span class="badge bg-info user-badge">
                                                            <?php echo $added_by; ?>
                                                        </span>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
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
        function handleProfileLinkClick(url) {
            // For Android with WTN support
            if (typeof WTN !== 'undefined' && WTN.openUrlInBrowser) {
                WTN.openUrlInBrowser(url);
            } else {
                // For iOS or fallback - use the href with loadIn parameter
                window.location.href = url + '?loadIn=defaultBrowser';
            }
        }
        
        // Ensure all external links work properly
        $(document).ready(function() {
            // Handle profile links with both methods
            $('a.open_in_browser').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href') ? $(this).attr('href').replace('?loadIn=defaultBrowser', '') : $(this).text().trim();
                handleProfileLinkClick(url);
            });
        });
    </script>
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