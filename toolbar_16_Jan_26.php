<?php
//  toolbar.php - Enhanced with safe session management for 365-day persistence

// First, check if we need to start session management
if (session_status() === PHP_SESSION_NONE) {
    // No session active - use our session manager
    require_once 'android_session_manager.php';
    $sessionManager = new AndroidSessionManager();
} else {
    // Session already active - create manager without starting new session
    require_once 'android_session_manager.php';
    $sessionManager = new AndroidSessionManager();
    
    // Just validate the existing session
    $sessionManager->validateAndroidSession();
}

// Update session activity and extend cookie if user is logged in
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    $_SESSION['session_expires'] = time() + 31536000;
    
    // Only update cookie if we have write access to headers AND session wasn't already active
    if (!headers_sent() && method_exists($sessionManager, 'wasSessionStartedByManager') && $sessionManager->wasSessionStartedByManager()) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + 31536000,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'None'
        ]);
    }
}

// Database connection for user data
$host = 'localhost';
$dbname = 'doctorie_webihooks_card';
$username = 'doctorie_webihooks';
$password = 'S@g@r4834';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Silent fail - don't break the page if DB connection fails
    error_log("Toolbar DB Connection Error: " . $e->getMessage());
    $conn = null;
}

// Get user data if logged in
$user_name = "Guest";
$user_id = "N/A";
$user_role = "guest";
$profile_url = "";
$user_phone = "";

if (isset($_SESSION['user_id']) && $conn) {
    try {
        $stmt = $conn->prepare("SELECT name, id, role, phone FROM users WHERE id = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user_name = htmlspecialchars($user['name'] ?? 'User');
            $user_id = $user['id'];
            $user_role = $user['role'] ?? 'user';
            $user_phone = $user['phone'] ?? '';
            
            // Get website URL from business_info table for regular users
            if ($user_role === 'user') {
                // First, try to get website from business_info table
                $business_stmt = $conn->prepare("SELECT website FROM business_info WHERE user_id = :id");
                $business_stmt->bindParam(':id', $_SESSION['user_id']);
                $business_stmt->execute();
                $business_info = $business_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($business_info && !empty($business_info['website'])) {
                    // Use website from business_info table
                    $profile_url = $business_info['website'];
                    
                    // Clean up the URL - ensure proper format
                    $profile_url = trim($profile_url);
                    
                    // If it's just a slug or doesn't start with http, prepend https://
                    if (!preg_match("/^https?:\/\//i", $profile_url)) {
                        $profile_url = "https://" . ltrim($profile_url, '/');
                    }
                    
                    // Ensure it's a valid URL
                    $profile_url = filter_var($profile_url, FILTER_VALIDATE_URL) ? $profile_url : "";
                } else {
                    // If no website in business_info table, fall back to profile_url_details
                    $profile_stmt = $conn->prepare("SELECT profile_url FROM profile_url_details WHERE user_id = :id");
                    $profile_stmt->bindParam(':id', $_SESSION['user_id']);
                    $profile_stmt->execute();
                    $profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($profile && !empty($profile['profile_url'])) {
                        $profile_url = $profile['profile_url'];
                        
                        // Clean up the URL - ensure proper format
                        $profile_url = trim($profile_url);
                        
                        // If it's just a slug, prepend the domain
                        if (!preg_match("/^https?:\/\//i", $profile_url)) {
                            $profile_url = "https://deegeecard.com/" . ltrim($profile_url, '/');
                        }
                        
                        // Ensure it's the correct domain
                        $profile_url = preg_replace('/^(https?:\/\/)?(www\.)?deegeecard\.com\//i', 'https://deegeecard.com/', $profile_url);
                    } else {
                        // If no profile URL in database either, use the main site
                        $profile_url = "https://deegeecard.com";
                    }
                }
            } else {
                // For non-users (admin, sales_person), use main site
                $profile_url = "https://deegeecard.com";
            }
        }
    } catch (PDOException $e) {
        error_log("Toolbar User Query Error: " . $e->getMessage());
    }
}

// Check if we're in Android app context
$isAndroidApp = $sessionManager->isAndroidApp();

// Set Android-specific session data
if ($isAndroidApp && isset($_SESSION['user_id'])) {
    $_SESSION['android_last_activity'] = time();
    $_SESSION['toolbar_accessed'] = true;
}
?>

