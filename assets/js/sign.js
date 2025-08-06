async function makeApiCall(url, method, data = null, isFormData = false) {
    try {
        const options = {
            method: method,
            headers: {
                'Accept': 'application/json',
                ...(isFormData ? {} : {'Content-Type': 'application/json'})
            },
            credentials: 'include'
        };
        
        // Get CSRF token from hidden input
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
        
        if (method === 'POST' && data) {
            options.body = isFormData ? data : JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        const text = await response.text();
        
        try {
            const result = JSON.parse(text);
            
            // Always update CSRF token if provided
            if (result.csrf_token) {
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => {
                    el.value = result.csrf_token;
                });
            }
            
            if (!response.ok) {
                // Handle specific error codes with better messages
                if (response.status === 409) {
                    throw new Error(result.error || 'This email or phone number is already registered');
                }
                if (response.status === 403) {
                    throw new Error('Session expired. Please refresh the page and try again.');
                }
                throw new Error(result.error || `HTTP ${response.status}`);
            }
            
            return result;
        } catch (parseError) {
            // Try to extract error message even if JSON parsing fails
            if (text.includes('Email or phone already exists')) {
                throw new Error('This email or phone number is already registered');
            }
            console.error('Failed to parse JSON response:', text);
            throw new Error('Invalid server response format');
        }
    } catch (error) {
        console.error('API call failed:', error);
        throw new Error(error.message || 'Network error');
    }
}

// Data object to store registration information
let registrationData = {
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    password: '',
    verificationType: '',
    bvnMethod: '',
    bvnValue: null,
    ninMethod: '',
    ninValue: '',
    ninFiles: { front: null, back: null }
};

// Password validation requirements
function getPasswordRequirements() {
    return {
        length: { 
            element: document.getElementById('lengthReq'), 
            test: (pwd) => pwd.length >= 8 
        },
        uppercase: { 
            element: document.getElementById('uppercaseReq'), 
            test: (pwd) => /[A-Z]/.test(pwd) 
        },
        lowercase: { 
            element: document.getElementById('lowercaseReq'), 
            test: (pwd) => /[a-z]/.test(pwd) 
        },
        number: { 
            element: document.getElementById('numberReq'), 
            test: (pwd) => /\d/.test(pwd) 
        },
        special: { 
            element: document.getElementById('specialReq'), 
            test: (pwd) => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pwd) 
        },
        match: { 
            element: document.getElementById('matchReq'), 
            test: (pwd, confirmPwd) => pwd === confirmPwd && pwd.length > 0 && confirmPwd.length > 0 
        }
    };
}

function showError(message) {
    // Map specific error codes to friendly messages
    const errorMap = {
        'Invalid server response format': 'There was a problem processing your request. Please try again.',
        'Email or phone already exists': 'This email or phone number is already registered. Please use a different one or login.',
        'Invalid CSRF token': 'Your session has expired. Please refresh the page and try again.'
    };
    
    const friendlyMessage = errorMap[message] || message;
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${DOMPurify.sanitize(friendlyMessage)}`;
    const container = document.querySelector('.right-section');
    const existingError = container.querySelector('.error-message');
    if (existingError) existingError.remove();
    container.insertBefore(errorDiv, container.firstChild);
    setTimeout(() => errorDiv.remove(), 5000);
}

// Utility function to show success messages
function showSuccess(message) {
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message';
    successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${DOMPurify.sanitize(message)}`;
    const container = document.querySelector('.right-section');
    const existingSuccess = container.querySelector('.success-message');
    if (existingSuccess) existingSuccess.remove();
    container.insertBefore(successDiv, container.firstChild);
    setTimeout(() => successDiv.remove(), 5000);
}

// Utility function to toggle loading state
function toggleLoading(button, isLoading) {
    if (!button) {
        console.error('Button element not found');
        return;
    }
    const text = button.querySelector('span');
    const spinner = button.querySelector('.fa-spinner');
    button.disabled = isLoading;
    if (text) text.classList.toggle('hidden', isLoading);
    if (spinner) spinner.classList.toggle('hidden', !isLoading);
}

