<?php
require_once 'api/config.php';
require_once 'db.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch admin data
$stmt = $conn->prepare("SELECT full_name, role FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e49b8;
            --primary-hover: #16368f;
            --secondary-blue: #4c7fff;
            --light-blue: #e8f2ff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --background: #f8fafc;
            --white: #ffffff;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--background);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 280px;
            background: var(--white);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .admin-logo {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-logo i {
            font-size: 28px;
            color: var(--primary-color);
        }

        .admin-logo span {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .admin-nav {
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 20px;
        }

        .nav-section-title {
            padding: 0 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            font-weight: 500;
            border-left: 4px solid transparent;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--light-blue);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }

        .nav-badge {
            background: var(--danger);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
            font-weight: 600;
        }

        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 0;
        }

        .admin-header {
            background: var(--white);
            padding: 16px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-left h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--light-blue);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .admin-user:hover {
            background: var(--primary-color);
            color: white;
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content-area {
            padding: 32px;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }

        .dashboard-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-card.users::before { background: var(--success); }
        .stat-card.transactions::before { background: var(--info); }
        .stat-card.revenue::before { background: var(--warning); }
        .stat-card.pending::before { background: var(--danger); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .stat-title {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.users { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-icon.transactions { background: rgba(6, 182, 212, 0.1); color: var(--info); }
        .stat-icon.revenue { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-icon.pending { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }

        .content-section {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .section-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .section-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: var(--light-blue);
            border-color: var(--primary-color);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            background: var(--background);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .data-table td {
            font-size: 14px;
            color: var(--text-primary);
        }

        .data-table tr:hover {
            background: var(--background);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-suspended { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .status-banned { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .status-pending { background: rgba(156, 163, 175, 0.1); color: #6b7280; }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .quick-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .action-btn.edit {
            background: rgba(6, 182, 212, 0.1);
            color: var(--info);
        }

        .action-btn.edit:hover {
            background: var(--info);
            color: white;
        }

        .action-btn.ban {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .action-btn.ban:hover {
            background: var(--danger);
            color: white;
        }

        .action-btn.suspend {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .action-btn.suspend:hover {
            background: var(--warning);
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease-out;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            padding: 32px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: var(--background);
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 73, 184, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .table-controls {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .pagination {
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination-info {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .page-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .page-btn:hover, .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .chart-container {
            padding: 24px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            border: 2px dashed var(--border);
            border-radius: 8px;
            margin: 24px;
        }

        @media (max-width: 1024px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-sidebar.open {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
            }

            .dashboard-overview {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 16px;
            }

            .admin-header {
                padding: 16px;
            }

            .dashboard-overview {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .table-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: auto;
            }

            .data-table {
                font-size: 12px;
            }

            .data-table th,
            .data-table td {
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="admin-logo">
                <i class="fas fa-shield-alt"></i>
                <span>Ubiaza Admin</span>
            </div>
            
            <div class="admin-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="#" class="nav-item active" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">User Management</div>
                    <a href="#" class="nav-item" data-section="users">
                        <i class="fas fa-users"></i>
                        Users
                        <span class="nav-badge" id="userCount">0</span>
                    </a>
                    <a href="#" class="nav-item" data-section="accounts">
                        <i class="fas fa-credit-card"></i>
                        Accounts
                    </a>
                    <a href="#" class="nav-item" data-section="verification">
                        <i class="fas fa-user-check"></i>
                        Verification
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Financial</div>
                    <a href="#" class="nav-item" data-section="transactions">
                        <i class="fas fa-exchange-alt"></i>
                        Transactions
                        <span class="nav-badge" id="pendingCount">0</span>
                    </a>
                    <a href="#" class="nav-item" data-section="bills">
                        <i class="fas fa-receipt"></i>
                        Bill Payments
                    </a>
                    <a href="#" class="nav-item" data-section="cards">
                        <i class="fas fa-credit-card"></i>
                        Cards
                    </a>
                    <a href="#" class="nav-item" data-section="savings">
                        <i class="fas fa-piggy-bank"></i>
                        Savings
                    </a>
                    <a href="#" class="nav-item" data-section="loans">
                        <i class="fas fa-handshake"></i>
                        Loans
                    </a>
                    <a href="#" class="nav-item" data-section="investments">
                        <i class="fas fa-chart-line"></i>
                        Investments
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Operations</div>
                    <a href="#" class="nav-item" data-section="support">
                        <i class="fas fa-headset"></i>
                        Support Tickets
                    </a>
                    <a href="#" class="nav-item" data-section="notifications">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                    <a href="#" class="nav-item" data-section="reports">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="#" class="nav-item" data-section="settings">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                    <a href="#" class="nav-item" data-section="admins">
                        <i class="fas fa-user-shield"></i>
                        Admin Users
                    </a>
                    <a href="#" class="nav-item" data-section="logs">
                        <i class="fas fa-file-alt"></i>
                        Activity Logs
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-left">
                    <h1 id="pageTitle">Dashboard</h1>
                </div>
                <div class="header-right">
                    <div class="admin-user" onclick="showAdminMenu()">
                        <div class="admin-avatar"><?php echo substr($admin['full_name'], 0, 1); ?></div>
                        <div class="admin-info">
                            <div><?php echo htmlspecialchars($admin['full_name']); ?></div>
                            <div style="font-size: 12px; color: var(--text-secondary);"><?php echo $admin['role']; ?></div>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area" id="contentArea">
                <!-- Dashboard Section -->
                <div class="content-section" id="dashboard">
                    <div class="dashboard-overview">
                        <div class="stat-card users">
                            <div class="stat-header">
                                <div class="stat-title">Total Users</div>
                                <div class="stat-icon users">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="totalUsers">0</div>
                            <div class="stat-change positive" id="usersChange">+0% from last month</div>
                        </div>

                        <div class="stat-card transactions">
                            <div class="stat-header">
                                <div class="stat-title">Total Transactions</div>
                                <div class="stat-icon transactions">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="totalTransactions">0</div>
                            <div class="stat-change positive" id="transactionsChange">+0% from last month</div>
                        </div>

                        <div class="stat-card revenue">
                            <div class="stat-header">
                                <div class="stat-title">Total Revenue</div>
                                <div class="stat-icon revenue">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="totalRevenue">₦0</div>
                            <div class="stat-change positive" id="revenueChange">+0% from last month</div>
                        </div>

                        <div class="stat-card pending">
                            <div class="stat-header">
                                <div class="stat-title">Pending Actions</div>
                                <div class="stat-icon pending">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-value" id="pendingActions">0</div>
                            <div class="stat-change negative" id="pendingChange">0% from last month</div>
                        </div>
                    </div>

                    <div class="content-section">
                        <div class="section-header">
                            <h2 class="section-title">Recent Activity</h2>
                            <div class="section-actions">
                                <button class="btn btn-outline">
                                    <i class="fas fa-download"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <div>
                                <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 16px;"></i>
                                <div>Activity Chart will be displayed here</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Section -->
                <div class="content-section" id="users" style="display: none;">
                    <div class="section-header">
                        <h2 class="section-title">User Management</h2>
                        <div class="section-actions">
                            <button class="btn btn-primary" onclick="openUserModal()">
                                <i class="fas fa-plus"></i>
                                Add User
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-controls">
                        <div class="search-box">
                            <input type="text" placeholder="Search users..." id="userSearch">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="filter-group">
                            <select class="filter-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="banned">Banned</option>
                            </select>
                            <select class="filter-select" id="verificationFilter">
                                <option value="">All Verification</option>
                                <option value="verified">Verified</option>
                                <option value="unverified">Unverified</option>
                            </select>
                        </div>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Verification</th>
                                <th>Balance</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">Loading users...</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pagination">
                        <div class="pagination-info" id="usersPaginationInfo">Showing 1-10 of 0 users</div>
                        <div class="pagination-controls" id="usersPagination">
                            <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
                            <button class="page-btn active">1</button>
                            <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Transactions Section -->
                <div class="content-section" id="transactions" style="display: none;">
                    <div class="section-header">
                        <h2 class="section-title">Transaction Management</h2>
                        <div class="section-actions">
                            <button class="btn btn-outline">
                                <i class="fas fa-download"></i>
                                Export
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-controls">
                        <div class="search-box">
                            <input type="text" placeholder="Search transactions..." id="transactionSearch">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="filter-group">
                            <select class="filter-select" id="typeFilter">
                                <option value="">All Types</option>
                                <option value="transfer">Transfer</option>
                                <option value="airtime">Airtime</option>
                                <option value="bills">Bills</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                            </select>
                            <select class="filter-select" id="transactionStatusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">Loading transactions...</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pagination">
                        <div class="pagination-info" id="transactionsPaginationInfo">Showing 1-10 of 0 transactions</div>
                        <div class="pagination-controls" id="transactionsPagination">
                            <button class="page-btn" disabled><i class="fas fa-chevron-left"></i></button>
                            <button class="page-btn active">1</button>
                            <button class="page-btn" disabled><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Settings Section -->
                <div class="content-section" id="settings" style="display: none;">
                    <div class="section-header">
                        <h2 class="section-title">System Settings</h2>
                    </div>
                    
                    <div style="padding: 24px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                            <div class="stat-card">
                                <h3 style="margin-bottom: 16px;">General Settings</h3>
                                <div class="form-group">
                                    <label class="form-label">Application Name</label>
                                    <input type="text" class="form-control" id="appName" value="Ubiaza">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Maintenance Mode</label>
                                    <select class="form-control" id="maintenanceMode">
                                        <option value="off">Off</option>
                                        <option value="on">On</option>
                                    </select>
                                </div>
                            </div>

                            <div class="stat-card">
                                <h3 style="margin-bottom: 16px;">Transaction Limits</h3>
                                <div class="form-group">
                                    <label class="form-label">Daily Transfer Limit</label>
                                    <input type="number" class="form-control" id="dailyTransferLimit" value="1000000">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Single Transaction Limit</label>
                                    <input type="number" class="form-control" id="singleTransactionLimit" value="500000">
                                </div>
                            </div>

                            <div class="stat-card">
                                <h3 style="margin-bottom: 16px;">Fee Configuration</h3>
                                <div class="form-group">
                                    <label class="form-label">Transfer Fee (%)</label>
                                    <input type="number" class="form-control" id="transferFee" value="1.5" step="0.1">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Bill Payment Fee (%)</label>
                                    <input type="number" class="form-control" id="billPaymentFee" value="1.0" step="0.1">
                                </div>
                            </div>

                            <div class="stat-card">
                                <h3 style="margin-bottom: 16px;">Cashback Settings</h3>
                                <div class="form-group">
                                    <label class="form-label">Airtime Cashback (%)</label>
                                    <input type="number" class="form-control" id="airtimeCashback" value="6.0" step="0.1">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Cashback (%)</label>
                                    <input type="number" class="form-control" id="dataCashback" value="3.0" step="0.1">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 32px; text-align: center;">
                            <button class="btn btn-primary" onclick="saveSettings()">
                                <i class="fas fa-save"></i>
                                Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="userModalTitle">Add New User</h3>
                <button class="close-btn" onclick="closeUserModal()">&times;</button>
            </div>
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" id="status">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i>
                        Save User
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeUserModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ban User Modal -->
    <div class="modal" id="banModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ban User</h3>
                <button class="close-btn" onclick="closeBanModal()">&times;</button>
            </div>
            <form id="banForm">
                <input type="hidden" id="banUserId">
                <div class="form-group">
                    <label class="form-label">Reason for Ban</label>
                    <textarea class="form-control" id="banReason" rows="4" required placeholder="Please provide a reason for banning this user..."></textarea>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-danger" style="flex: 1;">
                        <i class="fas fa-ban"></i>
                        Ban User
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeBanModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Suspend User Modal -->
    <div class="modal" id="suspendModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Suspend User</h3>
                <button class="close-btn" onclick="closeSuspendModal()">&times;</button>
            </div>
            <form id="suspendForm">
                <input type="hidden" id="suspendUserId">
                <div class="form-group">
                    <label class="form-label">Suspension Duration</label>
                    <select class="form-control" id="suspendDuration" required>
                        <option value="">Select Duration</option>
                        <option value="1">1 Day</option>
                        <option value="7">7 Days</option>
                        <option value="30">30 Days</option>
                        <option value="90">90 Days</option>
                        <option value="365">1 Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Suspension</label>
                    <textarea class="form-control" id="suspendReason" rows="4" required placeholder="Please provide a reason for suspending this user..."></textarea>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-warning" style="flex: 1;">
                        <i class="fas fa-pause"></i>
                        Suspend User
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeSuspendModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSection = 'dashboard';
        let currentUser = null;
        const itemsPerPage = 10;

        // Debounce function for search
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

        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
            setupEventListeners();
            // Poll for updates every 30 seconds
            setInterval(loadDashboardStats, 30000);
        });

        function setupEventListeners() {
            // Navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const section = this.getAttribute('data-section');
                    if (section) {
                        switchSection(section);
                    }
                });
            });

            // Search and filter
            document.getElementById('userSearch').addEventListener('input', debounce(filterUsers, 300));
            document.getElementById('statusFilter').addEventListener('change', filterUsers);
            document.getElementById('verificationFilter').addEventListener('change', filterUsers);
            document.getElementById('transactionSearch').addEventListener('input', debounce(filterTransactions, 300));
            document.getElementById('typeFilter').addEventListener('change', filterTransactions);
            document.getElementById('transactionStatusFilter').addEventListener('change', filterTransactions);

            // Forms
            document.getElementById('userForm').addEventListener('submit', handleUserSubmit);
            document.getElementById('banForm').addEventListener('submit', handleBanSubmit);
            document.getElementById('suspendForm').addEventListener('submit', handleSuspendSubmit);

            // Pagination
            document.getElementById('usersPagination').addEventListener('click', handlePagination);
            document.getElementById('transactionsPagination').addEventListener('click', handlePagination);
        }

        function switchSection(section) {
            document.querySelectorAll('.content-section').forEach(s => {
                s.style.display = 'none';
            });
            document.getElementById(section).style.display = 'block';

            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-section="${section}"]`).classList.add('active');

            const titles = {
                dashboard: 'Dashboard',
                users: 'User Management',
                transactions: 'Transaction Management',
                settings: 'System Settings',
                accounts: 'Account Management',
                verification: 'Verification Management',
                bills: 'Bill Payment Management',
                cards: 'Card Management',
                savings: 'Savings Management',
                loans: 'Loan Management',
                investments: 'Investment Management',
                support: 'Support Tickets',
                notifications: 'Notification Management',
                reports: 'Reports & Analytics',
                admins: 'Admin User Management',
                logs: 'Activity Logs'
            };
            document.getElementById('pageTitle').textContent = titles[section] || 'Dashboard';

            currentSection = section;

            if (section === 'users') {
                loadUsers();
            } else if (section === 'transactions') {
                loadTransactions();
            }
        }

        async function loadDashboardStats() {
            try {
                const response = await fetch('api/admin_api.php?action=stats', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include'
                });
                const stats = await response.json();
                
                if (response.ok) {
                    document.getElementById('totalUsers').textContent = stats.total_users.toLocaleString();
                    document.getElementById('totalTransactions').textContent = stats.total_transactions.toLocaleString();
                    document.getElementById('totalRevenue').textContent = `₦${stats.total_revenue.toLocaleString()}`;
                    document.getElementById('pendingActions').textContent = stats.pending_actions.toLocaleString();
                    document.getElementById('userCount').textContent = stats.total_users.toLocaleString();
                    document.getElementById('pendingCount').textContent = stats.pending_actions.toLocaleString();
                    document.getElementById('usersChange').textContent = `${stats.users_change >= 0 ? '+' : ''}${stats.users_change}% from last month`;
                    document.getElementById('transactionsChange').textContent = `${stats.transactions_change >= 0 ? '+' : ''}${stats.transactions_change}% from last month`;
                    document.getElementById('revenueChange').textContent = `${stats.revenue_change >= 0 ? '+' : ''}${stats.revenue_change}% from last month`;
                    document.getElementById('pendingChange').textContent = `${stats.pending_change >= 0 ? '+' : ''}${stats.pending_change}% from last month`;
                    document.getElementById('usersChange').className = `stat-change ${stats.users_change >= 0 ? 'positive' : 'negative'}`;
                    document.getElementById('transactionsChange').className = `stat-change ${stats.transactions_change >= 0 ? 'positive' : 'negative'}`;
                    document.getElementById('revenueChange').className = `stat-change ${stats.revenue_change >= 0 ? 'positive' : 'negative'}`;
                    document.getElementById('pendingChange').className = `stat-change ${stats.pending_change >= 0 ? 'positive' : 'negative'}`;
                }
            } catch (error) {
                console.error('Error loading dashboard stats:', error);
            }
        }

        async function loadUsers(page = 1) {
            try {
                const search = document.getElementById('userSearch').value;
                const status = document.getElementById('statusFilter').value;
                const verification = document.getElementById('verificationFilter').value;
                
                const response = await fetch(`api/admin_api.php?action=users&page=${page}&search=${encodeURIComponent(search)}&status=${status}&verification=${verification}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    displayUsers(data.users);
                    updatePagination(data.pagination, 'usersPagination', 'usersPaginationInfo');
                } else {
                    throw new Error(data.error || 'Failed to load users');
                }
            } catch (error) {
                console.error('Error loading users:', error);
                document.getElementById('usersTableBody').innerHTML = 
                    `<tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--danger);">${error.message}</td></tr>`;
            }
        }

        async function loadTransactions(page = 1) {
            try {
                const search = document.getElementById('transactionSearch').value;
                const type = document.getElementById('typeFilter').value;
                const status = document.getElementById('transactionStatusFilter').value;
                
                const response = await fetch(`api/admin_api.php?action=transactions&page=${page}&search=${encodeURIComponent(search)}&type=${type}&status=${status}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    displayTransactions(data.transactions);
                    updatePagination(data.pagination, 'transactionsPagination', 'transactionsPaginationInfo');
                } else {
                    throw new Error(data.error || 'Failed to load transactions');
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
                document.getElementById('transactionsTableBody').innerHTML = 
                    `<tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--danger);">${error.message}</td></tr>`;
            }
        }

        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px;">No users found</td></tr>';
                return;
            }

            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="user-avatar">${user.first_name[0]}${user.last_name[0]}</div>
                            <div>
                                <div style="font-weight: 600;">${user.first_name} ${user.last_name}</div>
                                <div style="font-size: 12px; color: var(--text-secondary);">ID: ${user.id}</div>
                            </div>
                        </div>
                    </td>
                    <td>${user.email}</td>
                    <td>${user.phone}</td>
                    <td><span class="status-badge status-${user.status}">${user.status.toUpperCase()}</span></td>
                    <td>
                        <div style="font-size: 12px;">
                            <div>BVN: ${user.bvn_verified ? '✅' : '❌'}</div>
                            <div>Email: ${user.email_verified ? '✅' : '❌'}</div>
                        </div>
                    </td>
                    <td>₦${Number(user.balance).toLocaleString()}</td>
                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                    <td>
                        <div class="quick-actions">
                            <button class="action-btn edit" onclick="editUser(${user.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn suspend" onclick="suspendUser(${user.id})" title="Suspend">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button class="action-btn ban" onclick="banUser(${user.id})" title="Ban">
                                <i class="fas fa-ban"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function displayTransactions(transactions) {
            const tbody = document.getElementById('transactionsTableBody');
            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px;">No transactions found</td></tr>';
                return;
            }

            tbody.innerHTML = transactions.map(tx => `
                <tr>
                    <td>
                        <div style="font-weight: 600;">${tx.reference}</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">${tx.external_reference || ''}</div>
                    </td>
                    <td>${tx.user_name}</td>
                    <td><span class="status-badge status-${tx.type}">${tx.type.toUpperCase()}</span></td>
                    <td>₦${Number(tx.amount).toLocaleString()}</td>
                    <td>₦${Number(tx.fee || 0).toLocaleString()}</td>
                    <td><span class="status-badge status-${tx.status}">${tx.status.toUpperCase()}</span></td>
                    <td>${new Date(tx.created_at).toLocaleDateString()}</td>
                    <td>
                        <div class="quick-actions">
                            <button class="action-btn edit" onclick="viewTransaction('${tx.reference}')" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function updatePagination(pagination, containerId, infoId) {
            const container = document.getElementById(containerId);
            const info = document.getElementById(infoId);
            const totalItems = pagination.total;
            const currentPage = pagination.current_page;
            const totalPages = Math.ceil(totalItems / itemsPerPage);

            info.textContent = `Showing ${(currentPage - 1) * itemsPerPage + 1}-${Math.min(currentPage * itemsPerPage, totalItems)} of ${totalItems} items`;

            let html = `
                <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
            
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }

            html += `
                <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;

            container.innerHTML = html;
        }

        function handlePagination(e) {
            const btn = e.target.closest('.page-btn');
            if (!btn || btn.disabled) return;
            
            const page = parseInt(btn.dataset.page);
            if (currentSection === 'users') {
                loadUsers(page);
            } else if (currentSection === 'transactions') {
                loadTransactions(page);
            }
        }

        function filterUsers() {
            loadUsers(1);
        }

        function filterTransactions() {
            loadTransactions(1);
        }

        function openUserModal(user = null) {
            currentUser = user;
            const modal = document.getElementById('userModal');
            const title = document.getElementById('userModalTitle');
            
            if (user) {
                title.textContent = 'Edit User';
                document.getElementById('userId').value = user.id;
                document.getElementById('firstName').value = user.first_name;
                document.getElementById('lastName').value = user.last_name;
                document.getElementById('email').value = user.email;
                document.getElementById('phone').value = user.phone;
                document.getElementById('status').value = user.status;
            } else {
                title.textContent = 'Add New User';
                document.getElementById('userForm').reset();
                document.getElementById('userId').value = '';
            }
            
            modal.classList.add('show');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('show');
            currentUser = null;
        }

        async function editUser(userId) {
            try {
                const response = await fetch(`api/admin_api.php?action=get_user&id=${userId}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include'
                });
                const user = await response.json();
                if (response.ok) {
                    openUserModal(user);
                } else {
                    throw new Error(user.error || 'Failed to fetch user');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        function banUser(userId) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banModal').classList.add('show');
        }

        function closeBanModal() {
            document.getElementById('banModal').classList.remove('show');
        }

        function suspendUser(userId) {
            document.getElementById('suspendUserId').value = userId;
            document.getElementById('suspendModal').classList.add('show');
        }

        function closeSuspendModal() {
            document.getElementById('suspendModal').classList.remove('show');
        }

        async function handleUserSubmit(e) {
            e.preventDefault();
            
            const userData = {
                id: document.getElementById('userId').value,
                first_name: document.getElementById('firstName').value,
                last_name: document.getElementById('lastName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                status: document.getElementById('status').value
            };

            try {
                const response = await fetch('api/admin_api.php?action=save_user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(userData),
                    credentials: 'include'
                });

                const result = await response.json();
                
                if (response.ok) {
                    alert('User saved successfully!');
                    closeUserModal();
                    loadUsers();
                } else {
                    throw new Error(result.error || 'Failed to save user');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function handleBanSubmit(e) {
            e.preventDefault();
            
            const userId = document.getElementById('banUserId').value;
            const reason = document.getElementById('banReason').value;

            try {
                const response = await fetch('api/admin_api.php?action=ban_user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ user_id: userId, reason }),
                    credentials: 'include'
                });

                const result = await response.json();
                
                if (response.ok) {
                    alert('User banned successfully!');
                    closeBanModal();
                    loadUsers();
                } else {
                    throw new Error(result.error || 'Failed to ban user');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function handleSuspendSubmit(e) {
            e.preventDefault();
            
            const userId = document.getElementById('suspendUserId').value;
            const duration = document.getElementById('suspendDuration').value;
            const reason = document.getElementById('suspendReason').value;

            try {
                const response = await fetch('api/admin_api.php?action=suspend_user', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ user_id: userId, duration, reason }),
                    credentials: 'include'
                });

                const result = await response.json();
                
                if (response.ok) {
                    alert('User suspended successfully!');
                    closeSuspendModal();
                    loadUsers();
                } else {
                    throw new Error(result.error || 'Failed to suspend user');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function saveSettings() {
            try {
                const settings = {
                    app_name: document.getElementById('appName').value,
                    maintenance_mode: document.getElementById('maintenanceMode').value,
                    daily_transfer_limit: document.getElementById('dailyTransferLimit').value,
                    single_transaction_limit: document.getElementById('singleTransactionLimit').value,
                    transfer_fee: document.getElementById('transferFee').value,
                    bill_payment_fee: document.getElementById('billPaymentFee').value,
                    airtime_cashback: document.getElementById('airtimeCashback').value,
                    data_cashback: document.getElementById('dataCashback').value
                };

                const response = await fetch('api/admin_api.php?action=save_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(settings),
                    credentials: 'include'
                });

                const result = await response.json();
                
                if (response.ok) {
                    alert('Settings saved successfully!');
                } else {
                    throw new Error(result.error || 'Failed to save settings');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function viewTransaction(reference) {
            try {
                const response = await fetch(`api/admin_api.php?action=get_transaction&reference=${reference}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include'
                });
                const transaction = await response.json();
                if (response.ok) {
                    alert(`Transaction Details:\nReference: ${transaction.reference}\nUser: ${transaction.user_name}\nType: ${transaction.type}\nAmount: ₦${Number(transaction.amount).toLocaleString()}\nStatus: ${transaction.status}\nDate: ${new Date(transaction.created_at).toLocaleString()}`);
                } else {
                    throw new Error(transaction.error || 'Failed to fetch transaction');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        function showAdminMenu() {
            const choice = prompt('Admin Menu:\n1. Profile\n2. Change Password\n3. Logout\n\nEnter number:');
            if (choice === '3') {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = 'admin_logout.php';
                }
            }
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('open');
        }
    </script>
</body>
</html>