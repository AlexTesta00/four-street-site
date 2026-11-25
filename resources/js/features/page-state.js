/* Allo zero del countdown la pagina passa allo stato "aperti" senza ricaricare: le parti con
   data-state="pre" si nascondono, quelle con data-state="open" (già in pagina, nascoste) compaiono
   e cambia il titolo della scheda. Prima il blocco del countdown esce (.fs-stage.is-leaving). */

/** L'uscita del blocco (.fs-stage.is-leaving) dura 600 ms: lo stato cambia subito dopo. */
const LEAVE_MS = 620;

/**
 * @param {{ leaving: HTMLElement, title: string | null, reduceMotion: boolean, root?: ParentNode }} options
 *   leaving è il blocco che esce per primo; title il titolo della pagina nello stato "aperti"
 * @returns {() => void} passa allo stato "aperti"; le chiamate successive non fanno nulla
 */
export function createOpenSwitch({ leaving, title, reduceMotion, root = document }) {
  let switching = false;

  const swap = () => {
    root.querySelectorAll('[data-state="pre"]').forEach((element) => {
      element.hidden = true;
    });
    root.querySelectorAll('[data-state="open"]').forEach((element) => {
      element.hidden = false;
    });
    if (title) document.title = title;
  };

  return function open() {
    if (switching || leaving.hidden) return;
    switching = true;

    if (reduceMotion) {
      swap();
    } else {
      leaving.classList.add('is-leaving');
      window.setTimeout(swap, LEAVE_MS);
    }
  };
}
