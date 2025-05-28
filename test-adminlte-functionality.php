<?php
$page = 'test-functionality';
$home = './';
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">AdminLTE Functionality Test</h3>
                </div>
                <div class="card-body">
                    <h4>Test the following features:</h4>
                    <div class="alert alert-info">
                        <ul>
                            <li><strong>Sidebar Toggle:</strong> Click the hamburger menu (☰) button in the top navbar to collapse/expand the sidebar</li>
                            <li><strong>Theme Switch:</strong> Click the moon/sun icon in the top navbar to toggle dark/light mode</li>
                            <li><strong>Notifications:</strong> Click the bell icon in the top navbar to view notifications dropdown</li>
                            <li><strong>Fullscreen:</strong> Click the expand icon in the top navbar to enter/exit fullscreen mode</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Functionality Status</h3>
                                </div>
                                <div class="card-body">                    <div id="test-results">
                        <p><span id="mobile-toggle-status" class="badge bg-warning">Testing...</span> Mobile Sidebar Toggle</p>
                        <p><span id="theme-status" class="badge bg-warning">Testing...</span> Theme Switch</p>
                        <p><span id="notifications-status" class="badge bg-warning">Testing...</span> Notifications</p>
                        <p><span id="fullscreen-status" class="badge bg-warning">Testing...</span> Fullscreen</p>
                        <p><small class="text-muted">Note: Desktop sidebar collapse has been removed as requested.</small></p>
                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card card-success">
                                <div class="card-header">
                                    <h3 class="card-title">Manual Test Buttons</h3>
                                </div>                                <div class="card-body">
                                    <button id="test-mobile-toggle" class="btn btn-primary btn-block mb-2">Test Mobile Sidebar Toggle</button>
                                    <button id="test-theme" class="btn btn-secondary btn-block mb-2">Test Theme Toggle</button>
                                    <button id="test-fullscreen" class="btn btn-success btn-block mb-2">Test Fullscreen</button>
                                    <button id="test-notifications" class="btn btn-info btn-block">Test Notifications</button>
                                    <small class="text-muted d-block mt-2">Note: Mobile sidebar test works only on smaller screens.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    console.log('AdminLTE Functionality Test Page Loaded');
      // Test if elements exist
    setTimeout(function() {
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const themeToggle = document.querySelector('.theme-toggle');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const fullscreenBtn = document.getElementById('fullscreen-btn');
        
        // Update status indicators
        $('#mobile-toggle-status').removeClass('bg-warning').addClass(mobileToggle ? 'bg-success' : 'bg-danger')
            .text(mobileToggle ? 'Found' : 'Missing');
            
        $('#theme-status').removeClass('bg-warning').addClass(themeToggle ? 'bg-success' : 'bg-danger')
            .text(themeToggle ? 'Found' : 'Missing');
            
        $('#notifications-status').removeClass('bg-warning').addClass(notificationDropdown ? 'bg-success' : 'bg-danger')
            .text(notificationDropdown ? 'Found' : 'Missing');
            
        $('#fullscreen-status').removeClass('bg-warning').addClass(fullscreenBtn ? 'bg-success' : 'bg-danger')
            .text(fullscreenBtn ? 'Found' : 'Missing');
    }, 1000);
    
    // Manual test buttons
    $('#test-mobile-toggle').click(function() {
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        if (mobileToggle) {
            mobileToggle.click();
            console.log('Mobile sidebar toggle clicked programmatically');
        } else {
            alert('Mobile sidebar toggle button not found!');
        }
    });
    
    $('#test-theme').click(function() {
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.click();
            console.log('Theme toggle clicked programmatically');
        } else {
            alert('Theme toggle button not found!');
        }
    });
    
    $('#test-fullscreen').click(function() {
        const fullscreenBtn = document.getElementById('fullscreen-btn');
        if (fullscreenBtn) {
            fullscreenBtn.click();
            console.log('Fullscreen button clicked programmatically');
        } else {
            alert('Fullscreen button not found!');
        }
    });
    
    $('#test-notifications').click(function() {
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) {
            // Trigger Bootstrap dropdown
            const dropdown = new bootstrap.Dropdown(notificationDropdown);
            dropdown.toggle();
            console.log('Notifications dropdown toggled programmatically');
        } else {
            alert('Notifications dropdown not found!');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
