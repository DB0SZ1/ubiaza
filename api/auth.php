<?php
require_once 'session.php';
require_once 'config.php';
require_once '../db.php';
require_once '../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ob_start();

SessionManager::startSession();

header('Content-Type: application/json');

$logger = new Logger('auth');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'auth.log', Logger::INFO));

$ip = $_SERVER['REMOTE_ADDR'];
$logger->info("Session ID: " . session_id() . ", IP: $ip");

if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
if (!is_writable(LOG_DIR)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Log directory is not writable', 'code' => 500]);
    $logger->error("Log directory is not writable: " . LOG_DIR);
    ob_end_flush();
    exit;
}

function sendError($message, $code = 400, $logger, $details = []) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'error' => $message, 'details' => $details, 'code' => $code]);
    $logger->warning("Error response: $message, code: $code, details: " . json_encode($details) . ", IP: {$_SERVER['REMOTE_ADDR']}");
    ob_end_flush();
    exit;
}

function sendSuccess($message, $data = [], $logger, $csrf_token = null) {
    $response = ['status' => 'success', 'message' => $message, 'data' => $data, 'code' => 200];
    if ($csrf_token) {
        $response['csrf_token'] = $csrf_token;
    }
    echo json_encode($response);
    $logger->info("Success response: $message, IP: {$_SERVER['REMOTE_ADDR']}");
    ob_end_flush();
}

function validatePassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/\d/', $password) &&
           preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password);
}

function enforceRateLimit($ip, $action, $logger, $rate_limit = 5, $time_window = 60) {
    $safe_ip = str_replace(':', '_', $ip);
    $cache_file = LOG_DIR . "rate_limit_auth_{$safe_ip}_{$action}.json";
    $lock_file = $cache_file . '.lock';

    $fp = fopen($lock_file, 'w+');
    if (!$fp) {
        sendError('Failed to create lock file', 500, $logger);
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        sendError('Rate limiting error', 500, $logger);
    }

    $data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
    $count = isset($data['count']) && $data['time'] > time() - $time_window ? $data['count'] + 1 : 1;

    if ($count > $rate_limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        sendError('Too many requests', 429, $logger);
    }

    file_put_contents($cache_file, json_encode(['time' => time(), 'count' => $count]));
    flock($fp, LOCK_UN);
    fclose($fp);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = $_GET;
    $action = $input['action'] ?? '';
    
    if ($action === 'get_session') {
        if (!isset($_SESSION['registration_start_time']) || (time() - $_SESSION['registration_start_time']) > 1800) {
            $_SESSION['registration_data'] = null;
            $_SESSION['registration_start_time'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $logger->info("Session timeout, new CSRF token generated: " . $_SESSION['csrf_token']);
            sendSuccess('Session data retrieved', ['user' => []], $logger, $_SESSION['csrf_token']);
        }
        $user_data = $_SESSION['registration_data'] ?? [];
        if (isset($_SESSION['user_id']) && isset($_SESSION['user'])) {
            $user_data = array_merge($user_data, $_SESSION['user']);
        }
        sendSuccess('Session data retrieved', ['user' => $user_data], $logger, $_SESSION['csrf_token']);
        exit;
    }
}

// Handle POST requests
$input = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($content_type, 'application/json') !== false) {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true) ?: [];
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON payload', 400, $logger, ['json_error' => json_last_error_msg()]);
    }
} else {
    $input = $_POST;
}

// CSRF token handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for CSRF token in headers first, then in POST data
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $logger->error("CSRF token mismatch - Session: " . ($_SESSION['csrf_token'] ?? 'NULL') . 
                      ", Received: $csrf_token");
        sendError('Invalid CSRF token', 403, $logger, ['sent_token' => $csrf_token]);
    }
    
    // Only regenerate token if the current one was used successfully
    $new_csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $new_csrf_token;
    $logger->info("CSRF token regenerated for session: " . session_id());
} else {
    $new_csrf_token = $_SESSION['csrf_token'] ?? '';
}

$action = $_GET['action'] ?? $input['action'] ?? '';
enforceRateLimit($ip, $action, $logger, $action === 'login' ? 3 : 5);

