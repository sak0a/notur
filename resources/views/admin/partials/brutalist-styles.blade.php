{{-- Notur Brutalist Admin Theme --}}
{{-- Scoped to body.notur-admin-page so it never leaks into panel chrome --}}
<script>document.body.classList.add('notur-admin-page');</script>
<style>
/* ═══════════════════════════════════════════════════════════════════
   NOTUR BRUTALIST ADMIN THEME
   Colors: #7c3aed (violet), #0a0a0b (void), #111 (surface)
   ═══════════════════════════════════════════════════════════════════ */

@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap');

:root {
    --nb-void: #0a0a0b;
    --nb-surface: #111113;
    --nb-surface-raised: #161618;
    --nb-surface-hover: #1a1a1d;
    --nb-border: #222225;
    --nb-border-strong: #333338;
    --nb-accent: #7c3aed;
    --nb-accent-dim: rgba(124, 58, 237, 0.15);
    --nb-accent-glow: rgba(124, 58, 237, 0.4);
    --nb-accent-light: #a78bfa;
    --nb-accent-bright: #c4b5fd;
    --nb-text: #e0e0e5;
    --nb-text-dim: #888890;
    --nb-text-muted: #55555a;
    --nb-success: #22c55e;
    --nb-success-dim: rgba(34, 197, 94, 0.12);
    --nb-warning: #eab308;
    --nb-warning-dim: rgba(234, 179, 8, 0.12);
    --nb-danger: #ef4444;
    --nb-danger-dim: rgba(239, 68, 68, 0.12);
    --nb-mono: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Cascadia Code', 'Consolas', monospace;
}

/* ── Page Shell ────────────────────────────────────────────────── */

body.notur-admin-page .content-wrapper {
    background: var(--nb-void) !important;
}

body.notur-admin-page .content-header {
    background: var(--nb-void);
    border-bottom: 1px solid var(--nb-border);
    padding: 28px 20px 18px;
    position: relative;
}

body.notur-admin-page .content-header > h1 {
    font-family: var(--nb-mono);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 800;
    color: var(--nb-text);
    font-size: 20px;
    margin: 0 0 4px;
}

body.notur-admin-page .content-header > h1 small {
    color: var(--nb-accent-light);
    font-family: var(--nb-mono);
    text-transform: lowercase;
    letter-spacing: 0.02em;
    font-weight: 400;
    font-size: 12px;
    margin-left: 12px;
    opacity: 0.7;
}

body.notur-admin-page .content-header .pull-right {
    position: absolute;
    top: 22px;
    right: 20px;
    margin-top: 0 !important;
    z-index: 2;
}

body.notur-admin-page .content-header .pull-right .btn {
    background: var(--nb-surface);
    border: 1px solid var(--nb-border);
    border-radius: 0;
    color: var(--nb-text-dim);
    font-family: var(--nb-mono);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    padding: 5px 10px;
    transition: all 0.15s;
}

body.notur-admin-page .content-header .pull-right .btn:hover {
    background: var(--nb-accent-dim);
    border-color: var(--nb-accent);
    color: var(--nb-accent-light);
}

/* ── Breadcrumb ────────────────────────────────────────────────── */

body.notur-admin-page .breadcrumb {
    background: transparent;
    padding: 12px 0 0;
    margin: 0;
}

