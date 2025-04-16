<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
<!-- Main Footer -->
<footer class="main-footer">
    <strong>Copyright &copy; 2014-2021 <a href="<?php echo $home;?>">HRMS</a>.</strong>
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