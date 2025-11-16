<?php
// room_share_section.php - Share Section for Rooms

// Function to generate share URLs
function getShareUrls($profile_url, $business_name, $description = '') {
    $encoded_url = urlencode($profile_url);
    $encoded_title = urlencode($business_name);
    $encoded_description = urlencode($description);
    
    return [
        'whatsapp' => "https://wa.me/?text=" . urlencode("Check out {$business_name}: {$profile_url}"),
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}",
        'twitter' => "https://twitter.com/intent/tweet?url={$encoded_url}&text={$encoded_title}",
        'telegram' => "https://t.me/share/url?url={$encoded_url}&text={$encoded_title}",
        'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$encoded_url}",
        'email' => "mailto:?subject=" . urlencode("Check out {$business_name}") . "&body=" . urlencode("I found this amazing hotel: {$profile_url}")
    ];
}

$business_name = $business_info['business_name'] ?? $user['name'] ?? 'Hotel';
$share_urls = getShareUrls(
    "https://deegeecard.com/post.php?profile_url=" . urlencode($profile_url),
    $business_name,
    $business_info['business_description'] ?? 'Amazing hotel experience'
);
?>

<!-- Share Section -->
<div class="mt-6 px-4">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <!-- Section Header -->
        <div class="text-center mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Share <?= htmlspecialchars($business_name) ?></h2>
            <p class="text-gray-600 text-sm">Share this hotel with friends and family</p>
        </div>

        <!-- Share Buttons Grid -->
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-4 mb-6">
            <!-- WhatsApp -->
            <button onclick="shareOnPlatform('whatsapp')" 
                    class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 border border-green-200 rounded-xl transition-all duration-300 transform hover:-translate-y-1 hover:shadow-md">
                <i class="fab fa-whatsapp text-green-600 text-2xl mb-2"></i>
                <span class="text-xs font-medium text-gray-700">WhatsApp</span>
            </button>

            <!-- Facebook -->
            <button onclick="shareOnPlatform('facebook')" 
                    class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl transition-all duration-300 transform hover:-translate-y-1 hover:shadow-md">
                <i class="fab fa-facebook text-blue-600 text-2xl mb-2"></i>
                <span class="text-xs font-medium text-gray-700">Facebook</span>
            </button>

            <!-- Twitter -->
            <button onclick="shareOnPlatform('twitter')" 
                    class="flex flex-col items-center p-4 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-xl transition-all duration-300 transform hover:-translate-y-1 hover:shadow-md">
                <i class="fab fa-twitter text-sky-500 text-2xl mb-2"></i>
                <span class="text-xs font-medium text-gray-700">Twitter</span>
            </button>

            <!-- Telegram -->
            <button onclick="shareOnPlatform('telegram')" 
                    class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl transition-all duration-300 transform hover:-translate-y-1 hover:shadow-md">
                <i class="fab fa-telegram text-blue-500 text-2xl mb-2"></i>
                <span class="text-xs font-medium text-gray-700">Telegram</span>
            </button>

            <!-- Email -->
            <button onclick="shareOnPlatform('email')" 
                    class="flex flex-col items-center p-4 bg-red-50 hover:bg-red-100 border border-red-200 rounded-xl transition-all duration-300 transform hover:-translate-y-1 hover:shadow-md">
                <i class="fas fa-envelope text-red-500 text-2xl mb-2"></i>
                <span class="text-xs font-medium text-gray-700">Email</span>
            </button>
        </div>
    </div>
</div>

<!-- Share Success Modal -->
<div id="shareSuccessModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check text-green-500 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2" id="shareSuccessTitle">Success!</h3>
        <p class="text-gray-600 text-sm mb-6" id="shareSuccessMessage">Profile link copied to clipboard!</p>
        <button class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-xl font-semibold text-sm transition-all duration-300"
                onclick="closeShareSuccessModal()">
            Continue
        </button>
    </div>
</div>

<script>
// Share functionality
const shareUrls = <?= json_encode($share_urls) ?>;

function shareOnPlatform(platform) {
    const url = shareUrls[platform];
    
    if (!url) {
        showShareSuccess('Error', 'Share platform not supported');
        return;
    }

    if (platform === 'email') {
        window.location.href = url;
    } else {
        window.open(url, '_blank', 'width=600,height=400');
    }
    
    // Track share event
    trackShareEvent(platform);
}

function trackShareEvent(platform) {
    // Here you can add analytics tracking
    console.log(`Shared on ${platform}`);
    
    // Example: Send to Google Analytics
    // gtag('event', 'share', {
    //     'method': platform,
    //     'content_type': 'hotel_profile',
    //     'content_id': '<?= $profile_url ?>'
    // });
}

function showShareSuccess(title, message) {
    const modal = document.getElementById('shareSuccessModal');
    const modalTitle = document.getElementById('shareSuccessTitle');
    const modalMessage = document.getElementById('shareSuccessMessage');
    
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeShareSuccessModal() {
    const modal = document.getElementById('shareSuccessModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

// Add animation to share buttons
document.addEventListener('DOMContentLoaded', function() {
    const shareButtons = document.querySelectorAll('.grid button');
    shareButtons.forEach((button, index) => {
        button.style.opacity = '0';
        button.style.transform = 'translateY(20px)';
        button.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        
        setTimeout(() => {
            button.style.opacity = '1';
            button.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Close modal when clicking outside
document.getElementById('shareSuccessModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeShareSuccessModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeShareSuccessModal();
    }
});
</script>

<style>
/* Print styles */
@media print {
    .room_share_section {
        display: none !important;
    }
}

/* Animation for success modal */
#shareSuccessModal > div {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Hover effects for social links */
.grid button:hover i {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}
</style>