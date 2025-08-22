/**
 * Notifications Module
 * 
 * Provides functionality for real-time notifications and notification management
 */

class NotificationsManager {
  constructor() {
    this.notificationsToggle = document.getElementById('notifications-toggle');
    this.notificationsDropdown = document.getElementById('notifications-dropdown');
    this.notificationsContent = document.getElementById('notifications-content');
    this.unreadCount = 0;
    this.pollingInterval = null;
    
    this.init();
  }
  
  init() {
    if (!this.notificationsToggle || !this.notificationsDropdown) return;
    
    // Toggle dropdown on click
    this.notificationsToggle.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const isOpen = !this.notificationsDropdown.classList.contains('hidden');
      
      // Close all other dropdowns
      document.querySelectorAll('.dropdown-content').forEach(dropdown => {
        if (dropdown !== this.notificationsDropdown) {
          dropdown.classList.add('hidden');
        }
      });
      
      // Toggle current dropdown
      this.notificationsDropdown.classList.toggle('hidden');
      
      if (!isOpen) {
        // Fetch notifications when opening
        this.fetchNotifications();
        
        // Start polling for new notifications
        this.startPolling();
      } else {
        // Stop polling when closing
        this.stopPolling();
      }
    });
    
    // Close when clicking outside
    document.addEventListener('click', (e) => {
      if (!this.notificationsToggle.contains(e.target) && !this.notificationsDropdown.contains(e.target)) {
        this.notificationsDropdown.classList.add('hidden');
        this.stopPolling();
      }
    });
    
    // Initial fetch of unread count
    this.fetchUnreadCount();
    
    // Check for new notifications periodically
    setInterval(() => this.fetchUnreadCount(), 60000); // Every minute
  }
  
  startPolling() {
    if (this.pollingInterval) return;
    
    // Poll for new notifications every 10 seconds
    this.pollingInterval = setInterval(() => {
      if (!this.notificationsDropdown.classList.contains('hidden')) {
        this.fetchNotifications();
      }
    }, 10000);
  }
  
  stopPolling() {
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    }
  }
  
  fetchNotifications() {
    if (!this.notificationsContent) return;
    
    // Show loading state
    this.notificationsContent.innerHTML = `
      <div class="p-4 text-center text-sm text-gray-500">
        <i class="fas fa-spinner fa-spin mr-2"></i> Loading...
      </div>
    `;
    
    // Fetch notifications via AJAX
    fetch('/api/get-notifications.php?limit=5')
      .then(response => response.json())
      .then(data => {
        if (!data.notifications || data.notifications.length === 0) {
          this.notificationsContent.innerHTML = `
            <div class="p-6 text-center">
              <i class="fas fa-bell-slash text-gray-400 text-2xl mb-2"></i>
              <p class="text-sm text-gray-500">No notifications yet</p>
            </div>
          `;
          return;
        }
        
        // Render notifications
        this.renderNotifications(data.notifications);
        this.unreadCount = data.unread_count;
        this.updateUnreadBadge();
      })
      .catch(error => {
        console.error('Error fetching notifications:', error);
        this.notificationsContent.innerHTML = `
          <div class="p-4 text-center text-sm text-red-500">
            <i class="fas fa-exclamation-circle mr-2"></i> Failed to load notifications
          </div>
        `;
      });
  }
  
  fetchUnreadCount() {
    fetch('/api/get-unread-count.php')
      .then(response => response.json())
      .then(data => {
        if (data.count !== undefined) {
          this.unreadCount = data.count;
          this.updateUnreadBadge();
        }
      })
      .catch(error => console.error('Error fetching unread count:', error));
  }
  
  renderNotifications(notifications) {
    this.notificationsContent.innerHTML = '';
    
    notifications.forEach(notification => {
      const item = document.createElement('div');
      item.className = `p-4 hover:bg-gray-50 ${notification.is_read ? '' : 'bg-blue-50'}`;
      
      // Set icon based on notification type
      let iconClass = 'fa-info-circle text-blue-500';
      if (notification.type === 'success') {
        iconClass = 'fa-check-circle text-green-500';
      } else if (notification.type === 'warning') {
        iconClass = 'fa-exclamation-triangle text-yellow-500';
      } else if (notification.type === 'error') {
        iconClass = 'fa-exclamation-circle text-red-500';
      }
      
      item.innerHTML = `
        <div class="flex items-start">
          <i class="fas ${iconClass} mt-1 mr-3"></i>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">
              ${this.escapeHtml(notification.title)}
              ${!notification.is_read ? '<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">New</span>' : ''}
            </p>
            <p class="text-sm text-gray-500 mt-1">${this.escapeHtml(notification.message)}</p>
            <div class="flex justify-between mt-1">
              <span class="text-xs text-gray-400">${this.timeAgo(notification.created_at)}</span>
              <div class="flex space-x-2">
                ${notification.link ? `<a href="${notification.link}" class="text-xs text-blue-600 hover:text-blue-800">View</a>` : ''}
                ${!notification.is_read ? `<a href="#" class="text-xs text-gray-600 hover:text-gray-800 mark-read" data-id="${notification.id}">Mark as read</a>` : ''}
              </div>
            </div>
          </div>
        </div>
      `;
      
      this.notificationsContent.appendChild(item);
      
      // Add event listeners for mark as read buttons
      const markReadBtn = item.querySelector('.mark-read');
      if (markReadBtn) {
        markReadBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.markAsRead(notification.id);
        });
      }
    });
  }
  
  markAsRead(notificationId) {
    fetch('/api/mark-notification-read.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Refresh notifications
        this.fetchNotifications();
        this.fetchUnreadCount();
      }
    })
    .catch(error => console.error('Error marking notification as read:', error));
  }
  
  updateUnreadBadge() {
    const badge = this.notificationsToggle.querySelector('span');
    
    if (this.unreadCount > 0) {
      if (badge) {
        // Update existing badge
        const countSpan = badge.querySelector('span:last-child');
        if (countSpan) {
          countSpan.textContent = this.unreadCount > 9 ? '9+' : this.unreadCount;
        }
      } else {
        // Create new badge
        const newBadge = document.createElement('span');
        newBadge.className = 'absolute top-0 right-0 -mt-1 -mr-1 flex h-4 w-4';
        newBadge.innerHTML = `
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 text-xs text-white flex items-center justify-center">
            ${this.unreadCount > 9 ? '9+' : this.unreadCount}
          </span>
        `;
        this.notificationsToggle.appendChild(newBadge);
      }
    } else if (badge) {
      // Remove badge if no unread notifications
      badge.remove();
    }
  }
  
  timeAgo(timestamp) {
    const now = new Date();
    const date = new Date(timestamp);
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
      return interval === 1 ? '1 year ago' : `${interval} years ago`;
    }
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
      return interval === 1 ? '1 month ago' : `${interval} months ago`;
    }
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
      return interval === 1 ? '1 day ago' : `${interval} days ago`;
    }
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
      return interval === 1 ? '1 hour ago' : `${interval} hours ago`;
    }
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
      return interval === 1 ? '1 minute ago' : `${interval} minutes ago`;
    }
    
    return seconds < 10 ? 'just now' : `${Math.floor(seconds)} seconds ago`;
  }
  
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  window.notificationsManager = new NotificationsManager();
  
  // Also initialize user profile dropdown
  const userDropdownToggle = document.getElementById('user-profile-dropdown');
  const userDropdownMenu = document.getElementById('user-dropdown-menu');
  
  if (userDropdownToggle && userDropdownMenu) {
    userDropdownToggle.querySelector('button').addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      userDropdownMenu.classList.toggle('hidden');
    });
    
    // Close when clicking outside
    document.addEventListener('click', function(e) {
      if (!userDropdownToggle.contains(e.target)) {
        userDropdownMenu.classList.add('hidden');
      }
    });
  }
});