    function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }

        function toggleUserDropdown() {
            const isMobile = window.innerWidth <= 768;
            const dropdownId = isMobile ? 'userDropdown' : 'desktopUserDropdown';
            const dropdown = document.getElementById(dropdownId);
            
            // Close other dropdowns
            const otherDropdownId = isMobile ? 'desktopUserDropdown' : 'userDropdown';
            const otherDropdown = document.getElementById(otherDropdownId);
            if (otherDropdown) {
                otherDropdown.classList.remove('show');
            }
            
            dropdown.classList.toggle('show');
        }

        // Dark Mode Toggle
        function toggleTheme() {
            const body = document.body;
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            
            // Sync both toggles
            const themeToggles = document.querySelectorAll('.theme-toggle-checkbox');
            themeToggles.forEach(toggle => {
                toggle.checked = !isDark;
            });
            
            // Save preference
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger');
            const userAvatars = document.querySelectorAll('.user-avatar');
            const dropdowns = document.querySelectorAll('.user-dropdown');
            
            // Close sidebar on mobile when clicking outside
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !hamburger.contains(e.target)) {
                closeSidebar();
            }
            
            // Close user dropdowns when clicking outside
            let clickedOnAvatar = false;
            userAvatars.forEach(avatar => {
                if (avatar.contains(e.target)) {
                    clickedOnAvatar = true;
                }
            });
            
            if (!clickedOnAvatar) {
                dropdowns.forEach(dropdown => {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
            
            // Close all dropdowns on resize
            document.querySelectorAll('.user-dropdown').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });

        // Eye icon toggle
        document.querySelector('.eye-icon').addEventListener('click', function() {
            const icon = this.querySelector('i');
            const amount = document.querySelector('.balance-amount');
            
            if (icon.classList.contains('fa-eye-slash')) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                amount.textContent = '****';
            } else {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                amount.textContent = '$0.00';
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme based on saved preference or system preference
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.body.setAttribute('data-theme', savedTheme);
                document.querySelectorAll('.theme-toggle-checkbox').forEach(toggle => {
                    toggle.checked = savedTheme === 'dark';
                });
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.body.setAttribute('data-theme', 'dark');
                document.querySelectorAll('.theme-toggle-checkbox').forEach(toggle => {
                    toggle.checked = true;
                });
            }

            // Add event listeners for theme toggles
            document.querySelectorAll('.theme-toggle-checkbox').forEach(toggle => {
                toggle.addEventListener('change', toggleTheme);
            });
        });

// Enhanced Dashboard JavaScript - Add to your existing dashboard.js

