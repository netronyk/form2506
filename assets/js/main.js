// assets/js/main.js - JavaScript עיקרי

// פונקציות כלליות
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// טיפול בטפסים
function handleFormSubmission(formId, onSuccess = null) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'שולח...';
        
        // החזרת הכפתור למצב רגיל אחרי 5 שניות
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }, 5000);
    });
}

// אימות תמונות
function validateImages(input, maxFiles = 10, maxSize = 5 * 1024 * 1024) {
    if (input.files.length > maxFiles) {
        showAlert(`ניתן להעלות עד ${maxFiles} תמונות`, 'warning');
        input.value = '';
        return false;
    }
    
    for (let file of input.files) {
        if (file.size > maxSize) {
            showAlert(`גודל התמונה חייב להיות קטן מ-5MB`, 'warning');
            input.value = '';
            return false;
        }
        
        if (!file.type.startsWith('image/')) {
            showAlert('ניתן להעלות רק קבצי תמונה', 'warning');
            input.value = '';
            return false;
        }
    }
    
    return true;
}

// טעינת תמונות עם תצוגה מקדימה
function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (!input || !preview) return;
    
    input.addEventListener('change', function() {
        if (!validateImages(this)) return;
        
        preview.innerHTML = '';
        
        Array.from(this.files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.style.cssText = 'display: inline-block; margin: 10px; position: relative;';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;';
                
                const removeBtn = document.createElement('button');
                removeBtn.textContent = '×';
                removeBtn.style.cssText = 'position: absolute; top: -5px; left: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer;';
                removeBtn.onclick = () => div.remove();
                
                div.appendChild(img);
                div.appendChild(removeBtn);
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });
}

// הגדרת טווח תאריכים
function setupDateRange(startId, endId) {
    const startDate = document.getElementById(startId);
    const endDate = document.getElementById(endId);
    
    if (!startDate || !endDate) return;
    
    const today = new Date().toISOString().split('T')[0];
    startDate.min = today;
    
    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });
}

// חיפוש בטבלה
function setupTableSearch(searchId, tableId) {
    const searchInput = document.getElementById(searchId);
    const table = document.getElementById(tableId);
    
    if (!searchInput || !table) return;
    
    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

// סינון לפי סטטוס
function setupStatusFilter(filterId, tableId, statusColumnIndex = 2) {
    const filter = document.getElementById(filterId);
    const table = document.getElementById(tableId);
    
    if (!filter || !table) return;
    
    filter.addEventListener('change', function() {
        const selectedStatus = this.value;
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            if (!selectedStatus) {
                row.style.display = '';
                return;
            }
            
            const statusCell = row.cells[statusColumnIndex];
            const statusText = statusCell ? statusCell.textContent.trim() : '';
            
            row.style.display = statusText.includes(selectedStatus) ? '' : 'none';
        });
    });
}

// אישור מחיקה
function confirmDelete(message = 'אתה בטוח שברצונך למחוק?') {
    return confirm(message);
}

// העתקה ללוח
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('הועתק ללוח', 'success');
    }).catch(() => {
        showAlert('שגיאה בהעתקה', 'danger');
    });
}

// פורמט מחיר
function formatPrice(amount) {
    return new Intl.NumberFormat('he-IL', {
        style: 'currency',
        currency: 'ILS'
    }).format(amount);
}

// פורמט תאריך
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('he-IL');
}

// פורמט זמן יחסי
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 60) return `לפני ${minutes} דקות`;
    if (hours < 24) return `לפני ${hours} שעות`;
    return `לפני ${days} ימים`;
}

// AJAX helpers
function apiRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    return fetch(url, { ...defaultOptions, ...options })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

// טעינת דירוגים בכוכבים
function renderStars(rating, container) {
    const stars = Math.round(rating);
    let html = '';
    
    for (let i = 1; i <= 5; i++) {
        html += `<span class="star ${i <= stars ? '' : 'empty'}">★</span>`;
    }
    
    if (container) {
        container.innerHTML = html;
    }
    
    return html;
}

// auto-refresh לעמודים
function setupAutoRefresh(interval = 60000) {
    const refreshBtn = document.createElement('button');
    refreshBtn.textContent = '🔄';
    refreshBtn.className = 'btn btn-outline';
    refreshBtn.style.cssText = 'position: fixed; bottom: 20px; left: 20px; z-index: 1000; border-radius: 50%; width: 50px; height: 50px;';
    refreshBtn.title = 'רענן דף';
    
    refreshBtn.onclick = () => location.reload();
    document.body.appendChild(refreshBtn);
    
    // רענון אוטומטי כל דקה
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            const tables = document.querySelectorAll('.table tbody');
            if (tables.length > 0) {
                // רק אם יש טבלאות בדף
                location.reload();
            }
        }
    }, interval);
}

// Responsive navigation
function setupMobileNav() {
    const nav = document.querySelector('.nav-links');
    if (!nav) return;
    
    const toggleBtn = document.createElement('button');
    toggleBtn.innerHTML = '☰';
    toggleBtn.className = 'mobile-nav-toggle';
    toggleBtn.style.cssText = 'display: none; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;';
    
    const navbar = document.querySelector('.navbar .container');
    navbar.appendChild(toggleBtn);
    
    toggleBtn.addEventListener('click', () => {
        nav.classList.toggle('mobile-active');
    });
    
    // CSS ל-mobile
    const style = document.createElement('style');
    style.textContent = `
        @media (max-width: 768px) {
            .mobile-nav-toggle { display: block !important; }
            .nav-links { 
                display: none; 
                position: absolute; 
                top: 100%; 
                right: 0; 
                background: var(--secondary-color); 
                width: 100%; 
                flex-direction: column; 
                padding: 1rem; 
            }
            .nav-links.mobile-active { display: flex !important; }
            .nav-links li { margin: 0.5rem 0; }
        }
    `;
    document.head.appendChild(style);
}

// אתחול כללי
document.addEventListener('DOMContentLoaded', function() {
    // הגדרת פונקציות בסיסיות
    setupMobileNav();
    
    // הגדרת אימות תמונות לכל input file
    document.querySelectorAll('input[type="file"][accept*="image"]').forEach(input => {
        input.addEventListener('change', function() {
            validateImages(this);
        });
    });
    
    // הגדרת חיפוש בטבלאות
    const searchInputs = document.querySelectorAll('input[data-table-search]');
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-table-search');
        setupTableSearch(input.id, tableId);
    });
    
    // הגדרת תאריכים
    setupDateRange('work_start_date', 'work_end_date');
    
    // הגדרת auto-refresh לדפי ניהול
    if (window.location.pathname.includes('/admin/') || 
        window.location.pathname.includes('/vehicle-owner/') || 
        window.location.pathname.includes('/customer/')) {
        setupAutoRefresh(300000); // 5 דקות
    }
    
    // הסתרת הודעות אחרי זמן
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
});

// Export functions for global use
window.nahagimApp = {
    showAlert,
    validateImages,
    setupImagePreview,
    setupDateRange,
    setupTableSearch,
    confirmDelete,
    copyToClipboard,
    formatPrice,
    formatDate,
    timeAgo,
    apiRequest,
    renderStars
};