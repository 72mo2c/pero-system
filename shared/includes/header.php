<?php
/**
 * Common Header for Warehouse SaaS System
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
    <meta name="description" content="نظام إدارة المخازن - SaaS">
    <meta name="author" content="MiniMax Agent">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SYSTEM_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SYSTEM_URL; ?>/assets/images/favicon.png">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SYSTEM_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-color: #e2e8f0;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu .menu-item {
            margin: 0.25rem 0;
        }
        
        .sidebar-menu .menu-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
        }
        
        .sidebar-menu .menu-link:hover,
        .sidebar-menu .menu-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-right: 3px solid white;
        }
        
        .sidebar-menu .menu-link i {
            width: 20px;
            margin-left: 0.75rem;
            text-align: center;
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
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border-color);
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 700;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: var(--light-color);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        .table {
            margin: 0;
        }
        
        .table thead th {
            background-color: var(--light-color);
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
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
        
        .form-control, .form-select {
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        .modal-header {
            background-color: var(--light-color);
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-footer {
            background-color: var(--light-color);
            border-top: 1px solid var(--border-color);
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
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
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <div class="mt-2">جاري التحميل...</div>
        </div>
    </div>