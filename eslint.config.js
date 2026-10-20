export default [
  {
    ignores: ['node_modules/**', 'webroot/dist/**'],
  },
  {
    files: ['webroot/js/**/*.js', 'tests/js/**/*.js', 'tests/browser/**/*.mjs', 'tests/browser/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
    },
    rules: {
      'no-restricted-globals': ['error', 'jQuery', '$'],
    },
  },
];
