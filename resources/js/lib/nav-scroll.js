/**
 * Dove scorrere una riga di voci che scorre di lato perché la voce indicata stia nel mezzo, senza andare
 * oltre l'inizio né oltre la fine della riga.
 *
 * @param {{ viewWidth: number, contentWidth: number, itemStart: number, itemWidth: number }} box
 *   larghezza visibile, larghezza di tutta la riga, inizio e larghezza della voce (px, dall'inizio della riga)
 * @returns {number} lo scorrimento orizzontale (px)
 */
export function centeredScroll({ viewWidth, contentWidth, itemStart, itemWidth }) {
  const max = Math.max(0, contentWidth - viewWidth);
  const target = itemStart + itemWidth / 2 - viewWidth / 2;
  return Math.round(Math.min(max, Math.max(0, target)));
}