// Navigation functions
function showStep1() {
    hideAllSteps();
    document.getElementById('register-step1').classList.remove('hidden');
    document.getElementById('left-title').textContent = 'Join Ubiaza Today';
    document.getElementById('left-description').textContent = 'Create your account to start sending money internationally with competitive rates and fast transfers.';
    populateStep1Form();
}

function showStep2() {
    hideAllSteps();
    document.getElementById('register-step2').classList.remove('hidden');
    document.getElementById('left-title').textContent = 'Secure Your Account';
    document.getElementById('left-description').textContent = 'Create a strong password to protect your account.';
    initializePasswordValidation();
}

function showVerificationType() {
    hideAllSteps();
    document.getElementById('register-step3-type').classList.remove('hidden');
    resetVerification();
    document.getElementById('left-title').textContent = 'Verify Your Identity';
    document.getElementById('left-description').textContent = 'Select a verification method to secure your account.';
}

function showBvnOptions() {
    hideAllSteps();
    document.getElementById('register-step3-bvn-options').classList.remove('hidden');
    registrationData.verificationType = 'bvn';
    resetVerificationDetails();
    document.getElementById('left-title').textContent = 'BVN Verification';
    document.getElementById('left-description').textContent = 'Provide your BVN to verify your identity.';
}

function showNinOptions() {
    hideAllSteps();
    document.getElementById('register-step3-nin-options').classList.remove('hidden');
    registrationData.verificationType = 'nin';
    resetVerificationDetails();
    document.getElementById('left-title').textContent = 'NIN Verification';
    document.getElementById('left-description').textContent = 'Upload your NIN documents to verify your identity.';
}

function showBvnDetails() {
    hideAllSteps();
    document.getElementById('register-step3-bvn-details').classList.remove('hidden');
    if (registrationData.bvnMethod === 'input') {
        document.getElementById('bvn-input-section').classList.remove('hidden');
        const bvnInput = document.getElementById('bvnNumber');
        if (registrationData.bvnValue) bvnInput.value = registrationData.bvnValue;
    } else if (registrationData.bvnMethod === 'upload') {
        document.getElementById('bvn-upload-section').classList.remove('hidden');
    }
    checkVerificationCompletion();
}

function showNinDetails() {
    hideAllSteps();
    document.getElementById('register-step3-nin-details').classList.remove('hidden');
    if (registrationData.ninMethod === 'camera') {
        document.getElementById('nin-camera-section').classList.remove('hidden');
    } else if (registrationData.ninMethod === 'upload') {
        document.getElementById('nin-upload-section').classList.remove('hidden');
    }
    checkVerificationCompletion();
}

function showStep4() {
    hideAllSteps();
    updateSummary();
    document.getElementById('register-step4').classList.remove('hidden');
    document.getElementById('left-title').textContent = 'Review Your Information';
    document.getElementById('left-description').textContent = 'Please review your details before submitting.';
}

function hideAllSteps() {
    document.getElementById('register-step1').classList.add('hidden');
    document.getElementById('register-step2').classList.add('hidden');
    document.getElementById('register-step3-type').classList.add('hidden');
    document.getElementById('register-step3-bvn-options').classList.add('hidden');
    document.getElementById('register-step3-bvn-details').classList.add('hidden');
    document.getElementById('register-step3-nin-options').classList.add('hidden');
    document.getElementById('register-step3-nin-details').classList.add('hidden');
    document.getElementById('register-step4').classList.add('hidden');
    document.getElementById('login-form').classList.add('hidden');
}

function showLogin() {
    hideAllSteps();
    document.getElementById('login-form').classList.remove('hidden');
    document.getElementById('left-title').textContent = 'Welcome Back!';
    document.getElementById('left-description').textContent = 'Access your account to manage your international transfers, track your transactions, and more.';
}

