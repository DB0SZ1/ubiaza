<?php
session_start();
require_once 'db.php';
require_once 'api/config.php';
require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Enable error reporting for debugging (disable display in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Prevent JSON response for direct access
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'This endpoint serves HTML only. Use api/auth.php for API requests.']);
    exit;
}

// Initialize logger
$logger = new Logger('auth');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'auth.log', Logger::INFO));

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $logger->info("CSRF token generated for session: " . session_id() . ", token: " . $_SESSION['csrf_token'] . ", IP: " . $_SERVER['REMOTE_ADDR']);
} else {
    $logger->info("CSRF token already exists for session: " . session_id() . ", token: " . $_SESSION['csrf_token'] . ", IP: " . $_SERVER['REMOTE_ADDR']);
}

// Display error/success messages from query parameters
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Ubiaza - Admin Login</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sign.css">
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="left-section">
            <div class="logo">
                <a href="index.php"><img src="https://via.placeholder.com/150" alt="Ubiaza Logo"></a>
                <span class="logo-text">Ubiaza</span>
            </div>
            <h1 class="left-title">Admin Portal</h1>
            <p class="left-description">
                Sign in to manage users, transactions, and system settings.
            </p>
        </div>

        <div class="right-section">
            <!-- Admin Login -->
            <div class="form-container" id="admin-login-form">
                <h2 class="page-title">Admin Sign In</h2>
                <form id="adminLoginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="adminEmail" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-input" id="adminPassword" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('adminPassword')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span>Sign In</span>
                        <i class="fas fa-spinner hidden"></i>
                    </button>
                </form>

                <div class="form-footer">
                    Return to <a href="sign.php">User Login</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js"></script>
    <script>
        // Utility function to toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.nextElementSibling.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        // Utility function to show error messages
        function showError(message) {
            const errorMap = {
                'Invalid server response format': 'There was a problem processing your request. Please try again.',
                'Invalid email or password': 'Invalid email or password. Please check your credentials.',
                'Invalid CSRF token': 'Your session has expired. Please refresh the page and try again.'
            };
            
            const friendlyMessage = errorMap[message] || message;
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${DOMPurify.sanitize(friendlyMessage)}`;
            const container = document.querySelector('.right-section');
            const existingError = container.querySelector('.error-message');
            if (existingError) existingError.remove();
            container.insertBefore(errorDiv, container.firstChild);
            setTimeout(() => errorDiv.remove(), 5000);
        }

        // Utility function to show success messages
        function showSuccess(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message';
            successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${DOMPurify.sanitize(message)}`;
            const container = document.querySelector('.right-section');
            const existingSuccess = container.querySelector('.success-message');
            if (existingSuccess) existingSuccess.remove();
            container.insertBefore(successDiv, container.firstChild);
            setTimeout(() => successDiv.remove(), 5000);
        }

        // Utility function to toggle loading state
        function toggleLoading(button, isLoading) {
            if (!button) {
                console.error('Button element not found');
                return;
            }
            const text = button.querySelector('span');
            const spinner = button.querySelector('.fa-spinner');
            button.disabled = isLoading;
            if (text) text.classList.toggle('hidden', isLoading);
            if (spinner) spinner.classList.toggle('hidden', !isLoading);
        }

        // API call function
        async function makeApiCall(url, method, data = null) {
            try {
                const options = {
                    method: method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include'
                };
                
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                if (csrfToken) {
                    options.headers['X-CSRF-Token'] = csrfToken;
                }
                
                if (method === 'POST' && data) {
                    options.body = JSON.stringify(data);
                }
                
                const response = await fetch(url, options);
                const text = await response.text();
                
                try {
                    const result = JSON.parse(text);
                    
                    if (result.csrf_token) {
                        document.querySelector('input[name="csrf_token"]').value = result.csrf_token;
                    }
                    
                    if (!response.ok) {
                        if (response.status === 401) {
                            throw new Error('Invalid email or password');
                        }
                        if (response.status === 403) {
                            throw new Error('Invalid CSRF token');
                        }
                        throw new Error(result.error || `HTTP ${response.status}`);
                    }
                    
                    return result;
                } catch (parseError) {
                    if (text.includes('Invalid email or password')) {
                        throw new Error('Invalid email or password');
                    }
                    console.error('Failed to parse JSON response:', text);
                    throw new Error('Invalid server response format');
                }
            } catch (error) {
                console.error('API call failed:', error);
                throw new Error(error.message || 'Network error');
            }
        }

        // Handle form submission
        document.getElementById('adminLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = e.target.querySelector('button[type="submit"]');
            toggleLoading(button, true);
            
            try {
                const data = {
                    action: 'login',
                    is_admin: true, // Indicate admin login
                    email: DOMPurify.sanitize(document.getElementById('adminEmail').value.trim()),
                    password: document.getElementById('adminPassword').value
                };
                
                const result = await makeApiCall('api/auth.php', 'POST', data);
                showSuccess(result.message || 'Login successful');
                window.location.href = 'admin_dashboard.php';
            } catch (error) {
                showError(error.message);
            } finally {
                toggleLoading(button, false);
            }
        });

        // Check session on page load
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                const result = await makeApiCall('api/auth.php?action=get_session', 'GET');
                if (result.data && result.data.admin) {
                    window.location.href = 'admin_dashboard.php';
                }
            } catch (error) {
                console.error('Session check failed:', error);
                // Allow login page to remain if session check fails
            }
        });
    </script>
</body>
</html>