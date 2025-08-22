/**
 * Applications Management Module
 * 
 * Provides functionality for applications listing, viewing, and management
 * Includes filtering, sorting, and action handling
 */

class ApplicationManager {
  constructor() {
    this.initFormHandlers();
    this.initViewDetailsHandlers();
    this.initFiltersAndSorting();
    this.initUploadHandlers();
    this.initApprovalHandlers();
  }
  
  initFormHandlers() {
    // Application form calculation and validation
    const applicationForm = document.getElementById('application-form');
    if (!applicationForm) return;
    
    // EMI Calculator
    const loanAmountInput = document.getElementById('loanAmount');
    const rateOfInterestInput = document.getElementById('rateOfInterest');
    const tenureMonthsInput = document.getElementById('tenureMonths');
    const emiResult = document.getElementById('emiResult');
    
    // Update EMI calculation on input change
    const calculateEMI = () => {
      const loanAmount = parseFloat(loanAmountInput.value) || 0;
      const rateOfInterest = parseFloat(rateOfInterestInput.value) || 0;
      const tenureMonths = parseInt(tenureMonthsInput.value) || 0;
      
      if (loanAmount && rateOfInterest && tenureMonths) {
        // EMI calculation formula
        const monthlyInterestRate = rateOfInterest / 100 / 12;
        const emi = loanAmount * monthlyInterestRate * Math.pow(1 + monthlyInterestRate, tenureMonths) / 
                   (Math.pow(1 + monthlyInterestRate, tenureMonths) - 1);
        
        if (emiResult) {
          emiResult.textContent = '₹' + Math.round(emi).toLocaleString();
          emiResult.parentElement.classList.remove('hidden');
        }
      } else if (emiResult) {
        emiResult.parentElement.classList.add('hidden');
      }
    };
    
    // Set up event listeners for calculation
    [loanAmountInput, rateOfInterestInput, tenureMonthsInput].forEach(input => {
      if (input) input.addEventListener('input', calculateEMI);
    });
    
    // Handle old HP checkbox
    const oldHPCheckbox = document.getElementById('oldHP');
    const existingLenderField = document.getElementById('existingLenderField');
    
    if (oldHPCheckbox && existingLenderField) {
      oldHPCheckbox.addEventListener('change', function() {
        if (this.checked) {
          existingLenderField.classList.remove('hidden');
          document.getElementById('existingLender').setAttribute('required', '');
        } else {
          existingLenderField.classList.add('hidden');
          document.getElementById('existingLender').removeAttribute('required');
        }
      });
    }
    
    // Initial calculations
    calculateEMI();
    
    // Loading animation and status for application form submission
    const submitBtn = document.getElementById('submit-application');
    const loadingSpinner = document.getElementById('loading-spinner');
    const submissionStatus = document.getElementById('submission-status');

    applicationForm.addEventListener('submit', function(e) {
      if (submitBtn && loadingSpinner) {
        submitBtn.disabled = true;
        loadingSpinner.classList.remove('hidden');
        submissionStatus.textContent = '';
      }
    });

    // Optionally, handle AJAX submission here for dynamic status
    // If using normal POST, PHP will reload and show success/error
    // If using AJAX, update submissionStatus.textContent with result
  }
  
