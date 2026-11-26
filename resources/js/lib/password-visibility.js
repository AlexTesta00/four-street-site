/**
 * Lo stato di un campo password e del suo bottone "Mostra": il tipo del campo, il testo del bottone,
 * il nome per i lettori di schermo e la frase da annunciare quando cambia.
 *
 * @param {boolean} visible la password si legge
 * @returns {{ type: 'text' | 'password', label: string, ariaLabel: string, announcement: string }}
 */
export function passwordVisibility(visible) {
  return visible
    ? {
        type: 'text',
        label: 'Nascondi',
        ariaLabel: 'Nascondi la password',
        announcement: 'La password è visibile.',
      }
    : {
        type: 'password',
        label: 'Mostra',
        ariaLabel: 'Mostra la password',
        announcement: 'La password è nascosta.',
      };
}
