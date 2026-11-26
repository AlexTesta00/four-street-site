/**
 * L'id a cui punta un link interno alla pagina ("#email" → "email"), oppure null se il link
 * porta altrove o non punta a nulla.
 *
 * @param {string | null} href il valore dell'attributo href
 * @returns {string | null}
 */
export function fragmentId(href) {
  if (typeof href !== 'string' || !href.startsWith('#') || href.length < 2) return null;
  try {
    return decodeURIComponent(href.slice(1));
  } catch {
    return null;
  }
}
