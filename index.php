<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Mhavis Medical & Diagnostic Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="shortcut icon" href="img/logo2.jpeg" type="image/x-icon" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('img/bg2.jpg') no-repeat center center fixed;
            background-size: cover;
            filter: blur(3px);
            pointer-events: none;
            z-index: 0;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(8px) saturate(180%);
            box-shadow: 0 4px 30px rgba(18, 24, 165, 0.15);
            padding: 1.2rem 0;
            border-bottom: 2px solid rgba(18, 24, 165, 0.25);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1000;
        }

        .navbar-toggler {
            border: 2px solid rgba(18, 24, 165, 0.3);
            border-radius: 8px;
            padding: 6px 12px;
            transition: all 0.3s ease;
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(18, 24, 165, 0.25);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(18, 24, 165, 0.85)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .navbar-collapse {
            margin-top: 15px;
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.98);
                border-radius: 12px;
                padding: 15px;
                margin-top: 15px;
                box-shadow: 0 4px 15px rgba(18, 24, 165, 0.15);
            }

            .navbar-nav {
                align-items: flex-start;
            }

            .nav-item {
                width: 100%;
            }

            .dropdown-menu {
                position: static !important;
                transform: none !important;
                width: 100%;
                margin-top: 10px;
                box-shadow: none;
                border: 1px solid rgba(18, 24, 165, 0.1);
            }
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            background: linear-gradient(135deg, #1218a5 0%, #0D92F4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .navbar-brand img {
            border-radius: 12px;
            height: 45px;
            width: 45px;
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(18, 24, 165, 0.3);
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: rotate(5deg) scale(1.1);
        }

        .dropdown-toggle {
            background: linear-gradient(135deg, #1218a5 0%, #0D92F4 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(18, 24, 165, 0.4);
        }

        .dropdown-toggle:hover {
            background: linear-gradient(135deg, #0d1180 0%, #0b6bc0 100%);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(18, 24, 165, 0.5);
        }

        .dropdown-toggle:focus {
            box-shadow: 0 0 0 0.25rem rgba(18, 24, 165, 0.3);
        }

        .dropdown-toggle::after {
            margin-left: 8px;
            vertical-align: 0.15em;
        }

        .nav-link.dropdown-toggle {
            color: white !important;
        }

        .nav-link.dropdown-toggle:hover {
            color: white !important;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeInDown 0.3s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu {
            border: 1px solid rgba(18, 66, 181, 0.15);
            box-shadow: 0 10px 40px rgba(18, 66, 181, 0.2);
            border-radius: 20px;
            padding: 12px;
            margin-top: 10px;
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(5px) !important;
            min-width: 280px;
            z-index: 9999 !important;
            position: absolute !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .dropdown-item {
            border-radius: 12px;
            padding: 14px 18px;
            margin: 6px 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            background: transparent;
            color: rgba(18, 66, 181, 0.9) !important;
            text-decoration: none;
        }

        .dropdown-item:hover {
            background: rgba(18, 66, 181, 0.15) !important;
            color: #1242B5 !important;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(18, 66, 181, 0.25);
        }

        .dropdown-item:focus {
            background: rgba(18, 66, 181, 0.15) !important;
            color: #1242B5 !important;
        }

        .dropdown-item strong {
            color: inherit;
            display: block;
            font-size: 1rem;
            margin-bottom: 4px;
            filter: none !important;
        }

        .dropdown-item small {
            color: rgba(18, 66, 181, 0.7) !important;
            font-size: 0.85rem;
            filter: none !important;
        }

        .dropdown-item:hover small {
            color: rgba(18, 66, 181, 0.95) !important;
        }

        .dropdown-item i {
            font-size: 1.3rem;
            width: 28px;
            color: #1242B5;
            flex-shrink: 0;
            filter: none !important;
        }

        .dropdown-item div {
            filter: none !important;
        }

        .dropdown-menu * {
            filter: none !important;
            backdrop-filter: none !important;
        }

        .dropdown-divider {
            margin: 8px 0;
            border-top: 1px solid rgba(18, 66, 181, 0.25);
            opacity: 1;
        }

        .hero-section {
            padding: 80px 0 60px;
            color: white;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
        }

        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 3px 3px 10px rgba(0, 0, 0, 0.4);
            animation: fadeInUp 0.8s ease-out;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .hero-section p {
            font-size: 1.2rem;
            margin-bottom: 0;
            opacity: 0.95;
            animation: fadeInUp 1s ease-out;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            font-weight: 300;
        }

        .video-section {
            padding: 60px 0;
            position: relative;
            z-index: 1;
        }

        .video-container {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(18, 66, 181, 0.5);
            background: rgba(18, 66, 181, 0.2);
            animation: fadeInUp 1.2s ease-out;
            aspect-ratio: 16 / 9;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .video-container::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background:  #1a5cd4;
            border-radius: 25px;
            z-index: -1;
            opacity: 0.7;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            display: block;
            border-radius: 22px;
            object-fit: contain;
        }

        .services-section {
            background: linear-gradient(135deg, #0a0e3d 0%, #1218a5 50%, #0d4a6b 100%);
            color: white;
            position: relative;
            z-index: 2;
            padding: 60px 0 30px;
            margin-top: 40px;
        }

        .services-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0D92F4, #28a745, #0D92F4);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 40px;
        }

        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .footer-brand-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .footer-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .footer-brand h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin: 0;
        }

        .footer-tagline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            width: fit-content;
            margin-bottom: 15px;
        }

        .footer-tagline i {
            font-size: 1.1rem;
        }

        .footer-hours {
            background: rgba(255, 255, 255, 0.1);
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 20px;
            border-left: 3px solid #0D92F4;
        }

        .footer-mission {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.7;
            font-size: 0.95rem;
            text-align: justify;
        }

        .footer-section {
            display: flex;
            flex-direction: column;
        }

        .footer-section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-section-title i {
            color: #0D92F4;
        }

        .services-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .service-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .service-item:hover {
            color: white;
            transform: translateX(5px);
        }

        .service-item i {
            color: #28a745;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .service-item span {
            font-weight: 500;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            color: white;
        }

        .contact-item i {
            color: #0D92F4;
            font-size: 1.2rem;
            width: 24px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .contact-item span {
            line-height: 1.6;
        }

        .contact-item a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .contact-item a:hover {
            color: #0D92F4;
            text-decoration: underline;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 25px;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }


        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }


        /* Extra Small Devices (phones, 320px and up) */
        @media (max-width: 575.98px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }

            .navbar {
                padding: 0.8rem 0;
            }

            .navbar-brand {
                font-size: 0.85rem;
                gap: 8px;
                flex-wrap: wrap;
            }

            .navbar-brand img {
                height: 35px;
                width: 35px;
            }

            .navbar .container {
                padding-left: 15px;
                padding-right: 15px;
            }

            .dropdown-toggle {
                padding: 8px 16px;
                font-size: 0.9rem;
            }

            .dropdown-menu {
                min-width: 100%;
                margin-top: 8px;
                border-radius: 15px;
            }

            .dropdown-item {
                padding: 12px 14px;
                font-size: 0.9rem;
            }

            .dropdown-item i {
                font-size: 1.1rem;
                width: 24px;
            }

            .hero-section {
                padding: 40px 15px 30px;
            }

            .hero-section h1 {
                font-size: 1.8rem;
                margin-bottom: 0.8rem;
                line-height: 1.3;
            }

            .hero-section p {
                font-size: 0.9rem;
                padding: 0 10px;
            }

            .video-section {
                padding: 30px 15px;
            }

            .video-container {
                border-radius: 15px;
                max-width: 100%;
            }

            .services-section {
                padding: 40px 15px 20px;
                margin-top: 30px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .footer-brand-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .footer-logo {
                width: 60px;
                height: 60px;
            }

            .footer-brand h2 {
                font-size: 1.3rem;
            }

            .footer-tagline {
                font-size: 0.85rem;
                padding: 6px 16px;
                margin: 0 auto 12px;
            }

            .footer-hours {
                font-size: 0.85rem;
                padding: 10px 14px;
            }

            .footer-mission {
                font-size: 0.9rem;
                text-align: left;
            }

            .footer-section-title {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }

            .service-item {
                font-size: 0.9rem;
                gap: 10px;
            }

            .service-item i {
                font-size: 1.1rem;
            }

            .contact-item {
                font-size: 0.85rem;
                gap: 10px;
            }

            .contact-item i {
                font-size: 1.1rem;
                width: 20px;
            }

            .footer-bottom {
                font-size: 0.85rem;
                padding-top: 20px;
            }
        }

        /* Small Devices (landscape phones, 576px and up) */
        @media (min-width: 576px) and (max-width: 767.98px) {
            .navbar-brand {
                font-size: 1.1rem;
            }

            .navbar-brand img {
                height: 40px;
                width: 40px;
            }

            .hero-section {
                padding: 50px 0 35px;
            }

            .hero-section h1 {
                font-size: 2rem;
            }

            .hero-section p {
                font-size: 1rem;
            }

            .video-section {
                padding: 35px 0;
            }

            .video-container {
                border-radius: 18px;
            }

            .services-section {
                padding: 50px 0 25px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .footer-brand-header {
                flex-direction: row;
                justify-content: center;
            }

            .footer-brand h2 {
                font-size: 1.4rem;
            }
        }

        /* Medium Devices (tablets, 768px and up) */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .hero-section {
                padding: 70px 0 50px;
            }

            .hero-section h1 {
                font-size: 2.8rem;
            }

            .hero-section p {
                font-size: 1.1rem;
            }

            .video-section {
                padding: 50px 0;
            }

            .video-container {
                max-width: 90%;
            }

            .services-section {
                padding: 55px 0 30px;
            }

            .footer-content {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }

            .footer-brand {
                grid-column: 1 / -1;
            }
        }

        /* Large Devices (desktops, 992px and up) - Default styles apply */
        @media (min-width: 992px) {
            .container {
                max-width: 1140px;
            }
        }

        /* Extra Large Devices (large desktops, 1200px and up) */
        @media (min-width: 1200px) {
            .container {
                max-width: 1320px;
            }
        }

        /* Very Small Devices (phones, less than 360px) */
        @media (max-width: 359.98px) {
            .navbar-brand {
                font-size: 0.75rem;
            }

            .navbar-brand img {
                height: 30px;
                width: 30px;
            }

            .hero-section h1 {
                font-size: 1.5rem;
            }

            .hero-section p {
                font-size: 0.8rem;
            }

            .dropdown-toggle {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
        }

        /* Landscape orientation adjustments for phones */
        @media (max-width: 767.98px) and (orientation: landscape) {
            .hero-section {
                padding: 30px 0 20px;
            }

            .hero-section h1 {
                font-size: 1.6rem;
            }

            .hero-section p {
                font-size: 0.85rem;
            }

            .video-section {
                padding: 25px 0;
            }
        }

        /* Touch-friendly improvements for mobile */
        @media (max-width: 991.98px) {
            .dropdown-item,
            .nav-link {
                min-height: 44px;
                display: flex;
                align-items: center;
            }

            .dropdown-toggle {
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="img/logo2.jpeg" alt="Mhavis Logo">
                Welcome to Mhavis Medical & Diagnostic Center 
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="loginDropdown">
                            <li>
                                <a class="dropdown-item" href="login.php">
                                    <i class="bi bi-shield-check"></i>
                                    <div>
                                        <strong>Login as Admin</strong>
                                        <small class="d-block text-muted">Administrator access</small>
                                    </div>
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="login.php">
                                    <i class="bi bi-person-badge"></i>
                                    <div>
                                        <strong>Login as Doctor</strong>
                                        <small class="d-block text-muted">Medical professional access</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="patient_login.php">
                                    <i class="bi bi-person-heart"></i>
                                    <div>
                                        <strong>Login as Patient</strong>
                                        <small class="d-block text-muted">Patient portal access</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Our Services</h1>
            <p>Discover the comprehensive healthcare services we offer at Mhavis Medical & Diagnostic Center.</p>
        </div>
    </section>

    <!-- Video Section -->
    <section class="video-section">
        <div class="container">
            <div class="video-container">
                <video controls autoplay muted loop playsinline>
                    <source src="img/mhavisServices.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="services-section">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-brand-header">
                        <img src="img/logo2.jpeg" alt="Mhavis Logo" class="footer-logo">
                        <h2>MHAVIS MEDICAL CENTER</h2>
                    </div>
                    <div class="footer-tagline">
                        <i class="bi bi-heart-fill"></i>
                        <span>Dahil BUHAY ay Mas Mahalaga</span>
                    </div>
                    <div class="footer-hours">
                        MONDAY to SUNDAY 7am until 12 midnight
                    </div>
                    <div class="footer-mission">
                        At Mhavis Medical Center, we are committed to providing compassionate care to each and every patient. Our team of dedicated healthcare professionals go above and beyond to ensure that you receive best possible care for your health needs.
                    </div>
                </div>

                <div class="footer-section">
                    <div class="footer-section-title">
                        <i class="bi bi-list-check"></i>
                        <span>Services We Offer</span>
                    </div>
                    <div class="services-list">
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>CARDIOLOGY</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>ENT</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>INTERNAL MEDICINE</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>OBGYN</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>ORTHO</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>PEDIA</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>PSYCHIATRY</span>
                        </div>
                        <div class="service-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>SURGERY</span>
                        </div>
                    </div>
                </div>

                <div class="footer-section">
                    <div class="footer-section-title">
                        <i class="bi bi-info-circle"></i>
                        <span>Contact Information</span>
                    </div>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="bi bi-facebook"></i>
                            <span>Facebook Page - <a href="https://www.facebook.com/mhaviscenter" target="_blank" rel="noopener noreferrer">https://www.facebook.com/mhaviscenter</a></span>
                        </div>
                        <div class="contact-item">
                            <i class="bi bi-geo-alt"></i>
                            <span>De Ocampo St. Poblacion 3, Indang, Philippines</span>
                        </div>
                        <div class="contact-item">
                            <i class="bi bi-telephone"></i>
                            <span>0908 981 4957</span>
                        </div>
                        <div class="contact-item">
                            <i class="bi bi-envelope"></i>
                            <span>mhavismedicalcenter@gmail.com</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Mhavis Medical Center. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

