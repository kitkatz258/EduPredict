/**
 * Resolve the Livewire method for a shared dialog close control.
 * Alpine's $el during a click is the button, which has no data-dialog-close.
 * Reading that button used to call Livewire with method "undefined".
 */
export function dialogCloseMethod(el) {
    const host = el && typeof el.closest === 'function'
        ? (el.closest('[data-dialog-close]') ?? el)
        : el;
    const method = host?.dataset?.dialogClose ?? '';

    if (method === 'undefined' || !/^[A-Za-z_][A-Za-z0-9_]*$/.test(method)) {
        return '';
    }

    return method;
}
