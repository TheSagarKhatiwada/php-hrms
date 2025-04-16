<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="index3.html" class="nav-link">
          <i class="fas fa-home"></i>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="#" class="nav-link">Contacts</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Online/Offline Status -->
      <li class="nav-item">
        <div class="connection-status">
          <span class="status-dot"></span>
          <span class="status-text"></span>
        </div>
      </li>
      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
          <i class="fas fa-search"></i>
        </a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </li>
      <!-- Dark Mode Toggle -->
      <li class="nav-item">
        <a class="nav-link" id="darkModeToggle" href="#" role="button">
          <i class="fas fa-sun"></i>
          <i class="fas fa-moon" style="display: none;"></i>
        </a>
      </li>
      <!-- Fullscreen Toggle -->
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
    </ul>
</nav>
<!-- /.navbar -->

<!-- Dark Mode Toggle Script -->
<script>
$(document).ready(function() {
    // Function to update theme based on preference
    function setTheme(theme) {
        if (theme === 'dark-mode') {
            $('body').addClass('dark-mode');
            $('.main-sidebar').addClass('dark-mode');
            $('.main-header').addClass('dark-mode');
            $('.navbar').addClass('dark-mode');
            $('.brand-link').addClass('dark-mode');
            $('.sidebar').addClass('dark-mode');
            $('.navbar-light').removeClass('navbar-light').addClass('navbar-dark');
            updateThemeIcon('dark-mode');
        } else {
            $('body').removeClass('dark-mode');
            $('.main-sidebar').removeClass('dark-mode');
            $('.main-header').removeClass('dark-mode');
            $('.navbar').removeClass('dark-mode');
            $('.brand-link').removeClass('dark-mode');
            $('.sidebar').removeClass('dark-mode');
            $('.navbar-dark').removeClass('navbar-dark').addClass('navbar-light');
            updateThemeIcon('');
        }
        localStorage.setItem('theme', theme);
    }

    // Function to update theme icon
    function updateThemeIcon(theme) {
        const sunIcon = $('#darkModeToggle .fa-sun');
        const moonIcon = $('#darkModeToggle .fa-moon');
        
        if (theme === 'dark-mode') {
            sunIcon.hide();
            moonIcon.show();
        } else {
            sunIcon.show();
            moonIcon.hide();
        }
    }

    // Function to get system theme preference
    function getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark-mode' : '';
    }

    // Function to check if theme is manually set
    function isThemeManuallySet() {
        return localStorage.getItem('theme') !== null;
    }

    // Function to update theme based on system preference
    function updateThemeFromSystem() {
        const systemTheme = getSystemTheme();
        if (!isThemeManuallySet()) {
            setTheme(systemTheme);
        }
    }

    // Initialize theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme !== null) {
        setTheme(savedTheme);
    } else {
        updateThemeFromSystem();
    }

    // Listen for system theme changes
    const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    darkModeMediaQuery.addListener(function(e) {
        if (!isThemeManuallySet()) {
            setTheme(e.matches ? 'dark-mode' : '');
        }
    });

    // Toggle dark mode on click
    $('#darkModeToggle').click(function(e) {
        e.preventDefault();
        const currentTheme = $('body').hasClass('dark-mode') ? '' : 'dark-mode';
        setTheme(currentTheme);
    });
});
</script>

<!-- Online/Offline Indicator Styles -->
<style>
.connection-status {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    margin-right: 0.5rem;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
}
.status-dot.online {
    background-color: #28a745;
}
.status-dot.offline {
    background-color: #6c757d;
}
.status-text {
    font-size: 0.8rem;
    font-weight: 500;
    color: #343a40;
}
body.dark-mode .status-text {
    color: #ffffff;
}
/* Dark Mode Topbar Styles */
body.dark-mode .main-header {
    background-color: #1a1a1a;
    border-bottom: 1px solid #2d2d2d;
}
body.dark-mode .navbar-white {
    background-color: #1a1a1a !important;
    color: #ffffff !important;
}
body.dark-mode .navbar-light .navbar-nav .nav-link {
    color: #ffffff !important;
}
body.dark-mode .navbar-light .navbar-nav .nav-link:hover {
    color: #2980b9 !important;
}
body.dark-mode .navbar-light .navbar-nav .nav-link i {
    color: #ffffff !important;
}
body.dark-mode .navbar-light .navbar-nav .nav-link:hover i {
    color: #2980b9 !important;
}
body.dark-mode .dropdown-menu {
    background-color: #1a1a1a;
    border-color: #2d2d2d;
}
body.dark-mode .dropdown-item {
    color: #ffffff;
}
body.dark-mode .dropdown-item:hover {
    background-color: #2d2d2d;
    color: #ffffff;
}
body.dark-mode .dropdown-divider {
    border-top-color: #2d2d2d;
}
body.dark-mode .navbar-search-block {
    background-color: #1a1a1a;
    border-color: #2d2d2d;
}
body.dark-mode .form-control-navbar {
    background-color: #2d2d2d;
    border-color: #2d2d2d;
    color: #ffffff;
}
body.dark-mode .btn-navbar {
    background-color: #2d2d2d;
    border-color: #2d2d2d;
    color: #ffffff;
}
body.dark-mode .badge {
    background-color: #2d2d2d;
    color: #ffffff;
}
body.dark-mode .navbar-badge {
    background-color: #2980b9;
    color: #ffffff;
}
</style>