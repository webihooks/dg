<?php
   // Start the session to access user data
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   session_start();
   date_default_timezone_set('Asia/Kolkata'); // for Indian Standard Time
   
   require 'db_connection.php';
   
   if (!isset($_SESSION['user_id'])) {
       header("Location: login.php");
       exit();
   }
   
   $user_id = $_SESSION['user_id'];
   $success_message = '';
   $error_message = '';
   
   // Fetch user's name and role
   $role = '';
   $user_name = 'User';
   $user_info_sql = "SELECT name, role FROM users WHERE id = ?";
   $user_info_stmt = $conn->prepare($user_info_sql);
   
   if ($user_info_stmt) {
       $user_info_stmt->bind_param("i", $user_id);
       $user_info_stmt->execute();
       $user_info_stmt->bind_result($user_name, $role);
       $user_info_stmt->fetch();
       $user_info_stmt->close();
   }
   
   // Handle updating record
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
       $record_id = (int)$_POST['record_id'];
       $contacted_person = trim($_POST['contacted_person']);
       $phone = trim($_POST['phone']);
       $owner_available = isset($_POST['owner_available']) ? 1 : 0;
       $decision_maker_name = trim($_POST['decision_maker_name']);
       $decision_maker_phone = trim($_POST['decision_maker_phone']);
       $follow_up_date = trim($_POST['follow_up_date']);
       $package_price = trim($_POST['package_price']);
       $new_remark = trim($_POST['new_remark']);
       $status = trim($_POST['status']); // New status field
       
       if ($record_id > 0) {
           // First verify the user has permission to update this record
           $verify_sql = "SELECT user_id FROM sales_track WHERE id = ?";
           $verify_stmt = $conn->prepare($verify_sql);
           $verify_stmt->bind_param("i", $record_id);
           $verify_stmt->execute();
           $verify_stmt->bind_result($record_user_id);
           $verify_stmt->fetch();
           $verify_stmt->close();
           
           if ($role !== 'admin' && $record_user_id != $user_id) {
               $error_message = "You don't have permission to update this record";
           } else {
               // Get existing remark
               $get_remark_sql = "SELECT remark FROM sales_track WHERE id = ?";
               $get_remark_stmt = $conn->prepare($get_remark_sql);
               $get_remark_stmt->bind_param("i", $record_id);
               $get_remark_stmt->execute();
               $get_remark_stmt->bind_result($existing_remark);
               $get_remark_stmt->fetch();
               $get_remark_stmt->close();
               
               $updated_remark = $existing_remark;
               
               if (!empty($new_remark)) {
                   if (!empty($existing_remark)) {
                       $updated_remark .= "\n\n";
                   }
                   $updated_remark .= date('Y-m-d h:i A') . " - " . $user_name . ": " . $new_remark;
               }
               
               $update_sql = "UPDATE sales_track SET 
                   contacted_person = ?, 
                   phone = ?, 
                   owner_available = ?, 
                   decision_maker_name = ?, 
                   decision_maker_phone = ?, 
                   follow_up_date = ?, 
                   package_price = ?, 
                   remark = ?,
                   status = ?,
                   record_date = CURDATE(),
                   time_stamp = CURRENT_TIME()
                   WHERE id = ?";
   
               $update_stmt = $conn->prepare($update_sql);
   
               if ($update_stmt) {
                   $update_stmt->bind_param("ssisssdssi", 
                       $contacted_person,
                       $phone,
                       $owner_available,
                       $decision_maker_name,
                       $decision_maker_phone,
                       $follow_up_date,
                       $package_price,
                       $updated_remark,
                       $status,
                       $record_id);
                   
                   if ($update_stmt->execute()) {
                       $success_message = "Record updated successfully!";
                       header("Location: ".$_SERVER['PHP_SELF']);
                       exit();
                   } else {
                       $error_message = "Error updating record: " . $update_stmt->error;
                   }
                   $update_stmt->close();
               } else {
                   $error_message = "Error preparing update statement: " . $conn->error;
               }
           }
       } else {
           $error_message = "Invalid record ID.";
       }
   }
   
   // Pagination setup
   $records_per_page = 500;
   $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
   if ($current_page < 1) {
       $current_page = 1;
   }
   $offset = ($current_page - 1) * $records_per_page;
   
   // Search and filter parameters
   $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
   $date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
   $follow_up_filter = isset($_GET['follow_up_filter']) ? trim($_GET['follow_up_filter']) : '';
   $owner_filter = isset($_GET['owner_filter']) ? (int)$_GET['owner_filter'] : -1;
   $sales_person_filter = isset($_GET['sales_person_filter']) ? (int)$_GET['sales_person_filter'] : ($role === 'admin' ? 0 : $user_id);
   $status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'in process'; 
   
   // Fetch all team members for admin filter dropdown
   $team_members = [];
   if ($role === 'admin') {
       $team_sql = "SELECT id, name, role FROM users WHERE role IN ('admin', 'sales_person') ORDER BY name";
       $team_result = $conn->query($team_sql);
       while ($row = $team_result->fetch_assoc()) {
           $team_members[] = $row;
       }
   }
   
   // Build WHERE clause
   $where_clauses = [];
   $params = [];
   $param_types = '';
   
   // For non-admin users, restrict to their own records
   if ($role !== 'admin') {
       $where_clauses[] = "user_id = ?";
       $params[] = $user_id;
       $param_types .= 'i';
   } 
   // For admin users, apply sales person filter if selected
   elseif ($sales_person_filter > 0) {
       $where_clauses[] = "user_id = ?";
       $params[] = $sales_person_filter;
       $param_types .= 'i';
   }
   
   // Add other filters
   if (!empty($search_query)) {
       $where_clauses[] = "(restaurant_name LIKE ? OR 
                           contacted_person LIKE ? OR 
                           phone LIKE ? OR 
                           decision_maker_name LIKE ? OR 
                           decision_maker_phone LIKE ? OR 
                           CONCAT(street, ' ', city, ' ', state, ' ', location) LIKE ?)";
       $search_param = "%$search_query%";
       $params = array_merge($params, array_fill(0, 6, $search_param));
       $param_types .= str_repeat('s', 6);
   }
   
   if (!empty($date_filter)) {
       $where_clauses[] = "record_date = ?";
       $params[] = $date_filter;
       $param_types .= 's';
   }
   
   if (!empty($follow_up_filter)) {
       $where_clauses[] = "follow_up_date = ?";
       $params[] = $follow_up_filter;
       $param_types .= 's';
   }
   
   if ($owner_filter >= 0) {
       $where_clauses[] = "owner_available = ?";
       $params[] = $owner_filter;
       $param_types .= 'i';
   }
   
   // Status filter - default to 'in process' but allow override
   if (empty($_GET['status_filter'])) {
       $where_clauses[] = "status = ?";
       $params[] = 'in process';
       $param_types .= 's';
   } elseif (!empty($status_filter)) {
       $where_clauses[] = "status = ?";
       $params[] = $status_filter;
       $param_types .= 's';
   }
   
   $where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);
   
   // Fetch total records count
   $count_sql = "SELECT COUNT(*) FROM sales_track $where_sql";
   $count_stmt = $conn->prepare($count_sql);
   
   if ($count_stmt) {
       if (!empty($params)) {
           $count_stmt->bind_param($param_types, ...$params);
       }
       $count_stmt->execute();
       $count_stmt->bind_result($total_records);
       $count_stmt->fetch();
       $count_stmt->close();
   }
   
   $total_pages = ceil($total_records / $records_per_page);
   
