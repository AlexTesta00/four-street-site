/* Bottoni "Copia indirizzo": copiano il testo di data-copy e lo confermano nell'etichetta.
   data-copy-status è l'id di una regione aria-live: la conferma arriva anche ai lettori di schermo. */

/** Dopo quanto l'etichetta torna al testo di partenza. */
const FEEDBACK_MS = 2000;

/**
 * @param {ParentNode} [root]
 */
export function initCopyAddress(root = document) {
  root.querySelectorAll('[data-copy]').forEach((button) => {
    const label = button.querySelector('[data-copy-label]');
    const status = document.getElementById(button.getAttribute('data-copy-status') ?? '');
    const original = label ? label.textContent : '';
    let resetTimer;

    const done = (ok) => {
      if (label) label.textContent = ok ? 'Copiato' : 'Copia non riuscita';
      if (status) status.textContent = ok ? 'Indirizzo copiato.' : 'Copia non riuscita.';
      window.clearTimeout(resetTimer);
      resetTimer = window.setTimeout(() => {
        if (label) label.textContent = original;
        // Vuota la regione: alla copia successiva il messaggio viene letto di nuovo.
        if (status) status.textContent = '';
      }, FEEDBACK_MS);
    };

    button.addEventListener('click', () => {
      const text = button.getAttribute('data-copy');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(
          () => done(true),
          () => done(false),
        );
        return;
      }
      done(copyWithTextarea(text));
    });
  });
}

/**
 * Ripiego per i browser senza Clipboard API (o pagina non servita in HTTPS).
 *
 * @param {string} text
 * @returns {boolean} true se la copia è riuscita
 */
function copyWithTextarea(text) {
  const field = document.createElement('textarea');
  field.value = text;
  field.setAttribute('readonly', '');
  field.style.position = 'fixed';
  field.style.opacity = '0';
  document.body.appendChild(field);
  field.select();
  let ok;
  try {
    ok = document.execCommand('copy');
  } catch {
    ok = false;
  }
  document.body.removeChild(field);
  return ok;
}
