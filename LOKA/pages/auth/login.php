<?php
/**
 * LOKA - Modern Login Page
 *
 * Sleek, animated login with glassmorphism design
 */

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/?page=dashboard');
}

$errors = [];
$isLocked = false;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email = Security::getInstance()->sanitizeEmail(post('email'));
    $password = post('password'); // Don't sanitize password
    $remember = post('remember') === '1';

    // Validation
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    }

    if (empty($errors)) {
        $auth = new Auth();
        $result = $auth->attempt($email, $password, $remember);

        if ($result['success']) {
            // Get user name for welcome message
            $userName = $_SESSION['user_name'] ?? 'User';
            redirectWith('/?page=dashboard', 'success', 'Welcome back, ' . e($userName) . '!');
        } else {
            $errors[] = $result['error'];
            $isLocked = $result['locked'] ?? false;
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ASSETS_PATH ?>/css/style.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body.login-page {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Modern Gradient Background */
        .login-wrapper {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
            padding: 20px;
            overflow: hidden;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Subtle Background Pattern */
        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Logo Container */
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: translateY(-3px) scale(1.05);
        }

        /* Typography */
        .app-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 0;
            font-weight: 400;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            font-size: 0.95rem;
            color: #1a202c;
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control:focus {
            background: #ffffff;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: #a0aec0;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-control:focus ~ .input-icon {
            color: #667eea;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #718096;
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.2s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        /* Remember Me */
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
            accent-color: #667eea;
        }

        .form-check-label {
            font-size: 0.875rem;
            color: #4a5568;
            cursor: pointer;
            user-select: none;
        }

        .forgot-link {
            font-size: 0.875rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 0.875rem;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .spinner-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alerts */
        .alert-modern {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            border-radius: 10px;
            color: #c53030;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            animation: slideDown 0.3s ease-out;
        }

        .alert-modern .alert-success {
            background: #f0fff4;
            border-color: #9ae6b4;
            color: #22543d;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger-modern {
            background: #fff5f5;
            border-color: #feb2b2;
            color: #c53030;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            color: #718096;
            font-size: 0.8rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        /* Demo Credentials Card */
        .demo-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .demo-card strong {
            color: #2d3748;
            font-size: 0.875rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .demo-badges {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .demo-badge {
            padding: 0.375rem 0.75rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #4a5568;
            font-family: 'Courier New', monospace;
        }

        /* Card Body Padding */
        .card-body {
            padding: 2.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-body {
                padding: 2rem 1.5rem;
            }

            .app-name {
                font-size: 1.5rem;
            }

            .login-wrapper {
                padding: 15px;
            }
        }

        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            .login-wrapper,
            .glass-card,
            .demo-card {
                animation: none;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-wrapper">
        <!-- Login Card -->
        <div class="glass-card">
            <!-- Loading Overlay -->
            <div class="spinner-overlay" id="loadingOverlay">
                <div class="loading-spinner"></div>
            </div>

            <!-- Card Content -->
            <div class="card-body">
                <!-- Logo and Header -->
                <div class="logo-container">
                    <div class="logo">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h1 class="app-name"><?= APP_NAME ?></h1>
                    <p class="login-subtitle">Welcome back! Please sign in to continue.</p>
                </div>

                <?php if ($flash = getFlash()): ?>
                <div class="alert alert-modern alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                        <?= e($flash['message']) ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-modern alert-danger-modern">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Login Failed</strong>
                    </div>
                    <ul class="mb-0 ps-4" style="list-style: none; padding-left: 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                        <li style="list-style: disc;"><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <?= csrfField() ?>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div style="position: relative;">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email"
                                   value="<?= e(post('email', '')) ?>"
                                   placeholder="Enter your email"
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div style="position: relative;">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password"
                                   placeholder="Enter your password" 
                                   required>
                            <button type="button" 
                                    class="password-toggle" 
                                    id="togglePassword" 
                                    aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="remember-me">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="remember" 
                                   value="1" 
                                   id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>

                <!-- Footer -->
                <div class="login-footer">
                    <i class="bi bi-shield-check me-1"></i>
                    Secure login with end-to-end encryption
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            // Toggle Password Visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const passwordIcon = togglePassword.querySelector('i');

            togglePassword.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });

            // Form Submit with Loading State
            const loginForm = document.getElementById('loginForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const submitBtn = document.getElementById('submitBtn');

            loginForm.addEventListener('submit', function(e) {
                // Show loading state
                loadingOverlay.classList.add('active');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Signing In...';

                // Allow form to submit normally
                // The loading state will persist until page redirects
            });


            // Remove loading overlay if page stays (error case)
            window.addEventListener('load', function() {
                if (document.querySelector('.alert-danger-modern') || document.querySelector('.alert-modern')) {
                    loadingOverlay.classList.remove('active');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Sign In';
                }
            });
        })();
    </script>
</body>
</html>
