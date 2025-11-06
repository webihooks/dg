<div class="main-nav">
     <!-- Sidebar Logo -->
     <div class="logo-box">
          <a href="admin-dashboard.php" class="logo-dark">
               <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
               <img src="assets/images/logo-dark.png" class="logo-lg" alt="logo dark">
          </a>

          <a href="admin-dashboard.php" class="logo-light">
               <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
               <img src="assets/images/logo-light.png" class="logo-lg" alt="logo light">
          </a>
     </div>

     <!-- Menu Toggle Button (sm-hover) -->
     <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
          <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon"></iconify-icon>
     </button>

     <div class="scrollbar" data-simplebar>
          <ul class="navbar-nav" id="navbar-nav">



               <li class="nav-item">
                    <a class="nav-link" href="admin-dashboard.php">
                         <span class="nav-icon">
                              <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                         </span>
                         <span class="nav-text"> Dashboard </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="packages.php">
                         <span class="nav-icon">
                              <iconify-icon icon="tabler:packages"></iconify-icon>
                         </span>
                         <span class="nav-text"> Packages </span>
                    </a>
               </li>


               <li class="nav-item">
                  <a class="nav-link menu-arrow" href="#addon" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="addon">
                     <span class="nav-icon">
                        <iconify-icon icon="carbon:application-mobile"></iconify-icon>
                     </span>
                     <span class="nav-text"> Addons </span>
                  </a>
                  <div class="collapse" id="addon">
                     <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                           <a class="sub-nav-link" href="addon.php">Create Addon</a>
                        </li>
                        <li class="sub-nav-item">
                           <a class="sub-nav-link" href="addon_orders.php">View Addon Orders</a>
                        </li>
                     </ul>
                  </div>
               </li>

               <li class="menu-title">Products</li>

               <li class="nav-item">
                    <a class="nav-link" href="scan_to_csv.php">
                         <span class="nav-icon">
                              <iconify-icon icon="bx:scan"></iconify-icon>
                         </span>
                         <span class="nav-text"> Scan Text to CSV </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link open_in_browser" target="_blank" href="https://gemini.google.com/share/bccb685f59ac">
                         <span class="nav-icon">
                              <iconify-icon icon="hugeicons:ai-image"></iconify-icon>
                         </span>
                         <span class="nav-text"> AI Image </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="image_resizer.php">
                         <span class="nav-icon">
                              <iconify-icon icon="fluent:resize-16-filled"></iconify-icon>
                         </span>
                         <span class="nav-text"> Image Resizer </span>
                    </a>
               </li>

               <li class="nav-item">
                 <a class="nav-link" href="whatsapp_marketing.php">
                    <span class="nav-icon">
                       <iconify-icon icon="ic:sharp-whatsapp"></iconify-icon>
                    </span>
                    <span class="nav-text">Bulk WhatsApp Marketing</span>
                 </a>
              </li>

               <li class="nav-item">
                    <a class="nav-link" href="master_products.php">
                         <span class="nav-icon">
                              <iconify-icon icon="fluent-mdl2:product-list"></iconify-icon>
                         </span>
                         <span class="nav-text"> Master Products </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="bulk_master_products.php">
                         <span class="nav-icon">
                              <iconify-icon icon="fluent-mdl2:bulk-upload"></iconify-icon>
                         </span>
                         <span class="nav-text"> Bulk Master Products </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="all_orders.php">
                         <span class="nav-icon">
                              <iconify-icon icon="famicons:fast-food-outline"></iconify-icon>
                         </span>
                         <span class="nav-text"> All Orders </span>
                    </a>
               </li>

               <li class="menu-title">User</li>

               <!-- <li class="nav-item">
                    <a class="nav-link" href="list-of-users.php">
                         <span class="nav-icon">
                              <iconify-icon icon="mdi:users"></iconify-icon>
                         </span>
                         <span class="nav-text"> Users </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="list-of-trial-users.php">
                         <span class="nav-icon">
                              <iconify-icon icon="carbon:global-loan-and-trial"></iconify-icon>
                         </span>
                         <span class="nav-text"> Trial Users </span>
                    </a>
               </li> -->


               <li class="nav-item">
                    <a class="nav-link" href="list-of-trial-users.php">
                         <span class="nav-icon">
                              <iconify-icon icon="mdi:subscriber-identification-module-outline"></iconify-icon>
                         </span>
                         <span class="nav-text"> Trial Users </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="list-of-subscribers.php">
                         <span class="nav-icon">
                              <iconify-icon icon="mdi:subscriber-identification-module-outline"></iconify-icon>
                         </span>
                         <span class="nav-text"> Subscribers </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="subscription_payments.php">
                         <span class="nav-icon">
                              <iconify-icon icon="majesticons:rupee-circle-line"></iconify-icon>
                         </span>
                         <span class="nav-text"> Subscription </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="cards.php">
                         <span class="nav-icon">
                              <iconify-icon icon="ph:cards-light"></iconify-icon>
                         </span>
                         <span class="nav-text"> Cards Design</span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="create_cards_assignment.php">
                         <span class="nav-icon">
                              <iconify-icon icon="ph:cards-light"></iconify-icon>
                         </span>
                         <span class="nav-text"> Cards Assignment</span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="qr.php">
                         <span class="nav-icon">
                              <iconify-icon icon="ic:baseline-qrcode"></iconify-icon>
                         </span>
                         <span class="nav-text"> QR Code </span>
                    </a>
               </li>

               <li class="menu-title">Accounts</li>

               <li class="nav-item">
                    <a class="nav-link" href="ac_category.php">
                         <span class="nav-icon">
                              <iconify-icon icon="carbon:category"></iconify-icon>
                         </span>
                         <span class="nav-text"> Category </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="finance-management.php">
                         <span class="nav-icon">
                              <iconify-icon icon="material-symbols:finance-sharp"></iconify-icon>
                         </span>
                         <span class="nav-text"> Finance Management </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link open_in_browser" href="https://deegeecardinvoicegenerator.netlify.app">
                         <span class="nav-icon">
                              <iconify-icon icon="la:file-invoice-dollar"></iconify-icon>
                         </span>
                         <span class="nav-text"> Invoice </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="https://deegeecard.com/register.php">
                         <span class="nav-icon">
                              <iconify-icon icon="mdi:register-outline"></iconify-icon>
                         </span>
                         <span class="nav-text"> Register </span>
                    </a>
               </li>

               
               <li class="menu-title">Sales Track Records</li>

               <li class="nav-item">
                    <a class="nav-link" href="add_sales_record.php">
                         <span class="nav-icon">
                              <iconify-icon icon="carbon:sales-ops"></iconify-icon>
                         </span>
                         <span class="nav-text"> Add Sales Record </span>
                    </a>
               </li>

               <li class="nav-item">
                    <a class="nav-link" href="view_sales_records.php">
                         <span class="nav-icon">
                              <iconify-icon icon="lsicon:sales-return-outline"></iconify-icon>
                         </span>
                         <span class="nav-text"> View Sales Records </span>
                    </a>
               </li>

               <!-- Dont remove very important <li class="nav-item">
                    <a class="nav-link" href="bulk_import_sales_track.php">
                         <span class="nav-icon">
                              <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                         </span>
                         <span class="nav-text"> Bulk Import Sales Track </span>
                    </a>
               </li> -->


