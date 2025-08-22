<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Handle login form submission
$error = null;
$email = '';
$loading = false;
$login_method = $_POST['login_method'] ?? 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $login_method === 'mpin' ? ($_POST['mpin'] ?? '') : ($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);
    $loading = true;

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
        $loading = false;
    } else {
        $loginResult = login($email, $password, $rememberMe, $login_method);
        if (is_array($loginResult) && isset($loginResult['success']) && $loginResult['success']) {
            // Store email in localStorage if remember me is checked
            if ($rememberMe) {
                echo "<script>localStorage.setItem('remember_email', '" . addslashes($email) . "');</script>";
            }
            $profile = getProfile();
            if ($profile && isset($profile['role']) && strtolower($profile['role']) === 'manager') {
                header('Location: /manager/dashboard.php');
                exit;
            } elseif ($profile && isset($profile['role']) && strtolower($profile['role']) === 'admin') {
                header('Location: /admin/dashboard');
                exit;
            } else {
                $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard';
                unset($_SESSION['redirect_after_login']);
                header("Location: $redirect");
                exit;
            }
        } else {
            $error = is_array($loginResult) && isset($loginResult['message']) ? $loginResult['message'] : 'Invalid email or password';
            $loading = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="apple-touch-icon" sizes="76x76" href="/assets/img/apple-icon.png" />
    <link rel="icon" type="image/png" href="/assets/logo.png" />
    <title>Login - DSA Sales Portal</title>
    <!-- Fonts and icons -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Main Styling -->
    <link href="/assets/css/soft-ui-dashboard-tailwind.css?v=1.0.5" rel="stylesheet" />
    <style>
        #email-login-btn, #mpin-login-btn {
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        #email-login-btn.bg-red-500, #mpin-login-btn.bg-red-500 {
            background-color: #f53939 !important;
            color: white !important;
        }
        #email-login-btn.text-gray-700, #mpin-login-btn.text-gray-700 {
            background-color: transparent !important;
            color: #374151 !important;
        }
        .loading-spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            width: 1rem;
            height: 1rem;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>

