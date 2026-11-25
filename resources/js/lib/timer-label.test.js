import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { timerLabel } from './timer-label.js';

describe('timerLabel', () => {
  it('descrive giorni, ore e minuti che mancano', () => {
    assert.equal(
      timerLabel({ days: 11, hours: 7, minutes: 5 }),
      "Mancano 11 giorni, 7 ore e 5 minuti all'inaugurazione",
    );
  });

  it('usa i giorni reali anche oltre le due cifre del countdown', () => {
    assert.match(timerLabel({ days: 120, hours: 0, minutes: 0 }), /^Mancano 120 giorni/);
  });
});
