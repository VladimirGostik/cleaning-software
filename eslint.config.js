import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import tsParser from '@typescript-eslint/parser';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import prettier from 'eslint-config-prettier';

const browserGlobals = {
    window: 'readonly',
    document: 'readonly',
    setTimeout: 'readonly',
    clearTimeout: 'readonly',
    setInterval: 'readonly',
    clearInterval: 'readonly',
    CustomEvent: 'readonly',
    Event: 'readonly',
    HTMLElement: 'readonly',
    HTMLInputElement: 'readonly',
    HTMLSelectElement: 'readonly',
    HTMLTextAreaElement: 'readonly',
    HTMLMetaElement: 'readonly',
    KeyboardEvent: 'readonly',
    DragEvent: 'readonly',
    AbortController: 'readonly',
    DOMException: 'readonly',
    File: 'readonly',
    FileList: 'readonly',
    FormData: 'readonly',
    XMLHttpRequest: 'readonly',
    console: 'readonly',
    fetch: 'readonly',
    URL: 'readonly',
    URLSearchParams: 'readonly',
    // Global TypeScript namespace from generated.d.ts
    App: 'readonly',
};

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'storage/**',
            'bootstrap/cache/**',
            'resources/js/types/generated.d.ts',
            'resources/js/generated/**',
        ],
    },
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    // TypeScript rules for .ts files only
    {
        files: ['**/*.ts'],
        languageOptions: {
            parser: tsParser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: browserGlobals,
        },
        plugins: {
            '@typescript-eslint': tsPlugin,
        },
        rules: {
            ...tsPlugin.configs.recommended.rules,
        },
    },
    // TypeScript rules for .vue files — vue-eslint-parser is the main parser,
    // tsParser is used for the <script> block via parserOptions.parser
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: tsParser,
                ecmaVersion: 'latest',
                sourceType: 'module',
                extraFileExtensions: ['.vue'],
            },
            globals: browserGlobals,
        },
        plugins: {
            '@typescript-eslint': tsPlugin,
        },
        rules: {
            ...tsPlugin.configs.recommended.rules,
            'vue/multi-word-component-names': 'off',
        },
    },
    prettier,
];