try {
    $db = new Database();
    $conn = $db->getConnection();

    switch ($action) {
        case 'check_existing':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405, $logger);
            }
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $phone = filter_var($input['phone'] ?? '', FILTER_SANITIZE_STRING);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendError('Invalid email format', 400, $logger);
            }
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                sendError('Invalid phone number format', 400, $logger);
            }

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->bind_param("ss", $email, $phone);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                sendError('Email or phone already exists', 409, $logger);
            }
            sendSuccess('Email and phone are available', [], $logger, $new_csrf_token);
            break;

        case 'store_session_data':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405, $logger);
            }
            $registration_data = $input['registration_data'] ?? null;
            if (!$registration_data) {
                sendError('No registration data provided', 400, $logger);
            }

            // Validate all fields
            $errors = [];
            if (isset($registration_data['firstName']) && !preg_match('/^[A-Za-z\s]{2,50}$/', $registration_data['firstName'])) {
                $errors[] = 'Invalid first name';
            }
            if (isset($registration_data['lastName']) && !preg_match('/^[A-Za-z\s]{2,50}$/', $registration_data['lastName'])) {
                $errors[] = 'Invalid last name';
            }
            if (isset($registration_data['email']) && !filter_var($registration_data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email';
            }
            if (isset($registration_data['phone']) && !preg_match('/^\+?\d{10,15}$/', $registration_data['phone'])) {
                $errors[] = 'Invalid phone number';
            }
            if (isset($registration_data['password']) && !validatePassword($registration_data['password'])) {
                $errors[] = 'Password does not meet requirements';
            }

            if (!empty($errors)) {
                sendError(implode(', ', $errors), 400, $logger);
            }

            $_SESSION['registration_data'] = array_merge($_SESSION['registration_data'] ?? [], $registration_data);
            $_SESSION['registration_start_time'] = time();
            sendSuccess('Registration data stored', [], $logger, $new_csrf_token);
            break;

        case 'clear_session_data':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405, $logger);
            }
            $_SESSION['registration_data'] = [
                'firstName' => '',
                'lastName' => '',
                'email' => '',
                'phone' => '',
                'password' => '',
                'verificationType' => '',
                'bvnMethod' => '',
                'bvnValue' => null,
                'bvnSessionId' => null,
                'bvnMethods' => null,
                'bvnVerified' => false,
                'ninMethod' => '',
                'ninValue' => '',
                'ninFiles' => ['front' => null, 'back' => null],
                'ninVerified' => false,
                'currentStep' => 1
            ];
            $_SESSION['registration_start_time'] = time();
            sendSuccess('Registration data cleared', [], $logger, $new_csrf_token);
            break;

        case 'complete_registration':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405, $logger);
            }
            if (!isset($_SESSION['registration_data'])) {
                sendError('No registration data found', 400, $logger);
            }

            $registration_data = $_SESSION['registration_data'];
            $first_name = filter_var($registration_data['firstName'] ?? '', FILTER_SANITIZE_STRING);
            $last_name = filter_var($registration_data['lastName'] ?? '', FILTER_SANITIZE_STRING);
            $email = filter_var($registration_data['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $phone = filter_var($registration_data['phone'] ?? '', FILTER_SANITIZE_STRING);
            $password = $registration_data['password'] ?? '';
            $verification_type = $registration_data['verificationType'] ?? '';
            $bvn_method = $registration_data['bvnMethod'] ?? '';
            $bvn_value = $registration_data['bvnValue'] ?? null;
            $nin_method = $registration_data['ninMethod'] ?? '';
            $nin_value = $registration_data['ninValue'] ?? '';
            $nin_files = $registration_data['ninFiles'] ?? ['front' => null, 'back' => null];
            $bvn_verified = $registration_data['bvnVerified'] ?? false;
            $nin_verified = $registration_data['ninVerified'] ?? false;

            // Validate all fields
            $errors = [];
            if (!$first_name || !preg_match('/^[A-Za-z\s]{2,50}$/', $first_name)) {
                $errors[] = 'Invalid first name';
            }
            if (!$last_name || !preg_match('/^[A-Za-z\s]{2,50}$/', $last_name)) {
                $errors[] = 'Invalid last name';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                $errors[] = 'Invalid phone number format';
            }
            if (!$password || !validatePassword($password)) {
                $errors[] = 'Invalid password';
            }

            if (!empty($errors)) {
                sendError(implode('. ', $errors), 400, $logger);
            }

            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                sendError('Email already registered', 409, $logger);
            }

            // Begin transaction
            $conn->begin_transaction();

            try {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $verification_token = bin2hex(random_bytes(32));
                $stmt = $conn->prepare(
                    "INSERT INTO users (first_name, last_name, email, phone, password_hash, verification_token, verification_type, bvn_verified, nin_verified, bvn_verification_date, nin_verification_date, email_verified) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                
                // Set verification status - allow skipped verification
                $bvn_verified_int = ($verification_type === 'bvn' && $bvn_verified) ? 1 : 0;
                $nin_verified_int = ($verification_type === 'nin' && $nin_verified) ? 1 : 0;
                $bvn_date = $bvn_verified_int ? date('Y-m-d H:i:s') : null;
                $nin_date = $nin_verified_int ? date('Y-m-d H:i:s') : null;
                $email_verified = 0; // Default to not verified
                
                $stmt->bind_param(
                    "sssssssiissi",
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $hashed_password,
                    $verification_token,
                    $verification_type,
                    $bvn_verified_int,
                    $nin_verified_int,
                    $bvn_date,
                    $nin_date,
                    $email_verified
                );
                if (!$stmt->execute()) {
                    throw new Exception('Database error during user registration');
                }
                $user_id = $conn->insert_id;

                // Create wallet
                $stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                // Handle verification data if provided
                if ($verification_type === 'bvn' && $bvn_method === 'input' && $bvn_verified) {
                    $stmt = $conn->prepare("UPDATE users SET bvn = ?, bvn_verified = TRUE, bvn_verification_date = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $bvn_value, $user_id);
                    $stmt->execute();
                    $logger->info("Verified BVN stored for user ID: $user_id");
                } elseif ($verification_type === 'bvn' && $bvn_method === 'upload' && $bvn_value) {
                    $filename = "bvn_{$user_id}_" . time() . "." . pathinfo($bvn_value, PATHINFO_EXTENSION);
                    rename($bvn_value, UPLOAD_DIR . $filename);
                    $stmt = $conn->prepare("UPDATE users SET bvn_document = ?, bvn_verified = FALSE, bvn_verification_date = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $filename, $user_id);
                    $stmt->execute();
                    $logger->info("BVN document stored for user ID: $user_id");
                } elseif ($verification_type === 'nin' && $nin_verified) {
                    $front_filename = "nin_front_{$user_id}_" . time() . "." . pathinfo($nin_files['front'], PATHINFO_EXTENSION);
                    $back_filename = "nin_back_{$user_id}_" . time() . "." . pathinfo($nin_files['back'], PATHINFO_EXTENSION);
                    rename($nin_files['front'], UPLOAD_DIR . $front_filename);
                    rename($nin_files['back'], UPLOAD_DIR . $back_filename);
                    $stmt = $conn->prepare("UPDATE users SET nin = ?, nin_front = ?, nin_back = ?, nin_verified = TRUE, nin_verification_date = NOW() WHERE id = ?");
                    $stmt->bind_param("sssi", $nin_value, $front_filename, $back_filename, $user_id);
                    $stmt->execute();
                    $logger->info("Verified NIN stored for user ID: $user_id");
                }

                // Commit transaction
                $conn->commit();

                // Send verification email
                try {
                    $mailer = new PHPMailer(true);
                    $mailer->isSMTP();
                    $mailer->Host = SMTP_HOST;
                    $mailer->SMTPAuth = true;
                    $mailer->Username = SMTP_USER;
                    $mailer->Password = SMTP_PASS;
                    $mailer->SMTPSecure = 'tls';
                    $mailer->Port = SMTP_PORT;
                    $mailer->setFrom(SMTP_USER, 'Ubiaza');
                    $mailer->addAddress($email);
                    $mailer->isHTML(true);
                    $mailer->Subject = 'Verify Your Ubiaza Account';
                    $mailer->Body = "Click to verify: <a href='" . SITE_URL . "api/auth.php?action=verify_email&token=$verification_token'>Verify Email</a>";
                    $mailer->send();
                    $logger->info("Verification email sent to $email");
                } catch (Exception $e) {
                    $logger->error("Failed to send verification email to $email: " . $e->getMessage());
                    // Don't send error, just log it
                }

                // Update session
                SessionManager::regenerateSession();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user'] = [
                    'firstName' => $first_name,
                    'lastName' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'bvnVerified' => (bool)$bvn_verified_int,
                    'ninVerified' => (bool)$nin_verified_int,
                    'emailVerified' => false
                ];

                $_SESSION['registration_data'] = null;
                $_SESSION['registration_start_time'] = null;
                sendSuccess('Account creation completed! Please check your email to verify your account.', [], $logger, $new_csrf_token);
            } catch (Exception $e) {
                $conn->rollback();
                sendError('Database error during registration: ' . $e->getMessage(), 500, $logger);
            }
            break;

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405, $logger);
            }
            
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $input['password'] ?? '';
            $is_admin = isset($input['is_admin']) && $input['is_admin'] === true;
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendError('Invalid email format', 400, $logger);
            }
            if (empty($password)) {
                sendError('Password is required', 400, $logger);
            }
            
            if ($is_admin) {
                // Admin login
                $stmt = $conn->prepare("SELECT id, full_name, email, password_hash, role, is_active FROM admins WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendError('Invalid email or password', 401, $logger);
                }
                
                $admin = $result->fetch_assoc();
                if (!password_verify($password, $admin['password_hash'])) {
                    sendError('Invalid email or password', 401, $logger);
                }
                
                if (!$admin['is_active']) {
                    sendError('Admin account is not active', 403, $logger);
                }
                
                // Update last login
                $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $admin['id']);
                $stmt->execute();
                
                // Set admin session
                SessionManager::regenerateSession();
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin'] = [
                    'fullName' => $admin['full_name'],
                    'email' => $admin['email'],
                    'role' => $admin['role']
                ];
                
                // Log admin activity
                $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, target_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
                $action = 'admin_login';
                $target_type = 'system';
                $description = "Admin {$admin['email']} logged in";
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $stmt->bind_param("isssss", $admin['id'], $action, $target_type, $description, $ip_address, $user_agent);
                $stmt->execute();
                
                sendSuccess('Admin login successful', [
                    'admin' => [
                        'fullName' => $admin['full_name'],
                        'email' => $admin['email'],
                        'role' => $admin['role']
                    ]
                ], $logger, $new_csrf_token);
            } else {
                // User login
                $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, password_hash, email_verified, bvn_verified, nin_verified FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    sendError('Invalid email or password', 401, $logger);
                }
                
                $user = $result->fetch_assoc();
                if (!password_verify($password, $user['password_hash'])) {
                    sendError('Invalid email or password', 401, $logger);
                }
                
                // Allow login even if email is not verified
                SessionManager::regenerateSession();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'firstName' => $user['first_name'],
                    'lastName' => $user['last_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'bvnVerified' => (bool)$user['bvn_verified'],
                    'ninVerified' => (bool)$user['nin_verified'],
                    'emailVerified' => (bool)$user['email_verified']
                ];
                
                sendSuccess('Login successful', [
                    'email_verified' => $user['email_verified'],
                    'verification_status' => [
                        'bvn' => $user['bvn_verified'],
                        'nin' => $user['nin_verified']
                    ]
                ], $logger, $new_csrf_token);
            }
            break;

        case 'resend_verification':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendError('Method not allowed', 405, $logger);
            }
            
            if (!isset($_SESSION['user_id'])) {
                sendError('Not logged in', 401, $logger);
            }
            
            $stmt = $conn->prepare("SELECT email, verification_token FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if (!$user) {
                sendError('User not found', 404, $logger);
            }
            
            try {
                $mailer = new PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = SMTP_HOST;
                $mailer->SMTPAuth = true;
                $mailer->Username = SMTP_USER;
                $mailer->Password = SMTP_PASS;
                $mailer->SMTPSecure = 'tls';
                $mailer->Port = SMTP_PORT;
                $mailer->setFrom(SMTP_USER, 'Ubiaza');
                $mailer->addAddress($user['email']);
                $mailer->isHTML(true);
                $mailer->Subject = 'Verify Your Ubiaza Account';
                $mailer->Body = "Click to verify: <a href='" . SITE_URL . "api/auth.php?action=verify_email&token={$user['verification_token']}>Verify Email</a>";
                $mailer->send();
                sendSuccess('Verification email sent', [], $logger, $new_csrf_token);
            } catch (Exception $e) {
                sendError('Failed to send verification email', 500, $logger, ['message' => $e->getMessage()]);
            }
            break;

        case 'verify_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                sendError('Method not allowed', 405, $logger);
            }
            
            $token = filter_var($_GET['token'] ?? '', FILTER_SANITIZE_STRING);
            if (empty($token)) {
                sendError('Verification token is required', 400, $logger);
            }
            
            $stmt = $conn->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL WHERE verification_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                if (isset($_SESSION['user_id'])) {
                    $_SESSION['user']['emailVerified'] = true;
                }
                // Redirect to success page
                header("Location: " . SITE_URL . "sign.php?success=Email+verified+successfully");
                exit;
            } else {
                header("Location: " . SITE_URL . "sign.php?error=Invalid+or+expired+verification+token");
                exit;
            }
            break;

        default:
            sendError('Invalid action', 400, $logger);
    }
} catch (Exception $e) {
    sendError('Server error', 500, $logger, ['message' => $e->getMessage()]);
} finally {
    $db->close();
    ob_end_flush();
}
?>