<?php
require_once 'session.php';
require_once 'config.php';
require_once '../db.php';
require_once '../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

SessionManager::startSession();

$logger = new Logger('verification');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'verification.log', Logger::INFO));

$ip = $_SERVER['REMOTE_ADDR'];

function sendError($message, $code = 400, $logger, $details = []) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'error' => $message, 'details' => $details, 'code' => $code]);
    $logger->warning("Error response: $message, code: $code, details: " . json_encode($details) . ", IP: {$_SERVER['REMOTE_ADDR']}");
    exit;
}

function sendSuccess($message, $data = [], $logger, $csrf_token = null) {
    $response = ['status' => 'success', 'message' => $message, 'data' => $data, 'code' => 200];
    if ($csrf_token) {
        $response['csrf_token'] = $csrf_token;
    }
    echo json_encode($response);
    $logger->info("Success response: $message, IP: {$_SERVER['REMOTE_ADDR']}");
}

function enforceRateLimit($ip, $action, $logger, $rate_limit = 5, $time_window = 60) {
    // Sanitize IP for filename
    $safe_ip = str_replace([':', '.'], '_', $ip);
    $cache_file = LOG_DIR . "rate_limit_verification_{$safe_ip}_{$action}.json";
    $lock_file = $cache_file . '.lock';
    
    // Create logs directory if it doesn't exist
    if (!is_dir(LOG_DIR)) {
        if (!mkdir(LOG_DIR, 0755, true)) {
            $logger->error("Failed to create log directory: " . LOG_DIR);
            sendError('Internal server error', 500, $logger);
        }
    }

    $fp = @fopen($lock_file, 'w+');
    if (!$fp) {
        $logger->error("Failed to open lock file: " . $lock_file);
        sendError('Internal server error', 500, $logger);
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        $logger->error("Failed to acquire lock on file: " . $lock_file);
        sendError('Rate limiting error', 500, $logger);
    }

    $data = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
    $count = isset($data['count']) && $data['time'] > time() - $time_window ? $data['count'] + 1 : 1;
    
    if ($count > $rate_limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        sendError('Too many verification attempts', 429, $logger);
    }
    
    file_put_contents($cache_file, json_encode(['time' => time(), 'count' => $count]));
    flock($fp, LOCK_UN);
    fclose($fp);
}

function validateAndStoreFile($file, $type, $logger) {
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024;
    $temp_dir = sys_get_temp_dir() . '/ubiaza_uploads/';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0700, true);
    }

    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $logger->warning("File upload failed for type: $type");
        return ['error' => 'File upload failed'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types) || $file['size'] > $max_size) {
        $logger->warning("Invalid file type or size for type: $type, file: {$file['name']}, mime: $mime_type");
        return ['error' => 'Invalid file type or size. Supported formats: JPG, PNG, PDF (Max 5MB)'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $temp_path = $temp_dir . uniqid("{$type}_") . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $temp_path)) {
        $logger->warning("Failed to move uploaded file for type: $type");
        return ['error' => 'Failed to store file'];
    }

    return ['file' => ['path' => $temp_path, 'name' => $file['name'], 'type' => $mime_type]];
}

function callMonoApi($endpoint, $data, $logger) {
    $base_url = 'https://api.withmono.com/';
    $url = $base_url . $endpoint;
    $api_key = MONO_SECRET_KEY;

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "content-type: application/json",
            "mono-sec-key: $api_key"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        $logger->error("Mono API error for endpoint $endpoint: $err");
        return ['status' => 'error', 'message' => 'API request failed', 'details' => $err];
    }

    $decoded_response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->error("Invalid JSON response from Mono API for endpoint $endpoint: $response");
        return ['status' => 'error', 'message' => 'Invalid API response'];
    }

    $logger->info("Mono API response for endpoint $endpoint: " . json_encode($decoded_response));
    return $decoded_response;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405, $logger);
}

$input = $_POST;
$csrf_token = $input['csrf_token'] ?? '';
if (!SessionManager::validateCsrfToken($csrf_token)) {
    sendError('Invalid CSRF token', 403, $logger);
}

$logger->info("CSRF token validated for IP: $ip, token: $csrf_token");
$new_csrf_token = SessionManager::refreshCsrfToken();

