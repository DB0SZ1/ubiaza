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

// Fetch recent transactions
$stmt = $conn->prepare("SELECT type, amount, status, reference, created_at FROM transactions WHERE user_id = ? OR recipient_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                <a href="send_money.php" class="nav-item">
                    <i class="fas fa-paper-plane"></i>
                    Send Money
                </a>
                <div class="nav-subitem">
                    <i class="fas fa-circle" style="font-size: 6px; margin-right: 8px;"></i>
                    P2P Transfer
                </div>
                <div class="nav-subitem">
                    <i class="fas fa-circle" style="font-size: 6px; margin-right: 8px;"></i>
                    International Transfer
                </div>
                <a href="converter.php" class="nav-item">
                    <i class="fas fa-sync-alt"></i>
                    Converter
                </a>
                <a href="track_payment.php" class="nav-item">
                    <i class="fas fa-search"></i>
                    Track Payment
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

                    <div class="balance-card">
                        <div class="balance-header">
                            <h2 class="balance-title">Your Balance</h2>
                            <button class="eye-icon">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        <div class="balance-amount">₦<?php echo $balance; ?></div>
                        <div class="balance-status">Available</div>
                        
                    </div>

                    <div class="action-buttons">
                        <button class="action-btn send-btn" onclick="window.location.href='send_money.php'">
                            <i class="fas fa-paper-plane"></i>
                            Send Money
                        </button>
                        <button class="action-btn add-btn" onclick="window.location.href='add_money.php'">
                            <i class="fas fa-plus"></i>
                            Add Money
                        </button>
                    </div>

                    <div class="quick-actions">
                        <h3 class="section-title">Quick Actions</h3>
                        <div class="quick-grid">
                            <a href="send_money.php" class="quick-item">
                                <div class="quick-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="quick-text">P2P Transfer</div>
                            </a>
                            <a href="send_money.php" class="quick-item">
                                <div class="quick-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="quick-text">International Transfer</div>
                            </a>
                            <a href="cards.php" class="quick-item">
                                <div class="quick-icon">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div class="quick-text">Setup Wallet</div>
                            </a>
                            <a href="transactions.php" class="quick-item">
                                <div class="quick-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="quick-text">Transaction History</div>
                            </a>
                        </div>
                    </div>

                    <div class="saved-beneficiaries">
                        <div class="beneficiaries-header">
                            <h3 class="section-title">Saved Beneficiaries</h3>
                            <button class="add-beneficiary" onclick="window.location.href='send_money.php'">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <?php
                        // Placeholder: Fetch beneficiaries from database in future
                        $beneficiaries = [
                           
                        ];
                        foreach ($beneficiaries as $b) {
                            echo '
                            <div class="beneficiary-item">
                                <div class="beneficiary-avatar">' . substr($b['name'], 0, 1) . substr(explode(' ', $b['name'])[1], 0, 1) . '</div>
                                <div class="beneficiary-info">
                                    <h4>' . htmlspecialchars($b['name']) . '</h4>
                                    <div class="beneficiary-account">' . htmlspecialchars($b['account']) . ' • ' . htmlspecialchars($b['bank']) . '</div>
                                </div>
                            </div>';
                        }
                        ?>
                    </div>

                    <div class="help-section">
                        <h3 class="help-title">Need Help?</h3>
                        <p class="help-text">Our support team is available 24/7 to assist you with any questions.</p>
                        <button class="contact-btn" onclick="window.location.href='support.php'">Contact Support</button>
                    </div>

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

                <div class="right-sidebar">
                    <div class="saved-beneficiaries">
                        <div class="beneficiaries-header">
                            <h3 class="section-title">Saved Beneficiaries</h3>
                            <button class="add-beneficiary" onclick="window.location.href='send_money.php'">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <?php foreach ($beneficiaries as $b): ?>
                            <div class="beneficiary-item">
                                <div class="beneficiary-avatar"><?php echo substr($b['name'], 0, 1) . substr(explode(' ', $b['name'])[1], 0, 1); ?></div>
                                <div class="beneficiary-info">
                                    <h4><?php echo htmlspecialchars($b['name']); ?></h4>
                                    <div class="beneficiary-account"><?php echo htmlspecialchars($b['account']) . ' • ' . htmlspecialchars($b['bank']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <script>
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