/* Fasce orarie: la scelta la fanno le radio e il CSS. Qui si aggiorna solo il riepilogo
   "La tua fascia" (orario e numero del posto), una regione aria-live: i lettori di schermo lo leggono. */

/**
 * @param {ParentNode} [root]
 */
export function initSlotPicker(root = document) {
  const picker = root.querySelector('[data-slot-picker]');
  const time = root.querySelector('[data-slot-time]');
  const seat = root.querySelector('[data-slot-seat]');
  if (!picker || !time || !seat) return;

  /** @param {HTMLInputElement} input la radio scelta: value è l'orario, data-seat il posto */
  const show = (input) => {
    const next = input.getAttribute('data-seat') ?? '';
    // Si scrive solo ciò che cambia: un testo riscritto uguale verrebbe letto di nuovo.
    if (time.textContent !== input.value) time.textContent = input.value;
    if (seat.textContent !== next) seat.textContent = next;
  };

  picker.addEventListener('change', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.checked) show(event.target);
  });

  // Tornando indietro o ricaricando, il browser può ripristinare una scelta diversa da quella del server.
  const checked = picker.querySelector('input:checked');
  if (checked instanceof HTMLInputElement) show(checked);
}
