/**
 * Notification Manager
 * 
 * Handles all notification operations in the browser
 */
class NotificationManager {
    constructor() {
        // UI elements
        this.notificationDropdown = document.getElementById('notificationDropdown');
        this.notificationList = document.getElementById('notificationList');
        this.notificationBadge = document.getElementById('notificationBadge');
        this.markAllReadBtn = document.getElementById('markAllRead');
        
        // Mobile elements
        this.mobileNotificationList = document.getElementById('mobileNotificationList');
        this.mobileNotificationBadge = document.getElementById('mobileNotificationBadge');
        this.mobileMarkAllReadBtn = document.getElementById('mobileMarkAllRead');
        
        // Initialize
        this.initialize();
    }
    
    /**
     * Initialize the notification manager
     */
    initialize() {
        // Fetch unread count on page load
        this.fetchUnreadCount();
        
        // Fetch notifications when dropdown is opened
        if (this.notificationDropdown) {
            this.notificationDropdown.addEventListener('click', () => {
                this.fetchNotifications();
            });
        }
        
        // Mark all as read
        if (this.markAllReadBtn) {
            this.markAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
        }
        
        // Mobile mark all as read
        if (this.mobileMarkAllReadBtn) {
            this.mobileMarkAllReadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
        }
        
        // Set up auto-refresh for notification count
        setInterval(() => {
            this.fetchUnreadCount();
        }, 60000); // Every minute
    }
    
