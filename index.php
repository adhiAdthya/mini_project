<?php
session_start();
require_once __DIR__ . '/db_connect.php'; // Database connection
?>
<!DOCTYPE html>
<html lang="en">
<head> 
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AutoCare Garage</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
:root { --primary: #2c3e50; --secondary: #e5b840; --accent: #3498db; --dark: #2c3e50; --gray: #7f8c8d; }
body { background-color: #f5f7fa; color: #333; line-height: 1.6; display: flex; flex-direction: column; min-height: 100vh; }
.container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 15px; }


/* Header */
header { background-color: var(--primary); color: white; padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.header-content { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.header-title { font-size: 24px; font-weight: 700; }
.btn { padding: 10px 20px; border-radius: 30px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s ease; }
.btn-login { background-color: var(--secondary); color: var(--dark); }
.btn-signup { background-color: var(--accent); color: white; }
.btn:hover { opacity: 0.9; transform: translateY(-2px); }


/* Navigation */
nav { background-color: var(--dark); padding: 10px 0; }
.nav-menu { display: flex; list-style: none; justify-content: center; }
.nav-menu li { margin: 0 15px; }
.nav-menu a { color: white; text-decoration: none; font-weight: 500; padding: 10px 15px; border-radius: 4px; transition: all 0.3s ease; }
.nav-menu a:hover, .nav-menu a.active { background-color: var(--secondary); color: var(--dark); }


/* Pages */
main { flex: 1; padding: 40px 0; }
.page { display: none; }
.page.active { display: block; }


/* Hero */
.hero { 
    background: linear-gradient(rgba(44,62,80,0.8), rgba(44,62,80,0.8)), 
                url('https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1200&q=80'); 
    background-size: cover; background-position: center; color: white; text-align: center; 
    padding: 80px 20px; margin-bottom: 40px; border-radius: 8px; 
}
.hero h2 { font-size: 36px; margin-bottom: 20px; }
.hero p { font-size: 18px; max-width: 700px; margin: 0 auto; }


/* Services */
.services { padding: 60px 0; }
.section-title { text-align: center; margin-bottom: 40px; color: var(--dark); position: relative; }
.section-title:after { content: ''; display: block; width: 80px; height: 4px; background: var(--secondary); margin: 15px auto; }
.services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
.service-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transition: transform 0.3s ease; display: flex; flex-direction: column; }
.service-card:hover { transform: translateY(-10px); }
.service-img { height: 200px; overflow: hidden; }
.service-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
.service-card:hover .service-img img { transform: scale(1.1); }
.service-content { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
.service-content h3 { color: var(--dark); margin-bottom: 15px; font-size: 20px; }
.service-content p { color: var(--gray); margin-bottom: 15px; }


/* About */
.about-section { padding: 40px 0; text-align: center; }
.about-section h2 { font-size: 32px; margin-bottom: 20px; }
.about-section p { font-size: 18px; max-width: 800px; margin: 0 auto; color: var(--dark); }


/* Footer */
footer { background-color: var(--dark); color: white; padding: 60px 0 20px; margin-top: auto; }
footer a { color: var(--secondary); text-decoration: none; }
footer a:hover { text-decoration: underline; }
footer .footer-stack { text-align:center; margin-bottom:20px; }
footer .copyright { text-align:center; border-top:1px solid rgba(255,255,255,0.2); padding-top:15px; }


/* Responsive */
@media (max-width:768px) {
    .nav-menu { flex-direction: column; align-items: center; }
    .nav-menu li { margin: 5px 0; }
    .hero h2 { font-size: 28px; }
    .hero p { font-size: 16px; }
}
</style>
</head>
<body>


<!-- Header -->
<header>
    <div class="container">
        <div class="header-content">
            <div class="header-title">AutoCare Garage</div>
            <div>
                <?php if (!empty($_SESSION['user'])): ?>
                    <span style="color:#fff;margin-right:10px;">Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
                    <?php if (($_SESSION['user']['role'] ?? '') === 'customer'): ?>
                        <button class="btn btn-login" onclick="window.location.href='customer/dashboard.php'">
                            <i class="fas fa-gauge"></i> Dashboard
                        </button>
                    <?php elseif (($_SESSION['user']['role'] ?? '') === 'supervisor'): ?>
                        <button class="btn btn-login" onclick="window.location.href='supervisor/dashboard.php'">
                            <i class="fas fa-gauge"></i> Supervisor
                        </button>
                    <?php elseif (($_SESSION['user']['role'] ?? '') === 'mechanic'): ?>
                        <button class="btn btn-login" onclick="window.location.href='mechanic/dashboard.php'">
                            <i class="fas fa-gauge"></i> Mechanic
                        </button>
                    <?php elseif (($_SESSION['user']['role'] ?? '') === 'manager'): ?>
                        <button class="btn btn-login" onclick="window.location.href='manager/dashboard.php'">
                            <i class="fas fa-gauge"></i> Manager
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-signup" onclick="window.location.href='logout.php'">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                <?php else: ?>
                    <!-- Login button links to login.php -->
                    <button class="btn btn-login" onclick="window.location.href='login.php'">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <!-- Sign Up button links to signup.php -->
                    <button class="btn btn-signup" onclick="window.location.href='signup.php'">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>


<!-- Navigation -->
<nav>
    <div class="container">
        <ul class="nav-menu">
            <li><a href="#" class="active" onclick="showPage('home-page')">Home</a></li>
            <li><a href="#" onclick="showPage('about-page')">About</a></li>
            <li><a href="#" onclick="showPage('services-page')">Services</a></li>
            <li><a href="#" onclick="showPage('contact-page')">Contact</a></li>
        </ul>
    </div>
</nav>


<!-- Main Content -->
<main>
    <div class="container">
        <!-- Home Page -->
        <div id="home-page" class="page active">
            <section class="hero">
                <h2>Professional Auto Repair & Maintenance</h2>
                <p>We provide top-quality automotive services with experienced mechanics and state-of-the-art equipment to keep your vehicle running smoothly.</p>
            </section>
        </div>


        <!-- About Page -->
        <div id="about-page" class="page">
            <section class="about-section">
                <h2>About Us</h2>
                <p>We are dedicated to providing reliable and affordable auto repair services to keep your car running like new.</p>
            </section>
        </div>


        <!-- Services Page -->
        <div id="services-page" class="page">
            <section class="services">
                <h2 class="section-title">Our Services</h2>
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-img"><img src="images/Wheel-alignment-machine-mobile.jpg" alt="Wheel Alignment"></div>
                        <div class="service-content"><h3>Wheel Alignment</h3><p>Ensure your vehicle drives straight and true with our precision wheel alignment service.</p></div>
                    </div>
                    <div class="service-card">
                        <div class="service-img"><img src="images/a3.jpg" alt="Engine Tune Up"></div>
                        <div class="service-content"><h3>Engine Tune Up</h3><p>Regular engine tune-ups bring power and efficiency back to your car.</p></div>
                    </div>
                    <div class="service-card">
                        <div class="service-img"><img src="images/brake.jpeg" alt="Brake Repair"></div>
                        <div class="service-content"><h3>Brake Repair</h3><p>Your safety is our priority. We provide comprehensive brake inspections and repairs.</p></div>
                    </div>
                    <div class="service-card">
                        <div class="service-img"><img src="images/oil.jpeg" alt="Oil Change"></div>
                        <div class="service-content"><h3>Oil Change</h3><p>Keep your engine running smoothly with our quick and efficient oil change service.</p></div>
                    </div>
                    <div class="service-card">
                        <div class="service-img"><img src="images/images.jpeg" alt="Tire Replacement"></div>
                        <div class="service-content"><h3>Tire Replacement</h3><p>High-quality tire replacement to ensure your safety and comfort on the road.</p></div>
                    </div>
                    <div class="service-card">
                        <div class="service-img"><img src="images/battery.jpeg" alt="Battery Service"></div>
                        <div class="service-content"><h3>Battery Service</h3><p>Reliable battery inspection, replacement, and maintenance for your vehicle.</p></div>
                    </div>
                </div>
            </section>
        </div>


        <!-- Contact Page -->
        <div id="contact-page" class="page">
            <section class="about-section">
                <h2>Contact Us</h2>
                <p>Email: <a href="mailto:info@autocare.com">info@autocare.com</a></p>
                <p>Phone: <a href="tel:+919876543210">+91 9876543210</a></p>
            </section>
        </div>
    </div>
</main>


<!-- Footer -->
<footer>
    <div class="container">
        <div class="footer-stack">
            <h3>About Us</h3>
            <p>We are dedicated to providing reliable and affordable auto repair services to keep your car running like new.</p>
            <h3>Contact Us</h3>
            <p>Email: <a href="mailto:info@autocare.com">info@autocare.com</a></p>
            <p>Phone: <a href="tel:+919876543210">+91 9876543210</a></p>
        </div>
        <div class="copyright">
            <p>&copy; <?php echo date("Y"); ?> AutoCare Garage. All rights reserved.</p>
            <p>
                Quick Links: 
                <a href="#" onclick="showPage('home-page')">Home</a> | 
                <a href="#" onclick="showPage('about-page')">About</a> | 
                <a href="#" onclick="showPage('services-page')">Services</a>
            </p>
        </div>
    </div>
</footer>


<script>
function showPage(pageId) {
    document.querySelectorAll('.page').forEach(page => page.classList.remove('active'));
    const page = document.getElementById(pageId);
    if(page) page.classList.add('active');


    // Highlight active nav link
    document.querySelectorAll('.nav-menu a').forEach(link => link.classList.remove('active'));
    document.querySelector(`.nav-menu a[onclick*="${pageId}"]` ).classList.add('active');
}
</script>


</body>
</html>