// Enhanced Dashboard Class
class EnhancedDashboard {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.startPeriodicUpdates();
        this.checkWelcomeBonus();
    }

    init() {
        this.balancesVisible = true;
        this.lastBalance = null;
        this.notificationQueue = [];
        this.setupBalanceToggle();
        this.setupQuickActions();
        this.setupTransactionTracking();
    }

    setupEventListeners() {
        // Resend verification email
        const resendBtn = document.getElementById('resendVerification');
        if (resendBtn) {
            resendBtn.addEventListener('click', this.handleResendVerification.bind(this));
        }

        // Balance visibility toggle
        const eyeIcon = document.querySelector('.eye-icon');
        if (eyeIcon) {
            eyeIcon.addEventListener('click', this.toggleBalanceVisibility.bind(this));
        }

        // Quick action tracking
        document.querySelectorAll('.quick-item').forEach(item => {
            item.addEventListener('click', this.handleQuickAction.bind(this));
        });

        // Stat card interactions
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', this.handleStatCardClick.bind(this));
        });

        // Promo banner interaction
        const promoBanner = document.querySelector('.promo-banner');
        if (promoBanner) {
            promoBanner.addEventListener('click', this.handlePromoBannerClick.bind(this));
        }

        // Investment option clicks
        document.querySelectorAll('.investment-option').forEach(option => {
            option.addEventListener('click', this.handleInvestmentClick.bind(this));
        });

        // Transaction item clicks
        document.querySelectorAll('.transaction-item').forEach(item => {
            item.addEventListener('click', this.handleTransactionClick.bind(this));
        });
    }

    setupBalanceToggle() {
        this.balanceElements = [
            document.querySelector('.balance-amount'),
            document.querySelector('.owealth-balance'),
            ...document.querySelectorAll('.stat-value')
        ].filter(el => el && el.textContent.includes('₦'));
    }

    setupQuickActions() {
        // Add ripple effect to quick actions
        document.querySelectorAll('.quick-item').forEach(item => {
            item.addEventListener('click', this.createRipple.bind(this));
        });
    }

    setupTransactionTracking() {
        // Track page interactions for analytics
        this.pageLoadTime = Date.now();
        this.interactions = [];
    }

    async handleResendVerification(e) {
        const button = e.target;
        const originalText = button.innerHTML;
        
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
                    csrf_token: this.getCSRFToken()
                }),
                credentials: 'include'
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.showNotification('Verification email sent successfully!', 'success');
                this.trackUserAction('verification_email_resent');
            } else {
                this.showNotification(result.error || 'Failed to resend verification email', 'error');
            }
        } catch (error) {
            this.showNotification('Network error: ' + error.message, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    toggleBalanceVisibility() {
        this.balancesVisible = !this.balancesVisible;
        const eyeIcon = document.querySelector('.eye-icon i');
        
        if (this.balancesVisible) {
            eyeIcon.className = 'fas fa-eye-slash';
            this.balanceElements.forEach(el => {
                if (el) el.style.filter = 'none';
            });
        } else {
            eyeIcon.className = 'fas fa-eye';
            this.balanceElements.forEach(el => {
                if (el) el.style.filter = 'blur(8px)';
            });
        }
        
        this.trackUserAction('balance_visibility_toggle', { visible: this.balancesVisible });
    }

    handleQuickAction(e) {
        const actionElement = e.currentTarget;
        const actionType = actionElement.querySelector('.quick-text').textContent;
        
        // Add loading state
        actionElement.classList.add('loading');
        
        // Track the action
        this.trackUserAction('quick_action_click', { 
            action: actionType,
            position: Array.from(actionElement.parentElement.children).indexOf(actionElement)
        });

        // Remove loading state after navigation
        setTimeout(() => {
            actionElement.classList.remove('loading');
        }, 1000);
    }

    handleStatCardClick(e) {
        const card = e.currentTarget;
        const statType = card.querySelector('.stat-label').textContent;
        
        this.trackUserAction('stat_card_click', { type: statType });
        
        // Navigate based on stat type
        switch(statType.toLowerCase()) {
            case 'owealth savings':
                window.location.href = 'owealth.php';
                break;
            case 'cashback this month':
                window.location.href = 'transactions.php?filter=cashback';
                break;
            case 'transfer success rate':
                window.location.href = 'transactions.php?filter=transfers';
                break;
            case 'all bill payments':
                window.location.href = 'bills.php';
                break;
        }
    }

    handlePromoBannerClick() {
        this.trackUserAction('promo_banner_click');
        this.showWelcomeBonusModal();
    }

    handleInvestmentClick(e) {
        const option = e.currentTarget;
        const investmentType = option.querySelector('div div').textContent;
        
        this.trackUserAction('investment_option_click', { type: investmentType });
        window.location.href = `investments.php?type=${encodeURIComponent(investmentType.toLowerCase())}`;
    }

    handleTransactionClick(e) {
        const transaction = e.currentTarget;
        const transactionType = transaction.querySelector('.transaction-info h4').textContent;
        const reference = transaction.querySelector('.transaction-details').textContent.split(' • ')[0];
        
        this.trackUserAction('transaction_click', { type: transactionType, reference });
        window.location.href = `transaction_details.php?ref=${encodeURIComponent(reference)}`;
    }

    createRipple(e) {
        const button = e.currentTarget;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        if (!button.style.position || button.style.position === 'static') {
            button.style.position = 'relative';
        }
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.parentNode.removeChild(ripple);
            }
        }, 600);
    }

    async refreshBalance() {
        try {
            const response = await fetch('api/balance.php', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                const newBalance = parseFloat(data.balance);
                const balanceElement = document.querySelector('.balance-amount');
                
                if (this.lastBalance !== null && newBalance !== this.lastBalance) {
                    // Balance changed, update and notify
                    const formattedBalance = '₦' + newBalance.toLocaleString('en-NG', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    if (balanceElement) {
                        balanceElement.textContent = formattedBalance;
                        balanceElement.style.animation = 'pulse 0.5s ease';
                    }
                    
                    if (newBalance > this.lastBalance) {
                        this.showNotification(`Balance increased by ₦${(newBalance - this.lastBalance).toFixed(2)}`, 'success');
                    }
                }
                
                this.lastBalance = newBalance;
                
                // Update Owealth balance if available
                if (data.owealth_balance) {
                    const owealthElement = document.querySelector('.owealth-balance');
                    if (owealthElement) {
                        owealthElement.textContent = '₦' + parseFloat(data.owealth_balance).toLocaleString('en-NG', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
            }
        } catch (error) {
            console.log('Balance refresh failed:', error);
        }
    }

    async checkWelcomeBonus() {
        try {
            const response = await fetch('api/bonus.php?check_welcome=1', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.eligible) {
                setTimeout(() => {
                    this.showWelcomeBonusModal();
                }, 2000); // Show after 2 seconds
            }
        } catch (error) {
            console.log('Welcome bonus check failed:', error);
        }
    }

    showWelcomeBonusModal() {
        const modal = document.createElement('div');
        modal.className = 'welcome-bonus-modal';
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
            backdrop-filter: blur(5px);
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                padding: 30px;
                border-radius: 16px;
                text-align: center;
                max-width: 320px;
                margin: 20px;
                animation: slideInUp 0.3s ease;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            ">
                <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
                <h3 style="margin-bottom: 8px; color: var(--text-primary); font-weight: 700;">Welcome Bonus Ready!</h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px; font-size: 14px; line-height: 1.4;">
                    Complete your first transaction to earn <strong>₦500 bonus</strong> instantly!
                </p>
                <div style="display: flex; gap: 10px;">
                    <button id="claimBonus" style="
                        flex: 1;
                        background: var(--primary-color);
                        color: white;
                        border: none;
                        padding: 12px 16px;
                        border-radius: 8px;
                        font-weight: 600;
                        cursor: pointer;
                        font-size: 14px;
                    ">Start Transaction</button>
                    <button id="dismissModal" style="
                        background: transparent;
                        color: var(--text-secondary);
                        border: 1px solid var(--border);
                        padding: 12px 16px;
                        border-radius: 8px;
                        font-weight: 600;
                        cursor: pointer;
                        font-size: 14px;
                    ">Later</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Event listeners
        modal.querySelector('#claimBonus').addEventListener('click', () => {
            this.trackUserAction('welcome_bonus_claim_clicked');
            window.location.href = 'transfer.php';
        });
        
        modal.querySelector('#dismissModal').addEventListener('click', () => {
            this.trackUserAction('welcome_bonus_dismissed');
            document.body.removeChild(modal);
        });
        
        // Auto-dismiss after 15 seconds
        setTimeout(() => {
            if (document.body.contains(modal)) {
                document.body.removeChild(modal);
                this.trackUserAction('welcome_bonus_auto_dismissed');
            }
        }, 15000);
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ';
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 16px;">${icon}</span>
                <span>${message}</span>
            </div>
        `;
        
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
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 4000);
    }

    trackUserAction(action, data = {}) {
        this.interactions.push({
            action,
            data,
            timestamp: Date.now(),
            url: window.location.pathname
        });

        // Send to analytics endpoint
        fetch('api/analytics.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                data: data,
                timestamp: new Date().toISOString(),
                session_duration: Date.now() - this.pageLoadTime
            }),
            credentials: 'include'
        }).catch(error => {
            console.log('Analytics tracking failed:', error);
        });
    }

    getCSRFToken() {
        const tokenElement = document.querySelector('input[name="csrf_token"]') || 
                            document.querySelector('meta[name="csrf-token"]');
        return tokenElement ? tokenElement.value || tokenElement.content : '';
    }

    startPeriodicUpdates() {
        // Refresh balance every 30 seconds
        setInterval(() => {
            this.refreshBalance();
        }, 30000);

        // Send analytics data every 2 minutes
        setInterval(() => {
            if (this.interactions.length > 0) {
                this.trackUserAction('session_summary', {
                    interactions: this.interactions.length,
                    session_duration: Date.now() - this.pageLoadTime
                });
            }
        }, 120000);
    }

    // Utility function to format currency
    formatCurrency(amount) {
        return '₦' + parseFloat(amount).toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Function to handle offline/online states
    handleConnectionChange() {
        if (navigator.onLine) {
            this.showNotification('Connection restored', 'success');
            this.refreshBalance();
        } else {
            this.showNotification('No internet connection', 'error');
        }
    }
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);

// Initialize enhanced dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.enhancedDashboard = new EnhancedDashboard();
    
    // Handle connection changes
    window.addEventListener('online', () => window.enhancedDashboard.handleConnectionChange());
    window.addEventListener('offline', () => window.enhancedDashboard.handleConnectionChange());
    
    // Handle page visibility for performance
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            window.enhancedDashboard.refreshBalance();
        }
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedDashboard;
}