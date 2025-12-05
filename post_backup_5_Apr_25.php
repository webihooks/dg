<?php
// Database connection
require 'db_connection.php';

// Get profile_url from URL or redirect if not found
if(isset($_GET['profile_url'])) {
    $profile_url = $_GET['profile_url'];
    
    // First, get user_id from profile_url_details table
    $profile_query = "SELECT user_id FROM profile_url_details WHERE profile_url = ?";
    $stmt = $conn->prepare($profile_query);
    $stmt->bind_param("s", $profile_url);
    $stmt->execute();
    $profile_result = $stmt->get_result();
    
    if($profile_result->num_rows === 0) {
        // Profile URL not found
        header("Location: page-not-found.php");
    }
    
    $profile_data = $profile_result->fetch_assoc();
    $user_id = $profile_data['user_id'];
    $stmt->close();
} else {
    // If profile_url is not provided, redirect to a default page or show error
    header("HTTP/1.0 400 Bad Request");
    die("Profile URL is required");
}

if ($user_id <= 0) {
    die("Invalid user ID");
}

// Fetch user info
$user_query = "SELECT name, email, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->num_rows > 0 ? $user_result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    die("User not found");
}

// Fetch business info
$business_query = "SELECT business_name, business_description, business_address, google_direction, designation 
                  FROM business_info WHERE user_id = ?";
