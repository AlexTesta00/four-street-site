/**
 * Scarto tra l'orologio del server e quello del dispositivo, in millisecondi.
 *
 * @param {number} serverNow ora del server quando ha generato la pagina (ms)
 * @param {number} deviceNow ora del dispositivo nello stesso momento (ms)
 * @returns {number}
 */
export function clockSkew(serverNow, deviceNow) {
  return serverNow - deviceNow;
}

/**
 * Orologio allineato al server: il countdown non dipende dall'ora impostata sul dispositivo.
 * Lo scarto si misura una volta, all'avvio.
 *
 * @param {number} serverNow ora del server quando ha generato la pagina (ms)
 * @param {() => number} [deviceNow] orologio del dispositivo, Date.now se non indicato
 * @returns {() => number} ora del server stimata, in ms
 */
export function createServerClock(serverNow, deviceNow = Date.now) {
  const skew = clockSkew(serverNow, deviceNow());
  return () => deviceNow() + skew;
}
