import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { passwordVisibility } from './password-visibility.js';

describe('passwordVisibility', () => {
  it('nascosta: campo password e bottone "Mostra"', () => {
    assert.deepEqual(passwordVisibility(false), {
      type: 'password',
      label: 'Mostra',
      ariaLabel: 'Mostra la password',
      announcement: 'La password è nascosta.',
    });
  });

  it('visibile: campo di testo e bottone "Nascondi"', () => {
    assert.deepEqual(passwordVisibility(true), {
      type: 'text',
      label: 'Nascondi',
      ariaLabel: 'Nascondi la password',
      announcement: 'La password è visibile.',
    });
  });

  it("il bottone dice sempre cosa farà, non com'è il campo", () => {
    assert.match(passwordVisibility(false).ariaLabel, /^Mostra/);
    assert.match(passwordVisibility(true).ariaLabel, /^Nascondi/);
  });
});
