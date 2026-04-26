// ToolShare Hub — app.js
// Minimal vanilla JS: sidebar toggle + modal system + helpers

document.addEventListener('DOMContentLoaded', function () {

    // ── SIDEBAR TOGGLE (mobile) ───────────────────────────────
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebar-overlay');
    const hamburger = document.getElementById('hamburger');

    if (hamburger && sidebar && overlay) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    // ── MODAL SYSTEM ─────────────────────────────────────────
    // Open modal: data-modal-open="modalId"
    // Close modal: .modal-close button or clicking overlay
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) closeModal(modal.id);
        });
    });

    // Close modals with Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
        }
    });

    // ── AUTO-DISMISS FLASH MESSAGES ──────────────────────────
    const flash = document.querySelector('.flash-msg');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity .5s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 5000);
    }

    // ── CONFIRM DANGEROUS ACTIONS ────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    // ── FILTER/SEARCH ON TABLES ──────────────────────────────
    const searchInput = document.getElementById('table-search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.searchable-row').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ── STAR RATING PREVIEW (input) ───────────────────────────
    document.querySelectorAll('.star-input').forEach(container => {
        const stars  = container.querySelectorAll('label');
        const inputs = container.querySelectorAll('input[type="radio"]');
        // initial render handled by CSS :checked ~ label
    });

});

// ── GLOBAL MODAL FUNCTIONS ────────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

// Pre-populate a modal's hidden input and title text
function openRequestModal(toolId, toolName) {
    document.getElementById('req_tool_id').value   = toolId;
    document.getElementById('req_tool_name').textContent = toolName;
    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('req_requested_date').min   = today;
    document.getElementById('req_requested_date').value = today;
    openModal('modal-request');
}

function openReturnModal(loanId, toolName) {
    document.getElementById('ret_loan_id').value = loanId;
    document.getElementById('ret_tool_name').textContent = toolName;
    openModal('modal-return');
}

function openReviewModal(loanId, revieweeId, revieweeName, role) {
    document.getElementById('rev_loan_id').value    = loanId;
    document.getElementById('rev_reviewee_id').value = revieweeId;
    document.getElementById('rev_reviewee_name').textContent = revieweeName;
    document.getElementById('rev_role').textContent  = '(' + role + ')';
    openModal('modal-review');
}

function openDamageModal(loanId) {
    document.getElementById('dmg_loan_id').value = loanId;
    openModal('modal-damage');
}

function openEditToolModal(toolId, name, catId, cond, desc) {
    document.getElementById('edit_tool_id').value   = toolId;
    document.getElementById('edit_tool_name').value = name;
    document.getElementById('edit_cat_id').value    = catId;
    document.getElementById('edit_condition').value = cond;
    document.getElementById('edit_desc').value      = desc;
    openModal('modal-edit-tool');
}

// Toggle filter visibility
function toggleFilter() {
    const fb = document.getElementById('filter-block');
    if (fb) fb.classList.toggle('d-none');
}