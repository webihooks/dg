<?php
// Function to get average rating (excluding 1 and 2 star ratings)
function getAverageRating($ratings) {
    if (empty($ratings)) {
        return 5.0; // Default to 5 stars if no reviews
    }
    
    // Filter out 1 and 2 star ratings
    $filtered_ratings = array_filter($ratings, function($rating) {
        return $rating['rating'] >= 3;
    });
    
    if (empty($filtered_ratings)) {
        return 5.0; // Default to 5 stars if all ratings are 1-2 stars
    }
    
    $total_rating = array_sum(array_column($filtered_ratings, 'rating'));
    return round($total_rating / count($filtered_ratings), 1);
}

// Function to get rating distribution (excluding 1 and 2 stars)
function getRatingDistribution($ratings) {
    $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    
    foreach ($ratings as $rating) {
        $star = (int)$rating['rating'];
        if (isset($distribution[$star])) {
            $distribution[$star]++;
        }
    }
    
    return $distribution;
}

// Function to get rating percentage
function getRatingPercentage($distribution, $total) {
    if ($total === 0) return 0;
    return round(($distribution / $total) * 100, 1);
}

// Function to get time ago
function getTimeAgo($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}

// Calculate rating statistics
$average_rating = getAverageRating($ratings);
$total_reviews = count($ratings);

// Filter out 1 and 2 star reviews for display
$filtered_ratings = array_filter($ratings, function($rating) {
    return $rating['rating'] >= 3;
});

$displayed_reviews_count = count($filtered_ratings);
$rating_distribution = getRatingDistribution($ratings);
?>

<!-- Ratings and Reviews Section -->
<div class="mt-6 px-4">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <!-- Section Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Guest Reviews</h2>
            <button class="primary-bg text-white px-6 py-3 rounded-xl font-semibold text-sm flex items-center transition-all duration-300 hover:shadow-lg transform hover:-translate-y-0.5"
                    onclick="openRatingModal()">
                <i class="fas fa-star mr-2"></i>
                Write a Review
            </button>
        </div>
        
        <?php if (empty($filtered_ratings)): ?>
        <!-- No Reviews State -->
        <div class="text-center py-12">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-star text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Reviews Yet</h3>
            <p class="text-gray-600 text-sm mb-6">Be the first to share your experience at this hotel!</p>
            <button class="primary-bg text-white px-8 py-4 rounded-xl font-semibold text-sm transition-all duration-300 hover:shadow-lg transform hover:-translate-y-0.5"
                    onclick="openRatingModal()">
                <i class="fas fa-pen mr-2"></i>
                Write First Review
            </button>
        </div>
        <?php else: ?>
        
        <!-- Rating Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <!-- Overall Rating -->
            <div class="text-center md:col-span-3">
                <div class="text-5xl font-bold text-gray-800 mb-2"><?= $average_rating ?></div>
                <div class="flex justify-center mb-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= floor($average_rating)): ?>
                            <i class="fas fa-star text-yellow-400 text-xl mx-0.5"></i>
                        <?php elseif ($i - 0.5 <= $average_rating): ?>
                            <i class="fas fa-star-half-alt text-yellow-400 text-xl mx-0.5"></i>
                        <?php else: ?>
                            <i class="far fa-star text-yellow-400 text-xl mx-0.5"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div class="text-gray-600 text-sm">Based on <?= $displayed_reviews_count ?> reviews</div>
            </div>
        </div>
        
        <!-- Reviews List -->
        <div class="space-y-6" id="reviewsContainer">
            <?php 
            $displayed_reviews = array_slice($filtered_ratings, 0, 3); // Show first 3 reviews initially
            foreach ($displayed_reviews as $review): 
                $time_ago = getTimeAgo($review['created_at']);
            ?>
            <div class="review-item border-b border-gray-200 pb-6 last:border-b-0 last:pb-0">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="flex items-center mb-2">
                            <!-- Review Stars -->
                            <div class="flex mr-3">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $review['rating']): ?>
                                        <i class="fas fa-star text-yellow-400 text-sm mx-0.5"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-yellow-400 text-sm mx-0.5"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span class="text-lg font-semibold text-gray-800"><?= $review['rating'] ?></span>
                        </div>
                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($review['reviewer_name']) ?></h4>
                    </div>
                    <div class="text-gray-500 text-sm"><?= $time_ago ?></div>
                </div>
                
                <?php if (!empty($review['feedback'])): ?>
                <p class="text-gray-700 leading-relaxed mb-3"><?= htmlspecialchars($review['feedback']) ?></p>
                <?php endif; ?>
                
                <!-- Review Meta -->
                <div class="flex items-center text-gray-500 text-sm">
                    <div class="flex items-center mr-4">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        <span>Verified Stay</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        <span><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Load More Reviews Button -->
        <?php if (count($filtered_ratings) > 3): ?>
        <div class="text-center mt-8">
            <button class="bg-white border-2 border-gray-300 hover:border-blue-500 text-gray-700 hover:text-blue-600 px-8 py-4 rounded-xl font-semibold text-sm transition-all duration-300 transform hover:-translate-y-1 shadow-md hover:shadow-lg"
                    id="loadMoreReviews"
                    data-current-count="3"
                    data-total-count="<?= count($filtered_ratings) ?>">
                <i class="fas fa-chevron-down mr-2"></i>
                Load More Reviews (<?= count($filtered_ratings) - 3 ?> more)
            </button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Rating Modal -->
