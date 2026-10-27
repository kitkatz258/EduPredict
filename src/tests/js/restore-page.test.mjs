import assert from 'node:assert/strict';
import test from 'node:test';
import { restorePageInteraction } from '../../resources/js/restore-page.js';

function fakeDocument({ dialogs = [], swal = false } = {}) {
    const body = {
        classList: {
            values: new Set(['overflow-hidden']),
            contains(name) { return this.values.has(name); },
            remove(name) { this.values.delete(name); },
        },
        style: {
            overflow: 'hidden',
            pointerEvents: 'none',
            removeProperty(name) {
                if (name === 'padding-right') {
                    this.paddingRight = '';
                }
                if (name === 'pointer-events') {
                    this.pointerEvents = '';
                }
            },
        },
    };
    const documentElement = {
        classList: {
            values: new Set(['overflow-hidden']),
            contains(name) { return this.values.has(name); },
            remove(name) { this.values.delete(name); },
        },
        style: {
            removeProperty() {},
        },
    };
    const nodes = [...dialogs];
    if (swal) {
        nodes.push({
            classList: { contains: (name) => name === 'swal2-container' || name === 'swal2-popup' },
            closest: (selector) => (selector === '.swal2-container' ? nodes[nodes.length - 1] : null),
        });
    }

    return {
        body,
        documentElement,
        querySelectorAll(selector) {
            if (selector !== '[role="dialog"][aria-modal="true"]') {
                return [];
            }

            return dialogs;
        },
        querySelector(selector) {
            if (selector === '.swal2-container') {
                return swal ? nodes[nodes.length - 1] : null;
            }

            return null;
        },
    };
}

function dialog(overrides = {}) {
    return {
        classList: { contains: () => false },
        closest: () => null,
        ...overrides,
    };
}

test('restorePageInteraction clears leftover body locks when no dialog remains', () => {
    const document = fakeDocument();

    assert.equal(restorePageInteraction(document), true);
    assert.equal(document.body.classList.contains('overflow-hidden'), false);
    assert.equal(document.body.style.overflow, '');
    assert.equal(document.body.style.pointerEvents, '');
});

test('restorePageInteraction keeps the lock while an app dialog is open', () => {
    const document = fakeDocument({ dialogs: [dialog()] });

    assert.equal(restorePageInteraction(document), false);
    assert.equal(document.body.classList.contains('overflow-hidden'), true);
});

test('restorePageInteraction ignores a dialog that is leaving', () => {
    const leaving = dialog();
    const document = fakeDocument({ dialogs: [leaving] });

    assert.equal(restorePageInteraction(document, leaving), true);
    assert.equal(document.body.classList.contains('overflow-hidden'), false);
});

test('restorePageInteraction keeps the lock while SweetAlert is open', () => {
    const document = fakeDocument({ swal: true });

    assert.equal(restorePageInteraction(document), false);
    assert.equal(document.body.classList.contains('overflow-hidden'), true);
});