<body class="m-0 font-sans antialiased font-normal bg-white text-start text-base leading-default text-slate-500">
    <main class="mt-0 transition-all duration-200 ease-soft-in-out">
        <section>
            <div class="relative flex items-center p-0 overflow-hidden bg-center bg-cover min-h-screen">
                <div class="container z-10">
                    <div class="flex flex-wrap mt-0 -mx-3">
                        <div class="flex flex-col w-full max-w-full px-3 mx-auto md:flex-0 shrink-0 md:w-6/12 lg:w-5/12 xl:w-4/12">
                            <div class="relative flex flex-col min-w-0 mt-32 break-words bg-transparent border-0 shadow-none rounded-2xl bg-clip-border">
                                <div class="p-6 pb-0 mb-0 bg-transparent border-b-0 rounded-t-2xl">
                                    <div class="flex justify-center mb-6">
                                         <img src="/assets/logo.png" alt="Finonest Logo" class="h-16 w-16 object-contain">
                                    </div>
                                    <h3 class="relative z-10 font-bold text-center text-transparent bg-gradient-to-tl from-red-600 to-red-400 bg-clip-text">Login to your account</h3>
                                    <p class="mb-0 text-center">Enter your email and password to sign in</p>
                                </div>
                                <div class="flex-auto p-6">
                                    <!-- Login Method Toggle -->
                                    <div class="flex justify-center mb-6">
                                        <div class="relative flex p-1 bg-gray-200 rounded-lg">
                                            <button id="email-login-btn" class="px-4 py-2 text-sm font-medium text-white bg-red-500 rounded-lg focus:outline-none">Email & Password</button>
                                            <button id="mpin-login-btn" class="px-4 py-2 text-sm font-medium text-gray-700 rounded-lg focus:outline-none">MPIN</button>
                                        </div>
                                    </div>

                                    <!-- Email/Password Form -->
                                    <form id="login-form" role="form" method="POST" action="">
                                        <input type="hidden" name="login_method" value="email">
                                        <?php if ($error && $login_method === 'email'): ?>
                                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert">
                                                <p><?= htmlspecialchars($error) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Email Address</label>
                                        <div class="mb-4">
                                            <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-red-300 focus:outline-none focus:transition-shadow" placeholder="Enter your email" aria-label="Email" />
                                        </div>

                                        <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Password</label>
                                        <div class="relative mb-4">
                                            <input type="password" id="password" name="password" required class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-red-300 focus:outline-none focus:transition-shadow" placeholder="Enter your password" aria-label="Password" />
                                            <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600" tabindex="-1">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <input id="remember-me" name="remember_me" type="checkbox" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" <?= !empty($rememberedEmail) ? 'checked' : '' ?>>
                                                <label for="remember-me" class="ml-2 block text-sm text-slate-700">Remember me</label>
                                            </div>
                                            <a href="/auth/reset-password.php" class="text-sm font-semibold text-red-600 hover:text-red-500">Forgot password?</a>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" class="inline-block w-full px-6 py-3 mt-6 mb-0 font-bold text-center text-white uppercase align-middle transition-all bg-transparent border-0 rounded-lg cursor-pointer shadow-soft-md bg-x-25 bg-150 leading-pro text-xs ease-soft-in tracking-tight-soft bg-gradient-to-tl from-red-600 to-red-500 hover:scale-102 hover:shadow-soft-xs active:opacity-85 disabled:opacity-75 disabled:cursor-not-allowed" <?= $loading ? 'disabled' : '' ?>>
                                                <?php if ($loading): ?>
                                                    <div class="loading-spinner inline-block mr-2"></div>
                                                    <span>Signing In...</span>
                                                <?php else: ?>
                                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                                    <span>Sign in</span>
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                    </form>

                                    <!-- MPIN Form -->
                                    <form id="mpin-form" role="form" method="POST" action="" class="hidden">
                                        <input type="hidden" name="login_method" value="mpin">
                                        <?php if ($error && $login_method === 'mpin'): ?>
                                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md" role="alert">
                                                <p><?= htmlspecialchars($error) ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <label class="mb-2 ml-1 font-bold text-xs text-slate-700">Email Address</label>
                                        <div class="mb-4">
                                            <input type="email" id="mpin_email" name="email" required value="<?= htmlspecialchars($email) ?>" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-red-300 focus:outline-none focus:transition-shadow" placeholder="Enter your email" aria-label="Email" />
                                        </div>

                                        <label class="mb-2 ml-1 font-bold text-xs text-slate-700">MPIN</label>
                                        <div class="mb-4">
                                            <input type="password" id="mpin" name="mpin" required maxlength="4" class="focus:shadow-soft-primary-outline text-sm leading-5.6 ease-soft block w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 transition-all focus:border-red-300 focus:outline-none focus:transition-shadow" placeholder="Enter your 4-digit MPIN" aria-label="MPIN" />
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" class="inline-block w-full px-6 py-3 mt-6 mb-0 font-bold text-center text-white uppercase align-middle transition-all bg-transparent border-0 rounded-lg cursor-pointer shadow-soft-md bg-x-25 bg-150 leading-pro text-xs ease-soft-in tracking-tight-soft bg-gradient-to-tl from-red-600 to-red-500 hover:scale-102 hover:shadow-soft-xs active:opacity-85 disabled:opacity-75 disabled:cursor-not-allowed" <?= $loading ? 'disabled' : '' ?>>
                                                <?php if ($loading): ?>
                                                    <div class="loading-spinner inline-block mr-2"></div>
                                                    <span>Signing In...</span>
                                                <?php else: ?>
                                                    <i class="fas fa-sign-in-alt mr-2"></i>
                                                    <span>Sign in with MPIN</span>
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                    </form>

                                    <div class="my-4 text-center">
                                        <span class="text-sm text-slate-400">or</span>
                                    </div>
                                    <a href="/auth/google-login.php" class="inline-block w-full px-6 py-3 font-bold text-center text-white uppercase align-middle transition-all bg-transparent border-0 rounded-lg cursor-pointer shadow-soft-md bg-x-25 bg-150 leading-pro text-xs ease-soft-in tracking-tight-soft bg-gradient-to-tl from-blue-600 to-blue-500 hover:scale-102 hover:shadow-soft-xs active:opacity-85">
                                        <i class="fab fa-google mr-2"></i>
                                        <span>Sign in with Google</span>
                                    </a>
                                </div>
                                <div class="p-6 px-1 pt-0 text-center bg-transparent border-t-0 border-t-solid rounded-b-2xl lg:px-2">
                                    <p class="mx-auto mb-6 leading-normal text-sm">
                                        Don't have an account?
                                        <a href="/signup" class="relative z-10 font-semibold text-transparent bg-gradient-to-tl from-red-600 to-red-400 bg-clip-text">Sign up here</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="w-full max-w-full px-3 lg:flex-0 shrink-0 md:w-6/12">
                            <div class="absolute top-0 hidden w-3/5 h-full -mr-32 overflow-hidden -skew-x-10 -right-40 rounded-bl-xl md:block">
                                <div class="absolute inset-x-0 top-0 z-0 h-full -ml-16 bg-cover skew-x-10" style="background-image: url('/assets/img/curved-images/curved6.jpg')"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <footer class="py-12">
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-8/12 max-w-full px-3 mx-auto mt-1 text-center flex-0">
                    <p class="mb-0 text-slate-400">
                        Copyright © <script>document.write(new Date().getFullYear())</script> Finonest. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password');
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function(e) {
                    e.preventDefault();
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }

            const emailLoginBtn = document.getElementById('email-login-btn');
            const mpinLoginBtn = document.getElementById('mpin-login-btn');
            const loginForm = document.getElementById('login-form');
            const mpinForm = document.getElementById('mpin-form');

            function showEmailForm() {
                loginForm.classList.remove('hidden');
                mpinForm.classList.add('hidden');
                emailLoginBtn.classList.add('bg-red-500', 'text-white');
                emailLoginBtn.classList.remove('text-gray-700');
                mpinLoginBtn.classList.remove('bg-red-500', 'text-white');
                mpinLoginBtn.classList.add('text-gray-700');
            }

            function showMpinForm() {
                loginForm.classList.add('hidden');
                mpinForm.classList.remove('hidden');
                mpinLoginBtn.classList.add('bg-red-500', 'text-white');
                mpinLoginBtn.classList.remove('text-gray-700');
                emailLoginBtn.classList.remove('bg-red-500', 'text-white');
                emailLoginBtn.classList.add('text-gray-700');
            }

            emailLoginBtn.addEventListener('click', showEmailForm);
            mpinLoginBtn.addEventListener('click', showMpinForm);

            // Set initial form based on PHP variable
            const loginMethod = "<?php echo $login_method; ?>";
            if (loginMethod === 'mpin') {
                showMpinForm();
            } else {
                showEmailForm();
            }
        });
    </script>
</body>
</html>