    /**
     * Fetch unread notification count
     */
    fetchUnreadCount() {
        fetch('api/notifications.php?action=get_count')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.updateNotificationBadge(data.count);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error fetching notification count:', error);
                // Don't update the UI on error to avoid disrupting user experience
            });
    }
    
    /**
     * Update notification badge count
     * 
     * @param {number} count Unread notification count
     */
    updateNotificationBadge(count) {
        // Update desktop badge
        if (this.notificationBadge) {
            if (count > 0) {
                this.notificationBadge.textContent = count;
                this.notificationBadge.classList.remove('d-none');
            } else {
                this.notificationBadge.classList.add('d-none');
            }
        }
        
        // Update mobile badge
        if (this.mobileNotificationBadge) {
            if (count > 0) {
                this.mobileNotificationBadge.textContent = count;
                this.mobileNotificationBadge.classList.remove('d-none');
            } else {
                this.mobileNotificationBadge.classList.add('d-none');
            }
        }
    }
    
    /**
     * Fetch notifications
     */
    fetchNotifications() {
        // Show loading indicator
        if (this.notificationList) {
            this.notificationList.innerHTML = '<div class="list-group-item d-flex align-items-center"><div class="small text-gray-500 text-center w-100">Loading notifications...</div></div>';
        }
        
        if (this.mobileNotificationList) {
            this.mobileNotificationList.innerHTML = '<div class="dropdown-item d-flex align-items-center"><div class="small text-gray-500 text-center w-100">Loading notifications...</div></div>';
        }
        
        fetch('api/notifications.php?action=get_notifications')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.renderNotifications(data.notifications);
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                const errorMessage = `Error loading notifications: ${error.message}`;
                
                if (this.notificationList) {
                    this.notificationList.innerHTML = `<div class="list-group-item d-flex align-items-center"><div class="small text-danger text-center w-100">${errorMessage}</div></div>`;
                }
                
                if (this.mobileNotificationList) {
                    this.mobileNotificationList.innerHTML = `<div class="dropdown-item d-flex align-items-center"><div class="small text-danger text-center w-100">${errorMessage}</div></div>`;
                }
            });
    }
    
    /**
     * Render notifications in the dropdown
     * 
     * @param {Array} notifications Array of notification objects
     */
    renderNotifications(notifications) {
        // Empty both lists first
        if (this.notificationList) {
            this.notificationList.innerHTML = '';
        }
        
        if (this.mobileNotificationList) {
            this.mobileNotificationList.innerHTML = '';
        }
        
        if (notifications.length === 0) {
            const emptyMessage = '<div class="list-group-item text-center py-3"><div class="text-gray-500">No new notifications</div></div>';
            
            if (this.notificationList) {
                this.notificationList.innerHTML = emptyMessage;
            }
            
            if (this.mobileNotificationList) {
                this.mobileNotificationList.innerHTML = emptyMessage;
            }
            
            return;
        }
        
        // Create notification items
        notifications.forEach(notification => {
            // Create desktop notification item
            if (this.notificationList) {
                const notificationItem = this.createNotificationItem(notification);
                this.notificationList.appendChild(notificationItem);
            }
            
            // Create mobile notification item
            if (this.mobileNotificationList) {
                const mobileNotificationItem = this.createNotificationItem(notification, true);
                this.mobileNotificationList.appendChild(mobileNotificationItem);
            }
        });
    }
    
    /**
     * Create a notification item element
     * 
     * @param {Object} notification Notification object
     * @param {boolean} isMobile Whether this is for the mobile dropdown
     * @returns {HTMLElement} The notification item element
     */
    createNotificationItem(notification, isMobile = false) {
        const item = document.createElement('div');
        item.className = 'notification-item p-3 border-bottom';
        if (!notification.is_read) {
            item.classList.add('unread');
        }
        item.setAttribute('data-id', notification.id);
        
        // Determine icon class based on notification type
        let iconClass = 'fa-info-circle text-info';
        let bgColorClass = 'bg-info-light';
        
        switch (notification.type) {
            case 'success':
                iconClass = 'fa-check-circle text-success';
                bgColorClass = 'bg-success-light';
                break;
            case 'warning':
                iconClass = 'fa-exclamation-triangle text-warning';
                bgColorClass = 'bg-warning-light';
                break;
            case 'danger':
                iconClass = 'fa-times-circle text-danger';
                bgColorClass = 'bg-danger-light';
                break;
        }
        
        // Format date
        const date = new Date(notification.created_at);
        const formattedDate = this.formatDate(date);
        
        // Create notification content
        item.innerHTML = `
            <div class="d-flex">
                <div class="flex-shrink-0">
                    <div class="icon-circle ${bgColorClass} p-2 rounded-circle">
                        <i class="fas ${iconClass}"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fs-sm">${notification.title}</h6>
                        ${!notification.is_read ? '<span class="badge bg-primary rounded-pill ms-2">New</span>' : ''}
                    </div>
                    <p class="mb-1 small text-truncate">${notification.message}</p>
                    <small class="text-muted">${formattedDate}</small>
                </div>
            </div>
        `;
        
        // Add custom styles
        item.style.transition = 'background-color 0.2s ease';
        item.style.cursor = 'pointer';
        
        // Hover effect
        item.addEventListener('mouseenter', () => {
            item.style.backgroundColor = 'rgba(0, 0, 0, 0.03)';
        });
        
        item.addEventListener('mouseleave', () => {
            item.style.backgroundColor = '';
        });
        
        // If notification has a link, make it clickable
        if (notification.link) {
            item.addEventListener('click', () => {
                this.markAsRead(notification.id, () => {
                    window.location.href = notification.link;
                });
            });
        } else {
            // Otherwise just mark as read when clicked
            item.addEventListener('click', () => {
                this.markAsRead(notification.id);
            });
        }
        
        return item;
    }
    
    /**
     * Format a date for display
     * 
     * @param {Date} date Date to format
     * @returns {string} Formatted date string
     */
    formatDate(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else if (diffDays < 7) {
            return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        } else {
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }
    }
    
    /**
     * Mark a notification as read
     * 
     * @param {number} notificationId ID of the notification to mark as read
     * @param {Function} callback Optional callback function to execute after successful action
     */
    markAsRead(notificationId, callback = null) {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            formData.append('csrf_token', csrfToken.getAttribute('content'));
        }
        
        fetch('api/notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Remove notification from both lists
                const items = document.querySelectorAll(`[data-id="${notificationId}"]`);
                items.forEach(item => {
                    item.remove();
                });
                
                // Update the count immediately instead of waiting for the next refresh
                this.fetchUnreadCount();
                
                // Execute callback if provided
                if (callback && typeof callback === 'function') {
                    callback();
                }
                
                // Check if lists are empty and show empty message if needed
                if (this.notificationList && this.notificationList.children.length === 0) {
                    this.notificationList.innerHTML = '<div class="list-group-item text-center py-3"><div class="text-gray-500">No new notifications</div></div>';
                }
                
                if (this.mobileNotificationList && this.mobileNotificationList.children.length === 0) {
                    this.mobileNotificationList.innerHTML = '<div class="list-group-item text-center py-3"><div class="text-gray-500">No new notifications</div></div>';
                }
            } else {
                throw new Error(data.message || 'Failed to mark notification as read');
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
        const formData = new FormData();
        formData.append('action', 'mark_all_read');
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            formData.append('csrf_token', csrfToken.getAttribute('content'));
        } else {
            // If no meta tag, try to get token from another source
            console.error('CSRF token meta tag not found');
            // Display error in notification lists
            const errorMessage = 'CSRF token not found. Please refresh the page.';
            
            if (this.notificationList) {
                this.notificationList.innerHTML = `<div class="list-group-item text-center py-3"><div class="small text-danger">${errorMessage}</div></div>`;
            }
            
            if (this.mobileNotificationList) {
                this.mobileNotificationList.innerHTML = `<div class="list-group-item text-center py-3"><div class="small text-danger">${errorMessage}</div></div>`;
            }
            return;
        }
        
        // Show loading message
        if (this.notificationList) {
            this.notificationList.innerHTML = '<div class="list-group-item text-center py-3"><div class="small text-gray-500">Processing...</div></div>';
        }
        
        if (this.mobileNotificationList) {
            this.mobileNotificationList.innerHTML = '<div class="list-group-item text-center py-3"><div class="small text-gray-500">Processing...</div></div>';
        }
        
        fetch('api/notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Clear both notification lists
                if (this.notificationList) {
                    this.notificationList.innerHTML = '<div class="list-group-item text-center py-3"><div class="text-gray-500">No new notifications</div></div>';
                }
                
                if (this.mobileNotificationList) {
                    this.mobileNotificationList.innerHTML = '<div class="list-group-item text-center py-3"><div class="text-gray-500">No new notifications</div></div>';
                }
                
                // Update the badge count immediately
                this.updateNotificationBadge(0);
                
                // Also fetch the updated count from the server to be safe
                this.fetchUnreadCount();
                
                // Show success message
                console.log('All notifications marked as read successfully');
            } else {
                throw new Error(data.message || 'Failed to mark all notifications as read');
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
            
            // Show error message in notification lists
            const errorMessage = `Error: ${error.message}`;
            
            if (this.notificationList) {
                this.notificationList.innerHTML = `<div class="list-group-item text-center py-3"><div class="small text-danger">${errorMessage}</div></div>`;
            }
            
            if (this.mobileNotificationList) {
                this.mobileNotificationList.innerHTML = `<div class="list-group-item text-center py-3"><div class="small text-danger">${errorMessage}</div></div>`;
            }
        });
    }
}

// Initialize notification manager when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Create global instance for use in other scripts
    window.notificationManager = new NotificationManager();
});