<!-- Page Content - Ends Here -->
        </div><!-- /.content-wrapper -->
      </div><!-- /.main-wrapper -->
      
      <!-- Main Footer -->
      <footer class="main-footer" id="main-footer">
        <div class="container-fluid">
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div class="text-center text-sm-start">
              <span>&copy; <?php echo date('Y') . ' ' . COMPANY_NAME; ?>. All rights reserved.</span>
            </div>
            <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
              <span class="d-none d-sm-inline">Version 2.0</span>
              <div class="theme-preference-notice d-none alert alert-sm alert-info mb-0 py-1 px-2">
                <small>Using system preference</small>
              </div>
            </div>
          </div>
        </div>
      </footer>
    </div><!-- /.app-container -->

<style>
/* Footer Styles */
.main-footer {
  background-color: #ffffff;
  color: #6c757d;
  transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
  padding: 1rem 1.5rem;
  flex-shrink: 0;
  height: var(--footer-height);
  display: flex;
  align-items: center;
}

body.dark-mode .main-footer {
  background-color: #2c3136;
  color: #adb5bd;
  border-color: rgba(255, 255, 255, 0.05);
}

.footer-link {
  color: #0d6efd;
  transition: color 0.3s ease;
}

.footer-link:hover {
  color: #0a58ca;
}

body.dark-mode .footer-link {
  color: #6ea8fe;
}

body.dark-mode .footer-link:hover {
  color: #9ec5fe;
}

.theme-border {
  border-color: rgba(0, 0, 0, 0.125);
}

body.dark-mode .theme-border {
  border-color: rgba(255, 255, 255, 0.05);
}

@media (max-width: 575.98px) {
  .main-footer {
    padding: 0.35rem 0.5rem;
  }
  
  .main-footer .gap-3 {
    gap: 0.5rem !important;
  }
}

/* Mobile Auto-Hide Footer on Scroll */
@media (max-width: 767.98px) {
  .main-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1020;
    transition: transform 0.3s ease;
  }
  
  .footer-scroll-up {
    transform: translateY(0);
  }
  
  .footer-scroll-down {
    transform: translateY(100%);
  }
  
  /* Add padding to body to prevent content from being hidden behind fixed footer */
  body {
    padding-bottom: var(--footer-height, 60px);
  }
}

/* Enhanced Theme Toggle Styles */
.theme-toggle {
  cursor: pointer;
  transition: transform 0.3s ease;
}

.theme-toggle:hover {
  transform: scale(1.1);
}

.theme-toggle:active {
  transform: scale(0.95);
}

/* Smooth theme transitions for all elements */
html {
  transition: color 0.3s ease, background-color 0.3s ease;
}

html * {
  transition: color 0.3s ease, background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

/* SweetAlert2 Toast Customization */
.swal2-popup.swal2-toast {
  padding: 0.85rem 1.15rem;
  border-radius: 0.5rem;
  box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.15);
  border: 1px solid transparent;
  font-family: inherit;
  transition: all 0.3s ease;
  max-width: 360px;
}

/* Toast title */
.swal2-popup.swal2-toast .swal2-title {
  margin: 0.25rem 0;
  padding: 0;
  font-size: 1rem;
  font-weight: 500;
  line-height: 1.4;
}

/* Toast timer */
.swal2-popup.swal2-toast .swal2-timer-progress-bar {
  height: 4px;
  opacity: 0.7;
  border-bottom-left-radius: 0.5rem;
  border-bottom-right-radius: 0.5rem;
}

/* Toast icons */
.swal2-popup.swal2-toast .swal2-icon {
  width: 1.5em;
  min-width: 1.5em;
  height: 1.5em;
  margin: 0 0.75em 0 0;
  position: relative;
  box-sizing: content-box;
  border-width: 0.15em;
  display: flex;
  justify-content: center;
  align-items: center;
}

