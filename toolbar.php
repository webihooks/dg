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

                    <!-- Menu Toggle Button -->
                    <div class="topbar-item">
                         <h4 class="fw-bold topbar-button pe-none text-uppercase mb-0">Welcome,
                         <?php echo htmlspecialchars($user_name); ?>!</h4>
                    </div>
               </div>

               <div class="d-flex align-items-center gap-1">

                    <!-- Theme Color (Light/Dark) -->
                    <div class="topbar-item">
                         <button type="button" class="topbar-button" id="light-dark-mode">
                              <iconify-icon icon="solar:moon-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                         </button>
                    </div>


                    <!-- User -->
                    <div class="dropdown topbar-item">
                         <a type="button" class="topbar-button" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <span class="d-flex align-items-center">
                                   <img class="rounded-circle" width="32" src="assets/images/users/dummy-avatar.jpg" alt="avatar-3">
                              </span>
                         </a>
                         <div class="dropdown-menu dropdown-menu-end">
                              <!-- item-->

                              <h6 class="dropdown-header">Welcome, <?php echo htmlspecialchars($user_name); ?>! 
                              <span class="userid_class">ID: <?php echo $user_id; ?></span></h6>


                              <!-- <a class="dropdown-item" href="profile.php">
                                   <i class="bx bx-user-circle text-muted fs-18 align-middle me-1"></i><span class="align-middle">Profile</span>
                              </a> -->
                                
                              <div class="dropdown-divider my-1"></div>
                                <?php
                                if (!isset($_SESSION['android_logout_button'])) {
                                    echo '<a class="dropdown-item text-danger" href="logout.php">
                                   <i class="bx bx-log-out fs-18 align-middle me-1"></i><span class="align-middle">Logout</span>
                              </a>';
                                } ?>
                              
                         </div>
                    </div>


               </div>
          </div>
     </div>
</header>
