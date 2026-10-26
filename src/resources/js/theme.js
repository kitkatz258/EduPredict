const STORAGE_KEY = 'edupredict.theme';

function savedTheme() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        if (value === 'light' || value === 'dark') {
            return value;
        }
    } catch {
        // Private mode can block localStorage.
    }

    return null;
}

export function currentTheme() {
    const saved = savedTheme();
    if (saved) {
        return saved;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function isDark() {
    return document.documentElement.classList.contains('dark');
}

export function chartColors() {
    const styles = getComputedStyle(document.documentElement);

    return {
        ink: (styles.getPropertyValue('--ed-muted').trim() || '#6b7280'),
        grid: isDark() ? 'rgba(255,255,255,0.08)' : '#eef0f3',
        line: (styles.getPropertyValue('--ed-line').trim() || '#e8eaee'),
        tooltipBg: (styles.getPropertyValue('--ed-surface').trim() || '#ffffff'),
        tooltipInk: (styles.getPropertyValue('--ed-ink').trim() || '#111827'),
        indigo: '#818cf8',
    };
}

function applyChartColors(chart, colors) {
    const scales = chart.options?.scales || {};
    Object.values(scales).forEach((scale) => {
        if (!scale) {
            return;
        }
        scale.ticks = { ...(scale.ticks || {}), color: colors.ink };
        if (scale.grid && scale.grid.display === false) {
            return;
        }
        scale.grid = { ...(scale.grid || {}), color: colors.grid };
    });

    if (chart.options?.plugins?.legend?.labels) {
        chart.options.plugins.legend.labels.color = colors.ink;
    }

    const tooltip = chart.options?.plugins?.tooltip;
    if (tooltip) {
        tooltip.backgroundColor = colors.tooltipBg;
        tooltip.titleColor = colors.tooltipInk;
        tooltip.bodyColor = colors.tooltipInk;
        tooltip.borderColor = colors.line;
        tooltip.borderWidth = 1;
    }
}

export function paintCharts() {
    if (!window.Chart) {
        return;
    }

    const colors = chartColors();
    if (window.Chart.defaults) {
        window.Chart.defaults.color = colors.ink;
        window.Chart.defaults.borderColor = colors.grid;
    }

    document.querySelectorAll('canvas').forEach((canvas) => {
        const chart = window.Chart.getChart?.(canvas);
        if (!chart) {
            return;
        }
        applyChartColors(chart, colors);
        chart.update('none');
    });
}

export function applyTheme(theme, persist = true) {
    const dark = theme === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    if (persist) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch {
            // Ignore quota / private-mode failures.
        }
    }
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        const next = dark ? 'Switch to light mode' : 'Switch to dark mode';
        button.setAttribute('aria-label', next);
        button.setAttribute('title', next);
    });
    document.dispatchEvent(new CustomEvent('edupredict-theme', { detail: { theme: dark ? 'dark' : 'light' } }));
    paintCharts();
}

export function toggleTheme() {
    applyTheme(isDark() ? 'light' : 'dark');
}

export function installTheme() {
    window.edupredictTheme = {
        current: currentTheme,
        apply: applyTheme,
        toggle: toggleTheme,
        isDark,
        colors: chartColors,
        paintCharts,
    };

    applyTheme(currentTheme(), false);

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        if (savedTheme()) {
            return;
        }
        applyTheme(event.matches ? 'dark' : 'light', false);
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-theme-toggle]');
        if (!button) {
            return;
        }
        event.preventDefault();
        toggleTheme();
    });
}
