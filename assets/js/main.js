// PayForeign Universal JavaScript
class PayForeignApp {
    constructor() {
        this.currentStep = 1;
        this.maxSteps = 2;
        this.formData = {};
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateStepIndicator();
    }

    setupEventListeners() {
        // Password toggle functionality
        const passwordToggles = document.querySelectorAll('.password-toggle');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', this.togglePassword.bind(this));
        });

        // Continue button (Step 1 to Step 2)
        const continueBtn = document.getElementById('continueBtn');
        if (continueBtn) {
            continueBtn.addEventListener('click', this.handleContinue.bind(this));
        }

        // Back button (Step 2 to Step 1)
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.addEventListener('click', this.handleBack.bind(this));
        }

        // Create Account button
        const createAccountBtn = document.getElementById('createAccountBtn');
        if (createAccountBtn) {
            createAccountBtn.addEventListener('click', this.handleCreateAccount.bind(this));
        }

        // Sign In button
        const signInBtn = document.getElementById('signInBtn');
        if (signInBtn) {
            signInBtn.addEventListener('click', this.handleSignIn.bind(this));
        }

        // Form validation on input
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('input', this.validateInput.bind(this));
            input.addEventListener('blur', this.validateInput.bind(this));
        });

        // Page navigation links
        const signInLink = document.getElementById('signInLink');
        const createAccountLink = document.getElementById('createAccountLink');
        
        if (signInLink) {
            signInLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchToLogin();
            });
        }

        if (createAccountLink) {
            createAccountLink.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchToSignup();
            });
        }
    }

    togglePassword(e) {
        const toggle = e.target;
        const input = toggle.previousElementSibling;
        
        if (input.type === 'password') {
            input.type = 'text';
            toggle.innerHTML = '👁️';
        } else {
            input.type = 'password';
            toggle.innerHTML = '👁️‍🗨️';
        }
    }

    validateInput(e) {
        const input = e.target;
        const value = input.value.trim();
        
        // Remove existing error styling
        input.classList.remove('error');
        
        // Basic validation
        if (input.hasAttribute('required') && !value) {
            this.showInputError(input, 'This field is required');
            return false;
        }

        // Email validation
        if (input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                this.showInputError(input, 'Please enter a valid email address');
                return false;
            }
        }

        // Phone validation
        if (input.name === 'phone' && value) {
            const phoneRegex = /^[\+]?[\d\s\-\(\)]{10,}$/;
            if (!phoneRegex.test(value)) {
                this.showInputError(input, 'Please enter a valid phone number');
                return false;
            }
        }

        // Password validation
        if (input.name === 'password' && value) {
            if (value.length < 8) {
                this.showInputError(input, 'Password must be at least 8 characters long');
                return false;
            }
        }

        // Confirm password validation
        if (input.name === 'confirmPassword' && value) {
            const passwordInput = document.querySelector('input[name="password"]');
            if (passwordInput && value !== passwordInput.value) {
                this.showInputError(input, 'Passwords do not match');
                return false;
            }
        }

        this.clearInputError(input);
        return true;
    }

    showInputError(input, message) {
        input.classList.add('error');
        
        // Remove existing error message
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }

        // Add error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 4px;';
        
        input.parentNode.appendChild(errorDiv);
    }

    clearInputError(input) {
        input.classList.remove('error');
        const errorMessage = input.parentNode.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    }

    validateStep(step) {
        let isValid = true;
        const stepContainer = document.getElementById(`step${step}`);
        
        if (!stepContainer) return isValid;

        const requiredInputs = stepContainer.querySelectorAll('input[required]');
        
        requiredInputs.forEach(input => {
            if (!this.validateInput({ target: input })) {
                isValid = false;
            }
        });

        return isValid;
    }

    handleContinue(e) {
        e.preventDefault();
        
        if (!this.validateStep(1)) {
            this.showNotification('Please fill in all required fields correctly.', 'error');
            return;
        }

        // Store step 1 data
        const step1Inputs = document.querySelectorAll('#step1 input');
        step1Inputs.forEach(input => {
            this.formData[input.name] = input.value;
        });

        this.nextStep();
    }

    handleBack(e) {
        e.preventDefault();
        this.previousStep();
    }

    handleCreateAccount(e) {
        e.preventDefault();
        
        if (!this.validateStep(2)) {
            this.showNotification('Please fill in all required fields correctly.', 'error');
            return;
        }

        // Store step 2 data
        const step2Inputs = document.querySelectorAll('#step2 input');
        step2Inputs.forEach(input => {
            this.formData[input.name] = input.value;
        });

        // Simulate account creation
        this.showNotification('Creating your account...', 'info');
        
        setTimeout(() => {
            this.showNotification('Account created successfully! Welcome to PayForeign!', 'success');
            console.log('Account Data:', this.formData);
            
            // In a real app, you would redirect to dashboard
            setTimeout(() => {
                this.switchToLogin();
                this.showNotification('Please sign in to your new account.', 'info');
            }, 2000);
        }, 1500);
    }

    handleSignIn(e) {
        e.preventDefault();
        
        const email = document.querySelector('input[name="email"]').value;
        const password = document.querySelector('input[name="password"]').value;

        if (!email || !password) {
            this.showNotification('Please enter both email and password.', 'error');
            return;
        }

        if (!this.validateInput({ target: document.querySelector('input[name="email"]') }) ||
            !this.validateInput({ target: document.querySelector('input[name="password"]') })) {
            return;
        }

        // Simulate sign in
        this.showNotification('Signing you in...', 'info');
        
        setTimeout(() => {
            this.showNotification('Welcome back! Redirecting to your dashboard...', 'success');
            console.log('Sign In Data:', { email, password });
            
            // In a real app, you would redirect to dashboard
        }, 1500);
    }

    nextStep() {
        if (this.currentStep < this.maxSteps) {
            document.getElementById(`step${this.currentStep}`).classList.add('hidden');
            this.currentStep++;
            document.getElementById(`step${this.currentStep}`).classList.remove('hidden');
            this.updateStepIndicator();
        }
    }

    previousStep() {
        if (this.currentStep > 1) {
            document.getElementById(`step${this.currentStep}`).classList.add('hidden');
            this.currentStep--;
            document.getElementById(`step${this.currentStep}`).classList.remove('hidden');
            this.updateStepIndicator();
        }
    }

    updateStepIndicator() {
        const stepIndicator = document.querySelector('.step-indicator');
        if (!stepIndicator) return;

        // Update step circles
        for (let i = 1; i <= this.maxSteps; i++) {
            const stepElement = document.getElementById(`stepIndicator${i}`);
            const labelElement = document.getElementById(`stepLabel${i}`);
            
            if (stepElement) {
                if (i <= this.currentStep) {
                    stepElement.classList.remove('inactive');
                    stepElement.classList.add('active');
                } else {
                    stepElement.classList.remove('active');
                    stepElement.classList.add('inactive');
                }
            }

            if (labelElement) {
                if (i <= this.currentStep) {
                    labelElement.classList.add('active');
                } else {
                    labelElement.classList.remove('active');
                }
            }
        }

        // Update connector
        const connector = document.querySelector('.step-connector');
        if (connector) {
            if (this.currentStep > 1) {
                connector.classList.add('active');
            } else {
                connector.classList.remove('active');
            }
        }
    }

    switchToLogin() {
        const signupPage = document.getElementById('signupPage');
        const loginPage = document.getElementById('loginPage');
        
        if (signupPage && loginPage) {
            signupPage.classList.add('hidden');
            loginPage.classList.remove('hidden');
        }

        // Update left section content
        this.updateLeftSection('login');
    }

    switchToSignup() {
        const signupPage = document.getElementById('signupPage');
        const loginPage = document.getElementById('loginPage');
        
        if (signupPage && loginPage) {
            loginPage.classList.add('hidden');
            signupPage.classList.remove('hidden');
        }

        // Reset to step 1
        if (this.currentStep !== 1) {
            document.getElementById(`step${this.currentStep}`).classList.add('hidden');
            this.currentStep = 1;
            document.getElementById('step1').classList.remove('hidden');
            this.updateStepIndicator();
        }

        // Update left section content
        this.updateLeftSection('signup');
    }

    updateLeftSection(pageType) {
        const heading = document.querySelector('.main-heading');
        const description = document.querySelector('.main-description');

        if (pageType === 'login') {
            heading.textContent = 'Welcome Back!';
            description.textContent = 'Access your account to manage your international transfers, track your transactions, and more.';
        } else {
            heading.textContent = 'Join PayForeign Today';
            description.textContent = 'Create your account to start sending money internationally with competitive rates and fast transfers.';
        }
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            max-width: 300px;
            word-wrap: break-word;
        `;

        // Color based on type
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6',
            warning: '#f59e0b'
        };

        notification.style.background = colors[type] || colors.info;

        // Add to document
        document.body.appendChild(notification);

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (notification && notification.parentNode) {
                notification.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 4000);
    }
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .form-input.error {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
`;
document.head.appendChild(style);

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.payForeignApp = new PayForeignApp();
});

// Export for potential module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PayForeignApp;
}