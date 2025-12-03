// Admin Panel JavaScript

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar toggle
    const toggleSidebar = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const adminBody = document.querySelector('.admin-body');
    
    if (toggleSidebar && sidebar) {
        toggleSidebar.addEventListener('click', function() {
            adminBody.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', adminBody.classList.contains('sidebar-collapsed'));
        });
        
        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            adminBody.classList.add('sidebar-collapsed');
        }
    }
    
    // Initialize tooltips
    initTooltips();
    
    // Initialize notifications
    initNotifications();
    
    // Initialize data tables
    initDataTables();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = this.title;
    tooltip.style.position = 'absolute';
    tooltip.style.background = '#1f2937';
    tooltip.style.color = 'white';
    tooltip.style.padding = '6px 10px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.fontSize = '12px';
    tooltip.style.zIndex = '9999';
    tooltip.style.maxWidth = '200px';
    
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    
    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
    }
}

// Notifications
function initNotifications() {
    // Check for new reservations every minute
    setInterval(checkNewReservations, 60000);
}

function checkNewReservations() {
    fetch('ajax/check-new-reservations.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                showNotification('New Reservations', `You have ${data.count} new reservation(s)`, 'reservations.php');
            }
        })
        .catch(error => console.error('Error:', error));
}

function showNotification(title, message, link = '#') {
    // Check if browser supports notifications
    if (!('Notification' in window)) {
        return;
    }
    
    // Check if permission is already granted
    if (Notification.permission === 'granted') {
        createNotification(title, message, link);
    }
    // Otherwise, ask for permission
    else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                createNotification(title, message, link);
            }
        });
    }
}

function createNotification(title, message, link) {
    const notification = new Notification(title, {
        body: message,
        icon: '/admin/assets/images/logo-icon.png'
    });
    
    notification.onclick = function() {
        window.focus();
        window.location.href = link;
        notification.close();
    };
    
    setTimeout(() => notification.close(), 5000);
}

// Data Tables
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        // Add sort functionality
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            if (header.querySelector('input') === null) { // Skip if header has input
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => sortTable(table, index));
            }
        });
        
        // Add search functionality
        const searchRow = document.createElement('tr');
        searchRow.className = 'search-row';
        headers.forEach(() => {
            const cell = document.createElement('td');
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = 'Search...';
            input.addEventListener('input', () => filterTable(table));
            cell.appendChild(input);
            searchRow.appendChild(cell);
        });
        
        table.querySelector('thead').appendChild(searchRow);
    });
}

function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isAsc = table.dataset.sortColumn === column.toString() && table.dataset.sortOrder === 'asc';
    
    rows.sort((a, b) => {
        const aVal = a.children[column].textContent.trim();
        const bVal = b.children[column].textContent.trim();
        
        // Try to parse as number
        const aNum = parseFloat(aVal.replace(/[^0-9.-]+/g, ''));
        const bNum = parseFloat(bVal.replace(/[^0-9.-]+/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAsc ? aNum - bNum : bNum - aNum;
        }
        
        // Otherwise sort as string
        return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    
    // Remove existing rows
    rows.forEach(row => row.remove());
    
    // Add sorted rows
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    table.querySelectorAll('th').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
    const header = table.querySelectorAll('th')[column];
    header.classList.add(isAsc ? 'sorted-desc' : 'sorted-asc');
    
    // Store sort state
    table.dataset.sortColumn = column;
    table.dataset.sortOrder = isAsc ? 'desc' : 'asc';
}

function filterTable(table) {
    const inputs = table.querySelectorAll('.search-row input');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        let showRow = true;
        Array.from(row.children).forEach((cell, index) => {
            if (inputs[index].value) {
                const searchText = inputs[index].value.toLowerCase();
                const cellText = cell.textContent.toLowerCase();
                if (!cellText.includes(searchText)) {
                    showRow = false;
                }
            }
        });
        row.style.display = showRow ? '' : 'none';
    });
}

// Modal System
const modalStack = [];

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        modalStack.push(modalId);
        document.body.style.overflow = 'hidden';
        
        // Focus on first input
        const input = modal.querySelector('input, select, textarea');
        if (input) input.focus();
    }
}

