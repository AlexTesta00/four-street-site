/**
 * Testo per i lettori di schermo: le cifre del countdown sono aria-hidden,
 * il riquadro role="timer" porta questa frase.
 *
 * @param {{ days: number, hours: number, minutes: number }} timeLeft
 * @returns {string}
 */
export function timerLabel({ days, hours, minutes }) {
  return `Mancano ${days} giorni, ${hours} ore e ${minutes} minuti all'inaugurazione`;
}
