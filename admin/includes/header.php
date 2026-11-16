<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PANEL REUSABLE HEADER (Section 1. Design & Security)
// =============================================================================

require_once __DIR__ . '/../../config/config.php';
require_admin();

$adminUser = current_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($adminTitle) ? e($adminTitle) . ' — ' : '' ?>VALENOR Admin Concierge</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom Admin Stylesheet -->
    <style>
        :root {
            --caramel-primary: #B8895B;
            --caramel-dark: #8B6342;
            --caramel-light: #D4A373;
            --bg-white: #FFFFFF;
            --bg-cream: #F8F3EB;
            --text-charcoal: #252525;
            --border-beige: #E8DED2;
            --font-serif: 'Cormorant Garamond', Georgia, serif;
            --font-sans: 'Inter', -apple-system, sans-serif;
            --sidebar-width: 260px;
        }

        body {
            font-family: var(--font-sans);
            background-color: var(--bg-cream);
            color: var(--text-charcoal);
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: #1e1b18;
            color: #f0e6dc;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            z-index: 1030;
        }

        .admin-sidebar .sidebar-brand {
            padding: 22px 20px;
            background: #151311;
            border-bottom: 1px solid rgba(232, 222, 210, 0.1);
        }

        .admin-sidebar .nav-link {
            color: #c9bfb5;
            padding: 12px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .admin-sidebar .nav-link:hover {
            color: #ffffff;
            background: rgba(184, 137, 91, 0.15);
            border-left-color: var(--caramel-primary);
        }

        .admin-sidebar .nav-link.active {
            color: #ffffff;
            background: rgba(184, 137, 91, 0.25);
            border-left-color: var(--caramel-primary);
            font-weight: 600;
        }

        .admin-sidebar .nav-link i {
            font-size: 1.15rem;
            color: var(--caramel-light);
        }

        /* Main Content Container */
        .admin-main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Top Navbar */
        .admin-topbar {
            background: var(--bg-white);
            border-bottom: 1px solid var(--border-beige);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        /* Stats Cards */
        .stat-card {
            background: var(--bg-white);
            border: 1px solid var(--border-beige);
            border-radius: 10px;
            padding: 22px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(139, 99, 66, 0.08);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--bg-cream);
            color: var(--caramel-dark);
        }

        .stat-card .stat-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #777;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-charcoal);
            font-family: var(--font-serif);
            margin: 0;
        }

        /* Data Tables */
        .admin-table {
            background: var(--bg-white);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-beige);
        }

        .admin-table th {
            background: var(--bg-cream);
            color: var(--text-charcoal);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-beige);
        }

        .admin-table td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f3ece4;
            font-size: 0.88rem;
        }

        .admin-table tr:hover td {
            background-color: #faf6f0;
        }

        .btn-caramel {
            background-color: var(--caramel-primary);
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-caramel:hover {
            background-color: var(--caramel-dark);
            color: #fff;
        }

        /* Image Manager Thumbnails */
        .img-manage-card {
            position: relative;
            border: 2px solid var(--border-beige);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            aspect-ratio: 1;
        }

        .img-manage-card.is-primary {
            border-color: var(--caramel-primary);
            box-shadow: 0 0 0 2px rgba(184, 137, 91, 0.3);
        }

        .img-manage-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .img-manage-card .img-badge-primary {
            position: absolute;
            top: 6px;
            left: 6px;
            background: var(--caramel-primary);
            color: #fff;
            font-size: 0.68rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
        }

        .img-manage-card .img-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: space-around;
            padding: 4px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .img-manage-card:hover .img-actions {
            opacity: 1;
        }

        @media (max-width: 991px) {
            .admin-sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                bottom: 0;
            }
            .admin-sidebar.show {
                left: 0;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
