/* Calcoli sul tempo che manca all'inaugurazione. Niente DOM: si provano con node --test. */

const SECONDS_PER_DAY = 86400;
const SECONDS_PER_HOUR = 3600;
const SECONDS_PER_MINUTE = 60;

/** Margine dopo lo scatto del secondo, perché il conto sia già cambiato quando si ridisegna. */
const TICK_MARGIN_MS = 20;

/**
 * Scompone i millisecondi che mancano in giorni, ore, minuti e secondi.
 * Il secondo in corso conta come intero: a 1500 ms dalla fine mancano ancora 2 secondi.
 *
 * @param {number} ms millisecondi che mancano, maggiori di zero
 * @returns {{ days: number, hours: number, minutes: number, seconds: number }}
 */
export function splitTimeLeft(ms) {
  const total = Math.ceil(ms / 1000);
  return {
    days: Math.floor(total / SECONDS_PER_DAY),
    hours: Math.floor((total % SECONDS_PER_DAY) / SECONDS_PER_HOUR),
    minutes: Math.floor((total % SECONDS_PER_HOUR) / SECONDS_PER_MINUTE),
    seconds: total % SECONDS_PER_MINUTE,
  };
}

/**
 * Attesa fino al prossimo scatto del secondo.
 *
 * @param {number} ms millisecondi che mancano, maggiori di zero
 * @returns {number}
 */
export function delayToNextSecond(ms) {
  return (ms % 1000) + TICK_MARGIN_MS;
}
