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
    <title>Blog - Ubiaza</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>
            <nav class="nav-menu">
                <a href="index.php#home">Home</a>
                <a href="about.php">About Us</a>
                <a href="index.php#features">Features</a>
                <a href="blog.php" class="active">Blog</a>
                <a href="sign.php">Login</a>
            </nav>
            <div class="nav-actions">
                <a href="sign.php#register">
                    <button class="open-account-btn">Open Account</button>
                </a>
            </div>
        </div>
    </header>

    <section class="hero" id="blog">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">Ubiaza Blog</div>
                <h1 class="hero-title">Insights for Your Financial Journey</h1>
                <p class="hero-subtitle">Stay informed with tips, updates, and strategies to make the most of your banking and savings with Ubiaza.</p>
            </div>
            <div class="hero-image">
                <img src="assets/images/img1.png" alt="Ubiaza Blog">
            </div>
        </div>
    </section>

    <section class="blog-posts">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Latest Posts</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="blog-image">
                        <img src="assets/images/img2.png" alt="Blog Post 1">
                    </div>
                    <h3 class="feature-title">5 Tips to Maximize Your Savings with Ubiaza</h3>
                    <p class="feature-description">Learn how to grow your money faster with our expert savings strategies tailored for Nigerians.</p>
                    <a href="blog-post.php?id=1" class="service-btn">Read More <span>→</span></a>
                </div>
                <div class="feature-card">
                    <div class="blog-image">
                        <img src="assets/images/img3.png" alt="Blog Post 2">
                    </div>
                    <h3 class="feature-title">How to Use Local Transfers Effectively</h3>
                    <p class="feature-description">Discover the best ways to send money instantly across Nigeria with Ubiaza’s secure platform.</p>
                    <a href="blog-post.php?id=2" class="service-btn">Read More <span>→</span></a>
                </div>
                <div class="feature-card">
                    <div class="blog-image">
                        <img src="assets/images/img4.png" alt="Blog Post 3">
                    </div>
                    <h3 class="feature-title">Understanding Loans: A Guide for Beginners</h3>
                    <p class="feature-description">Get insights into choosing the right loan for your needs, from home to business financing.</p>
                    <a href="blog-post.php?id=3" class="service-btn">Read More <span>→</span></a>
                </div>
                <div class="feature-card">
                    <div class="blog-image">
                        <img src="assets/images/img2.png" alt="Blog Post 4">
                    </div>
                    <h3 class="feature-title">Why Secure Banking Matters in 2025</h3>
                    <p class="feature-description">Explore how Ubiaza ensures your transactions are safe with top-tier security measures.</p>
                    <a href="blog-post.php?id=4" class="service-btn">Read More <span>→</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="final-cta-container">
            <h2>Ready to Start Your Financial Journey?</h2>
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
                    <li><a href="index.php#home">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="blog.php">Blog</a></li>
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