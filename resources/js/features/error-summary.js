/* Il riepilogo degli errori di un modulo (data-error-summary) prende il focus quando la pagina si apre:
   chi usa la tastiera o un lettore di schermo sa subito cosa correggere. Ogni errore è un link al suo campo:
   con lo script il link mette a fuoco il campo e porta in vista anche la sua etichetta. */

import { fragmentId } from '../lib/fragment.js';

/**
 * @param {ParentNode} [root]
 */
export function initErrorSummary(root = document) {
  const summary = root.querySelector('[data-error-summary]');
  if (!(summary instanceof HTMLElement)) return;

  summary.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a') : null;
    const field = document.getElementById(fragmentId(link?.getAttribute('href') ?? null) ?? '');
    if (!(field instanceof HTMLElement)) return;

    event.preventDefault();
    const label = document.querySelector(`label[for="${CSS.escape(field.id)}"]`);
    (label ?? field).scrollIntoView({ block: 'start' });
    field.focus({ preventScroll: true });
  });

  summary.focus();
}
