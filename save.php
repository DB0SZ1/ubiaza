<?php
require_once 'api/config.php';
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: sign.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch user data
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, bvn_verified, nin_verified, email_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch wallet balance
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$wallet = $stmt->get_result()->fetch_assoc();
$balance = $wallet ? number_format($wallet['balance'], 2) : '0.00';

// Fetch Owealth savings balance
$stmt = $conn->prepare("SELECT balance FROM owealth_savings WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$savings = $stmt->get_result()->fetch_assoc();
$savings_balance = $savings ? number_format($savings['balance'], 2) : '0.00';

// Fetch recent transactions
$stmt = $conn->prepare("SELECT type, amount, status, reference, created_at FROM transactions WHERE user_id = ? OR recipient_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch cashback earned this month
$stmt = $conn->prepare("SELECT SUM(cashback_amount) as total_cashback FROM transactions WHERE user_id = ? AND cashback_amount > 0 AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cashback_result = $stmt->get_result()->fetch_assoc();
$monthly_cashback = $cashback_result ? number_format($cashback_result['total_cashback'], 2) : '0.00';

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza - Your Smart Banking Partner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <script src="assets/js/dashboard.js"></script>
    <style>
        /* Enhanced Dashboard Styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 18px;
        }

        .stat-icon.savings { background: #e8f5e8; color: #22c55e; }
        .stat-icon.cashback { background: #fef3c7; color: #f59e0b; }
        .stat-icon.transfers { background: #e0f2fe; color: #0ea5e9; }
        .stat-icon.bills { background: #f3e8ff; color: #8b5cf6; }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Enhanced Quick Actions - 3x3 grid for mobile */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .quick-item {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.2s ease;
            position: relative;
        }

        .quick-item:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .quick-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            font-size: 16px;
            color: var(--white);
        }

        .quick-icon.transfer { background: linear-gradient(45deg, #0ea5e9, #0284c7); }
        .quick-icon.airtime { background: linear-gradient(45deg, #10b981, #059669); }
        .quick-icon.bills { background: linear-gradient(45deg, #8b5cf6, #7c3aed); }
        .quick-icon.card { background: linear-gradient(45deg, #f59e0b, #d97706); }
        .quick-icon.savings { background: linear-gradient(45deg, #22c55e, #16a34a); }
        .quick-icon.investment { background: linear-gradient(45deg, #ef4444, #dc2626); }
        .quick-icon.pay { background: linear-gradient(45deg, #6366f1, #4f46e5); }
        .quick-icon.insurance { background: linear-gradient(45deg, #06b6d4, #0891b2); }
        .quick-icon.loans { background: linear-gradient(45deg, #84cc16, #65a30d); }

        .quick-text {
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
        }

        .cashback-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #f59e0b;
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 6px;
            font-weight: 600;
        }

        /* Owealth Savings Card */
        .owealth-card {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .owealth-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .owealth-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .owealth-title {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }

        .owealth-rate {
            background: rgba(255,255,255,0.2);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .owealth-balance {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .owealth-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }

        /* Debit Card Preview */
        .card-preview {
            background: linear-gradient(135deg, #1e40af 0%, #3730a3 100%);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            position: relative;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-logo {
            font-size: 14px;
            font-weight: 700;
        }

        .card-type {
            font-size: 12px;
            opacity: 0.8;
        }

        .card-number {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-holder {
            font-size: 12px;
            font-weight: 500;
        }

        .card-expiry {
            font-size: 12px;
        }

        .card-benefits {
            background: var(--white);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            font-size: 13px;
            color: var(--text-primary);
        }

        .benefit-icon {
            color: #22c55e;
            font-size: 14px;
        }

        /* Promo Banner */
        .promo-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .promo-banner::before {
            content: '🎉';
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
        }

        .promo-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .promo-text {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Transaction Item Enhancements */
        .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .transaction-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
        }

        .transaction-icon.transfer { background: #0ea5e9; }
        .transaction-icon.airtime { background: #10b981; }
        .transaction-icon.bills { background: #8b5cf6; }
        .transaction-icon.cashback { background: #f59e0b; }

        .transaction-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--text-primary);
        }

        .transaction-details {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .transaction-amount {
            text-align: right;
        }

        .amount-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .amount-status {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-top: 2px;
            display: inline-block;
        }

        .status-success { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fecaca; color: #991b1b; }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 16px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 20px;
            }

            .owealth-balance {
                font-size: 24px;
            }

            .card-preview {
                padding: 16px;
            }

            .card-number {
                font-size: 14px;
            }
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
                <i class="fas fa-bell"></i>
                <div class="theme-toggle">
                    <label class="theme-toggle-label">
                        <input type="checkbox" class="theme-toggle-checkbox" id="themeToggle">
                        <span class="theme-toggle-slider"></span>
                    </label>
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
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="transfer.php" class="nav-item">
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
                <a href="owealth.php" class="nav-item">
                    <i class="fas fa-piggy-bank"></i>
                    Owealth Savings
                </a>
                <a href="investments.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    Investments
                </a>
                <a href="cards.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    Debit Card
                </a>
                <a href="pay_transfer.php" class="nav-item">
                    <i class="fas fa-qrcode"></i>
                    Pay With Transfer
                </a>
                <a href="loans.php" class="nav-item">
                    <i class="fas fa-hand-holding-usd"></i>
                    Loans
                </a>
                <a href="insurance.php" class="nav-item">
                    <i class="fas fa-shield-alt"></i>
                    Insurance
                </a>
                <a href="transactions.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    Transaction History
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
                <a href="support.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    Help & Support
                </a>
            </div>
            <a href="logout.php" class="nav-item sign-out">
                <i class="fas fa-sign-out-alt"></i>
                Sign Out
            </a>
        </nav>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <div class="top-header">
                <div class="header-left">
                    <h1>Dashboard</h1>
                </div>
                <div class="header-right">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="theme-toggle">
                        <label class="theme-toggle-label">
                            <input type="checkbox" class="theme-toggle-checkbox" id="desktopThemeToggle">
                            <span class="theme-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="user-avatar" onclick="toggleUserDropdown()"><?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?></div>
                    <div class="user-dropdown" id="desktopUserDropdown">
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

            <div class="content-grid">
                <div class="left-content">
                    <!-- Verification Status -->
                    <div class="verification-status">
                        <h3 class="section-title">
                            <i class="fas fa-shield-check"></i>
                            Account Verification
                        </h3>
                        <div class="verification-item">
                            <i class="fas fa-envelope <?php echo $user['email_verified'] ? 'verified' : 'unverified'; ?>"></i>
                            <span>Email: <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?></span>
                            <?php if (!$user['email_verified']): ?>
                                <button class="btn-small" id="resendVerification">Resend</button>
                            <?php endif; ?>
                        </div>
                        <div class="verification-item">
                            <i class="fas fa-id-card <?php echo $user['bvn_verified'] ? 'verified' : 'unverified'; ?>"></i>
                            <span>BVN: <?php echo $user['bvn_verified'] ? 'Verified' : 'Unverified'; ?></span>
                            <?php if (!$user['bvn_verified']): ?>
                                <button class="btn-small" onclick="window.location.href='verify.php?type=bvn'">Verify</button>
                            <?php endif; ?>
                        </div>
                        <div class="verification-item">
                            <i class="fas fa-id-card-alt <?php echo $user['nin_verified'] ? 'verified' : 'unverified'; ?>"></i>
                            <span>NIN: <?php echo $user['nin_verified'] ? 'Verified' : 'Unverified'; ?></span>
                            <?php if (!$user['nin_verified']): ?>
                                <button class="btn-small" onclick="window.location.href='verify.php?type=nin'">Verify</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Main Balance Card -->
                    <div class="balance-card">
                        <div class="balance-header">
                            <h2 class="balance-title">Available Balance</h2>
                            <button class="eye-icon">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        <div class="balance-amount">₦<?php echo $balance; ?></div>
                        <div class="balance-status">Instantly available for all transactions</div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon savings">
                                <i class="fas fa-piggy-bank"></i>
                            </div>
                            <div class="stat-value">₦<?php echo $savings_balance; ?></div>
                            <div class="stat-label">Owealth Savings</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon cashback">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div class="stat-value">₦<?php echo $monthly_cashback; ?></div>
                            <div class="stat-label">Cashback This Month</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon transfers">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="stat-value">100%</div>
                            <div class="stat-label">Transfer Success Rate</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bills">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="stat-value">Free</div>
                            <div class="stat-label">All Bill Payments</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="action-btn send-btn" onclick="window.location.href='transfer.php'">
                            <i class="fas fa-paper-plane"></i>
                            Send Money
                        </button>
                        <button class="action-btn add-btn" onclick="window.location.href='add_money.php'">
                            <i class="fas fa-plus"></i>
                            Add Money
                        </button>
                    </div>

                    <!-- Quick Actions - 3x3 Grid -->
                    <div class="quick-actions">
                        <h3 class="section-title">Quick Actions</h3>
                        <div class="quick-grid">
                            <a href="transfer.php" class="quick-item">
                                <div class="quick-icon transfer">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="quick-text">Bank Transfer</div>
                            </a>
                            <a href="airtime.php" class="quick-item">
                                <div class="quick-icon airtime">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="quick-text">Airtime & Data</div>
                                <div class="cashback-badge">6%</div>
                            </a>
                            <a href="bills.php" class="quick-item">
                                <div class="quick-icon bills">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="quick-text">Pay Bills</div>
                            </a>
                            <a href="cards.php" class="quick-item">
                                <div class="quick-icon card">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="quick-text">Get Card</div>
                            </a>
                            <a href="owealth.php" class="quick-item">
                                <div class="quick-icon savings">
                                    <i class="fas fa-piggy-bank"></i>
                                </div>
                                <div class="quick-text">Owealth</div>
                            </a>
                            <a href="investments.php" class="quick-item">
                                <div class="quick-icon investment">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="quick-text">Invest</div>
                            </a>
                            <a href="pay_transfer.php" class="quick-item">
                                <div class="quick-icon pay">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="quick-text">Pay Transfer</div>
                                <div class="cashback-badge">Cash</div>
                            </a>
                            <a href="insurance.php" class="quick-item">
                                <div class="quick-icon insurance">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="quick-text">Insurance</div>
                            </a>
                            <a href="loans.php" class="quick-item">
                                <div class="quick-icon loans">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                                <div class="quick-text">Quick Loans</div>
                            </a>
                        </div>
                    </div>

                    <!-- Promo Banner -->
                    <div class="promo-banner">
                        <div class="promo-title">Welcome Bonus Available!</div>
                        <div class="promo-text">Get ₦500 bonus when you complete your first transaction</div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="recent-transactions">
                        <div class="transactions-header">
                            <h3 class="section-title">Recent Transactions</h3>
                            <a href="transactions.php" class="view-all">
                                View All <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        <?php if (empty($transactions)): ?>
                            <div class="no-transactions">
                                <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                                <p>Start your first transaction today!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <div class="transaction-item">
                                    <div class="transaction-left">
                                        <div class="transaction-icon <?php echo strtolower($t['type']); ?>">
                                            <i class="fas fa-<?php echo $t['type'] == 'transfer' ? 'exchange-alt' : ($t['type'] == 'airtime' ? 'mobile-alt' : 'file-invoice'); ?>"></i>
                                        </div>
                                        <div class="transaction-info">
                                            <h4><?php echo ucfirst($t['type']); ?></h4>
                                            <div class="transaction-details">
                                                <?php echo $t['reference']; ?> • <?php echo date('M j', strtotime($t['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="transaction-amount">
                                        <div class="amount-value">₦<?php echo number_format($t['amount'], 2); ?></div>
                                        <div class="amount-status status-<?php echo strtolower($t['status']); ?>"><?php echo ucfirst($t['status']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="right-sidebar">
                    <!-- Owealth Savings Card -->
                    <div class="owealth-card">
                        <div class="owealth-header">
                            <div class="owealth-title">Owealth Savings</div>
                            <div class="owealth-rate">Daily Interest</div>
                        </div>
                        <div class="owealth-balance">₦<?php echo $savings_balance; ?></div>
                        <div class="owealth-subtitle">Earn interest daily • Full control</div>
                    </div>

                    <!-- Debit Card Preview -->
                    <div class="card-preview">
                        <div class="card-header">
                            <div class="card-logo">UBIAZA</div>
                            <div class="card-type">DEBIT</div>
                        </div>
                        <div class="card-number">**** **** **** 1234</div>
                        <div class="card-bottom">
                            <div class="card-holder"><?php echo strtoupper($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            <div class="card-expiry">12/28</div>
                        </div>
                    </div>

                    <!-- Card Benefits -->
                    <div class="card-benefits">
                        <h3 class="section-title">Free Debit Card Benefits</h3>
                        <div class="benefit-item">
                            <i class="fas fa-check benefit-icon"></i>
                            10 free ATM withdrawals monthly
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-check benefit-icon"></i>
                            Zero maintenance fees
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-check benefit-icon"></i>
                            Contactless payments
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-check benefit-icon"></i>
                            Global acceptance
                        </div>
                    </div>

                    <!-- Investment Options -->
                    <div class="investment-preview">
                        <div style="background: var(--white); padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                            <h3 class="section-title">Investment Opportunities</h3>
                            <div class="investment-option" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Mutual Funds</div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Diversified portfolio</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 700; color: #22c55e;">Up to 25%</div>
                                        <div style="font-size: 11px; color: var(--text-secondary);">per annum</div>
                                    </div>
                                </div>
                            </div>
                            <div class="investment-option" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Fixed Deposits</div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Guaranteed returns</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 700; color: #22c55e;">Up to 18%</div>
                                        <div style="font-size: 11px; color: var(--text-secondary);">per annum</div>
                                    </div>
                                </div>
                            </div>
                            <div class="investment-option" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Insurance Plans</div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">Life & health coverage</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 700; color: #22c55e;">Up to 40%</div>
                                        <div style="font-size: 11px; color: var(--text-secondary);">returns</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help Section -->
                    <div class="help-section">
                        <h3 class="help-title">24/7 Support Available</h3>
                        <p class="help-text">Our customer support team is always ready to help you with any questions or issues.</p>
                        <button class="contact-btn" onclick="window.location.href='support.php'">Contact Support</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enhanced dashboard functionality
        const resendBtn = document.getElementById('resendVerification');
        if (resendBtn) {
            resendBtn.addEventListener('click', async function() {
                const button = this;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                try {
                    const response = await fetch('api/auth.php?action=resend_verification', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            csrf_token: document.querySelector('input[name="csrf_token"]').value
                        }),
                        credentials: 'include'
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok) {
                        showNotification('Verification email sent successfully!', 'success');
                    } else {
                        showNotification(result.error || 'Failed to resend verification email', 'error');
                    }
                } catch (error) {
                    showNotification('Network error: ' + error.message, 'error');
                } finally {
                    button.disabled = false;
                    button.innerHTML = 'Resend';
                }
            });
        }

        // Balance visibility toggle
        const eyeIcon = document.querySelector('.eye-icon');
        const balanceAmount = document.querySelector('.balance-amount');
        const owealthBalance = document.querySelector('.owealth-balance');
        let balancesVisible = true;

        if (eyeIcon) {
            eyeIcon.addEventListener('click', function() {
                balancesVisible = !balancesVisible;
                const icon = this.querySelector('i');
                
                if (balancesVisible) {
                    icon.className = 'fas fa-eye-slash';
                    balanceAmount.style.filter = 'none';
                    if (owealthBalance) owealthBalance.style.filter = 'none';
                    document.querySelectorAll('.stat-value').forEach(el => {
                        if (el.textContent.includes('₦')) {
                            el.style.filter = 'none';
                        }
                    });
                } else {
                    icon.className = 'fas fa-eye';
                    balanceAmount.style.filter = 'blur(8px)';
                    if (owealthBalance) owealthBalance.style.filter = 'blur(8px)';
                    document.querySelectorAll('.stat-value').forEach(el => {
                        if (el.textContent.includes('₦')) {
                            el.style.filter = 'blur(6px)';
                        }
                    });
                }
            });
        }

        // Quick action tracking
        document.querySelectorAll('.quick-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const actionType = this.querySelector('.quick-text').textContent;
                trackUserAction('quick_action_click', { action: actionType });
            });
        });

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

        // User action tracking
        function trackUserAction(action, data = {}) {
            fetch('api/analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    data: data,
                    timestamp: new Date().toISOString()
                }),
                credentials: 'include'
            }).catch(error => {
                console.log('Analytics tracking failed:', error);
            });
        }

        // Auto-refresh balance every 30 seconds
        setInterval(async function() {
            try {
                const response = await fetch('api/balance.php', {
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success) {
                    const balanceElement = document.querySelector('.balance-amount');
                    const newBalance = '₦' + parseFloat(data.balance).toLocaleString('en-NG', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    if (balanceElement.textContent !== newBalance) {
                        balanceElement.textContent = newBalance;
                        showNotification('Balance updated', 'info');
                    }
                }
            } catch (error) {
                console.log('Balance refresh failed:', error);
            }
        }, 30000);

        // Welcome bonus check
        checkWelcomeBonus();

        async function checkWelcomeBonus() {
            try {
                const response = await fetch('api/bonus.php?check_welcome=1', {
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.eligible) {
                    showWelcomeBonusModal();
                }
            } catch (error) {
                console.log('Welcome bonus check failed:', error);
            }
        }

        function showWelcomeBonusModal() {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            `;
            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 16px; text-align: center; max-width: 300px; margin: 20px;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
                    <h3 style="margin-bottom: 8px; color: var(--text-primary);">Welcome Bonus Ready!</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 14px;">Complete your first transaction to earn ₦500 bonus</p>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: var(--primary-color); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;">Got it!</button>
                </div>
            `;
            document.body.appendChild(modal);
            
            setTimeout(() => {
                if (document.body.contains(modal)) {
                    document.body.removeChild(modal);
                }
            }, 10000);
        }
    });
    </script>
</body>
</html>