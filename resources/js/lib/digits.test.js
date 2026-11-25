import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { padDigits } from './digits.js';

describe('padDigits', () => {
  it('aggiunge gli zeri davanti fino al numero di celle', () => {
    assert.equal(padDigits(0, 2), '00');
    assert.equal(padDigits(7, 2), '07');
    assert.equal(padDigits(7, 3), '007');
  });

  it('lascia invariato un valore che riempie già le celle', () => {
    assert.equal(padDigits(42, 2), '42');
  });

  it('tiene le ultime cifre se il valore ne ha più delle celle', () => {
    assert.equal(padDigits(123, 2), '23');
  });
});
