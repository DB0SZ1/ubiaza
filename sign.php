
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
    $_SESSION['registration_start_time'] = time();
    $logger->info("CSRF token generated for session: " . session_id() . ", token: " . $_SESSION['csrf_token'] . ", IP: " . $_SERVER['REMOTE_ADDR']);
} else {
    $logger->info("CSRF token already exists for session: " . session_id() . ", token: " . $_SESSION['csrf_token'] . ", IP: " . $_SERVER['REMOTE_ADDR']);
}

// Check registration timeout (30 minutes)
if (isset($_SESSION['registration_start_time']) && (time() - $_SESSION['registration_start_time']) > 1800) {
    $logger->info("Registration timeout for session: " . session_id());
    $_SESSION['registration_data'] = null;
    $_SESSION['registration_start_time'] = time();
}

// Initialize registration data in session if not exists
if (!isset($_SESSION['registration_data'])) {
    $_SESSION['registration_data'] = [
        'firstName' => '',
        'lastName' => '',
        'email' => '',
        'phone' => '',
        'password' => '',
        'verificationType' => '',
        'bvnMethod' => '',
        'bvnValue' => null,
        'ninMethod' => '',
        'ninValue' => '',
        'ninFiles' => ['front' => null, 'back' => null]
    ];
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
    <title>Ubiaza - Sign In / Register</title>
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
            <h1 class="left-title" id="left-title">Join Ubiaza Today</h1>
            <p class="left-description" id="left-description">
                Create your account to start sending money internationally with competitive rates and fast transfers.
            </p>
        </div>

        <div class="right-section">
            <!-- Registration Step 1 -->
            <div class="form-container" id="register-step1">
                <h2 class="page-title">Create your account</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step active">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <form id="personalInfoForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-input" id="firstName" placeholder="First Name" required pattern="[A-Za-z\s]{2,50}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-input" id="lastName" placeholder="Last Name" required pattern="[A-Za-z\s]{2,50}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-input" id="phone" placeholder="Enter your phone number" required pattern="\+?\d{10,15}">
                    </div>

                    <button type="submit" class="btn-primary">
                        <span>Continue</span>
                        <i class="fas fa-spinner hidden"></i>
                    </button>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 2 -->
            <div class="form-container hidden" id="register-step2">
                <h2 class="page-title">Create your account</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <form id="securityForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-input" placeholder="Create a password" id="password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="password-requirements">
                            <p id="lengthReq" class="invalid"><i class="fas fa-times"></i> At least 8 characters</p>
                            <p id="uppercaseReq" class="invalid"><i class="fas fa-times"></i> At least one uppercase letter</p>
                            <p id="lowercaseReq" class="invalid"><i class="fas fa-times"></i> At least one lowercase letter</p>
                            <p id="numberReq" class="invalid"><i class="fas fa-times"></i> At least one number</p>
                            <p id="specialReq" class="invalid"><i class="fas fa-times"></i> At least one special character</p>
                            <p id="matchReq" class="invalid"><i class="fas fa-times"></i> Passwords must match</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-input" placeholder="Confirm your password" id="confirmPassword" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="showStep1()">Back</button>
                        <button type="submit" class="btn-primary" disabled id="securityContinue">
                            <span>Continue</span>
                            <i class="fas fa-spinner hidden"></i>
                        </button>
                    </div>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 3 - Verification Type Selection -->
            <div class="form-container hidden" id="register-step3-type">
                <h2 class="page-title">Verify your identity</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Choose Verification Method</label>
                    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                        Select either BVN or NIN to verify your identity
                    </p>
                </div>

                <div class="verification-options">
                    <div class="verification-option" onclick="showBvnOptions()">
                        <i class="fas fa-id-card"></i>
                        <h4>BVN Verification</h4>
                        <p>Verify using your Bank Verification Number</p>
                    </div>
                    <div class="verification-option" onclick="showNinOptions()">
                        <i class="fas fa-file-upload"></i>
                        <h4>NIN Verification</h4>
                        <p>Verify using your National Identity Number</p>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button type="button" class="btn-secondary" onclick="showStep2()">Back</button>
                    <button type="button" class="btn-tertiary" onclick="skipVerification()">
                        <span>Skip for now</span>
                    </button>
                </div>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 3 - BVN Options -->
            <div class="form-container hidden" id="register-step3-bvn-options">
                <h2 class="page-title">BVN Verification</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Bank Verification Number (BVN)</label>
                    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">
                        Choose how you'd like to provide your BVN for identity verification
                    </p>
                </div>

                <div class="bvn-options">
                    <div class="bvn-option" onclick="selectBvnOption('input')">
                        <i class="fas fa-keyboard"></i>
                        <h4>Enter BVN</h4>
                        <p>Type your 11-digit BVN number</p>
                    </div>
                    <div class="bvn-option" onclick="selectBvnOption('upload')">
                        <i class="fas fa-upload"></i>
                        <h4>Upload Document</h4>
                        <p>Upload your BVN slip or statement</p>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button type="button" class="btn-secondary" onclick="showVerificationType()">Back</button>
                    <button type="button" class="btn-tertiary" onclick="skipVerification()">
                        <span>Skip for now</span>
                    </button>
                </div>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 3 - BVN Input/Upload -->
            <div class="form-container hidden" id="register-step3-bvn-details">
                <h2 class="page-title">BVN Verification</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <form id="bvnForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group hidden" id="bvn-input-section">
                        <label class="form-label">BVN Number</label>
                        <input type="text" class="form-input" id="bvnNumber" name="bvn" placeholder="Enter your 11-digit BVN" maxlength="11" pattern="\d{11}" required>
                    </div>

                    <div class="form-group hidden" id="bvn-upload-section">
                        <div class="upload-area" onclick="document.getElementById('bvnFile').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload BVN document</p>
                            <small>Supported formats: PDF, JPG, PNG (Max 5MB)</small>
                        </div>
                        <input type="file" id="bvnFile" name="bvn_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" onchange="handleBvnFileUpload(this)">
                        <div id="bvn-uploaded-files"></div>
                    </div>

                    <div class="form-actions" style="margin-top: 30px;">
                        <button type="button" class="btn-secondary" onclick="showBvnOptions()">Back</button>
                        <button type="button" class="btn-tertiary" onclick="skipVerification()">
                            <span>Skip for now</span>
                        </button>
                        <button type="submit" class="btn-primary" disabled id="bvn-continue-btn">
                            <span>Verify</span>
                            <i class="fas fa-spinner hidden"></i>
                        </button>
                    </div>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 3 - NIN Options -->
            <div class="form-container hidden" id="register-step3-nin-options">
                <h2 class="page-title">NIN Verification</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">National Identity Number (NIN)</label>
                    <p style="font-size: 12px; color: #666; margin-bottom: 20px;">
                        Choose how you'd like to upload your NIN document
                    </p>
                </div>

                <div class="nin-options">
                    <div class="nin-option" onclick="selectNinOption('camera')">
                        <i class="fas fa-camera"></i>
                        <h4>Take Photo</h4>
                        <p>Use your camera to capture both sides of your NIN</p>
                    </div>
                    <div class="nin-option" onclick="selectNinOption('upload')">
                        <i class="fas fa-file-upload"></i>
                        <h4>Upload Files</h4>
                        <p>Upload images of your NIN front and back</p>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button type="button" class="btn-secondary" onclick="showVerificationType()">Back</button>
                    <button type="button" class="btn-tertiary" onclick="skipVerification()">
                        <span>Skip for now</span>
                    </button>
                </div>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 3 - NIN Camera/Upload -->
            <div class="form-container hidden" id="register-step3-nin-details">
                <h2 class="page-title">NIN Verification</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line"></div>
                    <div class="step-wrapper">
                        <div class="step inactive">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <form id="ninForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label">NIN Number</label>
                        <input type="text" class="form-input" id="ninNumber" name="nin" placeholder="Enter your 11-digit NIN" maxlength="11" pattern="\d{11}" required>
                    </div>
                    <div class="form-group hidden" id="nin-camera-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">NIN Front</label>
                                <div class="upload-area" onclick="document.getElementById('ninFrontCamera').click()">
                                    <i class="fas fa-camera"></i>
                                    <p>Capture NIN Front</p>
                                    <small>Take a clear photo</small>
                                </div>
                                <input type="file" id="ninFrontCamera" name="nin_front" class="file-input" accept="image/*" capture="environment" onchange="handleNinFileUpload(this, 'front')">
                                <div id="nin-front-camera-files"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">NIN Back</label>
                                <div class="upload-area" onclick="document.getElementById('ninBackCamera').click()">
                                    <i class="fas fa-camera"></i>
                                    <p>Capture NIN Back</p>
                                    <small>Take a clear photo</small>
                                </div>
                                <input type="file" id="ninBackCamera" name="nin_back" class="file-input" accept="image/*" capture="environment" onchange="handleNinFileUpload(this, 'back')">
                                <div id="nin-back-camera-files"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group hidden" id="nin-upload-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">NIN Front</label>
                                <div class="upload-area" onclick="document.getElementById('ninFront').click()">
                                    <i class="fas fa-file-image"></i>
                                    <p>Upload NIN Front</p>
                                    <small>JPG, PNG (Max 5MB)</small>
                                </div>
                                <input type="file" id="ninFront" name="nin_front" class="file-input" accept=".jpg,.jpeg,.png" onchange="handleNinFileUpload(this, 'front')">
                                <div id="nin-front-files"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">NIN Back</label>
                                <div class="upload-area" onclick="document.getElementById('ninBack').click()">
                                    <i class="fas fa-file-image"></i>
                                    <p>Upload NIN Back</p>
                                    <small>JPG, PNG (Max 5MB)</small>
                                </div>
                                <input type="file" id="ninBack" name="nin_back" class="file-input" accept=".jpg,.jpeg,.png" onchange="handleNinFileUpload(this, 'back')">
                                <div id="nin-back-files"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 30px;">
                        <button type="button" class="btn-secondary" onclick="showNinOptions()">Back</button>
                        <button type="button" class="btn-tertiary" onclick="skipVerification()">
                            <span>Skip for now</span>
                        </button>
                        <button type="submit" class="btn-primary" disabled id="nin-continue-btn">
                            <span>Verify</span>
                            <i class="fas fa-spinner hidden"></i>
                        </button>
                    </div>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Registration Step 4 - Review & Submit -->
            <div class="form-container hidden" id="register-step4">
                <h2 class="page-title">Review your information</h2>
                <div class="step-indicator">
                    <div class="step-wrapper">
                        <div class="step completed">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">2</div>
                        <div class="step-label">Security</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step completed">3</div>
                        <div class="step-label">Verification</div>
                    </div>
                    <div class="step-line completed"></div>
                    <div class="step-wrapper">
                        <div class="step active">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="summary-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Full Name</span>
                        <span class="summary-value" id="summary-name">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Email</span>
                        <span class="summary-value" id="summary-email">-</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Phone</span>
                        <span class="summary-value" id="summary-phone">-</span>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="summary-title">
                        <i class="fas fa-shield-alt"></i>
                        Security
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Password</span>
                        <span class="summary-value">••••••••</span>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="summary-title">
                        <i class="fas fa-id-card"></i>
                        Identity Verification
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Verification Method</span>
                        <span class="summary-value" id="summary-verification">-</span>
                    </div>
                </div>

                <form id="reviewForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group" style="margin-top: 30px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #333;">
                            <input type="checkbox" id="termsCheckbox" required>
                            I agree to the <a href="#" style="color: var(--primary-color);">Terms of Service</a> and <a href="#" style="color: var(--primary-color);">Privacy Policy</a>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="showVerificationType()">Back</button>
                        <button type="submit" class="btn-primary" id="submit-btn" disabled>
                            <span>Create Account</span>
                            <i class="fas fa-spinner hidden"></i>
                        </button>
                    </div>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" onclick="showLogin()">Sign in</a>
                </div>
            </div>

            <!-- Login -->
            <div class="form-container hidden" id="login-form">
                <h2 class="page-title">Sign in to your account</h2>
                <form id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="loginEmail" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-input" id="loginPassword" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('loginPassword')"><i class="fas fa-eye"></i></button>
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
                    Don't have an account? <a href="#" onclick="showRegister()">Create an account</a> | Are you an admin? <a href="admin_login.php">Click here</a>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/sign.js"></script>
</body>
</html>