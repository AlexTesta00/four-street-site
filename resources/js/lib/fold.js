/**
 * L'elemento comincia sotto il bordo inferiore dello schermo: chi apre la pagina non lo vede ancora.
 *
 * @param {number} top distanza del bordo superiore dell'elemento dalla cima dello schermo (px)
 * @param {number} viewportHeight altezza dello schermo (px)
 * @returns {boolean}
 */
export function isBelowFold(top, viewportHeight) {
  return top >= viewportHeight;
}
