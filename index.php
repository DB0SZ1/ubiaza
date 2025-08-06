<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubiaza - Local Banking & Savings</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="#" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>
            <nav class="nav-menu">
                <a href="#home">Home</a>
                <a href="about.php">About Us</a>
                <a href="blog.php">Blog</a>
                <a href="sign.php">Login</a>
            </nav>
            <div class="nav-actions">
                <a href="sign.php#register">
                    <button class="open-account-btn">Open Account</button>
                </a>
            </div>
        </div>
    </header>

    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">Simple. Transparent. Secure</div>
                <h1 class="hero-title">Local Banking & Savings</h1>
                <p class="hero-subtitle">Enjoy easy, fast, and reliable banking and savings solutions in Nigeria with Ubiaza.</p>
                <div class="hero-actions">
                    <a href="sign.php#register"><button class="btn-primary">Open Account</button></a>
                    <a href="sign.php"><button class="btn-secondary">Login</button></a>
                </div>
                <div class="trust-badge">Trusted by 2M+ customers across Nigeria</div>
            </div>
            <div class="hero-image">
                <img src="assets/images/img1.png" alt="Local Banking and Savings">
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Our Features</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <h3 class="feature-title">Local Transfers</h3>
                    <p class="feature-description">Send money across Nigeria instantly</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <h3 class="feature-title">Loans</h3>
                    <p class="feature-description">Easy Loans</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-lock"></i></div>
                    <h3 class="feature-title">Secure Banking</h3>
                    <p class="feature-description">Protected transactions</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-university"></i></div>
                    <h3 class="feature-title">Smart Banking</h3>
                    <p class="feature-description">Real-time notifications keep you informed with everything that's happening on your account.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-piggy-bank"></i></div>
                    <h3 class="feature-title">Safe Savings</h3>
                    <p class="feature-description">The better way to save and grow your money with high returns.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
                    <h3 class="feature-title">Local Access</h3>
                    <p class="feature-description">Cards that work across Nigeria with no ATM fees or minimum balance requirements.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="notifications">
        <div class="notifications-container">
            <div class="notifications-content">
                <div class="feature-icon"><i class="fas fa-bell"></i></div>
                <h3>Real-time Notifications</h3>
                <p>Stay informed in real-time with everything happening on your account: payments, transfers, advice. Get visibility on your flows to anticipate your needs.</p>
                <ul class="notifications-features">
                    <li>Cards that work across Nigeria.</li>
                    <li>No ATM fees. No minimum balance. No overdrafts.</li>
                </ul>
            </div>
            <div class="notifications-image">
                <img src="assets/images/img2.png" alt="Customer Support Notifications">
            </div>
        </div>
    </section>

    <section class="investment">
        <div class="investment-container">
            <div class="investment-image">
                <img src="assets/images/img3.png" alt="Savings and Investments">
            </div>
            <div class="investment-content">
                <div class="section-badge">Safe Savings</div>
                <h2 class="section-title">The Better Way to Save & Invest</h2>
                <p class="section-description">Ubiaza helps over 2 million Nigerians achieve their financial goals by helping them save and invest with ease.</p>
                <p class="section-description">Put that extra cash to use without putting it at risk with Ubiaza.</p>
                <div class="investment-features">
                    <div class="investment-feature">
                        <div class="investment-feature-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="investment-feature-content">
                            <h4>Profitable to Save</h4>
                            <p>Easy to manage with our intuitive dashboard and tools</p>
                        </div>
                    </div>
                    <div class="investment-feature">
                        <div class="investment-feature-icon"><i class="fas fa-percentage"></i></div>
                        <div class="investment-feature-content">
                            <h4>Highest Returns</h4>
                            <p>Get the best rates on your savings with Ubiaza</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="banking">
        <div class="banking-container">
            <div class="banking-content">
                <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                <h3>Banking at Your Fingertips</h3>
                <p>Your banking experience anytime, anywhere. Get your money moving with our simple-to-use, accessible mobile app. As good as a bank branch within your phone!</p>
                <ul class="banking-features">
                    <li>Bill Payments, Funds Transfer, Airtime Top-up</li>
                    <li>Savings and Local Transfers</li>
                </ul>
            </div>
            <div class="banking-image">
                <img src="assets/images/img4.png" alt="Mobile Banking Interface">
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-container">
            <h2>Ready to make the leap?</h2>
            <p>Let us help you.</p>
            <div class="cta-actions">
                <a href="sign.php#register"><button class="btn-white">Open Account</button></a>
                <a href="#contact"><button class="btn-outline">Get in touch</button></a>
            </div>
        </div>
    </section>

    <section class="loans">
        <div class="loans-container">
            <div class="loans-header">
                <div class="section-badge">Financial Planning</div>
                <h2>Let's plan your finances the right way</h2>
                <p>Lending that doesn't weigh you down. We know how hard it is to start something new, that's why we have the perfect plan for you.</p>
                <a href="sign.php#register"><button class="apply-loan-btn">Apply for a loan</button></a>
            </div>
            <div class="loans-grid">
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-home"></i></div>
                    <h3 class="loan-title">Home Loans</h3>
                    <p class="loan-description">Get your dream home with our competitive home loan rates.</p>
                    <ul class="loan-features">
                        <li>Lowest interest rates</li>
                        <li>Fast Loan Processing</li>
                    </ul>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-car"></i></div>
                    <h3 class="loan-title">Car Loans</h3>
                    <p class="loan-description">Drive your dream car with our flexible car loan options.</p>
                    <ul class="loan-features">
                        <li>Competitive rates</li>
                        <li>Quick & Easy</li>
                    </ul>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3 class="loan-title">Education Loans</h3>
                    <p class="loan-description">Invest in your future with our education loan plans.</p>
                    <ul class="loan-features">
                        <li>Pay back conveniently</li>
                        <li>Fast Loan Processing</li>
                    </ul>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-briefcase"></i></div>
                    <h3 class="loan-title">Business Loans</h3>
                    <p class="loan-description">Grow your business with our tailored business loan solutions.</p>
                    <ul class="loan-features">
                        <li>Easy Approvals</li>
                        <li>Full Assistance</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonials">
        <div class="testimonials-container">
            <h2>Testimonials</h2>
            <p class="testimonials-subtitle">Don't take our word for it, take theirs. Take a look at our past customers' success stories. Our goal is to help you grow.</p>
            
            <div class="testimonial-card active">
                <div class="stars">⭐⭐⭐⭐⭐</div>
                <p class="testimonial-text">"Ubiaza has transformed how I manage my savings and local payments for my business. The rates are competitive and the service is exceptional."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">C</div>
                    <div class="author-info">
                        <h4>Chinedu Okeke</h4>
                        <p>Small Business Owner</p>
                    </div>
                </div>
            </div>
            <!-- Add more testimonials as needed -->
            <div class="testimonial-nav">
                <button class="nav-btn" onclick="changeTestimonial(-1)">←</button>
                <button class="nav-btn" onclick="changeTestimonial(1)">→</button>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="final-cta-container">
            <h2>Ready to get started?</h2>
            <p>It only takes a few minutes to register your FREE Ubiaza account.</p>
            <a href="sign.php#register"><button class="final-cta-btn">Open an Account</button></a>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3><h3><a href="#" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>Ubiaza</h3>
                <p>Your trusted partner for local banking and savings in Nigeria.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                    <li><a href="support.php">Support</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Services</h3>
                <ul class="footer-links">
                    <li><a href="#transfers">Local Transfers</a></li>
                    <li><a href="#savings">Savings</a></li>
                    <li><a href="#p2p">P2P Transfers</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Email: support@ubiaza.io</p>
                <p>Phone: +234 123 456 7890</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Ubiaza. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/index.js"></script>
</body>
</html>