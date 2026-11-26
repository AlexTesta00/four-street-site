/* Moduli con data-submit-once: un secondo tocco su "Invia" mentre la pagina carica non manda il modulo
   una seconda volta (una registrazione doppia, due email). Il modulo in corso d'invio ha aria-busy.
   Se il browser torna alla pagina dalla sua cache (indietro), il modulo si sblocca. */

import { createSubmitGuard } from '../lib/submit-guard.js';

/**
 * @param {ParentNode} [root]
 */
export function initSubmitOnce(root = document) {
  root.querySelectorAll('form[data-submit-once]').forEach((form) => {
    const guard = createSubmitGuard();

    form.addEventListener('submit', (event) => {
      if (!guard.lock()) {
        event.preventDefault();
        return;
      }
      form.setAttribute('aria-busy', 'true');
    });

    window.addEventListener('pageshow', (event) => {
      if (!event.persisted) return;
      guard.release();
      form.removeAttribute('aria-busy');
    });
  });
}
