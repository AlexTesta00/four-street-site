/* Allo zero il countdown lascia il posto allo stato "Ci siamo": sezione #live e mappa. */

import { loadMap } from './map.js';

/** L'uscita del countdown (.fs-stage.is-leaving) dura 600 ms: si cambia sezione subito dopo. */
const LEAVE_MS = 620;

/**
 * @param {{ countdown: HTMLElement, live: HTMLElement | null, reduceMotion: boolean }} options
 * @returns {() => void} passa allo stato "Ci siamo"; non fa nulla se è già visibile
 */
export function createLiveSwitch({ countdown, live, reduceMotion }) {
  return function goLive() {
    if (!live || !live.hidden) return;

    const swap = () => {
      countdown.hidden = true;
      live.hidden = false;
      loadMap(document);
      document.title = live.getAttribute('data-title') || document.title;
      window.scrollTo(0, 0);
    };

    if (reduceMotion) {
      swap();
    } else {
      countdown.classList.add('is-leaving');
      window.setTimeout(swap, LEAVE_MS);
    }
  };
}
