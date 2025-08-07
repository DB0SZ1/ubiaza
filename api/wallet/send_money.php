<?php
require_once 'api/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: sign.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza - Send Money</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="container">
        <div class="mobile-header">
            <button class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <span>Ubiaza</span>
            </div>
        </div>

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
                <a href="transactions.php" class="nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    Transactions
                </a>
                <a href="send_money.php" class="nav-item active">
                    <i class="fas fa-paper-plane"></i>
                    Send Money
                </a>
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

        <main class="main-content">
            <div class="top-header">
                <div class="header-left">
                    <h1>Send Money</h1>
                </div>
            </div>
            <div class="content-grid">
                <div class="left-content">
                    <div class="form-card">
                        <h2>P2P Transfer</h2>
                        <form id="transferForm">
                            <div class="form-group">
                                <label for="recipient_account">Recipient Account Number</label>
                                <input type="text" id="recipient_account" name="recipient_account" placeholder="Enter 10-digit account number" required>
                            </div>
                            <div class="form-group">
                                <label for="recipient_bank">Recipient Bank</label>
                                <select id="recipient_bank" name="recipient_bank" required>
                                    <option value="">Select Bank</option>
                                    <option value="044">Access Bank</option>
                                    <option value="063">Diamond Bank</option>
                                    <option value="050">Ecobank</option>
                                    <option value="070">Fidelity Bank</option>
                                    <option value="011">First Bank</option>
                                    <option value="058">GTBank</option>
                                    <!-- Add more banks from Flutterwave API -->
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="amount">Amount (₦)</label>
                                <input type="number" id="amount" name="amount" placeholder="Enter amount" min="100" required>
                            </div>
                            <button type="submit" class="action-btn send-btn">Send Money</button>
                        </form>
                        <div id="transferMessage"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <script src="js/dashboard.js"></script>
    <script>
        document.getElementById('transferForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const recipient_account = document.getElementById('recipient_account').value;
            const recipient_bank = document.getElementById('recipient_bank').value;
            const amount = document.getElementById('amount').value;
            const messageDiv = document.getElementById('transferMessage');

            try {
                const response = await fetch('api/transactions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'transfer',
                        recipient_account,
                        recipient_bank,
                        amount
                    })
                });
                const result = await response.json();
                if (response.ok) {
                    messageDiv.innerHTML = `<p style="color: green;">${result.message} (Ref: ${result.reference})</p>`;
                    setTimeout(() => window.location.href = 'dashboard.php', 2000);
                } else {
                    messageDiv.innerHTML = `<p style="color: red;">${result.error}</p>`;
                }
            } catch (error) {
                messageDiv.innerHTML = '<p style="color: red;">Error processing transfer</p>';
            }
        });
    </script>
</body>
</html>