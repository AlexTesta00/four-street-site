import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { createVisibilityTracker } from './visibility.js';

describe('createVisibilityTracker', () => {
  it("all'inizio nessun elemento è sullo schermo", () => {
    const update = createVisibilityTracker();

    assert.equal(update('primo', false), false);
  });

  it('basta un elemento sullo schermo', () => {
    const update = createVisibilityTracker();

    assert.equal(update('primo', true), true);
    assert.equal(update('secondo', false), true);
  });

  it('torna falso solo quando escono tutti', () => {
    const update = createVisibilityTracker();
    update('primo', true);
    update('secondo', true);

    assert.equal(update('primo', false), true);
    assert.equal(update('secondo', false), false);
  });

  it('lo stesso elemento segnalato due volte conta una volta sola', () => {
    const update = createVisibilityTracker();
    update('primo', true);
    update('primo', true);

    assert.equal(update('primo', false), false);
  });
});
