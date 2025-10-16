<?php
/**
 * Setup and Installation Script for Warehouse SaaS System
 * Automated system installation and configuration
 * Created: 2025-10-16
 */

// Prevent running setup if system is already installed
if (file_exists('config/installed.lock')) {
    die('System is already installed. Delete config/installed.lock to run setup again.');
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success_messages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);
    
    switch ($step) {
        case 1:
            // Requirements check is automatic
            $step = 2;
            break;
            
        case 2:
            // Database configuration
            $db_host = trim($_POST['db_host'] ?? 'localhost');
            $db_username = trim($_POST['db_username'] ?? '');
            $db_password = $_POST['db_password'] ?? '';
            $db_name = trim($_POST['db_name'] ?? 'warehouse_saas_main');
            
            if (empty($db_username)) {
                $errors[] = 'Database username is required';
            } else {
                try {
                    // Test database connection
                    $dsn = "mysql:host={$db_host};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_username, $db_password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Create database if it doesn't exist
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS '{$db_name}' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    // Update database configuration
                    $config_content = file_get_contents('config/database.php');
                    $config_content = preg_replace(
                        "/'host' => 'localhost'/",
                        "'host' => '{$db_host}'",
                        $config_content
                    );
                    $config_content = preg_replace(
                        "/'username' => 'root'/",
                        "'username' => '{$db_username}'",
                        $config_content
                    );
                    $config_content = preg_replace(
                        "/'password' => ''/",
                        "'password' => '{$db_password}'",
                        $config_content
                    );
                    $config_content = preg_replace(
                        "/'database' => 'warehouse_saas_main'/",
                        "'database' => '{$db_name}'",
                        $config_content
                    );
                    
                    file_put_contents('config/database.php', $config_content);
                    $success_messages[] = 'Database configuration updated successfully';
                    $step = 3;
                    
                } catch (Exception $e) {
                    $errors[] = 'Database connection failed: ' . $e->getMessage();
                }
            }
            break;
            
        case 3:
            // Import database structure
            try {
                require_once 'config/database.php';
                $pdo = DatabaseConfig::getMainConnection();
                
                // Import main structure
                $sql = file_get_contents('database/main_structure.sql');
                
                // Split SQL statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE)/', $statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                $success_messages[] = 'Database structure imported successfully';
                $step = 4;
                
            } catch (Exception $e) {
                $errors[] = 'Database import failed: ' . $e->getMessage();
            }
            break;
            
        case 4:
            // Create admin account
            $admin_username = trim($_POST['admin_username'] ?? 'admin');
            $admin_email = trim($_POST['admin_email'] ?? 'admin@example.com');
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_confirm = $_POST['admin_confirm'] ?? '';
            $admin_fullname = trim($_POST['admin_fullname'] ?? 'System Administrator');
            
            if (empty($admin_password)) {
                $errors[] = 'Administrator password is required';
            } elseif ($admin_password !== $admin_confirm) {
                $errors[] = 'Password confirmation does not match';
            } elseif (strlen($admin_password) < 6) {
                $errors[] = 'Password must be at least 6 characters long';
            } else {
                try {
                    require_once 'config/config.php';
                    $pdo = DatabaseConfig::getMainConnection();
                    
                    $password_hash = password_hash($admin_password, PASSWORD_BCRYPT);
                    
                    // Update default admin account
                    $stmt = $pdo->prepare("
                        UPDATE developer_accounts 
                        SET username = ?, email = ?, password_hash = ?, full_name = ?, phone = ?
                        WHERE id = 1
                    ");
                    $stmt->execute([$admin_username, $admin_email, $password_hash, $admin_fullname, '+966500000000']);
                    
                    $success_messages[] = 'Administrator account created successfully';
                    $step = 5;
                    
                } catch (Exception $e) {
                    $errors[] = 'Failed to create administrator account: ' . $e->getMessage();
                }
            }
            break;
            
        case 5:
            // Final configuration
            try {
                // Create directories
                $dirs = ['backups', 'uploads', 'logs', 'temp'];
                foreach ($dirs as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }
                
                // Create .htaccess files for security
                file_put_contents('backups/.htaccess', "Order deny,allow\nDeny from all");
                file_put_contents('logs/.htaccess', "Order deny,allow\nDeny from all");
                file_put_contents('config/.htaccess', "Order deny,allow\nDeny from all");
                
                // Create installation lock file
                file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
                
                $success_messages[] = 'System installation completed successfully';
                $step = 6;
                
            } catch (Exception $e) {
                $errors[] = 'Final configuration failed: ' . $e->getMessage();
            }
            break;
    }
}

// Check system requirements
function checkRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'MySQL Extension' => extension_loaded('mysql') || extension_loaded('mysqli') || extension_loaded('pdo_mysql'),
        'PDO Extension' => extension_loaded('pdo'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'JSON Extension' => extension_loaded('json'),
        'cURL Extension' => extension_loaded('curl'),
        'GD Extension' => extension_loaded('gd'),
        'Config Directory Writable' => is_writable('config'),
        'Logs Directory Writable' => is_writable('.') // We'll create logs directory
    ];
    
    return $requirements;
}

