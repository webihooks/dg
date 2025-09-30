<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Fetch ratings data
$ratings = [];
$sql_ratings = "SELECT reviewer_name, reviewer_email, reviewer_phone, rating, feedback 
                FROM ratings 
                WHERE user_id = ? 
                ORDER BY created_at DESC";
$stmt_ratings = $conn->prepare($sql_ratings);
$stmt_ratings->bind_param("i", $user_id);
$stmt_ratings->execute();
$result = $stmt_ratings->get_result();

while ($row = $result->fetch_assoc()) {
    $ratings[] = $row;
}
$stmt_ratings->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Customer Reviews</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.materialdesignicons.com/5.4.55/css/materialdesignicons.min.css" rel="stylesheet">
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
                    <div class="col-xl-12">
                        
                        <div class="card">

                            <div class="card-header">
                                <h4 class="card-title">Customer Reviews</h4>
                            </div>


                            <div class="card-body">
                                
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (empty($ratings)): ?>
                                    <div class="alert alert-info">No ratings found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-centered mb-0">
                                            <thead>
                                                <tr>
                                                    <th width="50">Sr. No.</th>
                                                    <th width="150">Reviewer Name</th>
                                                    <th>Email</th>
                                                    <th>Phone</th>
                                                    <th width="100">Rating</th>
                                                    <th>Feedback</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ratings as $index => $rating): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo htmlspecialchars($rating['reviewer_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($rating['reviewer_email']); ?></td>
                                                        <td><?php echo htmlspecialchars($rating['reviewer_phone']); ?></td>
                                                        <td>
                                                            <?php 
                                                            // Display star rating
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $rating['rating']) {
                                                                    echo '<i class="mdi mdi-star text-warning"></i>';
                                                                } else {
                                                                    echo '<i class="mdi mdi-star-outline text-warning"></i>';
                                                                }
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($rating['feedback']); ?></td>
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
    
</body>
</html>