.swal2-popup.swal2-toast .swal2-icon .swal2-icon-content {
  font-size: 1.2em;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

/* Fix animated icons for success toast */
.swal2-popup.swal2-toast .swal2-success {
  border-color: #4CAF50;
  color: #4CAF50;
}

.swal2-popup.swal2-toast .swal2-success [class^=swal2-success-line] {
  height: 0.3em;
  background-color: #4CAF50;
  display: block;
  position: absolute;
  z-index: 2;
  border-radius: 0.125em;
}

.swal2-popup.swal2-toast .swal2-success [class^=swal2-success-line][class$=tip] {
  top: 0.8em;
  left: 0.2em;
  width: 0.6em;
  transform: rotate(45deg);
}

.swal2-popup.swal2-toast .swal2-success [class^=swal2-success-line][class$=long] {
  top: 0.65em;
  right: 0.2em;
  width: 0.95em;
  transform: rotate(-45deg);
}

/* Fix animated icons for error toast */
.swal2-popup.swal2-toast .swal2-error {
  border-color: #F44336;
  color: #F44336;
}

.swal2-popup.swal2-toast .swal2-error [class^=swal2-x-mark-line] {
  position: absolute;
  height: 0.15em;
  width: 1em;
  background-color: #F44336;
  display: block;
  top: 0.65em;
  border-radius: 0.125em;
}

.swal2-popup.swal2-toast .swal2-error [class^=swal2-x-mark-line][class$=left] {
  left: 0.25em;
  transform: rotate(45deg);
}

.swal2-popup.swal2-toast .swal2-error [class^=swal2-x-mark-line][class$=right] {
  right: 0.25em;
  transform: rotate(-45deg);
}

/* Fix animated icons for warning toast */
.swal2-popup.swal2-toast .swal2-warning {
  border-color: #FF9800;
  color: #FF9800;
}

/* Fix animated icons for info toast */
.swal2-popup.swal2-toast .swal2-info {
  border-color: #2196F3;
  color: #2196F3;
}

/* Toast close button */
.swal2-popup.swal2-toast .swal2-close {
  margin-left: 0.5em;
  font-size: 1.5em;
  color: #6c757d;
  transition: color 0.2s ease;
}

.swal2-popup.swal2-toast .swal2-close:hover {
  color: #495057;
}

/* Toast background and text colors - LIGHT THEME */
.swal2-popup.swal2-toast.success-toast {
  background-color: #E8F5E9;
  border-color: #A5D6A7;
  color: #1B5E20;
}

.swal2-popup.swal2-toast.error-toast {
  background-color: #FFEBEE;
  border-color: #FFCDD2;
  color: #B71C1C;
}

.swal2-popup.swal2-toast.warning-toast {
  background-color: #FFF3E0;
  border-color: #FFCC80;
  color: #E65100;
}

.swal2-popup.swal2-toast.info-toast {
  background-color: #E3F2FD;
  border-color: #90CAF9;
  color: #0D47A1;
}

/* Timer progress bar colors */
.swal2-popup.swal2-toast.success-toast .swal2-timer-progress-bar {
  background-color: #4CAF50;
}

.swal2-popup.swal2-toast.error-toast .swal2-timer-progress-bar {
  background-color: #F44336;
}

.swal2-popup.swal2-toast.warning-toast .swal2-timer-progress-bar {
  background-color: #FF9800;
}

.swal2-popup.swal2-toast.info-toast .swal2-timer-progress-bar {
  background-color: #2196F3;
}

/* Dark mode styles */
body.dark-mode .swal2-popup.swal2-toast {
  box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.35);
}

body.dark-mode .swal2-popup.swal2-toast.success-toast {
  background-color: #1B3724;
  border-color: #4CAF50;
  color: #A5D6A7;
}

body.dark-mode .swal2-popup.swal2-toast.error-toast {
  background-color: #3E1F22;
  border-color: #F44336;
  color: #FFCDD2;
}

body.dark-mode .swal2-popup.swal2-toast.warning-toast {
  background-color: #3E2A10;
  border-color: #FF9800;
  color: #FFCC80;
}

body.dark-mode .swal2-popup.swal2-toast.info-toast {
  background-color: #102A43;
  border-color: #2196F3;
  color: #90CAF9;
}

body.dark-mode .swal2-popup.swal2-toast .swal2-close {
  color: rgba(255, 255, 255, 0.7);
}

body.dark-mode .swal2-popup.swal2-toast .swal2-close:hover {
  color: #ffffff;
}

/* Add a subtle left border accent for better visual distinction */
.swal2-popup.swal2-toast.success-toast {
  border-left: 4px solid #4CAF50;
}

.swal2-popup.swal2-toast.error-toast {
  border-left: 4px solid #F44336;
}

.swal2-popup.swal2-toast.warning-toast {
  border-left: 4px solid #FF9800;
}

.swal2-popup.swal2-toast.info-toast {
  border-left: 4px solid #2196F3;
}