<!-- ========================================= -->
<!-- Enhanced Session Management - 365 Days -->
<!-- ========================================= -->
<script>
// Android Session Recovery System
class AndroidSessionRecovery {
    constructor() {
        this.isAndroidApp = <?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false) ? 'true' : 'false'; ?>;
        this.recoveryInterval = null;
        this.forceRecoveryInterval = null;
        this.init();
    }

    init() {
        if (!this.isAndroidApp) return;
        
        console.log('🔧 Starting Android Session Recovery System');
        
        this.startRecoveryMonitoring();
        this.startForceRecovery();
        this.setupRecoveryEventListeners();
        this.attemptImmediateRecovery();
    }

    startRecoveryMonitoring() {
        // Check session every 20 seconds
        this.recoveryInterval = setInterval(() => {
            this.checkAndRecoverSession();
        }, 20000);
    }

    startForceRecovery() {
        // Force recovery every 2 minutes as backup
        this.forceRecoveryInterval = setInterval(() => {
            this.forceSessionRecovery();
        }, 120000);
    }

    async checkAndRecoverSession() {
        try {
            const response = await fetch('session-keepalive.php?android_check=true&t=' + Date.now(), {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.status !== 'success') {
                console.warn('⚠️ Session check failed, attempting recovery');
                await this.forceSessionRecovery();
            } else {
                console.log('✅ Session check passed');
            }
        } catch (error) {
            console.error('❌ Session check error, forcing recovery:', error);
            await this.forceSessionRecovery();
        }
    }

    async forceSessionRecovery() {
        console.log('🔄 Forcing Android session recovery...');
        
        try {
            const response = await fetch('android_session_recovery.php?force=true&t=' + Date.now(), {
                credentials: 'include',
                headers: {
                    'X-Android-Force-Recovery': 'true'
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.recovered) {
                console.log('✅ Android session recovery successful');
                
                // Force WebToNative cookie update
                this.forceCookieUpdate();
                
                // Update recovery stats
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastAndroidRecovery', Date.now());
                    const recoveryCount = parseInt(localStorage.getItem('androidRecoveryCount') || '0') + 1;
                    localStorage.setItem('androidRecoveryCount', recoveryCount);
                }
            } else {
                throw new Error('Recovery failed: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('❌ Android session recovery failed:', error);
            this.showRecoveryAlert();
        }
    }

    setupRecoveryEventListeners() {
        // Recover on any visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                setTimeout(() => {
                    this.forceSessionRecovery();
                    this.forceCookieUpdate();
                }, 500);
            }
        });

        // Recover on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.forceSessionRecovery();
            }, 2000);
        });

        // Recover on any user interaction
        const recoveryEvents = ['click', 'touchstart', 'keydown', 'scroll'];
        recoveryEvents.forEach(event => {
            document.addEventListener(event, () => {
                this.forceCookieUpdate();
            }, { passive: true });
        });
    }

    attemptImmediateRecovery() {
        // Immediate recovery attempts
        setTimeout(() => {
            this.forceSessionRecovery();
        }, 1000);
        
        setTimeout(() => {
            this.forceSessionRecovery();
        }, 5000);
        
        setTimeout(() => {
            this.forceSessionRecovery();
        }, 10000);
    }

    forceCookieUpdate() {
        if (typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            try {
                WTN.forceUpdateCookies();
                console.log('✅ WebToNative Cookies Updated - ' + new Date().toLocaleTimeString());
            } catch (error) {
                console.error('❌ WebToNative Cookie Update Failed:', error);
            }
        }
    }

    showRecoveryAlert() {
        const alertDiv = document.createElement('div');
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 15px;
            border-radius: 8px;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
        `;
        alertDiv.innerHTML = `
            <strong>🚨 Session Recovery Needed</strong>
            <p style="margin: 8px 0; font-size: 14px;">Please refresh the app to restore your session</p>
            <button onclick="location.reload()" style="
                background: white; 
                color: #dc3545; 
                border: none; 
                padding: 8px 15px; 
                border-radius: 4px; 
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
            ">Refresh Now</button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 10000);
    }

    destroy() {
        if (this.recoveryInterval) {
            clearInterval(this.recoveryInterval);
        }
        if (this.forceRecoveryInterval) {
            clearInterval(this.forceRecoveryInterval);
        }
    }
}

