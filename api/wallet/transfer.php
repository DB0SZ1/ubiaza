<?php
require_once 'api/config.php';
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: sign.php');
    exit;
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$conn = $db->getConnection();

// Fetch user data and verification status
$stmt = $conn->prepare("SELECT u.first_name, u.last_name, u.email, u.bvn_verified, u.nin_verified, w.available_balance FROM users u JOIN wallets w ON u.id = w.user_id WHERE u.id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if user exists
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: sign.php?error=invalid_user');
    exit;
}

// Fetch recent recipients from beneficiaries table, including virtual accounts
$stmt = $conn->prepare("
    SELECT b.id, b.type, b.name, b.email, b.account_number, b.bank_code, b.account_name, b.nickname, b.last_used, va.account_number AS va_number
    FROM beneficiaries b
    LEFT JOIN virtual_accounts va ON b.user_id = va.user_id AND b.type = 'virtual'
    WHERE b.user_id = ? AND b.is_verified = 1
    ORDER BY b.last_used DESC LIMIT 8
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch transfer statistics (last 30 days)
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transfers,
        SUM(CASE WHEN type = 'transfer' THEN 1 ELSE 0 END) as ubiaza_transfers,
        SUM(CASE WHEN type = 'external_bank' THEN 1 ELSE 0 END) as bank_transfers,
        SUM(amount) as total_sent
    FROM transactions 
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Fetch recent transactions (last 5)
$stmt = $conn->prepare("
    SELECT type, amount, fee, bank_details, recipient_email, status, created_at
    FROM transactions
    WHERE user_id = ? AND type IN ('transfer', 'external_bank', 'virtual')
    ORDER BY created_at DESC LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch notifications
$stmt = $conn->prepare("SELECT id, title, message, is_read FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Money - Ubiaza</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Transfer Page Specific Styles (unchanged from original) */
        .transfer-hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }

        .transfer-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><circle cx="100" cy="100" r="80" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/><path d="M50 100 L150 100 M130 80 L150 100 L130 120" stroke="rgba(255,255,255,0.15)" stroke-width="3" fill="none"/></svg>');
            opacity: 0.6;
            transform: translate(50px, -50px);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .hero-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .hero-stats {
            display: flex;
            gap: 32px;
        }

        .hero-stat {
            text-align: center;
        }

        .hero-stat-value {
            font-size: 20px;
            font-weight: 700;
        }

        .hero-stat-label {
            font-size: 12px;
            opacity: 0.8;
        }

        .transfer-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .transfer-method {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .transfer-method::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-blue));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .transfer-method:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }

        .transfer-method:hover::before {
            transform: scaleX(1);
        }

        .transfer-method.active {
            border-color: var(--primary-color);
            background: var(--light-blue);
        }

        .transfer-method.active::before {
            transform: scaleX(1);
        }

        .method-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .method-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--white);
            position: relative;
        }

        .method-icon.ubiaza {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .method-icon.bank {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .method-icon.virtual {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .method-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .method-info p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .method-features {
            list-style: none;
            margin-top: 16px;
        }

        .method-features li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .method-features i {
            color: var(--success);
            font-size: 12px;
        }

        .method-fee {
            background: var(--background);
            padding: 12px 16px;
            border-radius: 12px;
            margin-top: 16px;
            text-align: center;
        }

        .fee-amount {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .fee-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .recent-recipients {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .recipients-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .recipients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .recipient-card {
            background: var(--background);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .recipient-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-color);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .recipient-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .recipient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: var(--white);
            position: relative;
        }

        .recipient-avatar.ubiaza {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .recipient-avatar.bank {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .recipient-avatar.virtual {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .recipient-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .recipient-details p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .recipient-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .last-sent {
            background: var(--light-blue);
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        .transfer-form-container {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .form-tabs {
            display: flex;
            background: var(--background);
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 24px;
            gap: 4px;
        }

        .form-tab {
            flex: 1;
            text-align: center;
            padding: 12px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            position: relative;
        }

        .form-tab.active {
            background: var(--white);
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
            animation: slideInUp 0.3s ease-out;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(30, 73, 184, 0.1);
        }

        .form-group.error input,
        .form-group.error select {
            border-color: var(--danger);
        }

        .form-group .error-message {
            color: var(--danger);
            font-size: 12px;
            margin-top: 4px;
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
        }

        .account-name-display {
            margin-top: 8px;
            padding: 12px 16px;
            background: var(--light-blue);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
            display: none;
        }

        .account-name-display.show {
            display: block;
            animation: slideInUp 0.3s ease-out;
        }

        .amount-presets {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .amount-preset {
            background: var(--background);
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .amount-preset:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        .transfer-summary {
            background: var(--background);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 14px;
        }

        .summary-row.total {
            border-top: 2px solid var(--border);
            margin-top: 12px;
            padding-top: 12px;
            font-weight: 700;
            font-size: 16px;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-blue));
            color: var(--white);
            border: none;
            padding: 18px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 73, 184, 0.3);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .submit-btn .btn-text {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .verification-banner {
            background: linear-gradient(135deg, #fef3c7, #fcd34d);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .verification-banner i {
            font-size: 20px;
            color: #d97706;
        }

        .verification-banner .content {
            flex: 1;
        }

        .verification-banner h4 {
            font-size: 14px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 4px;
        }

        .verification-banner p {
            font-size: 13px;
            color: #92400e;
            margin: 0;
        }

        .verify-btn {
            background: #f59e0b;
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .verify-btn:hover {
            background: #d97706;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .confirmation-modal {
            background: var(--white);
            border-radius: 20px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .modal-overlay.show .confirmation-modal {
            transform: translateY(0);
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-blue));
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .modal-details {
            background: var(--background);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .modal-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .modal-row:last-child {
            margin-bottom: 0;
        }

        .modal-row.total {
            border-top: 1px solid var(--border);
            padding-top: 12px;
            margin-top: 12px;
            font-weight: 700;
            font-size: 16px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .confirm-btn {
            background: var(--primary-color);
            color: var(--white);
        }

        .confirm-btn:hover {
            background: var(--primary-hover);
        }

        .cancel-btn {
            background: var(--background);
            color: var(--text-primary);
        }

        .cancel-btn:hover {
            background: var(--border);
        }

        .transaction-history {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .transaction-item {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text-primary);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        /* Notification Styles (from dashboard.php) */
        .notification-bell {
            position: relative;
            cursor: pointer;
        }

        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .notification-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-item {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: #f1f5f9;
        }

        .notification-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Sidebar Styles (from dashboard.php) */
        .sidebar {
            width: 220px;
            background: var(--white);
            padding: 20px;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .nav-item:hover, .nav-item.active {
            background: #f1f5f9;
            color: var(--primary-color);
        }

        .nav-item i {
            font-size: 16px;
        }

        .sign-out {
            margin-top: auto;
            color: #ef4444;
        }

        @keyframes slideInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .transfer-hero {
                padding: 24px;
                margin: 10px;
            }

            .hero-title {
                font-size: 24px;
            }

            .hero-stats {
                gap: 16px;
                justify-content: space-between;
            }

            .hero-stat-value {
                font-size: 16px;
            }

            .transfer-methods {
                grid-template-columns: 1fr;
                gap: 16px;
                margin: 0 10px 24px;
            }

            .transfer-method {
                padding: 20px;
            }

            .recipients-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .transfer-form-container {
                margin: 0 10px 24px;
                padding: 24px;
            }

            .form-tabs {
                flex-direction: column;
                gap: 2px;
            }

            .amount-presets {
                justify-content: center;
            }

            .modal-actions {
                flex-direction: column;
            }

            .confirmation-modal {
                padding: 24px;
                margin: 20px;
            }
        }

        @media (max-width: 480px) {
            .hero-stats {
                flex-direction: column;
                gap: 12px;
            }

            .method-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .form-group input,
            .form-group select {
                padding: 14px 16px;
            }
        }

        [data-theme="dark"] .transfer-method,
        [data-theme="dark"] .recent-recipients,
        [data-theme="dark"] .transfer-form-container,
        [data-theme="dark"] .transaction-history {
            background: var(--dark-card);
        }

        [data-theme="dark"] .transfer-method.active {
            background: rgba(30, 73, 184, 0.2);
        }

        [data-theme="dark"] .confirmation-modal {
            background: var(--dark-card);
        }

        [data-theme="dark"] .modal-details,
        [data-theme="dark"] .transfer-summary {
            background: var(--dark-background);
        }

        [data-theme="dark"] .form-group input,
        [data-theme="dark"] .form-group select {
            background: var(--dark-card);
            border-color: var(--dark-border);
            color: var(--dark-text-primary);
        }

        [data-theme="dark"] .account-name-display {
            background: rgba(30, 73, 184, 0.2);
        }

        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Ubiaza</span>
            </div>
            <div class="mobile-user">
                <div class="notification-bell" onclick="toggleNotificationDropdown()">
                    <i class="fas fa-bell"></i>
                    <?php if (count($notifications) > 0): ?>
                        <span class="notification-count"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item" onclick="markNotificationRead(<?php echo $notification['id']; ?>)">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="user-avatar" onclick="toggleUserDropdown()"><?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?></div>
                <div class="user-dropdown" id="userDropdown">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                    <a href="settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                    <a href="notifications.php" class="dropdown-item">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                    <a href="support.php" class="dropdown-item">
                        <i class="fas fa-question-circle"></i>
                        Help & Support
                    </a>
                    <a href="logout.php" class="dropdown-item danger">
                        <i class="fas fa-sign-out-alt"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Ubiaza</span>
            </div>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="transfer.php" class="nav-item active">
                    <i class="fas fa-exchange-alt"></i>
                    Send Money
                </a>
                <a href="airtime.php" class="nav-item">
                    <i class="fas fa-mobile-alt"></i>
                    Airtime & Data
                </a>
                <a href="bills.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    Pay Bills
                </a>
                <a href="transactions.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    Transactions
                </a>
                <a href="support.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    Support
                </a>
            </div>
            <a href="logout.php" class="nav-item sign-out">
                <i class="fas fa-sign-out-alt"></i>
                Sign Out
            </a>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Transfer Hero Section -->
            <div class="transfer-hero">
                <div class="hero-content">
                    <h1 class="hero-title">Send Money</h1>
                    <p class="hero-subtitle">Fast, secure, and affordable transfers to anyone, anywhere</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-value"><?php echo number_format($stats['total_transfers'] ?? 0); ?></div>
                            <div class="hero-stat-label">Total Transfers</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-value">₦<?php echo number_format($stats['total_sent'] ?? 0, 2); ?></div>
                            <div class="hero-stat-label">Amount Sent</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-value">₦<?php echo number_format($user['available_balance'], 2); ?></div>
                            <div class="hero-stat-label">Available Balance</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verification Banner -->
            <?php if (!$user['bvn_verified'] || !$user['nin_verified']): ?>
            <div class="verification-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="content">
                    <h4>Complete Your Verification</h4>
                    <p>Verify your BVN and NIN to unlock bank transfers and higher limits</p>
                </div>
                <button class="verify-btn" onclick="window.location.href='verify.php'">
                    Verify Now
                </button>
            </div>
            <?php endif; ?>

            <!-- Transfer Methods -->
            <div class="transfer-methods">
                <div class="transfer-method active" data-method="ubiaza">
                    <div class="method-header">
                        <div class="method-icon ubiaza">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="method-info">
                            <h3>Ubiaza Transfer</h3>
                            <p>Send money instantly to other Ubiaza users using their email address</p>
                        </div>
                    </div>
                    <ul class="method-features">
                        <li><i class="fas fa-check"></i> Instant transfer</li>
                        <li><i class="fas fa-check"></i> ₦10 fee</li>
                        <li><i class="fas fa-check"></i> 24/7 availability</li>
                        <li><i class="fas fa-check"></i> Real-time notifications</li>
                    </ul>
                    <div class="method-fee">
                        <div class="fee-amount">₦10</div>
                        <div class="fee-label">Transfer Fee</div>
                    </div>
                </div>

                <div class="transfer-method" data-method="bank">
                    <div class="method-header">
                        <div class="method-icon bank">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="method-info">
                            <h3>Bank Transfer</h3>
                            <p>Send money to any Nigerian bank account with instant settlement</p>
                        </div>
                    </div>
                    <ul class="method-features">
                        <li><i class="fas fa-check"></i> Instant settlement</li>
                        <li><i class="fas fa-check"></i> ₦50 fee</li>
                        <li><i class="fas fa-check"></i> Supports all Nigerian banks</li>
                        <li><i class="fas fa-check"></i> Secure verification</li>
                    </ul>
                    <div class="method-fee">
                        <div class="fee-amount">₦50</div>
                        <div class="fee-label">Transfer Fee</div>
                    </div>
                </div>

                <div class="transfer-method" data-method="virtual">
                    <div class="method-header">
                        <div class="method-icon virtual">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="method-info">
                            <h3>Virtual Account Transfer</h3>
                            <p>Send money to a virtual account using the account number</p>
                        </div>
                    </div>
                    <ul class="method-features">
                        <li><i class="fas fa-check"></i> Instant transfer</li>
                        <li><i class="fas fa-check"></i> ₦20 fee</li>
                        <li><i class="fas fa-check"></i> Secure and private</li>
                        <li><i class="fas fa-check"></i> Real-time notifications</li>
                    </ul>
                    <div class="method-fee">
                        <div class="fee-amount">₦20</div>
                        <div class="fee-label">Transfer Fee</div>
                    </div>
                </div>
            </div>

            <!-- Recent Recipients -->
            <?php if (!empty($recent_recipients)): ?>
            <div class="recent-recipients">
                <div class="recipients-header">
                    <h3 class="section-title">Recent Recipients</h3>
                </div>
                <div class="recipients-grid">
                    <?php foreach ($recent_recipients as $recipient): ?>
                    <div class="recipient-card" data-type="<?php echo htmlspecialchars($recipient['type']); ?>"
                        data-email="<?php echo htmlspecialchars($recipient['email'] ?? ''); ?>"
                        data-account-number="<?php echo htmlspecialchars($recipient['type'] === 'virtual' ? $recipient['va_number'] : $recipient['account_number'] ?? ''); ?>"
                        data-bank-code="<?php echo htmlspecialchars($recipient['bank_code'] ?? ''); ?>"
                        data-account-name="<?php echo htmlspecialchars($recipient['account_name'] ?? ''); ?>">
                        <div class="recipient-header">
                            <div class="recipient-avatar <?php echo $recipient['type']; ?>">
                                <?php echo substr($recipient['name'], 0, 1); ?>
                            </div>
                            <div class="recipient-details">
                                <h4><?php echo htmlspecialchars($recipient['nickname'] ?: $recipient['name']); ?></h4>
                                <p>
                                    <?php
                                    if ($recipient['type'] === 'ubiaza') {
                                        echo htmlspecialchars($recipient['email']);
                                    } elseif ($recipient['type'] === 'virtual') {
                                        echo htmlspecialchars($recipient['va_number'] . ' - Virtual Account');
                                    } else {
                                        echo htmlspecialchars($recipient['account_number'] . ' - ' . $recipient['account_name']);
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="recipient-meta">
                            <span class="last-sent">
                                Last sent: <?php echo $recipient['last_used'] ? date('M d, Y', strtotime($recipient['last_used'])) : 'Never'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transfer Form -->
            <div class="transfer-form-container">
                <div class="form-tabs">
                    <div class="form-tab active" data-tab="ubiaza">Ubiaza Transfer</div>
                    <div class="form-tab" data-tab="bank">Bank Transfer</div>
                    <div class="form-tab" data-tab="virtual">Virtual Account</div>
                </div>

                <!-- Ubiaza Transfer Form -->
                <form id="ubiaza-transfer-form" class="form-section active">
                    <div class="form-group">
                        <label for="ubiaza-email">Recipient Email</label>
                        <input type="email" id="ubiaza-email" name="recipient_email" placeholder="Enter recipient's email" required>
                        <i class="fas fa-envelope input-icon"></i>
                        <div class="error-message" id="ubiaza-email-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="ubiaza-amount">Amount (₦)</label>
                        <input type="number" id="ubiaza-amount" name="amount" placeholder="Enter amount" min="100" step="0.01" required>
                        <i class="fas fa-money-bill input-icon"></i>
                        <div class="error-message" id="ubiaza-amount-error"></div>
                        <div class="amount-presets">
                            <div class="amount-preset" data-amount="1000">₦1,000</div>
                            <div class="amount-preset" data-amount="5000">₦5,000</div>
                            <div class="amount-preset" data-amount="10000">₦10,000</div>
                            <div class="amount-preset" data-amount="25000">₦25,000</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ubiaza-note">Note (Optional)</label>
                        <input type="text" id="ubiaza-note" name="note" placeholder="Add a note">
                        <i class="fas fa-sticky-note input-icon"></i>
                    </div>
                    <div class="transfer-summary" id="ubiaza-summary" style="display: none;">
                        <div class="summary-row">
                            <span>Amount</span>
                            <span id="ubiaza-summary-amount">₦0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Fee</span>
                            <span id="ubiaza-summary-fee">₦10.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="ubiaza-summary-total">₦0.00</span>
                        </div>
                    </div>
                    <button type="button" class="submit-btn" id="ubiaza-submit">
                        <span class="btn-text"><i class="fas fa-paper-plane"></i> Review Transfer</span>
                    </button>
                </form>

                <!-- Bank Transfer Form -->
                <form id="bank-transfer-form" class="form-section">
                    <div class="form-group">
                        <label for="bank-account">Account Number</label>
                        <input type="text" id="bank-account" name="account_number" placeholder="Enter account number" maxlength="10" required>
                        <i class="fas fa-university input-icon"></i>
                        <div class="error-message" id="bank-account-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="bank-code">Select Bank</label>
                        <select id="bank-code" name="bank_code" required>
                            <option value="">Select a bank</option>
                        </select>
                        <i class="fas fa-chevron-down input-icon"></i>
                        <div class="error-message" id="bank-code-error"></div>
                        <div class="account-name-display" id="account-name-display">
                            Account Name: <span id="account-name"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bank-amount">Amount (₦)</label>
                        <input type="number" id="bank-amount" name="amount" placeholder="Enter amount" min="100" step="0.01" required>
                        <i class="fas fa-money-bill input-icon"></i>
                        <div class="error-message" id="bank-amount-error"></div>
                        <div class="amount-presets">
                            <div class="amount-preset" data-amount="1000">₦1,000</div>
                            <div class="amount-preset" data-amount="5000">₦5,000</div>
                            <div class="amount-preset" data-amount="10000">₦10,000</div>
                            <div class="amount-preset" data-amount="25000">₦25,000</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bank-note">Note (Optional)</label>
                        <input type="text" id="bank-note" name="note" placeholder="Add a note">
                        <i class="fas fa-sticky-note input-icon"></i>
                    </div>
                    <div class="transfer-summary" id="bank-summary" style="display: none;">
                        <div class="summary-row">
                            <span>Amount</span>
                            <span id="bank-summary-amount">₦0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Fee</span>
                            <span id="bank-summary-fee">₦50.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="bank-summary-total">₦0.00</span>
                        </div>
                    </div>
                    <button type="button" class="submit-btn" id="bank-submit" <?php if (!$user['bvn_verified'] || !$user['nin_verified']) echo 'disabled'; ?>>
                        <span class="btn-text"><i class="fas fa-paper-plane"></i> Review Transfer</span>
                    </button>
                </form>

                <!-- Virtual Account Transfer Form -->
                <form id="virtual-transfer-form" class="form-section">
                    <div class="form-group">
                        <label for="virtual-account">Virtual Account Number</label>
                        <input type="text" id="virtual-account" name="account_number" placeholder="Enter virtual account number" required>
                        <i class="fas fa-wallet input-icon"></i>
                        <div class="error-message" id="virtual-account-error"></div>
                        <div class="account-name-display" id="virtual-account-name-display">
                            Account Name: <span id="virtual-account-name"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="virtual-amount">Amount (₦)</label>
                        <input type="number" id="virtual-amount" name="amount" placeholder="Enter amount" min="100" step="0.01" required>
                        <i class="fas fa-money-bill input-icon"></i>
                        <div class="error-message" id="virtual-amount-error"></div>
                        <div class="amount-presets">
                            <div class="amount-preset" data-amount="1000">₦1,000</div>
                            <div class="amount-preset" data-amount="5000">₦5,000</div>
                            <div class="amount-preset" data-amount="10000">₦10,000</div>
                            <div class="amount-preset" data-amount="25000">₦25,000</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="virtual-note">Note (Optional)</label>
                        <input type="text" id="virtual-note" name="note" placeholder="Add a note">
                        <i class="fas fa-sticky-note input-icon"></i>
                    </div>
                    <div class="transfer-summary" id="virtual-summary" style="display: none;">
                        <div class="summary-row">
                            <span>Amount</span>
                            <span id="virtual-summary-amount">₦0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Fee</span>
                            <span id="virtual-summary-fee">₦20.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="virtual-summary-total">₦0.00</span>
                        </div>
                    </div>
                    <button type="button" class="submit-btn" id="virtual-submit">
                        <span class="btn-text"><i class="fas fa-paper-plane"></i> Review Transfer</span>
                    </button>
                </form>
            </div>

            <!-- Transaction History -->
            <?php if (!empty($transactions)): ?>
            <div class="transaction-history">
                <h3 class="section-title">Recent Transactions</h3>
                <?php foreach ($transactions as $txn): ?>
                <div class="transaction-item">
                    <?php
                    $recipient = $txn['recipient_email'] ?: ($txn['bank_details'] ? json_decode($txn['bank_details'], true)['account_name'] : ($txn['type'] === 'virtual' ? 'Virtual Account' : 'Unknown'));
                    $amount = number_format($txn['amount'], 2);
                    $fee = number_format($txn['fee'], 2);
                    echo ucfirst($txn['type']) . " of ₦{$amount} to {$recipient} (Fee: ₦{$fee}, Status: {$txn['status']}) on " . date('M d, Y H:i', strtotime($txn['created_at']));
                    ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Confirmation Modal -->
            <div class="modal-overlay" id="confirmation-modal">
                <div class="confirmation-modal">
                    <div class="modal-icon"><i class="fas fa-paper-plane"></i></div>
                    <h3 class="modal-title">Confirm Transfer</h3>
                    <div class="modal-details" id="modal-details"></div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="save-beneficiary" name="save_beneficiary"> Save as beneficiary
                        </label>
                        <input type="text" id="beneficiary-nickname" name="beneficiary_nickname" placeholder="Nickname (optional)" style="display: none; margin-top: 8px;">
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn cancel-btn" onclick="closeModal()">Cancel</button>
                        <button class="modal-btn confirm-btn" id="confirm-transfer">Confirm</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        const AVAILABLE_BALANCE = <?php echo $user['available_balance']; ?>;
        let currentMethod = 'ubiaza';
        let bankList = [];

        // Toggle sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay')?.classList.toggle('active');
        }

        // Toggle user dropdown
        function toggleUserDropdown() {
            const dropdowns = [document.getElementById('userDropdown'), document.getElementById('desktopUserDropdown')];
            dropdowns.forEach(dropdown => {
                if (dropdown) dropdown.classList.toggle('active');
            });
        }

        // Toggle notification dropdown
        function toggleNotificationDropdown() {
            const dropdowns = [document.getElementById('notificationDropdown'), document.getElementById('desktopNotificationDropdown')];
            dropdowns.forEach(dropdown => {
                if (dropdown) dropdown.classList.toggle('active');
            });
        }

        // Mark notification as read
        async function markNotificationRead(notificationId) {
            try {
                const response = await fetch('api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'mark_read',
                        notification_id: notificationId,
                        csrf_token: CSRF_TOKEN
                    }),
                    credentials: 'include'
                });

                if (response.ok) {
                    const dropdowns = [document.getElementById('notificationDropdown'), document.getElementById('desktopNotificationDropdown')];
                    dropdowns.forEach(dropdown => {
                        if (dropdown) {
                            const item = dropdown.querySelector(`.notification-item[onclick*="${notificationId}"]`);
                            if (item) item.remove();
                        }
                    });
                    updateNotificationCount();
                }
            } catch (error) {
                showNotification('Failed to mark notification as read', 'error');
            }
        }

        // Update notification count
        function updateNotificationCount() {
            const countElements = document.querySelectorAll('.notification-count');
            const remainingNotifications = document.querySelectorAll('.notification-item').length;
            countElements.forEach(element => {
                if (remainingNotifications > 0) {
                    element.textContent = remainingNotifications;
                    element.style.display = 'flex';
                } else {
                    element.style.display = 'none';
                }
            });
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#22c55e' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10000;
                font-weight: 500;
                max-width: 300px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Fetch bank list
        async function fetchBanks() {
            try {
                const response = await fetch('api/banks.json');
                bankList = await response.json();
                const bankSelect = document.getElementById('bank-code');
                bankList.forEach(bank => {
                    const option = document.createElement('option');
                    option.value = bank.code;
                    option.textContent = bank.name;
                    bankSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Failed to fetch banks:', error);
                showNotification('Failed to load bank list', 'error');
            }
        }

        // Initialize form
        function initializeForm() {
            const methodElements = document.querySelectorAll('.transfer-method');
            const tabElements = document.querySelectorAll('.form-tab');
            const formSections = document.querySelectorAll('.form-section');

            methodElements.forEach(method => {
                method.addEventListener('click', () => {
                    methodElements.forEach(m => m.classList.remove('active'));
                    method.classList.add('active');
                    currentMethod = method.dataset.method;
                    tabElements.forEach(tab => tab.classList.remove('active'));
                    document.querySelector(`.form-tab[data-tab="${currentMethod}"]`).classList.add('active');
                    formSections.forEach(section => section.classList.remove('active'));
                    document.getElementById(`${currentMethod}-transfer-form`).classList.add('active');
                    resetForm();
                });
            });

            tabElements.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabElements.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    currentMethod = tab.dataset.tab;
                    methodElements.forEach(m => m.classList.remove('active'));
                    document.querySelector(`.transfer-method[data-method="${currentMethod}"]`).classList.add('active');
                    formSections.forEach(section => section.classList.remove('active'));
                    document.getElementById(`${currentMethod}-transfer-form`).classList.add('active');
                    resetForm();
                });
            });

            // Amount presets
            document.querySelectorAll('.amount-preset').forEach(preset => {
                preset.addEventListener('click', () => {
                    const amountInput = document.getElementById(`${currentMethod}-amount`);
                    amountInput.value = preset.dataset.amount;
                    updateSummary();
                });
            });

            // Recipient card click
            document.querySelectorAll('.recipient-card').forEach(card => {
                card.addEventListener('click', () => {
                    currentMethod = card.dataset.type;
                    methodElements.forEach(m => m.classList.remove('active'));
                    document.querySelector(`.transfer-method[data-method="${currentMethod}"]`).classList.add('active');
                    tabElements.forEach(t => t.classList.remove('active'));
                    document.querySelector(`.form-tab[data-tab="${currentMethod}"]`).classList.add('active');
                    formSections.forEach(section => section.classList.remove('active'));
                    document.getElementById(`${currentMethod}-transfer-form`).classList.add('active');

                    if (currentMethod === 'ubiaza') {
                        document.getElementById('ubiaza-email').value = card.dataset.email;
                    } else if (currentMethod === 'bank') {
                        document.getElementById('bank-account').value = card.dataset.accountNumber;
                        document.getElementById('bank-code').value = card.dataset.bankCode;
                        const accountNameDisplay = document.getElementById('account-name-display');
                        const accountName = document.getElementById('account-name');
                        accountName.textContent = card.dataset.accountName;
                        accountNameDisplay.classList.add('show');
                    } else if (currentMethod === 'virtual') {
                        document.getElementById('virtual-account').value = card.dataset.accountNumber;
                        const virtualAccountNameDisplay = document.getElementById('virtual-account-name-display');
                        const virtualAccountName = document.getElementById('virtual-account-name');
                        virtualAccountName.textContent = card.dataset.accountName;
                        virtualAccountNameDisplay.classList.add('show');
                    }
                });
            });

            // Real-time amount validation
            ['ubiaza-amount', 'bank-amount', 'virtual-amount'].forEach(id => {
                document.getElementById(id).addEventListener('input', updateSummary);
            });

            // Bank account lookup
            document.getElementById('bank-account').addEventListener('input', debounce(async () => {
                const accountNumber = document.getElementById('bank-account').value;
                const bankCode = document.getElementById('bank-code').value;
                if (accountNumber.length === 10 && bankCode) {
                    await lookupAccount('bank');
                } else {
                    document.getElementById('account-name-display').classList.remove('show');
                }
            }, 500));

            document.getElementById('bank-code').addEventListener('change', async () => {
                const accountNumber = document.getElementById('bank-account').value;
                if (accountNumber.length === 10) {
                    await lookupAccount('bank');
                }
            });

            // Virtual account lookup
            document.getElementById('virtual-account').addEventListener('input', debounce(async () => {
                const accountNumber = document.getElementById('virtual-account').value;
                if (accountNumber.length >= 10) {
                    await lookupAccount('virtual');
                } else {
                    document.getElementById('virtual-account-name-display').classList.remove('show');
                }
            }, 500));

            // Submit buttons
            document.getElementById('ubiaza-submit').addEventListener('click', () => reviewTransfer('ubiaza'));
            document.getElementById('bank-submit').addEventListener('click', () => reviewTransfer('bank'));
            document.getElementById('virtual-submit').addEventListener('click', () => reviewTransfer('virtual'));

            // Beneficiary nickname toggle
            document.getElementById('save-beneficiary').addEventListener('change', function() {
                document.getElementById('beneficiary-nickname').style.display = this.checked ? 'block' : 'none';
            });
        }

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Reset form
        function resetForm() {
            document.getElementById('ubiaza-transfer-form').reset();
            document.getElementById('bank-transfer-form').reset();
            document.getElementById('virtual-transfer-form').reset();
            document.getElementById('account-name-display').classList.remove('show');
            document.getElementById('virtual-account-name-display').classList.remove('show');
            ['ubiaza-email', 'ubiaza-amount', 'bank-account', 'bank-amount', 'bank-code', 'virtual-account', 'virtual-amount'].forEach(id => {
                document.getElementById(`${id}-error`).textContent = '';
                document.getElementById(id).parentElement.classList.remove('error');
            });
            updateSummary();
        }

        // Update transfer summary
        function updateSummary() {
            const amountInput = document.getElementById(`${currentMethod}-amount`);
            const amount = parseFloat(amountInput.value) || 0;
            const fee = currentMethod === 'ubiaza' ? 10 : currentMethod === 'bank' ? 50 : 20;
            const total = amount + fee;

            const summary = document.getElementById(`${currentMethod}-summary`);
            document.getElementById(`${currentMethod}-summary-amount`).textContent = `₦${amount.toLocaleString('en-NG', { minimumFractionDigits: 2 })}`;
            document.getElementById(`${currentMethod}-summary-fee`).textContent = `₦${fee.toLocaleString('en-NG', { minimumFractionDigits: 2 })}`;
            document.getElementById(`${currentMethod}-summary-total`).textContent = `₦${total.toLocaleString('en-NG', { minimumFractionDigits: 2 })}`;
            summary.style.display = amount > 0 ? 'block' : 'none';

            const submitBtn = document.getElementById(`${currentMethod}-submit`);
            submitBtn.disabled = amount <= 0 || total > AVAILABLE_BALANCE;
        }

        // Account lookup
        async function lookupAccount(type) {
            const accountNumber = document.getElementById(`${type}-account`).value;
            const bankCode = type === 'bank' ? document.getElementById('bank-code').value : null;
            const accountNameDisplay = document.getElementById(`${type}-account-name-display`);
            const accountName = document.getElementById(`${type}-account-name`);
            const error = document.getElementById(`${type}-account-error`);
            const formGroup = document.getElementById(`${type}-account`).parentElement;

            if ((type === 'bank' && (accountNumber.length !== 10 || !bankCode)) || (type === 'virtual' && accountNumber.length < 10)) {
                error.textContent = `Please enter a valid ${type} account number${type === 'bank' ? ' and select a bank' : ''}`;
                formGroup.classList.add('error');
                accountNameDisplay.classList.remove('show');
                return;
            }

            try {
                const response = await fetch(`api/wallet/balance.php?action=lookup_${type}_account`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        account_number: accountNumber,
                        bank_code: bankCode,
                        csrf_token: CSRF_TOKEN
                    })
                });
                const result = await response.json();
                if (result.success && result.account_name) {
                    accountName.textContent = result.account_name;
                    accountNameDisplay.classList.add('show');
                    error.textContent = '';
                    formGroup.classList.remove('error');
                } else {
                    error.textContent = result.error || 'Invalid account details';
                    formGroup.classList.add('error');
                    accountNameDisplay.classList.remove('show');
                }
            } catch (error) {
                error.textContent = 'Failed to verify account';
                formGroup.classList.add('error');
                accountNameDisplay.classList.remove('show');
            }
        }

        // Review transfer
        async function reviewTransfer(method) {
            const form = document.getElementById(`${method}-transfer-form`);
            const amount = parseFloat(document.getElementById(`${method}-amount`).value) || 0;
            const fee = method === 'ubiaza' ? 10 : method === 'bank' ? 50 : 20;
            const total = amount + fee;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (total > AVAILABLE_BALANCE) {
                document.getElementById(`${method}-amount-error`).textContent = 'Insufficient balance';
                document.getElementById(`${method}-amount`).parentElement.classList.add('error');
                return;
            }

            const data = {
                recipient_type: method,
                amount: amount,
                note: document.getElementById(`${method}-note`).value,
                csrf_token: CSRF_TOKEN
            };

            if (method === 'ubiaza') {
                data.recipient_email = document.getElementById('ubiaza-email').value;
                if (!data.recipient_email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    document.getElementById('ubiaza-email-error').textContent = 'Invalid email address';
                    document.getElementById('ubiaza-email').parentElement.classList.add('error');
                    return;
                }
            } else if (method === 'bank') {
                data.account_number = document.getElementById('bank-account').value;
                data.bank_code = document.getElementById('bank-code').value;
                data.account_name = document.getElementById('account-name').textContent;
                if (!data.account_number || !data.bank_code || !data.account_name) {
                    document.getElementById('bank-account-error').textContent = 'Please verify account details';
                    document.getElementById('bank-account').parentElement.classList.add('error');
                    return;
                }
            } else if (method === 'virtual') {
                data.account_number = document.getElementById('virtual-account').value;
                data.account_name = document.getElementById('virtual-account-name').textContent;
                if (!data.account_number || !data.account_name) {
                    document.getElementById('virtual-account-error').textContent = 'Please verify account details';
                    document.getElementById('virtual-account').parentElement.classList.add('error');
                    return;
                }
            }

            const modalDetails = document.getElementById('modal-details');
            modalDetails.innerHTML = `
                <div class="modal-row">
                    <span>Amount</span>
                    <span>₦${amount.toLocaleString('en-NG', { minimumFractionDigits: 2 })}</span>
                </div>
                <div class="modal-row">
                    <span>Fee</span>
                    <span>₦${fee.toLocaleString('en-NG', { minimumFractionDigits: 2 })}</span>
                </div>
                <div class="modal-row">
                    <span>Recipient</span>
                    <span>${method === 'ubiaza' ? data.recipient_email : (method === 'virtual' ? `Virtual Account (${data.account_number})` : `${data.account_name} (${data.account_number})`)}</span>
                </div>
                <div class="modal-row">
                    <span>Note</span>
                    <span>${data.note || 'None'}</span>
                </div>
                <div class="modal-row total">
                    <span>Total</span>
                    <span>₦${total.toLocaleString('en-NG', { minimumFractionDigits: 2 })}</span>
                </div>
            `;

            document.getElementById('confirmation-modal').classList.add('show');
            document.getElementById('confirm-transfer').onclick = () => confirmTransfer(data);
            document.getElementById('save-beneficiary').checked = false;
            document.getElementById('beneficiary-nickname').value = '';
            document.getElementById('beneficiary-nickname').style.display = 'none';
        }

        // Confirm transfer
        async function confirmTransfer(data) {
            const submitBtn = document.getElementById(`${data.recipient_type}-submit`);
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            try {
                const response = await fetch('api/wallet/balance.php?action=transfer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.success) {
                    if (data.recipient_type === 'ubiaza') {
                        const recipientName = data.recipient_email.split('@')[0];
                        data.name = recipientName.charAt(0).toUpperCase() + recipientName.slice(1);
                    } else {
                        data.name = data.account_name;
                    }

                    if (document.getElementById('save-beneficiary').checked) {
                        const beneficiaryData = {
                            type: data.recipient_type,
                            name: data.name,
                            email: data.recipient_type === 'ubiaza' ? data.recipient_email : null,
                            account_number: data.recipient_type !== 'ubiaza' ? data.account_number : null,
                            bank_code: data.recipient_type === 'bank' ? data.bank_code : null,
                            account_name: data.recipient_type !== 'ubiaza' ? data.account_name : null,
                            nickname: document.getElementById('beneficiary-nickname').value || null,
                            is_verified: 1,
                            csrf_token: CSRF_TOKEN
                        };
                        await fetch('api/wallet/balance.php?action=save_beneficiary', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(beneficiaryData)
                        });
                    }

                    closeModal();
                    resetForm();
                    showSuccess();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    const errorField = data.recipient_type === 'ubiaza' ? 'ubiaza-email' : (data.recipient_type === 'bank' ? 'bank-account' : 'virtual-account');
                    document.getElementById(`${errorField}-error`).textContent = result.error || 'Transfer failed';
                    document.getElementById(errorField).parentElement.classList.add('error');
                    closeModal();
                }
            } catch (error) {
                const errorField = data.recipient_type === 'ubiaza' ? 'ubiaza-email' : (data.recipient_type === 'bank' ? 'bank-account' : 'virtual-account');
                document.getElementById(`${errorField}-error`).textContent = 'Transfer failed';
                document.getElementById(errorField).parentElement.classList.add('error');
                closeModal();
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('confirmation-modal').classList.remove('show');
        }

        // Show success animation
        function showSuccess() {
            const modal = document.getElementById('confirmation-modal');
            modal.innerHTML = `
                <div class="confirmation-modal">
                    <div class="modal-icon success-icon"><i class="fas fa-check-circle"></i></div>
                    <h3 class="modal-title">Transfer Successful</h3>
                    <p>Your transfer has been initiated successfully.</p>
                </div>
            `;
            setTimeout(closeModal, 2000);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            fetchBanks();
            initializeForm();
        });
    </script>
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
</body>
</html>