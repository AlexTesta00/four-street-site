/**
 * Testo da mostrare in un gruppo di celle del countdown: zeri davanti fino a `length` cifre;
 * se le cifre sono di più, restano le ultime.
 *
 * @param {number} value
 * @param {number} length numero di celle
 * @returns {string}
 */
export function padDigits(value, length) {
  return String(value).padStart(length, '0').slice(-length);
}
