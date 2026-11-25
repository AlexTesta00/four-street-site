/**
 * Tiene il conto degli elementi sullo schermo, uno per volta come li segnala IntersectionObserver.
 * Niente DOM: gli elementi sono solo chiavi, quindi si prova con node --test.
 *
 * @returns {(target: unknown, isVisible: boolean) => boolean} registra lo stato di un elemento
 *   e dice se almeno uno è sullo schermo
 */
export function createVisibilityTracker() {
  const visible = new Set();
  return (target, isVisible) => {
    if (isVisible) visible.add(target);
    else visible.delete(target);
    return visible.size > 0;
  };
}
