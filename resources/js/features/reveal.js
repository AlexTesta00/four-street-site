/* Comparsa allo scorrimento per i browser che non legano le animazioni allo scorrimento
   (animation-timeline: view(), vedi reveal.css): nasconde solo ciò che all'apertura sta sotto
   il bordo dello schermo, quindi nessuno lo vede sparire, e lo fa entrare quando arriva.
   Se il CSS lo sa fare da solo, o con il movimento ridotto, non fa nulla. */

import { isBelowFold } from '../lib/fold.js';

/**
 * @param {ParentNode} root
 * @param {{ reduceMotion: boolean }} options
 */
export function initReveal(root, { reduceMotion }) {
  if (
    reduceMotion ||
    !('IntersectionObserver' in window) ||
    CSS.supports('animation-timeline', 'view()')
  ) {
    return;
  }

  const waiting = [...root.querySelectorAll('[data-reveal]')].filter((element) =>
    isBelowFold(element.getBoundingClientRect().top, window.innerHeight),
  );
  if (waiting.length === 0) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        entry.target.classList.remove('is-waiting');
        entry.target.classList.add('is-entering');
      });
    },
    // Entra quando è già un po' dentro lo schermo, non appena ne sfiora il bordo.
    { rootMargin: '0px 0px -10% 0px' },
  );

  waiting.forEach((element) => {
    element.classList.add('is-waiting');
    observer.observe(element);
  });
}
