<?php
/**
 * Developer Portal Header
 * Created: 2025-10-16
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not allowed');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="لوحة تحكم المطور - نظام إدارة المخازن">
    <meta name="author" content="MiniMax Agent">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>لوحة المطور - <?php echo SYSTEM_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SYSTEM_URL; ?>/assets/images/favicon.png">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --dev-primary: #6366f1;
            --dev-secondary: #8b5cf6;
            --dev-success: #10b981;
            --dev-danger: #ef4444;
            --dev-warning: #f59e0b;
            --dev-info: #06b6d4;
            --dev-light: #f8fafc;
            --dev-dark: #1e293b;
            --dev-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
        }
        
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: var(--dev-gradient);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-header .logo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .sidebar-header .subtitle {
            font-size: 0.875rem;
            opacity: 0.8;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu .menu-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            opacity: 0.7;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu .menu-item {
            margin: 0.25rem 0;
        }
        
        .sidebar-menu .menu-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
            position: relative;
        }
        
        .sidebar-menu .menu-link:hover,
        .sidebar-menu .menu-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(-5px);
        }
        
        .sidebar-menu .menu-link.active::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
        }
        
        .sidebar-menu .menu-link i {
            width: 20px;
            margin-left: 0.75rem;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .sidebar-menu .menu-link .badge {
            margin-right: auto;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .content-wrapper {
            flex: 1;
            margin-right: 280px;
            transition: all 0.3s ease;
        }
        
        .content-wrapper.expanded {
            margin-right: 70px;
        }
        
        .top-navbar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-left: 4px solid var(--dev-primary);
        }
        
        .page-header h1 {
            margin: 0;
            color: var(--dev-dark);
            font-weight: 700;
            font-size: 1.75rem;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
            font-size: 0.875rem;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: var(--dev-light);
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem;
            font-weight: 600;
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }
        
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--dev-primary);
            border-color: var(--dev-primary);
        }
        
        .btn-primary:hover {
            background: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-1px);
        }
        
        .stats-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stats-card.primary {
            border-left-color: var(--dev-primary);
        }
        
        .stats-card.success {
            border-left-color: var(--dev-success);
        }
        
        .stats-card.warning {
            border-left-color: var(--dev-warning);
        }
        
        .stats-card.danger {
            border-left-color: var(--dev-danger);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .developer-badge {
            background: var(--dev-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .activity-item {
            border-left: 3px solid var(--dev-primary);
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .content-wrapper {
                margin-right: 0;
            }
            
            .main-content {
                padding: 1rem;
            }
        }
        
        /* Custom animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Loading states */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 9999; display: flex; align-items: center; justify-content: center;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <div class="mt-3 fw-bold">جاري التحميل...</div>
        </div>
    </div>