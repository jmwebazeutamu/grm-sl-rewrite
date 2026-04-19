/* eslint-env node */
module.exports = {
    root: true,
    extends: [
        'plugin:vue/vue3-recommended',
        '@vue/eslint-config-typescript',
        '@vue/eslint-config-prettier',
    ],
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
    rules: {
        'vue/multi-word-component-names': 'off',
        'vue/no-v-html': 'error',
        '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        '@typescript-eslint/consistent-type-imports': 'error',
    },
};
