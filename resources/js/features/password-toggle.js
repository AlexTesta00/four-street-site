/* Bottoni "Mostra" dei campi password (data-password-toggle, aria-controls è l'id del campo):
   rendono leggibile la password e la nascondono di nuovo. Senza script il bottone resta nascosto (hidden)
   e il campo funziona come sempre. La regione data-password-status del campo (data-password-field)
   dice ai lettori di schermo se la password si vede. Prima dell'invio la password torna nascosta:
   il browser non la salva fra i testi già scritti nei moduli. */

import { passwordVisibility } from '../lib/password-visibility.js';

/**
 * @param {ParentNode} [root]
 */
export function initPasswordToggles(root = document) {
  root.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls') ?? '');
    if (!(input instanceof HTMLInputElement)) return;
    const status = button.closest('[data-password-field]')?.querySelector('[data-password-status]');
    let visible = false;

    const show = (isVisible, announce) => {
      visible = isVisible;
      const state = passwordVisibility(visible);
      input.type = state.type;
      button.textContent = state.label;
      button.setAttribute('aria-label', state.ariaLabel);
      if (status && announce) status.textContent = state.announcement;
    };

    button.addEventListener('click', () => show(!visible, true));
    input.form?.addEventListener('submit', () => {
      if (visible) show(false, false);
    });
    button.hidden = false;
  });
}
