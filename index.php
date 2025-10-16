<?php
/**
 * Warehouse SaaS System - Main Entry Point
 * System router and welcome page
 * Created: 2025-10-16
 */

// Check if system is installed
if (!file_exists('config/installed.lock')) {
    header('Location: setup.php');
    exit;
}

// Include configuration
require_once 'config/config.php';

// Simple routing based on user type
if (Session::isDeveloperLoggedIn()) {
    header('Location: developer_portal/dashboard.php');
    exit;
} elseif (Session::isUserLoggedIn()) {
    header('Location: warehouse_system/dashboard.php');
    exit;
}

// Default welcome page
$page_title = 'Welcome to Warehouse SaaS System';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .welcome-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .welcome-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>') repeat;
            animation: float 20s linear infinite;
        }
        
        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .welcome-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 3rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-header p {
            margin: 1rem 0 0 0;
            opacity: 0.9;
            font-size: 1.2rem;
            position: relative;
            z-index: 1;
        }
        
        .welcome-body {
            padding: 3rem 2rem;
        }
        
        .feature-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .btn-portal {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }
        
        .btn-portal:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-warehouse {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }
        
        .btn-warehouse:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .stats {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2563eb;
            display: block;
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="welcome-container">
                    <div class="welcome-header">
                        <i class="fas fa-warehouse mb-4" style="font-size: 4rem;"></i>
                        <h1>Warehouse SaaS System</h1>
                        <p>Complete Multi-Tenant Warehouse Management Solution</p>
                        <div class="mt-4">
                            <span class="badge bg-light text-dark px-3 py-2 me-2">
                                <i class="fas fa-code me-1"></i>Version <?php echo SYSTEM_VERSION; ?>
                            </span>
                            <span class="badge bg-light text-dark px-3 py-2">
                                <i class="fas fa-calendar me-1"></i>Released 2025
                            </span>
                        </div>
                    </div>
                    
                    <div class="welcome-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <h3 class="mb-4">Choose Your Portal</h3>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="feature-card">
                                            <div class="feature-icon bg-primary">
                                                <i class="fas fa-code"></i>
                                            </div>
                                            <h5>Developer Portal</h5>
                                            <p class="text-muted mb-3">
                                                System administration, tenant management, and global settings.
                                            </p>
                                            <ul class="list-unstyled text-sm text-muted">
                                                <li><i class="fas fa-check text-success me-2"></i>Manage all tenants</li>
                                                <li><i class="fas fa-check text-success me-2"></i>System monitoring</li>
                                                <li><i class="fas fa-check text-success me-2"></i>Backup & restore</li>
                                                <li><i class="fas fa-check text-success me-2"></i>Analytics & reports</li>
                                            </ul>
                                            <a href="developer_portal/" class="btn-portal">
                                                <i class="fas fa-cogs me-2"></i>Access Developer Portal
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="feature-card">
                                            <div class="feature-icon bg-success">
                                                <i class="fas fa-warehouse"></i>
                                            </div>
                                            <h5>Warehouse System</h5>
                                            <p class="text-muted mb-3">
                                                Complete warehouse management for individual companies.
                                            </p>
                                            <ul class="list-unstyled text-sm text-muted">
                                                <li><i class="fas fa-check text-success me-2"></i>Inventory management</li>
                                                <li><i class="fas fa-check text-success me-2"></i>Sales & purchases</li>
                                                <li><i class="fas fa-check text-success me-2"></i>Financial tracking</li>
                                                <li><i class="fas fa-check text-success me-2"></i>Detailed reports</li>
                                            </ul>
                                            <a href="warehouse_system/" class="btn-warehouse">
                                                <i class="fas fa-boxes me-2"></i>Access Warehouse System
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h4>Key Features</h4>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="feature-icon bg-info me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <i class="fas fa-shield-alt"></i>
                                                </div>
                                                <div>
                                                    <strong>Enterprise Security</strong>
                                                    <br><small class="text-muted">Advanced security measures</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="feature-icon bg-warning me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <i class="fas fa-mobile-alt"></i>
                                                </div>
                                                <div>
                                                    <strong>Mobile Responsive</strong>
                                                    <br><small class="text-muted">Works on all devices</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="feature-icon bg-danger me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <i class="fas fa-language"></i>
                                                </div>
                                                <div>
                                                    <strong>Arabic Support</strong>
                                                    <br><small class="text-muted">Full RTL language support</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <div class="stats">
                                    <h4 class="mb-4">System Stats</h4>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <span class="stat-number">100%</span>
                                                <span class="stat-label">Ready</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <span class="stat-number">24/7</span>
                                                <span class="stat-label">Support</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <span class="stat-number">∞</span>
                                                <span class="stat-label">Scalable</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <span class="stat-number">2025</span>
                                                <span class="stat-label">Modern</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 pt-3 border-top">
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie me-1"></i>
                                            Developed by <strong><?php echo SYSTEM_AUTHOR; ?></strong>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mt-4 text-center">
                                    <h5>Need Help?</h5>
                                    <p class="text-muted small">Access the documentation or contact support for assistance.</p>
                                    <a href="#" class="btn btn-outline-primary btn-sm me-2">
                                        <i class="fas fa-book me-1"></i>Documentation
                                    </a>
                                    <a href="#" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-headset me-1"></i>Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>