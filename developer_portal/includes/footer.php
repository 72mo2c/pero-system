<?php
/**
 * Developer Portal Footer
 * Created: 2025-10-16
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not allowed');
}
?>
    </div>
</div>

<!-- Footer -->
<footer class="bg-white border-top mt-auto py-3">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0 text-muted small">
                    &copy; <?php echo date('Y'); ?> <?php echo SYSTEM_NAME; ?> - لوحة المطور. جميع الحقوق محفوظة.
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-0 text-muted small">
                    إصدار <?php echo SYSTEM_VERSION; ?> | طور بواسطة <?php echo SYSTEM_AUTHOR; ?>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Custom JavaScript -->
<script>
    // Global JavaScript functions and variables
    const SYSTEM_URL = '<?php echo SYSTEM_URL; ?>';
    const DEVELOPER_PORTAL_URL = '<?php echo DEVELOPER_PORTAL_URL; ?>';
    const CSRF_TOKEN = '<?php echo Security::generateCSRFToken(); ?>';
    
    // Loading overlay functions
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }
    
    // Sidebar toggle
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const contentWrapper = document.querySelector('.content-wrapper');
        
        sidebar.classList.toggle('collapsed');
        contentWrapper.classList.toggle('expanded');
        
        // Save state in localStorage
        localStorage.setItem('dev_sidebar_collapsed', sidebar.classList.contains('collapsed'));
    }
    
    // Initialize sidebar state
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarCollapsed = localStorage.getItem('dev_sidebar_collapsed') === 'true';
        if (sidebarCollapsed) {
            document.querySelector('.sidebar').classList.add('collapsed');
            document.querySelector('.content-wrapper').classList.add('expanded');
        }
    });
    
    // Mobile sidebar toggle
    function toggleMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('show');
    }
    
    // AJAX helper function
    function makeAjaxRequest(url, data = {}, method = 'POST') {
        return new Promise((resolve, reject) => {
            // Add CSRF token to data
            data.csrf_token = CSRF_TOKEN;
            
            $.ajax({
                url: url,
                method: method,
                data: data,
                dataType: 'json',
                timeout: 10000, // 10 second timeout
                beforeSend: function() {
                    showLoading();
                },
                success: function(response) {
                    hideLoading();
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('AJAX Error Details:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    
                    // Try to parse responseText as JSON if possible
                    let message = 'حدث خطأ في الاتصال بالخادم';
                    try {
                        if (xhr.responseJSON) {
                            message = xhr.responseJSON.message || message;
                        } else if (xhr.responseText) {
                            const parsed = JSON.parse(xhr.responseText);
                            message = parsed.message || message;
                        }
                    } catch (e) {
                        // If responseText is not JSON, show first 100 characters
                        if (xhr.responseText && xhr.responseText.length > 0) {
                            console.error('Non-JSON response:', xhr.responseText.substring(0, 100));
                        }
                    }
                    
                    reject({
                        status: xhr.status,
                        message: message,
                        responseText: xhr.responseText
                    });
                }
            });
        });
    }
    
    // Show toast notification
    function showToast(message, type = 'info') {
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast_' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });
        
        toast.show();
        
        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
    
    // Confirm dialog
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
    
    // Format number
    function formatNumber(number, decimals = 2) {
        return new Intl.NumberFormat('ar-SA', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }
    
    // Format currency
    function formatCurrency(amount) {
        return formatNumber(amount, 2) + ' ر.س';
    }
    
    // Debounce function for search inputs
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Logout function
    function logout() {
        if (confirm('هل أنت متأكد من أنك تريد تسجيل الخروج؟')) {
            makeAjaxRequest('logout.php').then(function(response) {
                if (response.success) {
                    window.location.href = 'login.php';
                } else {
                    showToast(response.message || 'حدث خطأ أثناء تسجيل الخروج', 'danger');
                }
            }).catch(function(error) {
                showToast(error.message, 'danger');
            });
        }
    }
    
    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert[data-auto-hide]');
        alerts.forEach(function(alert) {
            const delay = parseInt(alert.getAttribute('data-auto-hide')) || 5000;
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, delay);
        });
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    });
    
    // Table row actions
    function editRecord(id, url) {
        window.location.href = url + '?action=edit&id=' + id;
    }
    
    function deleteRecord(id, url, message = 'هل أنت متأكد من حذف هذا العنصر؟') {
        confirmAction(message, function() {
            makeAjaxRequest(url, {
                action: 'delete',
                id: id
            }).then(function(response) {
                if (response.success) {
                    showToast(response.message || 'تم الحذف بنجاح', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(response.message || 'حدث خطأ أثناء الحذف', 'danger');
                }
            }).catch(function(error) {
                showToast(error.message, 'danger');
            });
        });
    }
    
    // Form validation helper
    function validateForm(formElement) {
        const requiredFields = formElement.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    // Real-time system status check
    function checkSystemStatus() {
        console.log('Checking system status...');
        makeAjaxRequest('ajax/system_status.php', {}, 'GET')
            .then(function(response) {
                console.log('System status response received:', response);
                if (response && response.success) {
                    updateSystemStatus(response.data);
                } else {
                    console.warn('System status check returned unsuccessful response:', response);
                }
            })
            .catch(function(error) {
                console.error('System status check failed:', error);
                // إضافة محاولة مع الملف البديل
                console.log('Trying simple test endpoint...');
                makeAjaxRequest('ajax/simple_test.php', {}, 'GET')
                    .then(function(testResponse) {
                        console.log('Simple test successful:', testResponse);
                    })
                    .catch(function(testError) {
                        console.error('Simple test also failed:', testError);
                    });
            });
    }
    
    function updateSystemStatus(data) {
        // Update status indicators based on response
        const indicators = document.querySelectorAll('.system-status-indicator');
        indicators.forEach(function(indicator) {
            const statusType = indicator.getAttribute('data-status-type');
            if (data[statusType]) {
                indicator.className = 'badge bg-success rounded-pill system-status-indicator';
                indicator.title = `${statusType}: فعال`;
            } else {
                indicator.className = 'badge bg-danger rounded-pill system-status-indicator';
                indicator.title = `${statusType}: معطل`;
            }
        });
    }
    
    // Check system status every 2 minutes
    setInterval(checkSystemStatus, 120000);
    
    // Initial status check
    checkSystemStatus();
</script>

<?php if (isset($additional_js)): ?>
    <?php foreach ($additional_js as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (isset($inline_js)): ?>
    <script>
        <?php echo $inline_js; ?>
    </script>
<?php endif; ?>

</body>
</html>