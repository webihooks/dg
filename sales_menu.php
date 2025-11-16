<div class="main-nav">
     <!-- Sidebar Logo -->
     <div class="logo-box">
          <a href="sales-dashboard.php" class="logo-dark">
               <img src="assets/images/logo-sm.png" class="logo-sm" alt="logo sm">
               <img src="assets/images/logo-dark.png" class="logo-lg" alt="logo dark">
          </a>

          <a href="sales-dashboard.php" class="logo-light">
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
                    <a class="nav-link" href="sales-dashboard.php">
                         <span class="nav-icon">
                              <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                         </span>
                         <span class="nav-text"> Dashboard </span>
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