<div id="ratingModal" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <!-- Modal Header -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Write a Review</h3>
                <button class="text-gray-400 hover:text-gray-600 transition-colors"
                        onclick="closeRatingModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Rating Form -->
            <form id="ratingForm" method="POST">
                <input type="hidden" name="submit_rating" value="1">
                
                <!-- Star Rating -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Overall Rating</label>
                    <div class="flex justify-center space-x-2" id="starRating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" 
                                    class="text-3xl text-yellow-400 transition-colors star-btn"
                                    data-rating="<?= $i ?>"
                                    onclick="setRating(<?= $i ?>)">
                                <i class="fas fa-star"></i>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="selectedRating" value="5" required>
                    <div class="text-center text-sm text-red-500 mt-2 hidden" id="ratingError">
                        Please select a rating
                    </div>
                </div>
                
                <!-- Reviewer Name -->
                <div class="mb-4">
                    <label for="reviewer_name" class="block text-sm font-medium text-gray-700 mb-2">Your Name *</label>
                    <input type="text" 
                           id="reviewer_name" 
                           name="reviewer_name" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your name"
                           required>
                </div>
                
                <!-- Email -->
                <div class="mb-4">
                    <label for="reviewer_email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" 
                           id="reviewer_email" 
                           name="reviewer_email" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your email (optional)">
                </div>
                
                <!-- Phone -->
                <div class="mb-4">
                    <label for="reviewer_phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" 
                           id="reviewer_phone" 
                           name="reviewer_phone" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter 10-digit phone number"
                           pattern="[1-9][0-9]{9}"
                           maxlength="10"
                           oninput="validatePhoneNumber(this)">
                    <div class="text-xs text-gray-500 mt-1">10-digit number, cannot start with 0</div>
                    <div class="text-sm text-red-500 mt-1 hidden" id="phoneError">
                        Please enter a valid 10-digit phone number that doesn't start with 0
                    </div>
                </div>
                
                <!-- Feedback -->
                <div class="mb-6">
                    <label for="feedback" class="block text-sm font-medium text-gray-700 mb-2">Your Review *</label>
                    <textarea 
                        id="feedback" 
                        name="feedback" 
                        rows="5"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Share your experience at this hotel..."
                        required></textarea>
                </div>
                
                <!-- Submit Button -->
                <div class="flex space-x-3">
                    <button type="button" 
                            class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-4 rounded-xl font-semibold text-sm transition-all duration-300"
                            onclick="closeRatingModal()">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 primary-bg hover:opacity-90 text-white py-4 rounded-xl font-semibold text-sm transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Rating Modal Functions