// Initialize Android Session Recovery
document.addEventListener('DOMContentLoaded', function() {
    if (<?php echo (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WebToNative') !== false) ? 'true' : 'false'; ?>) {
        window.androidSessionRecovery = new AndroidSessionRecovery();
    }
});

// ==============================================
// WhatsApp Share Profile Functionality - START
// ==============================================

class WhatsAppShareManager {
    constructor() {
        this.userRole = '<?php echo $user_role; ?>';
        this.profileUrl = '<?php echo $profile_url; ?>';
        this.baseUrl = 'https://deegeecard.com/';
        this.userId = '<?php echo $user_id; ?>';
        this.userName = '<?php echo addslashes($user_name); ?>';
        this.userPhone = '<?php echo $user_phone; ?>';
        this.init();
    }

    init() {
        console.log('📱 WhatsApp Share Manager Initialized');
        console.log('👤 User Role:', this.userRole);
        console.log('👤 User ID:', this.userId);
        console.log('👤 User Name:', this.userName);
        console.log('📱 User Phone:', this.userPhone);
        console.log('🔗 Profile URL:', this.profileUrl);
        
        this.setupModalEvents();
        this.setupFormValidation();
    }

    setupModalEvents() {
        const whatsappBtn = document.getElementById('whatsapp-share-btn');
        const whatsappModal = new bootstrap.Modal(document.getElementById('whatsappShareModal'));
        const closeBtn = document.getElementById('closeWhatsappModal');
        const shareForm = document.getElementById('whatsappShareForm');
        const languageRadios = document.querySelectorAll('input[name="message_language"]');
        
        // Open modal on button click
        if (whatsappBtn) {
            whatsappBtn.addEventListener('click', () => {
                // Reset form
                shareForm.reset();
                document.getElementById('customerPhone').value = '';
                
                // Hide language selection for regular users
                const languageSection = document.getElementById('languageSection');
                if (languageSection) {
                    if (this.userRole === 'user') {
                        languageSection.style.display = 'none';
                        document.querySelector('input[name="message_language"][value="english"]').checked = true;
                    } else {
                        languageSection.style.display = 'block';
                    }
                }
                
                whatsappModal.show();
            });
        }
        
        // Close modal
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                whatsappModal.hide();
            });
        }
        
        // Language change handler
        languageRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.updateMessagePreview(e.target.value);
            });
        });
        
        // Form submit handler
        if (shareForm) {
            shareForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendWhatsAppMessage();
            });
        }
    }

    setupFormValidation() {
        const phoneInput = document.getElementById('customerPhone');
        
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                e.target.value = value;
                
                // Update validation message
                const phoneError = document.getElementById('phoneError');
                if (phoneError) {
                    if (value.length === 10) {
                        phoneError.textContent = '';
                        phoneError.style.display = 'none';
                    } else if (value.length > 0) {
                        phoneError.textContent = 'Phone number must be 10 digits';
                        phoneError.style.display = 'block';
                    } else {
                        phoneError.textContent = '';
                        phoneError.style.display = 'none';
                    }
                }
            });
        }
    }

    updateMessagePreview(language) {
        const previewElement = document.getElementById('messagePreview');
        if (!previewElement) return;
        
        const customerName = document.getElementById('customerName').value || '';
        
        if (this.userRole === 'user') {
            // User message
            const message = this.generateUserMessage(customerName);
            previewElement.textContent = message.substring(0, 200) + '...';
        } else {
            // Sales/Admin message
            const message = this.generateSalesMessage(customerName, language);
            previewElement.textContent = message.substring(0, 200) + '...';
        }
    }

    generateUserMessage(customerName) {
        const fullUrl = this.profileUrl;
        const greeting = customerName ? `*Hello ${customerName}*,` : "*Hello*";
        return `${greeting}\n\n🎉 *Great News!* We've launched our Direct Ordering Website! 🎉\n\nNow you can order straight from us — *No middlemen, No extra charges.* 💰\nEnjoy better prices, faster service & exclusive offers only on our website!\n\n*Save Money – Order Direct from Us!*\n\n*Order Now:* 👇\n${fullUrl}`;
    }

    generateSalesMessage(customerName, language = 'english') {
        // Base contact info - fixed contacts (Inayat and Sagar)
        const baseContactInfo = "Inayat Shaikh – 9819411026\nSagar Pawar – 9004998995";
        
        // Determine contact info based on user role
        let contactInfo = baseContactInfo;
        
        if (this.userRole === 'sales_person') {
            // For sales person: add their contact info before base contacts
            if (this.userPhone && this.userName) {
                contactInfo = `${this.userName} – ${this.userPhone}\n${baseContactInfo}`;
            }
        }
        // For admin: use only base contacts (no admin contact added)
        // For other roles: use only base contacts
        
        const greeting = customerName ? `*Hello ${customerName}*` : "*Hello*";
        
        if (language === 'hindi') {
            return `${greeting},\nक्या आप अपनी मेहनत की कमाई *S.w.i.g.g.y / Z.o.m.a.t.o* कमीशन में गंवा रहे हैं? 💸\n\nअब पेश है *DeeGeeCard* – आपका खुद का ब्रांडेड फूड ऑर्डरिंग सिस्टम, जिसमें है *ZERO कमीशन – हमेशा के लिए!*\n\n🚀 *सिर्फ 60 मिनट में लॉन्च करें – अपनी खुद की वेबसाइट + एंड्रॉइड ऐप + एडमिन ऐप!*\n\nयह सब आपको मिलेगा 👇\n\n✅ *आपकी खुद की वेबसाइट (पर्सनलाइज़्ड डोमेन):* बिल्कुल S.w.i.g.g.y / Z.o.m.a.t.o जैसी वेबसाइट, लेकिन आपके रेस्टोरेंट के नाम से – बिना किसी कमीशन के।\n\n✅ *एडमिन मैनेजमेंट ऐप for Desktop & Mobile:* मोबाइल से ही ऑर्डर स्वीकारें/रिजेक्ट करें, मेनू और दाम तुरंत अपडेट करें।\n\n✅ *1000 पर्सनलाइज़्ड स्कैन-टू-ऑर्डर QR कार्ड्स + 8 QR टेबल स्टैंडीज़!* कस्टमर अब तुरंत डिलीवरी के लिए या सीधे अपनी टेबल से ऑर्डर कर सकते हैं।\nहर कार्ड और स्टैंडी को अपना सेल्फ-ऑर्डरिंग स्टेशन बनाएं — रीऑर्डर बढ़ाएं, सर्विस स्पीड बढ़ाएं और सुविधा भी!\n\n✅ *KOT और बिल प्रिंटिंग:* सिर्फ एक क्लिक में किचन ऑर्डर टिकट और बिल निकालें। 🧾\n\n✅ *फुल स्टोर कंट्रोल:* स्टोर टाइमिंग, डिलीवरी चार्ज, GST, डिस्काउंट, कूपन कोड और कैटेगरी – सबकुछ आसानी से सेट करें। ⚙️\n\n✅ *बुल्क व्हाट्सएप मार्केटिंग पैनल:* 10,000 फ्री क्रेडिट्स के साथ ऑफर्स और अपडेट भेजें। 📢\n\n✅ *डायरेक्ट पेमेंट्स:* UPI या कार्ड से पेमेंट सीधे आपके अकाउंट में – बिना किसी प्लेटफ़ॉर्म चार्ज के।\n\n✅ *कस्टमर रिव्यू का जवाब व्हाट्सएप पर दें:* सिर्फ एक क्लिक में ग्राहकों को रिप्लाई करें। 💬\n\n💡 *फ्री इंटीग्रेशन:* Google, Instagram, Facebook, YouTube और Maps – ताकि ग्राहक आपको आसानी से ढूंढ सकें।\n\n🔥 *अब कमीशन देना बंद करें। अपनी 100% कमाई अपने पास रखें।*\nआज ही शुरू करें अपने रेस्टोरेंट की डिजिटल क्रांति!\n\n💰 *सिर्फ ₹9,999/साल (कोई छुपा चार्ज नहीं)*\n\n📞 *कॉल करें अभी:*\n${contactInfo}\n\n🌐 https://www.deegeecard.com\n📧 support@deegeecard.com\n\n🌟 *रेस्टोरेंट्स को सशक्त बनाना। कमीशन को खत्म करना।*`;
        } else {
            return `${greeting}\nTired of losing your profits to S.w.i.g.g.y / Z.o.m.a.t.o commissions? 💸\n\nIntroducing DeeGeeCard – Your own branded food ordering system with ZERO commission, forever!\n\n🚀 Launch your own Ordering Website + Android App + Admin App in just 60 mins!\n\nHere's what you get:\n\n✅ *Your Own Ordering Website (Personalized Domain):* Just like S.w.i.g.g.y / Z.o.m.a.t.o – but branded for your restaurant, with zero commissions.\n\n✅ *Admin Management App for Desktop & Mobile:* Accept/reject orders, update menu & prices instantly from your phone.\n\n✅ *1000 Personalized Scan-to-Order QR Cards + 8 QR Table Standees!:* Let customers order instantly for delivery or straight from their dining table. Turn every card and standee into your own self-ordering station — boosting reorders, speed, and convenience!\n\n✅ *KOT & Bill Printing:* Generate kitchen order tickets and bills in just one click. 🧾\n\n✅ *Full Store Control:* Set store timings, delivery charges, GST, discounts, coupon codes, and menu categories easily. ⚙️\n\n✅ *Bulk WhatsApp Marketing Panel:* Get 10,000 FREE credits to send offers & updates to your customers directly. 📢\n\n✅ *Direct Payments:* Receive UPI/card payments instantly in your account — 0% platform fee.\n\n✅ *Reply to Reviews Instantly:* Respond to customer reviews directly via WhatsApp in one click. 💬\n\n💡 *Free Integrations:* Google, Instagram, Facebook, YouTube & Maps – make your restaurant easily discoverable.\n\n🔥 *Stop paying commissions. Start keeping 100% of your profits.*\n*Your restaurant's digital revolution starts TODAY!*\n\nAll this for just ₹14,999/year (No Hidden Costs)\n\n📞 Call us NOW:\n${contactInfo}\n\n🌐 https://www.deegeecard.com\n\n📧 support@deegeecard.com\n\n🌟 Empowering Restaurants. Eliminating Commissions.`;
        }
    }

    async sendWhatsAppMessage() {
        const customerName = document.getElementById('customerName').value.trim();
        const customerPhone = document.getElementById('customerPhone').value.trim();
        const language = document.querySelector('input[name="message_language"]:checked')?.value || 'english';
        
        // Validation - Only phone is required now
        if (!customerPhone || customerPhone.length !== 10) {
            this.showAlert('Please enter a valid 10-digit phone number', 'danger');
            document.getElementById('customerPhone').focus();
            return;
        }
        
        // Save customer data to database (only if customer name is provided)
        if (customerName) {
            const saveResult = await this.saveCustomerData(customerName, customerPhone);
            
            if (!saveResult.success) {
                this.showAlert('Failed to save customer data: ' + saveResult.message, 'warning');
                // Continue anyway, but log the error
                console.error('Customer data save failed:', saveResult.message);
            }
        }
        
        // Generate message based on user role
        let message = '';
        if (this.userRole === 'user') {
            message = this.generateUserMessage(customerName);
        } else {
            message = this.generateSalesMessage(customerName, language);
        }
        
        // Encode message for URL
        const encodedMessage = encodeURIComponent(message);
        const whatsappUrl = `https://wa.me/91${customerPhone}?text=${encodedMessage}`;
        
        // Open WhatsApp in new tab
        window.open(whatsappUrl, '_blank');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('whatsappShareModal'));
        if (modal) {
            modal.hide();
        }
        
        // Show success message
        if (customerName) {
            this.showAlert('✅ Customer saved & WhatsApp opened!', 'success');
        } else {
            this.showAlert('✅ WhatsApp opened!', 'success');
        }
        
        // Log the action
        console.log('📤 WhatsApp message sent to:', customerPhone);
        console.log('👤 Customer Name:', customerName || 'Not provided');
        console.log('👤 User Role:', this.userRole);
        console.log('📝 Message Length:', message.length);
    }

    async saveCustomerData(customerName, customerPhone) {
        try {
            const formData = new FormData();
            formData.append('customer_name', customerName);
            formData.append('customer_phone', customerPhone);
            
            const response = await fetch('save_customer_data.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Important for session
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('❌ Error saving customer data:', error);
            return {
                success: false,
                message: 'Network error: ' + error.message
            };
        }
    }

    showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlert = document.querySelector('.whatsapp-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show whatsapp-alert`;
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            min-width: 300px;
        `;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }
}

// Initialize WhatsApp Share Manager when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.whatsappShareManager = new WhatsAppShareManager();
});

// ==============================================
// WhatsApp Share Profile Functionality - END
// ==============================================

// Enhanced Universal Session Management - 365 Days with WebToNative
class UniversalSessionManager {
    constructor() {
        this.keepAliveInterval = 300000; // 5 minutes
        this.isAndroidApp = <?php echo json_encode($isAndroidApp); ?>;
        this.isWebToNative = typeof WTN !== 'undefined';
        this.healthCheckInterval = 120000; // 2 minutes for health checks
        this.heartbeatInterval = this.isAndroidApp ? 300000 : 600000; // 5 min Android, 10 min Web
        this.androidMonitorInterval = 30000; // 30 seconds for Android
        this.init();
    }

    init() {
        console.log('🚀 Universal Session Manager Initialized');
        console.log('📱 Android App:', this.isAndroidApp);
        console.log('🔧 WebToNative:', this.isWebToNative);
        console.log('❤️ Heartbeat Interval:', this.heartbeatInterval / 1000 + 's');
        
        this.startKeepAlive();
        this.startHealthChecks();
        this.startHeartbeat();
        this.setupVisibilityHandler();
        this.setupActivityHandlers();
        this.initializeSession();
        
        // Only setup WebToNative features if in WebToNative environment
        if (this.isWebToNative) {
            this.setupWebToNativeFeatures();
        }
        
        // Aggressive monitoring for Android apps
        if (this.isAndroidApp) {
            this.startAndroidAggressiveMonitoring();
        }
    }

    setupWebToNativeFeatures() {
        if (this.isWebToNative && typeof WTN !== 'undefined') {
            console.log('🔧 Setting up WebToNative features');
            
            // Force cookie update immediately
            this.forceCookieUpdate();
            
            // Set up periodic cookie updates for WebToNative
            this.cookieUpdateInterval = setInterval(() => {
                this.forceCookieUpdate();
            }, 60000); // Every minute for WebToNative
            
            // Listen for WebToNative events
            this.setupWebToNativeEventListeners();
        }
    }

    startAndroidAggressiveMonitoring() {
        console.log('📱 Starting aggressive Android session monitoring');
        
        // Monitor every 30 seconds for Android
        this.androidMonitorInterval = setInterval(() => {
            this.androidSessionHealthCheck();
        }, this.androidMonitorInterval);
        
        // Immediate health check
        setTimeout(() => {
            this.androidSessionHealthCheck();
        }, 5000);
    }

    async androidSessionHealthCheck() {
        if (!this.isAndroidApp) return;
        
        try {
            const response = await fetch('session-keepalive.php?android_health_check=true&t=' + Date.now(), {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-Android-Health-Check': 'true',
                    'Cache-Control': 'no-cache'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Android Session Health Check Passed');
                
                // Force cookie update after health check
                this.forceCookieUpdate();
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastAndroidHealthCheck', Date.now());
                }
            } else {
                console.warn('⚠️ Android Session Health Check Failed');
                this.attemptAndroidSessionRecovery();
            }
        } catch (error) {
            console.error('❌ Android Health Check Request Failed:', error);
            this.attemptAndroidSessionRecovery();
        }
    }

    async attemptAndroidSessionRecovery() {
        if (!this.isAndroidApp) return;
        
        console.log('🔄 Attempting Android session recovery...');
        
        try {
            // Try multiple recovery methods
            const recoveryPromises = [
                fetch('session-keepalive.php?android_recovery=true&t=' + Date.now(), {
                    credentials: 'include'
                }),
                fetch('heartbeat.php?android_recovery=true&t=' + Date.now(), {
                    credentials: 'include'
                })
            ];
            
            const results = await Promise.allSettled(recoveryPromises);
            
            let recoverySuccessful = false;
            for (const result of results) {
                if (result.status === 'fulfilled' && result.value.ok) {
                    const data = await result.value.json();
                    if (data.success || data.status === 'success') {
                        recoverySuccessful = true;
                        break;
                    }
                }
            }
            
            if (recoverySuccessful) {
                console.log('✅ Android session recovery successful');
                this.forceCookieUpdate();
                this.updateSessionStatus('recovered');
            } else {
                throw new Error('All recovery methods failed');
            }
            
        } catch (error) {
            console.error('❌ Android session recovery failed:', error);
            this.showAndroidRecoveryAlert();
        }
    }

    showAndroidRecoveryAlert() {
        // Create Android-specific recovery notification
        const recoveryDiv = document.createElement('div');
        recoveryDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: Arial, sans-serif;
        `;
        recoveryDiv.innerHTML = `
            <strong>⚠️ Android Session Issue</strong>
            <p style="margin: 8px 0; font-size: 14px;">Your session needs refresh</p>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button onclick="location.reload()" style="
                    background: #856404; 
                    color: white; 
                    border: none; 
                    padding: 8px 15px; 
                    border-radius: 4px; 
                    cursor: pointer;
                    font-size: 14px;
                ">Refresh App</button>
                <button onclick="this.parentNode.parentNode.remove()" style="
                    background: none; 
                    border: 1px solid #856404; 
                    color: #856404; 
                    padding: 8px 15px; 
                    border-radius: 4px; 
                    cursor: pointer;
                    font-size: 14px;
                ">Dismiss</button>
            </div>
        `;
        document.body.appendChild(recoveryDiv);
        
        // Auto-remove after 15 seconds
        setTimeout(() => {
            if (recoveryDiv.parentNode) {
                recoveryDiv.parentNode.removeChild(recoveryDiv);
            }
        }, 15000);
    }

    forceCookieUpdate() {
        if (this.isWebToNative && typeof WTN !== 'undefined' && WTN.forceUpdateCookies) {
            console.log('🔧 WebToNative: Forcing cookie update');
            try {
                WTN.forceUpdateCookies();
                
                // Log successful cookie update
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastCookieUpdate', Date.now());
                    localStorage.setItem('webtonative_update_count', 
                        parseInt(localStorage.getItem('webtonative_update_count') || '0') + 1);
                }
                
                console.log('✅ WebToNative: Cookies updated successfully');
            } catch (error) {
                console.error('❌ WebToNative: Cookie update failed:', error);
            }
        }
    }

    setupWebToNativeEventListeners() {
        // Listen for app state changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isWebToNative) {
                // App came to foreground - force cookie update
                console.log('📱 WebToNative: App foreground - refreshing session');
                setTimeout(() => {
                    this.forceCookieUpdate();
                    this.keepSessionAlive();
                    this.androidSessionHealthCheck();
                }, 10000);
            }
        });

        // Listen for any user interaction to trigger cookie updates
        const interactiveEvents = ['touchstart', 'click', 'scroll', 'keydown'];
        interactiveEvents.forEach(event => {
            document.addEventListener(event, () => {
                if (this.isWebToNative) {
                    // Debounced cookie update on user interaction
                    clearTimeout(this.cookieUpdateTimeout);
                    this.cookieUpdateTimeout = setTimeout(() => {
                        this.forceCookieUpdate();
                    }, 20000);
                }
            }, { passive: true });
        });
    }

    initializeSession() {
        // Set session persistence in localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('sessionInitialized', Date.now());
            localStorage.setItem('userAgent', navigator.userAgent);
            localStorage.setItem('sessionStart', new Date().toISOString());
            localStorage.setItem('isWebToNative', this.isWebToNative.toString());
            localStorage.setItem('isAndroidApp', this.isAndroidApp.toString());
            localStorage.setItem('platform', this.isAndroidApp ? 'android' : 'web');
        }
    }

    startKeepAlive() {
        // Immediate keep-alive on load
        this.keepSessionAlive();
        
        // Periodic keep-alive
        this.keepAliveTimer = setInterval(() => {
            this.keepSessionAlive();
        }, this.keepAliveInterval);
    }

    startHealthChecks() {
        // Health check every 2 minutes
        this.healthCheckTimer = setInterval(() => {
            this.performHealthCheck();
        }, this.healthCheckInterval);
    }

    startHeartbeat() {
        // Heartbeat for session maintenance (more frequent for Android)
        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.heartbeatInterval);
    }

    async performHealthCheck() {
        try {
            const response = await fetch('session-keepalive.php?health_check=true&t=' + Date.now(), {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Session Health Check Passed');
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastHealthCheck', Date.now());
                    localStorage.setItem('sessionHealth', 'healthy');
                }
                
                this.updateSessionStatus('active');
                
                // Force cookie update for WebToNative after health check
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
            } else {
                console.warn('⚠️ Session Health Check Failed:', data.issues);
                this.updateSessionStatus('warning');
                
                // Try to recover session
                this.recoverSession();
            }
        } catch (error) {
            console.error('❌ Health Check Request Failed:', error);
            this.updateSessionStatus('error');
        }
    }

    async sendHeartbeat() {
        try {
            const response = await fetch('heartbeat.php?t=' + Date.now(), {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('❤️ Heartbeat maintained - Count:', data.heartbeat_count);
                
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastHeartbeat', Date.now());
                    localStorage.setItem('heartbeatCount', data.heartbeat_count);
                }
                
                // Force cookie update for WebToNative after heartbeat
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
            } else {
                console.warn('💔 Heartbeat failed:', data.error);
            }
        } catch (error) {
            console.error('💔 Heartbeat request failed:', error);
        }
    }

    async keepSessionAlive() {
        try {
            const response = await fetch('/session-keepalive.php?keep_alive=true&t=' + Date.now(), {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Toolbar-Request': 'true',
                    'X-WebToNative': this.isWebToNative ? 'true' : 'false'
                }
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                console.log('✅ Toolbar Session kept alive:', new Date().toLocaleTimeString());
                
                // Update session info in storage
                if (typeof(Storage) !== "undefined") {
                    localStorage.setItem('lastKeepAlive', Date.now());
                    localStorage.setItem('lastActivity', new Date().toISOString());
                }
                
                // Update session status indicator
                this.updateSessionStatus('active');
                
                // Force cookie update for WebToNative after keep-alive
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
            } else {
                console.warn('⚠️ Toolbar Session keep-alive failed');
                this.updateSessionStatus('warning');
            }
        } catch (error) {
            console.error('❌ Toolbar Keep-alive request failed:', error);
            this.updateSessionStatus('error');
        }
    }

    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page became visible - refresh session immediately
                console.log('🔄 Toolbar Page visible - refreshing session');
                this.keepSessionAlive();
                this.performHealthCheck();
                
                // Additional session validation
                this.validateSessionState();
                
                // Android-specific: Aggressive session check
                if (this.isAndroidApp) {
                    this.androidSessionHealthCheck();
                }
                
                // Force cookie update for WebToNative
                if (this.isWebToNative) {
                    this.forceCookieUpdate();
                }
            } else {
                // Page hidden - prepare for background
                this.prepareForBackground();
            }
        });
    }

    // In setupActivityHandlers(), add error suppression:
    setupActivityHandlers() {
        const activities = ['scroll', 'touchstart', 'click'];
        activities.forEach(activity => {
            document.addEventListener(activity, () => {
                // Use debouncing to prevent rapid consecutive calls
                clearTimeout(this.activityTimeout);
                this.activityTimeout = setTimeout(() => {
                    this.keepSessionAlive().catch(() => {
                        // Silent fail - don't log network errors to console
                    });
                }, 1000); // 1 second debounce
            }, { passive: true });
        });
    }

    prepareForBackground() {
        console.log('📱 Preparing for background/switch');
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem('lastBackgroundTime', Date.now());
            localStorage.setItem('wasInBackground', 'true');
            
            // Force cookie update before going to background
            if (this.isWebToNative) {
                this.forceCookieUpdate();
            }
        }
    }

    validateSessionState() {
        // Check if session is still valid
        if (typeof(Storage) !== "undefined") {
            const lastKeepAlive = localStorage.getItem('lastKeepAlive');
            if (lastKeepAlive && (Date.now() - parseInt(lastKeepAlive)) > 600000) { // 10 minutes
                console.log('🔄 Session state validation triggered');
                this.keepSessionAlive();
            }
        }
    }

    updateSessionStatus(status) {
        // Update visual indicator if exists
        const statusElement = document.getElementById('sessionStatusIndicator');
        if (statusElement) {
            const statusConfig = {
                'active': { 
                    text: this.isWebToNative ? '📱 Android - Session Active (365 Days)' : '🌐 Web - Session Active (365 Days)', 
                    color: '#28a745', 
                    icon: '✅' 
                },
                'warning': { 
                    text: 'Session Warning', 
                    color: '#ffc107', 
                    icon: '⚠️' 
                },
                'error': { 
                    text: 'Session Error', 
                    color: '#dc3545', 
                    icon: '❌' 
                },
                'recovered': { 
                    text: 'Session Restored', 
                    color: '#17a2b8', 
                    icon: '🔄' 
                }
            };
            
            const config = statusConfig[status] || statusConfig['active'];
            statusElement.innerHTML = `${config.icon} ${config.text}`;
            statusElement.style.backgroundColor = config.color;
        }
    }

    // Get session statistics for debugging
    getSessionStats() {
        if (typeof(Storage) === "undefined") return null;
        
        return {
            platform: localStorage.getItem('platform') || 'unknown',
            webtonative: localStorage.getItem('webtonative') || 'false',
            androidApp: localStorage.getItem('isAndroidApp') || 'false',
            sessionStart: localStorage.getItem('sessionStart'),
            lastHealthCheck: localStorage.getItem('lastHealthCheck'),
            lastAndroidHealthCheck: localStorage.getItem('lastAndroidHealthCheck'),
            lastHeartbeat: localStorage.getItem('lastHeartbeat'),
            lastKeepAlive: localStorage.getItem('lastKeepAlive'),
            lastCookieUpdate: localStorage.getItem('lastCookieUpdate'),
            heartbeatCount: localStorage.getItem('heartbeatCount'),
            cookieUpdateCount: localStorage.getItem('webtonative_update_count'),
            userAgent: localStorage.getItem('userAgent')
        };
    }

    // Cleanup method
    destroy() {
        if (this.keepAliveTimer) {
            clearInterval(this.keepAliveTimer);
        }
        if (this.healthCheckTimer) {
            clearInterval(this.healthCheckTimer);
        }
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        if (this.cookieUpdateInterval) {
            clearInterval(this.cookieUpdateInterval);
        }
        if (this.androidMonitorInterval) {
            clearInterval(this.androidMonitorInterval);
        }
        if (this.cookieUpdateTimeout) {
            clearTimeout(this.cookieUpdateTimeout);
        }
        if (this.activityCookieTimeout) {
            clearTimeout(this.activityCookieTimeout);
        }
        
        console.log('🧹 Toolbar Session Manager Cleaned Up');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main session manager
    window.toolbarSessionManager = new UniversalSessionManager();
    
    // Store session initialization
    console.log('🚀 Enhanced Toolbar Session Management Initialized');
    
    // Show session status on first load
    setTimeout(() => {
        const statusElement = document.getElementById('sessionStatusIndicator');
        if (statusElement) {
            statusElement.style.display = 'block';
            setTimeout(() => {
                statusElement.style.display = 'none';
            }, 5000);
        }
    }, 2000);
});

