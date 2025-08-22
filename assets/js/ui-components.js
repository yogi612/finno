/**
 * UI Components Module
 * 
 * Modern JavaScript for interactive UI components
 * Provides React-like functionality for UI elements
 */

class UIComponentManager {
  constructor() {
    this.components = {};
    this.initComponents();
  }
  
  initComponents() {
    // Initialize all components
    this.initDropdowns();
    this.initModals();
    this.initTabs();
    this.initTooltips();
    this.initSidebar();
    this.initAlerts();
    this.initCollapsibles();
    this.initFileUploads();
    
    // Add resize and scroll event listeners
    window.addEventListener('resize', this.handleResize.bind(this));
    window.addEventListener('scroll', this.handleScroll.bind(this));
  }
  
  // Dropdown menus
  initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
      const trigger = dropdown.querySelector('.dropdown-trigger');
      const menu = dropdown.querySelector('.dropdown-menu');
      
      if (!trigger || !menu) return;
      
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns
        document.querySelectorAll('.dropdown.active').forEach(d => {
          if (d !== dropdown) d.classList.remove('active');
        });
        
        // Toggle current dropdown
        dropdown.classList.toggle('active');
      });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown.active').forEach(d => {
          d.classList.remove('active');
        });
      }
    });
  }
  
  // Modal dialogs
  initModals() {
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    
    modalTriggers.forEach(trigger => {
      const modalId = trigger.getAttribute('data-modal-target');
      const modal = document.getElementById(modalId);
      
      if (!modal) return;
      
      // Open modal
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        
        // Add fade-in animation
        modal.querySelector('.modal-content')?.classList.add('zoom-in');
        
        // Focus first input if present
        setTimeout(() => {
          modal.querySelector('input')?.focus();
        }, 100);
      });
      
      // Close modal buttons
      modal.querySelectorAll('.modal-close, .modal-cancel').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
          modal.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        });
      });
      
      // Close when clicking backdrop
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        }
      });
      
      // Close with Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
          modal.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        }
      });
    });
  }
  
  // Tabs
  initTabs() {
    const tabGroups = document.querySelectorAll('.tabs');
    
    tabGroups.forEach(tabGroup => {
      const tabs = tabGroup.querySelectorAll('.tab');
      
      tabs.forEach(tab => {
        tab.addEventListener('click', function() {
          const target = this.getAttribute('data-tab-target');
          
          // Update active state on tabs
          tabGroup.querySelectorAll('.tab').forEach(t => {
            t.classList.remove('active');
          });
          this.classList.add('active');
          
          // Show corresponding content
          const tabContents = document.querySelectorAll('.tab-content');
          tabContents.forEach(content => {
            content.classList.add('hidden');
          });
          
          document.getElementById(target)?.classList.remove('hidden');
        });
      });
    });
    
    // Activate first tab by default if none active
    tabGroups.forEach(group => {
      if (!group.querySelector('.tab.active')) {
        group.querySelector('.tab')?.click();
      }
    });
  }
  
  // Tooltips
  initTooltips() {
    const tooltips = document.querySelectorAll('.tooltip');
    
    tooltips.forEach(tooltip => {
      const content = tooltip.getAttribute('data-tooltip');
      if (!content) return;
      
      // Create tooltip element
      const tooltipEl = document.createElement('div');
      tooltipEl.className = 'tooltip-content';
      tooltipEl.textContent = content;
      tooltip.appendChild(tooltipEl);
      
      // Position tooltip (top by default)
      const position = tooltip.getAttribute('data-tooltip-position') || 'top';
      tooltip.classList.add(`tooltip-${position}`);
    });
  }
  
  // Sidebar navigation
  initSidebar() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    // Use a more flexible selector for desktop sidebar
    const desktopSidebar = document.getElementById('desktop-sidebar') || document.querySelector('.w-64.flex-col');

    // Remove 'hidden' class from desktop sidebar on desktop
    function updateDesktopSidebarVisibility() {
      if (!desktopSidebar) return;
      if (window.innerWidth < 1024) {
        desktopSidebar.style.display = 'none';
      } else {
        desktopSidebar.style.display = '';
        // Removed automatic removal of 'hidden' class
      }
    }

    if (mobileMenuButton && mobileSidebar) {
      mobileMenuButton.setAttribute('aria-controls', 'mobile-sidebar');
      mobileMenuButton.setAttribute('aria-expanded', 'false');
      mobileMenuButton.addEventListener('click', () => {
        const isOpen = mobileSidebar.classList.toggle('open');
        document.body.classList.toggle('sidebar-open', isOpen);
        mobileMenuButton.setAttribute('aria-expanded', isOpen);
        updateDesktopSidebarVisibility();
        // Create overlay if not exists
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
          overlay = document.createElement('div');
          overlay.className = 'sidebar-overlay';
          document.body.appendChild(overlay);
          overlay.addEventListener('click', () => {
            mobileSidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
            overlay.classList.remove('active');
            updateDesktopSidebarVisibility();
            mobileMenuButton.setAttribute('aria-expanded', 'false');
          });
        }
        overlay.classList.toggle('active', isOpen);
        updateDesktopSidebarVisibility();
      });
      // Hide mobile sidebar on navigation (for SPA or anchor links)
      document.querySelectorAll('#mobile-sidebar a').forEach(link => {
        link.addEventListener('click', () => {
          mobileSidebar.classList.remove('open');
          document.body.classList.remove('sidebar-open');
          document.querySelector('.sidebar-overlay')?.classList.remove('active');
          updateDesktopSidebarVisibility();
          mobileMenuButton.setAttribute('aria-expanded', 'false');
        });
      });
      // Also update on resize
      window.addEventListener('resize', updateDesktopSidebarVisibility);
      // Initial state
      updateDesktopSidebarVisibility();
    } else {
      // Always update on resize even if no mobile menu
      window.addEventListener('resize', updateDesktopSidebarVisibility);
      updateDesktopSidebarVisibility();
    }
  }
  
  // Alerts
  initAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
      const dismissBtn = alert.querySelector('.alert-dismiss');
      if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
          alert.classList.add('fade-out');
          setTimeout(() => {
            alert.remove();
          }, 300);
        });
      }
      
      // Auto-dismiss if configured
      const autoDismiss = alert.getAttribute('data-auto-dismiss');
      if (autoDismiss) {
        setTimeout(() => {
          alert.classList.add('fade-out');
          setTimeout(() => {
            alert.remove();
          }, 300);
        }, parseInt(autoDismiss) * 1000);
      }
    });
  }
  
  // Collapsible sections
  initCollapsibles() {
    const collapsibles = document.querySelectorAll('.collapsible-trigger');
    
    collapsibles.forEach(trigger => {
      const target = document.getElementById(trigger.getAttribute('data-collapse-target'));
      if (!target) return;
      
      trigger.addEventListener('click', () => {
        const isCollapsed = target.classList.contains('collapsed');
        
        // Toggle state
        target.classList.toggle('collapsed');
        
        // Set max-height for animation
        if (isCollapsed) {
          target.style.maxHeight = target.scrollHeight + 'px';
          setTimeout(() => {
            target.style.maxHeight = 'none';
          }, 300);
        } else {
          target.style.maxHeight = target.scrollHeight + 'px';
          setTimeout(() => {
            target.style.maxHeight = '0';
          }, 10);
        }
        
        // Update icon if present
        const icon = trigger.querySelector('i.collapse-icon');
        if (icon) {
          icon.classList.toggle('fa-chevron-down');
          icon.classList.toggle('fa-chevron-up');
        }
      });
      
      // Initialize collapsed state
      if (target.classList.contains('collapsed')) {
        target.style.maxHeight = '0';
      }
    });
  }
  
  // File uploads with preview
  initFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
      input.addEventListener('change', function() {
        const previewContainer = document.querySelector(`[data-preview-for="${this.id}"]`);
        if (!previewContainer || !this.files.length) return;
        
        const file = this.files[0];
        
        // Clear previous preview
        previewContainer.innerHTML = '';
        
        // Show preview based on file type
        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.classList.add('max-h-32', 'max-w-full', 'object-contain');
          img.file = file;
          
          previewContainer.appendChild(img);
          
          const reader = new FileReader();
          reader.onload = (function(aImg) { 
            return function(e) { 
              aImg.src = e.target.result; 
            }; 
          })(img);
          
          reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
          const icon = document.createElement('div');
          icon.innerHTML = '<i class="fas fa-file-pdf text-red-500 text-4xl"></i>';
          icon.innerHTML += `<p class="text-sm mt-2">${file.name}</p>`;
          previewContainer.appendChild(icon);
        } else {
          const icon = document.createElement('div');
          icon.innerHTML = '<i class="fas fa-file text-gray-500 text-4xl"></i>';
          icon.innerHTML += `<p class="text-sm mt-2">${file.name}</p>`;
          previewContainer.appendChild(icon);
        }
      });
    });
  }
  
  // Handle window resize events
  handleResize() {
    // Adjust UI elements based on window size
    const isMobile = window.innerWidth < 768;
    
    // Update sidebar behavior
    if (!isMobile) {
      document.getElementById('mobile-sidebar')?.classList.remove('open');
      document.querySelector('.sidebar-overlay')?.classList.remove('active');
      document.body.classList.remove('sidebar-open');
    }
    
    // Adjust other responsive elements
    document.querySelectorAll('.responsive-height').forEach(el => {
      el.style.height = isMobile ? 'auto' : el.getAttribute('data-height') + 'px';
    });
  }
  
  // Handle window scroll events
  handleScroll() {
    // Add/remove 'scrolled' class to header
    const header = document.querySelector('header');
    if (header) {
      if (window.scrollY > 10) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    }
    
    // Handle scroll-to-top button
    const scrollToTopBtn = document.getElementById('scroll-to-top');
    if (scrollToTopBtn) {
      if (window.scrollY > 300) {
        scrollToTopBtn.classList.remove('hidden');
      } else {
        scrollToTopBtn.classList.add('hidden');
      }
    }
  }
}

// Initialize all UI components
const UI = new UIComponentManager();

// Export for global access
window.UI = UI;