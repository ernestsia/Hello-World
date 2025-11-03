// SchoolManagement System - Custom JavaScript

// Toggle Mobile Menu
function toggleMobileMenu() {
    const menu = document.querySelector('.navbar-menu');
    if (menu) {
        menu.classList.toggle('active');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.querySelector('.navbar-menu');
    const toggle = document.querySelector('.mobile-toggle');
    
    if (menu && toggle && window.innerWidth <= 992) {
        if (!menu.contains(event.target) && !toggle.contains(event.target)) {
            menu.classList.remove('active');
        }
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Set active menu item based on current page
    const currentPath = window.location.pathname;
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    menuLinks.forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href').split('/').pop())) {
            link.classList.add('active');
        }
    });
});

// Confirm delete actions
document.querySelectorAll('a[onclick*="confirm"]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
});

// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Password strength indicator
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const strengthColor = ['danger', 'warning', 'info', 'primary', 'success'];
        
        // You can add a strength indicator element here
    });
}

// Print functionality
function printPage() {
    window.print();
}

// Export to CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
