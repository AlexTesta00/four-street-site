// Costruisce public/assets/js/app.js da resources/js/app.js con esbuild.
//
//   node scripts/build-js.mjs           build di produzione, minificata
//   node scripts/build-js.mjs --watch   ricostruisce a ogni modifica, con source map in linea
//
// Quando il contenuto cambia, cambia anche la data di modifica del file: il PHP la mette
// nell'URL come ?v=<mtime>, così il browser non usa una copia vecchia.

import { fileURLToPath } from 'node:url';

import * as esbuild from 'esbuild';

const watch = process.argv.includes('--watch');

/** @type {import('esbuild').BuildOptions} */
const options = {
  absWorkingDir: fileURLToPath(new URL('..', import.meta.url)),
  entryPoints: ['resources/js/app.js'],
  outfile: 'public/assets/js/app.js',
  bundle: true,
  // Script classico in un unico file: la pagina lo carica con <script defer>, non come modulo.
  format: 'iife',
  // Gli stessi browser su cui il CSS funziona: quelli con i cascade layer (@layer),
  // usati da Tailwind v4. Più indietro la pagina non avrebbe comunque i suoi stili.
  target: ['chrome99', 'edge99', 'firefox97', 'safari15.4'],
  minify: !watch,
  sourcemap: watch ? 'inline' : false,
  legalComments: 'none',
  logLevel: 'info',
};

if (watch) {
  const context = await esbuild.context(options);
  await context.watch();
} else {
  await esbuild.build(options);
}
