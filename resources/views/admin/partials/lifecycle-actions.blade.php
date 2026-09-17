{{-- Shared submission feedback for synchronous extension lifecycle actions. --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var forms = Array.from(document.querySelectorAll('form[method="POST"]')).filter(function (form) {
        return new URL(form.action, window.location.href).pathname.indexOf('/admin/notur/') === 0;
    });
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (form.dataset.pending === 'true') {
                event.preventDefault();
                return;
            }
            var confirmation = form.getAttribute('data-confirm');
            var keepData = form.querySelector('[name="keep_data"]');
            if (keepData && keepData.checked) confirmation = form.getAttribute('data-confirm-keep');
            if (confirmation && !window.confirm(confirmation)) {
                event.preventDefault();
                return;
            }
            form.dataset.pending = 'true';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
            var status = document.createElement('span');
            status.className = 'help-block notur-action-status';
            status.setAttribute('role', 'status');
            status.textContent = form.getAttribute('data-progress') || 'Applying changes… Please keep this page open.';
            form.appendChild(status);
        });
    });
    window.addEventListener('pageshow', function () {
        forms.forEach(function (form) {
            if (form.dataset.pending !== 'true') return;
            delete form.dataset.pending;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button[type="submit"]').forEach(function (button) { button.disabled = false; });
            form.querySelectorAll('.notur-action-status').forEach(function (status) { status.remove(); });
        });
    });
});
</script>
