<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Basic form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['message']));
    
    // Placeholder for form processing (e.g., send email or store in database)
    // Replace with your actual backend logic (e.g., mail() or database insert)
    $form_response = "Thank you, $name! Your message has been received. We'll get back to you soon.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - Ubiaza</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .contact-form-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            color: #333;
        }
        .contact-form textarea {
            resize: vertical;
            min-height: 150px;
        }
        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .form-response {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 1rem;
            text-align: center;
        }
        .faq-item {
            background: #f8f9ff;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .faq-item:hover {
            background: var(--primary-light);
        }
        .faq-question {
            font-weight: 600;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .faq-answer {
            color: #666;
            margin-top: 0.5rem;
            display: none;
        }
        .faq-item.active .faq-answer {
            display: block;
        }
        .faq-icon {
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        .contact-info-card {
            text-align: center;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .contact-info-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo"><img src="assets/images/logo.png" alt="Ubiaza Logo">Ubiaza</a>
            <nav class="nav-menu">
                <a href="index.php#home">Home</a>
                <a href="about.php">About Us</a>
                <a href="blog.php">Blog</a>
                <a href="support.php" class="active">Support</a>
                <a href="sign.php">Login</a>
            </nav>
            <div class="nav-actions">
                <a href="sign.php#register">
                    <button class="open-account-btn">Open Account</button>
                </a>
            </div>
        </div>
    </header>

    <section class="hero" id="support">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">Ubiaza Support</div>
                <h1 class="hero-title">We're Here to Help You</h1>
                <p class="hero-subtitle">Get quick and reliable support for all your banking and savings needs with Ubiaza. Contact us or explore our FAQs for instant answers.</p>
            </div>
            <div class="hero-image">
                <img src="assets/images/img2.png" alt="Ubiaza Support">
            </div>
        </div>
    </section>

    <section class="contact-form">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Get in Touch</p>
                <h2 class="section-title">Send Us a Message</h2>
                <p class="section-description">Have a question or need assistance? Fill out the form below, and our team will respond promptly.</p>
            </div>
            <div class="contact-form-container">
                <?php if (isset($form_response)): ?>
                    <p class="form-response"><?php echo $form_response; ?></p>
                <?php endif; ?>
                <form method="POST" action="support.php">
                    <input type="text" name="name" placeholder="Your Name" required>
                    <input type="email" name="email" placeholder="Your Email" required>
                    <input type="text" name="subject" placeholder="Subject" required>
                    <textarea name="message" placeholder="Your Message" required></textarea>
                    <button type="submit" class="btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <section class="faqs">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Frequently Asked Questions</p>
                <h2 class="section-title">Find Answers Fast</h2>
                <p class="section-description">Browse our FAQs to get quick answers to common questions about Ubiaza’s services.</p>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">How do I open an account with Ubiaza? <i class="fas fa-chevron-down faq-icon"></i></div>
                    <div class="faq-answer">Click the "Open Account" button on our website, fill in your details, and follow the verification steps. It takes just a few minutes!</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">What are the requirements for a loan? <i class="fas fa-chevron-down faq-icon"></i></div>
                    <div class="faq-answer">You need a valid ID, proof of income, and an active Ubiaza account. Specific requirements depend on the loan type.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">Is my money safe with Ubiaza? <i class="fas fa-chevron-down faq-icon"></i></div>
                    <div class="faq-answer">Yes, we use advanced encryption and security protocols to protect your transactions and personal information.</div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">How can I contact support? <i class="fas fa-chevron-down faq-icon"></i></div>
                    <div class="faq-answer">Reach us via the form above, email at support@ubiaza.io, or call +234 123 456 7890. We’re available 24/7.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-info">
        <div class="features-container">
            <div class="features-header">
                <p class="features-title">Contact Information</p>
                <h2 class="section-title">Other Ways to Reach Us</h2>
                <p class="section-description">Connect with us through multiple channels for quick and personalized assistance.</p>
            </div>
            <div class="contact-info-grid">
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
                    <h3 class="feature-title">Email Support</h3>
                    <p class="feature-description">Send us an email at <a href="mailto:support@ubiaza.io">support@ubiaza.io</a> for detailed inquiries.</p>
                </div>
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-phone"></i></div>
                    <h3 class="feature-title">Phone Support</h3>
                    <p class="feature-description">Call us at +234 123 456 7890 for immediate assistance, available 24/7.</p>
                </div>
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-comment-dots"></i></div>
                    <h3 class="feature-title">Live Chat</h3>
                    <p class="feature-description">Start a live chat on our website or mobile app for real-time support.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="final-cta">
        <div class="final-cta-container">
            <h2>Need More Help?</h2>
            <p>Join Ubiaza and get access to our dedicated support team anytime, anywhere.</p>
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
                    <li><a href="support.php">Support</a></li>
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
    <script>
        document.querySelectorAll('.faq-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('active');
            });
        });
    </script>
</body>
</html>