// Handle page unload for session preservation
window.addEventListener('beforeunload', function() {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem('sessionPreserved', 'true');
        localStorage.setItem('lastUnload', Date.now());
        localStorage.setItem('toolbarLastAccess', Date.now());
    }
    
    // Clean up session managers
    if (window.toolbarSessionManager) {
        window.toolbarSessionManager.destroy();
    }
});

// Enhanced activity monitoring
let lastActivityTime = Date.now();
document.addEventListener('mousemove', function() {
    lastActivityTime = Date.now();
});
document.addEventListener('keypress', function() {
    lastActivityTime = Date.now();
});

// Make checkExistingPendingOrders available globally
function checkExistingPendingOrders() {
    console.log('🔄 Default pending orders check - override this in specific pages');
    // This will be overridden by specific dashboard pages
}
window.checkExistingPendingOrders = checkExistingPendingOrders;

// Session debug function
function debugSession() {
    if (window.toolbarSessionManager) {
        const stats = window.toolbarSessionManager.getSessionStats();
        const sessionInfo = {
            phpSession: {
                userId: <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : 'null'; ?>,
                isAndroid: <?php echo json_encode($isAndroidApp); ?>,
                lastActivity: <?php echo isset($_SESSION['last_activity']) ? json_encode($_SESSION['last_activity']) : 'null'; ?>,
                sessionId: <?php echo json_encode(session_id()); ?>
            },
            javascript: stats,
            timestamp: new Date().toISOString()
        };
        console.log('🔍 Full Session Debug:', sessionInfo);
        return sessionInfo;
    }
    return null;
}
window.getSessionDebugInfo = debugSession;

