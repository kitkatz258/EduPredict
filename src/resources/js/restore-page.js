/**
 * Clears leftover dialog/SweetAlert scroll and pointer locks so the page
 * stays clickable after a Livewire modal unmounts.
 */
export function restorePageInteraction(root = document, ignore = null) {
    const body = root.body ?? null;
    const documentElement = root.documentElement ?? null;
    const dialogOpen = [...root.querySelectorAll('[role="dialog"][aria-modal="true"]')].some((el) => {
        if (ignore && (el === ignore || (typeof ignore.contains === 'function' && ignore.contains(el)))) {
            return false;
        }

        return ! el.classList.contains('swal2-container')
            && ! el.classList.contains('swal2-popup')
            && ! el.closest('.swal2-container');
    });
    const swalOpen = root.querySelector('.swal2-container') !== null;

    if (dialogOpen || swalOpen) {
        return false;
    }

    body?.classList.remove('overflow-hidden');
    documentElement?.classList.remove('overflow-hidden');
    if (body?.style.overflow === 'hidden') {
        body.style.overflow = '';
    }
    body?.style.removeProperty('padding-right');
    body?.style.removeProperty('pointer-events');
    documentElement?.style.removeProperty('overflow');
    documentElement?.style.removeProperty('padding-right');

    return true;
}