<li class="nav-item">
   <a class="nav-link menu-arrow" href="#ticket" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="ticket">
      <span class="nav-icon">
         <iconify-icon icon="material-symbols:help-outline"></iconify-icon>
      </span>
      <span class="nav-text"> Ticket </span>
   </a>
   <div class="collapse" id="ticket">
      <ul class="nav sub-navbar-nav">
         <li class="sub-nav-item">
            <a class="sub-nav-link" href="create_ticket.php">Create Ticket</a>
         </li>
         <li class="sub-nav-item">
            <a class="sub-nav-link" href="view_tickets.php">View Tickets</a>
         </li>
      </ul>
   </div>
</li>

<li class="nav-item">
     <a class="nav-link" href="announcement.php">
          <span class="nav-icon">
               <iconify-icon icon="streamline:announcement-megaphone-remix"></iconify-icon>
          </span>
          <span class="nav-text"> Announcement </span>
     </a>
</li>


          </ul>
     </div>
</div>


<script>
    function handleProfileLinkClick(url) {
        // For Android with WTN support
        if (typeof WTN !== 'undefined' && WTN.openUrlInBrowser) {
            WTN.openUrlInBrowser(url);
        } else {
            // For iOS or fallback - use the href with loadIn parameter
            window.location.href = url + '?loadIn=defaultBrowser';
        }
    }
    
    // Ensure all external links work properly
    $(document).ready(function() {
        // Handle profile links with both methods
        $('a.open_in_browser').on('click', function(e) {
            e.preventDefault();
            const url = $(this).attr('href') ? $(this).attr('href').replace('?loadIn=defaultBrowser', '') : $(this).text().trim();
            handleProfileLinkClick(url);
        });
    });
</script>