// Force WebToNative cookie update (can be called from anywhere)
function forceWebToNativeCookieUpdate() {
    if (window.toolbarSessionManager) {
        window.toolbarSessionManager.forceCookieUpdate();
        return true;
    }
    return false;
}
window.forceWebToNativeCookieUpdate = forceWebToNativeCookieUpdate;
</script>

<style>
.session-info {
    font-size: 11px;
    opacity: 0.8;
    display: block;
    margin-top: 2px;
}

/* Session status badge in user dropdown */
.session-badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
}

/* WhatsApp Share Modal Styles */
.whatsapp-modal .modal-dialog {
    max-width: 500px;
}

.whatsapp-modal .preview-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    max-height: 150px;
    overflow-y: auto;
    font-size: 12px;
    white-space: pre-wrap;
}

.whatsapp-modal .language-options {
    display: flex;
    gap: 20px;
    margin: 15px 0;
}

.whatsapp-modal .language-option {
    display: flex;
    align-items: center;
    gap: 8px;
}

.whatsapp-modal .phone-input-container {
    position: relative;
}

.whatsapp-modal .phone-input-container .input-group-text {
    background: #25d366;
    color: white;
    border: 1px solid #25d366;
}

.whatsapp-modal .phone-error {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: none;
}

.whatsapp-modal .btn-whatsapp {
    background: #25d366;
    border-color: #25d366;
    color: white;
}

