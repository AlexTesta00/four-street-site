/* Barra delle azioni: mai due bottoni oro nella stessa vista. Mentre un bottone oro del contenuto
   (data-primary-cta) è sullo schermo la barra si fa da parte (is-hidden) e diventa inerte:
   non riceve il focus e i lettori di schermo non la leggono. Senza script resta sempre visibile. */

import { createVisibilityTracker } from '../lib/visibility.js';

/**
 * @param {ParentNode} [root]
 */
export function initActionBar(root = document) {
  const bar = root.querySelector('[data-action-bar]');
  const primaries = root.querySelectorAll('[data-primary-cta]');
  if (
    !(bar instanceof HTMLElement) ||
    primaries.length === 0 ||
    !('IntersectionObserver' in window)
  ) {
    return;
  }

  const update = createVisibilityTracker();
  const observer = new IntersectionObserver((entries) => {
    let anyVisible = false;
    entries.forEach((entry) => {
      anyVisible = update(entry.target, entry.isIntersecting);
    });
    bar.classList.toggle('is-hidden', anyVisible);
    bar.inert = anyVisible;
  });
  primaries.forEach((primary) => observer.observe(primary));
}
