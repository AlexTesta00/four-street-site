import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { isBelowFold } from './fold.js';

describe('isBelowFold', () => {
  it('un elemento che inizia sotto lo schermo non si vede ancora', () => {
    assert.equal(isBelowFold(1200, 800), true);
  });

  it('un elemento che inizia proprio sul bordo non si vede ancora', () => {
    assert.equal(isBelowFold(800, 800), true);
  });

  it('un elemento che spunta anche di un pixel si vede già', () => {
    assert.equal(isBelowFold(799, 800), false);
  });

  it('un elemento sopra lo schermo, già passato, non è sotto il bordo', () => {
    assert.equal(isBelowFold(-300, 800), false);
  });
});
