// ESLint (flat config): regole consigliate più alcune regole severe che trovano errori veri.
// Lo stile (spazi, virgolette, a capo) lo decide Prettier, non ESLint.

import js from '@eslint/js';
import globals from 'globals';

export default [
  {
    // File generati, dipendenze e strumenti locali.
    ignores: [
      'public/assets/**',
      'build/**',
      'coverage/**',
      'node_modules/**',
      'vendor/**',
      '.tools/**',
    ],
  },

  js.configs.recommended,

  {
    linterOptions: {
      reportUnusedDisableDirectives: 'error',
    },
    rules: {
      'array-callback-return': 'error',
      'consistent-return': 'error',
      eqeqeq: ['error', 'always'],
      'no-implied-eval': 'error',
      'no-param-reassign': 'error',
      'no-promise-executor-return': 'error',
      'no-return-assign': 'error',
      'no-self-compare': 'error',
      'no-sequences': 'error',
      'no-shadow': 'error',
      'no-template-curly-in-string': 'error',
      'no-throw-literal': 'error',
      'no-unmodified-loop-condition': 'error',
      'no-unreachable-loop': 'error',
      'no-unused-expressions': 'error',
      'no-use-before-define': ['error', { functions: false }],
      'no-useless-assignment': 'error',
      'no-var': 'error',
      'prefer-const': 'error',
    },
  },

  {
    // Codice che gira nel browser.
    files: ['resources/js/**/*.js'],
    languageOptions: {
      globals: globals.browser,
    },
  },

  {
    // Test (node --test), script di build e file di configurazione: girano in Node.
    files: ['resources/js/**/*.test.js', 'scripts/**/*.mjs', '.husky/**/*.mjs', '*.config.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
];
