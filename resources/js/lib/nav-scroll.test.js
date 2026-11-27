import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { centeredScroll } from './nav-scroll.js';

describe('centeredScroll', () => {
  it('porta la voce nel mezzo della parte visibile', () => {
    assert.equal(
      centeredScroll({ viewWidth: 320, contentWidth: 520, itemStart: 300, itemWidth: 80 }),
      180,
    );
  });

  it('non scorre prima dell’inizio: la prima voce resta dov’è', () => {
    assert.equal(
      centeredScroll({ viewWidth: 320, contentWidth: 520, itemStart: 20, itemWidth: 60 }),
      0,
    );
  });

  it('non scorre oltre la fine: l’ultima voce resta attaccata al bordo', () => {
    assert.equal(
      centeredScroll({ viewWidth: 320, contentWidth: 520, itemStart: 400, itemWidth: 110 }),
      200,
    );
  });

  it('una riga che sta tutta nello schermo non scorre', () => {
    assert.equal(
      centeredScroll({ viewWidth: 480, contentWidth: 440, itemStart: 300, itemWidth: 120 }),
      0,
    );
  });

  it('arrotonda al pixel', () => {
    assert.equal(
      centeredScroll({ viewWidth: 321, contentWidth: 520, itemStart: 300, itemWidth: 81 }),
      180,
    );
  });
});
