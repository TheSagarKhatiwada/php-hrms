/**
 * Notifications JavaScript
 * 
 * Handles notification operations in the browser including:
 * - Loading notifications into the dropdown
 * - Updating the notification badge
 * - Marking notifications as read
 * - Deleting notifications
 */

class NotificationManager {
    constructor() {
        // Cache DOM elements
        this.badge = document.getElementById('notificationBadge');
        this.mobileBadge = document.getElementById('mobileNotificationBadge');
        this.dropdown = document.getElementById('notificationDropdown');
        this.container = document.getElementById('notificationList');
        this.mobileContainer = document.getElementById('mobileNotificationList');
        this.markAllReadBtn = document.getElementById('markAllRead');
        this.mobileMarkAllReadBtn = document.getElementById('mobileMarkAllRead');
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Initialize notification system
        this.init();
    }
    
    /**
     * Initialize notification system
     */
    init() {
        // Initial load of notifications
        this.loadNotificationCount();
        
        // Set up polling for new notifications (every 60 seconds)
        setInterval(() => {
            this.loadNotificationCount();
        }, 60000);
        
        // Set up dropdown events - load notifications when dropdown is opened
        if (this.dropdown) {
            this.dropdown.addEventListener('show.bs.dropdown', () => {
                this.loadNotifications();
            });
        }
        
        // Mobile dropdown
        const mobileDropdown = document.getElementById('mobileNotificationsDropdown');
        if (mobileDropdown) {
            mobileDropdown.addEventListener('show.bs.dropdown', () => {
                this.loadMobileNotifications();
            });
        }
        
        // Mark all as read functionality
        if (this.markAllReadBtn) {
            this.markAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
        }
        
        if (this.mobileMarkAllReadBtn) {
            this.mobileMarkAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
        }
    }
    
