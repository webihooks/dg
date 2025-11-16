<?php
// Debug: Check what photos data we have
error_log("Room Photos data: " . print_r($photos, true));
error_log("Room User ID: " . $user_id);
?>

<!-- Hero Image Section -->
<div class="relative">
    <?php 
    // Check if cover photo exists and build correct path
    $cover_photo_path = null;
    if (!empty($photos['cover_photo'])) {
        // Try different possible paths
        $possible_paths = [
            "https://deegeecard.com/uploads/cover/{$photos['cover_photo']}",
            "https://deegeecard.com/uploads/{$photos['cover_photo']}",
            "https://deegeecard.com/{$photos['cover_photo']}",
            $photos['cover_photo'] // In case it's already a full URL
        ];
        
        foreach ($possible_paths as $path) {
            // Use curl to check if image exists
            $ch = curl_init($path);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $cover_photo_path = $path;
                break;
            }
        }
    }
    ?>
    
    <?php if ($cover_photo_path): ?>
    <img src="<?= htmlspecialchars($cover_photo_path) ?>" 
         class="w-full h-48 object-cover" 
         alt="Cover Photo"
         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
    <div class="w-full h-48 bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center hidden">
        <i class="fas fa-hotel text-white text-4xl"></i>
    </div>
    <?php else: ?>
    <div class="w-full h-48 bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
        <i class="fas fa-hotel text-white text-4xl"></i>
    </div>
    <?php endif; ?>
    
    <!-- Profile Image Overlay -->
    <div class="absolute -bottom-10 left-4">
        <div class="relative">
            <?php 
            // Check if profile photo exists and build correct path
            $profile_photo_path = null;
            if (!empty($photos['profile_photo'])) {
                $possible_paths = [
                    "https://deegeecard.com/uploads/profile/{$photos['profile_photo']}",
                    "https://deegeecard.com/uploads/{$photos['profile_photo']}",
                    "https://deegeecard.com/{$photos['profile_photo']}",
                    $photos['profile_photo']
                ];
                
                foreach ($possible_paths as $path) {
                    $ch = curl_init($path);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http_code == 200) {
                        $profile_photo_path = $path;
                        break;
                    }
                }
            }
            ?>
            
            <?php if ($profile_photo_path): ?>
            <img src="<?= htmlspecialchars($profile_photo_path) ?>" 
                 class="w-20 h-20 rounded-full border-4 border-white object-cover shadow-md" 
                 alt="Profile Photo"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="w-20 h-20 rounded-full border-4 border-white bg-gray-200 flex items-center justify-center shadow-md hidden">
                <i class="fas fa-user text-gray-400 text-xl"></i>
            </div>
            <?php else: ?>
            <div class="w-20 h-20 rounded-full border-4 border-white bg-gray-200 flex items-center justify-center shadow-md">
                <i class="fas fa-user text-gray-400 text-xl"></i>
            </div>
            <?php endif; ?>
            <div class="absolute -bottom-1 -right-1 bg-green-500 rounded-full p-1">
                <i class="fas fa-check text-white text-xs"></i>
            </div>
        </div>
    </div>
</div>

