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
    padding: 0.75rem 1rem;
  }
  
  .main-footer .gap-3 {
    gap: 0.5rem !important;
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

<!-- Loading Overlay Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Hide loading overlay once the page is fully loaded
  const loadingOverlay = document.getElementById('loadingOverlay');
  if (loadingOverlay) {
    loadingOverlay.style.opacity = '0';
    setTimeout(() => {
      loadingOverlay.style.display = 'none';
    }, 300);
  }
});
</script>

<!-- Notification System -->
<script>
function showNotification(type, message) {
  Swal.fire({
    icon: type, // 'success', 'error', 'warning', 'info'
    title: type.charAt(0).toUpperCase() + type.slice(1),
    text: message,
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
  });
}

// Helper functions for common notifications
function showSuccessToast(message) {
  showNotification('success', message);
}

function showErrorToast(message) {
  showNotification('error', message);
}

function showWarningToast(message) {
  showNotification('warning', message);
}

function showInfoToast(message) {
  showNotification('info', message);
}

// Handle PHP Session Messages if they exist
<?php if(isset($_SESSION['success'])): ?>
  showSuccessToast('<?php echo addslashes($_SESSION['success']); ?>');
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
  showErrorToast('<?php echo addslashes($_SESSION['error']); ?>');
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['warning'])): ?>
  showWarningToast('<?php echo addslashes($_SESSION['warning']); ?>');
  <?php unset($_SESSION['warning']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['info'])): ?>
  showInfoToast('<?php echo addslashes($_SESSION['info']); ?>');
  <?php unset($_SESSION['info']); ?>
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

<!-- Sidebar Collapse/Expand Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('main-sidebar');
  const toggleButton = document.getElementById('sidebar-toggle'); // Assuming the main toggle button has this ID
  const mobileToggleButton = document.getElementById('mobile-sidebar-toggle');
  const sidebarCloseButton = document.getElementById('sidebar-close');

  const applySidebarState = (isCollapsed) => {
    if (isCollapsed) {
      document.body.classList.add('sidebar-collapse');
      if (sidebar) sidebar.classList.add('collapsed');
      localStorage.setItem('sidebarState', 'collapsed');
      if (toggleButton) toggleButton.setAttribute('aria-expanded', 'false');
    } else {
      document.body.classList.remove('sidebar-collapse');
      if (sidebar) sidebar.classList.remove('collapsed');
      localStorage.setItem('sidebarState', 'expanded');
      if (toggleButton) toggleButton.setAttribute('aria-expanded', 'true');
    }
    // Trigger a resize event slightly after transition to help libraries redraw
    setTimeout(() => window.dispatchEvent(new Event('resize')), 350); 
  };

  // Desktop Toggle
  if (toggleButton && sidebar) {
    toggleButton.addEventListener('click', function(e) {
      e.preventDefault();
      applySidebarState(!document.body.classList.contains('sidebar-collapse'));
    });
  }

  // Mobile Toggle (Show/Hide)
  if (mobileToggleButton && sidebar) {
    mobileToggleButton.addEventListener('click', function() {
      sidebar.classList.toggle('show'); // For mobile overlay visibility
      // Optionally collapse desktop view if mobile opens?
      // applySidebarState(false); // Uncomment if opening mobile should always expand desktop layout
    });
  }

  // Mobile Close Button
  if (sidebarCloseButton && sidebar) {
    sidebarCloseButton.addEventListener('click', function() {
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
      sidebar.classList.remove('show');
    }
  });

  // Apply initial state from localStorage on desktop
  if (window.innerWidth > 768) {
      const savedState = localStorage.getItem('sidebarState');
      applySidebarState(savedState === 'collapsed');
  } else {
      // Ensure mobile starts hidden and layout is not collapsed
      if(sidebar) sidebar.classList.remove('show');
      applySidebarState(false); 
  }
});
</script>

<!-- Mobile Sidebar Management Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const mobileToggle = document.getElementById('mobile-sidebar-toggle');
  const sidebar = document.getElementById('main-sidebar');
  const sidebarClose = document.getElementById('sidebar-close');
  
  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', function() {
      sidebar.classList.toggle('show');
    });
  }
  
  if (sidebarClose && sidebar) {
    sidebarClose.addEventListener('click', function() {
      sidebar.classList.remove('show');
    });
  }
  
  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', function(event) {
    if (window.innerWidth <= 768 && 
        sidebar && 
        !sidebar.contains(event.target) && 
        mobileToggle && 
        !mobileToggle.contains(event.target) &&
        sidebar.classList.contains('show')) {
      sidebar.classList.remove('show');
    }
  });
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

<!-- Online/Offline Status Detection -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const indicators = document.querySelectorAll('.connection-dot');
  const statusTexts = document.querySelectorAll('#connection-text');
  
  function updateOnlineStatus() {
    if (navigator.onLine) {
      indicators.forEach(indicator => {
        indicator.classList.remove('offline');
        indicator.classList.add('online');
      });
      statusTexts.forEach(text => {
        if (text) text.textContent = 'Online';
      });
    } else {
      indicators.forEach(indicator => {
        indicator.classList.remove('online');
        indicator.classList.add('offline');
      });
      statusTexts.forEach(text => {
        if (text) text.textContent = 'Offline';
      });
    }
  }
  
  window.addEventListener('online', updateOnlineStatus);
  window.addEventListener('offline', updateOnlineStatus);
  updateOnlineStatus(); // Initial check
});
</script>

<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('<?php echo isset($home) ? $home : ''; ?>sw.js')
      .then(registration => {
        console.log('ServiceWorker registration successful');
      })
      .catch(err => {
        console.log('ServiceWorker registration failed: ', err);
      });
  });
}
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
</body>
</html>