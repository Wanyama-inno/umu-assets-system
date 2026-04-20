// =====================================================
// UMU Assets Management System - Main JavaScript
// =====================================================

// Modal Functions
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => {
            m.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

// Sidebar Toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
}

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.5s ease, margin 0.5s ease, padding 0.5s ease, height 0.5s ease';
        alert.style.opacity = '0';
        alert.style.marginBottom = '0';
        alert.style.padding = '0';
        setTimeout(() => alert.remove(), 500);
    }, 5000);
});

// Confirm delete forms
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});

// Asset type change — update status options
const assetTypeSelect = document.querySelector('[name="asset_type"]');
const statusSelect = document.querySelector('[name="status"]');
if (assetTypeSelect && statusSelect) {
    assetTypeSelect.addEventListener('change', () => {
        if (assetTypeSelect.value === 'non_borrowable') {
            // Remove 'available' for non-borrowable
            for (let opt of statusSelect.options) {
                if (opt.value === 'available') opt.style.display = 'none';
            }
            if (statusSelect.value === 'available') statusSelect.value = 'in_use';
        } else {
            for (let opt of statusSelect.options) opt.style.display = '';
        }
    });
}

// Live search filter for tables
const searchInputs = document.querySelectorAll('[data-search-table]');
searchInputs.forEach(input => {
    const tableId = input.dataset.searchTable;
    const table = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

// Animate stat numbers on page load
function animateCounter(el) {
    const target = parseInt(el.textContent);
    if (isNaN(target) || target === 0) return;
    let current = 0;
    const increment = Math.ceil(target / 30);
    const timer = setInterval(() => {
        current = Math.min(current + increment, target);
        el.textContent = current;
        if (current >= target) clearInterval(timer);
    }, 30);
}

document.querySelectorAll('.stat-body h3').forEach(el => {
    animateCounter(el);
});

console.log('UMU Assets Management System v1.0 — Masaka Campus');