$stmt = $conn->prepare($business_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$business_result = $stmt->get_result();
$business_info = $business_result->num_rows > 0 ? $business_result->fetch_assoc() : null;
$stmt->close();

// Fetch profile and cover photos
$photos_query = "SELECT profile_photo, cover_photo FROM profile_cover_photo WHERE user_id = ?";
$stmt = $conn->prepare($photos_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$photos_result = $stmt->get_result();
$photos = $photos_result->num_rows > 0 ? $photos_result->fetch_assoc() : null;
$stmt->close();

// Fetch Social Link
$social_query = "SELECT facebook, instagram, whatsapp, linkedin, youtube, telegram 
                  FROM social_link WHERE user_id = ?";
$stmt = $conn->prepare($social_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$social_result = $stmt->get_result();
$social_link = $social_result->num_rows > 0 ? $social_result->fetch_assoc() : null;
$stmt->close();

// Fetch Products
$products_query = "SELECT product_name, description, price, quantity, image_path FROM products WHERE user_id = ?";
$stmt = $conn->prepare($products_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$products_result = $stmt->get_result();
$products = [];
if ($products_result->num_rows > 0) {
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();

// Fetch Services
$services_query = "SELECT service_name, description, price, duration, image_path FROM services WHERE user_id = ?";
$stmt = $conn->prepare($services_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
if ($services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }
}
$stmt->close();

// Fetch Photo Gallery
$gallery_query = "SELECT filename, photo_gallery_path, title, description FROM photo_gallery WHERE user_id = ?";
$stmt = $conn->prepare($gallery_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$gallery_result = $stmt->get_result();
$gallery = [];
if ($gallery_result->num_rows > 0) {
    while ($row = $gallery_result->fetch_assoc()) {
        $gallery[] = $row;
    }
}
$stmt->close();

// Fetch Ratings
$ratings_query = "SELECT reviewer_name, rating, feedback, created_at FROM ratings WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($ratings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratings_result = $stmt->get_result();
$ratings = [];
if ($ratings_result->num_rows > 0) {
    while ($row = $ratings_result->fetch_assoc()) {
        $ratings[] = $row;
    }
}
$stmt->close();

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $reviewer_name = $_POST['reviewer_name'] ?? '';
    $reviewer_email = $_POST['reviewer_email'] ?? '';
    $reviewer_phone = $_POST['reviewer_phone'] ?? '';
    $rating = intval($_POST['rating'] ?? 0);
    $feedback = $_POST['feedback'] ?? '';
    
    if (!empty($reviewer_name) && $rating >= 1 && $rating <= 5) {
        $insert_query = "INSERT INTO ratings (user_id, reviewer_name, reviewer_email, reviewer_phone, rating, feedback) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isssis", $user_id, $reviewer_name, $reviewer_email, $reviewer_phone, $rating, $feedback);
        $stmt->execute();
        $stmt->close();
        
        // Refresh to show the new rating
        header("Location: ?profile_url=$profile_url");
        exit();
    }
}

// Fetch Ratings - Modified to only show ratings 3, 4, or 5
$ratings_query = "SELECT reviewer_name, rating, feedback, created_at FROM ratings WHERE user_id = ? AND rating IN (3, 4, 5) ORDER BY created_at DESC";
$stmt = $conn->prepare($ratings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratings_result = $stmt->get_result();
$ratings = [];
if ($ratings_result->num_rows > 0) {
    while ($row = $ratings_result->fetch_assoc()) {
        $ratings[] = $row;
    }
}
$stmt->close();

// Fetch Bank Details
$bank_query = "SELECT account_name, bank_name, account_number, account_type, ifsc_code 
              FROM bank_details WHERE user_id = ?";
$stmt = $conn->prepare($bank_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bank_result = $stmt->get_result();
$bank_details = [];
if ($bank_result->num_rows > 0) {
    while ($row = $bank_result->fetch_assoc()) {
        $bank_details[] = $row;
    }
}
$stmt->close();

// Fetch QR Code Details
$qr_query = "SELECT id, mobile_number, upload_qr_code, payment_type, is_default 
             FROM qrcode_details 
             WHERE user_id = ? 
             ORDER BY is_default DESC, created_at DESC";
$stmt = $conn->prepare($qr_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$qr_result = $stmt->get_result();
$qr_codes = [];
if ($qr_result->num_rows > 0) {
    while ($row = $qr_result->fetch_assoc()) {
        $qr_codes[] = $row;
    }
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
<title><?php echo htmlspecialchars($user['name']); ?> | <?php echo htmlspecialchars($business_info['business_name'] ?? ''); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link href="assets/css/main.css" rel="stylesheet">
<script>

window.addEventListener('scroll', function() {
    const coverPhoto = document.querySelector('.cover_photo');
    if (window.scrollY > 50) { // Adjust this value to change when the animation triggers
        coverPhoto.classList.add('small');
    } else {
        coverPhoto.classList.remove('small');
    }
});

window.addEventListener('scroll', function() {
  const scrollY = window.scrollY;
  const profilePhoto = document.querySelector('.profile_photo');
  const burgerMenu = document.querySelector('.burger-menu');
  
  if (scrollY > 50) {
    profilePhoto.classList.add('small');
    burgerMenu.classList.add('show');
    profilePhoto.classList.add('with-burger');
  } else {
    profilePhoto.classList.remove('small');
    burgerMenu.classList.remove('show');
    profilePhoto.classList.remove('with-burger');
  }
});





function sendProductEnquiry(productName, productPrice, productDescription) {
    // Get WhatsApp number from social links
    const whatsappLink = "<?php echo isset($social_link['whatsapp']) ? $social_link['whatsapp'] : '' ?>";
    
    // Extract phone number from WhatsApp link (assuming format is https://wa.me/91XXXXXXXXXX)
    let phoneNumber = '';
    if (whatsappLink) {
        const matches = whatsappLink.match(/wa\.me\/(\d+)/) || whatsappLink.match(/whatsapp\.com/);
        if (matches && matches[1]) {
            phoneNumber = matches[1];
        }
    }
    
    // If we couldn't extract from link, try to get from user phone
    if (!phoneNumber) {
        phoneNumber = "<?php echo isset($user['phone']) ? $user['phone'] : '' ?>";
    }
    
    // Format the message
    const message = `Product Enquiry:\n\n*Product Name:* ${productName}\n*Price:* ₹${productPrice}\n*Description:* ${productDescription}\n\nI'm interested in this product. Please provide more details.`;
    
    // Create WhatsApp URL
    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
    
    // Open in new tab
    window.open(whatsappUrl, '_blank');
}

function sendServiceEnquiry(serviceName, servicePrice, serviceDescription, serviceDuration) {
    // Get WhatsApp number from social links
    const whatsappLink = "<?php echo isset($social_link['whatsapp']) ? $social_link['whatsapp'] : '' ?>";
    
    // Extract phone number from WhatsApp link (assuming format is https://wa.me/91XXXXXXXXXX)
    let phoneNumber = '';
    if (whatsappLink) {
        const matches = whatsappLink.match(/wa\.me\/(\d+)/) || whatsappLink.match(/whatsapp\.com/);
        if (matches && matches[1]) {
            phoneNumber = matches[1];
        }
    }
    
    // If we couldn't extract from link, try to get from user phone
    if (!phoneNumber) {
        phoneNumber = "<?php echo isset($user['phone']) ? $user['phone'] : '' ?>";
    }
    
    // Format the message
    const message = `Service Enquiry:\n\n*Service Name:* ${serviceName}\n*Price:* ₹${servicePrice}\n*Duration:* ${serviceDuration}\n*Description:* ${serviceDescription}\n\nI'm interested in this service. Please provide more details.`;
    
    // Create WhatsApp URL
    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
    
    // Open in new tab
    window.open(whatsappUrl, '_blank');
}
</script>
</head>
<body class="restaurant">

<div class="main">

    <!-- Menu -->
    <div class="burger-menu">
      <div class="bar1"></div>
      <div class="bar2"></div>
      <div class="bar3"></div>
    </div>


    <!-- Cover -->
    <div class="cover_photo">
        <?php if (!empty($photos['cover_photo'])): ?>
        <img src="uploads/cover/<?php echo htmlspecialchars($photos['cover_photo']); ?>" class="img-fluid" alt="Cover Photo">
        <?php endif; ?>
    </div>

    <!-- Profile Photo -->
    <div class="profile_photo">
        <?php if (!empty($photos['profile_photo'])): ?>
        <img src="uploads/profile/<?php echo htmlspecialchars($photos['profile_photo']); ?>" class="img-fluid" alt="Profile Photo">
        <?php endif; ?>
    </div>

    <!-- Profile Name and Detials -->
    <div class="personal_info">
        <h1><?php echo htmlspecialchars($user['name']); ?></h1>

        <div class="designation">
            <?php if ($business_info && !empty($business_info['designation'])): ?>
            <h2><?php echo htmlspecialchars($business_info['designation']); ?></h2>
            <?php endif; ?>
        </div>

        <ul class="social_networks">
            <?php if (!empty($social_link['facebook'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['facebook']); ?>" target="_blank"><i class="bi bi-facebook"></i></a>
            </li>
            <?php endif; ?>
            <?php if (!empty($social_link['instagram'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['instagram']); ?>" target="_blank"><i class="bi bi-instagram"></i></a>
            </li>
            <?php endif; ?>
            <?php if (!empty($social_link['whatsapp'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['whatsapp']); ?>" target="_blank"><i class="bi bi-whatsapp"></i></a>
            </li>
            <?php endif; ?>
            <?php if (!empty($social_link['linkedin'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['linkedin']); ?>" target="_blank"><i class="bi bi-linkedin"></i></a>
            </li>
            <?php endif; ?>
            <?php if (!empty($social_link['youtube'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['youtube']); ?>" target="_blank"><i class="bi bi-youtube"></i></a>
            </li>
            <?php endif; ?>
            <?php if (!empty($social_link['telegram'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['telegram']); ?>" target="_blank"><i class="bi bi-telegram"></i></a>
            </li>
            <?php endif; ?>
        </ul>
        
        <ul class="personal_contact mt-4">
            <?php if (!empty($user['phone'])): ?>
            <li>
                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>">
                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (!empty($social_link['whatsapp'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['whatsapp']); ?>">
                    <i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($social_link['whatsapp']); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (!empty($user['email'])): ?>
            <li>
                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (!empty($business_info['google_direction'])): ?>
            <li>
                <a href="<?php echo htmlspecialchars($business_info['google_direction']); ?>" target="_blank">
                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($business_info['google_direction']); ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Business Details -->
    <?php if ($business_info): ?>
    <div class="business_details">
        <h6>Business</h6>
        <h2><?php echo htmlspecialchars($business_info['business_name']); ?></h2>
        <p><?php echo htmlspecialchars($business_info['business_description']); ?></p>
        <?php if (!empty($business_info['business_address'])): ?>
        <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($business_info['business_address']); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Products -->
    <div class="products">
        <h6>Products</h6>
        <div class="row">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-sm-12">
                        <div class="card product-card">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200?text=No+Image" class="card-img-top product-img" alt="No Image Available">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-primary fw-bold">₹<?php echo number_format($product['price']); ?></span>
                                    <span class="badge bg-<?php echo ($product['quantity'] > 0) ? 'success' : 'danger'; ?>">
                                        <?php echo ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </div>
                                <?php if ($product['quantity'] > 0): ?>
                                    <small class="text-muted">Quantity: <?php echo $product['quantity']; ?></small>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <button class="btn btn-success w-100" 
                                            onclick="sendProductEnquiry(
                                                '<?php echo htmlspecialchars($product['product_name']); ?>',
                                                '<?php echo number_format($product['price'], 2); ?>',
                                                '<?php echo htmlspecialchars($product['description']); ?>'
                                            )">
                                        <i class="bi bi-whatsapp"></i> Enquire on WhatsApp
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No products available yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Services -->
    <!-- <div class="services">
        <h6>Services</h6>
        <div class="row">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $service): ?>
                    <div class="col-sm-12">
                        <div class="card service-card">
                            <?php if (!empty($service['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($service['image_path']); ?>" class="card-img-top service-img" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200?text=No+Image" class="card-img-top service-img" alt="No Image Available">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($service['service_name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-primary fw-bold">₹<?php echo number_format($service['price'], 2); ?></span>
                                    <span class="badge duration-badge">
                                        <i class="bi bi-clock"></i> <?php echo htmlspecialchars($service['duration']); ?>
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-success w-100" 
                                            onclick="sendServiceEnquiry(
                                                '<?php echo htmlspecialchars($service['service_name']); ?>',
                                                '<?php echo number_format($service['price'], 2); ?>',
                                                '<?php echo htmlspecialchars($service['description']); ?>',
                                                '<?php echo htmlspecialchars($service['duration']); ?>'
                                            )">
                                        <i class="bi bi-whatsapp"></i> Enquire on WhatsApp
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No services available yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div> -->

    <!-- Gallery -->
    <div class="gallery">
        <h6>Photo Gallery</h6>
        <div class="row">
        <?php if (!empty($gallery)): ?>
            <?php foreach ($gallery as $photo): ?>
                <div class="col-sm-12">
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($photo['photo_gallery_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($photo['title']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($photo['title']); ?></h5>
                            <p class="card-text">
                                <?php echo htmlspecialchars($photo['description']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No photos in the gallery yet.</div>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Display Ratings -->
    <div class="display_ratings">
        <h6>Customer Reviews</h6>
        
        <?php if (!empty($ratings)): ?>
            <div class="row">
                <?php foreach ($ratings as $review): ?>
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h5 class="card-title"><?php echo htmlspecialchars($review['reviewer_name']); ?></h5>
                                    <div class="star-rating">
                                        <?php echo str_repeat('★', $review['rating']); ?><?php echo str_repeat('☆', 5 - $review['rating']); ?>
                                    </div>
                                </div>
                                <p class="card-text"><?php echo htmlspecialchars($review['feedback']); ?></p>
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No reviews yet.</div>
        <?php endif; ?>
    </div>

    <!-- Rating Form -->
    <div class="rating card">
    <h6>Leave a Review</h6>
        <form method="POST">
            <div class="row mb-3">
                <div class="col-sm-12">
                    <label for="reviewer_name" class="form-label">Your Name*</label>
                    <input type="text" class="form-control" id="reviewer_name" name="reviewer_name" required>
                </div>
                <div class="col-sm-12">
                    <label for="reviewer_email" class="form-label">Your Email</label>
                    <input type="email" class="form-control" id="reviewer_email" name="reviewer_email">
                </div>
                <div class="col-sm-12">
                    <label for="reviewer_phone" class="form-label">Your Phone</label>
                    <input type="tel" class="form-control" id="reviewer_phone" name="reviewer_phone" pattern="[0-9]{10,15}" title="Phone number (10-15 digits)">
                </div>
            </div>
            
            <div class="mb-3 col-sm-12">
                <label class="form-label">Rating*</label>
                <div class="rating-input">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating1" value="1" required>
                        <label class="form-check-label" for="rating1">1 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating2" value="2">
                        <label class="form-check-label" for="rating2">2 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating3" value="3">
                        <label class="form-check-label" for="rating3">3 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating4" value="4">
                        <label class="form-check-label" for="rating4">4 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating5" value="5">
                        <label class="form-check-label" for="rating5">5 ★</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="feedback" class="form-label">Your Feedback</label>
                <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
            </div>
            
            <button type="submit" name="submit_rating" class="btn btn-primary">Submit Review</button>
        </form>
    </div>

    <!-- Bank Details -->
    <div class="bank_details">
        <h6>Bank Accounts</h6>
        
        <?php if (!empty($bank_details)): ?>
            <div class="row">
                <?php foreach ($bank_details as $account): ?>
                    <div class="col-sm-12 mb-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($account['account_name']); ?></h5>
                                <div class="bank-details">
                                    <p><strong>Bank:</strong> <?php echo htmlspecialchars($account['bank_name']); ?></p>
                                    <p><strong>Account Number:</strong> <?php echo htmlspecialchars($account['account_number']); ?></p>
                                    <p><strong>Account Type:</strong> <?php echo htmlspecialchars($account['account_type']); ?></p>
                                    <p><strong>IFSC Code:</strong> <?php echo htmlspecialchars($account['ifsc_code']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No bank accounts registered yet.</div>
        <?php endif; ?>
    </div>

    <!-- QR Code Payment Methods -->
    <div class="qr_code_details">
        <h6>QR Code Payment Methods</h6>
        
        <?php if (!empty($qr_codes)): ?>
            <div class="row">
                <?php foreach ($qr_codes as $qr): ?>
                    <div class="col-sm-12 mb-2">
                        <div class="card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-sm-12 text-center">
                                        <img src="uploads/qrcodes/<?php echo htmlspecialchars($qr['upload_qr_code']); ?>" 
                                             class="img-fluid qr-code-img" 
                                             alt="Payment QR Code"
                                             style="max-width: 150px;">
                                    </div>
                                    <div class="col-sm-12">
                                        <h5><?php echo htmlspecialchars($qr['payment_type']); ?></h5>
                                        <p class="mb-1">
                                            <i class="bi bi-phone"></i> <?php echo htmlspecialchars($qr['mobile_number']); ?>
                                        </p>
                                        <?php if ($qr['is_default']): ?>
                                            <span class="badge bg-success">Default Payment Method</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-sm-12 text-md-end mt-3 mt-md-0">
                                        <button class="btn btn-outline-primary btn-sm" 
                                                onclick="showQrModal('<?php echo htmlspecialchars($qr['payment_type']); ?>', 'uploads/qrcodes/<?php echo htmlspecialchars($qr['upload_qr_code']); ?>')">
                                            <i class="bi bi-zoom-in"></i> Enlarge
                                        </button>
                                        <a href="upi://pay?pa=<?php echo urlencode($qr['mobile_number']); ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="bi bi-arrow-up-right-circle"></i> Pay Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No QR code payment methods available yet.</div>
        <?php endif; ?>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalQrImage" src="" class="img-fluid" alt="QR Code">
                    <div class="mt-3">
                        <a href="#" id="payNowLink" class="btn btn-primary">
                            <i class="bi bi-arrow-up-right-circle"></i> Pay Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Share -->
    <div class="share-section">
        <h6>Share Profile</h6>
        <div class="share-buttons">
            <?php 
            $current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $share_text = "Check out " . htmlspecialchars($user['name']) . "'s profile";
            if ($business_info && !empty($business_info['business_name'])) {
                $share_text .= " - " . htmlspecialchars($business_info['business_name']);
            }
            ?>
            
            <!-- WhatsApp -->
            <a href="https://wa.me/?text=<?php echo urlencode($share_text . ': ' . $current_url); ?>" 
               target="_blank" class="share-btn whatsapp">
                <i class="bi bi-whatsapp"></i>
            </a>
            
            <!-- Facebook -->
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" 
               target="_blank" class="share-btn facebook">
                <i class="bi bi-facebook"></i>
            </a>
            
            <!-- Twitter -->
            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($share_text); ?>&url=<?php echo urlencode($current_url); ?>" 
               target="_blank" class="share-btn twitter">
                <i class="bi bi-twitter"></i>
            </a>
            
            <!-- LinkedIn -->
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($current_url); ?>&title=<?php echo urlencode($share_text); ?>" 
               target="_blank" class="share-btn linkedin">
                <i class="bi bi-linkedin"></i>
            </a>
            
            <!-- Telegram -->
            <a href="https://t.me/share/url?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($share_text); ?>" 
               target="_blank" class="share-btn telegram">
                <i class="bi bi-telegram"></i>
            </a>
            
            <!-- Email -->
            <a href="mailto:?subject=<?php echo rawurlencode($share_text); ?>&body=<?php echo rawurlencode("Check out this profile: " . $current_url); ?>" 
               class="share-btn email">
                <i class="bi bi-envelope"></i>
            </a>
        </div>
    </div>


    

    <script>
    function showQrModal(paymentType, imageSrc) {
        document.getElementById('qrModalTitle').textContent = paymentType + ' QR Code';
        document.getElementById('modalQrImage').src = imageSrc;
        document.getElementById('payNowLink').href = 'upi://pay?pa=' + encodeURIComponent('<?php echo isset($qr_codes[0]["mobile_number"]) ? $qr_codes[0]["mobile_number"] : ""; ?>');
        var modal = new bootstrap.Modal(document.getElementById('qrModal'));
        modal.show();
    }
    </script>

    <p class="text-center"><a href="login.php">Login</a></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>