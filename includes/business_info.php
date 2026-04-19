<?php 

// Show login popup only if not logged in
if (!$customer_data) {
    showLoginPopup($user_id, $profile_url, $customer_data);
} else {
    // Get profile picture from customer data
    $profile_picture = $customer_data['picture'] ?? '';
    ?>
    <div class="container mt-3" style="padding:0px;">
        <div class="alert alert-success fade show d-flex justify-content-between align-items-center" role="alert">
            <div class="d-flex align-items-center">
                <?php if (!empty($profile_picture)): ?>
                    <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile" class="rounded-circle me-2" style="width: 50px; height: 50px; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-person-circle me-2"></i>
                <?php endif; ?>
                <div>
                    Logged in as <strong><?= htmlspecialchars($customer_data['name']) ?></strong> 
                    (<?= htmlspecialchars($customer_data['email']) ?>)
                </div>
            </div>
            <div>
                <a href="?profile_url=<?= urlencode($profile_url) ?>&logout=1" class="btn btn-sm btn-danger">Logout</a>
            </div>
        </div>
    </div>
    <?php
}
// Show login popup only if not logged in

$show_subscription_popup = !$active_subscription;
$package_id = $active_subscription ? $active_subscription['package_id'] : null;
?>    
<?php if ($show_subscription_popup): ?>
<!-- Overlay -->
<div class="overlay" id="subscriptionOverlay"></div>

<!-- Subscription Popup -->
<div class="subscription-popup" id="subscriptionPopup">
    <!-- <button type="button" class="btn-close" onclick="closeSubscriptionPopup()"></button> -->
    <h3>Subscription Expired</h3>
    <p>You don't have any active subscription. Please subscribe to continue using our services.</p>
    <button class="btn btn-primary" onclick="redirectToSubscription()">Subscribe Now</button>
</div>

<script>
    // Show the popup when page loads
    window.onload = function() {
        document.getElementById('subscriptionOverlay').style.display = 'block';
        document.getElementById('subscriptionPopup').style.display = 'block';
    };
    
    function closeSubscriptionPopup() {
        document.getElementById('subscriptionOverlay').style.display = 'none';
        document.getElementById('subscriptionPopup').style.display = 'none';
    }
    
    function redirectToSubscription() {
        // Replace with your actual subscription page URL
        window.location.href = 'login.php';
    }
</script>
<?php endif; ?>

<?php if ($business_info): ?>
<div class="business_details mt-4">
    <h6>Business</h6>
    <h2><?= htmlspecialchars($business_info['business_name']) ?></h2>
    <p><?= htmlspecialchars($business_info['business_description']) ?></p>
    
    <!-- Store Status Badge -->
    <div class="store-status-badge">
        <div class="status-indicator <?php echo $is_store_open ? 'open' : 'closed'; ?>">
            <?php echo $is_store_open ? '🟢 Open' : '🔴 Closed'; ?>
        </div>
        <div class="timing-info">
            <?php if ($is_store_open && $store_timing_data): ?>
                <small>Closes at <?php echo date('g:i A', strtotime($store_timing_data['close_time'])); ?></small>
            <?php elseif (!$is_store_open && $next_opening_time): ?>
                <small>Opens at <?php echo $next_opening_time; ?></small>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($business_info['website'])): ?>
    <p><i class="bi bi-globe"></i> <a href="https://<?= htmlspecialchars($business_info['website']) ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($business_info['website']) ?></a></p>
    <?php endif; ?>
    <?php if (!empty($business_info['business_address'])): ?>
    <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($business_info['business_address']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>