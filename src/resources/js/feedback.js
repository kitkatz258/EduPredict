import Swal from 'sweetalert2';

const brand = '#1B5E20';

function motionOptions() {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
        return {
            showClass: { popup: 'swal2-noanimation', backdrop: 'swal2-noanimation', icon: 'swal2-noanimation' },
            hideClass: { popup: 'swal2-noanimation', backdrop: 'swal2-noanimation', icon: 'swal2-noanimation' },
        };
    }

    return {
        showClass: { popup: 'ed-pop-in', backdrop: 'ed-pop-in' },
        hideClass: { popup: 'ed-pop-out', backdrop: 'ed-pop-out' },
    };
}

const toastMixin = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (popup) => {
        popup.addEventListener('mouseenter', Swal.stopTimer);
        popup.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

const toastIcons = ['success', 'error', 'warning', 'info'];

export function toast(type, message) {
    if (!message) {
        return;
    }

    toastMixin.fire({
        icon: toastIcons.includes(type) ? type : 'info',
        title: String(message),
        ...motionOptions(),
    });
}

export function confirmAction({ title = 'Are you sure?', text = '', confirmText = 'Continue', danger = true } = {}) {
    return Swal.fire({
        title,
        text,
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        confirmButtonColor: danger ? '#B91C1C' : brand,
        cancelButtonColor: '#6B7280',
        reverseButtons: true,
        focusCancel: danger,
        ...motionOptions(),
    }).then((result) => result.isConfirmed);
}

/* Top progress bar for page navigation and slow Livewire requests. */
const progress = (() => {
    let bar = null;
    let timer = null;
    let active = 0;

    const ensure = () => {
        if (bar === null) {
            bar = document.createElement('div');
            bar.id = 'page-progress';
            bar.setAttribute('aria-hidden', 'true');
            document.body.appendChild(bar);
        }

        return bar;
    };

    return {
        start(delay = 0) {
            active += 1;
            clearTimeout(timer);
            timer = setTimeout(() => {
                const el = ensure();
                el.style.transition = 'none';
                el.style.opacity = '1';
                el.style.width = '0%';
                requestAnimationFrame(() => {
                    el.style.transition = 'width 8s cubic-bezier(.1,.7,.2,1), opacity .3s';
                    el.style.width = '85%';
                });
            }, delay);
        },
        done() {
            active = Math.max(0, active - 1);
            if (active > 0) {
                return;
            }
            clearTimeout(timer);
            if (bar === null) {
                return;
            }
            bar.style.transition = 'width .2s ease-out, opacity .4s .2s';
            bar.style.width = '100%';
            bar.style.opacity = '0';
        },
        reset() {
            active = 0;
            clearTimeout(timer);
            if (bar !== null) {
                bar.style.transition = 'none';
                bar.style.width = '0%';
                bar.style.opacity = '0';
            }
        },
    };
})();

const isLivewireForm = (form) => [...form.attributes].some((attr) => attr.name.startsWith('wire:submit'));

const isPlainNavigation = (event, link) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }
    if (link.target && link.target !== '_self') {
        return false;
    }
    if (link.hasAttribute('download') || link.dataset.noProgress !== undefined) {
        return false;
    }
    const href = link.getAttribute('href') || '';
    if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) {
        return false;
    }

    try {
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) {
            return false;
        }

        return !(url.pathname === window.location.pathname && url.search === window.location.search && url.hash !== '');
    } catch {
        return false;
    }
};

function markBusy(form, submitter) {
    form.dataset.busy = '1';
    form.setAttribute('aria-busy', 'true');
    const buttons = submitter ? [submitter] : [...form.querySelectorAll('button[type="submit"], button:not([type])')];
    // Disable after the browser has captured the submitter's name/value.
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
        buttons.forEach((button) => button.setAttribute('aria-busy', 'true'));
    }, 0);
}

function lockControl(control) {
    if (!control || control.dataset.locked === '1' || control.disabled) {
        return;
    }
    control.dataset.locked = '1';
    control.setAttribute('aria-busy', 'true');
    setTimeout(() => {
        if (control.dataset.locked === '1') {
            control.disabled = true;
        }
    }, 0);
}