/* Mobile responsive adjustments */
@media (max-width: 576px) {
  .swal2-popup.swal2-toast {
    padding: 0.65rem 0.85rem;
    width: 300px;
    max-width: 90vw;
  }
  
  .swal2-popup.swal2-toast .swal2-title {
    font-size: 0.95rem;
  }
  
  .swal2-popup.swal2-toast .swal2-icon {
    width: 1.25em;
    min-width: 1.25em;
    height: 1.25em;
    margin: 0 0.4em 0 0;
  }
  
  .swal2-popup.swal2-toast .swal2-icon .swal2-icon-content {
    font-size: 1em;
  }
  
  /* Position adjustment for mobile */
  body.swal2-toast-shown .swal2-container.swal2-top-end,
  body.swal2-toast-shown .swal2-container.swal2-top-right {
    right: 10px;
    top: 10px;
  }
}
</style>

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 Bundle JS (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Notifications System JS -->
<script src="<?php echo isset($home) ? $home : './'; ?>resources/js/notifications.js"></script>

<!-- Loading Overlay Script -->
<script>
// Force hide the loading overlay immediately and again after a short timeout
(function() {
  // Hide immediately
  var loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.style.display = 'none';
  }
  
  // Also hide on DOMContentLoaded
  document.addEventListener('DOMContentLoaded', function() {
    var loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
      loadingOverlay.style.display = 'none';
    }
  });
  
  // Failsafe - force hide after 1 second
  setTimeout(function() {
    var loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
      loadingOverlay.style.display = 'none';
    }
  }, 1000);
})();
</script>

