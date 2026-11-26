/**
 * Un lucchetto per l'invio di un modulo: la prima volta si chiude e lascia passare, poi blocca
 * ogni altro invio finché qualcuno non lo riapre.
 *
 * @returns {{ lock: () => boolean, release: () => void }} lock() dice se l'invio può partire
 */
export function createSubmitGuard() {
  let locked = false;
  return {
    lock() {
      if (locked) return false;
      locked = true;
      return true;
    },
    release() {
      locked = false;
    },
  };
}