function showRegister() {
    hideAllSteps();
    document.getElementById('register-step1').classList.remove('hidden');
    document.getElementById('left-title').textContent = 'Join Ubiaza Today';
    document.getElementById('left-description').textContent = 'Create your account to start sending money internationally with competitive rates and fast transfers.';
    populateStep1Form();
}

function populateStep1Form() {
    document.getElementById('firstName').value = registrationData.firstName;
    document.getElementById('lastName').value = registrationData.lastName;
    document.getElementById('email').value = registrationData.email;
    document.getElementById('phone').value = registrationData.phone;
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function selectBvnOption(option) {
    document.querySelectorAll('.bvn-option').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    registrationData.bvnMethod = option;
    showBvnDetails();
}

function selectNinOption(option) {
    document.querySelectorAll('.nin-option').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    registrationData.ninMethod = option;
    showNinDetails();
}

function handleBvnFileUpload(input) {
    const file = input.files[0];
    if (file) {
        if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            showError('Please upload a valid PDF, JPG, or PNG file (max 5MB).');
            input.value = '';
            return;
        }
        const fileDiv = document.createElement('div');
        fileDiv.className = 'uploaded-file';
        fileDiv.innerHTML = `
            <i class="fas fa-file"></i>
            <span>${DOMPurify.sanitize(file.name)}</span>
            <button type="button" class="remove-file" onclick="removeBvnFile()">
                <i class="fas fa-times"></i>
            </button>
        `;
        document.getElementById('bvn-uploaded-files').innerHTML = '';
        document.getElementById('bvn-uploaded-files').appendChild(fileDiv);
        registrationData.bvnValue = file;
        checkVerificationCompletion();
    }
}

function handleNinFileUpload(input, side) {
    const file = input.files[0];
    if (file) {
        if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            showError('Please upload a valid JPG or PNG file (max 5MB).');
            input.value = '';
            return;
        }
        const fileDiv = document.createElement('div');
        fileDiv.className = 'uploaded-file';
        fileDiv.innerHTML = `
            <i class="fas fa-file-image"></i>
            <span>${DOMPurify.sanitize(file.name)}</span>
            <button type="button" class="remove-file" onclick="removeNinFile('${side}')">
                <i class="fas fa-times"></i>
            </button>
        `;
        const containerId = registrationData.ninMethod === 'camera' ? `nin-${side}-camera-files` : `nin-${side}-files`;
        document.getElementById(containerId).innerHTML = '';
        document.getElementById(containerId).appendChild(fileDiv);
        registrationData.ninFiles[side] = file;
        checkVerificationCompletion();
    }
}

function removeBvnFile() {
    document.getElementById('bvn-uploaded-files').innerHTML = '';
    document.getElementById('bvnFile').value = '';
    registrationData.bvnValue = null;
    checkVerificationCompletion();
}

function removeNinFile(side) {
    const containerId = registrationData.ninMethod === 'camera' ? `nin-${side}-camera-files` : `nin-${side}-files`;
    document.getElementById(containerId).innerHTML = '';
    document.getElementById(registrationData.ninMethod === 'camera' ? `nin${side.charAt(0).toUpperCase() + side.slice(1)}Camera` : `nin${side.charAt(0).toUpperCase() + side.slice(1)}`).value = '';
    registrationData.ninFiles[side] = null;
    checkVerificationCompletion();
}