.whatsapp-modal .btn-whatsapp:hover {
    background: #128c7e;
    border-color: #128c7e;
}
</style>

<!-- WhatsApp Share Profile Modal -->
<div class="modal fade whatsapp-modal" id="whatsappShareModal" tabindex="-1" aria-labelledby="whatsappShareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="whatsappShareModalLabel">
                    <iconify-icon icon="logos:whatsapp-icon" class="me-2"></iconify-icon>
                    Share Profile on WhatsApp
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeWhatsappModal"></button>
            </div>
            <div class="modal-body">
                <form id="whatsappShareForm">
                    <!-- Customer Name -->
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Customer Name (Optional)</label>
                        <input type="text" class="form-control" id="customerName" placeholder="Enter customer name (optional)">
                    </div>
                    
                    <!-- Customer Phone -->
                    <div class="mb-3">
                        <label for="customerPhone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <div class="phone-input-container">
                            <div class="input-group">
                                <span class="input-group-text">+91</span>
                                <input type="tel" class="form-control" id="customerPhone" 
                                       placeholder="Enter 10-digit phone number" 
                                       maxlength="10" 
                                       pattern="[0-9]{10}" 
                                       required>
                            </div>
                            <div class="phone-error" id="phoneError"></div>
                        </div>
                    </div>
                    
                    <!-- Language Selection (for sales_person and admin only) -->
                    <div id="languageSection" class="mb-3">
                        <label class="form-label">Select Message Language</label>
                        <div class="language-options">
                            <div class="language-option">
                                <input class="form-check-input" type="radio" name="message_language" id="englishLang" value="english" checked>
                                <label class="form-check-label" for="englishLang">
                                    <iconify-icon icon="circle-flags:uk"></iconify-icon> English
                                </label>
                            </div>
                            <div class="language-option">
                                <input class="form-check-input" type="radio" name="message_language" id="hindiLang" value="hindi">
                                <label class="form-check-label" for="hindiLang">
                                    <iconify-icon icon="circle-flags:in"></iconify-icon> Hindi
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Preview -->
                    <div class="mb-3">
                        <label class="form-label">Message Preview</label>
                        <div class="preview-box" id="messagePreview">
                            Enter phone number to preview message...
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-whatsapp btn-lg">
                            <iconify-icon icon="logos:whatsapp-icon" class="me-2"></iconify-icon>
                            Share on WhatsApp
                        </button>
                    </div>
                </form>
            </div>
            <!-- <div class="modal-footer">
                <small class="text-muted">
                    <iconify-icon icon="material-symbols:info-outline" class="me-1"></iconify-icon>
                    Message will be sent via WhatsApp Web. Make sure WhatsApp is installed on your device.
                </small>
            </div> -->
        </div>
    </div>