<!-- Notification System -->
<script>
  <?php if (isset($_SESSION['success'])): ?>
        // Success Toast Notification
        Swal.fire({
            position: 'bottom-end',
            icon: 'success',
            title: '<?php echo $_SESSION['success']; ?>',
            showConfirmButton: false,
            timer: 3000,
            toast: true,
            timerProgressBar: true,
            showCloseButton: true,
            customClass: {
                popup: 'success-toast'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        // Clear session variable after showing the toast
        <?php unset($_SESSION['success']); ?>
    <?php elseif (isset($_SESSION['error'])): ?>
        // Error Toast Notification
        Swal.fire({
            position: 'top-end',
            icon: 'error',
            title: '<?php echo $_SESSION['error']; ?>',
            showConfirmButton: false,
            timer: 3000,
            toast: true,
            timerProgressBar: true,
            showCloseButton: true,
            customClass: {
                popup: 'error-toast'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        // Clear session variable after showing the toast
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</script>

<!-- Date and Time Display -->
<script>
function updateCurrentDateTime() {
  const now = new Date();
  const formatted = now.getFullYear() + '-' +
                   String(now.getMonth() + 1).padStart(2, '0') + '-' +
                   String(now.getDate()).padStart(2, '0') + ' ' +
                   String(now.getHours()).padStart(2, '0') + ':' +
                   String(now.getMinutes()).padStart(2, '0') + ':' +
                   String(now.getSeconds()).padStart(2, '0');
  
  const dateTimeElements = document.querySelectorAll('.current-datetime');
  dateTimeElements.forEach(el => {
    if (el) el.textContent = formatted;
  });
}

// Update date/time every second if elements exist
if (document.querySelectorAll('.current-datetime').length > 0) {
  setInterval(updateCurrentDateTime, 1000);
  updateCurrentDateTime();
}
</script>

<!-- Fullscreen Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const fullscreenBtn = document.getElementById('fullscreen-btn');
  console.log('Fullscreen button (footer):', fullscreenBtn);
  
  if (fullscreenBtn) {
    const toggleFullScreen = () => {
      // ... (fullscreen toggle logic as before) ...
       if (!document.fullscreenElement && 
          !document.mozFullScreenElement && 
          !document.webkitFullscreenElement && 
          !document.msFullscreenElement) {
        // Enter fullscreen
        const element = document.documentElement;
        if (element.requestFullscreen) {
          element.requestFullscreen();
        } else if (element.msRequestFullscreen) { /* IE11 */
          element.msRequestFullscreen();
        } else if (element.mozRequestFullScreen) { /* Firefox */
          element.mozRequestFullScreen();
        } else if (element.webkitRequestFullscreen) { /* Safari */
          element.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
      } else {
        // Exit fullscreen
        if (document.exitFullscreen) {
          document.exitFullscreen();
        } else if (document.msExitFullscreen) {
          document.msExitFullscreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
          document.webkitExitFullscreen();
        }
      }
    };

    const updateFullscreenButton = () => {
      // ... (fullscreen button update logic as before) ...
      const isFullscreen = document.fullscreenElement || 
                           document.webkitFullscreenElement || 
                           document.mozFullScreenElement || 
                           document.msFullscreenElement;
      if (isFullscreen) {
        fullscreenBtn.innerHTML = '<i class="fas fa-compress-arrows-alt"></i>';
        document.body.classList.add('fullscreen-enabled');
        fullscreenBtn.setAttribute('aria-label', 'Exit fullscreen');
      } else {
        fullscreenBtn.innerHTML = '<i class="fas fa-expand-arrows-alt"></i>';
        document.body.classList.remove('fullscreen-enabled');
        fullscreenBtn.setAttribute('aria-label', 'Enter fullscreen');
      }
    };

    fullscreenBtn.addEventListener('click', () => {
      console.log('Fullscreen button clicked (footer)');
      toggleFullScreen();
    });
    
    document.addEventListener('fullscreenchange', updateFullscreenButton);
    document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
    document.addEventListener('mozfullscreenchange', updateFullscreenButton);
    document.addEventListener('MSFullscreenChange', updateFullscreenButton);

    updateFullscreenButton(); 
    console.log('Fullscreen functionality initialized (footer)');
  }
});
</script>

<!-- Unified Dark Mode Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Dark mode toggle functionality that works with both localStorage and cookie
  const toggleDarkMode = (enableDark) => {
    if (enableDark) {
      document.body.classList.add('dark-mode');
      localStorage.setItem('dark-mode', 'true');
      document.cookie = 'dark-mode=true; path=/; max-age=31536000'; // 1 year
    } else {
      document.body.classList.remove('dark-mode');
      localStorage.setItem('dark-mode', 'false');
      document.cookie = 'dark-mode=false; path=/; max-age=31536000';
    }
    
    // Update table classes
    document.querySelectorAll('table').forEach(table => {
      table.classList.toggle('table-dark', enableDark);
    });
    
    // Update any toggle icons
    const darkIcons = document.querySelectorAll('.dark-icon');
    const lightIcons = document.querySelectorAll('.light-icon');
    
    darkIcons.forEach(icon => {
      icon.classList.toggle('d-none', enableDark);
    });
    
    lightIcons.forEach(icon => {
      icon.classList.toggle('d-none', !enableDark);
    });
    
    // Update mobile theme text if present
    const mobileThemeText = document.querySelector('.mobile-theme-text');
    if (mobileThemeText) {
      mobileThemeText.textContent = enableDark ? 'Light Mode' : 'Dark Mode';
    }
    
    // Update sidebar and footer classes for expanded/collapsed state
    const contentWrapper = document.getElementById('content-wrapper');
    const mainHeader = document.getElementById('main-header');
    const mainFooter = document.getElementById('main-footer');
    const sidebar = document.getElementById('main-sidebar');
    
    if (sidebar && sidebar.classList.contains('collapsed')) {
      if (contentWrapper) contentWrapper.classList.add('content-wrapper-expanded');
      if (mainHeader) mainHeader.classList.add('main-header-expanded');
      if (mainFooter) mainFooter.classList.add('main-footer-expanded');
    } else {
      if (contentWrapper) contentWrapper.classList.remove('content-wrapper-expanded');
      if (mainHeader) mainHeader.classList.remove('main-header-expanded');
      if (mainFooter) mainFooter.classList.remove('main-footer-expanded');
    }
  };
  
  // Check for dark mode preference and apply it
  const darkModeEnabled = localStorage.getItem('dark-mode') === 'true' || 
                         document.cookie.split('; ').find(row => row.startsWith('dark-mode='))?.split('=')[1] === 'true';
  
  // Apply initial theme (including table class)
  toggleDarkMode(darkModeEnabled);
  
  // Set up event listeners for theme toggles
  document.querySelectorAll('.theme-toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
      const isDarkMode = document.body.classList.contains('dark-mode');
      toggleDarkMode(!isDarkMode);
    });
  });
});
</script>