$action = $input['action'] ?? '';
enforceRateLimit($ip, $action, $logger, $action === 'verify_bvn_otp' ? 3 : 5);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($action) {
        case 'store_verification_data':
            $verification_type = filter_var($input['verification_type'] ?? '', FILTER_SANITIZE_STRING);
            if (!in_array($verification_type, ['bvn', 'nin'])) {
                sendError('Invalid verification type', 400, $logger);
            }
            
            $_SESSION['registration_data']['verificationType'] = $verification_type;
            
            if ($verification_type === 'bvn') {
                $bvn_method = filter_var($input['bvn_method'] ?? '', FILTER_SANITIZE_STRING);
                if (!in_array($bvn_method, ['input', 'upload'])) {
                    sendError('Invalid BVN method', 400, $logger);
                }
                
                $_SESSION['registration_data']['bvnMethod'] = $bvn_method;
                
                if ($bvn_method === 'input') {
                    $bvn = filter_var($input['bvn'] ?? '', FILTER_SANITIZE_STRING);
                    if (!preg_match('/^\d{11}$/', $bvn)) {
                        sendError('Invalid BVN number', 400, $logger);
                    }
                    $_SESSION['registration_data']['bvnValue'] = $bvn;
                    
                    $api_response = callMonoApi('v2/lookup/bvn/initiate', [
                        'bvn' => $bvn,
                        'scope' => 'identity'
                    ], $logger);
                    
                    if ($api_response['status'] !== 'successful') {
                        sendError('BVN resolution failed', 400, $logger, $api_response);
                    }
                    
                    $_SESSION['registration_data']['bvnSessionId'] = $api_response['data']['session_id'];
                    $_SESSION['registration_data']['bvnMethods'] = $api_response['data']['methods'];
                    sendSuccess('BVN submitted, OTP required', ['bvn_methods' => $api_response['data']['methods']], $logger, $new_csrf_token);
                } elseif ($bvn_method === 'upload') {
                    if (!isset($_FILES['bvn_file'])) {
                        sendError('BVN file required', 400, $logger);
                    }
                    $file_result = validateAndStoreFile($_FILES['bvn_file'], 'bvn', $logger);
                    if (isset($file_result['error'])) {
                        sendError($file_result['error'], 400, $logger);
                    }
                    $_SESSION['registration_data']['bvnValue'] = $file_result['file']['path'];
                    $_SESSION['registration_data']['bvnVerified'] = true;
                    sendSuccess('BVN file uploaded successfully', [], $logger, $new_csrf_token);
                }
            } elseif ($verification_type === 'nin') {
                $nin_method = filter_var($input['nin_method'] ?? '', FILTER_SANITIZE_STRING);
                if (!in_array($nin_method, ['camera', 'upload'])) {
                    sendError('Invalid NIN method', 400, $logger);
                }
                
                $nin = filter_var($input['nin'] ?? '', FILTER_SANITIZE_STRING);
                if (!preg_match('/^\d{11}$/', $nin)) {
                    sendError('Invalid NIN number', 400, $logger);
                }
                
                if (!isset($_FILES['nin_front']) || !isset($_FILES['nin_back'])) {
                    sendError('Both NIN front and back images are required', 400, $logger);
                }
                
                $front_result = validateAndStoreFile($_FILES['nin_front'], 'nin_front', $logger);
                if (isset($front_result['error'])) {
                    sendError($front_result['error'], 400, $logger);
                }
                
                $back_result = validateAndStoreFile($_FILES['nin_back'], 'nin_back', $logger);
                if (isset($back_result['error'])) {
                    sendError($back_result['error'], 400, $logger);
                }
                
                $api_response = callMonoApi('v3/lookup/nin', ['nin' => $nin], $logger);
                if ($api_response['status'] !== 'successful') {
                    sendError('NIN verification failed', 400, $logger, $api_response);
                }
                
                $_SESSION['registration_data']['ninMethod'] = $nin_method;
                $_SESSION['registration_data']['ninValue'] = $nin;
                $_SESSION['registration_data']['ninFiles'] = [
                    'front' => $front_result['file']['path'],
                    'back' => $back_result['file']['path']
                ];
                $_SESSION['registration_data']['ninVerified'] = true;
                sendSuccess('NIN verification completed', [], $logger, $new_csrf_token);
            }
            break;

        case 'verify_bvn_otp':
            $method = filter_var($input['method'] ?? '', FILTER_SANITIZE_STRING);
            $otp = filter_var($input['otp'] ?? '', FILTER_SANITIZE_STRING);
            if (!$method || !$otp) {
                sendError('Verification method and OTP are required', 400, $logger);
            }
            
            if (!isset($_SESSION['registration_data']['bvnSessionId'])) {
                sendError('No BVN session found', 400, $logger);
            }
            
            $api_response = callMonoApi('v2/lookup/bvn/details', [
                'otp' => $otp,
                'session_id' => $_SESSION['registration_data']['bvnSessionId']
            ], $logger);
            
            if ($api_response['status'] !== 'successful') {
                sendError('BVN OTP verification failed', 400, $logger, $api_response);
            }
            
            $_SESSION['registration_data']['bvnVerified'] = true;
            sendSuccess('BVN OTP verified successfully', [], $logger, $new_csrf_token);
            break;

        default:
            sendError('Invalid action', 400, $logger);
    }
} catch (Exception $e) {
    sendError('Server error', 500, $logger, ['message' => $e->getMessage()]);
} finally {
    $db->close();
}
?>