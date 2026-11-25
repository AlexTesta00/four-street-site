// Prettier formatta JavaScript, CSS, JSON, YAML e Markdown. Il PHP lo formatta PHP-CS-Fixer.

/** @type {import('prettier').Config} */
export default {
  printWidth: 100,
  overrides: [
    {
      // Nel JavaScript apici singoli; CSS, JSON e YAML restano con le virgolette doppie.
      files: ['*.js', '*.mjs', '*.cjs'],
      options: {
        singleQuote: true,
      },
    },
  ],
};
