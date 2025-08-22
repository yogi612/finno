document.addEventListener('DOMContentLoaded', function() {

    function attachSignupHandler() {
        const signupForm = document.getElementById('signup-form');
        const formContainer = document.querySelector('.space-y-6');
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(signupForm);
                formData.append('ajax', '1');

                // Show loader
                const loader = document.getElementById('page-loader');
                if (loader) {
                    loader.style.display = 'flex';
                }

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.html) {
                            formContainer.innerHTML = data.html;
                            initOtpHandlers();
                            attachSignupHandler(); // Re-attach in case signup form is re-rendered
                        } else {
                            // If no OTP form is sent, display a success message and redirect.
                            const successMessage = data.message || 'Signup successful! Your account is pending admin approval.';
                            formContainer.innerHTML = `
                                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                                    <div class="flex items-start">
                                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0 text-green-500"></i>
                                        <span class="text-sm">${successMessage} You will be redirected to the login page in 5 seconds.</span>
                                    </div>
                                </div>
                            `;
                            setTimeout(() => {
                                window.location.href = '/login';
                            }, 5000);
                        }
                    } else {
                        showError(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Hide loader on error
                    const loader = document.getElementById('page-loader');
                    if (loader) {
                        loader.style.display = 'none';
                    }
                });
            });
        }
    }

    attachSignupHandler();

    // Initialize OTP form handlers on page load
    initOtpHandlers();
});

function initOtpHandlers() {
    // Handle OTP verification form (look for a form with an input named 'otp' and a button named 'otp_verify')
    const otpForm = document.querySelector('form input[name="otp"]')?.closest('form');
    if (otpForm && otpForm.querySelector('[name="otp_verify"]')) {
        const verifyButton = otpForm.querySelector('[name="otp_verify"]');
        const otpInput = otpForm.querySelector('#otp');
        // Auto-submit when OTP is 6 digits
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                if (this.value.length === 6) {
                    submitOtp(otpForm, verifyButton);
                }
            });
        }
        otpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitOtp(otpForm, verifyButton);
        });
    }

    // Handle resend OTP form
    // Find the form that contains a button named 'resend_otp'
    const resendForm = Array.from(document.querySelectorAll('form')).find(f => f.querySelector('[name="resend_otp"]'));
    if (resendForm) {
        resendForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(resendForm);
            formData.append('ajax', '1');
            // Always include the resend_otp button's name/value
            const resendButton = resendForm.querySelector('[name="resend_otp"]');
            if (resendButton && resendButton.name) {
                formData.append(resendButton.name, resendButton.value || '');
                resendButton.disabled = true;
                resendButton.textContent = 'sending...';
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Verification code resent successfully!', 'success');
                    } else {
                        showError(data.error);
                    }
                    // Re-enable after 60 seconds
                    setTimeout(() => {
                        resendButton.disabled = false;
                        resendButton.textContent = 'resend the code';
                    }, 60000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    resendButton.disabled = false;
                    resendButton.textContent = 'resend the code';
                });
            }
        });
    }
}


function submitOtp(form, button) {
    const formData = new FormData(form);
    formData.append('ajax', '1');
    // Ensure the submit button's name/value is included (for PHP: isset($_POST['otp_verify']))
    if (button && button.name) {
        formData.append(button.name, button.value || '');
    }
    // Disable button and show loading
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verifying...';

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.innerHTML = '<i class="fas fa-check mr-2"></i>Success!';
            showMessage('Verification successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 500);
        } else {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-check mr-2"></i>Verify and Complete Signup';
            showError(data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-check mr-2"></i>Verify and Complete Signup';
        showError('Network error. Please try again.');
    });
}

function showError(message) {
    const form = document.querySelector('form');
    if (!form) return;

    let errorDiv = form.querySelector('.error-message-container');

    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message-container bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg';
        form.insertBefore(errorDiv, form.firstChild);
    }

    errorDiv.innerHTML = `
        <div class="flex items-start">
            <i class="fas fa-exclamation-circle mr-2 mt-1 flex-shrink-0"></i>
            <span class="text-sm">${message}</span>
        </div>
    `;
}

function showMessage(message, type = 'info') {
    const colors = {
        success: 'green',
        info: 'blue',
        warning: 'yellow'
    };
    const color = colors[type] || 'blue';

    const messageDiv = document.createElement('div');
    messageDiv.className = `bg-${color}-50 border border-${color}-200 text-${color}-700 px-4 py-3 rounded-lg`;
    messageDiv.innerHTML = `
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-2 mt-1 flex-shrink-0 text-${color}-500"></i>
            <span class="text-sm">${message}</span>
        </div>
    `;

    const form = document.querySelector('form');
    if (form) {
        const existingMessage = form.querySelector(`.bg-${color}-50`);
        if (existingMessage) {
            existingMessage.remove();
        }
        form.insertBefore(messageDiv, form.firstChild);
    }
}
