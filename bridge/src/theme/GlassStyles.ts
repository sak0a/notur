const GLASS_STYLE_ID = 'notur-glass-styles';

const DEFAULT_GLASS_CSS = `
:root {
  --notur-surface-padding: 16px;
  --notur-surface-gap: 12px;
}

.notur-surface {
  background: linear-gradient(135deg, var(--notur-glass-highlight), rgba(255, 255, 255, 0) 65%), var(--notur-glass-bg);
  border: 1px solid var(--notur-glass-border);
  border-radius: var(--notur-radius-lg);
  box-shadow: var(--notur-glass-shadow);
  backdrop-filter: blur(var(--notur-glass-blur));
  -webkit-backdrop-filter: blur(var(--notur-glass-blur));
  color: var(--notur-text-primary);
}

.notur-surface--card {
  padding: var(--notur-surface-padding);
}

.notur-surface--page {
  padding: clamp(16px, 2.4vw, 28px);
}

#notur-slot-dashboard\\.widgets > .notur-surface {
  margin-bottom: var(--notur-surface-gap);
}

#notur-slot-dashboard\\.widgets > .notur-surface:last-child {
  margin-bottom: 0;
}
`;

export function ensureGlassStyles(): void {
    if (typeof document === 'undefined') return;
    if (document.getElementById(GLASS_STYLE_ID)) return;

    const style = document.createElement('style');
    style.id = GLASS_STYLE_ID;
    style.textContent = DEFAULT_GLASS_CSS;
    document.head.appendChild(style);
}