    /**
     * Load notification count and update badge
     */
    loadNotificationCount() {
        // Use a relative path with respect to the current page
        const url = new URL('api/notifications.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
        
        fetch(url + '?action=get_count')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.updateBadge(data.count);
                } else {
                    console.error('API Error:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error loading notification count:', error);
            });
    }
    
    /**
     * Load notifications into desktop dropdown
     */
    loadNotifications() {
        if (!this.container) return;
        
        // Show loading indicator
        this.container.innerHTML = `
            <div class="d-flex justify-content-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        // Use a relative path with respect to the current page
        const url = new URL('api/notifications.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
        
        fetch(url + '?action=get_notifications')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.renderNotifications(data.notifications, this.container);
                } else {
                    this.container.innerHTML = '<div class="p-3 text-center text-muted">Failed to load notifications</div>';
                    console.error('API Error:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                this.container.innerHTML = `<div class="p-3 text-center text-muted">Error loading notifications: ${error.message}</div>`;
            });
    }
    
    /**
     * Load notifications into mobile dropdown
     */
    loadMobileNotifications() {
        if (!this.mobileContainer) return;
        
        // Show loading indicator
        this.mobileContainer.innerHTML = `
            <div class="d-flex justify-content-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        // Use a relative path with respect to the current page
        const url = new URL('api/notifications.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
        
        fetch(url + '?action=get_notifications')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.renderNotifications(data.notifications, this.mobileContainer);
                } else {
                    this.mobileContainer.innerHTML = '<div class="p-3 text-center text-muted">Failed to load notifications</div>';
                    console.error('API Error:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                this.mobileContainer.innerHTML = `<div class="p-3 text-center text-muted">Error loading notifications: ${error.message}</div>`;
            });
    }
    
    /**
     * Render notifications in the dropdown
     * 
     * @param {Array} notifications Notification data
     * @param {HTMLElement} container Container to render notifications in
     */
    renderNotifications(notifications, container) {
        if (!container) return;
        
        if (notifications.length === 0) {
            container.innerHTML = '<div class="p-3 text-center text-muted">No new notifications</div>';
            return;
        }
        
        let html = '';
        
        // Build notification items
        notifications.forEach(notification => {
            // Determine notification icon based on type
            let icon = 'fa-info-circle';
            switch (notification.type) {
                case 'success':
                    icon = 'fa-check-circle';
                    break;
                case 'warning':
                    icon = 'fa-exclamation-triangle';
                    break;
                case 'danger':
                    icon = 'fa-times-circle';
                    break;
            }
            
            // Format date
            const date = new Date(notification.created_at);
            const timeAgo = this.timeAgo(date);
            
            // Determine read class
            const readClass = notification.is_read ? 'read' : '';
            
            // Build item HTML with improved styling
            html += `
                <div class="notification-item ${readClass}" data-id="${notification.id}">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            <i class="fas ${icon} fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${notification.title}</h6>
                            <p class="mb-1">${notification.message}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">${timeAgo}</small>
                                <div class="notification-actions">
                                    ${!notification.is_read ? 
                                        `<button class="btn btn-sm btn-outline-secondary mark-read-btn" 
                                                data-id="${notification.id}" 
                                                title="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>` : ''}
                                    ${notification.link ? 
                                        `<a href="${notification.link}" class="btn btn-sm btn-outline-primary ms-1" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        // Update DOM
        container.innerHTML = html;
        
        // Attach event listeners to new elements
        this.attachEventListeners(container);
    }
    
    /**
     * Attach event listeners to notification items
     * 
     * @param {HTMLElement} container Container with notification items
     */
    attachEventListeners(container) {
        // Mark as read buttons
        const markReadButtons = container.querySelectorAll('.mark-read-btn');
        markReadButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const notificationId = button.getAttribute('data-id');
                this.markAsRead(notificationId, button);
            });
        });
        
        // Notification items - mark as read when clicked
        const notificationItems = container.querySelectorAll('.notification-item:not(.read)');
        notificationItems.forEach(item => {
            item.addEventListener('click', (e) => {
                // Only process if not clicking on a button or link
                if (!e.target.closest('button') && !e.target.closest('a')) {
                    const notificationId = item.getAttribute('data-id');
                    const markReadBtn = item.querySelector('.mark-read-btn');
                    if (markReadBtn) {
                        this.markAsRead(notificationId, markReadBtn);
                    }
                }
            });
        });
    }
    
    /**
     * Mark a notification as read
     * 
     * @param {string} notificationId ID of the notification to mark as read
     * @param {HTMLElement} button The mark as read button element
     */
    markAsRead(notificationId, button) {
        if (!this.csrfToken) return;
        
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);
        formData.append('csrf_token', this.csrfToken);
        
        // Use a relative path with respect to the current page
        const url = new URL('api/notifications.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Find and update notification item
                const item = button.closest('.notification-item');
                if (item) {
                    item.classList.add('read');
                    button.remove();
                }
                
                // Update badge count
                this.loadNotificationCount();
            } else {
                console.error('API Error:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    /**
     * Mark all notifications as read
     */
    markAllAsRead() {
        if (!this.csrfToken) return;
        
        const formData = new FormData();
        formData.append('action', 'mark_all_read');
        formData.append('csrf_token', this.csrfToken);
        
        // Use a relative path with respect to the current page
        const url = new URL('api/notifications.php', window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/'));
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update UI in both dropdowns
                if (this.container) {
                    const items = this.container.querySelectorAll('.notification-item:not(.read)');
                    items.forEach(item => {
                        item.classList.add('read');
                        const btn = item.querySelector('.mark-read-btn');
                        if (btn) btn.remove();
                    });
                }
                
                if (this.mobileContainer) {
                    const items = this.mobileContainer.querySelectorAll('.notification-item:not(.read)');
                    items.forEach(item => {
                        item.classList.add('read');
                        const btn = item.querySelector('.mark-read-btn');
                        if (btn) btn.remove();
                    });
                }
                
                // Update badge count
                this.updateBadge(0);
            } else {
                console.error('API Error:', data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
    }
    
    /**
     * Update notification badge with count
     * 
     * @param {number} count Number of unread notifications
     */
    updateBadge(count) {
        if (this.badge) {
            if (count > 0) {
                this.badge.textContent = count;
                this.badge.classList.remove('d-none');
            } else {
                this.badge.textContent = '';
                this.badge.classList.add('d-none');
            }
        }
        
        if (this.mobileBadge) {
            if (count > 0) {
                this.mobileBadge.textContent = count;
                this.mobileBadge.classList.remove('d-none');
            } else {
                this.mobileBadge.textContent = '';
                this.mobileBadge.classList.add('d-none');
            }
        }
    }
    
    /**
     * Format date as time ago string
     * 
     * @param {Date} date Date to format
     * @return {string} Formatted time ago string
     */
    timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = Math.floor(seconds / 31536000);
        if (interval > 1) return interval + ' years ago';
        if (interval === 1) return '1 year ago';
        
        interval = Math.floor(seconds / 2592000);
        if (interval > 1) return interval + ' months ago';
        if (interval === 1) return '1 month ago';
        
        interval = Math.floor(seconds / 86400);
        if (interval > 1) return interval + ' days ago';
        if (interval === 1) return '1 day ago';
        
        interval = Math.floor(seconds / 3600);
        if (interval > 1) return interval + ' hours ago';
        if (interval === 1) return '1 hour ago';
        
        interval = Math.floor(seconds / 60);
        if (interval > 1) return interval + ' minutes ago';
        if (interval === 1) return '1 minute ago';
        
        if (seconds < 10) return 'just now';
        
        return Math.floor(seconds) + ' seconds ago';
    }
}

// Initialize notification manager when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
});