body.notur-admin-page .breadcrumb > li {
    font-family: var(--nb-mono);
    font-size: 10px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

body.notur-admin-page .breadcrumb > li > a {
    color: var(--nb-accent);
}

body.notur-admin-page .breadcrumb > li > a:hover {
    color: var(--nb-accent-light);
}

body.notur-admin-page .breadcrumb > .active {
    color: var(--nb-text-muted);
}

body.notur-admin-page .breadcrumb > li + li::before {
    color: var(--nb-border-strong);
    content: '/';
    padding: 0 6px;
}

/* ── Content Area ──────────────────────────────────────────────── */

body.notur-admin-page .content {
    padding: 24px 20px;
}

/* ── Boxes (Cards) ─────────────────────────────────────────────── */

body.notur-admin-page .box {
    background: var(--nb-surface);
    border: 1px solid var(--nb-border);
    border-left: 3px solid var(--nb-accent);
    border-radius: 0;
    box-shadow: none;
    margin-bottom: 24px;
}

body.notur-admin-page .box-primary,
body.notur-admin-page .box-info,
body.notur-admin-page .box-success,
body.notur-admin-page .box-warning,
body.notur-admin-page .box-default {
    border-top-color: var(--nb-border);
}

body.notur-admin-page .box-primary { border-left-color: var(--nb-accent); }
body.notur-admin-page .box-info    { border-left-color: var(--nb-accent); }
body.notur-admin-page .box-success { border-left-color: var(--nb-accent); }
body.notur-admin-page .box-warning { border-left-color: var(--nb-warning); }
body.notur-admin-page .box-default { border-left-color: var(--nb-border-strong); }

body.notur-admin-page .box-header {
    background: var(--nb-void);
    border-bottom: 1px solid var(--nb-border);
    padding: 12px 16px;
    border-radius: 0;
}

body.notur-admin-page .box-header.with-border {
    border-bottom: 1px solid var(--nb-border);
}

body.notur-admin-page .box-header .box-title {
    font-family: var(--nb-mono);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    font-size: 11px;
    color: var(--nb-text-dim);
}

body.notur-admin-page .box-body {
    padding: 16px;
    color: var(--nb-text);
}

body.notur-admin-page .box-body.no-padding {
    padding: 0;
}

body.notur-admin-page .box-footer {
    background: var(--nb-void);
    border-top: 1px solid var(--nb-border);
    padding: 12px 16px;
    border-radius: 0;
}

body.notur-admin-page .box-body.table-responsive {
    padding: 0;
}

body.notur-admin-page .box-body.table-responsive.no-padding {
    padding: 0;
}

/* ── Box Tools ─────────────────────────────────────────────────── */

body.notur-admin-page .box-tools .btn-box-tool {
    color: var(--nb-text-muted);
}

body.notur-admin-page .box-tools .btn-box-tool:hover {
    color: var(--nb-accent);
}

body.notur-admin-page .box-tools .btn {
    background: var(--nb-surface-raised);
    border: 1px solid var(--nb-border);
    border-radius: 0;
    color: var(--nb-text-dim);
    font-family: var(--nb-mono);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
    margin-top: 4px;
}

body.notur-admin-page .box-tools .btn:hover {
    background: var(--nb-accent-dim);
    border-color: var(--nb-accent);
    color: var(--nb-accent-light);
}

/* ── Tables ────────────────────────────────────────────────────── */

body.notur-admin-page .table {
    background: transparent;
    color: var(--nb-text);
    margin-bottom: 0;
}

body.notur-admin-page .table > thead > tr > th {
    font-family: var(--nb-mono);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 9px;
    font-weight: 700;
    color: var(--nb-text-muted);
    border-bottom: 2px solid var(--nb-border-strong);
    padding: 10px 16px;
    background: rgba(0, 0, 0, 0.3);
    white-space: nowrap;
}

body.notur-admin-page .table > tbody > tr > td {
    border-top: 1px solid var(--nb-border);
    padding: 10px 16px;
    font-size: 13px;
    color: var(--nb-text);
    vertical-align: middle;
}

body.notur-admin-page .table > tbody > tr > td small {
    color: var(--nb-text-dim);
}

body.notur-admin-page .table-hover > tbody > tr:hover > td {
    background: var(--nb-accent-dim);
}

body.notur-admin-page .table-striped > tbody > tr:nth-of-type(odd) > td {
    background: rgba(255, 255, 255, 0.015);
}

/* Bootstrap's striped table styles often target <tr> directly; override that too. */
body.notur-admin-page .table-striped > tbody > tr:nth-of-type(odd),
body.notur-admin-page .table-striped > tbody > tr:nth-of-type(even),
body.notur-admin-page .table-striped > tbody > tr:nth-of-type(odd) > th,
body.notur-admin-page .table-striped > tbody > tr:nth-of-type(even) > th,
body.notur-admin-page .table-striped > tbody > tr:nth-of-type(even) > td {
    background: transparent !important;
}

body.notur-admin-page .table-striped > tbody > tr:nth-of-type(odd) > td,
body.notur-admin-page .table-striped > tbody > tr:nth-of-type(odd) > th {
    background: rgba(255, 255, 255, 0.015) !important;
}

body.notur-admin-page .table-striped > tbody > tr:hover > td {
    background: var(--nb-accent-dim);
}

/* ── Buttons ───────────────────────────────────────────────────── */

body.notur-admin-page .btn {
    border-radius: 0;
    font-family: var(--nb-mono);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid transparent;
    box-shadow: none !important;
    transition: all 0.12s ease;
    outline: none !important;
}

body.notur-admin-page .btn:active {
    transform: translateY(1px);
}

body.notur-admin-page .btn-primary {
    background: var(--nb-accent);
    border-color: var(--nb-accent);
    color: #fff;
}

body.notur-admin-page .btn-primary:hover,
body.notur-admin-page .btn-primary:focus {
    background: #6d28d9;
    border-color: #6d28d9;
    color: #fff;
}

body.notur-admin-page .btn-success {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
}

body.notur-admin-page .btn-success:hover,
body.notur-admin-page .btn-success:focus {
    background: #15803d;
    border-color: #15803d;
    color: #fff;
}

body.notur-admin-page .btn-warning {
    background: #a16207;
    border-color: #a16207;
    color: #fff;
}

body.notur-admin-page .btn-warning:hover,
body.notur-admin-page .btn-warning:focus {
    background: #854d0e;
    border-color: #854d0e;
    color: #fff;
}

body.notur-admin-page .btn-danger {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
}

body.notur-admin-page .btn-danger:hover,
body.notur-admin-page .btn-danger:focus {
    background: #b91c1c;
    border-color: #b91c1c;
    color: #fff;
}

body.notur-admin-page .btn-default {
    background: var(--nb-surface-raised);
    border-color: var(--nb-border-strong);
    color: var(--nb-text-dim);
}

body.notur-admin-page .btn-default:hover,
body.notur-admin-page .btn-default:focus {
    background: var(--nb-surface-hover);
    border-color: var(--nb-accent);
    color: var(--nb-accent-light);
}

body.notur-admin-page .btn-info {
    background: var(--nb-accent);
    border-color: var(--nb-accent);
    color: #fff;
}

body.notur-admin-page .btn-info:hover,
body.notur-admin-page .btn-info:focus {
    background: #6d28d9;
    border-color: #6d28d9;
    color: #fff;
}

/* ── Forms ──────────────────────────────────────────────────────── */

body.notur-admin-page .form-control {
    background: var(--nb-void);
    border: 1px solid var(--nb-border-strong);
    border-radius: 0;
    color: var(--nb-text);
    box-shadow: none;
    font-family: var(--nb-mono);
    font-size: 13px;
    height: 38px;
    transition: border-color 0.15s;
}

body.notur-admin-page textarea.form-control {
    height: auto;
}

body.notur-admin-page .form-control:focus {
    border-color: var(--nb-accent);
    box-shadow: 0 0 0 1px var(--nb-accent-glow);
    background: var(--nb-void);
    color: var(--nb-text);
}

body.notur-admin-page .form-control::placeholder {
    color: var(--nb-text-muted);
}

body.notur-admin-page label {
    color: var(--nb-text-dim);
    font-family: var(--nb-mono);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 10px;
    font-weight: 600;
    margin-bottom: 6px;
}

body.notur-admin-page .help-block {
    color: var(--nb-text-muted);
    font-family: var(--nb-mono);
    font-size: 10px;
    letter-spacing: 0.02em;
}

body.notur-admin-page .checkbox label {
    color: var(--nb-text);
    font-family: inherit;
    text-transform: none;
    letter-spacing: normal;
    font-size: 13px;
}

body.notur-admin-page select.form-control {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888890' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
}

/* ── Alerts ─────────────────────────────────────────────────────── */

body.notur-admin-page .alert {
    border-radius: 0;
    border: none;
    border-left: 3px solid;
    font-family: var(--nb-mono);
    font-size: 12px;
    padding: 12px 16px;
}

body.notur-admin-page .alert-success {
    background: var(--nb-success-dim);
    border-left-color: var(--nb-success);
    color: var(--nb-success);
}

body.notur-admin-page .alert-danger {
    background: var(--nb-danger-dim);
    border-left-color: var(--nb-danger);
    color: var(--nb-danger);
}

body.notur-admin-page .alert-info {
    background: var(--nb-accent-dim);
    border-left-color: var(--nb-accent);
    color: var(--nb-accent-light);
}

body.notur-admin-page .alert-warning {
    background: var(--nb-warning-dim);
    border-left-color: var(--nb-warning);
    color: var(--nb-warning);
}

body.notur-admin-page .alert .close {
    color: var(--nb-text-dim);
    opacity: 0.6;
    text-shadow: none;
}

body.notur-admin-page .alert .close:hover {
    color: var(--nb-text);
    opacity: 1;
}

/* ── Callouts ──────────────────────────────────────────────────── */

body.notur-admin-page .callout {
    border-radius: 0;
    background: var(--nb-surface-raised);
    border: 1px solid var(--nb-border);
    border-left: 3px solid var(--nb-accent);
    color: var(--nb-text);
    font-family: var(--nb-mono);
    font-size: 12px;
}

body.notur-admin-page .callout-info {
    background: var(--nb-accent-dim);
    border-left-color: var(--nb-accent);
    color: var(--nb-accent-light);
}

body.notur-admin-page .callout-danger {
    border-left-color: var(--nb-danger);
}

body.notur-admin-page .callout-warning {
    border-left-color: var(--nb-warning);
}

body.notur-admin-page .callout-success {
    border-left-color: var(--nb-success);
}

/* ── Labels / Badges ───────────────────────────────────────────── */

body.notur-admin-page .label {
    border-radius: 0;
    font-family: var(--nb-mono);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    font-size: 9px;
    padding: 3px 7px 2px;
    display: inline-block;
}

body.notur-admin-page .label-success {
    background: rgba(34, 197, 94, 0.2);
    color: var(--nb-success);
}

body.notur-admin-page .label-default {
    background: var(--nb-border);
    color: var(--nb-text);
}

body.notur-admin-page .table .label-default {
    background: var(--nb-border) !important;
    color: var(--nb-text) !important;
}

body.notur-admin-page .label-info {
    background: var(--nb-accent-dim);
    color: var(--nb-accent-light);
}

body.notur-admin-page .label-warning {
    background: var(--nb-warning-dim);
    color: var(--nb-warning);
}

body.notur-admin-page .label-danger {
    background: var(--nb-danger-dim);
    color: var(--nb-danger);
}

body.notur-admin-page .label-primary {
    background: var(--nb-accent);
    color: #fff;
}

/* ── Code ──────────────────────────────────────────────────────── */

body.notur-admin-page code {
    background: var(--nb-accent-dim);
    color: var(--nb-accent-light);
    border-radius: 0;
    padding: 1px 6px;
    font-size: 12px;
    font-family: var(--nb-mono);
    border: 1px solid rgba(124, 58, 237, 0.15);
}

body.notur-admin-page pre {
    background: var(--nb-void);
    border: 1px solid var(--nb-border);
    border-radius: 0;
    color: var(--nb-text);
    font-family: var(--nb-mono);
    font-size: 12px;
    padding: 16px;
}

/* ── Links ─────────────────────────────────────────────────────── */

body.notur-admin-page .box a:not(.btn) {
    color: var(--nb-accent-light);
    text-decoration: none;
    transition: color 0.12s;
}

body.notur-admin-page .box a:not(.btn):hover {
    color: var(--nb-accent-bright);
}

/* ── Text Overrides ────────────────────────────────────────────── */

body.notur-admin-page .text-muted {
    color: var(--nb-text-muted) !important;
}

body.notur-admin-page p.text-muted {
    font-family: var(--nb-mono);
    font-size: 12px;
}

body.notur-admin-page .text-green {
    color: var(--nb-success) !important;
}

/* ── List Groups ───────────────────────────────────────────────── */

body.notur-admin-page .list-group-item {
    background: var(--nb-surface-raised);
    border: 1px solid var(--nb-border);
    border-radius: 0;
    color: var(--nb-text);
    font-family: var(--nb-mono);
    font-size: 12px;
    padding: 10px 14px;
}

body.notur-admin-page .list-group-item + .list-group-item {
    border-top: 1px solid var(--nb-border);
}

body.notur-admin-page .list-group-item strong {
    color: var(--nb-text);
}

/* ── has-error state ───────────────────────────────────────────── */

body.notur-admin-page .has-error .form-control {
    border-color: var(--nb-danger);
}

body.notur-admin-page .has-error .help-block {
    color: var(--nb-danger);
}

/* ── Status indicator dots ─────────────────────────────────────── */

body.notur-admin-page .nb-status {
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-right: 6px;
    vertical-align: middle;
}

body.notur-admin-page .nb-status--active {
    background: var(--nb-success);
    box-shadow: 0 0 6px var(--nb-success);
}

body.notur-admin-page .nb-status--inactive {
    background: var(--nb-text-muted);
}

/* ── Notur Branding Bar ────────────────────────────────────────── */

body.notur-admin-page .nb-brand-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0 0;
    margin-top: 16px;
    border-top: 1px solid var(--nb-border);
}

