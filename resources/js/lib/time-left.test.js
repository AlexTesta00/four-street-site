import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { delayToNextSecond, splitTimeLeft } from './time-left.js';

const SECOND = 1000;
const MINUTE = 60 * SECOND;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

describe('splitTimeLeft', () => {
  it('scompone in giorni, ore, minuti e secondi', () => {
    assert.deepEqual(splitTimeLeft(DAY + HOUR + MINUTE + SECOND), {
      days: 1,
      hours: 1,
      minutes: 1,
      seconds: 1,
    });
  });

  it('conta il secondo in corso come intero', () => {
    assert.deepEqual(splitTimeLeft(1), { days: 0, hours: 0, minutes: 0, seconds: 1 });
    assert.deepEqual(splitTimeLeft(1500), { days: 0, hours: 0, minutes: 0, seconds: 2 });
    assert.deepEqual(splitTimeLeft(MINUTE - 1), { days: 0, hours: 0, minutes: 1, seconds: 0 });
  });

  it('a un giorno esatto mostra 1 giorno e zero il resto', () => {
    assert.deepEqual(splitTimeLeft(DAY), { days: 1, hours: 0, minutes: 0, seconds: 0 });
  });

  it('non limita i giorni: il tetto delle due cifre spetta al countdown', () => {
    assert.equal(splitTimeLeft(120 * DAY).days, 120);
  });
});

describe('delayToNextSecond', () => {
  it('aspetta fino allo scatto del secondo più 20 ms', () => {
    assert.equal(delayToNextSecond(5 * SECOND + 250), 270);
    assert.equal(delayToNextSecond(999), 1019);
  });

  it('a secondo pieno aspetta solo il margine', () => {
    assert.equal(delayToNextSecond(3 * SECOND), 20);
  });
});
