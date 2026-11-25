/* Mappa: l'iframe di Google Maps si carica solo quando serve (data-src diventa src). */

/**
 * @param {ParentNode} [root]
 */
export function loadMap(root = document) {
  const frame = root.querySelector('.fs-map__frame[data-src]');
  if (frame) {
    frame.src = frame.getAttribute('data-src');
    frame.removeAttribute('data-src');
  }
}
