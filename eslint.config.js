import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';

export default [
    {
        ignores: [
            'node_modules/**',
            'public/build/**',
            'public/hot',
            'storage/**',
            'vendor/**',
            'resources/js/types/generated.d.ts',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
    },
    {
        files: ['**/*.{ts,vue}'],
        languageOptions: {
            globals: {
                // App namespace is declared globally in resources/js/types/generated.d.ts
                App: 'readonly',
            },
        },
        rules: {
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn',
            'no-restricted-syntax': [
                'error',
                {
                    selector: "CallExpression[callee.name='ref']",
                    message:
                        "Vue 'ref' is forbidden for application logic / cross-component state. Use props/emits, Inertia shared props, Pinia, or composables. 'ref' is allowed only for unavoidable imperative DOM access — disable this rule per-line with a justification comment.",
                },
            ],
        },
    },
    prettier,
];