body.notur-admin-page .nb-brand-bar__logo {
    width: 18px;
    height: 18px;
    background: var(--nb-accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--nb-mono);
    font-weight: 800;
    font-size: 10px;
    color: #fff;
    flex-shrink: 0;
}

body.notur-admin-page .nb-brand-bar__text {
    font-family: var(--nb-mono);
    font-size: 10px;
    color: var(--nb-text-muted);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

/* ── Transition overrides ──────────────────────────────────────── */

body.notur-admin-page .box,
body.notur-admin-page .btn,
body.notur-admin-page .form-control,
body.notur-admin-page .label {
    transition: all 0.12s ease;
}

/* ── Responsive fixes ──────────────────────────────────────────── */

@media (max-width: 768px) {
    body.notur-admin-page .content-header {
        padding: 16px 12px 12px;
    }

    body.notur-admin-page .content {
        padding: 16px 12px;
    }

    body.notur-admin-page .content-header > h1 {
        font-size: 16px;
    }

    body.notur-admin-page .content-header .pull-right {
        position: static;
        margin-top: 8px !important;
        float: none !important;
    }
}
</style>

<script>
    (function () {
        const buttons = document.querySelectorAll('.btn-box-tool[data-widget="collapse"]');
        if (!buttons.length) {
            return;
        }

        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                const box = button.closest('.box');
                if (!box) {
                    return;
                }

                const isCollapsed = box.classList.toggle('nb-collapsed');
                const body = box.querySelector('.box-body');
                const footer = box.querySelector('.box-footer');
                const icon = button.querySelector('i');

                [body, footer].forEach((section) => {
                    if (!section) {
                        return;
                    }
                    section.style.display = isCollapsed ? 'none' : '';
                });

                if (icon) {
                    icon.classList.toggle('fa-plus', isCollapsed);
                    icon.classList.toggle('fa-minus', !isCollapsed);
                }
            });
        });
    })();
</script>
