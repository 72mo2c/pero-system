/**
 * Main JavaScript Application File
 * Warehouse SaaS System
 * Created: 2025-10-16
 */

'use strict';

// Application namespace
const WareHouseApp = {
    // Configuration
    config: {
        baseUrl: window.location.origin,
        apiUrl: window.location.origin + '/api',
        ajaxTimeout: 30000,
        toastTimeout: 5000
    },

    // Initialize application
    init: function() {
        this.setupEventListeners();
        this.initializeTooltips();
        this.initializeModals();
        this.setupAjaxDefaults();
        this.initializeConfirmations();
        this.setupFormValidation();
        this.initializeDataTables();
        this.setupSidebar();
    },

    // Setup global event listeners
    setupEventListeners: function() {
        // Handle all AJAX form submissions
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.dataset.ajax === 'true') {
                e.preventDefault();
                WareHouseApp.handleAjaxForm(form);
            }
        });

        // Handle confirmation dialogs
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-confirm]');
            if (target) {
                e.preventDefault();
                WareHouseApp.showConfirmation(target.dataset.confirm, function() {
                    if (target.tagName === 'A') {
                        window.location.href = target.href;
                    } else if (target.tagName === 'BUTTON' && target.type === 'submit') {
                        target.closest('form').submit();
                    } else if (target.onclick) {
                        target.onclick();
                    }
                });
            }
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-dismissible')) {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }
            });
        }, WareHouseApp.config.toastTimeout);

        // Handle number formatting
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('number-format')) {
                WareHouseApp.formatNumber(e.target);
            }
        });
    },

    // Initialize Bootstrap tooltips
    initializeTooltips: function() {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    },

    // Initialize Bootstrap modals
    initializeModals: function() {
        if (typeof bootstrap !== 'undefined') {
            // Auto-focus first input when modal opens
            document.addEventListener('shown.bs.modal', function(e) {
                const firstInput = e.target.querySelector('input:not([type="hidden"]), select, textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            });

            // Clear form when modal closes
            document.addEventListener('hidden.bs.modal', function(e) {
                const form = e.target.querySelector('form');
                if (form && form.dataset.clearOnClose !== 'false') {
                    form.reset();
                    // Clear validation states
                    const invalidFields = form.querySelectorAll('.is-invalid');
                    invalidFields.forEach(field => field.classList.remove('is-invalid'));
                    const errorMessages = form.querySelectorAll('.invalid-feedback');
                    errorMessages.forEach(msg => msg.remove());
                }
            });
        }
    },

    // Setup AJAX defaults
    setupAjaxDefaults: function() {
        // Add CSRF token to all AJAX requests
        if (typeof $ !== 'undefined') {
            $.ajaxSetup({
                timeout: WareHouseApp.config.ajaxTimeout,
                beforeSend: function(xhr, settings) {
                    if (!/^(GET|HEAD|OPTIONS|TRACE)$/i.test(settings.type) && !this.crossDomain) {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]');
                        if (csrfToken) {
                            xhr.setRequestHeader("X-CSRFToken", csrfToken.getAttribute('content'));
                        }
                    }
                    WareHouseApp.showLoading();
                },
                complete: function() {
                    WareHouseApp.hideLoading();
                },
                error: function(xhr, status, error) {
                    WareHouseApp.handleAjaxError(xhr, status, error);
                }
            });
        }
    },

    // Handle AJAX form submissions
    handleAjaxForm: function(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';

        // Disable submit button
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...';
        }

        fetch(form.action || window.location.href, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ${response.status}');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                WareHouseApp.showSuccess(data.message || 'تم الحفظ بنجاح');
                
                // Handle redirect
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1000);
                }
                
                // Handle modal close
                if (form.closest('.modal')) {
                    const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
                    if (modal) {
                        modal.hide();
                    }
                }
                
                // Handle table reload
                if (data.reload_table && typeof WareHouseApp.reloadTable === 'function') {
                    WareHouseApp.reloadTable();
                }
            } else {
                WareHouseApp.showError(data.message || 'حدث خطأ أثناء الحفظ');
                
                // Handle field errors
                if (data.errors) {
                    WareHouseApp.showFieldErrors(form, data.errors);
                }
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            WareHouseApp.showError('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
        })
        .finally(() => {
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    },

    // Show field validation errors
    showFieldErrors: function(form, errors) {
        // Clear previous errors
        const previousErrors = form.querySelectorAll('.is-invalid, .invalid-feedback');
        previousErrors.forEach(el => {
            if (el.classList.contains('is-invalid')) {
                el.classList.remove('is-invalid');
            } else {
                el.remove();
            }
        });

        // Show new errors
        Object.keys(errors).forEach(fieldName => {
            const field = form.querySelector('[name="${fieldName}"]');
            if (field) {
                field.classList.add('is-invalid');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = Array.isArray(errors[fieldName]) ? errors[fieldName][0] : errors[fieldName];
                field.parentNode.appendChild(errorDiv);
            }
        });
    },

    // Show confirmation dialog
    showConfirmation: function(message, callback, cancelCallback) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'تأكيد',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'نعم',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed && callback) {
                    callback();
                } else if (result.isDismissed && cancelCallback) {
                    cancelCallback();
                }
            });
        } else {
            // Fallback to native confirm
            if (confirm(message) && callback) {
                callback();
            }
        }
    },

    // Initialize confirmation dialogs
    initializeConfirmations: function() {
        // Already handled in setupEventListeners
    },

    // Setup form validation
    setupFormValidation: function() {
        // Real-time validation
        document.addEventListener('blur', function(e) {
            if (e.target.matches('input, select, textarea')) {
                WareHouseApp.validateField(e.target);
            }
        }, true);

        // Form submission validation
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.classList.contains('needs-validation')) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            }
        });
    },

    // Validate individual field
    validateField: function(field) {
        const isValid = field.checkValidity();
        
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }
        
        return isValid;
    },

    // Initialize DataTables
    initializeDataTables: function() {
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            $('.data-table').each(function() {
                const table = $(this);
                if (!table.hasClass('dataTable')) {
                    table.DataTable({
                        responsive: true,
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/ar.json'
                        },
                        pageLength: 25,
                        order: [[0, 'desc']],
                        columnDefs: [
                            { targets: 'no-sort', orderable: false },
                            { targets: 'text-center', className: 'text-center' }
                        ]
                    });
                }
            });
        }
    },

    // Setup sidebar functionality
    setupSidebar: function() {
        const sidebarToggle = document.querySelector('[data-bs-toggle="sidebar"]');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target) &&
                    sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            });
        }
    },

    // Show loading indicator
    showLoading: function() {
        let loader = document.getElementById('global-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'global-loader';
            loader.innerHTML = '
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">جاري التحميل...</span>
                </div>
            ';
            loader.style.cssText = '
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
                background: rgba(255, 255, 255, 0.9);
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            ';
            document.body.appendChild(loader);
        }
        loader.style.display = 'block';
    },

    // Hide loading indicator
    hideLoading: function() {
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    },

    // Show success message
    showSuccess: function(message) {
        this.showToast(message, 'success');
    },

    // Show error message
    showError: function(message) {
        this.showToast(message, 'error');
    },

    // Show warning message
    showWarning: function(message) {
        this.showToast(message, 'warning');
    },

    // Show info message
    showInfo: function(message) {
        this.showToast(message, 'info');
    },

    // Show toast notification
    showToast: function(message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            const icons = {
                success: 'success',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };
            
            Swal.fire({
                title: message,
                icon: icons[type] || 'info',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            // Fallback to creating toast manually
            this.createToast(message, type);
        }
    },

    // Create manual toast
    createToast: function(message, type) {
        const toast = document.createElement('div');
        const typeClasses = {
            success: 'alert-success',
            error: 'alert-danger',
            warning: 'alert-warning',
            info: 'alert-info'
        };
        
        toast.className = 'alert ${typeClasses[type] || 'alert-info'} alert-dismissible fade show position-fixed';
        toast.style.cssText = 'top: 20px; left: 20px; z-index: 9999; max-width: 400px;';
        toast.innerHTML = '
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        ';
        
        document.body.appendChild(toast);
        
        // Auto-remove after timeout
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, this.config.toastTimeout);
    },

    // Handle AJAX errors
    handleAjaxError: function(xhr, status, error) {
        console.error('AJAX Error:', { xhr, status, error });
        
        let message = 'حدث خطأ في الاتصال';
        
        if (xhr.status === 401) {
            message = 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى.';
            setTimeout(() => window.location.href = '/login', 2000);
        } else if (xhr.status === 403) {
            message = 'ليس لديك صلاحية للقيام بهذا الإجراء';
        } else if (xhr.status === 404) {
            message = 'الصفحة المطلوبة غير موجودة';
        } else if (xhr.status === 500) {
            message = 'خطأ في الخادم. يرجى المحاولة مرة أخرى لاحقاً';
        } else if (status === 'timeout') {
            message = 'انتهت مهلة الاتصال. يرجى المحاولة مرة أخرى';
        }
        
        this.showError(message);
    },

    // Format number input
    formatNumber: function(input) {
        let value = input.value.replace(/[^\d.]/g, '');
        const parts = value.split('.');
        
        if (parts.length > 2) {
            value = parts[0] + '.' + parts.slice(1).join('');
        }
        
        if (parts[0]) {
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        input.value = parts.join('.');
    },

    // Utility functions
    utils: {
        // Format currency
        formatCurrency: function(amount, currency = 'ر.س') {
            const number = parseFloat(amount);
            if (isNaN(number)) return '0.00 ' + currency;
            return number.toLocaleString('ar-SA', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' ' + currency;
        },

        // Format date
        formatDate: function(date, format = 'YYYY-MM-DD') {
            if (!date) return '';
            const d = new Date(date);
            if (isNaN(d.getTime())) return '';
            
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            
            return format
                .replace('YYYY', year)
                .replace('MM', month)
                .replace('DD', day);
        },

        // Debounce function
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        // Generate random string
        randomString: function(length = 10) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },

        // Copy to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    WareHouseApp.showSuccess('تم النسخ إلى الحافظة');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    WareHouseApp.showSuccess('تم النسخ إلى الحافظة');
                } catch (err) {
                    WareHouseApp.showError('فشل في النسخ');
                }
                document.body.removeChild(textArea);
            }
        }
    }
};

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    WareHouseApp.init();
});

// Make available globally
window.WareHouseApp = WareHouseApp;
