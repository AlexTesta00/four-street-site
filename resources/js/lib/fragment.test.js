import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { fragmentId } from './fragment.js';

describe('fragmentId', () => {
  it('un link interno punta al suo id', () => {
    assert.equal(fragmentId('#email'), 'email');
    assert.equal(fragmentId('#privacy'), 'privacy');
  });

  it('decodifica i caratteri codificati', () => {
    assert.equal(fragmentId('#nome%20e%20cognome'), 'nome e cognome');
  });

  it('un link che porta altrove non punta a un campo', () => {
    assert.equal(fragmentId('/account/accedi/'), null);
    assert.equal(fragmentId('https://fourstreet.studio/#email'), null);
  });

  it('niente id, niente campo', () => {
    assert.equal(fragmentId('#'), null);
    assert.equal(fragmentId(''), null);
    assert.equal(fragmentId(null), null);
  });

  it('una codifica rotta non fa fallire nulla', () => {
    assert.equal(fragmentId('#%E0%A4%A'), null);
  });
});
