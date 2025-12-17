<style>
/* Hide download button in PWA mode on Android */
@media (display-mode: standalone) {
    .download_btn {
        display: none !important;
    }
}

/* Ensure it's visible in browser mode */
@media (display-mode: browser) {
    .download_btn {
        display: block !important;
    }
}
</style>
<script>
// Enhanced PWA detection for Android
function isAndroidPWA() {
    const ua = navigator.userAgent.toLowerCase();
    const isAndroid = ua.indexOf("android") > -1;
    
    // Multiple ways to detect PWA
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
    const isFullscreen = window.matchMedia('(display-mode: fullscreen)').matches;
    const isMinimalUI = window.matchMedia('(display-mode: minimal-ui)').matches;
    
    // Check various PWA indicators
    const isPWA = isStandalone || isFullscreen || isMinimalUI || 
                  window.navigator.standalone === true ||
                  window.location.search.includes('source=pwa') ||
                  document.referrer.includes('android-app://');
    
    return isAndroid && isPWA;
}

// Detect on page load
window.addEventListener('load', function() {
    const downloadBtn = document.getElementById('downloadApkBtn');
    const styleElement = document.getElementById('pwaDetectionStyle');
    
    if (isAndroidPWA() && downloadBtn) {
        // Hide the button
        downloadBtn.style.display = 'none';
        
        // Also add a CSS class for additional styling if needed
        downloadBtn.classList.add('pwa-hidden');
        
        // Optional: Log for debugging
        console.log('Android PWA detected - hiding download button');
    } else if (downloadBtn) {
        // Ensure button is visible for web browsers
        downloadBtn.style.display = 'block';
        console.log('Web browser detected - showing download button');
    }
    
    // Add CSS rule for PWA mode (optional)
    if (styleElement && isAndroidPWA()) {
        styleElement.textContent = `
            @media (display-mode: standalone) {
                #downloadApkBtn {
                    display: none !important;
                }
            }
        `;
    }
});

// Also check when display mode changes
if (window.matchMedia) {
    const displayModeQuery = window.matchMedia('(display-mode: standalone)');
    displayModeQuery.addListener(function(e) {
        const downloadBtn = document.getElementById('downloadApkBtn');
        if (downloadBtn && /Android/.test(navigator.userAgent)) {
            downloadBtn.style.display = e.matches ? 'none' : 'block';
        }
    });
}
</script>


<?php 
// Add APK Download Section after business info
if ($apk_data && file_exists($apk_data['file_path'])) {
    $base_domain = 'https://deegeecard.com';
    $file_path = ltrim($apk_data['file_path'], '/');
    $full_apk_url = $base_domain . '/' . $file_path;
    
    echo '<style id="pwaDetectionStyle"></style>';
    
    echo '<a href="' . htmlspecialchars($full_apk_url) . '" download class="btn btn-success btn-lg download_btn" id="downloadApkBtn" style="display:none;">';
    echo '<i class="bi bi-download me-2"></i> Download Our Android App';
    echo '</a>';
}
?>