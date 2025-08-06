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
    <title>About Us - Ubiaza</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>
            <nav class="nav-menu">
                <a href="index.php#home">Home</a>
                <a href="about.php" class="active">About Us</a>
                <a href="index.php#features">Features</a>
                <a href="sign.php">Login</a>
            </nav>
            <div class="nav-actions">
                <a href="sign.php">
                    <button class="open-account-btn">Open Account</button>
                </a>
            </div>
        </div>
    </header>

    <section class="hero" id="about">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">About Ubiaza</div>
                <h1 class="hero-title">Empowering Nigeria's Financial Future</h1>
                <p class="hero-subtitle">Ubiaza is your trusted partner for local banking and savings, delivering simple, transparent, and secure financial solutions to over 2 million Nigerians.</p>
            </div>
            <div class="hero-image">
                <img src="assets/images/img7.jpg" alt="About Ubiaza">
            </div>
        </div>
    </section>

    <section class="about-mission">
        <div class="investment-container">
            <div class="investment-content">
                <div class="section-badge">Our Mission</div>
                <h2 class="section-title">Driving Financial Inclusion</h2>
                <p class="section-description">At Ubiaza, our mission is to provide accessible and reliable financial services that empower individuals and businesses across Nigeria to achieve their financial goals with ease.</p>
            </div>
            <div class="investment-image">
                <img src="assets/images/f1.jfif" alt="Our Mission">
            </div>
        </div>
    </section>

    <section class="about-values">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Our Values</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-lightbulb"></i></div>
                    <h3 class="feature-title">Innovation</h3>
                    <p class="feature-description">We leverage cutting-edge technology to deliver seamless banking experiences.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-handshake"></i></div>
                    <h3 class="feature-title">Integrity</h3>
                    <p class="feature-description">Transparency and trust are at the core of everything we do.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-users"></i></div>
                    <h3 class="feature-title">Customer-Centricity</h3>
                    <p class="feature-description">Our customers’ needs drive our solutions and services.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-star"></i></div>
                    <h3 class="feature-title">Excellence</h3>
                    <p class="feature-description">We strive for the highest standards in every aspect of our operations.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-team">
        <div class="notifications-container">
            <div class="notifications-content">
                <div class="section-badge">Our Team</div>
                <h2 class="section-title">Meet the Team Behind Ubiaza</h2>
                <p class="section-description">Our diverse team of experts in technology, finance, and customer service is passionate about transforming Nigeria’s financial landscape, delivering unparalleled value to our customers.</p>
            </div>
            <div class="notifications-image">
                <img src="assets/images/img6.jfif" alt="Ubiaza Team">
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="final-cta-container">
            <h2>Ready to Join Ubiaza?</h2>
            <p>It only takes a few minutes to register your FREE Ubiaza account.</p>
            <a href="sign.php"><button class="final-cta-btn">Open an Account</button></a>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3><a href="#" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>Ubiaza</h3>
                <p>Your trusted partner for local banking and savings in Nigeria.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php#home">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Services</h3>
                <ul class="footer-links">
                    <li><a href="index.php#transfers">Local Transfers</a></li>
                    <li><a href="index.php#savings">Savings</a></li>
                    <li><a href="index.php#p2p">P2P Transfers</a></li>
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