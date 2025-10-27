<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata');
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT role, name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($role, $user_name);
$user_stmt->fetch();
$user_stmt->close();

// Restrict access to printer role only
if ($role !== 'printer') {
    header("Location: index.php");
    exit();
}

// Fetch messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get search term from GET parameter
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build card designs query with search filter
$cards_sql = "SELECT uc.*, u.name as user_name 
              FROM user_cards uc
              JOIN users u ON uc.user_id = u.id";

// Add WHERE clause if search term exists
if (!empty($search_term)) {
    $search_like = "%" . $conn->real_escape_string($search_term) . "%";
    $cards_sql .= " WHERE (u.name LIKE ? OR uc.card_type LIKE ? OR uc.file_path LIKE ?)";
}

$cards_sql .= " ORDER BY uc.uploaded_at DESC, u.name ASC, uc.card_type ASC";

// Prepare and execute the query with search parameters if needed
$cards_stmt = $conn->prepare($cards_sql);
if (!empty($search_term)) {
    $cards_stmt->bind_param("sss", $search_like, $search_like, $search_like);
}
$cards_stmt->execute();
$cards_result = $cards_stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Card Designs | Printer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <style>
        .card-design-container {
            margin-bottom: 30px;
        }
        .card-design-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .card-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
        }
        .file-info {
            margin-top: 10px;
            font-size: 0.9em;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .search-form {
            display: flex;
            gap: 10px;
        }
        .search-form .form-control {
            flex-grow: 1;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .mr-1 {
            margin-right: 0.25rem !important;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'printer_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <!-- Card Designs Table -->
                <div class="row mt-4">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="card-title">Card Designs</h4>
                                <div class="search-container">
                                    <form method="get" action="" class="search-form">
                                        <input type="text" class="form-control" name="search" placeholder="Search by user, type, or filename" value="<?php echo htmlspecialchars($search_term); ?>">
                                        <button type="submit" class="btn btn-primary">Search</button>
                                        <?php if (!empty($search_term)): ?>
                                            <a href="list_of_printing_cards.php" class="btn btn-secondary">Clear</a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User Name</th>
                                                <th>Card Type</th>
                                                <th>File</th>
                                                <th>Uploaded At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($cards_result->num_rows > 0): ?>
                                                <?php while ($card = $cards_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo $card['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($card['user_name']); ?></td>
                                                        <td><?php echo ucfirst($card['card_type']); ?> Design</td>
                                                        <td>
                                                            <?php 
                                                            $file_name = basename($card['file_path']);
                                                            echo htmlspecialchars($file_name);
                                                            ?>
                                                        </td>
                                                        <td><?php echo date('d M Y H:i', strtotime($card['uploaded_at'])); ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="<?php echo $card['file_path']; ?>" class="btn btn-sm btn-primary mr-1" download>
                                                                    Download
                                                                </a>
                                                                <a href="<?php echo $card['file_path']; ?>" class="btn btn-sm btn-info mr-1" target="_blank">
                                                                    View
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <?php echo empty($search_term) ? 'No card designs found' : 'No results found for "' . htmlspecialchars($search_term) . '"'; ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add jQuery before Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>