function resetVerification() {
    registrationData.verificationType = '';
    resetVerificationDetails();
    document.querySelectorAll('.verification-option').forEach(el => el.classList.remove('selected'));
    document.querySelectorAll('.bvn-option').forEach(el => el.classList.remove('selected'));
    document.querySelectorAll('.nin-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('bvn-input-section').classList.add('hidden');
    document.getElementById('bvn-upload-section').classList.add('hidden');
    document.getElementById('nin-camera-section').classList.add('hidden');
    document.getElementById('nin-upload-section').classList.add('hidden');
    document.getElementById('bvn-uploaded-files').innerHTML = '';
    document.getElementById('nin-front-camera-files').innerHTML = '';
    document.getElementById('nin-back-camera-files').innerHTML = '';
    document.getElementById('nin-front-files').innerHTML = '';
    document.getElementById('nin-back-files').innerHTML = '';
    document.getElementById('bvn-continue-btn').disabled = true;
    document.getElementById('nin-continue-btn').disabled = true;
}

function resetVerificationDetails() {
    registrationData.bvnMethod = '';
    registrationData.bvnValue = null;
    registrationData.ninMethod = '';
    registrationData.ninFiles = { front: null, back: null };
}

function checkVerificationCompletion() {
    if (registrationData.verificationType === 'bvn') {
        const bvnInput = document.getElementById('bvnNumber');
        const continueBtn = document.getElementById('bvn-continue-btn');
        if (registrationData.bvnMethod === 'input') {
            const isValid = bvnInput.value.length === 11 && /^\d{11}$/.test(bvnInput.value);
            continueBtn.disabled = !isValid;
            if (isValid) registrationData.bvnValue = bvnInput.value;
        } else if (registrationData.bvnMethod === 'upload') {
            continueBtn.disabled = !registrationData.bvnValue;
        }
    } else if (registrationData.verificationType === 'nin') {
        const continueBtn = document.getElementById('nin-continue-btn');
        const hasAllFiles = registrationData.ninFiles.front && registrationData.ninFiles.back;
        continueBtn.disabled = !hasAllFiles;
    }
}

function updateSummary() {
    document.getElementById('summary-name').textContent = DOMPurify.sanitize(`${registrationData.firstName} ${registrationData.lastName}`);
    document.getElementById('summary-email').textContent = DOMPurify.sanitize(registrationData.email);
    document.getElementById('summary-phone').textContent = DOMPurify.sanitize(registrationData.phone);
    let verificationText = '-';
    if (registrationData.verificationType === 'bvn') {
        verificationText = registrationData.bvnMethod === 'input' ? `BVN: ${registrationData.bvnValue}` : 'BVN Document Uploaded';
    } else if (registrationData.verificationType === 'nin') {
        verificationText = `NIN: Front & Back Uploaded (${registrationData.ninMethod})`;
    } else if (registrationData.verificationType === 'skip') {
        verificationText = 'Verification skipped (will be required later)';
    }
    document.getElementById('summary-verification').textContent = DOMPurify.sanitize(verificationText);
}

function updateRequirement(req, isValid) {
    if (!req.element) return;
    const icon = req.element.querySelector('i');
    if (!icon) return;
    
    req.element.classList.toggle('valid', isValid);
    req.element.classList.toggle('invalid', !isValid);
    icon.className = isValid ? 'fas fa-check' : 'fas fa-times';
}

function validatePassword() {
    const passwordElement = document.getElementById('password');
    const confirmPasswordElement = document.getElementById('confirmPassword');
    
    if (!passwordElement || !confirmPasswordElement) {
        console.error('Password input elements not found');
        return false;
    }
    
    const password = passwordElement.value;
    const confirmPassword = confirmPasswordElement.value;
    const requirements = getPasswordRequirements();
    
    let allValid = true;
    let validCount = 0;

    Object.keys(requirements).forEach(key => {
        const req = requirements[key];
        if (!req.element) {
            console.warn(`Requirement element not found for: ${key}`);
            return;
        }
        
        let isValid;
        if (key === 'match') {
            isValid = req.test(password, confirmPassword);
        } else {
            isValid = req.test(password);
        }
        
        updateRequirement(req, isValid);
        
        if (isValid) validCount++;
        if (!isValid) allValid = false;
    });

    const submitButton = document.getElementById('securityContinue');
    if (submitButton) {
        submitButton.disabled = !allValid;
    }
    
    return allValid;
}

function initializePasswordValidation() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    if (passwordInput && confirmPasswordInput) {
        passwordInput.removeEventListener('input', validatePassword);
        confirmPasswordInput.removeEventListener('input', validatePassword);
        
        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);
        
        validatePassword();
    }
}