<!-- Unified Sidebar Management Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('main-sidebar');
  const mobileToggleButton = document.getElementById('mobile-sidebar-toggle');
  const sidebarCloseButton = document.getElementById('sidebar-close');

  console.log('Sidebar elements:', { 
    sidebar: sidebar, 
    mobileToggleButton: mobileToggleButton, 
    sidebarCloseButton: sidebarCloseButton 
  });

  // Mobile Toggle Button (Show/Hide)
  if (mobileToggleButton && sidebar) {
    mobileToggleButton.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      console.log('Mobile toggle clicked - showing/hiding sidebar');
      sidebar.classList.toggle('show');
    });
  }

  // Mobile Close Button
  if (sidebarCloseButton && sidebar) {
    sidebarCloseButton.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('Close button clicked - hiding sidebar');
      sidebar.classList.remove('show');
    });
  }

  // Close mobile sidebar when clicking outside
  document.addEventListener('click', function(event) {
    if (window.innerWidth <= 768 && 
        sidebar && 
        !sidebar.contains(event.target) && 
        mobileToggleButton && 
        !mobileToggleButton.contains(event.target) &&
        sidebar.classList.contains('show')) {
      console.log('Outside click detected - hiding sidebar');
      sidebar.classList.remove('show');
    }
  });

  // Ensure mobile starts hidden on page load
  if (window.innerWidth <= 768) {
    if (sidebar) sidebar.classList.remove('show');
  }
});
</script>

<!-- DataTables Initialization -->
<script>
$(function() {
  // Only initialize datatables if they exist
  if ($('.datatable').length > 0) {
    $('.datatable').DataTable({
      "responsive": true,
      "autoWidth": false,
      "language": {
        "paginate": {
          "previous": '<i class="fas fa-chevron-left"></i>',
          "next": '<i class="fas fa-chevron-right"></i>'
        }
      }
    });
  }
  
  // Initialize tooltips
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
  
  // Initialize popovers
  const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
  [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
});
</script>

<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      // First, unregister any existing service workers to force reload of the new one
      const registrations = await navigator.serviceWorker.getRegistrations();
      for (const registration of registrations) {
        await registration.unregister();
        console.log('ServiceWorker unregistered successfully');
      }
      
      // Then register the new service worker
      const registration = await navigator.serviceWorker.register('<?php echo isset($home) ? $home : ''; ?>sw.js?v=<?php echo time(); ?>', {
        updateViaCache: 'none'
      });
      console.log('ServiceWorker registration successful with scope:', registration.scope);
    } catch (error) {
      console.error('ServiceWorker registration failed:', error);
    }
  });
}
</script>

<!-- Auto-hide Footer on Scroll for Mobile -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Auto-hide footer on scroll for mobile
  if (window.innerWidth < 768) {
    const footer = document.getElementById('main-footer');
    let lastScrollTop = 0;
    let scrollTimer;
    
    // Add initial class
    if (footer) {
      footer.classList.add('footer-scroll-up');
      
      window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Clear previous timeout
        clearTimeout(scrollTimer);
        
        // Scrolling down
        if (scrollTop > lastScrollTop && scrollTop > 100) {
          footer.classList.remove('footer-scroll-up');
          footer.classList.add('footer-scroll-down');
        } 
        // Scrolling up
        else if (scrollTop < lastScrollTop) {
          footer.classList.remove('footer-scroll-down');
          footer.classList.add('footer-scroll-up');
        }
        
        // Show footer when user stops scrolling for a short while
        scrollTimer = setTimeout(function() {
          footer.classList.remove('footer-scroll-down');
          footer.classList.add('footer-scroll-up');
        }, 2000);
        
        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
      }, { passive: true });
    }
  }
});
</script>

<!-- Common AJAX Forms Handler -->
<script>
$(function() {
  // Handle all forms with the ajax-form class
  $(document).on('submit', '.ajax-form', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    
    // Show loading state
    submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
    submitBtn.prop('disabled', true);
    
    $.ajax({
      url: form.attr('action'),
      type: form.attr('method'),
      data: new FormData(this),
      processData: false,
      contentType: false,
      success: function(response) {
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (data.success) {
            showSuccessToast(data.message || 'Operation completed successfully');
            
            // Handle redirect if specified
            if (data.redirect) {
              setTimeout(() => {
                window.location.href = data.redirect;
              }, 1000);
            }
            
            // Handle form reset if needed
            if (data.reset && data.reset === true) {
              form[0].reset();
            }
            
            // Trigger custom event for additional handling
            form.trigger('ajax:success', [data]);
          } else {
            showErrorToast(data.message || 'An error occurred');
            form.trigger('ajax:error', [data]);
          }
        } catch (e) {
          console.error('Error parsing response:', e);
          showErrorToast('Invalid response from server');
        }
      },
      error: function(xhr) {
        let errorMsg = 'Server error occurred';
        
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        } else if (xhr.responseText) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) errorMsg = response.message;
          } catch (e) {
            // Keep default error message
          }
        }
        
        showErrorToast(errorMsg);
        form.trigger('ajax:error', [xhr]);
      },
      complete: function() {
        // Restore button state
        submitBtn.html(originalBtnText);
        submitBtn.prop('disabled', false);
      }
    });
  });
});
</script>

