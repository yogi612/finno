document.addEventListener('DOMContentLoaded', function() {
    const resendForm = document.querySelector('form[name="resend_otp"]');
    const resendButton = document.querySelector('button[name="resend_otp"]');
    let isLoading = false;

    if (resendForm) {
        resendForm.addEventListener('submit', function(e) {
            if (isLoading) {
                e.preventDefault();
                return;
            }

            isLoading = true;
            resendButton.textContent = 'sending...';
            resendButton.disabled = true;

            // Re-enable after 60 seconds
            setTimeout(() => {
                isLoading = false;
                resendButton.textContent = 'resend the code';
                resendButton.disabled = false;
            }, 60000);
        });
    }
});
