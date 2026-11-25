/* Bottoni "Copia indirizzo": copiano il testo di data-copy e lo confermano nell'etichetta. */

/** Dopo quanto l'etichetta torna al testo di partenza. */
const FEEDBACK_MS = 2000;

/**
 * @param {ParentNode} [root]
 */
export function initCopyAddress(root = document) {
  root.querySelectorAll('[data-copy]').forEach((button) => {
    const label = button.querySelector('[data-copy-label]');
    const original = label ? label.textContent : '';
    let resetTimer;

    const done = (ok) => {
      if (!label) return;
      label.textContent = ok ? 'Copiato' : 'Copia non riuscita';
      window.clearTimeout(resetTimer);
      resetTimer = window.setTimeout(() => {
        label.textContent = original;
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
