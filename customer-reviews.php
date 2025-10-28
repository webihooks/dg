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
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card-header h4 {
                text-align: center;
                font-size: 1.25rem;
            }
            
            .table-responsive table {
                min-width: 700px; /* Allow horizontal scrolling */
            }
            
            .review-card {
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                background-color: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .review-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .reviewer-info {
                flex: 1;
            }
            
            .reviewer-name {
                font-weight: 600;
                font-size: 1.1rem;
                color: #333;
                margin-bottom: 5px;
            }
            
            .reviewer-contact {
                font-size: 0.9rem;
                color: #666;
            }
            
            .review-rating {
                text-align: center;
                min-width: 100px;
            }
            
            .stars {
                font-size: 1.2rem;
                margin-bottom: 5px;
            }
            
            .rating-text {
                font-size: 0.8rem;
                color: #666;
            }
            
            .review-feedback {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #f0f0f0;
            }
            
            .feedback-label {
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
                display: block;
            }
            
            .feedback-content {
                color: #555;
                line-height: 1.4;
            }
            
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #666;
            }
            
            .empty-state .mdi {
                font-size: 3rem;
                margin-bottom: 15px;
                color: #ccc;
            }
            
            .sr-no-badge {
                background-color: #f8f9fa;
                color: #495057;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.8rem;
                font-weight: 600;
                margin-right: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .review-header {
                flex-direction: column;
            }
            
            .review-rating {
                margin-top: 10px;
                align-self: flex-start;
            }
            
            .reviewer-contact {
                display: flex;
                flex-direction: column;
                gap: 3px;
            }
            
            .card-body {
                padding: 15px;
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
        
        /* Star rating styles */
        .mdi-star {
            color: #ffc107;
        }
        
        .mdi-star-outline {
            color: #ddd;
        }
        
        /* No reviews state */
        .no-reviews {
            text-align: center;
            padding: 40px 20px;
        }
        
        .no-reviews .mdi {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .no-reviews h5 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-reviews p {
            color: #888;
            max-width: 400px;
            margin: 0 auto;
        }
        .scroll-to-top.show {
          bottom: 15px;
        }
    </style>
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
                                    <div class="no-reviews">
                                        <i class="mdi mdi-star-outline"></i>
                                        <h5>No Reviews Yet</h5>
                                        <p>Customer reviews will appear here once they start rating your service.</p>
                                    </div>
                                <?php else: ?>
                                    <!-- Desktop Table View -->
                                    <div class="desktop-table">
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
                                                                <small class="text-muted d-block mt-1">(<?php echo $rating['rating']; ?>/5)</small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($rating['feedback']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Mobile Card View -->
                                    <div class="mobile-cards">
                                        <?php foreach ($ratings as $index => $rating): ?>
                                            <div class="review-card">
                                                <div class="review-header">
                                                    <div class="reviewer-info">
                                                        <div class="d-flex align-items-center">
                                                            <div class="sr-no-badge">
                                                                <?php echo $index + 1; ?>
                                                            </div>
                                                            <div>
                                                                <div class="reviewer-name">
                                                                    <?php echo htmlspecialchars($rating['reviewer_name']); ?>
                                                                </div>
                                                                <div class="reviewer-contact">
                                                                    <?php if (!empty($rating['reviewer_email'])): ?>
                                                                        <div>
                                                                            <i class="mdi mdi-email-outline me-1"></i>
                                                                            <?php echo htmlspecialchars($rating['reviewer_email']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($rating['reviewer_phone'])): ?>
                                                                        <div>
                                                                            <i class="mdi mdi-phone-outline me-1"></i>
                                                                            <?php echo htmlspecialchars($rating['reviewer_phone']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="review-rating">
                                                        <div class="stars">
                                                            <?php 
                                                            // Display star rating
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $rating['rating']) {
                                                                    echo '<i class="mdi mdi-star"></i>';
                                                                } else {
                                                                    echo '<i class="mdi mdi-star-outline"></i>';
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="rating-text">
                                                            <?php echo $rating['rating']; ?>/5
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty(trim($rating['feedback']))): ?>
                                                    <div class="review-feedback">
                                                        <span class="feedback-label">Feedback:</span>
                                                        <div class="feedback-content">
                                                            <?php echo htmlspecialchars($rating['feedback']); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            // Add animation to review cards on mobile
            $('.review-card').each(function(index) {
                $(this).css({
                    'opacity': '0',
                    'transform': 'translateY(20px)'
                });
                
                setTimeout(() => {
                    $(this).animate({
                        'opacity': '1',
                        'transform': 'translateY(0)'
                    }, 300);
                }, index * 100);
            });
            
            // Handle empty state for mobile
            if ($('.mobile-cards').is(':visible') && $('.review-card').length === 0) {
                $('.mobile-cards').html(`
                    <div class="empty-state">
                        <i class="mdi mdi-star-outline"></i>
                        <h5>No Reviews Yet</h5>
                        <p>Customer reviews will appear here once they start rating your service.</p>
                    </div>
                `);
            }
        });
    </script>
</body>
</html>