<form class="space-y-4" method="POST" action="">
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-exclamation-circle mr-2 mt-1 flex-shrink-0"></i>
                <span class="text-sm"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
    <?php endif; ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-info-circle mr-2 mt-1 flex-shrink-0 text-blue-500"></i>
            <span class="text-sm">A verification code has been sent to <strong><?= htmlspecialchars($otpEmail) ?></strong>. Please enter the code below to verify your email and complete the signup process.</span>
        </div>
    </div>
    <div>
        <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">
            Verification Code
        </label>
        <input
            id="otp"
            name="otp"
            type="text"
            required
            maxlength="6"
            pattern="\\d{6}"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
            placeholder="Enter the verification code"
        />
    </div>
    <input type="hidden" name="otp_email" value="<?= htmlspecialchars($otpEmail) ?>">
    <button
        type="submit"
        name="otp_verify"
        class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 shadow-lg hover:shadow-xl"
    >
        <i class="fas fa-check mr-2"></i>
        Verify and Complete Signup
    </button>
    <div class="text-center">
        <p class="text-xs text-gray-500">
            Didn't receive the code? Check your spam folder or
            <form method="POST" action="" class="inline">
                <input type="hidden" name="otp_email" value="<?= htmlspecialchars($otpEmail) ?>">
                <button 
                    type="submit" 
                    name="resend_otp" 
                    class="text-red-600 hover:underline bg-transparent border-0 p-0 inline font-normal"
                >
                    resend the code
                </button>
            </form>
        </p>
    </div>
</form>
