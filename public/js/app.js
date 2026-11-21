/* SuperShop admin helpers */
const modalFocusReturn = new WeakMap();

window.SS = {
    openModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        modalFocusReturn.set(m, document.activeElement);
        m.style.display = 'grid';
        const focusTarget = m.querySelector('[data-modal-autofocus]') || m.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (focusTarget) focusTarget.focus();
    },
    closeModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.style.display = 'none';
        const focusTarget = modalFocusReturn.get(m);
        if (focusTarget && focusTarget.isConnected) focusTarget.focus();
    },
    // Populate a modal's fields from data-* attributes on the trigger, then open it.
    edit(id, data) {
        const m = document.getElementById(id);
        if (!m) return;
        Object.entries(data).forEach(([k, v]) => {
            const f = m.querySelector('[name="' + k + '"]');
            if (!f) return;
            if (f.type === 'checkbox') f.checked = !!(+v);
            else f.value = v;
        });
        this.openModal(id);
    },
    confirmSubmit(form, msg) {
        if (form && confirm(msg || document.documentElement.dataset.confirm || 'Are you sure?')) form.submit();
        return false;
    },
};

// Close modals on overlay click or Escape, and keep keyboard focus inside the active dialog.
document.addEventListener('click', (e) => {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) window.SS.closeModal(e.target.id);
});
document.addEventListener('keydown', (e) => {
    const modals = Array.from(document.querySelectorAll('.modal-overlay')).filter(m => m.style.display !== 'none');
    const modal = modals[modals.length - 1];
    if (!modal) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        window.SS.closeModal(modal.id);
        return;
    }

    if (e.key === 'Tab') {
        const focusable = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
});

document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-password-toggle]');
    if (toggle) {
        const input = document.getElementById(toggle.dataset.passwordToggle);
        if (!input) return;
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        toggle.textContent = showPassword ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
        toggle.setAttribute('aria-label', toggle.textContent);
    }
});

const strengthInput = document.querySelector('[data-password-strength-input]');
const strengthMeter = document.querySelector('[data-password-strength-meter]');
if (strengthInput && strengthMeter) {
    const strengthLabel = document.querySelector('[data-password-strength-label]');
    const labels = strengthMeter.dataset.labels.split('|');
    strengthInput.addEventListener('input', () => {
        const password = strengthInput.value;
        const score = [
            password.length >= 8,
            password.length >= 12,
            /[a-z]/.test(password) && /[A-Z]/.test(password),
            /\d/.test(password),
            /[^A-Za-z0-9]/.test(password),
        ].filter(Boolean).length;
        const level = password.length ? Math.max(1, Math.min(4, score)) : 0;
        strengthMeter.style.width = `${level * 25}%`;
        strengthMeter.dataset.level = level;
        if (strengthLabel) strengthLabel.textContent = labels[level];
    });
}

// Auto-dismiss flash alerts
setTimeout(() => {
    document.querySelectorAll('.content > .alert').forEach(a => {
        a.style.transition = 'opacity .4s, transform .4s';
        a.style.opacity = '0'; a.style.transform = 'translateY(-6px)';
        setTimeout(() => a.remove(), 420);
    });
}, 4200);
