/* SuperShop admin helpers */
window.SS = {
    openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'grid'; },
    closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; },
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
        m.style.display = 'grid';
    },
    confirmSubmit(form, msg) {
        if (form && confirm(msg || document.documentElement.dataset.confirm || 'Are you sure?')) form.submit();
        return false;
    },
};

// Close modals on overlay click or Escape
document.addEventListener('click', (e) => {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) e.target.style.display = 'none';
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
});

// Auto-dismiss flash alerts
setTimeout(() => {
    document.querySelectorAll('.content > .alert').forEach(a => {
        a.style.transition = 'opacity .4s, transform .4s';
        a.style.opacity = '0'; a.style.transform = 'translateY(-6px)';
        setTimeout(() => a.remove(), 420);
    });
}, 4200);
