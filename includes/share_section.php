<div class="share-section">
    <h6>Share Profile</h6>
    <div class="share-buttons">

        <?php 
        $current_url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Get business website URL for the share message
        $business_website = "";
        if ($business_info && !empty($business_info['website'])) {
            $business_website = $business_info['website'];
            // Clean up the URL - ensure proper format
            $business_website = trim($business_website);
            if (!preg_match("/^https?:\/\//i", $business_website)) {
                $business_website = "https://" . ltrim($business_website, '/');
            }
        } else {
            // If no business website, use the current profile URL
            $business_website = $current_url;
        }
        
        // Get business name for personalized message
        $business_name = "";
        if ($business_info && !empty($business_info['business_name'])) {
            $business_name = $business_info['business_name'];
        }
        
        // Enhanced share text based on business type
        if (!empty($business_name)) {
            // If we have business name, create personalized message
            $share_text = "🎉 *Great News!* {$business_name} is now online with Direct Ordering! 🎉

Now you can order straight from {$business_name} — *No middlemen, No extra charges.* 💰

Enjoy better prices, faster service & exclusive offers only on our website!

*Save Money – Order Direct from {$business_name}!*

*Order Now:* 👇";
        } else {
            // Generic message if no business name
            $share_text = "🎉 *Great News!* We've launched our Direct Ordering Website! 🎉

Now you can order straight from us — *No middlemen, No extra charges.* 💰

Enjoy better prices, faster service & exclusive offers only on our website!

*Save Money – Order Direct from Us!*

*Order Now:* 👇";
        }
        
        // Full message with URL
        $full_share_text = $share_text . "\n" . $business_website;
        ?>
        
        <!-- WhatsApp -->
        <a href="https://wa.me/?text=<?= urlencode($full_share_text) ?>" 
           target="_blank" class="share-btn whatsapp"
           title="Share on WhatsApp">
           <i class="bi bi-whatsapp"></i>
        </a>
        
        <!-- Facebook -->
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($business_website) ?>&quote=<?= urlencode($share_text) ?>" 
           target="_blank" class="share-btn facebook"
           title="Share on Facebook">
           <i class="bi bi-facebook"></i>
        </a>
        
        <!-- Twitter -->
        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($share_text) ?>&url=<?= urlencode($business_website) ?>" 
           target="_blank" class="share-btn twitter"
           title="Share on Twitter">
           <i class="bi bi-twitter"></i>
        </a>
        
        <!-- LinkedIn -->
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode($business_website) ?>&title=<?= urlencode('Direct Ordering Website Launched') ?>&summary=<?= urlencode($share_text) ?>" 
           target="_blank" class="share-btn linkedin"
           title="Share on LinkedIn">
           <i class="bi bi-linkedin"></i>
        </a>
        
        <!-- Telegram -->
        <a href="https://t.me/share/url?url=<?= urlencode($business_website) ?>&text=<?= urlencode($share_text) ?>" 
           target="_blank" class="share-btn telegram"
           title="Share on Telegram">
           <i class="bi bi-telegram"></i>
        </a>
        
        <!-- Email -->
        <a href="mailto:?subject=<?= rawurlencode(html_entity_decode('🚀 Direct Ordering Website Launched!' . (!empty($business_name) ? ' - ' . $business_name : ''))) ?>&body=<?= rawurlencode($full_share_text) ?>" 
           class="share-btn email"
           title="Share via Email">
           <i class="bi bi-envelope"></i>
        </a>
        
        <!-- Copy Link -->
        <button class="share-btn copy-link" style="background: #333;"
                onclick="copyToClipboard('<?= htmlspecialchars($business_website, ENT_QUOTES) ?>')"
                title="Copy Profile Link">
            <i class="bi bi-link-45deg"></i>
        </button>
    </div>
</div>

<script>
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        showShareNotification('✅ Profile link copied to clipboard!');
    }).catch(function(err) {
        // Fallback for older browsers
        var textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showShareNotification('✅ Profile link copied to clipboard!');
        } catch (err) {
            showShareNotification('❌ Failed to copy link', 'error');
        }
        document.body.removeChild(textArea);
    });
}

// Show notification
function showShareNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotification = document.querySelector('.share-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `share-notification alert alert-${type}`;
    notification.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 250px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <span>${message}</span>
            <button type="button" class="btn-close btn-close-white ms-2" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 3000);
}

// WhatsApp button opens in new tab without app redirect issues
document.addEventListener('DOMContentLoaded', function() {
    const whatsappBtn = document.querySelector('.share-btn.whatsapp');
    if (whatsappBtn) {
        whatsappBtn.addEventListener('click', function(e) {
            // Prevent default behavior for WhatsApp to ensure it opens in new tab properly
            e.preventDefault();
            const whatsappUrl = this.getAttribute('href');
            window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
        });
    }
});
</script>