/**
 * Modern Form Validation Module
 * 
 * Provides real-time form validation with visual feedback
 * Matches the behavior of the React version
 */

// Form validation configuration
const validationRules = {
  required: {
    validate: value => value.trim() !== '',
    message: 'This field is required'
  },
  email: {
    validate: value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    message: 'Please enter a valid email address'
  },
  mobileNumber: {
    validate: value => /^[0-9]{10}$/.test(value),
    message: 'Mobile number must be 10 digits'
  },
  password: {
    validate: value => value.length >= 6,
    message: 'Password must be at least 6 characters'
  },
  passwordMatch: {
    validate: (value, formData) => value === formData.password,
    message: 'Passwords do not match'
  },
  rcNumber: {
    validate: value => value.length > 0,
    message: 'RC number is required'
  },
  engineNumber: {
    validate: value => /^[a-zA-Z0-9]{5}$/.test(value),
    message: 'Engine number must be 5 characters'
  },
  chassisNumber: {
    validate: value => /^[a-zA-Z0-9]{5}$/.test(value),
    message: 'Chassis number must be 5 characters'
  },
  numericValue: {
    validate: value => !isNaN(parseFloat(value)) && parseFloat(value) > 0,
    message: 'Please enter a valid number'
  }
};

class FormValidator {
  constructor(formId, options = {}) {
    this.form = document.getElementById(formId);
    if (!this.form) return;
    
    this.options = {
      validateOnBlur: true,
      validateOnChange: true,
      validateOnSubmit: true,
      showSuccessState: false,
      ...options
    };
    
    this.errors = {};
    this.formData = {};
    this.submitButtonSelector = options.submitButton || 'button[type="submit"]';
    
    this.init();
  }
  
  init() {
    // Get all form fields with data-validate attribute
    const fields = this.form.querySelectorAll('[data-validate]');
    
    fields.forEach(field => {
      // Store initial values
      this.formData[field.name] = field.value;
      
      // Add event listeners
      if (this.options.validateOnBlur) {
        field.addEventListener('blur', (e) => this.validateField(e.target));
      }
      
      if (this.options.validateOnChange) {
        field.addEventListener('input', (e) => {
          this.formData[e.target.name] = e.target.value;
          this.validateField(e.target);
          this.updateSubmitButton();
        });
      }
    });
    
    // Handle form submission
    if (this.options.validateOnSubmit) {
      this.form.addEventListener('submit', (e) => {
        const isValid = this.validateForm();
        if (!isValid) {
          e.preventDefault();
          this.focusFirstInvalidField();
        }
      });
    }
  }
  
  validateField(field) {
    const rules = field.getAttribute('data-validate').split(' ');
    let isValid = true;
    let errorMessage = '';
    
    this.formData[field.name] = field.value;
    
    for (const rule of rules) {
      if (validationRules[rule]) {
        const validator = validationRules[rule];
        if (!validator.validate(field.value, this.formData)) {
          isValid = false;
          errorMessage = validator.message;
          break;
        }
      }
    }
    
    this.setFieldValidationState(field, isValid, errorMessage);
    this.errors[field.name] = isValid ? null : errorMessage;
    
    return isValid;
  }
  
  validateForm() {
    const fields = this.form.querySelectorAll('[data-validate]');
    let isFormValid = true;
    
    fields.forEach(field => {
      const isFieldValid = this.validateField(field);
      if (!isFieldValid) {
        isFormValid = false;
      }
    });
    
    return isFormValid;
  }
  
  setFieldValidationState(field, isValid, errorMessage = '') {
    // Remove existing states
    field.classList.remove('border-red-500', 'border-green-500', 'bg-red-50');
    
    const errorContainer = this.getErrorContainer(field);
    
    if (!isValid) {
      // Error state
      field.classList.add('border-red-500', 'bg-red-50');
      if (errorContainer) {
        errorContainer.textContent = errorMessage;
        errorContainer.classList.remove('hidden');
      }
    } else {
      // Success state
      if (this.options.showSuccessState && field.value) {
        field.classList.add('border-green-500');
      }
      if (errorContainer) {
        errorContainer.classList.add('hidden');
      }
    }
  }
  
  getErrorContainer(field) {
    // Look for an error element with data-error-for attribute
    const errorId = field.getAttribute('data-error-for') || `${field.id}-error`;
    return document.getElementById(errorId);
  }
  
  focusFirstInvalidField() {
    const firstInvalidField = this.form.querySelector('.border-red-500');
    if (firstInvalidField) {
      firstInvalidField.focus();
      firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
  
  updateSubmitButton() {
    const submitButton = this.form.querySelector(this.submitButtonSelector);
    if (!submitButton) return;
    
    const hasErrors = Object.values(this.errors).some(error => error !== null);
    const isComplete = this.form.querySelectorAll('[data-validate]:not([data-optional])').length === 
                       this.form.querySelectorAll('[data-validate]:not([data-optional]):valid').length;
    
    submitButton.disabled = hasErrors || !isComplete;
    
    if (hasErrors || !isComplete) {
      submitButton.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
      submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }
  }
  
  reset() {
    this.errors = {};
    this.form.reset();
    
    // Reset all field states
    const fields = this.form.querySelectorAll('[data-validate]');
    fields.forEach(field => {
      this.formData[field.name] = '';
      this.setFieldValidationState(field, true);
    });
    
    this.updateSubmitButton();
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  // Initialize validation for forms with data-validate-form attribute
  document.querySelectorAll('[data-validate-form]').forEach(form => {
    new FormValidator(form.id, {
      showSuccessState: form.getAttribute('data-show-success') === 'true'
    });
  });
  
  // Other validation-related setup
  setupPasswordVisibilityToggles();
  setupOTPInputs();
});

// Password visibility toggle
function setupPasswordVisibilityToggles() {
  document.querySelectorAll('.password-toggle').forEach(toggleButton => {
    toggleButton.addEventListener('click', function() {
      const targetInput = document.querySelector(this.getAttribute('data-target'));
      if (!targetInput) return;
      
      const isPassword = targetInput.type === 'password';
      targetInput.type = isPassword ? 'text' : 'password';
      
      // Toggle icon
      const icon = this.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      }
    });
  });
}

// OTP Input handling
function setupOTPInputs() {
  const otpContainers = document.querySelectorAll('.otp-container');
  
  otpContainers.forEach(container => {
    const inputs = container.querySelectorAll('input');
    
    inputs.forEach((input, index) => {
      // Handle digit input
      input.addEventListener('input', function() {
        if (this.value.length === 1) {
          // Move to next input
          if (index < inputs.length - 1) {
            inputs[index + 1].focus();
          }
        }
      });
      
      // Handle backspace
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && index > 0) {
          inputs[index - 1].focus();
        }
      });
    });
    
    // Auto-submit when all fields are filled
    inputs[inputs.length - 1].addEventListener('input', function() {
      const allFilled = Array.from(inputs).every(input => input.value.length === 1);
      if (allFilled && container.closest('form')) {
        // Wait a bit to show the completed state before submitting
        setTimeout(() => {
          container.closest('form').submit();
        }, 300);
      }
    });
  });
}