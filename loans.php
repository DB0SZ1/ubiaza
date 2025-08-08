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
    <meta name="description" content="Ubiaza offers personal and business loans in Nigeria with low rates, fast approval, and flexible terms. Apply today!">
    <meta name="keywords" content="personal loans Nigeria, business loans Ubiaza, low interest loans, fast loan approval">
    <title>Loans - Ubiaza</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>
            <a href="https://x.com" class="x-logo"><i class="fa-brands fa-x-twitter"></i></a>
            <button class="hamburger">☰</button>
            <nav class="nav-menu">
                <a href="index.php#home">Home</a>
                <a href="about.php">About Us</a>
                <a href="loans.php" class="active">Loans</a>
                <a href="blog.php">Blog</a>
                <a href="support.php">Support</a>
                <a href="sign.php">Login</a>
            </nav>
            <div class="nav-actions">
                <a href="sign.php#register">
                    <button class="open-account-btn">Open Account</button>
                </a>
            </div>
        </div>
    </header>

    <div class="account-type-container">
        <a href="index.php" class="account-type-btn">Personal</a>
        <a href="business.php" class="account-type-btn">Business</a>
    </div>

    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">Fast. Flexible. Affordable</div>
                <h1 class="hero-title">Get the Funds You Need Today</h1>
                <p class="hero-subtitle">Ubiaza offers personal and business loans tailored to your goals, with low rates and quick approval processes.</p>
                <div class="hero-actions">
                    <a href="sign.php#register"><button class="btn-primary">Apply for a Personal Loan</button></a>
                    <a href="sign.php#register"><button class="btn-secondary">Apply for a Business Loan</button></a>
                </div>
                <div class="trust-badge">Trusted by 2M+ Nigerians for their financial needs</div>
            </div>
            <div class="hero-image">
                <img src="assets/images/img10.jfif" alt="Ubiaza Loans">
            </div>
        </div>
    </section>

    <section class="personal-loans">
        <div class="loans-container">
            <div class="section-badge">Personal Loans</div>
            <h2 class="section-title">Loans for Your Personal Dreams</h2>
            <p class="section-description">Whether it’s a new home, a car, or education, Ubiaza’s personal loans offer competitive rates and flexible repayment plans to help you achieve your goals.</p>
            <div class="loans-grid">
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-home"></i></div>
                    <h3 class="loan-title">Home Loans</h3>
                    <p class="loan-description">Buy your dream home with low interest rates and easy repayment options.</p>
                    <ul class="loan-features">
                        <li>Up to ₦50M financing</li>
                        <li>Flexible tenures up to 20 years</li>
                    </ul>
                    <a href="sign.php#register"><button class="loan-btn primary">Apply Now</button></a>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-car"></i></div>
                    <h3 class="loan-title">Car Loans</h3>
                    <p class="loan-description">Drive your dream car with fast approval and affordable monthly payments.</p>
                    <ul class="loan-features">
                        <li>Up to ₦10M loans</li>
                        <li>Repayment up to 5 years</li>
                    </ul>
                    <a href="sign.php#register"><button class="loan-btn primary">Apply Now</button></a>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3 class="loan-title">Education Loans</h3>
                    <p class="loan-description">Invest in your future with loans for school fees and study abroad.</p>
                    <ul class="loan-features">
                        <li>Up to ₦5M financing</li>
                        <li>Grace period during studies</li>
                    </ul>
                    <a href="sign.php#register"><button class="loan-btn primary">Apply Now</button></a>
                </div>
            </div>
            <div class="loans-image">
                <img src="assets/images/ij.png" alt="Personal Loans">
            </div>
        </div>
    </section>

    <section class="business-loans">
        <div class="loans-container">
            <div class="section-badge">Business Loans</div>
            <h2 class="section-title">Grow Your Business with Confidence</h2>
            <p class="section-description">Ubiaza’s business loans provide the capital you need to expand, purchase equipment, or manage cash flow, with terms designed for Nigerian businesses.</p>
            <div class="loans-grid">
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-briefcase"></i></div>
                    <h3 class="loan-title">Working Capital Loans</h3>
                    <p class="loan-description">Boost your operations with funds for inventory, payroll, or daily expenses.</p>
                    <ul class="loan-features">
                        <li>Up to ₦20M financing</li>
                        <li>Short-term repayment options</li>
                    </ul>
                    <a href="sign.php#register"><button class="loan-btn primary">Apply Now</button></a>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-tools"></i></div>
                    <h3 class="loan-title">Equipment Financing</h3>
                    <p class="loan-description">Purchase machinery or tools to scale your business efficiently.</p>
                    <ul class="loan-features">
                        <li>Up to ₦30M loans</li>
                        <li>Collateralized by equipment</li>
                    </ul>
                    <a href="sign.php#register"><button class="loan-btn primary">Apply Now</button></a>
                </div>
                <div class="loan-card">
                    <div class="loan-icon"><i class="fas fa-store"></i></div>
                    <h3 class="loan-title">SME Expansion Loans</h3>
                    <p class="loan-description">Open new locations or launch products with tailored financing.</p>
                    <ul class="loan-features">
                        <li>Up to ₦50M financing</li>
                        <li>Flexible repayment up to 3 years</li>
                    </ul>
                    <a href="sign.php#register"><button class="loan-btn primary">Apply Now</button></a>
                </div>
            </div>
            <div class="loans-image">
                <img src="assets/images/loan-business.png" alt="Business Loans">
            </div>
        </div>
    </section>

    <section class="features">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Why Choose Ubiaza Loans?</p>
                <h2 class="section-title">Benefits That Make a Difference</h2>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-percentage"></i></div>
                    <h3 class="feature-title">Low Interest Rates</h3>
                    <p class="feature-description">Competitive rates to keep your repayments affordable.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-clock"></i></div>
                    <h3 class="feature-title">Fast Approval</h3>
                    <p class="feature-description">Get approved in as little as 24 hours.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-handshake"></i></div>
                    <h3 class="feature-title">Flexible Terms</h3>
                    <p class="feature-description">Choose repayment plans that suit your needs.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3 class="feature-title">No Hidden Fees</h3>
                    <p class="feature-description">Transparent pricing with no surprises.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonials">
        <div class="testimonials-container">
            <h2>Testimonials</h2>
            <p class="testimonials-subtitle">Hear from customers who achieved their goals with Ubiaza loans.</p>
            <div class="testimonial-card active">
                <div class="stars">⭐⭐⭐⭐⭐</div>
                <p class="testimonial-text">"Ubiaza’s car loan helped me get my dream car with affordable payments. The process was so fast!"</p>
                <div class="testimonial-author">
                    <div class="author-avatar">T</div>
                    <div class="author-info">
                        <h4>Tolu Adebayo</h4>
                        <p>Teacher</p>
                    </div>
                </div>
            </div>
            <div class="testimonial-nav">
                <button class="nav-btn" onclick="changeTestimonial(-1)">←</button>
                <button class="nav-btn" onclick="changeTestimonial(1)">→</button>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-container">
            <h2>Ready to Fund Your Future?</h2>
            <p>Apply for a loan today and take the next step toward your goals.</p>
            <div class="cta-actions">
                <a href="sign.php#register"><button class="btn-white">Apply Now</button></a>
                <a href="support.php"><button class="btn-outline">Contact Us</button></a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3>Ubiaza</h3>
                <p>Your trusted partner for local banking and savings in Nigeria.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php#home">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="loans.php">Loans</a></li>
                    <li><a href="support.php">Support</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Services</h3>
                <ul class="footer-links">
                    <li><a href="#transfers">Local Transfers</a></li>
                    <li><a href="#savings">Savings</a></li>
                    <li><a href="#loans">Loans</a></li>
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