// Determine order based on whether date filter is applied
$order_by = !empty($date_filter) ? "record_date DESC, time_stamp DESC" : "follow_up_date ASC";

// Fetch paginated records
$fetch_sales_sql = "SELECT 
    id, user_id, user_name, record_date, time_stamp, 
    restaurant_name, contacted_person, phone, 
    decision_maker_name, decision_maker_phone, 
    location, street, city, state, 
    postal_code, country, follow_up_date, 
    package_price, remark, owner_available, status,
    CONCAT(street, ' ', city, ' ', state, ' ', location) AS full_address
    FROM sales_track 
    $where_sql
    ORDER BY $order_by
    LIMIT ?, ?";
   
   $fetch_sales_stmt = $conn->prepare($fetch_sales_sql);
   
   if ($fetch_sales_stmt) {
       // Add pagination parameters
       $params[] = $offset;
       $params[] = $records_per_page;
       $param_types .= 'ii';
       
       if (!empty($params)) {
           $fetch_sales_stmt->bind_param($param_types, ...$params);
       }
       
       $fetch_sales_stmt->execute();
       $result = $fetch_sales_stmt->get_result();
       while ($row = $result->fetch_assoc()) {
           $sales_track_list[] = $row;
       }
       $fetch_sales_stmt->close();
   } else {
       $error_message = "Error preparing fetch query: " . $conn->error;
   }
   
   $conn->close();
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <title>View Sales Records</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link href="assets/css/vendor.min.css" rel="stylesheet">
      <link href="assets/css/icons.min.css" rel="stylesheet">
      <link href="assets/css/app.min.css" rel="stylesheet">
      <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
      <style>
         .status-badge {
         padding: 3px 8px;
         border-radius: 12px;
         font-size: 12px;
         font-weight: 500;
         text-transform: capitalize;
         }
         .status-badge.in-process {
         background-color: #fff3cd;
         color: #856404;
         }
         .status-badge.completed {
         background-color: #d4edda;
         color: #155724;
         }
         .status-badge.not-interested {
         background-color: #f8d7da;
         color: #721c24;
         }


