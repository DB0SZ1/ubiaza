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

// Check if account is set up
$stmt = $conn->prepare("SELECT id, balance, account_number FROM accounts WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$accountResult = $stmt->get_result();
$accountExists = $accountResult->num_rows > 0;
$account = $accountExists ? $accountResult->fetch_assoc() : ['balance' => 0.00, 'account_number' => 'Not Set'];
$balance = number_format($account['balance'], 2);
$account_number = $account['account_number'];

// Fetch recent transactions
$stmt = $conn->prepare("SELECT type, amount, status, reference, created_at FROM transactions WHERE user_id = ? OR recipient_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch saved beneficiaries
$stmt = $conn->prepare("SELECT name, account_number, bank_name FROM beneficiaries WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$beneficiaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <script src="assets/js/dashboard.js"></script>
    <style>
        /* Compact Verification Status */
        .verification-status {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-title {
            color: #1f2937;
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .verification-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .verification-item:last-child {
            border-bottom: none;
        }

        .verification-item span {
            color: #6b7280;
            font-size: 0.85rem;
            flex: 1;
        }

        .verification-item i {
            font-size: 1rem;
            margin-right: 0.5rem;
        }

        .verification-item i.verified {
            color: #10b981;
        }

        .verification-item i.unverified {
            color: #f59e0b;
        }

        .btn-small {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-small:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        /* 3x3 Grid for Quick Actions */
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
        .quick-icon.account { background: linear-gradient(45deg, #ec4899, #db2777); }
        .quick-icon.history { background: linear-gradient(45deg, #6b7280, #4b5563); }

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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .modal-content button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 20px;
        }

        .modal-content button:hover {
            background: #2563eb;
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
                <a href="transactions.php" class="nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    Transactions
                </a>
                <a href="transfer.php" class="nav-item">
                    <i class="fas fa-paper-plane"></i>
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
                <a href="cards.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    Payment Methods
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    Profile
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    Notifications
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
                        <h3 class="section-title">Account Verification</h3>
                        <div class="verification-item">
                            <i class="fas fa-envelope <?php echo $user['email_verified'] ? 'verified' : 'unverified'; ?>"></i>
                            <span>Email: <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?></span>
                            <?php if (!$user['email_verified']): ?>
                                <button class="btn-small" id="resendVerification">Resend Verification Email</button>
                            <?php endif; ?>
                        </div>
                        <div class="verification-item">
                            <i class="fas fa-id-card <?php echo $user['bvn_verified'] ? 'verified' : 'unverified'; ?>"></i>
                            <span>BVN: <?php echo $user['bvn_verified'] ? 'Verified' : 'Unverified'; ?></span>
                            <?php if (!$user['bvn_verified']): ?>
                                <button class="btn-small" onclick="window.location.href='verify.php?type=bvn'">Verify Now</button>
                            <?php endif; ?>
                        </div>
                        <div class="verification-item">
                            <i class="fas fa-id-card-alt <?php echo $user['nin_verified'] ? 'verified' : 'unverified'; ?>"></i>
                            <span>NIN: <?php echo $user['nin_verified'] ? 'Verified' : 'Unverified'; ?></span>
                            <?php if (!$user['nin_verified']): ?>
                                <button class="btn-small" onclick="window.location.href='verify.php?type=nin'">Verify Now</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account Balance Card -->
                    <div class="balance-card">
                        <div class="balance-header">
                            <h2 class="balance-title">Your Account Balance</h2>
                            <button class="eye-icon">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        <div class="balance-amount">₦<?php echo $balance; ?></div>
                        <div class="balance-status">Available • Account: <?php echo htmlspecialchars($account_number); ?></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="action-btn send-btn" onclick="checkAccountBeforeAction('transfer.php')">
                            <i class="fas fa-paper-plane"></i>
                            Send Money
                        </button>
                        <button class="action-btn add-btn" onclick="checkAccountBeforeAction('add_money.php')">
                            <i class="fas fa-plus"></i>
                            Add Money
                        </button>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <h3 class="section-title">Quick Actions</h3>
                        <div class="quick-grid">
                            <a href="api/wallet/transfer.php" class="quick-item" onclick="checkAccountBeforeAction('transfer.php'); return false;">
                                <div class="quick-icon transfer">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="quick-text">Bank Transfer</div>
                            </a>
                            <a href="airtime.php" class="quick-item" onclick="checkAccountBeforeAction('airtime.php'); return false;">
                                <div class="quick-icon airtime">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="quick-text">Airtime & Data</div>
                                <div class="cashback-badge">6%</div>
                            </a>
                            <a href="bills.php" class="quick-item" onclick="checkAccountBeforeAction('bills.php'); return false;">
                                <div class="quick-icon bills">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="quick-text">Pay Bills</div>
                            </a>
                            <a href="cards.php" class="quick-item">
                                <div class="quick-icon card">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="quick-text">Payment Methods</div>
                            </a>
                            <a href="api/wallet/wallet.php" class="quick-item">
                                <div class="quick-icon account">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="quick-text">My Account</div>
                            </a>
                            <a href="transactions.php" class="quick-item">
                                <div class="quick-icon history">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="quick-text">Transaction History</div>
                            </a>
                        </div>
                    </div>

                    <!-- Saved Beneficiaries -->
                    <div class="saved-beneficiaries">
                        <div class="beneficiaries-header">
                            <h3 class="section-title">Saved Beneficiaries</h3>
                            <button class="add-beneficiary" onclick="window.location.href='transfer.php'">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <?php if (empty($beneficiaries)): ?>
                            <p>No saved beneficiaries. Add one to make transfers faster.</p>
                        <?php else: ?>
                            <?php foreach ($beneficiaries as $b): ?>
                                <div class="beneficiary-item">
                                    <div class="beneficiary-avatar"><?php echo substr($b['name'], 0, 1) . (isset(explode(' ', $b['name'])[1]) ? substr(explode(' ', $b['name'])[1], 0, 1) : ''); ?></div>
                                    <div class="beneficiary-info">
                                        <h4><?php echo htmlspecialchars($b['name']); ?></h4>
                                        <div class="beneficiary-account"><?php echo htmlspecialchars($b['account_number']) . ' • ' . htmlspecialchars($b['bank_name']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Help Section -->
                    <div class="help-section">
                        <h3 class="help-title">Need Help?</h3>
                        <p class="help-text">Our support team is available 24/7 to assist you with any questions.</p>
                        <button class="contact-btn" onclick="window.location.href='support.php'">Contact Support</button>
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
                                <p>No recent transactions</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <h4><?php echo htmlspecialchars($t['type']); ?></h4>
                                        <div class="transaction-details">
                                            <?php echo htmlspecialchars($t['reference']); ?> • <?php echo $t['status']; ?>
                                        </div>
                                    </div>
                                    <div class="transaction-amount">
                                        ₦<?php echo number_format($t['amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="right-sidebar">
                    <div class="saved-beneficiaries">
                        <div class="beneficiaries-header">
                            <h3 class="section-title">Saved Beneficiaries</h3>
                            <button class="add-beneficiary" onclick="window.location.href='transfer.php'">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <?php if (empty($beneficiaries)): ?>
                            <p>No saved beneficiaries.</p>
                        <?php else: ?>
                            <?php foreach ($beneficiaries as $b): ?>
                                <div class="beneficiary-item">
                                    <div class="beneficiary-avatar"><?php echo substr($b['name'], 0, 1) . (isset(explode(' ', $b['name'])[1]) ? substr(explode(' ', $b['name'])[1], 0, 1) : ''); ?></div>
                                    <div class="beneficiary-info">
                                        <h4><?php echo htmlspecialchars($b['name']); ?></h4>
                                        <div class="beneficiary-account"><?php echo htmlspecialchars($b['account_number']) . ' • ' . htmlspecialchars($b['bank_name']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="help-section">
                        <h3 class="help-title">Need Help?</h3>
                        <p class="help-text">Our support team is available 24/7 to assist you with any questions.</p>
                        <button class="contact-btn" onclick="window.location.href='support.php'">Contact Support</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal for Account Setup -->
    <div id="accountSetupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Setup Your Account</h2>
            <p>You need to set up your account before you can perform this action. Follow these steps:</p>
            <ol>
                <li>Click "Setup Account" in the quick actions below.</li>
                <li>Follow the instructions to create your account.</li>
                <li>Once set up, you can send and add money.</li>
            </ol>
            <button onclick="window.location.href='setup_account.php'">Setup Now</button>
        </div>
    </div>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <script>
    const accountExists = <?php echo json_encode($accountExists); ?>;

    function checkAccountBeforeAction(url) {
        if (!accountExists) {
            document.getElementById('accountSetupModal').style.display = 'block';
        } else {
            window.location.href = url;
        }
    }

    function closeModal() {
        document.getElementById('accountSetupModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
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
                        alert('Verification email sent successfully!');
                    } else {
                        alert(result.error || 'Failed to resend verification email');
                    }
                } catch (error) {
                    alert('Network error: ' + error.message);
                } finally {
                    button.disabled = false;
                    button.innerHTML = 'Resend Verification Email';
                }
            });
        }
    });
    </script>
</body>
</html>