</div>

<header class="topbar">
     <div class="container-fluid">
          <div class="navbar-header">
               <div class="d-flex align-items-center">
                    <!-- Menu Toggle Button -->
                    <div class="topbar-item">
                         <button type="button" class="button-toggle-menu me-2">
                              <iconify-icon icon="solar:hamburger-menu-broken" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>

                    <!-- Welcome Message -->
                    <div class="topbar-item">
                         <h4 class="fw-bold topbar-button pe-none text-uppercase mb-0">
                         <?php echo $user_name; ?>
                    </h4>
                    </div>
               </div>

               <div class="d-flex align-items-center gap-1">

                    <!-- Theme Color (Light/Dark) -->
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="light-dark-mode">
                              <iconify-icon icon="solar:moon-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>

                    <!-- Share Profile on WhatsApp Button -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="whatsapp-share-btn" title="Share Profile on WhatsApp">
                              <iconify-icon icon="ic:baseline-whatsapp" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>
                    <?php endif; ?>

                    

                    <!-- User -->
                    <div class="dropdown topbar-item">
                        <a type="button" class="topbar-button" id="page-header-user-dropdown" 
                           data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <span class="d-flex align-items-center">
                                   <img class="rounded-circle" width="32" src="assets/images/users/dummy-avatar.jpg" alt="avatar-3">
                              </span>
                         </a>
                         <div class="dropdown-menu dropdown-menu-end">
                              <!-- User Info -->
                              <h6 class="dropdown-header">
                                  Welcome, <?php echo $user_name; ?>! 
                                  <span class="userid_class">ID: <?php echo $user_id; ?></span>
                              </h6>

                              <div class="dropdown-divider my-1"></div>
                                
                              <!-- Logout Option -->
                              <?php
                              if (!isset($_SESSION['android_logout_button'])) {
                                  echo '<a class="dropdown-item text-danger" href="logout.php" id="logoutButton">
                                   <i class="bx bx-log-out fs-18 align-middle me-1"></i>
                                   <span class="align-middle">Logout</span>
                              </a>';
                              } ?>
                              
                         </div>
                    </div>

               </div>
          </div>
     </div>
