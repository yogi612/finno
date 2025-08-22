/**
 * Document Upload Module
 * 
 * Provides advanced file upload functionality with previews,
 * validation, and progress tracking
 */

class DocumentUploader {
  constructor(uploaderId) {
    this.uploaderElement = document.getElementById(uploaderId);
    if (!this.uploaderElement) return;
    
    this.fileInput = this.uploaderElement.querySelector('input[type="file"]');
    this.previewContainer = this.uploaderElement.querySelector('.file-preview');
    this.dropZone = this.uploaderElement.querySelector('.drop-zone');
    this.uploadButton = this.uploaderElement.querySelector('.upload-button');
    this.progressBar = this.uploaderElement.querySelector('.progress-bar-fill');
    this.statusElement = this.uploaderElement.querySelector('.upload-status');
    
    this.maxFileSize = parseInt(this.uploaderElement.getAttribute('data-max-size') || 10485760); // 10MB default
    this.allowedTypes = (this.uploaderElement.getAttribute('data-allowed-types') || 'image/*,application/pdf').split(',');
    this.uploadUrl = this.uploaderElement.getAttribute('data-upload-url') || '/upload_document.php';
    this.uploadParams = JSON.parse(this.uploaderElement.getAttribute('data-upload-params') || '{}');
    
    this.currentFile = null;
    
    this.initEventListeners();
  }
  
