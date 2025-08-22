/**
 * Authentication Module
 * 
 * Provides functionality for user authentication, signup, login, 
 * password reset, and session management.
 */

class AuthenticationManager {
  constructor() {
    this.initLoginForm();
    this.initSignupForm();
    this.initPasswordReset();
    this.initEmailVerification();
    this.initSessionManager();
  }
  
  initLoginForm() {
    const loginForm = document.getElementById('login-form');
    if (!loginForm) return;
    
    // Password visibility toggle
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('toggle-password');
    
    if (passwordInput && togglePasswordBtn) {
      togglePasswordBtn.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        }
      });
    }
    
    // Remember me functionality
    const rememberMe = document.getElementById('remember-me');
    if (rememberMe) {
      // Check if we have stored email
      const storedEmail = localStorage.getItem('remember_email');
      if (storedEmail) {
        const emailInput = document.getElementById('email');
        if (emailInput) {
          emailInput.value = storedEmail;
          rememberMe.checked = true;
        }
      }
      
      rememberMe.addEventListener('change', function() {
        if (!this.checked) {
          localStorage.removeItem('remember_email');
        }
      });
    }
    
    // Form submission
    loginForm.addEventListener('submit', function(e) {
      // Store email if remember me is checked
      if (rememberMe && rememberMe.checked) {
        const emailInput = document.getElementById('email');
        if (emailInput) {
          localStorage.setItem('remember_email', emailInput.value);
        }
      }
      
      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        // Add loading spinner
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading-spinner loading-spinner-sm mr-2"></div> Signing in...';
        
        // Restore after 10 seconds in case of network issues
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.classList.remove('loading');
          submitBtn.innerHTML = originalText;
        }, 10000);
      }
    });
  }
  
  initSignupForm() {
    const signupForm = document.getElementById('signup-form');
    if (!signupForm) return;
    
    // Password strength meter
    const passwordInput = document.getElementById('password');
    const strengthMeter = document.getElementById('password-strength');
    
    if (passwordInput && strengthMeter) {
      passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = this.calculatePasswordStrength(password);
        
        // Update strength meter
        strengthMeter.style.width = `${strength}%`;
        
        // Update color
        if (strength < 30) {
          strengthMeter.className = 'bg-red-500';
        } else if (strength < 60) {
          strengthMeter.className = 'bg-yellow-500';
        } else {
          strengthMeter.className = 'bg-green-500';
        }
      });
    }
    
    // Password confirmation validation
    const confirmPasswordInput = document.getElementById('confirm-password');
    if (passwordInput && confirmPasswordInput) {
      confirmPasswordInput.addEventListener('input', function() {
        if (passwordInput.value !== this.value) {
          this.setCustomValidity('Passwords do not match');
        } else {
          this.setCustomValidity('');
        }
      });
      
      // Also check when password changes
      passwordInput.addEventListener('input', function() {
        if (confirmPasswordInput.value && confirmPasswordInput.value !== this.value) {
          confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
          confirmPasswordInput.setCustomValidity('');
        }
      });
    }
    
    // Form submission
    signupForm.addEventListener('submit', function(e) {
      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        // Add loading spinner
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading-spinner loading-spinner-sm mr-2"></div> Creating account...';
        
        // Restore after 10 seconds in case of network issues
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.classList.remove('loading');
          submitBtn.innerHTML = originalText;
        }, 10000);
      }
    });
  }
  
  calculatePasswordStrength(password) {
    if (!password) return 0;
    
    // Basic strength calculation
    let strength = 0;
    
    // Length contribution (up to 40%)
    strength += Math.min(password.length * 4, 40);
    
    // Complexity contribution
    if (/[a-z]/.test(password)) strength += 10; // Lowercase
    if (/[A-Z]/.test(password)) strength += 10; // Uppercase
    if (/[0-9]/.test(password)) strength += 10; // Numbers
    if (/[^a-zA-Z0-9]/.test(password)) strength += 10; // Special chars
    
    // Variety contribution (up to 20%)
    const uniqueChars = new Set(password.split('')).size;
    strength += Math.min(uniqueChars * 2, 20);
    
    // Cap at 100%
    return Math.min(strength, 100);
  }
  
  initPasswordReset() {
    const resetForm = document.getElementById('password-reset-form');
    if (!resetForm) return;
    
    resetForm.addEventListener('submit', function(e) {
      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading-spinner loading-spinner-sm mr-2"></div> Sending reset link...';
        
        // Restore after 10 seconds in case of network issues
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.classList.remove('loading');
          submitBtn.innerHTML = originalText;
        }, 10000);
      }
    });
    
    // New password form
    const newPasswordForm = document.getElementById('new-password-form');
    if (newPasswordForm) {
      const passwordInput = document.getElementById('new-password');
      const confirmInput = document.getElementById('confirm-new-password');
      
      if (passwordInput && confirmInput) {
        confirmInput.addEventListener('input', function() {
          if (passwordInput.value !== this.value) {
            this.setCustomValidity('Passwords do not match');
          } else {
            this.setCustomValidity('');
          }
        });
        
        // Also check when password changes
        passwordInput.addEventListener('input', function() {
          if (confirmInput.value && confirmInput.value !== this.value) {
            confirmInput.setCustomValidity('Passwords do not match');
          } else {
            confirmInput.setCustomValidity('');
          }
        });
      }
    }
  }
  
  initEmailVerification() {
    const verificationForm = document.getElementById('verification-form');
    if (!verificationForm) return;
    
    // OTP input handling
    const otpInputs = document.querySelectorAll('.otp-input');
    
    otpInputs.forEach((input, index) => {
      // Handle input
      input.addEventListener('input', function() {
        if (this.value.length === this.maxLength) {
          // Move to next input
          if (index < otpInputs.length - 1) {
            otpInputs[index + 1].focus();
          } else {
            // Submit form if all fields are filled
            const allFilled = Array.from(otpInputs).every(input => input.value.length === input.maxLength);
            if (allFilled) {
              // Small delay to show the complete state
              setTimeout(() => {
                verificationForm.submit();
              }, 300);
            }
          }
        }
      });
      
      // Handle backspace
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value) {
          // Move to previous input
          if (index > 0) {
            otpInputs[index - 1].focus();
          }
        }
      });
    });
    
    // Resend code functionality
    const resendButton = document.getElementById('resend-code');
    if (resendButton) {
      resendButton.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Show loading state
        this.disabled = true;
        this.classList.add('loading');
        const originalText = this.innerHTML;
        this.innerHTML = '<div class="loading-spinner loading-spinner-sm mr-2"></div> Sending...';
        
        // Simulate resending code
        setTimeout(() => {
          this.disabled = false;
          this.classList.remove('loading');
          this.innerHTML = originalText;
          
          // Show success message
          const successMessage = document.createElement('div');
          successMessage.className = 'text-sm text-green-600 mt-2 animate-fadeIn';
          successMessage.textContent = 'New code sent! Please check your email.';
          
          const existingMessage = document.querySelector('.resend-success');
          if (existingMessage) {
            existingMessage.replaceWith(successMessage);
          } else {
            this.parentNode.appendChild(successMessage);
          }
          
          // Remove after delay
          setTimeout(() => {
            successMessage.remove();
          }, 5000);
        }, 2000);
      });
    }
  }
  
  initSessionManager() {
    // Session timeout handling
    const sessionTimeout = parseInt(document.body.getAttribute('data-session-timeout') || '0');
    if (!sessionTimeout) return;
    
    let lastActivity = Date.now();
    let sessionTimer;
    
    // Reset timer on user activity
    const resetTimer = () => {
      lastActivity = Date.now();
    };
    
    // Add event listeners for user activity
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
      document.addEventListener(event, resetTimer, true);
    });
    
    // Check session every minute
    sessionTimer = setInterval(() => {
      const idleTime = (Date.now() - lastActivity) / 1000;
      
      // Warning at 80% of timeout
      const warningThreshold = sessionTimeout * 0.8;
      
      if (idleTime > warningThreshold && idleTime < sessionTimeout) {
        // Show warning
        this.showSessionWarning(Math.floor(sessionTimeout - idleTime));
      } else if (idleTime >= sessionTimeout) {
        // Session expired, log out
        clearInterval(sessionTimer);
        this.expireSession();
      }
    }, 60000); // Check every minute
  }
  
  showSessionWarning(secondsRemaining) {
    // Check if warning already exists
    if (document.getElementById('session-warning')) return;
    
    // Create warning element
    const warning = document.createElement('div');
    warning.id = 'session-warning';
    warning.className = 'fixed bottom-4 right-4 bg-yellow-100 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg shadow-lg z-50 animate-fadeIn';
    
    warning.innerHTML = `
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <i class="fas fa-clock text-yellow-600 mt-1"></i>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium">Your session is about to expire</h3>
          <div class="mt-2 flex items-center">
            <p class="text-sm">You'll be logged out in <span id="timeout-countdown">${Math.floor(secondsRemaining / 60)}:${(secondsRemaining % 60).toString().padStart(2, '0')}</span></p>
          </div>
          <div class="mt-3 flex items-center">
            <button type="button" id="extend-session" class="text-xs bg-yellow-600 text-white px-3 py-1 rounded hover:bg-yellow-700">
              Extend Session
            </button>
            <button type="button" id="logout-now" class="text-xs text-yellow-800 hover:text-yellow-900 underline ml-4">
              Logout Now
            </button>
          </div>
        </div>
        <button type="button" class="ml-auto -mr-1 -mt-1 text-yellow-600 hover:text-yellow-800" id="close-warning">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;
    
    document.body.appendChild(warning);
    
    // Set up countdown
    let countdownTime = secondsRemaining;
    const countdown = document.getElementById('timeout-countdown');
    
    const countdownInterval = setInterval(() => {
      countdownTime--;
      
      if (countdown) {
        const minutes = Math.floor(countdownTime / 60);
        const seconds = countdownTime % 60;
        countdown.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
      }
      
      if (countdownTime <= 0) {
        clearInterval(countdownInterval);
      }
    }, 1000);
    
    // Add event listeners
    document.getElementById('extend-session')?.addEventListener('click', () => {
      clearInterval(countdownInterval);
      this.extendSession();
      warning.remove();
    });
    
    document.getElementById('logout-now')?.addEventListener('click', () => {
      clearInterval(countdownInterval);
      window.location.href = '/logout.php';
    });
    
    document.getElementById('close-warning')?.addEventListener('click', () => {
      clearInterval(countdownInterval);
      warning.remove();
    });
  }
  
  extendSession() {
    // Send request to extend session
    fetch('/extend-session.php', { method: 'POST' })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show success message
          const toast = document.createElement('div');
          toast.className = 'fixed bottom-4 right-4 bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg z-50 animate-fadeIn';
          toast.innerHTML = `
            <div class="flex items-center">
              <i class="fas fa-check-circle text-green-600 mr-2"></i>
              <p class="text-sm">Session extended successfully</p>
            </div>
          `;
          
          document.body.appendChild(toast);
          
          // Remove after delay
          setTimeout(() => {
            toast.classList.add('animate-fadeOut');
            setTimeout(() => {
              toast.remove();
            }, 300);
          }, 3000);
        }
      })
      .catch(error => console.error('Error extending session:', error));
  }
  
  expireSession() {
    // Redirect to login page
    window.location.href = '/logout.php?expired=1';
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  window.authManager = new AuthenticationManager();
});