// Form event listeners
document.getElementById('personalInfoForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    toggleLoading(button, true);
    
    try {
        registrationData.firstName = DOMPurify.sanitize(document.getElementById('firstName').value.trim());
        registrationData.lastName = DOMPurify.sanitize(document.getElementById('lastName').value.trim());
        registrationData.email = DOMPurify.sanitize(document.getElementById('email').value.trim());
        registrationData.phone = DOMPurify.sanitize(document.getElementById('phone').value.trim());
        
        if (!/^[A-Za-z\s]{2,50}$/.test(registrationData.firstName)) {
            throw new Error('First name must be 2-50 characters and contain only letters and spaces');
        }
        if (!/^[A-Za-z\s]{2,50}$/.test(registrationData.lastName)) {
            throw new Error('Last name must be 2-50 characters and contain only letters and spaces');
        }
        if (!/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(registrationData.email)) {
            throw new Error('Invalid email format');
        }
        if (!/^\+?\d{10,15}$/.test(registrationData.phone)) {
            throw new Error('Invalid phone number format');
        }

        const checkData = {
            action: 'check_existing',
            csrf_token: document.querySelector('#personalInfoForm input[name="csrf_token"]').value,
            email: registrationData.email,
            phone: registrationData.phone
        };
        
        const checkResult = await makeApiCall('api/auth.php', 'POST', checkData);
        
        // Store in session
        const sessionData = {
            action: 'store_session_data',
            csrf_token: checkResult.csrf_token || checkData.csrf_token,
            registration_data: {
                firstName: registrationData.firstName,
                lastName: registrationData.lastName,
                email: registrationData.email,
                phone: registrationData.phone
            }
        };
        
        await makeApiCall('api/auth.php', 'POST', sessionData);
        
        showSuccess('Personal information saved');
        showStep2();
    } catch (error) {
        showError(error.message);
    } finally {
        toggleLoading(button, false);
    }
});

document.getElementById('securityForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    toggleLoading(button, true);
    
    try {
        const password = document.getElementById('password').value;
        if (!validatePassword()) {
            throw new Error('Please ensure all password requirements are met');
        }

        registrationData.password = password;
        
        const sessionData = {
            action: 'store_session_data',
            csrf_token: document.querySelector('#securityForm input[name="csrf_token"]').value,
            registration_data: {
                password: password
            }
        };
        
        await makeApiCall('api/auth.php', 'POST', sessionData);
        
        showSuccess('Security information saved');
        showVerificationType();
    } catch (error) {
        showError(error.message);
    } finally {
        toggleLoading(button, false);
    }
});

document.getElementById('bvnForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    toggleLoading(button, true);
    
    try {
        const formData = new FormData();
        formData.append('action', 'store_verification_data');
        formData.append('csrf_token', document.querySelector('#bvnForm input[name="csrf_token"]').value);
        formData.append('verification_type', 'bvn');
        formData.append('bvn_method', registrationData.bvnMethod);
        
        if (registrationData.bvnMethod === 'input') {
            formData.append('bvn', registrationData.bvnValue);
        } else if (registrationData.bvnMethod === 'upload') {
            formData.append('bvn_file', registrationData.bvnValue);
        }
        
        await makeApiCall('api/verification.php', 'POST', formData, true);
        showSuccess('BVN information saved');
        showStep4();
    } catch (error) {
        showError(error.message);
    } finally {
        toggleLoading(button, false);
    }
});

