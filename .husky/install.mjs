// Attiva gli hook git di husky dopo npm install (script "prepare").
// In CI e in produzione gli hook non servono; se husky non è installato
// (per esempio npm ci --omit=dev) si salta senza far fallire l'installazione.

if (process.env.CI === 'true' || process.env.NODE_ENV === 'production') {
  process.exit(0);
}

try {
  const { default: husky } = await import('husky');
  const message = husky();
  if (message) console.log(message);
} catch (error) {
  if (error?.code !== 'ERR_MODULE_NOT_FOUND') throw error;
  console.log('husky non è installato: hook git non attivati.');
}
