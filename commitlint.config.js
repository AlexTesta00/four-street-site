// Messaggi di commit secondo Conventional Commits: semantic-release li legge per decidere la versione.
// Tipi e scope restano quelli della configurazione convenzionale; la descrizione è in italiano.
// Può iniziare con la maiuscola o con la minuscola e contenere sigle e nomi propri
// ("docs: aggiungi il README", "test: copri i moduli con PHPUnit").
// Restano vietate solo le forme tutte maiuscole o con ogni parola maiuscola.

/** @type {import('@commitlint/types').UserConfig} */
export default {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'subject-case': [2, 'never', ['start-case', 'pascal-case', 'upper-case']],
  },
};