function openRatingModal() {
    const modal = document.getElementById('ratingModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    // Pre-select 5 stars by default
    setRating(5);
}

function closeRatingModal() {
    const modal = document.getElementById('ratingModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function setRating(rating) {
    const stars = document.querySelectorAll('.star-btn');
    const selectedRatingInput = document.getElementById('selectedRating');
    const ratingError = document.getElementById('ratingError');
    
    // Update stars visually
    stars.forEach((star, index) => {
        const starIcon = star.querySelector('i');
        if (index < rating) {
            starIcon.classList.remove('far', 'text-gray-300');
            starIcon.classList.add('fas', 'text-yellow-400');
        } else {
            starIcon.classList.remove('fas', 'text-yellow-400');
            starIcon.classList.add('far', 'text-gray-300');
        }
    });
    
    // Set the rating value
    selectedRatingInput.value = rating;
    
    // Hide error if rating is selected
    if (rating > 0) {
        ratingError.classList.add('hidden');
    }
}

// Phone number validation
function validatePhoneNumber(input) {
    const phoneError = document.getElementById('phoneError');
    const phoneValue = input.value.trim();
    
    // Remove any non-digit characters
    input.value = phoneValue.replace(/\D/g, '');
    
    // Validate phone number
    if (input.value.length > 0) {
        const phoneRegex = /^[1-9][0-9]{9}$/;
        if (!phoneRegex.test(input.value)) {
            phoneError.classList.remove('hidden');
            return false;
        } else {
            phoneError.classList.add('hidden');
            return true;
        }
    } else {
        phoneError.classList.add('hidden');
        return true;
    }
}

function resetRatingForm() {
    // Reset stars to 5 by default
    setRating(5);
    
    // Reset form fields
    document.getElementById('ratingForm').reset();
    document.getElementById('ratingError').classList.add('hidden');
    document.getElementById('phoneError').classList.add('hidden');
}

// Form Validation
document.getElementById('ratingForm').addEventListener('submit', function(e) {
    const rating = document.getElementById('selectedRating').value;
    const ratingError = document.getElementById('ratingError');
    const phoneInput = document.getElementById('reviewer_phone');
    const phoneError = document.getElementById('phoneError');
    
    let isValid = true;
    
    // Validate rating
    if (rating === '0' || rating === '') {
        ratingError.classList.remove('hidden');
        isValid = false;
    } else {
        ratingError.classList.add('hidden');
    }
    
    // Validate phone number if provided
    if (phoneInput.value.trim() !== '') {
        if (!validatePhoneNumber(phoneInput)) {
            phoneError.classList.remove('hidden');
            isValid = false;
        } else {
            phoneError.classList.add('hidden');
        }
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = document.querySelector('.text-red-500:not(.hidden)');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// Load More Reviews Functionality
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('loadMoreReviews');
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const currentCount = parseInt(this.dataset.currentCount);
            const totalCount = parseInt(this.dataset.totalCount);
            const reviewsToLoad = 3; // Load 3 more reviews at a time
            
            // Simulate loading more reviews
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            this.disabled = true;
            
            setTimeout(() => {
                // In a real application, you would fetch more reviews from the server
                // For now, we'll just show a message
                if (currentCount + reviewsToLoad >= totalCount) {
                    this.style.display = 'none';
                    showToast('All reviews loaded!', 'success');
                } else {
                    this.dataset.currentCount = currentCount + reviewsToLoad;
                    const remaining = totalCount - (currentCount + reviewsToLoad);
                    this.innerHTML = `<i class="fas fa-chevron-down mr-2"></i>Load More Reviews (${remaining} more)`;
                    this.disabled = false;
                    showToast(`Loaded ${reviewsToLoad} more reviews`, 'info');
                }
            }, 1000);
        });
    }
    
    // Add animation to review items
    const reviewItems = document.querySelectorAll('.review-item');
    reviewItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        
        setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 200);
    });
});

// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-lg text-white font-semibold transform translate-x-full transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle mr-3"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Close modal when clicking outside
document.getElementById('ratingModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRatingModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRatingModal();
    }
});

// Initialize with 5 stars pre-selected
document.addEventListener('DOMContentLoaded', function() {
    // Pre-select 5 stars in the modal when page loads
    const stars = document.querySelectorAll('.star-btn');
    stars.forEach((star, index) => {
        const starIcon = star.querySelector('i');
        if (index < 5) {
            starIcon.classList.remove('far', 'text-gray-300');
            starIcon.classList.add('fas', 'text-yellow-400');
        }
    });
});
</script>

<style>
.star-btn {
    transition: all 0.2s ease-in-out;
}

.star-btn:hover {
    transform: scale(1.2);
}

.review-item {
    transition: all 0.3s ease-in-out;
}

.review-item:hover {
    background-color: #f9fafb;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 0 -1rem;
}

#ratingModal {
    backdrop-filter: blur(4px);
}

/* Smooth scrolling for modal */
#ratingModal > div {
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

/* Loading animation for reviews */
.review-loading {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>