function hideModal(modalId = null) {
    if (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            modalStack.pop();
        }
    } else if (modalStack.length > 0) {
        const lastModalId = modalStack.pop();
        const modal = document.getElementById(lastModalId);
        if (modal) {
            modal.classList.remove('active');
        }
    }
    
    if (modalStack.length === 0) {
        document.body.style.overflow = '';
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modalStack.length > 0) {
        hideModal();
    }
});

// Close modal on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        hideModal(e.target.id);
    }
});

// Image Upload with Preview
function initImageUpload(inputId, previewId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please select a valid image file (JPEG, PNG, GIF, WebP).');
            this.value = '';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB.');
            this.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            let preview = document.getElementById(previewId);
            if (!preview) {
                preview = document.createElement('img');
                preview.id = previewId;
                preview.className = 'preview-image';
                input.parentNode.appendChild(preview);
            }
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
}

// Form Validation
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        field.classList.remove('error');
        
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        }
        
        // Email validation
        if (field.type === 'email' && field.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                field.classList.add('error');
                isValid = false;
            }
        }
        
        // URL validation
        if (field.type === 'url' && field.value) {
            try {
                new URL(field.value);
            } catch {
                field.classList.add('error');
                isValid = false;
            }
        }
        
        // Number validation
        if (field.type === 'number' && field.hasAttribute('min')) {
            const min = parseFloat(field.getAttribute('min'));
            if (parseFloat(field.value) < min) {
                field.classList.add('error');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

// Date and Time Helpers
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(date) {
    return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) return interval + ' year' + (interval === 1 ? '' : 's') + ' ago';
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) return interval + ' month' + (interval === 1 ? '' : 's') + ' ago';
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) return interval + ' day' + (interval === 1 ? '' : 's') + ' ago';
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval + ' hour' + (interval === 1 ? '' : 's') + ' ago';
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval + ' minute' + (interval === 1 ? '' : 's') + ' ago';
    
    return Math.floor(seconds) + ' second' + (seconds === 1 ? '' : 's') + ' ago';
}

// Export Data
function exportToCSV(data, filename) {
    const csvRows = [];
    const headers = Object.keys(data[0]);
    csvRows.push(headers.join(','));
    
    for (const row of data) {
        const values = headers.map(header => {
            const escaped = ('' + row[header]).replace(/"/g, '\\"');
            return `"${escaped}"`;
        });
        csvRows.push(values.join(','));
    }
    
    const csvString = csvRows.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename + '.csv');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Dashboard Charts
function initDashboardCharts() {
    // Reservations Chart
    const reservationsCtx = document.getElementById('reservationsChart');
    if (reservationsCtx) {
        const chart = new Chart(reservationsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Reservations',
                    data: [12, 19, 3, 5, 2, 3],
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        const chart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: ['Appetizers', 'Entrees', 'Desserts', 'Drinks'],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [1200, 4500, 800, 1500],
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#8b5cf6',
                        '#f59e0b'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
}

// Initialize charts when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboardCharts);
} else {
    initDashboardCharts();
}

// Real-time Updates (for reservations, etc.)
function initRealTimeUpdates() {
    // Use WebSocket or polling for real-time updates
    // This is a simplified version using polling
    setInterval(() => {
        fetch('ajax/updates.php')
            .then(response => response.json())
            .then(data => {
                // Update notification badge
                if (data.newReservations > 0) {
                    const badge = document.querySelector('.nav-badge');
                    if (badge) {
                        badge.textContent = data.newReservations;
                        badge.style.display = 'inline-block';
                    }
                }
                
                // Update dashboard stats
                if (data.stats) {
                    updateDashboardStats(data.stats);
                }
            })
            .catch(error => console.error('Error:', error));
    }, 30000); // Poll every 30 seconds
}

function updateDashboardStats(stats) {
    // Update stats cards
    document.querySelectorAll('.stat-card h3').forEach((card, index) => {
        switch(index) {
            case 0: card.textContent = stats.totalReservations; break;
            case 1: card.textContent = stats.pendingReservations; break;
            case 2: card.textContent = stats.totalMenuItems; break;
            case 3: card.textContent = stats.totalTestimonials; break;
        }
    });
}

// Initialize real-time updates
if (window.location.pathname.includes('index.php')) {
    initRealTimeUpdates();
}

// Export functions to global scope
window.showModal = showModal;
window.hideModal = hideModal;
window.validateForm = validateForm;
window.exportToCSV = exportToCSV;