  initEventListeners() {
    // File selection via input
    if (this.fileInput) {
      this.fileInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) {
          this.handleFileSelected(e.target.files[0]);
        }
      });
    }
    
    // Drag and drop functionality
    if (this.dropZone) {
      ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        this.dropZone.addEventListener(eventName, (e) => {
          e.preventDefault();
          e.stopPropagation();
        });
      });
      
      this.dropZone.addEventListener('dragenter', () => {
        this.dropZone.classList.add('border-blue-400', 'bg-blue-50');
      });
      
      this.dropZone.addEventListener('dragleave', () => {
        this.dropZone.classList.remove('border-blue-400', 'bg-blue-50');
      });
      
      this.dropZone.addEventListener('drop', (e) => {
        this.dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          this.handleFileSelected(e.dataTransfer.files[0]);
        }
      });
      
      // Click to browse
      this.dropZone.addEventListener('click', () => {
        this.fileInput?.click();
      });
    }
    
    // Upload button
    if (this.uploadButton) {
      this.uploadButton.addEventListener('click', () => {
        if (this.currentFile) {
          this.uploadFile();
        }
      });
    }
  }
  
  handleFileSelected(file) {
    // Validate file
    if (!this.validateFile(file)) {
      return;
    }
    
    this.currentFile = file;
    
    // Show preview
    this.showFilePreview(file);
    
    // Enable upload button
    if (this.uploadButton) {
      this.uploadButton.disabled = false;
      this.uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    
    // Reset progress
    if (this.progressBar) {
      this.progressBar.style.width = '0%';
    }
    
    // Update status
    if (this.statusElement) {
      this.statusElement.textContent = 'Ready to upload';
      this.statusElement.className = 'upload-status text-sm text-blue-600';
    }
  }
  
  validateFile(file) {
    // Check file size
    if (file.size > this.maxFileSize) {
      this.showError(`File is too large (max ${this.formatFileSize(this.maxFileSize)})`);
      return false;
    }
    
    // Check file type
    let isTypeValid = false;
    for (const type of this.allowedTypes) {
      if (type.trim() === '*' || 
          type.trim() === file.type || 
          (type.trim().endsWith('/*') && file.type.startsWith(type.trim().replace('/*', '')))) {
        isTypeValid = true;
        break;
      }
    }
    
    if (!isTypeValid) {
      this.showError('File type not allowed. Please select an image or PDF.');
      return false;
    }
    
    return true;
  }
  
  showFilePreview(file) {
    if (!this.previewContainer) return;
    
    this.previewContainer.innerHTML = '';
    
    if (file.type.startsWith('image/')) {
      const img = document.createElement('img');
      img.classList.add('max-h-full', 'max-w-full', 'object-contain');
      
      const reader = new FileReader();
      reader.onload = (e) => {
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
      
      this.previewContainer.appendChild(img);
    } else if (file.type === 'application/pdf') {
      const pdfPreview = document.createElement('div');
      pdfPreview.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full">
          <i class="fas fa-file-pdf text-red-500 text-4xl"></i>
          <span class="text-sm mt-2">${file.name}</span>
        </div>
      `;
      this.previewContainer.appendChild(pdfPreview);
    } else {
      const genericPreview = document.createElement('div');
      genericPreview.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full">
          <i class="fas fa-file text-gray-500 text-4xl"></i>
          <span class="text-sm mt-2">${file.name}</span>
        </div>
      `;
      this.previewContainer.appendChild(genericPreview);
    }
  }
  
  uploadFile() {
    if (!this.currentFile) return;
    
    const formData = new FormData();
    formData.append('document_file', this.currentFile);
    
    // Add additional parameters
    Object.entries(this.uploadParams).forEach(([key, value]) => {
      formData.append(key, value);
    });
    
    // Update UI
    if (this.uploadButton) {
      this.uploadButton.disabled = true;
      this.uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Uploading...';
    }
    
    if (this.statusElement) {
      this.statusElement.textContent = 'Uploading...';
      this.statusElement.className = 'upload-status text-sm text-blue-600';
    }
    
    // Create AJAX request
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        const percentComplete = Math.round((e.loaded / e.total) * 100);
        
        if (this.progressBar) {
          this.progressBar.style.width = percentComplete + '%';
        }
        
        if (this.statusElement) {
          this.statusElement.textContent = `Uploading (${percentComplete}%)`;
        }
      }
    });
    
    // Handle completion
    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        // Success
        if (this.progressBar) {
          this.progressBar.style.width = '100%';
        }
        
        if (this.statusElement) {
          this.statusElement.textContent = 'Upload successful';
          this.statusElement.className = 'upload-status text-sm text-green-600';
        }
        
        if (this.uploadButton) {
          this.uploadButton.innerHTML = '<i class="fas fa-check mr-2"></i> Uploaded';
          this.uploadButton.className = 'upload-button px-4 py-2 bg-green-600 text-white rounded';
        }
        
        // Show success message
        this.showSuccess('Document uploaded successfully!');
        
        // Redirect if specified
        const redirectUrl = this.uploaderElement.getAttribute('data-redirect-after');
        if (redirectUrl) {
          setTimeout(() => {
            window.location.href = redirectUrl;
          }, 1500);
        } else {
          // Reset after delay
          setTimeout(() => {
            this.resetUploader();
          }, 3000);
        }
      } else {
        // Error
        this.handleUploadError(xhr);
      }
    });
    
    // Handle errors
    xhr.addEventListener('error', () => {
      this.handleUploadError(xhr);
    });
    
    // Handle timeouts
    xhr.addEventListener('timeout', () => {
      this.showError('Upload timed out. Please try again.');
      this.resetUploadButton();
    });
    
    // Send the request
    xhr.open('POST', this.uploadUrl);
    xhr.timeout = 30000; // 30 seconds
    xhr.send(formData);
  }
  
  handleUploadError(xhr) {
    let errorMessage = 'Upload failed. Please try again.';
    
    try {
      // Try to parse error from response
      const response = JSON.parse(xhr.responseText);
      if (response && response.error) {
        errorMessage = response.error;
      }
    } catch (e) {
      // If can't parse JSON, use status text
      if (xhr.statusText) {
        errorMessage = `Upload failed: ${xhr.statusText}`;
      }
    }
    
    this.showError(errorMessage);
    this.resetUploadButton();
  }
  
  resetUploadButton() {
    if (this.uploadButton) {
      this.uploadButton.disabled = false;
      this.uploadButton.innerHTML = 'Upload File';
      this.uploadButton.className = 'upload-button px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700';
    }
    
    if (this.progressBar) {
      this.progressBar.style.width = '0%';
    }
  }
  
  showError(message) {
    if (this.statusElement) {
      this.statusElement.textContent = message;
      this.statusElement.className = 'upload-status text-sm text-red-600';
    } else {
      alert(message);
    }
  }
  
  showSuccess(message) {
    // Create success notification
    const notification = document.createElement('div');
    notification.className = 'fixed bottom-4 right-4 bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg z-50 animate-fadeIn';
    notification.innerHTML = `
      <div class="flex items-center">
        <i class="fas fa-check-circle text-green-500 mr-2"></i>
        <p class="text-sm">${message}</p>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove after delay
    setTimeout(() => {
      notification.classList.add('animate-fadeOut');
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 5000);
  }
  
  resetUploader() {
    // Clear file input
    if (this.fileInput) {
      this.fileInput.value = '';
    }
    
    // Clear preview
    if (this.previewContainer) {
      this.previewContainer.innerHTML = '';
    }
    
    // Reset progress
    if (this.progressBar) {
      this.progressBar.style.width = '0%';
    }
    
    // Reset status
    if (this.statusElement) {
      this.statusElement.textContent = 'Select a file to upload';
      this.statusElement.className = 'upload-status text-sm text-gray-500';
    }
    
    // Reset button
    this.resetUploadButton();
    
    // Clear current file
    this.currentFile = null;
  }
  
  formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
}

// Initialize uploaders on page load
document.addEventListener('DOMContentLoaded', function() {
  // Find all uploader elements and initialize
  document.querySelectorAll('[data-document-uploader]').forEach(uploader => {
    const id = uploader.id;
    if (id) {
      new DocumentUploader(id);
    }
  });
  
  // For simple file inputs without full uploader
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
      if (!this.files || !this.files[0]) return;
      
      const file = this.files[0];
      const previewContainer = document.querySelector(`[data-preview-for="${this.id}"]`);
      if (!previewContainer) return;
      
      previewContainer.innerHTML = '';
      
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.classList.add('max-h-full', 'max-w-full', 'object-contain');
        
        const reader = new FileReader();
        reader.onload = (e) => {
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        previewContainer.appendChild(img);
      } else {
        const icon = document.createElement('div');
        icon.classList.add('flex', 'flex-col', 'items-center', 'justify-center');
        
        if (file.type === 'application/pdf') {
          icon.innerHTML = `
            <i class="fas fa-file-pdf text-red-500 text-3xl"></i>
            <span class="text-sm mt-2">${file.name}</span>
          `;
        } else {
          icon.innerHTML = `
            <i class="fas fa-file text-gray-500 text-3xl"></i>
            <span class="text-sm mt-2">${file.name}</span>
          `;
        }
        
        previewContainer.appendChild(icon);
      }
    });
  });
});