  initViewDetailsHandlers() {
    // Application details view handlers
    document.querySelectorAll('.view-application-btn').forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        
        const applicationId = button.getAttribute('data-id');
        if (applicationId) {
          window.location.href = `/application/view.php?id=${applicationId}`;
        }
      });
    });
    
    // Tab switching in application details
    const tabButtons = document.querySelectorAll('[data-tab-target]');
    tabButtons.forEach(button => {
      button.addEventListener('click', function() {
        const tabTarget = this.getAttribute('data-tab-target');
        
        // Update active tab
        tabButtons.forEach(btn => btn.classList.remove('active-tab'));
        this.classList.add('active-tab');
        
        // Show target content
        document.querySelectorAll('.tab-content').forEach(content => {
          content.classList.add('hidden');
        });
        
        document.getElementById(tabTarget)?.classList.remove('hidden');
      });
    });
  }
  
  initFiltersAndSorting() {
    // Filter and search functionality
    const searchInput = document.querySelector('.application-search-input');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const applications = document.querySelectorAll('.application-item');
        
        applications.forEach(app => {
          const text = app.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            app.classList.remove('hidden');
          } else {
            app.classList.add('hidden');
          }
        });
        
        // Update "no results" message
        const noResults = document.querySelector('.no-results-message');
        if (noResults) {
          const visibleApps = document.querySelectorAll('.application-item:not(.hidden)').length;
          noResults.classList.toggle('hidden', visibleApps > 0);
        }
      });
    }
    
    // Status filters
    document.querySelectorAll('.status-filter').forEach(filter => {
      filter.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Update active filter
        document.querySelectorAll('.status-filter').forEach(f => {
          f.classList.remove('active-filter');
        });
        this.classList.add('active-filter');
        
        const status = this.getAttribute('data-status');
        const applications = document.querySelectorAll('.application-item');
        
        applications.forEach(app => {
          if (status === 'all') {
            app.classList.remove('hidden-by-status');
          } else {
            const appStatus = app.getAttribute('data-status');
            if (appStatus === status) {
              app.classList.remove('hidden-by-status');
            } else {
              app.classList.add('hidden-by-status');
            }
          }
        });
        
        // Update "no results" message
        const noResults = document.querySelector('.no-results-message');
        if (noResults) {
          const visibleApps = document.querySelectorAll('.application-item:not(.hidden):not(.hidden-by-status)').length;
          noResults.classList.toggle('hidden', visibleApps > 0);
        }
      });
    });
    
    // Sorting functionality
    document.querySelectorAll('.sort-header').forEach(header => {
      header.addEventListener('click', function() {
        const sortKey = this.getAttribute('data-sort');
        if (!sortKey) return;
        
        const currentDirection = this.getAttribute('data-sort-direction') || 'asc';
        const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
        
        // Update sort indicators
        document.querySelectorAll('.sort-header').forEach(h => {
          h.removeAttribute('data-sort-direction');
          h.querySelector('.sort-icon')?.classList.add('hidden');
        });
        
        this.setAttribute('data-sort-direction', newDirection);
        
        // Show sort direction icon
        const sortIcon = this.querySelector('.sort-icon');
        if (sortIcon) {
          sortIcon.classList.remove('hidden');
          
          // Update icon based on sort direction
          sortIcon.innerHTML = newDirection === 'asc' 
            ? '<i class="fas fa-sort-up"></i>' 
            : '<i class="fas fa-sort-down"></i>';
        }
        
        // Sort the applications
        this.sortApplications(sortKey, newDirection);
      });
    });
  }
  
  sortApplications(key, direction) {
    const container = document.querySelector('.applications-container');
    const applications = Array.from(container.querySelectorAll('.application-item'));
    
    applications.sort((a, b) => {
      let valueA = a.getAttribute(`data-${key}`) || '';
      let valueB = b.getAttribute(`data-${key}`) || '';
      
      // Handle different data types
      if (key === 'date' || key === 'created-at') {
        valueA = new Date(valueA).getTime();
        valueB = new Date(valueB).getTime();
      } else if (key === 'amount') {
        valueA = parseFloat(valueA.replace(/[^\d.-]/g, '')) || 0;
        valueB = parseFloat(valueB.replace(/[^\d.-]/g, '')) || 0;
      }
      
      // Compare based on direction
      if (direction === 'asc') {
        return valueA > valueB ? 1 : -1;
      } else {
        return valueA < valueB ? 1 : -1;
      }
    });
    
    // Re-append sorted items
    applications.forEach(app => {
      container.appendChild(app);
    });
    
    // Add sorted animation
    applications.forEach(app => {
      app.classList.add('sorted');
      setTimeout(() => {
        app.classList.remove('sorted');
      }, 500);
    });
  }
  
  initUploadHandlers() {
    // Document upload handlers
    document.querySelectorAll('.file-upload-input').forEach(input => {
      input.addEventListener('change', function() {
        if (!this.files || !this.files[0]) return;
        
        const file = this.files[0];
        const previewContainer = document.querySelector(`[data-preview-for="${this.id}"]`);
        if (!previewContainer) return;
        
        // Show preview based on file type
        previewContainer.innerHTML = '';
        
        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.classList.add('max-h-32', 'mx-auto');
          
          const reader = new FileReader();
          reader.onload = function(e) {
            img.src = e.target.result;
          };
          reader.readAsDataURL(file);
          
          previewContainer.appendChild(img);
        } else {
          const icon = document.createElement('div');
          icon.innerHTML = `
            <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
            <p class="text-sm mt-2">${file.name}</p>
          `;
          previewContainer.appendChild(icon);
        }
        
        // Update file name display
        const fileNameDisplay = document.querySelector(`[data-filename-for="${this.id}"]`);
        if (fileNameDisplay) {
          fileNameDisplay.textContent = file.name;
        }
        
        // Enable upload button
        const uploadButton = document.querySelector(`[data-upload-for="${this.id}"]`);
        if (uploadButton) {
          uploadButton.disabled = false;
          uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
      });
    });
  }
  
  initApprovalHandlers() {
    // Application approval/rejection handlers
    document.querySelectorAll('.approve-application-btn').forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        
        const applicationId = button.getAttribute('data-id');
        if (!applicationId) return;
        
        if (confirm('Are you sure you want to approve this application?')) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = `/admin/approve_application.php?id=${applicationId}`;
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
    
    document.querySelectorAll('.reject-application-btn').forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        
        const applicationId = button.getAttribute('data-id');
        if (!applicationId) return;
        
        if (confirm('Are you sure you want to reject this application?')) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = `/admin/reject_application.php?id=${applicationId}`;
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  window.applicationManager = new ApplicationManager();
  
  // Handle tabs specifically for application view page
  const applicationTabs = document.querySelectorAll('[data-tab-id]');
  if (applicationTabs.length > 0) {
    applicationTabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab-id');
        
        // Update active tab
        applicationTabs.forEach(t => {
          t.classList.remove('active-tab');
          document.getElementById(t.getAttribute('data-tab-id'))?.classList.add('hidden');
        });
        
        this.classList.add('active-tab');
        document.getElementById(tabId)?.classList.remove('hidden');
      });
    });
    
    // Activate first tab by default
    if (!document.querySelector('[data-tab-id].active-tab')) {
      applicationTabs[0]?.click();
    }
  }
});