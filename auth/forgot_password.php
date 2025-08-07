<?php
session_start();
require_once 'api/config.php';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza - Forgot Password</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/forgot_password.css">
</head>
<body>
    <div class="container">
        <div class="left-section">
            <div class="logo">
                <a href="index.php"><img src="assets/images/logo.jpg" alt="Ubiaza Logo"></a>
                <span class="logo-text">Ubiaza</span>
            </div>
            <h1 class="left-title">Forgot Your Password?</h1>
            <p class="left-description">
                No worries! Enter your email address and we'll send you a link to reset your password.
            </p>
        </div>

        <div class="right-section">
            <div class="form-container">
                <h2 class="page-title">Reset your password</h2>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>Enter the email address associated with your account and we'll send you a password reset link.</p>
                </div>

                <div class="success-message hidden" id="successMessage">
                    <i class="fas fa-check-circle"></i>
                    Password reset link has been sent to your email address. Please check your inbox and follow the instructions.
                </div>

                <div class="error-message hidden" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText"></span>
                </div>

                <form id="forgotPasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="emailInput" placeholder="Enter your email address" required>
                    </div>

                    <button type="submit" class="btn-primary" id="submitBtn">
                        <span id="submitText">Send Reset Link</span>
                        <i class="fas fa-spinner fa-spin hidden" id="loadingSpinner"></i>
                    </button>
                </form>

                <div class="form-footer">
                    Remember your password? <a href="sign.php">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/forgot_password.js"></script>
</body>
</html>