<!-- Hotel Info Section -->
<div class="bg-white pt-12 pb-4 px-4">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['name'] ?? 'Hotel Name') ?></h1>
            
            <div class="flex items-center mt-2">
                <div class="flex text-yellow-400">
                    <i class="fas fa-star rating-star"></i>
                    <i class="fas fa-star rating-star" style="animation-delay: 0.2s"></i>
                    <i class="fas fa-star rating-star" style="animation-delay: 0.4s"></i>
                    <i class="fas fa-star rating-star" style="animation-delay: 0.6s"></i>
                    <i class="fas fa-star-half-alt rating-star" style="animation-delay: 0.8s"></i>
                </div>
                <span class="text-gray-600 text-sm ml-2">
                    <?php 
                    $rating_count = count($ratings);
                    if ($rating_count > 0) {
                        $total_rating = array_sum(array_column($ratings, 'rating'));
                        $average_rating = $total_rating / $rating_count;
                        echo number_format($average_rating, 1) . " ($rating_count reviews)";
                    } else {
                        echo "4.5 (128 reviews)";
                    }
                    ?>
                </span>
            </div>
            
            <?php if (!empty($business_info['business_address'])): ?>
            <div class="flex items-center mt-2 text-gray-600 text-sm">
                <i class="fas fa-map-marker-alt mr-1"></i>
                <span><?= htmlspecialchars($business_info['business_address']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="bg-white border-t border-b border-gray-200 py-3 px-4">
    <div class="flex justify-between">
        <!-- Call Button -->
        <button onclick="makeCall('<?= htmlspecialchars($user['phone'] ?? '') ?>')" 
                class="flex flex-col items-center text-gray-700 hover:text-green-600 transition-colors">
            <i class="fas fa-phone-alt text-green-500 mb-1"></i>
            <span class="text-xs">Call</span>
        </button>
        
        <!-- WhatsApp Button -->
        <button onclick="openWhatsApp('<?= htmlspecialchars($user['phone'] ?? '') ?>', '<?= htmlspecialchars($user['name'] ?? '') ?>')" 
                class="flex flex-col items-center text-gray-700 hover:text-green-600 transition-colors">
            <i class="fab fa-whatsapp text-green-500 mb-1"></i>
            <span class="text-xs">WhatsApp</span>
        </button>
        
        <!-- Directions Button -->
        <button onclick="openDirections('<?= htmlspecialchars($business_info['business_address'] ?? '') ?>', '<?= htmlspecialchars($business_info['google_direction'] ?? '') ?>')" 
                class="flex flex-col items-center text-gray-700 hover:text-green-600 transition-colors">
            <i class="fas fa-directions text-blue-500 mb-1"></i>
            <span class="text-xs">Directions</span>
        </button>
        
        <!-- Share Button -->
        <button onclick="shareProfile('<?= htmlspecialchars($user['name'] ?? '') ?>', '<?= htmlspecialchars($profile_url ?? ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])) ?>')" 
                class="flex flex-col items-center text-gray-700 hover:text-green-600 transition-colors">
            <i class="fas fa-share text-purple-500 mb-1"></i>
            <span class="text-xs">Share</span>
        </button>
    </div>
</div>

<!-- JavaScript for Actions -->
<script>
// Call function
function makeCall(phoneNumber) {
    if (!phoneNumber) {
        alert('Phone number not available');
        return;
    }
    
    // Clean phone number (remove spaces, dashes, etc.)
    const cleanNumber = phoneNumber.replace(/[\s\-\(\)]/g, '');
    
    // Check if it's a mobile device
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        window.location.href = `tel:${cleanNumber}`;
    } else {
        // For desktop, show number
        alert(`Call: ${cleanNumber}`);
    }
}

// WhatsApp function
function openWhatsApp(phoneNumber, businessName) {
    if (!phoneNumber) {
        alert('Phone number not available for WhatsApp');
        return;
    }
    
    const cleanNumber = phoneNumber.replace(/[\s\-\(\)]/g, '');
    const message = `Hello${businessName ? ' ' + businessName : ''}! I found your profile on DeegeeCard and would like to know more about your hotel services.`;
    const encodedMessage = encodeURIComponent(message);
    
    // Open WhatsApp with pre-filled message
    window.open(`https://wa.me/91${cleanNumber}?text=${encodedMessage}`, '_blank');
}

// Directions function
function openDirections(address, googleMapsLink) {
    if (googleMapsLink) {
        // If custom Google Maps link is provided
        window.open(googleMapsLink, '_blank');
    } else if (address) {
        // Open Google Maps with the address
        const encodedAddress = encodeURIComponent(address);
        window.open(`https://www.google.com/maps/search/?api=1&query=${encodedAddress}`, '_blank');
    } else {
        alert('Address not available');
    }
}

// Share function
function shareProfile(businessName, profileUrl) {
    const currentUrl = profileUrl || window.location.href;
    const shareData = {
        title: businessName || 'Hotel Profile',
        text: `Check out ${businessName || 'this hotel'} on DeegeeCard`,
        url: currentUrl
    };

    // Check if Web Share API is supported
    if (navigator.share) {
        navigator.share(shareData)
            .then(() => console.log('Successful share'))
            .catch((error) => console.log('Error sharing:', error));
    } else {
        // Fallback: copy to clipboard
        const tempInput = document.createElement('input');
        tempInput.value = currentUrl;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('Profile link copied to clipboard!');
    }
}

// Add some animation to rating stars
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.rating-star');
    stars.forEach((star, index) => {
        star.style.animation = `pulse 2s infinite ${index * 0.2}s`;
    });
});

// Add CSS for animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
`;
document.head.appendChild(style);
</script>