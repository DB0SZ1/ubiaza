<?php
require_once '../config.php';
require_once '../../db.php';
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: sign.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$conn = $db->getConnection();
$logger = new Logger('wallet_setup');
$logger->pushHandler(new StreamHandler(LOG_DIR . 'wallet_setup.log', Logger::INFO));

// Fetch user data
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if account exists and fetch details
$stmt = $conn->prepare("SELECT id, account_number, balance, available_balance, pending_balance FROM accounts WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$accountExists = $result->num_rows > 0;
$account = $accountExists ? $result->fetch_assoc() : null;

// Handle account creation
$error = '';
$success = '';
if (!$accountExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_wallet'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
        $logger->warning("CSRF token mismatch for user ID: " . $_SESSION['user_id']);
    } else {
        $account_name = filter_var(trim($_POST['account_name']), FILTER_SANITIZE_STRING);
        if (empty($account_name)) {
            $error = "Account name is required.";
        } else {
            if (!check_rate_limit('create_wallet', $_SESSION['user_id'], 3, 3600)) {
                $error = "Too many attempts. Please try again later.";
                $logger->warning("Rate limit exceeded for user ID: " . $_SESSION['user_id']);
            } else {
                $account_ref = 'VA_' . $_SESSION['user_id'] . '_' . time();
                $api_token = getNombaApiToken();
                if (!$api_token) {
                    $error = "Failed to authenticate with Nomba API.";
                    $logger->error("No API token for user ID: " . $_SESSION['user_id']);
                } else {
                    $api_response = createNombaVirtualAccount($account_ref, $account_name, $api_token);
                    if ($api_response['success']) {
                        $stmt = $conn->prepare("
                            INSERT INTO accounts (user_id, account_number, account_name, phone_number, balance, available_balance, pending_balance) 
                            VALUES (?, ?, ?, ?, 0.00, 0.00, 0.00)
                        ");
                        $stmt->bind_param("isss", $_SESSION['user_id'], $api_response['account_number'], $account_name, $user['phone']);
                        if ($stmt->execute()) {
                            $logger->info("Account created for user ID: " . $_SESSION['user_id'] . " with account number: " . $api_response['account_number']);
                            $success = "Account created successfully! You can now fund your account.";
                            // Send email notification
                            $subject = "Ubiaza Account Created";
                            $message = "Dear {$user['first_name']},<br>Your virtual account ({$api_response['account_number']}) has been created. Fund it by sending money to: Amucha MFB, {$api_response['account_number']}.";
                            send_email_notification($user['email'], $subject, $message);
                            // Log notification
                            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Account Created', ?)");
                            $stmt->bind_param("is", $_SESSION['user_id'], $message);
                            $stmt->execute();
                            // Regenerate CSRF token
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            header('Location: wallet.php');
                            exit;
                        } else {
                            $error = "Failed to save account details.";
                            $logger->error("Database error for user ID: " . $_SESSION['user_id'] . ": " . $conn->error);
                        }
                    } else {
                        $error = "Failed to create virtual account: " . ($api_response['message'] ?? 'Unknown error');
                        $logger->error("Nomba API error for user ID: " . $_SESSION['user_id'] . ": " . $api_response['message']);
                    }
                }
            }
        }
    }
}

function createNombaVirtualAccount($account_ref, $account_name, $api_token) {
    $url = 'https://api.nomba.com/v1/accounts/virtual';
    $data = [
        'accountRef' => $account_ref,
        'accountName' => $account_name
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/json',
            'accountId: ' . NOMBA_ACCOUNT_ID
        ]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $GLOBALS['logger']->error("cURL error in createNombaVirtualAccount: " . curl_error($ch));
        curl_close($ch);
        return ['success' => false, 'message' => 'API request failed'];
    }
    curl_close($ch);
    $result = json_decode($response, true);
    return [
        'success' => $http_code === 200 && isset($result['code']) && $result['code'] === '00',
        'message' => $result['description'] ?? 'Unknown error',
        'account_number' => $result['data']['bankAccountNumber'] ?? null
    ];
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza - Account Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .account-details-card, .setup-account-card {
            padding: 20px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .account-details-card h2, .setup-account-card h2 {
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: 500;
            color: #666;
        }
        .detail-value {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .copy-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
        }
        .copy-btn:hover {
            background: #2563eb;
        }
        .step-form {
            max-width: 500px;
            margin: 20px auto;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        .step-dot {
            width: 10px;
            height: 10px;
            background: #ccc;
            border-radius: 50%;
        }
        .step-dot.active {
            background: #3b82f6;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .error, .success {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error {
            background: #fee2e2;
            color: #ef4444;
        }
        .success {
            background: #d1fae5;
            color: #10b981;
        }
        .create-account-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
        .create-account-btn:hover {
            background: #2563eb;
        }
        .funding-instructions {
            margin-top: 20px;
            padding: 15px;
            background: #f0f4ff;
            border-radius: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mobile-header">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span>Ubiaza</span>
            </div>
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span>Ubiaza</span>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
                <a href="wallet.php" class="nav-item active"><i class="fas fa-wallet"></i> Account</a>
                <a href="bills.php" class="nav-item"><i class="fas fa-file-invoice"></i> Bills</a>
                <a href="transfer.php" class="nav-item"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="transactions.php" class="nav-item"><i class="fas fa-history"></i> Transactions</a>
                <a href="signout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </nav>

        <main class="main-content" id="mainContent">
            <div class="top-header">
                <div class="header-left">
                    <h1>Account Setup</h1>
                </div>
                <div class="header-right">
                    <div class="theme-toggle">
                        <label class="theme-toggle-label">
                            <input type="checkbox" class="theme-toggle-checkbox" id="themeToggle" onchange="toggleTheme()">
                            <span class="theme-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="mobile-user">
                        <div class="user-avatar" onclick="toggleDropdown()">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> Profile</a>
                            <a href="signout.php" class="dropdown-item danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="left-content">
                    <?php if ($accountExists): ?>
                        <div class="account-details-card">
                            <h2>Your Account Details</h2>
                            <div class="detail-row">
                                <span class="detail-label">Account Number</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($account['account_number']); ?>
                                    <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($account['account_number']); ?>')">Copy</button>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Balance</span>
                                <span class="detail-value">₦<?php echo number_format($account['balance'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Available Balance</span>
                                <span class="detail-value">₦<?php echo number_format($account['available_balance'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Pending Balance</span>
                                <span class="detail-value">₦<?php echo number_format($account['pending_balance'], 2); ?></span>
                            </div>
                            <div class="funding-instructions">
                                <p><strong>Fund Your Account</strong></p>
                                <p>Send money to the following account to add funds:</p>
                                <p><strong>Bank:</strong> Amucha MFB<br><strong>Account Number:</strong> <?php echo htmlspecialchars($account['account_number']); ?></p>
                            </div>
                            <a href="dashboard.php" class="btn-small" style="margin-top: 20px; display: inline-block;">Back to Dashboard</a>
                        </div>
                    <?php else: ?>
                        <div class="setup-account-card">
                            <h2>Setup Your Virtual Account</h2>
                            <p>Create a virtual account to send and receive funds seamlessly.</p>
                            <?php if ($error): ?>
                                <p class="error"><?php echo htmlspecialchars($error); ?></p>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <p class="success"><?php echo htmlspecialchars($success); ?></p>
                            <?php endif; ?>
                            <div class="step-form">
                                <div class="step-indicator">
                                    <span class="step-dot active"></span>
                                    <span class="step-dot"></span>
                                </div>
                                <form method="post" action="wallet.php">
                                    <div class="step active" id="step1">
                                        <div class="form-group">
                                            <label for="account_name">Account Name</label>
                                            <input type="text" id="account_name" name="account_name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" required>
                                        </div>
                                        <button type="button" class="create-account-btn" onclick="nextStep()">Next</button>
                                    </div>
                                    <div class="step" id="step2">
                                        <p>Please review your details before creating your account.</p>
                                        <div class="form-group">
                                            <label>Account Name</label>
                                            <p id="review_account_name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                        </div>
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="account_name" id="hidden_account_name">
                                        <button type="submit" name="create_wallet" class="create-account-btn">Create Account</button>
                                        <button type="button" class="btn-small" onclick="prevStep()" style="margin-left: 10px;">Back</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <script src="assets/js/dashboard.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Account number copied to clipboard!');
            });
        }

        function nextStep() {
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const accountName = document.getElementById('account_name').value;
            if (!accountName.trim()) {
                alert('Please enter an account name.');
                return;
            }
            document.getElementById('review_account_name').textContent = accountName;
            document.getElementById('hidden_account_name').value = accountName;
            step1.classList.remove('active');
            step2.classList.add('active');
            document.querySelectorAll('.step-dot')[0].classList.remove('active');
            document.querySelectorAll('.step-dot')[1].classList.add('active');
        }

        function prevStep() {
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            step2.classList.remove('active');
            step1.classList.add('active');
            document.querySelectorAll('.step-dot')[1].classList.remove('active');
            document.querySelectorAll('.step-dot')[0].classList.add('active');
        }

        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</body>
</html>