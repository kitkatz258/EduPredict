import assert from 'node:assert/strict';
import test from 'node:test';
import { dialogCloseMethod } from '../../resources/js/dialog-close.js';

function element({ dialogClose, parent = null } = {}) {
    return {
        dataset: dialogClose === undefined ? {} : { dialogClose },
        closest(selector) {
            if (selector !== '[data-dialog-close]') {
                return null;
            }
            if (this.dataset.dialogClose) {
                return this;
            }

            return parent;
        },
    };
}

test('a close button resolves the dialog host method', () => {
    const host = element({ dialogClose: 'closeForm' });
    const button = element({ parent: host });

    assert.equal(dialogCloseMethod(button), 'closeForm');
    assert.notEqual(dialogCloseMethod(button), 'undefined');
});

test('a missing or undefined method is not sent to Livewire', () => {
    assert.equal(dialogCloseMethod(element()), '');
    assert.equal(dialogCloseMethod(element({ dialogClose: 'undefined' })), '');
    assert.equal(dialogCloseMethod(element({ dialogClose: 'close Form' })), '');
    assert.equal(dialogCloseMethod(null), '');
});

test('escape and backdrop use the same host method as cancel', () => {
    const host = element({ dialogClose: 'closeView' });

    assert.equal(dialogCloseMethod(host), 'closeView');
    assert.equal(dialogCloseMethod(element({ parent: host })), 'closeView');
});
