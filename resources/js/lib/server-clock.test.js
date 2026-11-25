import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { clockSkew, createServerClock } from './server-clock.js';

describe('clockSkew', () => {
  it('è positivo se il dispositivo è indietro', () => {
    assert.equal(clockSkew(10_000, 4_000), 6_000);
  });

  it('è negativo se il dispositivo è avanti', () => {
    assert.equal(clockSkew(4_000, 10_000), -6_000);
  });
});

describe('createServerClock', () => {
  it("segue il dispositivo ma con l'ora del server", () => {
    let device = 4_000;
    const now = createServerClock(10_000, () => device);

    assert.equal(now(), 10_000);
    device += 1_500;
    assert.equal(now(), 11_500);
  });

  it("misura lo scarto una volta sola, all'avvio", () => {
    let device = 0;
    const now = createServerClock(60_000, () => device);

    // L'ora del dispositivo cambia dopo l'avvio: lo scarto resta quello misurato.
    device = 3_600_000;
    assert.equal(now(), 3_660_000);
  });
});
