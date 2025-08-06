 const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const submitBtn = document.getElementById('submitBtn');
        
        // Password validation requirements
        const requirements = {
            length: { element: document.getElementById('lengthReq'), test: (pwd) => pwd.length >= 8 },
            uppercase: { element: document.getElementById('uppercaseReq'), test: (pwd) => /[A-Z]/.test(pwd) },
            lowercase: { element: document.getElementById('lowercaseReq'), test: (pwd) => /[a-z]/.test(pwd) },
            number: { element: document.getElementById('numberReq'), test: (pwd) => /\d/.test(pwd) },
            special: { element: document.getElementById('specialReq'), test: (pwd) => /[!@#$%^&*]/.test(pwd) },
            match: { element: document.getElementById('matchReq'), test: (pwd) => pwd === confirmPasswordInput.value && pwd.length > 0 }
        };
        
        function updateRequirement(req, isValid) {
            const icon = req.element.querySelector('i');
            if (isValid) {
                req.element.classList.add('valid');
                req.element.classList.remove('invalid');
                icon.className = 'fas fa-check';
            } else {
                req.element.classList.add('invalid');
                req.element.classList.remove('valid');
                icon.className = 'fas fa-times';
            }
        }
        
        function validatePassword() {
            const password = newPasswordInput.value;
            let allValid = true;
            
            Object.keys(requirements).forEach(key => {
                const req = requirements[key];
                const isValid = req.test(password);
                updateRequirement(req, isValid);
                if (!isValid) allValid = false;
            });
            
            submitBtn.disabled = !allValid;
            return allValid;
        }
        
        newPasswordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + 'Icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validatePassword()) {
                showError('Please ensure all password requirements are met.');
                return;
            }
            
            const submitText = document.getElementById('submitText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            
            // Show loading state
            submitBtn.disabled = true;
            submitText.textContent = 'Resetting...';
            loadingSpinner.classList.remove('hidden');
            errorMessage.style.display = 'none';
            
            // Simulate API call
            setTimeout(() => {
                // Hide loading state
                submitText.textContent = 'Reset Password';
                loadingSpinner.classList.add('hidden');
                
                // Check if reset token is valid (simulate)
                const urlParams = new URLSearchParams(window.location.search);
                const token = urlParams.get('token');
                
                if (!token) {
                    showError('Invalid or expired reset link. Please request a new password reset.');
                    return;
                }
                
                // Show success message
                successMessage.style.display = 'block';
                
                // Clear form
                newPasswordInput.value = '';
                confirmPasswordInput.value = '';
                validatePassword();
                
                // Auto redirect after 3 seconds
                setTimeout(() => {
                    goToLogin();
                }, 3000);
            }, 2000);
        });
        
        function showError(message) {
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorMessage.style.display = 'block';
            submitBtn.disabled = false;
        }
        
        function goToLogin() {
            // In a real application, this would redirect to the login page
            window.location.href = 'login.html';
        }
        
        // Check if reset token exists on page load
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            
            if (!token) {
                showError('Invalid or expired reset link. Please request a new password reset.');
                document.getElementById('resetPasswordForm').style.display = 'none';
            }
        });