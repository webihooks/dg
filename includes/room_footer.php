<?php
// room_footer.php - Footer for Rooms
?>

<!-- Footer -->
<footer class="mt-8 bg-gray-900 text-white">
    <div class="container mx-auto px-4 py-8">
        <!-- Main Footer Content -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <!-- Hotel Info -->
            <div class="col-span-1 md:col-span-2">
                <div class="flex items-center mb-4">
                    <?php if (!empty($photos['profile_photo'])): ?>
                    <img src="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>" 
                         class="w-12 h-12 rounded-full object-cover mr-4 border-2 border-white/20"
                         alt="<?= htmlspecialchars($business_info['business_name'] ?? $user['name'] ?? 'Hotel') ?>">
                    <?php else: ?>
                    <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center mr-4 border-2 border-white/20">
                        <i class="fas fa-hotel text-white/60"></i>
                    </div>
                    <?php endif; ?>
                    <div>
                        <h3 class="text-xl font-bold"><?= htmlspecialchars($business_info['business_name'] ?? $user['name'] ?? 'Hotel') ?></h3>
                        <p class="text-gray-400 text-sm">Luxury Hotel Experience</p>
                    </div>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed mb-4">
                    <?= htmlspecialchars($business_info['business_description'] ?? 'Experience luxury and comfort at our premium hotel. Book your stay now for an unforgettable experience.') ?>
                </p>
                <div class="flex items-center text-gray-400 text-sm">
                    <i class="fas fa-star text-yellow-400 mr-2"></i>
                    <span>Rated <?= getAverageRating($ratings) ?> by <?= count($ratings) ?> guests</span>
                </div>
            </div>

            <!-- Contact Info -->
            <div>
                <h4 class="text-lg font-semibold mb-4 text-white">Contact Info</h4>
                <div class="space-y-3">
                    <?php if (!empty($business_info['business_address'])): ?>
                    <div class="flex items-start text-gray-400 text-sm">
                        <i class="fas fa-map-marker-alt mt-1 mr-3 text-blue-400"></i>
                        <span><?= htmlspecialchars($business_info['business_address']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($user['phone'])): ?>
                    <div class="flex items-center text-gray-400 text-sm">
                        <i class="fas fa-phone mr-3 text-green-400"></i>
                        <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="hover:text-white transition-colors">
                            <?= htmlspecialchars($user['phone']) ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($business_info['website'])): ?>
                    <div class="flex items-center text-gray-400 text-sm">
                        <i class="fas fa-globe mr-3 text-purple-400"></i>
                        <a href="<?= htmlspecialchars($business_info['website']) ?>" target="_blank" class="hover:text-white transition-colors">
                            <?= htmlspecialchars($business_info['website']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Social Media Links -->
        <?php if (!empty($social_link) && array_filter($social_link)): ?>
        <div class="border-t border-gray-800 pt-6 mb-6">
            <h4 class="text-lg font-semibold mb-4 text-white text-center">Follow Us</h4>
            <div class="flex justify-center space-x-4">
                <?php if (!empty($social_link['Facebook'])): ?>
                <a href="<?= htmlspecialchars($social_link['Facebook']) ?>" 
                   target="_blank" 
                   class="w-10 h-10 bg-gray-800 hover:bg-blue-600 text-white rounded-full flex items-center justify-center transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_link['Instagram'])): ?>
                <a href="<?= htmlspecialchars($social_link['Instagram']) ?>" 
                   target="_blank" 
                   class="w-10 h-10 bg-gray-800 hover:bg-pink-600 text-white rounded-full flex items-center justify-center transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                    <i class="fab fa-instagram"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_link['WhatsApp'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $social_link['WhatsApp'])) ?>" 
                   target="_blank" 
                   class="w-10 h-10 bg-gray-800 hover:bg-green-600 text-white rounded-full flex items-center justify-center transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_link['YouTube'])): ?>
                <a href="<?= htmlspecialchars($social_link['YouTube']) ?>" 
                   target="_blank" 
                   class="w-10 h-10 bg-gray-800 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                    <i class="fab fa-youtube"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_link['LinkedIn'])): ?>
                <a href="<?= htmlspecialchars($social_link['LinkedIn']) ?>" 
                   target="_blank" 
                   class="w-10 h-10 bg-gray-800 hover:bg-blue-700 text-white rounded-full flex items-center justify-center transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                    <i class="fab fa-linkedin-in"></i>
                </a>
                <?php endif; ?>

                <?php if (!empty($social_link['Telegram'])): ?>
                <a href="<?= htmlspecialchars($social_link['Telegram']) ?>" 
                   target="_blank" 
                   class="w-10 h-10 bg-gray-800 hover:bg-blue-500 text-white rounded-full flex items-center justify-center transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                    <i class="fab fa-telegram"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bottom Bar -->
        <div class="border-t border-gray-800 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <!-- Copyright -->
                <div class="text-gray-400 text-sm">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($business_info['business_name'] ?? $user['name'] ?? 'Hotel') ?>. All rights reserved.
                </div>

                <!-- Powered By -->
                <div class="text-gray-400 text-sm">
                    Powered by <a href="https://deegeecard.com" target="_blank" class="text-blue-400 hover:text-blue-300 transition-colors">DEEGEECARD</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="backToTop" 
        class="fixed bottom-6 right-6 w-12 h-12 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-300 transform translate-y-16 opacity-0 z-40"
        onclick="scrollToTop()">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Loading Spinner -->
<div id="loadingSpinner" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-6 flex flex-col items-center">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mb-4"></div>
        <p class="text-gray-700 font-medium">Loading...</p>
    </div>
</div>

<script>
// Back to top functionality
window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    if (window.scrollY > 300) {
        backToTop.classList.remove('translate-y-16', 'opacity-0');
        backToTop.classList.add('translate-y-0', 'opacity-100');
    } else {
        backToTop.classList.remove('translate-y-0', 'opacity-100');
        backToTop.classList.add('translate-y-16', 'opacity-0');
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Loading spinner functions
function showLoading() {
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('loadingSpinner').classList.add('flex');
}

function hideLoading() {
    document.getElementById('loadingSpinner').classList.remove('flex');
    document.getElementById('loadingSpinner').classList.add('hidden');
}

// Smooth scrolling for anchor links
document.addEventListener('DOMContentLoaded', function() {
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add animation to footer elements
    const footerElements = document.querySelectorAll('footer > div > div > *');
    footerElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 200);
    });
});

// Print functionality
function printPage() {
    window.print();
}

// Add to home screen prompt (for mobile)
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();
    // Stash the event so it can be triggered later
    deferredPrompt = e;
    // Show your custom install prompt
    showInstallPrompt();
});

function showInstallPrompt() {
    // You can show a custom install button here
    console.log('App can be installed');
}

function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            deferredPrompt = null;
        });
    }
}

// Online/Offline detection
window.addEventListener('online', function() {
    showToast('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showToast('You are offline', 'error');
});

// Utility function for toast notifications
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-lg text-white font-semibold transform transition-transform duration-300 ${
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
    
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}
</script>

<style>
/* Print styles */
@media print {
    footer {
        display: none !important;
    }
    
    #backToTop {
        display: none !important;
    }
}

/* Smooth transitions for footer */
footer a {
    transition: all 0.3s ease;
}

/* Back to top button animation */
#backToTop {
    transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Loading spinner animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Hover effects for social icons */
footer .bg-gray-800:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
}
</style>
</body>
</html>