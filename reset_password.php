<?php
session_start();
require_once 'api/config.php';
require_once 'api/db.php';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check reset token validity
$token = filter_var($_GET['token'] ?? '', FILTER_SANITIZE_STRING);
$token_valid = false;
if ($token) {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $token_valid = $result->num_rows > 0;
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza - Reset Password</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/reset_password.css">
</head>
<body>
    <div class="container">
        <div class="left-section">
            <div class="logo">
                <a href="index.php"><img src="assets/images/logo.jpg" alt="Ubiaza Logo"></a>
                <span class="logo-text">Ubiaza</span>
            </div>
            <h1 class="left-title">Create New Password</h1>
            <p class="left-description">
                Your new password must be different from previously used passwords and meet our security requirements.
            </p>
        </div>

        <div class="right-section">
            <div class="form-container">
                <h2 class="page-title">Reset your password</h2>

                <div class="success-message hidden" id="successMessage">
                    <i class="fas fa-check-circle"></i>
                    Your password has been successfully reset! You can now sign in with your new password.
                </div>

                <div class="error-message <?php echo !$token_valid ? '' : 'hidden'; ?>" id="errorMessage">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="errorText"><?php echo !$token_valid ? 'Invalid or expired reset link. Please request a new password reset.' : ''; ?></span>
                </div>

                <form id="resetPasswordForm" class="<?php echo $token_valid ? '' : 'hidden'; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-input" id="newPassword" placeholder="Enter new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                                <i class="fas fa-eye" id="newPasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-input" id="confirmPassword" placeholder="Confirm new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                                <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-requirements">
                        <h4>Password must contain:</h4>
                        <div class="requirement invalid" id="lengthReq">
                            <i class="fas fa-times"></i>
                            At least 8 characters
                        </div>
                        <div class="requirement invalid" id="uppercaseReq">
                            <i class="fas fa-times"></i>
                            One uppercase letter
                        </div>
                        <div class="requirement invalid" id="lowercaseReq">
                            <i class="fas fa-times"></i>
                            One lowercase letter
                        </div>
                        <div class="requirement invalid" id="numberReq">
                            <i class="fas fa-times"></i>
                            One number
                        </div>
                        <div class="requirement invalid" id="specialReq">
                            <i class="fas fa-times"></i>
                            One special character (!@#$%^&*)
                        </div>
                        <div class="requirement invalid" id="matchReq">
                            <i class="fas fa-times"></i>
                            Passwords match
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" id="submitBtn" disabled>
                        <span id="submitText">Reset Password</span>
                        <i class="fas fa-spinner fa-spin hidden" id="loadingSpinner"></i>
                    </button>
                </form>

                <div class="form-footer">
                    Remember your password? <a href="sign.php">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/reset_password.js"></script>
</body>
</html>