document.getElementById('ninForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    toggleLoading(button, true);
    
    try {
        if (!registrationData.ninFiles.front || !registrationData.ninFiles.back) {
            throw new Error('Please upload both NIN front and back images');
        }
        
        const formData = new FormData();
        formData.append('action', 'store_verification_data');
        formData.append('csrf_token', document.querySelector('#ninForm input[name="csrf_token"]').value);
        formData.append('verification_type', 'nin');
        formData.append('nin_method', registrationData.ninMethod);
        formData.append('nin', registrationData.ninValue || '');
        formData.append('nin_front', registrationData.ninFiles.front);
        formData.append('nin_back', registrationData.ninFiles.back);
        
        await makeApiCall('api/verification.php', 'POST', formData, true);
        showSuccess('NIN information saved');
        showStep4();
    } catch (error) {
        showError(error.message);
    } finally {
        toggleLoading(button, false);
    }
});

function skipVerification() {
    registrationData.verificationType = 'skip';
    showStep4();
}

document.getElementById('reviewForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    toggleLoading(button, true);
    
    try {
        const termsChecked = document.getElementById('termsCheckbox').checked;
        if (!termsChecked) {
            throw new Error('Please accept the Terms of Service and Privacy Policy');
        }
        
        const data = {
            action: 'complete_registration',
            csrf_token: document.querySelector('#reviewForm input[name="csrf_token"]').value
        };
        
        const result = await makeApiCall('api/auth.php', 'POST', data);
        showSuccess(result.message || 'Account created successfully!');
        
        // Clear registration data
        registrationData = {
            firstName: '',
            lastName: '',
            email: '',
            phone: '',
            password: '',
            verificationType: '',
            bvnMethod: '',
            bvnValue: null,
            ninMethod: '',
            ninValue: '',
            ninFiles: { front: null, back: null }
        };
        
        // Redirect to dashboard (or verification page if needed)
        window.location.href = 'dashboard.php';
    } catch (error) {
        showError(error.message);
    } finally {
        toggleLoading(button, false);
    }
});

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = e.target.querySelector('button[type="submit"]');
    toggleLoading(button, true);
    
    try {
        const data = {
            action: 'login',
            csrf_token: document.querySelector('#loginForm input[name="csrf_token"]').value,
            email: DOMPurify.sanitize(document.getElementById('loginEmail').value.trim()),
            password: document.getElementById('loginPassword').value
        };
        
        const result = await makeApiCall('api/auth.php', 'POST', data);
        showSuccess(result.message || 'Login successful');
        window.location.href = 'dashboard.php';
    } catch (error) {
        showError(error.message);
    } finally {
        toggleLoading(button, false);
    }
});

// BVN input validation
document.getElementById('bvnNumber').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').substring(0, 11);
    checkVerificationCompletion();
});

// Terms checkbox validation
document.getElementById('termsCheckbox').addEventListener('change', function() {
    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) {
        submitBtn.disabled = !this.checked;
    }
});

// Check session on page load
document.addEventListener('DOMContentLoaded', async () => {
    // Load DOMPurify if not already loaded
    if (typeof DOMPurify === 'undefined') {
        const purifyScript = document.createElement('script');
        purifyScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js';
        purifyScript.onload = initializeApp;
        document.head.appendChild(purifyScript);
    } else {
        initializeApp();
    }
});

async function initializeApp() {
    try {
        const result = await makeApiCall('api/auth.php?action=get_session', 'GET');
        console.log('Session check result:', result);
        
        if (result.user && result.user.email) {
            window.location.href = 'dashboard.php';
        } else {
            showRegister();
        }
    } catch (error) {
        console.error('Session check failed:', error);
        showError('Unable to check session. Please refresh the page.');
        showRegister();
    }
}

// Proper error handling in form submit handlers should look like this:
try {
    // Your form submission code here
} catch (error) {
    if (error.message.includes('409')) {
        showError('This email is already registered. Please use a different email or login.');
    } else if (error.message.includes('403')) {
        showError('Session expired. Please refresh the page and try again.');
    } else {
        showError(error.message);
    }
}