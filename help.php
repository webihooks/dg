<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deegeecard Help Center</title>
    <link rel="stylesheet" href="css/help.css">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-81W5S4MMGY');
    </script>
</head>
<body>
    <div class="container">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
            <a href="index.php"><img src="assets/images/logo-light.png" class="logo" alt="Deegeecard"></a>
        </div>
        
        <div class="main-content">
            <!-- Left Column - Navigation -->
            <div class="left-column" id="leftColumn">
                <div class="nav-title"> 
                    <a href="index.php"><img src="assets/images/logo-light.png" height="40" alt="Deegeecard"></a> 
                </div>
                <ul class="nav-menu">
                    <li class="nav-item active" onclick="showHelpContent(1)">How to Register</li>
                    <li class="nav-item" onclick="showHelpContent(2)">How to Login</li>
                    <li class="nav-item" onclick="showHelpContent(3)">How to create profile URL</li>
                    <li class="nav-item" onclick="showHelpContent(4)">How to add Profile details</li>
                    <li class="nav-item" onclick="showHelpContent(5)">How to add Profile & Cover Photo</li>
                    <li class="nav-item" onclick="showHelpContent(6)">How to add Social Sites links</li>
                    <li class="nav-item" onclick="showHelpContent(7)">How to add Business Details</li>
                    <li class="nav-item" onclick="showHelpContent(8)">How to add Bank & QR Code Details</li>
                    <li class="nav-item" onclick="showHelpContent(9)">How to add Products or Services</li>
                    <li class="nav-item" onclick="showHelpContent(10)">How to add Photo Gallery</li>
                    <li class="nav-item" onclick="showHelpContent(11)">How to Change Color Theme</li>
                    <li class="nav-item" onclick="showHelpContent(12)">How to Check Customer Reviews</li>
                    <li class="nav-item" onclick="showHelpContent(13)">How to get Subscription</li>
                </ul>
            </div>

            <!-- Right Column - Content -->
            <div class="right-column">
                <div class="content-title">Deegeecard Help Center</div>
                
                <!-- New content for registration -->
                <div id="help-content-1" class="help-content active">
                    <h2>How to Register</h2>
                    <p>To create a new Deegeecard account:</p>
                    <ol>
                        <li>Visit <a href="index.php">www.deegeecard.com</a> and click on "Register"</li>
                        <li>Enter your email address and create a strong password</li>
                        <li>Provide your basic information (name, phone number)</li>
                        <li>Verify your email address by clicking the link sent to your inbox</li>
                        <li>Complete your profile setup by following the onboarding steps</li>
                        <li>Agree to the terms of service and privacy policy</li>
                        <li>Click "Start Free Trial" and Create account to finish registration</li>
                    </ol>
                    <p class="red">Note: You must be at least 13 years old to create an account.</p>

                    <img src="images/register.jpg" class="img-responsive">
                </div>
                
                <!-- New content for login -->
                <div id="help-content-2" class="help-content">
                    <h2>How to Login</h2>
                    <p>To access your Deegeecard account:</p>
                    <ol>
                        <li>Go to deegeecard.com and click "Login"</li>
                        <li>Enter your registered email address</li>
                        <li>Type your password (case sensitive)</li>
                        <li>Optionally check "Remember Me" to stay logged in on your device</li>
                        <li>Click the "Login" button</li>
                    </ol>

                    <img src="images/login.jpg" class="img-responsive">
                </div>
                
                <!-- Content for each help topic -->
                <div id="help-content-3" class="help-content">
                    <h2>How to create profile URL</h2>
                    <p>To create your profile URL:</p>
                    <ol>
                        <li>Log in to your Deegeecard account</li>
                        <li>Go to Dashboard</li>
                        <li>Click on 'Customize URL' option</li>
                        <li>Enter your preferred username (e.g., yourname)</li>
                        <li>Check availability and save your changes</li>
                        <li>Your profile URL will be: deegeecard.com/yourname or deegeecard.com/companyname</li>
                    </ol>
                    <p class="red">Note: Profile URLs must be unique and can only contain letters, numbers, and hyphens.</p>

                    <img src="images/profile-url.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-4" class="help-content">
                    <h2>How to add Profile details</h2>
                    <p>To add your profile details:</p>
                    <ol>
                        <li>Navigate to 'My Profile' in your account</li>
                        <li>Click on 'Edit Profile' button</li>
                        <li>Fill in your personal information (name, profession, etc.)</li>
                        <li>Add a short bio in the description section</li>
                        <li>Include your contact information</li>
                        <li>Click 'Save' to update your profile</li>
                    </ol>
                    <p>Tip: Complete profiles get more engagement, so provide as much relevant information as possible.</p>
                    <img src="images/profile-img.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-5" class="help-content">
                    <h2>How to add Profile & Cover Photo</h2>
                    <p>To add or change your profile and cover photos:</p>
                    <ol>
                        <li>Go to 'Profile & Cover Photo'</li>
                        <li>Click on the camera icon on your profile picture</li>
                        <li>Upload a square image (recommended 500x500 pixels)</li>
                        <li>For cover photo, click the camera icon on the cover area</li>
                        <li>Upload a wide image (recommended 1500x500 pixels)</li>
                        <li>Adjust the cropping as needed and save</li>
                    </ol>
                    <p class="red">Note: Use high-quality images for best results.</p>
                    <img src="images/profile-cover-photo.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-6" class="help-content">
                    <h2>How to add Social Sites links</h2>
                    <p>To connect your social media profiles:</p>
                    <ol>
                        <li>Access 'Social Links' from your profile settings</li>
                        <li>Click 'Add Social Media' button</li>
                        <li>Select the platform from the dropdown menu</li>
                        <li>Paste your profile URL in the field provided</li>
                        <li>Repeat for all social platforms you want to add</li>
                        <li>Arrange the order using drag and drop</li>
                        <li>Save your changes</li>
                    </ol>
                    <p>Supported platforms: Facebook, Instagram, Twitter, LinkedIn, YouTube, and more.</p>
                    <img src="images/social-sites.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-7" class="help-content">
                    <h2>How to add Business Details</h2>
                    <p>To add your business information:</p>
                    <ol>
                        <li>Go to 'Business' tab</li>
                        <li>Fill in your business name and description</li>
                        <li>Add your business address and location</li>
                        <li>Include business hours and contact information</li>
                        <li>Save your business profile</li>
                    </ol>
                    <p>Tip: Complete business profiles appear higher in search results.</p>
                    <img src="images/business-details.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-8" class="help-content">
                    <h2>How to add Bank & QR Code Details</h2>
                    <p>To set up payment information:</p>
                    <ol>
                        <li>Navigate to 'Bank Details' tab or 'QR Code Details' tab</li>
                        <li>Select 'Bank Account' tab</li>
                        <li>Enter your bank account details (account name, number, IFSC)</li>
                        <li>For QR Code, go to 'Digital Payments' tab</li>
                        <li>Upload your UPI QR code image</li>
                        <li>Or generate a Deegeecard QR code if you don't have one</li>
                        <li>Set default payment method and save</li>
                    </ol>
                    <p class="red">Note: Bank details are encrypted and stored securely.</p>
                    <img src="images/bank-details.jpg" class="img-responsive">
                    <br>
                    <img src="images/qr-code-details.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-9" class="help-content">
                    <h2>How to add Products or Services</h2>
                    <p>To showcase your offerings:</p>
                    <ol>
                        <li>Click on 'Products/Services' in your dashboard</li>
                        <li>Select 'Add New Item'</li>
                        <li>Choose whether it's a product or service</li>
                        <li>Add title, description, and pricing</li>
                        <li>Upload product images or service icon</li>
                        <li>Set categories and tags for better discovery</li>
                        <li>Specify availability and other details</li>
                        <li>Save and publish your item</li>
                    </ol>
                    <p>Tip: Add at least 3 high-quality images for each product.</p>
                    <img src="images/products-details.jpg" class="img-responsive">
                    <br>
                    <img src="images/service-detials.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-10" class="help-content">
                    <h2>How to add Photo Gallery</h2>
                    <p>To create your photo gallery:</p>
                    <ol>
                        <li>Go to 'Photo Gallery' section in your profile</li>
                        <li>Click 'Add Photos' button</li>
                        <li>Add titles and descriptions for each photo</li>
                        <li>Save your gallery</li>
                    </ol>
                    <img src="images/photo-gallery.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-11" class="help-content">
                    <h2>How to Change Color Theme</h2>
                    <p>To customize your profile appearance:</p>
                    <ol>
                        <li>Goto 'Theme' tab</li>
                        <li>Select 'Color Theme' tab</li>
                        <li>Choose from preset color schemes</li>
                        <li>Or create custom colors using the color picker</li>
                        <li>Save your theme when satisfied</li>
                        <li>Optionally set different themes for different seasons</li>
                    </ol>
                    <p>Tip: Use colors that match your brand identity.</p>
                    <img src="images/color-theme.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-12" class="help-content">
                    <h2>How to Check Customer Reviews</h2>
                    <p>To view and manage your reviews:</p>
                    <ol>
                        <li>Go to 'Customer Reviews' section in your dashboard</li>
                        <li>View all received reviews with ratings</li>
                        <li>Filter reviews by date or rating</li>
                        <li>Read detailed feedback from customers</li>
                        <li>Check your average rating and total reviews</li>
                    </ol>
                    <img src="images/customer-reviews.jpg" class="img-responsive">
                </div>
                
                <div id="help-content-13" class="help-content">
                    <h2>How to get Subscription</h2>
                    <p>To subscribe to Deegeecard premium features:</p>
                    <ol>
                        <li>Log in to your Deegeecard account</li>
                        <li>Go to the 'Subscription' section in your dashboard</li>
                        <li>Choose your preferred subscription plan (Monthly/Yearly)</li>
                        <li>Review the features included in each plan</li>
                        <li>Click 'Select Plan' for your chosen subscription</li>
                        <li>Enter your payment details (credit card, debit card, or UPI)</li>
                        <li>Confirm your subscription</li>
                        <li>You'll receive a confirmation email with your subscription details</li>
                    </ol>
                    <p class="red">Note: Your subscription will automatically renew unless canceled before the renewal date.</p>
                    <p>Tip: Yearly subscriptions offer significant savings compared to monthly plans.</p>
                    <img src="images/subscription-plans.jpg" class="img-responsive">
                </div>
            </div>
        </div>
    </div>

    <script>
        function showHelpContent(contentNumber) {
            // Hide all content divs
            const allContents = document.querySelectorAll('.help-content');
            allContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected content
            document.getElementById(`help-content-${contentNumber}`).classList.add('active');
            
            // Update active nav item
            const allNavItems = document.querySelectorAll('.nav-item');
            allNavItems.forEach(item => {
                item.classList.remove('active');
            });
            allNavItems[contentNumber-1].classList.add('active');
            
            // Close mobile menu if open
            if (window.innerWidth <= 768) {
                document.getElementById('leftColumn').classList.remove('active');
            }
        }
        
        function toggleMobileMenu() {
            document.getElementById('leftColumn').classList.toggle('active');
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const leftColumn = document.getElementById('leftColumn');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768 && 
                !leftColumn.contains(event.target) && 
                event.target !== mobileMenuBtn) {
                leftColumn.classList.remove('active');
            }
        });
        
        // Make navigation items take full width on mobile
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('leftColumn').classList.remove('active');
            }
        });
    </script>
</body>
</html>