<?php
// Only show PWA install on specific pages
$showPWA = in_array(basename($_SERVER['PHP_SELF']), ['index.php', 'admin-dashboard.php', 'user-dashboard.php']);
if ($showPWA):
?>
<script>
    // Debug function
    function debug(message) {
        console.log('[PWA Debug]', message);
    }

    // Add to Home Screen Prompt
    let deferredPrompt;
    let isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    let isStandalone = window.matchMedia('(display-mode: standalone)').matches;
    let isChrome = /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor);

    debug('Device Info:');
    debug('- iOS: ' + isIOS);
    debug('- Standalone: ' + isStandalone);
    debug('- Chrome: ' + isChrome);

    // Create install button
    const installButton = document.createElement('button');
    installButton.id = 'installButton';
    installButton.className = 'btn btn-primary';
    installButton.innerHTML = '<i class="fas fa-download"></i> Install App';
    installButton.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        display: none;
        padding: 10px 20px;
        border-radius: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        font-weight: bold;
        background-color: #007bff;
        border: none;
        color: white;
        transition: all 0.3s ease;
    `;
    installButton.onmouseover = function() {
        this.style.transform = 'scale(1.05)';
        this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
    };
    installButton.onmouseout = function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
    };
    document.body.appendChild(installButton);

    // Show install button for iOS devices
    if (isIOS && !isStandalone) {
        debug('Showing iOS install instructions');
        installButton.style.display = 'block';
        installButton.onclick = () => {
            Swal.fire({
                title: 'Install HRMS App',
                html: `
                    <div class="text-left">
                        <p>To install this app on your device:</p>
                        <ol>
                            <li>Tap the <strong>Share</strong> button <i class="fas fa-share"></i></li>
                            <li>Select <strong>Add to Home Screen</strong></li>
                            <li>Tap <strong>Add</strong></li>
                        </ol>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Got it!'
            });
        };
    }

    // Handle beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        debug('beforeinstallprompt event fired');
        e.preventDefault();
        deferredPrompt = e;
        
        // Show install button for non-iOS devices
        if (!isIOS) {
            debug('Showing install button for non-iOS device');
            installButton.style.display = 'block';
            installButton.onclick = () => {
                Swal.fire({
                    title: 'Install HRMS App',
                    text: 'Add HRMS to your home screen for quick access',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Install',
                    cancelButtonText: 'Later',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-secondary'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        debug('User confirmed installation');
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then((choiceResult) => {
                            if (choiceResult.outcome === 'accepted') {
                                debug('User accepted the install prompt');
                            } else {
                                debug('User dismissed the install prompt');
                            }
                            deferredPrompt = null;
                        });
                    }
                });
            };
        }
    });

    // Track successful installation
    window.addEventListener('appinstalled', (event) => {
        debug('App was installed');
        installButton.style.display = 'none';
        Swal.fire({
            title: 'Success!',
            text: 'HRMS has been installed on your device',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    });

    // Check if app is already installed
    window.matchMedia('(display-mode: standalone)').addEventListener('change', (evt) => {
        debug('Display mode changed: ' + evt.matches);
        if (evt.matches) {
            installButton.style.display = 'none';
        }
    });

    // Show install button after a short delay
    setTimeout(() => {
        debug('Checking if should show install button');
        if (!isStandalone) {
            debug('Showing install button');
            installButton.style.display = 'block';
        }
    }, 3000);

    // Check if PWA is installable
    if ('getInstalledRelatedApps' in navigator) {
        navigator.getInstalledRelatedApps().then(relatedApps => {
            debug('Installed related apps: ' + relatedApps.length);
        });
    }
</script>
<?php endif; ?> 