import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { createSubmitGuard } from './submit-guard.js';

describe('createSubmitGuard', () => {
  it('il primo invio parte', () => {
    assert.equal(createSubmitGuard().lock(), true);
  });

  it('il secondo invio, mentre il primo è in corso, no', () => {
    const guard = createSubmitGuard();
    guard.lock();

    assert.equal(guard.lock(), false);
    assert.equal(guard.lock(), false);
  });

  it('riaperto, lascia partire di nuovo un invio', () => {
    const guard = createSubmitGuard();
    guard.lock();
    guard.release();

    assert.equal(guard.lock(), true);
  });

  it('ogni modulo ha il suo lucchetto', () => {
    const first = createSubmitGuard();
    const second = createSubmitGuard();
    first.lock();

    assert.equal(second.lock(), true);
  });
});