</header>

<script>
// Enhanced toolbar functionality
document.addEventListener('DOMContentLoaded', function() {
    // WhatsApp share functionality is handled by WhatsAppShareManager class
    
    // Session status button functionality
    const sessionStatusBtn = document.getElementById('session-status-btn');
    const sessionStatusIndicator = document.getElementById('sessionStatusIndicator');
    
    if (sessionStatusBtn) {
        sessionStatusBtn.addEventListener('click', function() {
            if (sessionStatusIndicator) {
                // Toggle visibility
                if (sessionStatusIndicator.style.display === 'none') {
                    sessionStatusIndicator.style.display = 'block';
                    setTimeout(() => {
                        sessionStatusIndicator.style.display = 'none';
                    }, 5000);
                } else {
                    sessionStatusIndicator.style.display = 'block';
                }
            }
            
            // Trigger immediate health check
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.performHealthCheck();
                window.toolbarSessionManager.keepSessionAlive();
                
                // Show debug info in console
                const debugInfo = window.getSessionDebugInfo();
                console.log('🔍 Manual Session Check:', debugInfo);
            }
        });
    }

    // WebToNative debug button functionality
    const webtonativeDebugBtn = document.getElementById('webtonative-debug-btn');
    if (webtonativeDebugBtn) {
        webtonativeDebugBtn.addEventListener('click', function() {
            if (typeof androidDebug !== 'undefined') {
                androidDebug.togglePanel();
            }
            
            // Force cookie update
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.forceCookieUpdate();
            }
            
            // Show WebToNative debug info
            const debugInfo = window.getSessionDebugInfo();
            console.log('🔧 WebToNative Debug Info:', debugInfo);
            alert('WebToNative Debug Info:\n' + JSON.stringify(debugInfo, null, 2));
        });
    }
    
    // Enhanced logout - NO CONFIRMATION, device remains active
    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            // NO CONFIRMATION DIALOG - proceed directly to logout
            
            // Clean up session managers
            if (window.toolbarSessionManager) {
                window.toolbarSessionManager.destroy();
            }
            
            // Clear device-specific session storage
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('current_player_id');
                localStorage.removeItem('lastKeepAlive');
                localStorage.removeItem('sessionInitialized');
                localStorage.removeItem('webtonative_update_count');
                localStorage.removeItem('webtonative_last_cookie_update');
            }
            
            // Allow the default logout behavior to proceed
            // Device remains active in database for push notifications
        });
    }
    
    // Show session status on first load
    setTimeout(() => {
        if (sessionStatusIndicator) {
            sessionStatusIndicator.style.display = 'block';
            setTimeout(() => {
                sessionStatusIndicator.style.display = 'none';
            }, 5000);
        }
    }, 2000);
    
    // Auto-hide session status after 5 seconds
    setInterval(() => {
        if (sessionStatusIndicator && sessionStatusIndicator.style.display === 'block') {
            // Only hide if it's been visible for more than 5 seconds
            setTimeout(() => {
                sessionStatusIndicator.style.display = 'none';
            }, 5000);
        }
    }, 10000);
    
    // Real-time message preview when customer name changes
    const customerNameInput = document.getElementById('customerName');
    if (customerNameInput) {
        customerNameInput.addEventListener('input', function() {
            if (window.whatsappShareManager) {
                const language = document.querySelector('input[name="message_language"]:checked')?.value || 'english';
                window.whatsappShareManager.updateMessagePreview(language);
            }
        });
    }
});
</script>