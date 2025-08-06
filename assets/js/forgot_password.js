 document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('emailInput').value;
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const successMessage = document.getElementById('successMessage');
            
            // Validate email
            if (!email || !isValidEmail(email)) {
                alert('Please enter a valid email address');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitText.textContent = 'Sending...';
            loadingSpinner.classList.remove('hidden');
            
            // Simulate API call
            setTimeout(() => {
                // Hide loading state
                submitBtn.disabled = false;
                submitText.textContent = 'Send Reset Link';
                loadingSpinner.classList.add('hidden');
                
                // Show success message
                successMessage.style.display = 'block';
                
                // Clear form
                document.getElementById('emailInput').value = '';
                
                // Auto redirect after 3 seconds
                setTimeout(() => {
                    goToLogin();
                }, 3000);
            }, 2000);
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        function goToLogin() {
            // In a real application, this would redirect to the login page
            window.location.href = 'login.html';
        }