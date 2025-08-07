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
            $account_ref = 'VA_' . $_SESSION['user_id'] . '_' . time();
            $api_response = createNombaVirtualAccount($account_ref, $account_name);

            if ($api_response['success']) {
                $stmt = $conn->prepare("
                    INSERT INTO accounts (user_id, account_number, account_name, phone_number, balance, available_balance, pending_balance) 
                    VALUES (?, ?, ?, ?, 0.00, 0.00, 0.00)
                ");
                $stmt->bind_param("isss", $_SESSION['user_id'], $api_response['account_number'], $account_name, $user['phone']);
                if ($stmt->execute()) {
                    $logger->info("Account created for user ID: " . $_SESSION['user_id'] . " with account number: " . $api_response['account_number']);
                    $success = "Account created successfully!";
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // Reload page to show account details
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

function createNombaVirtualAccount($account_ref, $account_name) {
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
            'Authorization: Bearer ' . NOMBA_API_TOKEN,
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
    <title>Ubiaza - Virtual Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        /* Wallet-specific styles using dashboard.css as base */
        .wallet-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .wallet-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: scale(1.2);
        }

        .wallet-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .wallet-subtitle {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        /* Account Details Card */
        .account-details-card {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }

        .account-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--light-blue);
        }

        .account-header h2 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .account-status {
            background: var(--success);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row:hover {
            background: var(--light-blue);
            margin: 0 -16px;
            padding: 16px;
            border-radius: 12px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
        }

        .copy-btn {
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .copy-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .balance-highlight {
            font-size: 24px;
            font-weight: 700;
            color: var(--success);
        }

        /* Setup Account Card */
        .setup-account-card {
            background: var(--white);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid var(--border);
        }

        .setup-icon {
            width: 80px;
            height: 80px;
            background: var(--light-blue);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }

        .setup-account-card h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .setup-account-card p {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 32px;
            line-height: 1.6;
        }

        /* Step Form */
        .step-form {
            max-width: 500px;
            margin: 0 auto;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .step-dot {
            width: 12px;
            height: 12px;
            background: var(--border);
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
        }

        .step-dot.active {
            background: var(--primary-color);
            transform: scale(1.2);
        }

        .step-dot::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 24px;
            height: 24px;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .step-dot.active::after {
            opacity: 0.3;
        }

        .step-connector {
            width: 40px;
            height: 2px;
            background: var(--border);
            transition: all 0.3s ease;
        }

        .step-connector.active {
            background: var(--primary-color);
        }

        .step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 24px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--background);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(30, 73, 184, 0.1);
        }

        .review-section {
            background: var(--light-blue);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .review-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .review-item:last-child {
            margin-bottom: 0;
        }

        .review-label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .review-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Buttons */
        .create-account-btn {
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 160px;
            justify-content: center;
        }

        .create-account-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 73, 184, 0.3);
        }

        .btn-secondary {
            background: var(--background);
            color: var(--text-primary);
            border: 2px solid var(--border);
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            background: var(--light-blue);
            transform: translateY(-1px);
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert i {
            font-size: 18px;
        }

        /* Action Buttons Row */
        .wallet-actions {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            justify-content: center;
        }

        .wallet-actions .action-btn {
            background: var(--white);
            border: 2px solid var(--border);
            padding: 16px 24px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: var(--text-primary);
            min-width: 120px;
        }

        .wallet-actions .action-btn:hover {
            border-color: var(--primary-color);
            background: var(--light-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .wallet-actions .action-btn i {
            font-size: 24px;
            color: var(--primary-color);
        }

        /* Dark Mode Support */
        [data-theme="dark"] .account-details-card,
        [data-theme="dark"] .setup-account-card {
            background: var(--dark-card);
            border-color: var(--dark-border);
        }

        [data-theme="dark"] .account-header h2,
        [data-theme="dark"] .setup-account-card h2 {
            color: var(--dark-text-primary);
        }

        [data-theme="dark"] .detail-label {
            color: var(--dark-text-secondary);
        }

        [data-theme="dark"] .detail-value {
            color: var(--dark-text-primary);
        }

        [data-theme="dark"] .setup-account-card p {
            color: var(--dark-text-secondary);
        }

        [data-theme="dark"] .form-group input {
            background: var(--dark-background);
            border-color: var(--dark-border);
            color: var(--dark-text-primary);
        }

        [data-theme="dark"] .form-group input:focus {
            background: var(--dark-card);
        }

        [data-theme="dark"] .form-group label {
            color: var(--dark-text-primary);
        }

        [data-theme="dark"] .review-section {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
        }

        [data-theme="dark"] .review-label {
            color: var(--dark-text-secondary);
        }

        [data-theme="dark"] .review-value {
            color: var(--dark-text-primary);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .wallet-header {
                padding: 20px;
                margin-bottom: 20px;
            }

            .wallet-header h1 {
                font-size: 24px;
            }

            .account-details-card,
            .setup-account-card {
                padding: 24px;
                margin-bottom: 16px;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px 0;
            }

            .detail-value {
                align-self: flex-end;
            }

            .wallet-actions {
                flex-direction: column;
                gap: 12px;
            }

            .wallet-actions .action-btn {
                flex-direction: row;
                justify-content: center;
                min-width: auto;
            }

            .step-indicator {
                gap: 12px;
                margin-bottom: 24px;
            }

            .step-connector {
                width: 30px;
            }
        }

        @media (max-width: 480px) {
            .setup-account-card {
                padding: 20px;
            }

            .setup-account-card h2 {
                font-size: 24px;
            }

            .create-account-btn {
                width: 100%;
                padding: 16px;
            }

            .btn-secondary {
                width: 100%;
                justify-content: center;
                margin-top: 12px;
            }
        }

        /* Loading Animation */
        .loading-dots {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .loading-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: loading 1.4s ease-in-out infinite;
        }

        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes loading {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span>Ubiaza</span>
            </div>
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="mobile-user">
                <div class="theme-toggle">
                    <label class="theme-toggle-label">
                        <input type="checkbox" class="theme-toggle-checkbox" id="themeToggle" onchange="toggleTheme()">
                        <span class="theme-toggle-slider"></span>
                    </label>
                </div>
                <div class="user-avatar" onclick="toggleDropdown()">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="signout.php" class="dropdown-item danger">
                        <i class="fas fa-sign-out-alt"></i> Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-wallet"></i>
                <span>Ubiaza</span>
            </div>
            <div class="nav-menu">
                <a href="../../dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="wallet.php" class="nav-item active">
                    <i class="fas fa-wallet"></i> Virtual Account
                </a>
                <a href="transfer.php" class="nav-item">
                    <i class="fas fa-paper-plane"></i> Transfer
                </a>
                <a href="transactions.php" class="nav-item">
                    <i class="fas fa-history"></i> Transactions
                </a>
                <a href="signout.php" class="nav-item sign-out">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <h1>Virtual Account</h1>
                </div>
                <div class="header-right">
                    <div class="theme-toggle">
                        <label class="theme-toggle-label">
                            <input type="checkbox" class="theme-toggle-checkbox" id="themeToggle" onchange="toggleTheme()">
                            <span class="theme-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="mobile-user">
                        <div class="user-avatar" onclick="toggleDropdown()">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Profile
                            </a>
                            <a href="signout.php" class="dropdown-item danger">
                                <i class="fas fa-sign-out-alt"></i> Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet Header -->
            <div class="wallet-header">
                <h1>
                    <i class="fas fa-university"></i>
                    Virtual Banking Account
                </h1>
                <p class="wallet-subtitle">Manage your digital wallet and track your finances</p>
            </div>

            <!-- Content -->
            <?php if ($accountExists): ?>
                <!-- Account Details -->
                <div class="account-details-card">
                    <div class="account-header">
                        <h2>
                            <i class="fas fa-credit-card"></i>
                            Account Details
                        </h2>
                        <div class="account-status">
                            <i class="fas fa-check-circle"></i>
                            Active
                        </div>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-hashtag"></i>
                            Account Number
                        </span>
                        <span class="detail-value">
                            <span class="account-number"><?php echo htmlspecialchars($account['account_number']); ?></span>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($account['account_number']); ?>', 'Account number copied!')">
                                <i class="fas fa-copy"></i>
                                Copy
                            </button>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-coins"></i>
                            Total Balance
                        </span>
                        <span class="detail-value balance-highlight">
                            ₦<?php echo number_format($account['balance'], 2); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-wallet"></i>
                            Available Balance
                        </span>
                        <span class="detail-value">
                            ₦<?php echo number_format($account['available_balance'], 2); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-hourglass-half"></i>
                            Pending Balance
                        </span>
                        <span class="detail-value">
                            ₦<?php echo number_format($account['pending_balance'], 2); ?>
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="wallet-actions">
                    <a href="dashboard.php" class="action-btn">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="transfer.php" class="action-btn">
                        <i class="fas fa-paper-plane"></i>
                        <span>Transfer</span>
                    </a>
                    <a href="transactions.php" class="action-btn">
                        <i class="fas fa-history"></i>
                        <span>History</span>
                    </a>
                </div>

            <?php else: ?>
                <!-- Setup Account -->
                <div class="setup-account-card">
                    <div class="setup-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <h2>Setup Your Virtual Account</h2>
                    <p>Create a virtual account to send and receive funds seamlessly. Get instant access to digital banking services.</p>

                    <?php if ($error): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="step-form">
                        <div class="step-indicator">
                            <span class="step-dot active" id="dot1"></span>
                            <span class="step-connector" id="connector1"></span>
                            <span class="step-dot" id="dot2"></span>
                        </div>

                        <form method="post" action="wallet.php" id="walletForm">
                            <!-- Step 1: Account Setup -->
                            <div class="step active" id="step1">
                                <div class="form-group">
                                    <label for="account_name">
                                        <i class="fas fa-user"></i>
                                        Account Name
                                    </label>
                                    <input 
                                        type="text" 
                                        id="account_name" 
                                        name="account_name" 
                                        value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" 
                                        required 
                                        placeholder="Enter your full name as it should appear"
                                    >
                                    <small style="color: var(--text-secondary); margin-top: 8px; display: block;">
                                        This name will be used for your virtual account and banking transactions.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="phone_number">
                                        <i class="fas fa-phone"></i>
                                        Phone Number
                                    </label>
                                    <input 
                                        type="tel" 
                                        id="phone_number" 
                                        name="phone_number" 
                                        value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                        readonly
                                        style="background: var(--background); cursor: not-allowed;"
                                    >
                                    <small style="color: var(--text-secondary); margin-top: 8px; display: block;">
                                        Your registered phone number for account verification.
                                    </small>
                                </div>

                                <button type="button" class="create-account-btn" onclick="nextStep()">
                                    <i class="fas fa-arrow-right"></i>
                                    Continue
                                </button>
                            </div>

                            <!-- Step 2: Review & Confirm -->
                            <div class="step" id="step2">
                                <h3 style="text-align: center; margin-bottom: 24px; color: var(--text-primary);">
                                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    Review Your Information
                                </h3>
                                
                                <div class="review-section">
                                    <div class="review-item">
                                        <span class="review-label">Account Name:</span>
                                        <span class="review-value" id="review_account_name">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Phone Number:</span>
                                        <span class="review-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                                    </div>
                                    <div class="review-item">
                                        <span class="review-label">Email Address:</span>
                                        <span class="review-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                </div>

                                <div style="background: rgba(16, 185, 129, 0.1); border-radius: 12px; padding: 16px; margin: 24px 0; border: 1px solid rgba(16, 185, 129, 0.2);">
                                    <p style="color: var(--success); font-weight: 500; margin: 0; text-align: center; font-size: 14px;">
                                        <i class="fas fa-shield-alt"></i>
                                        Your virtual account will be created instantly and secured with bank-level encryption.
                                    </p>
                                </div>

                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="account_name" id="hidden_account_name">
                                
                                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                                    <button type="button" class="btn-secondary" onclick="prevStep()">
                                        <i class="fas fa-arrow-left"></i>
                                        Back
                                    </button>
                                    <button type="submit" name="create_wallet" class="create-account-btn" id="createBtn">
                                        <i class="fas fa-plus-circle"></i>
                                        Create Account
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- Toast Notification -->
    <div id="toast" class="notification" style="display: none;">
        <span id="toastMessage"></span>
    </div>

    <script>
        // Theme management
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            const themeToggles = document.querySelectorAll('#themeToggle');
            themeToggles.forEach(toggle => {
                toggle.checked = savedTheme === 'dark';
            });
        });

        // Sidebar management
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            mainContent.classList.toggle('expanded');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            mainContent.classList.remove('expanded');
        }

        // Dropdown management
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const avatar = document.querySelector('.user-avatar');
            
            if (!avatar.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Copy to clipboard function
        function copyToClipboard(text, message = 'Copied to clipboard!') {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast(message, 'success');
                }).catch(() => {
                    fallbackCopyTextToClipboard(text, message);
                });
            } else {
                fallbackCopyTextToClipboard(text, message);
            }
        }

        function fallbackCopyTextToClipboard(text, message) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showToast(message, 'success');
            } catch (err) {
                showToast('Failed to copy. Please copy manually.', 'error');
            }
            
            document.body.removeChild(textArea);
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.className = `notification ${type}`;
            toast.style.display = 'block';
            
            // Trigger animation
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 10);
            
            // Hide after 3 seconds
            setTimeout(() => {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 300);
            }, 3000);
        }

        // Multi-step form functionality
        function nextStep() {
            const accountNameInput = document.getElementById('account_name');
            const accountName = accountNameInput.value.trim();
            
            if (!accountName) {
                showToast('Please enter an account name.', 'error');
                accountNameInput.focus();
                return;
            }
            
            if (accountName.length < 3) {
                showToast('Account name must be at least 3 characters long.', 'error');
                accountNameInput.focus();
                return;
            }
            
            // Update review section
            document.getElementById('review_account_name').textContent = accountName;
            document.getElementById('hidden_account_name').value = accountName;
            
            // Switch steps
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            
            // Update indicators
            document.getElementById('dot1').classList.remove('active');
            document.getElementById('dot2').classList.add('active');
            document.getElementById('connector1').classList.add('active');
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevStep() {
            // Switch steps
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step1').classList.add('active');
            
            // Update indicators
            document.getElementById('dot2').classList.remove('active');
            document.getElementById('dot1').classList.add('active');
            document.getElementById('connector1').classList.remove('active');
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Form submission handling
        document.getElementById('walletForm')?.addEventListener('submit', function(e) {
            const createBtn = document.getElementById('createBtn');
            const originalText = createBtn.innerHTML;
            
            createBtn.disabled = true;
            createBtn.innerHTML = '<div class="loading-dots"><span></span><span></span><span></span></div> Creating Account...';
            
            // Re-enable button after 10 seconds as fallback
            setTimeout(() => {
                createBtn.disabled = false;
                createBtn.innerHTML = originalText;
            }, 10000);
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(-20px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

        // Add input validation and formatting
        document.addEventListener('DOMContentLoaded', function() {
            const accountNameInput = document.getElementById('account_name');
            
            if (accountNameInput) {
                accountNameInput.addEventListener('input', function(e) {
                    let value = e.target.value;
                    
                    // Remove multiple spaces and trim
                    value = value.replace(/\s+/g, ' ').replace(/^\s+/, '');
                    
                    // Capitalize first letter of each word
                    value = value.replace(/\b\w/g, l => l.toUpperCase());
                    
                    e.target.value = value;
                });
                
                // Prevent invalid characters
                accountNameInput.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    if (!/[a-zA-Z\s]/.test(char)) {
                        e.preventDefault();
                        showToast('Only letters and spaces are allowed in account name.', 'error');
                    }
                });
            }
        });

        // Add smooth transitions for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to buttons on hover
            const buttons = document.querySelectorAll('button, .action-btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    if (!this.disabled) {
                        this.style.transform = '';
                    }
                });
            });
        });

        // Responsive sidebar handling
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Close sidebar with Escape key
            if (e.key === 'Escape') {
                closeSidebar();
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Add focus management for accessibility
        document.addEventListener('DOMContentLoaded', function() {
            const focusableElements = document.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            // Add focus indicators
            focusableElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.outline = '2px solid var(--primary-color)';
                    this.style.outlineOffset = '2px';
                });
                
                element.addEventListener('blur', function() {
                    this.style.outline = '';
                    this.style.outlineOffset = '';
                });
            });
        });

        // Add connection status indicator
        function checkConnection() {
            if (navigator.onLine) {
                document.body.classList.remove('offline');
            } else {
                document.body.classList.add('offline');
                showToast('You are currently offline. Some features may not work.', 'error');
            }
        }

        window.addEventListener('online', checkConnection);
        window.addEventListener('offline', checkConnection);
        checkConnection();

        // Add auto-save for form data
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('walletForm');
            if (form) {
                const inputs = form.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"]');
                
                inputs.forEach(input => {
                    // Load saved data
                    const savedValue = localStorage.getItem(`wallet_form_${input.name}`);
                    if (savedValue && !input.hasAttribute('readonly')) {
                        input.value = savedValue;
                    }
                    
                    // Save data on input
                    input.addEventListener('input', function() {
                        if (!this.hasAttribute('readonly')) {
                            localStorage.setItem(`wallet_form_${this.name}`, this.value);
                        }
                    });
                });
                
                // Clear saved data on successful submission
                form.addEventListener('submit', function() {
                    inputs.forEach(input => {
                        localStorage.removeItem(`wallet_form_${input.name}`);
                    });
                });
            }
        });
    </script>
</body>
</html>