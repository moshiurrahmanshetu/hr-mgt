// HR Management System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle Functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const wrapper = document.getElementById('wrapper');
    
    if (sidebarToggle && wrapper) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            wrapper.classList.toggle('toggled');
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert.classList.contains('show')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
    
    // Form validation enhancements
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                // Re-enable button after 2 seconds (for demo purposes)
                setTimeout(function() {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.getAttribute('data-original-text') || submitButton.innerText;
                }, 2000);
            }
        });
    });
    
    // Password strength indicator (optional enhancement)
    const passwordInputs = document.querySelectorAll('input[type="password"][name="new_password"]');
    passwordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            // Update strength indicator if it exists
            const strengthIndicator = document.getElementById('password-strength');
            if (strengthIndicator) {
                const strengthText = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
                const strengthColors = ['#dc2626', '#f97316', '#eab308', '#22c55e', '#059669'];
                
                strengthIndicator.textContent = strengthText[strength] || '';
                strengthIndicator.style.color = strengthColors[strength] || '#64748b';
            }
        });
    });
    
    // Confirm before deletion
    const deleteButtons = document.querySelectorAll('form button[onclick*="confirm"]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('onclick').replace('return confirm(\'', '').replace('\')', ''))) {
                e.preventDefault();
            }
        });
    });
    
    // Tab persistence (optional enhancement)
    const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
    tabLinks.forEach(function(tab) {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-bs-target');
            if (tabId) {
                sessionStorage.setItem('activeTab', tabId);
            }
        });
    });
    
    // Restore active tab from session storage
    const activeTab = sessionStorage.getItem('activeTab');
    if (activeTab) {
        const tabElement = document.querySelector(`[data-bs-target="${activeTab}"]`);
        if (tabElement) {
            const tab = new bootstrap.Tab(tabElement);
            tab.show();
        }
    }
    
    // Clear tab storage when leaving page
    window.addEventListener('beforeunload', function() {
        sessionStorage.removeItem('activeTab');
    });
});
