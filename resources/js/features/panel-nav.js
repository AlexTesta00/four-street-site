/* La navigazione del pannello del titolare (data-panel-nav) sul telefono è una riga che scorre di lato:
   all'apertura della pagina la voce corrente (aria-current) viene portata nel mezzo, così si vede sempre
   in che sezione si è. Senza JavaScript la riga parte dall'inizio e si scorre con il dito. */

import { centeredScroll } from '../lib/nav-scroll.js';

/**
 * @param {ParentNode} [root]
 */
export function initPanelNav(root = document) {
  root.querySelectorAll('[data-panel-nav]').forEach((nav) => {
    const current = nav.querySelector('[aria-current]');
    if (!(nav instanceof HTMLElement) || !(current instanceof HTMLElement)) return;
    if (nav.scrollWidth <= nav.clientWidth) return;

    nav.scrollLeft = centeredScroll({
      viewWidth: nav.clientWidth,
      contentWidth: nav.scrollWidth,
      itemStart: current.offsetLeft,
      itemWidth: current.offsetWidth,
    });
  });
}