function releaseLocks() {
    document.querySelectorAll('[data-locked]').forEach((el) => {
        delete el.dataset.locked;
        el.removeAttribute('aria-busy');
        el.disabled = false;
    });
    clearBusy();
}

function clearBusy() {
    document.querySelectorAll('form[data-busy]').forEach((form) => {
        delete form.dataset.busy;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[aria-busy="true"]').forEach((el) => {
            if (el.dataset.locked !== '1') {
                el.removeAttribute('aria-busy');
            }
        });
        form.querySelectorAll('button[disabled][type="submit"], button[disabled]:not([type])').forEach((button) => {
            if (button.dataset.locked !== '1') {
                button.disabled = false;
            }
        });
    });
}

export function installFeedback() {
    window.toast = toast;
    window.confirmAction = confirmAction;
    window.pageProgress = progress;

    // Confirm before destructive clicks: <button data-confirm="Delete this?" wire:click="...">
    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-confirm]');
        if (!el || el.tagName === 'FORM') {
            return;
        }
        if (el.dataset.confirmed === '1') {
            delete el.dataset.confirmed;
            el.dataset.confirmPass = '1';

            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        confirmAction({
            title: el.dataset.confirmTitle || 'Please confirm',
            text: el.dataset.confirm,
            confirmText: el.dataset.confirmButton || 'Continue',
            danger: el.dataset.confirmTone !== 'neutral',
        }).then((ok) => {
            if (ok) {
                el.dataset.confirmed = '1';
                el.click();
            }
        });
    }, true);

    // Block a second Livewire click or submit before the first request finishes.
    document.addEventListener('click', (event) => {
        const control = event.target.closest('button, input[type="submit"]');
        const action = control ? [...control.attributes].find((attr) => attr.name === 'wire:click' || attr.name.startsWith('wire:click.')) : null;
        if (!control || !action || action.value.trim().startsWith('$')) {
            return;
        }
        if (control.dataset.confirmPass === '1') {
            delete control.dataset.confirmPass;
        } else if (control.dataset.confirm !== undefined && control.dataset.confirmed !== '1') {
            return;
        }
        if (control.dataset.locked === '1') {
            event.preventDefault();
            event.stopImmediatePropagation();

            return;
        }
        lockControl(control);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !isLivewireForm(form)) {
            return;
        }
        if (form.dataset.busy === '1') {
            event.preventDefault();
            event.stopImmediatePropagation();

            return;
        }
        form.dataset.busy = '1';
        form.setAttribute('aria-busy', 'true');
        if (event.submitter) {
            lockControl(event.submitter);
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || isLivewireForm(form)) {
            return;
        }

        if (form.dataset.confirm !== undefined && form.dataset.confirmed !== '1') {
            event.preventDefault();
            confirmAction({
                title: form.dataset.confirmTitle || 'Please confirm',
                text: form.dataset.confirm,
                confirmText: form.dataset.confirmButton || 'Continue',
                danger: form.dataset.confirmTone !== 'neutral',
            }).then((ok) => {
                if (ok) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit(event.submitter ?? undefined);
                }
            });

            return;
        }
        delete form.dataset.confirmed;

        if (form.dataset.busy === '1') {
            event.preventDefault();

            return;
        }
        if (event.defaultPrevented) {
            return;
        }

        markBusy(form, event.submitter);
        if ((form.getAttribute('target') || '_self') === '_self') {
            progress.start();
        }
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (link && isPlainNavigation(event, link)) {
            progress.start(120);
        }
    });

    window.addEventListener('pageshow', () => {
        progress.reset();
        releaseLocks();
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-flash]').forEach((el) => toast(el.dataset.flash, el.textContent.trim()));
    });

    document.addEventListener('livewire:init', () => {
        window.Livewire.on('toast', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            toast(data?.type ?? 'success', data?.message ?? '');
        });

        let inflight = 0;
        window.Livewire.hook('request', ({ succeed, fail }) => {
            inflight += 1;
            progress.start(250);
            const finish = () => {
                inflight = Math.max(0, inflight - 1);
                progress.done();
                if (inflight === 0) {
                    releaseLocks();
                }
            };
            succeed(finish);
            fail(finish);
        });
    });
}
