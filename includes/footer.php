<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
<!-- Main Footer -->
<footer class="main-footer">
    <strong>Copyright &copy; 2014-<?php echo date('Y'); ?> <a href="<?php echo $home;?>"><?php echo $companyShortName?></a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b id="currentDateTime"><?php echo $currentDateTime;?></b>
    </div>
  </footer>

  <script>
  function updateCurrentDateTime() {
    const now = new Date();
    // Format date and time, e.g. YYYY-MM-DD HH:MM:SS
    const formatted = now.getFullYear() + '-' +
                      String(now.getMonth() + 1).padStart(2, '0') + '-' +
                      String(now.getDate()).padStart(2, '0') + ' ' +
                      String(now.getHours()).padStart(2, '0') + ':' +
                      String(now.getMinutes()).padStart(2, '0') + ':' +
                      String(now.getSeconds()).padStart(2, '0');
    document.getElementById('currentDateTime').textContent = formatted;
  }

  // Update date/time every second
  setInterval(updateCurrentDateTime, 1000);
  updateCurrentDateTime();
</script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php include 'includes/sweetalert.php'; ?>
  <script>
    // Function to show success toast
    function showSuccessToast(message) {
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        showCloseButton: true,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });

      Toast.fire({
        icon: 'success',
        title: message
      });
    }

    // Function to show error toast
    function showErrorToast(message) {
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        showCloseButton: true,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });

      Toast.fire({
        icon: 'error',
        title: message
      });
    }

    // Function to show warning toast
    function showWarningToast(message) {
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        showCloseButton: true,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });

      Toast.fire({
        icon: 'warning',
        title: message
      });
    }

    // Online/Offline Status Handler
    function updateConnectionStatus() {
      const statusDot = document.querySelector('.status-dot');
      const statusText = document.querySelector('.status-text');
      
      if (navigator.onLine) {
        statusDot.classList.remove('offline');
        statusDot.classList.add('online');
        statusText.textContent = 'Online';
      } else {
        statusDot.classList.remove('online');
        statusDot.classList.add('offline');
        statusText.textContent = 'Offline';
        showWarningToast('You are currently offline. Some features may not be available.');
      }
    }

    // Initialize connection status
    document.addEventListener('DOMContentLoaded', () => {
      // Set initial status
      updateConnectionStatus();
      
      // Add event listeners
      window.addEventListener('online', updateConnectionStatus);
      window.addEventListener('offline', updateConnectionStatus);
    });
  </script>
  <script src="<?php echo $home;?>assets/js/manage_maintenance.js"></script>

  <!-- PWA Service Worker Registration -->
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
          .then(registration => {
            console.log('ServiceWorker registration successful');
          })
          .catch(err => {
            console.log('ServiceWorker registration failed: ', err);
          });
      });
    }
  </script>

<?php
if (isset($_SESSION['success'])) {
    echo "<script>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: '" . $_SESSION['success'] . "',
            showConfirmButton: false,
            timer: 3000
        });
    </script>";
    unset($_SESSION['success']);
}elseif (isset($_SESSION['error'])) {
    echo "<script>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: '" . $_SESSION['error'] . "',
            showConfirmButton: false,
            timer: 3000
        });
    </script>";
    unset($_SESSION['error']);
}elseif (isset($_SESSION['warning'])) {
  echo "<script>
      Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'warning',
          title: '" . $_SESSION['warning'] . "',
          showConfirmButton: false,
          timer: 3000
      });
  </script>";
  unset($_SESSION['warning']);
}
?>