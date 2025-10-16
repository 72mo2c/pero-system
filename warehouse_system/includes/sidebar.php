<?php
/**
 * Warehouse System Sidebar Navigation
 * Created: 2025-10-16
 */

$current_page = $current_page ?? '';
$user_role = Session::getUserRole();
?>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-header p-3">
        <div class="text-center">
            <h5 class="text-white mb-1"><?php echo Session::getTenantName(); ?></h5>
            <small class="text-light opacity-75">نظام إدارة المخازن</small>
        </div>
    </div>
    
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>لوحة التحكم</span>
                </a>
            </li>
            
            <!-- Inventory Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#inventoryMenu" role="button">
                    <i class="fas fa-boxes"></i>
                    <span>إدارة المخزون</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['products', 'categories', 'stock', 'adjustments']) ? 'show' : ''; ?>" id="inventoryMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'products' ? 'active' : ''; ?>" href="products.php">
                                <i class="fas fa-box"></i>
                                <span>المنتجات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'categories' ? 'active' : ''; ?>" href="categories.php">
                                <i class="fas fa-tags"></i>
                                <span>فئات المنتجات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'stock' ? 'active' : ''; ?>" href="stock.php">
                                <i class="fas fa-warehouse"></i>
                                <span>حالة المخزون</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'adjustments' ? 'active' : ''; ?>" href="adjustments.php">
                                <i class="fas fa-edit"></i>
                                <span>تعديل المخزون</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Sales Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#salesMenu" role="button">
                    <i class="fas fa-shopping-cart"></i>
                    <span>إدارة المبيعات</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['sales', 'sales_orders', 'invoices']) ? 'show' : ''; ?>" id="salesMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'sales' ? 'active' : ''; ?>" href="sales.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span>المبيعات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'sales_orders' ? 'active' : ''; ?>" href="sales_orders.php">
                                <i class="fas fa-file-invoice"></i>
                                <span>أوامر البيع</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'invoices' ? 'active' : ''; ?>" href="invoices.php">
                                <i class="fas fa-receipt"></i>
                                <span>الفواتير</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'pos' ? 'active' : ''; ?>" href="pos.php">
                                <i class="fas fa-cash-register"></i>
                                <span>نقطة البيع</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Purchase Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#purchaseMenu" role="button">
                    <i class="fas fa-truck"></i>
                    <span>إدارة المشتريات</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['purchases', 'purchase_orders', 'receiving']) ? 'show' : ''; ?>" id="purchaseMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'purchase_orders' ? 'active' : ''; ?>" href="purchase_orders.php">
                                <i class="fas fa-shopping-basket"></i>
                                <span>المشتريات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'receiving' ? 'active' : ''; ?>" href="receiving.php">
                                <i class="fas fa-dolly"></i>
                                <span>استلام البضائع</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Contacts Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#contactsMenu" role="button">
                    <i class="fas fa-users"></i>
                    <span>إدارة الجهات</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['customers', 'suppliers']) ? 'show' : ''; ?>" id="contactsMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'customers' ? 'active' : ''; ?>" href="customers.php">
                                <i class="fas fa-user-friends"></i>
                                <span>العملاء</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'suppliers' ? 'active' : ''; ?>" href="suppliers.php">
                                <i class="fas fa-truck-loading"></i>
                                <span>الموردين</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Treasury Management -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#treasuryMenu" role="button">
                    <i class="fas fa-coins"></i>
                    <span>إدارة الخزينة</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['treasury', 'accounts', 'transactions']) ? 'show' : ''; ?>" id="treasuryMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'treasury' ? 'active' : ''; ?>" href="treasury.php">
                                <i class="fas fa-coins"></i>
                                <span>الخزينة</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'accounts' ? 'active' : ''; ?>" href="treasury_accounts.php">
                                <i class="fas fa-university"></i>
                                <span>الحسابات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'transactions' ? 'active' : ''; ?>" href="transactions.php">
                                <i class="fas fa-exchange-alt"></i>
                                <span>الحركات المالية</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-toggle="collapse" href="#reportsMenu" role="button">
                    <i class="fas fa-chart-bar"></i>
                    <span>التقارير</span>
                    <i class="fas fa-chevron-down collapse-icon"></i>
                </a>
                <div class="collapse <?php echo in_array($current_page, ['reports', 'sales_report', 'inventory_report', 'financial_report']) ? 'show' : ''; ?>" id="reportsMenu">
                    <ul class="nav flex-column sub-menu">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>لوحة التقارير</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'sales_report' ? 'active' : ''; ?>" href="reports_sales.php">
                                <i class="fas fa-chart-line"></i>
                                <span>تقارير المبيعات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'inventory_report' ? 'active' : ''; ?>" href="reports_inventory.php">
                                <i class="fas fa-boxes"></i>
                                <span>تقارير المخزون</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'financial_report' ? 'active' : ''; ?>" href="reports_financial.php">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>التقارير المالية</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Activity Log (Admin/Manager only) -->
            <?php if (in_array($user_role, ['admin', 'manager'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'activity_log' ? 'active' : ''; ?>" href="activity_log.php">
                    <i class="fas fa-history"></i>
                    <span>سجل الأنشطة</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Warehouses (Admin/Manager only) -->
            <?php if (in_array($user_role, ['admin', 'manager'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'warehouses' ? 'active' : ''; ?>" href="warehouses.php">
                    <i class="fas fa-warehouse"></i>
                    <span>المخازن</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- User Management (Admin only) -->
            <?php if ($user_role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-user-cog"></i>
                    <span>إدارة المستخدمين</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Settings (Admin only) -->
            <?php if ($user_role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Profile -->
            <li class="nav-item mt-3">
                <a class="nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>الملف الشخصي</span>
                </a>
            </li>
            
            <!-- Logout -->
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- User Info Footer -->
    <div class="sidebar-footer p-3 border-top">
        <div class="user-info text-center">
            <div class="user-avatar mb-2">
                <i class="fas fa-user-circle fa-2x text-light"></i>
            </div>
            <div class="user-details text-light">
                <small class="d-block"><?php echo htmlspecialchars(Session::get('user_full_name')); ?></small>
                <small class="text-muted"><?php echo htmlspecialchars(Session::getUserRole()); ?></small>
            </div>
        </div>
    </div>
</nav>

<style>
.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    width: 280px;
    position: fixed;
    top: 0;
    right: 0;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-content {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    padding: 1rem 0;
}

.sidebar .nav-link {
    color: rgba(255,255,255,0.8);
    padding: 12px 20px;
    border-radius: 8px;
    margin: 2px 10px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    text-decoration: none;
}

.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(-5px);
}

.sidebar .nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    font-weight: 600;
}

.sidebar .nav-link i {
    width: 20px;
    margin-left: 12px;
    font-size: 16px;
}

.sidebar .nav-link span {
    flex: 1;
}

.collapse-icon {
    font-size: 12px;
    transition: transform 0.3s ease;
}

.nav-link[aria-expanded="true"] .collapse-icon {
    transform: rotate(180deg);
}

.sub-menu {
    background: rgba(0,0,0,0.1);
    border-radius: 8px;
    margin: 5px 10px;
    padding: 5px 0;
}

.sub-menu .nav-link {
    padding: 8px 20px 8px 50px;
    margin: 1px 5px;
    font-size: 14px;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.1);
}

.user-avatar {
    opacity: 0.8;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}

/* Custom scrollbar */
.sidebar-content::-webkit-scrollbar {
    width: 4px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 2px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.5);
}
</style>

<script>
// Handle sidebar toggle on mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.querySelector('[data-bs-toggle="sidebar"]');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !sidebarToggle?.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
});
</script>