<!-- JS to prevent caching and ensure fresh data -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Add timestamp to AJAX requests to prevent caching
  $.ajaxSetup({
    cache: false,
    headers: {
      'Cache-Control': 'no-cache, no-store, must-revalidate, max-age=0',
      'Pragma': 'no-cache',
      'Expires': '0'
    },
    beforeSend: function(xhr) {
      // Add a timestamp to the URL to prevent caching
      this.url += (this.url.indexOf('?') === -1 ? '?' : '&') + '_t=' + new Date().getTime();
    }
  });

  // Force reload of data-sensitive pages when user returns to the app after inactivity
  let lastActivity = Date.now();
  const inactivityThreshold = 5 * 60 * 1000; // 5 minutes

  // Update last activity time on user interaction
  ['click', 'keypress', 'scroll', 'mousemove', 'touchstart'].forEach(function(event) {
    document.addEventListener(event, function() {
      lastActivity = Date.now();
    }, true);
  });

  // Check if page needs refresh when user returns to window/tab
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
      const timeSinceActivity = Date.now() - lastActivity;
      if (timeSinceActivity > inactivityThreshold) {
        // Only reload data-sensitive pages
        const dataSensitivePages = ['dashboard.php', 'admin-dashboard.php', 'attendance.php', 
                                   'daily-report.php', 'periodic-report.php', 'employees.php',
                                   'assets.php', 'manage_assets.php'];
        
        const currentPage = window.location.pathname.split('/').pop();
        if (dataSensitivePages.includes(currentPage)) {
          // Show a subtle reload notification
          const notification = document.createElement('div');
          notification.style.position = 'fixed';
          notification.style.top = '60px';
          notification.style.right = '20px';
          notification.style.padding = '10px 15px';
          notification.style.backgroundColor = 'rgba(13, 110, 253, 0.9)';
          notification.style.color = 'white';
          notification.style.borderRadius = '4px';
          notification.style.zIndex = '9999';
          notification.innerHTML = '<i class="fas fa-sync-alt"></i> Refreshing data...';
          document.body.appendChild(notification);
          
          // Refresh the page after a short delay
          setTimeout(function() {
            window.location.reload();
          }, 1000);
        }
      }
    }
  });
});
</script>

<!-- Sidebar Navigation Enhancement -->
<script>
$(document).ready(function() {
  // Handle treeview navigation for Leave Module
  $('.has-treeview').click(function(e) {
    e.preventDefault();
    var $this = $(this);
    var $parent = $this.parent();
    var $treeview = $parent.find('.nav-treeview');
    
    // Toggle the treeview
    if ($treeview.is(':visible')) {
      $treeview.slideUp();
      $this.removeClass('active');
      $this.find('.fa-angle-left').removeClass('fa-angle-down');
    } else {
      // Close other open treeviews
      $('.nav-treeview:visible').slideUp();
      $('.has-treeview.active').removeClass('active');
      $('.has-treeview .fa-angle-left').removeClass('fa-angle-down');
      
      // Open this treeview
      $treeview.slideDown();
      $this.addClass('active');
      $this.find('.fa-angle-left').addClass('fa-angle-down');
    }
  });
  
  // Keep treeview open if we're on a child page
  if (window.location.href.indexOf('modules/leave/') !== -1) {
    var $leaveTreeview = $('a[href*="modules/leave/"]').first().closest('.nav-item').find('.nav-treeview');
    var $leaveToggle = $('a[href*="modules/leave/"]').first().closest('.nav-item').find('.has-treeview');
    
    $leaveTreeview.show();
    $leaveToggle.addClass('active');
    $leaveToggle.find('.fa-angle-left').addClass('fa-angle-down');
  }
});
</script>
</body>
</html>