$requirements = checkRequirements();
$all_requirements_met = !in_array(false, $requirements);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse SaaS System - Setup</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
        }
        
        .setup-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            color: #64748b;
            font-weight: 600;
            position: relative;
        }
        
        .step.active {
            background: #2563eb;
            color: white;
        }
        
        .step.completed {
            background: #059669;
            color: white;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            width: 20px;
            height: 2px;
            background: #e2e8f0;
            margin-top: -1px;
        }
        
        .step.completed:not(:last-child)::after {
            background: #059669;
        }
        
        .requirement {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .requirement.pass {
            background: rgba(5, 150, 105, 0.1);
            border-color: #059669;
        }
        
        .requirement.fail {
            background: rgba(220, 38, 38, 0.1);
            border-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="setup-container">
                    <div class="setup-header">
                        <i class="fas fa-warehouse mb-3" style="font-size: 3rem;"></i>
                        <h1>Warehouse SaaS System</h1>
                        <p>System Installation & Setup</p>
                    </div>
                    
                    <div class="p-4">
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step <?php echo $step >= 1 ? ($step == 1 ? 'active' : 'completed') : ''; ?>">1</div>
                            <div class="step <?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : ''; ?>">2</div>
                            <div class="step <?php echo $step >= 3 ? ($step == 3 ? 'active' : 'completed') : ''; ?>">3</div>
                            <div class="step <?php echo $step >= 4 ? ($step == 4 ? 'active' : 'completed') : ''; ?>">4</div>
                            <div class="step <?php echo $step >= 5 ? ($step == 5 ? 'active' : 'completed') : ''; ?>">5</div>
                            <div class="step <?php echo $step >= 6 ? 'active' : ''; ?>">6</div>
                        </div>
                        
                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Success Messages -->
                        <?php if (!empty($success_messages)): ?>
                            <div class="alert alert-success">
                                <ul class="mb-0">
                                    <?php foreach ($success_messages as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($step == 1): ?>
                            <!-- Step 1: Requirements Check -->
                            <h3>System Requirements Check</h3>
                            <p class="text-muted">Checking if your server meets the minimum requirements...</p>
                            
                            <div class="requirements-list">
                                <?php foreach ($requirements as $name => $status): ?>
                                    <div class="requirement <?php echo $status ? 'pass' : 'fail'; ?>">
                                        <span><?php echo $name; ?></span>
                                        <span>
                                            <?php if ($status): ?>
                                                <i class="fas fa-check text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($all_requirements_met): ?>
                                <form method="POST" class="mt-4">
                                    <input type="hidden" name="step" value="1">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-arrow-right me-2"></i>Continue to Database Setup
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning mt-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Please fix the requirements above before continuing.
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($step == 2): ?>
                            <!-- Step 2: Database Configuration -->
                            <h3>Database Configuration</h3>
                            <p class="text-muted">Configure your MySQL database connection...</p>
                            
                            <form method="POST">
                                <input type="hidden" name="step" value="2">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Database Host</label>
                                            <input type="text" class="form-control" name="db_host" 
                                                   value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Database Name</label>
                                            <input type="text" class="form-control" name="db_name" 
                                                   value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'warehouse_saas_main'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Database Username</label>
                                            <input type="text" class="form-control" name="db_username" 
                                                   value="<?php echo htmlspecialchars($_POST['db_username'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Database Password</label>
                                            <input type="password" class="form-control" name="db_password" 
                                                   value="<?php echo htmlspecialchars($_POST['db_password'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-database me-2"></i>Test Connection & Continue
                                </button>
                            </form>
                            
                        <?php elseif ($step == 3): ?>
                            <!-- Step 3: Database Import -->
                            <h3>Database Installation</h3>
                            <p class="text-muted">Installing database structure...</p>
                            
                            <form method="POST">
                                <input type="hidden" name="step" value="3">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    This will create all necessary tables and initial data.
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-download me-2"></i>Install Database Structure
                                </button>
                            </form>
                            
                        <?php elseif ($step == 4): ?>
                            <!-- Step 4: Admin Account -->
                            <h3>Administrator Account</h3>
                            <p class="text-muted">Create your administrator account...</p>
                            
                            <form method="POST">
                                <input type="hidden" name="step" value="4">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="admin_username" 
                                                   value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" class="form-control" name="admin_email" 
                                                   value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@example.com'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="admin_fullname" 
                                           value="<?php echo htmlspecialchars($_POST['admin_fullname'] ?? 'System Administrator'); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="password" class="form-control" name="admin_password" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Confirm Password</label>
                                            <input type="password" class="form-control" name="admin_confirm" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-user-plus me-2"></i>Create Administrator
                                </button>
                            </form>
                            
                        <?php elseif ($step == 5): ?>
                            <!-- Step 5: Final Configuration -->
                            <h3>Final Configuration</h3>
                            <p class="text-muted">Completing system setup...</p>
                            
                            <form method="POST">
                                <input type="hidden" name="step" value="5">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-cog me-2"></i>
                                    This will create necessary directories and complete the installation.
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-check me-2"></i>Complete Installation
                                </button>
                            </form>
                            
                        <?php elseif ($step == 6): ?>
                            <!-- Step 6: Installation Complete -->
                            <div class="text-center">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                                <h3 class="mt-3">Installation Complete!</h3>
                                <p class="text-muted">Your Warehouse SaaS System has been successfully installed.</p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-code text-primary" style="font-size: 2rem;"></i>
                                                <h5 class="mt-2">Developer Portal</h5>
                                                <p class="text-muted small">Manage tenants and system</p>
                                                <a href="developer_portal/" class="btn btn-primary">
                                                    <i class="fas fa-external-link-alt me-1"></i>Access Portal
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="fas fa-warehouse text-success" style="font-size: 2rem;"></i>
                                                <h5 class="mt-2">Warehouse System</h5>
                                                <p class="text-muted small">Main application for tenants</p>
                                                <a href="warehouse_system/" class="btn btn-success">
                                                    <i class="fas fa-external-link-alt me-1"></i>Access System
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-4">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <strong>Security Notice:</strong> Please delete this setup.php file for security reasons.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>