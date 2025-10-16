<?php
/**
 * Common Footer for Warehouse SaaS System
 * Created: 2025-10-16
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not allowed');
}
?>
    <!-- Footer -->
    <footer class="bg-white border-top mt-auto py-3">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted small">
                        &copy; <?php echo date('Y'); ?> <?php echo SYSTEM_NAME; ?>. جميع الحقوق محفوظة.
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
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
        }
        
        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
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
                    beforeSend: function() {
                        showLoading();
                    },
                    success: function(response) {
                        hideLoading();
                        resolve(response);
                    },
                    error: function(xhr, status, error) {
                        hideLoading();
                        console.error('AJAX Error:', error);
                        reject({
                            status: xhr.status,
                            message: xhr.responseJSON ? xhr.responseJSON.message : 'حدث خطأ في الاتصال بالخادم'
                        });
                    }
                });
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                // Create toast container if it doesn't exist
                const container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }
            
            const toastId = 'toast_' + Date.now();
            const toastHtml = '
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            ';
            
            document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
            
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
        
        // Auto-save form data to localStorage
        function autoSaveForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const inputs = form.querySelectorAll('input, textarea, select');
            const storageKey = 'autosave_' + formId;
            
            // Load saved data
            const savedData = localStorage.getItem(storageKey);
            if (savedData) {
                const data = JSON.parse(savedData);
                inputs.forEach(function(input) {
                    if (data[input.name] && input.type !== 'password') {
                        input.value = data[input.name];
                    }
                });
            }
            
            // Save data on input
            inputs.forEach(function(input) {
                input.addEventListener('input', debounce(function() {
                    const formData = new FormData(form);
                    const data = {};
                    for (let [key, value] of formData.entries()) {
                        data[key] = value;
                    }
                    localStorage.setItem(storageKey, JSON.stringify(data));
                }, 1000));
            });
            
            // Clear saved data on successful submit
            form.addEventListener('submit', function() {
                localStorage.removeItem(storageKey);
            });
        }
        
        // Initialize features
        document.addEventListener('DOMContentLoaded', function() {
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