@media (max-width: 767px) {
    .sales_table td[data-label="Sr. No"] {
        display: table-cell !important;
        background-color: #fff3cd;
        color: #856404;
    }
}
      </style>
      <script src="assets/js/config.js"></script>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script src="assets/js/jquery.validate.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
   </head>
   <body class="view_sales_records">
      <div class="wrapper">
         <?php include 'toolbar.php'; ?>
         <?php
            if ($role === 'admin') {
                include 'admin_menu.php';
            } elseif ($role === 'sales_person') {
                include 'sales_menu.php';
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
                           <h4 class="card-title">
                              View Sales Records
                              <button id="fullscreenToggle" class="btn btn-primary btn-block fr">Enter Fullscreen</button>
                           </h4>
                        </div>
                        <div class="card-body">
                           <div class="filter-section">
                              <form id="filterForm" method="GET" action="">
                                 <div class="row filter-row">
                                    <div class="row mt-1">
                                       <div class="col-md-4 search-box">
                                          <label class="form-label">Search</label>
                                          <input type="text" class="form-control" name="search" placeholder="Search..." 
                                             value="<?= htmlspecialchars($search_query) ?>">
                                          <?php if (!empty($search_query)): ?>
                                          <span class="clear-search" style="display:none;" onclick="clearSearch()">&times;</span>
                                          <?php endif; ?>
                                       </div>
                                       <div class="col-md-4">
                                          <label class="form-label">Date</label>
                                          <input type="date" class="form-control" name="date_filter" 
                                             value="<?= htmlspecialchars($date_filter) ?>">
                                       </div>
                                       <div class="col-md-4">
                                          <label class="form-label">Follow up Date</label>
                                          <div class="input-group date" id="followUpDatePicker">
                                             <input type="text" class="form-control" name="follow_up_filter" 
                                                value="<?= htmlspecialchars($follow_up_filter) ?>" placeholder="Follow Up Date">
                                             <div class="input-group-append">
                                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                             </div>
                                          </div>
                                       </div>
                                    </div>
                                    <?php if ($role === 'admin'): ?>
                                    <div class="row mt-1">
                                       <div class="col-md-4">
                                          <label class="form-label">Team member</label>
                                          <select class="form-control team-member-filter" name="sales_person_filter">
                                             <option value="0">All Team Members</option>
                                             <?php foreach ($team_members as $member): ?>
                                             <option value="<?= $member['id'] ?>" <?= $sales_person_filter == $member['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($member['name']) ?> (<?= ucfirst($member['role']) ?>)
                                             </option>
                                             <?php endforeach; ?>
                                          </select>
                                       </div>
                                       <?php endif; ?>
                                       <div class="col-md-4">
                                          <label class="form-label">Owner Status</label>
                                          <select class="form-control" name="owner_filter">
                                             <option value="-1" <?= $owner_filter == -1 ? 'selected' : '' ?>>Owner: All</option>
                                             <option value="1" <?= $owner_filter == 1 ? 'selected' : '' ?>>Owner: Available</option>
                                             <option value="0" <?= $owner_filter == 0 ? 'selected' : '' ?>>Owner: Not Available</option>
                                          </select>
                                       </div>
                                       <div class="col-md-4">
                                          <label class="form-label">Status</label>
                                          <select class="form-control" name="status_filter">
                                             <!-- <option value="">All Statuses</option> -->
                                             <option value="in process" <?= ($status_filter === 'in process' || empty($_GET['status_filter'])) ? 'selected' : '' ?>>In Process</option>
                                             <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                             <option value="not interested" <?= $status_filter === 'not interested' ? 'selected' : '' ?>>Not Interested</option>
                                          </select>
                                       </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary btn-block">Apply</button>

                                            <button type="button" id="downloadCsv" class="btn btn-success btn-block">
                                                <i class="fas fa-download"></i> Download CSV
                                            </button>
                                        </div>
                                    </div>
                                 </div>
                              </form>
                              <?php if (!empty($search_query) || !empty($date_filter) || !empty($follow_up_filter) || $owner_filter >= 0 || ($role === 'admin' && $sales_person_filter > 0) || !empty($status_filter)): ?>
                              <div class="row">
                                 <div class="col-md-12 mt-2">
                                    <div class="filter-results">
                                       <small class="text-muted">
                                       Filtered results: 
                                       <?php if (!empty($search_query)): ?>
                                       <span class="badge badge-info">Search: <?= htmlspecialchars($search_query) ?></span>
                                       <?php endif; ?>
                                       <?php if (!empty($date_filter)): ?>
                                       <span class="badge badge-info">Date: <?= htmlspecialchars($date_filter) ?></span>
                                       <?php endif; ?>
                                       <?php if (!empty($follow_up_filter)): ?>
                                       <span class="badge badge-info">Follow-up: <?= htmlspecialchars($follow_up_filter) ?>
                                       <a href="#" class="clear-follow-up" style="color: white; margin-left: 5px;">&times;</a>
                                       </span>
                                       <?php endif; ?>
                                       <?php if ($role === 'admin' && $sales_person_filter > 0): ?>
                                       <?php 
                                          $selected_member = '';
                                          foreach ($team_members as $member) {
                                              if ($member['id'] == $sales_person_filter) {
                                                  $selected_member = $member['name'] . ' (' . ucfirst($member['role']) . ')';
                                                  break;
                                              }
                                          }
                                          ?>
                                       <span class="badge badge-team-member">Team Member: <?= htmlspecialchars($selected_member) ?></span>
                                       <?php endif; ?>
                                       <?php if ($owner_filter == 1): ?>
                                       <span class="badge badge-info">Owner: Available</span>
                                       <?php elseif ($owner_filter == 0): ?>
                                       <span class="badge badge-info">Owner: Not Available</span>
                                       <?php endif; ?>
                                       <?php if (!empty($status_filter)): ?>
                                       <span class="badge badge-info">Status: <?= ucfirst($status_filter) ?></span>
                                       <?php endif; ?>
                                       <a href="?" class="badge badge-danger">Clear all</a>
                                       </small>
                                    </div>
                                 </div>
                              </div>
                              <?php endif; ?>
                           </div>
                           <?php if (!empty($success_message)): ?>
                           <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
                           <?php endif; ?>
                           <?php if (!empty($error_message)): ?>
                           <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                           <?php endif; ?>
                           <div class="row">
                              <div class="col-md-12">
                                 <div class="card">
                                    <div class="card-body">
                                       <div class="table-responsive sales_table">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Actions</th>
                                                        <th>Sr. No.</th>
                                                        <th style="display:none;">ID</th>
                                                        <?php if ($role === 'admin'): ?>
                                                        <th>Team</th>
                                                        <?php endif; ?>
                                                        <th>Date</th>
                                                        <th>Time</th>
                                                        <th>Restaurant</th>
                                                        <th>Owner</th>
                                                        <th>Follow Up</th>
                                                        <th>Status</th>
                                                        <th>Price</th>
                                                        <th>Remark</th>
                                                        <th>Contact</th>
                                                        <th>Phone</th>
                                                        <th>D.M.</th>
                                                        <th>D.M. Phone</th>
                                                        <th>Location Details</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($sales_track_list)): ?>
                                                    <?php foreach ($sales_track_list as $index => $entry): ?>
                                                    <tr>
                                                        <td data-label="Actions">
                                                            <?php if ($role === 'admin' || $entry['user_id'] == $user_id): ?>
                                                            <button class="btn btn-sm btn-outline-primary update-record-btn" 
                                                                data-record-id="<?= $entry['id'] ?>"
                                                                data-restaurant-name="<?= htmlspecialchars($entry['restaurant_name']) ?>"
                                                                data-contacted-person="<?= htmlspecialchars($entry['contacted_person']) ?>"
                                                                data-phone="<?= htmlspecialchars($entry['phone']) ?>"
                                                                data-owner-available="<?= $entry['owner_available'] ? '1' : '0' ?>"
                                                                data-decision-maker-name="<?= htmlspecialchars($entry['decision_maker_name']) ?>"
                                                                data-decision-maker-phone="<?= htmlspecialchars($entry['decision_maker_phone']) ?>"
                                                                data-follow-up-date="<?= htmlspecialchars($entry['follow_up_date']) ?>"
                                                                data-package-price="<?= htmlspecialchars($entry['package_price']) ?>"
                                                                data-status="<?= htmlspecialchars($entry['status']) ?>">
                                                            <i class="fas fa-edit"></i> Update
                                                            </button>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="Sr. No"><?= $index + 1 + $offset ?></td>
                                                        <td style="display:none;" data-label="ID"><?= htmlspecialchars($entry['id']) ?></td>
                                                        <?php if ($role === 'admin'): ?>
                                                        <td data-label="Team"><?= htmlspecialchars($entry['user_name']) ?></td>
                                                        <?php endif; ?>
                                                        <td data-label="Date"><?= htmlspecialchars($entry['record_date']) ?></td>
                                                        <td data-label="Time"><?= date('h:i A', strtotime($entry['time_stamp'])) ?></td>
                                                        <td data-label="Restaurant"><?= htmlspecialchars($entry['restaurant_name']) ?></td>
                                                        <td data-label="Owner"><?= $entry['owner_available'] ? 'Yes' : 'No' ?></td>
                                                        <td data-label="Follow Up"><?= htmlspecialchars($entry['follow_up_date']) ?></td>
                                                        <td data-label="Status">
                                                            <span class="status-badge <?= str_replace(' ', '-', $entry['status']) ?>">
                                                            <?= ucfirst($entry['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td data-label="Price"><?= number_format($entry['package_price']) ?></td>
                                                        <td data-label="Remark">
                                                            <?php if (!empty($entry['remark'])): ?>
                                                            <div class="remark-container">
                                                                <?php 
                                                                    $remarks = explode("\n\n", $entry['remark']);
                                                                    // Reverse the array to show latest first
                                                                    $reversed_remarks = array_reverse($remarks);
                                                                    foreach ($reversed_remarks as $remark): 
                                                                        if (!empty(trim($remark))):
                                                                            $parts = explode(" - ", $remark, 2);
                                                                ?>
                                                                <div class="remark-entry">
                                                                    <?php if (count($parts) > 1): ?>
                                                                    <div class="remark-date"><?= htmlspecialchars($parts[0]) ?></div>
                                                                    <div class="remark-content"><?= htmlspecialchars($parts[1]) ?></div>
                                                                    <?php else: ?>
                                                                    <div class="remark-content"><?= htmlspecialchars($remark) ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php 
                                                                        endif;
                                                                    endforeach; 
                                                                    ?>
                                                            </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="Contact"><?= htmlspecialchars($entry['contacted_person']) ?></td>
                                                        <td data-label="Phone"><?= htmlspecialchars($entry['phone']) ?></td>
                                                        <td data-label="D.M."><?= htmlspecialchars($entry['decision_maker_name']) ?></td>
                                                        <td data-label="D.M. Phone"><?= htmlspecialchars($entry['decision_maker_phone']) ?></td>
                                                        <td data-label="Location">
                                                            <?php
                                                                $fullAddress = [];
                                                                if (!empty($entry['location'])) {
                                                                    $fullAddress[] = htmlspecialchars($entry['location']);
                                                                }
                                                                if (!empty($entry['street'])) {
                                                                    $fullAddress[] = htmlspecialchars($entry['street']);
                                                                }
                                                                if (!empty($entry['city'])) {
                                                                    $fullAddress[] = htmlspecialchars($entry['city']);
                                                                }
                                                                if (!empty($entry['state'])) {
                                                                    $fullAddress[] = htmlspecialchars($entry['state']);
                                                                }
                                                                echo implode(', ', $fullAddress);
                                                                ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="<?= ($role === 'admin') ? '17' : '16' ?>" class="text-center">No records found</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                            <!-- Pagination remains the same -->
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
      <!-- Update Record Modal -->
      <div class="modal fade" id="updateRecordModal" tabindex="-1" role="dialog" aria-labelledby="updateRecordModalLabel" aria-hidden="true">
         <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="updateRecordModalLabel">Update Record</h5>
               </div>
               <form id="updateRecordForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                  <div class="modal-body">
                     <div class="row">
                        <div class="col-md-12">
                           <input type="hidden" name="record_id" id="modalRecordId">
                           <div class="form-group">
                              <label for="restaurantName">Restaurant</label>
                              <input type="text" class="form-control" id="modalRestaurantName" readonly>
                           </div>
                        </div>
                     </div>
                     <div class="row">
                        <div class="col-md-6">
                           <div class="form-group">
                              <label for="contacted_person">Contact Person</label>
                              <input type="text" class="form-control" name="contacted_person" id="modalContactedPerson">
                           </div>
                        </div>
                        <div class="col-md-6">
                           <div class="form-group">
                              <label for="phone">Phone</label>
                              <input type="text" class="form-control" name="phone" id="modalPhone">
                           </div>
                        </div>
                     </div>
                     <div class="row">
                        <div class="col-md-6">
                           <div class="form-group">
                              <label for="decision_maker_name">Decision Maker Name</label>
                              <input type="text" class="form-control" name="decision_maker_name" id="modalDecisionMakerName">
                           </div>
                        </div>
                        <div class="col-md-6">
                           <div class="form-group">
                              <label for="decision_maker_phone">Decision Maker Phone</label>
                              <input type="text" class="form-control" name="decision_maker_phone" id="modalDecisionMakerPhone">
                           </div>
                        </div>
                     </div>
                     <div class="row">
                        <div class="col-md-3">
                           <div class="form-group">
                              <label for="follow_up_date">Follow Up Date</label>
                              <input type="date" class="form-control" name="follow_up_date" id="modalFollowUpDate">
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="form-group">
                              <label for="package_price">Package Price</label>
                              <input type="number" step="0.01" class="form-control" name="package_price" id="modalPackagePrice">
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="form-group">
                              <label for="status">Status</label>
                              <select class="form-control" name="status" id="modalStatus">
                                 <option value="in process">In Process</option>
                                 <option value="completed">Completed</option>
                                 <option value="not interested">Not Interested</option>
                              </select>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="form-group form-check mt-4 pt-2">
                              <input type="checkbox" class="form-check-input" name="owner_available" id="modalOwnerAvailable">
                              <label class="form-check-label" for="owner_available">Owner Available</label>
                           </div>
                        </div>
                     </div>
                     <div class="row">
                        <div class="col-md-12 mt-2">
                           <div class="form-group">
                              <label for="new_remark">New Remark (optional)</label>
                              <textarea class="form-control remark-textarea" name="new_remark" id="modalNewRemark"></textarea>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                     <button type="submit" name="update_record" class="btn btn-primary">Update Record</button>
                  </div>
               </form>
            </div>
         </div>
      </div>
      <script>
         $(document).ready(function() {
             // Handle modal close functionality
             $(document).on('click', '#updateRecordModal .btn-secondary', function(e) {
                 e.preventDefault();
                 $('#updateRecordModal').modal('hide');
             });
         
             // Initialize date picker for follow up filter
             $('#followUpDatePicker').datepicker({
                 format: 'yyyy-mm-dd',
                 autoclose: true,
                 todayHighlight: true,
                 clearBtn: true
             });
         
             // Clear follow up date filter
             $(document).on('click', '.clear-follow-up', function(e) {
                 e.preventDefault();
                 $('#followUpDatePicker').datepicker('clearDates');
                 $('#filterForm').submit();
             });
         
             // Update record button click handler
             $(document).on('click', '.update-record-btn', function(e) {
                 e.preventDefault();
                 
                 var recordId = $(this).data('record-id');
                 var restaurantName = $(this).data('restaurant-name');
                 var contactedPerson = $(this).data('contacted-person');
                 var phone = $(this).data('phone');
                 var ownerAvailable = $(this).data('owner-available') === '1';
                 var decisionMakerName = $(this).data('decision-maker-name');
                 var decisionMakerPhone = $(this).data('decision-maker-phone');
                 var followUpDate = $(this).data('follow-up-date');
                 var packagePrice = $(this).data('package-price');
                 var status = $(this).data('status');
                 
                 $('#modalRecordId').val(recordId);
                 $('#modalRestaurantName').val(restaurantName);
                 $('#modalContactedPerson').val(contactedPerson);
                 $('#modalPhone').val(phone);
                 $('#modalOwnerAvailable').prop('checked', ownerAvailable);
                 $('#modalDecisionMakerName').val(decisionMakerName);
                 $('#modalDecisionMakerPhone').val(decisionMakerPhone);
                 $('#modalFollowUpDate').val(followUpDate);
                 $('#modalPackagePrice').val(packagePrice);
                 $('#modalStatus').val(status);
                 $('#modalNewRemark').val('');
         
                 $('#updateRecordModal').modal('show');
             });
         
             // Form validation for update record form
             $("#updateRecordForm").validate({
                 rules: {
                     contacted_person: {
                         required: true,
                         minlength: 2
                     },
                     phone: {
                         required: true,
                         minlength: 5
                     },
                     follow_up_date: {
                         required: true
                     },
                     package_price: {
                         required: true,
                         number: true,
                         min: 0
                     }
                 },
                 messages: {
                     contacted_person: {
                         required: "Please enter contact person name",
                         minlength: "Name should be at least 2 characters long"
                     },
                     phone: {
                         required: "Please enter phone number",
                         minlength: "Phone number should be at least 5 characters long"
                     },
                     follow_up_date: {
                         required: "Please select follow up date"
                     },
                     package_price: {
                         required: "Please enter package price",
                         number: "Please enter a valid number",
                         min: "Price cannot be negative"
                     }
                 },
                 errorElement: 'div',
                 errorPlacement: function(error, element) {
                     error.addClass('invalid-feedback');
                     element.closest('.form-group').append(error);
                 },
                 highlight: function(element) {
                     $(element).addClass('is-invalid');
                 },
                 unhighlight: function(element) {
                     $(element).removeClass('is-invalid');
                 }
             });
         
             // Handle modal hidden event to clear form
             $('#updateRecordModal').on('hidden.bs.modal', function() {
                 $('#updateRecordForm')[0].reset();
                 $('#updateRecordForm').validate().resetForm();
             });
             
             // Clear search function
             window.clearSearch = function() {
                 $('input[name="search"]').val('');
                 $('#filterForm').submit();
             };
             
             // Preserve filters in pagination links
             $('.pagination a').each(function() {
                 var href = $(this).attr('href');
                 if (href && href.indexOf('?') === -1) {
                     href = '?' + $('#filterForm').serialize();
                 } else {
                     // Get current filters
                     var filters = $('#filterForm').serialize();
                     // Remove page parameter if exists
                     filters = filters.replace(/page=\d+&?/, '');
                     // Add to existing href
                     href += (href.indexOf('?') === -1 ? '?' : '&') + filters
                 }
                 $(this).attr('href', href);
             });
             
             // Auto-submit form when filter selects change
             $('select[name="owner_filter"], select[name="sales_person_filter"]').change(function() {
                 $('#filterForm').submit();
             });
         
             
         });
         
         
         const toggleBtn = document.getElementById('fullscreenToggle');
         
         function isFullscreen() {
           return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
         }
         
         function enterFullscreen() {
           const elem = document.documentElement;
           if (elem.requestFullscreen) {
             elem.requestFullscreen();
           } else if (elem.webkitRequestFullscreen) {
             elem.webkitRequestFullscreen();
           } else if (elem.msRequestFullscreen) {
             elem.msRequestFullscreen();
           }
         }
         
         function exitFullscreen() {
           if (document.exitFullscreen) {
             document.exitFullscreen();
           } else if (document.webkitExitFullscreen) {
             document.webkitExitFullscreen();
           } else if (document.msExitFullscreen) {
             document.msExitFullscreen();
           }
         }
         
         toggleBtn.addEventListener('click', () => {
           if (isFullscreen()) {
             exitFullscreen();
           } else {
             enterFullscreen();
           }
         });
         
         // Update button label based on fullscreen change
         document.addEventListener('fullscreenchange', updateButtonLabel);
         document.addEventListener('webkitfullscreenchange', updateButtonLabel);
         document.addEventListener('msfullscreenchange', updateButtonLabel);
         
         function updateButtonLabel() {
           toggleBtn.textContent = isFullscreen() ? 'Exit Fullscreen' : 'Enter Fullscreen';
         }

         // CSV Download functionality
        $('#downloadCsv').click(function() {
            // Get current filter parameters
            var filters = $('#filterForm').serialize();
            
            // Open download in new window
            window.open('download_csv.php?' + filters, '_blank');
        });
      </script>
      <!-- Then your other scripts -->
      <script src="assets/js/vendor.js"></script>
      <script src="assets/js/app.js"></script>    
   </body>
</html>