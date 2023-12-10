module.exports = {
    "root": true,
    'overrides': [{
        'files': '.eslintrc.cjs',
        'plugins': ['@stylistic/migrate'],
        'rules': {
            '@stylistic/migrate/migrate-js': 'error',
            '@stylistic/migrate/migrate-ts': 'error'
        }
    }, {
        'files': '*',
        'excludedFiles': '.eslintrc.cjs',
        'parserOptions': {
            'parser': '@typescript-eslint/parser',
            'project': './tsconfig.json'
        },
        'settings': {
            'import/resolver': {
                'typescript': true,
                'node': true
            }
        },
        'extends': [
            'eslint:recommended',
            'plugin:vue/vue3-recommended',
            '@vue/typescript/recommended',
            'plugin:import/recommended',
            'plugin:import/typescript'
            // https://github.com/vuejs/eslint-config-typescript/issues/29
            // "plugin:@typescript-eslint/recommended-requiring-type-checking"
        ],
        'rules': {
            'import/no-unresolved': [2, { 'ignore': ['\\.(svg|gif|avifs)$'] }],
            'import/no-useless-path-segments': 'error',

            // as of eslint 8.6.0
            'no-await-in-loop': 'error',
            'no-promise-executor-return': 'error',
            'no-template-curly-in-string': 'error',
            'no-unreachable-loop': 'error',
            'no-unsafe-optional-chaining': 'error',
            'no-useless-backreference': 'error',
            'require-atomic-updates': 'error',
            'accessor-pairs': 'error',
            'array-callback-return': 'error',
            'complexity': ['error', { 'max': 30 }],
            'consistent-return': 'error',
            'curly': ['error', 'multi-or-nest', 'consistent'],
            'default-case-last': 'error',
            'dot-location': ['error', 'property'],
            'eqeqeq': 'error',
            'grouped-accessor-pairs': ['error', 'getBeforeSet'],
            'guard-for-in': 'error',
            'max-classes-per-file': 'error',
            'no-alert': 'error',
            'no-case-declarations': 'error',
            'no-constructor-return': 'error',
            'no-else-return': 'error',
            // "no-empty-function": "error",
            'no-eval': 'error',
            'no-extra-bind': 'error',
            'no-floating-decimal': 'error',
            'no-implicit-coercion': 'error',
            'no-implicit-globals': 'error',
            'no-labels': 'error',
            'no-lone-blocks': 'error',
            'no-multi-spaces': 'error',
            'no-multi-str': 'error',
            'no-new': 'error',
            'no-new-func': 'error',
            'no-new-wrappers': 'error',
            'no-nonoctal-decimal-escape': 'error',
            'no-octal-escape': 'error',
            'no-param-reassign': 'error',
            'no-proto': 'error',
            'no-return-assign': 'error',
            'no-script-url': 'error',
            'no-self-compare': 'error',
            'no-sequences': 'error',
            'no-unmodified-loop-condition': 'error',
            'no-useless-call': 'error',
            'no-useless-concat': 'error',
            'no-useless-escape': 'error',
            'no-useless-return': 'error',
            'prefer-promise-reject-errors': 'error',
            'prefer-regex-literals': ['error', { 'disallowRedundantWrapping': true }],
            'radix': ['error', 'as-needed'],
            'require-unicode-regexp': 'error',
            'vars-on-top': 'error',
            'wrap-iife': ['error', 'inside'],
            'yoda': 'error',
            'strict': 'error',
            'no-undef-init': 'error',
            'array-bracket-newline': ['error', 'consistent'],
            'array-bracket-spacing': 'error',
            'array-element-newline': ['error', 'consistent'],
            'block-spacing': 'error',
            'capitalized-comments': ['error', 'never'],
            'comma-style': 'error',
            'computed-property-spacing': 'error',
            'consistent-this': 'error',
            'eol-last': 'error',
            'func-name-matching': 'error',
            'function-call-argument-newline': ['error', 'consistent'],
            'function-paren-newline': ['error', 'consistent'],
            'jsx-quotes': 'error',
            'key-spacing': ['error', {
                'beforeColon': false,
                'afterColon': true,
                'mode': 'strict'
            }],
            'linebreak-style': 'error',
            'max-statements-per-line': ['error', { 'max': 2 }],
            'multiline-comment-style': ['error', 'separate-lines'],
            'multiline-ternary': ['error', 'always-multiline'],
            'new-cap': 'error',
            'new-parens': 'error',
            // "newline-per-chained-call": ["error", { "ignoreChainWithDepth": 3 }],
            'no-array-constructor': 'error',
            'no-bitwise': 'error',
            'no-continue': 'error',
            'no-lonely-if': 'error',
            'no-mixed-operators': 'error',
            'no-multi-assign': 'error',
            'no-multiple-empty-lines': 'error',
            'no-negated-condition': 'error',
            'no-nested-ternary': 'error',
            'no-new-object': 'error',
            'no-tabs': 'error',
            'no-trailing-spaces': 'error',
            'no-unneeded-ternary': 'error',
            'no-whitespace-before-property': 'error',
            'nonblock-statement-body-position': 'error',
            'object-curly-newline': ['error', {
                'multiline': true,
                'consistent': true
            }],
            'object-property-newline': ['error', { 'allowAllPropertiesOnSameLine': true }],
            'one-var': ['error', 'never'],
            'operator-assignment': 'error',
            'operator-linebreak': ['error', 'before'],
            'padded-blocks': ['error', 'never'],
            'prefer-exponentiation-operator': 'error',
            'prefer-object-spread': 'error',
            'quote-props': ['error', 'as-needed'],
            'semi-spacing': 'error',
            'semi-style': 'error',
            // "sort-keys": ["error", "asc", { "caseSensitive": false, "natural": true }],
            'sort-vars': ['error', { 'ignoreCase': true }],
            'space-before-blocks': 'error',
            'space-in-parens': 'error',
            'space-unary-ops': ['error', {
                'words': true,
                'nonwords': false
            }],
            'spaced-comment': 'error',
            'switch-colon-spacing': 'error',
            'template-tag-spacing': 'error',
            'unicode-bom': 'error',
            'arrow-body-style': 'error',
            'arrow-parens': ['error', 'as-needed'],
            'arrow-spacing': 'error',
            'generator-star-spacing': ['error', {
                'before': false,
                'after': true,
                'method': {
                    'before': true,
                    'after': false
                }
            }],
            'no-confusing-arrow': 'error',
            'no-new-symbol': 'error',
            'no-useless-computed-key': ['error', { 'enforceForClassMembers': true }],
            'no-useless-rename': 'error',
            'no-var': 'error',
            'object-shorthand': 'error',
            'prefer-arrow-callback': 'error',
            'prefer-const': 'error',
            'prefer-destructuring': 'error',
            'prefer-numeric-literals': 'error',
            'prefer-rest-params': 'error',
            'prefer-spread': 'error',
            'prefer-template': 'error',
            'rest-spread-spacing': 'error',
            'sort-imports': ['error', { 'ignoreDeclarationSort': true }],
            'symbol-description': 'error',
            'template-curly-spacing': 'error',
            'yield-star-spacing': 'error',
            'prefer-object-has-own': 'error',

            // as of @typescript-eslint 5.9.0
            '@typescript-eslint/no-empty-function': 'off',

            'no-void': 'off',
            'brace-style': 'off',
            '@typescript-eslint/brace-style': ['error', '1tbs', { 'allowSingleLine': true }],
            'comma-dangle': 'off',
            '@typescript-eslint/comma-dangle': 'error',
            'comma-spacing': 'off',
            '@typescript-eslint/comma-spacing': 'error',
            'default-param-last': 'off',
            '@typescript-eslint/default-param-last': 'error',
            'dot-notation': 'off',
            '@typescript-eslint/dot-notation': 'error',
            'func-call-spacing': 'off',
            '@typescript-eslint/func-call-spacing': 'error',
            'indent': 'off',
            '@typescript-eslint/indent': 'error',
            'init-declarations': 'off',
            '@typescript-eslint/init-declarations': 'error',
            'keyword-spacing': 'off',
            '@typescript-eslint/keyword-spacing': 'error',
            'lines-between-class-members': 'off',
            '@typescript-eslint/lines-between-class-members': 'error',
            'no-dupe-class-members': 'off',
            '@typescript-eslint/no-dupe-class-members': 'error',
            'no-extra-parens': 'off',
            '@typescript-eslint/no-extra-parens': ['error', 'all', {
                'ignoreJSX': 'multi-line',
                'enforceForArrowConditionals': false
            }],
            'no-invalid-this': 'off',
            '@typescript-eslint/no-invalid-this': 'error',
            'no-loop-func': 'off',
            '@typescript-eslint/no-loop-func': 'error',
            'no-loss-of-precision': 'off',
            '@typescript-eslint/no-loss-of-precision': 'error',
            'no-redeclare': 'off',
            '@typescript-eslint/no-redeclare': 'error',
            'no-shadow': 'off',
            '@typescript-eslint/no-shadow': ['error', {
                'builtinGlobals': true,
                'hoist': 'all',
                'allow': ['name']
            }],
            'no-throw-literal': 'off',
            '@typescript-eslint/no-throw-literal': 'error',
            'no-unused-expressions': 'off',
            '@typescript-eslint/no-unused-expressions': 'error',
            'no-use-before-define': 'off',
            '@typescript-eslint/no-use-before-define': 'error',
            'no-useless-constructor': 'off',
            '@typescript-eslint/no-useless-constructor': 'error',
            'object-curly-spacing': 'off',
            '@typescript-eslint/object-curly-spacing': ['error', 'always'],
            'quotes': 'off',
            '@typescript-eslint/quotes': ['error', 'single', { 'avoidEscape': true }],
            'no-return-await': 'off',
            '@typescript-eslint/return-await': 'error',
            'semi': 'off',
            '@typescript-eslint/semi': ['error', 'always', { 'omitLastInOneLineBlock': true }],
            'space-before-function-paren': 'off',
            '@typescript-eslint/space-before-function-paren': ['error', {
                'anonymous': 'always',
                'named': 'never',
                'asyncArrow': 'always'
            }],
            'space-infix-ops': 'off',
            '@typescript-eslint/space-infix-ops': ['error', { 'int32Hint': false }],
            'require-await': 'off',
            '@typescript-eslint/require-await': 'error',
            'padding-line-between-statements': 'off',
            '@typescript-eslint/padding-line-between-statements': ['error'],

            '@typescript-eslint/array-type': ['error', {
                'default': 'array-simple',
                'readonly': 'array-simple'
            }],
            '@typescript-eslint/class-literal-property-style': 'error',
            '@typescript-eslint/consistent-indexed-object-style': 'error',
            '@typescript-eslint/consistent-type-assertions': 'error',
            '@typescript-eslint/consistent-type-definitions': ['error', 'interface'],
            '@typescript-eslint/consistent-type-imports': 'error',
            '@typescript-eslint/explicit-member-accessibility': 'error',
            '@typescript-eslint/member-delimiter-style': ['error', {
                'multiline': {
                    'delimiter': 'comma',
                    'requireLast': false
                },
                'singleline': {
                    'delimiter': 'comma',
                    'requireLast': false
                }
            }],
            '@typescript-eslint/member-ordering': 'error',
            '@typescript-eslint/method-signature-style': 'error',
            'camelcase': 'off',
            '@typescript-eslint/naming-convention': ['error', {
                'selector': 'default',
                'format': ['camelCase']
            }, {
                'selector': 'objectLiteralProperty',
                'format': ['camelCase', 'PascalCase'] // vue component
            }, {
                'selector': ['objectLiteralProperty', 'objectLiteralMethod'],
                'format': null,
                'filter': { 'regex': '^\\w+:\\w+$', 'match': true } // vue event names in component.emit
            }, {
                'selector': ['function', 'variable', 'import'],
                'format': ['camelCase', 'PascalCase'] // vue component
            }, {
                'selector': 'import',
                'format': null,
                'filter': { 'regex': '^_$', 'match': true } // lodash
            }, {
                'selector': 'parameter',
                'format': ['camelCase'],
                'leadingUnderscore': 'allow'
            }, {
                'selector': 'memberLike',
                'modifiers': ['private'],
                'format': ['camelCase'],
                'leadingUnderscore': 'require'
            }, {
                'selector': 'typeLike',
                'format': ['PascalCase']
            }],
            '@typescript-eslint/no-base-to-string': 'error',
            '@typescript-eslint/no-confusing-void-expression': 'error',
            '@typescript-eslint/no-dynamic-delete': 'error',
            '@typescript-eslint/no-extraneous-class': 'error',
            '@typescript-eslint/no-invalid-void-type': 'error',
            '@typescript-eslint/no-require-imports': 'error',
            '@typescript-eslint/no-unnecessary-boolean-literal-compare': 'error',
            '@typescript-eslint/no-unnecessary-condition': 'error',
            '@typescript-eslint/no-unnecessary-qualifier': 'error',
            '@typescript-eslint/no-unnecessary-type-arguments': 'error',
            '@typescript-eslint/no-unnecessary-type-constraint': 'error',
            '@typescript-eslint/non-nullable-type-assertion-style': 'error',
            '@typescript-eslint/prefer-enum-initializers': 'error',
            '@typescript-eslint/prefer-for-of': 'error',
            '@typescript-eslint/prefer-function-type': 'error',
            '@typescript-eslint/prefer-includes': 'error',
            '@typescript-eslint/prefer-literal-enum-member': 'error',
            '@typescript-eslint/prefer-nullish-coalescing': 'error',
            '@typescript-eslint/prefer-optional-chain': 'error',
            '@typescript-eslint/prefer-readonly': 'error',
            '@typescript-eslint/prefer-reduce-type-parameter': 'error',
            '@typescript-eslint/prefer-string-starts-ends-with': 'error',
            '@typescript-eslint/prefer-ts-expect-error': 'error',
            '@typescript-eslint/promise-function-async': 'error',
            '@typescript-eslint/require-array-sort-compare': 'error',
            '@typescript-eslint/strict-boolean-expressions': 'error',
            '@typescript-eslint/switch-exhaustiveness-check': 'error',
            '@typescript-eslint/type-annotation-spacing': 'error',
            '@typescript-eslint/unified-signatures': 'error',
            '@typescript-eslint/no-unsafe-argument': 'error',
            '@typescript-eslint/prefer-return-this-type': 'error',
            '@typescript-eslint/no-non-null-asserted-nullish-coalescing': 'error',
            '@typescript-eslint/consistent-type-exports': 'error',

            // as of eslint-plugin-vue 8.2.0
            'vue/html-indent': ['error', 4],
            'vue/max-attributes-per-line': 'off',
            'vue/no-reserved-component-names': 'off', // for component in antdv
            'vue/attribute-hyphenation': ['error', 'never'],
            'vue/singleline-html-element-content-newline': 'off',
            'vue/attributes-order': ['error', {
                'order': ['DEFINITION', ['LIST_RENDERING', 'UNIQUE'], 'CONDITIONALS', 'TWO_WAY_BINDING', 'RENDER_MODIFIERS', 'SLOT', 'EVENTS', 'OTHER_DIRECTIVES', 'GLOBAL', 'OTHER_ATTR', 'CONTENT']
            }],
            'vue/multi-word-component-names': 'off',
            'vue/first-attribute-linebreak': ['error', {
                'singleline': 'beside',
                'multiline': 'beside'
            }],
            'vue/html-closing-bracket-newline': ['error', {
                'singleline': 'never',
                'multiline': 'never'
            }],
            'vue/html-self-closing': ['error', { 'html': { 'void': 'always' } }],
            'vue/v-on-event-hyphenation': ['error', 'never', { 'autofix': true }],
            'vue/require-default-prop': 'off',
            'vue/multiline-html-element-content-newline': 'off',

            'vue/block-tag-newline': 'error',
            'vue/component-api-style': ['error', ['script-setup', 'composition']],
            'vue/component-name-in-template-casing': 'error',
            'vue/component-options-name-casing': 'error',
            'vue/custom-event-name-casing': ['error', 'camelCase'],
            'vue/html-comment-content-spacing': 'error',
            'vue/html-comment-indent': ['error', 4],
            'vue/no-child-content': 'error',
            'vue/no-duplicate-attr-inheritance': 'error',
            'vue/no-empty-component-block': 'error',
            'vue/no-expose-after-await': 'error',
            'vue/no-invalid-model-keys': 'error',
            'vue/no-multiple-objects-in-class': 'error',
            'vue/no-undef-components': 'error',
            'vue/no-useless-mustaches': 'error',
            'vue/no-useless-v-bind': 'error',
            'vue/no-v-text': 'error',
            'vue/padding-line-between-blocks': 'error',
            'vue/prefer-separate-static-class': 'error',
            'vue/require-direct-export': 'error',
            'vue/require-emit-validator': 'error',
            // "vue/require-expose": "error",
            // "vue/static-class-names-order": "error",
            'vue/v-for-delimiter-style': 'error',
            'vue/v-on-function-call': 'error',
            'vue/array-bracket-newline': ['error', 'consistent'],
            'vue/array-bracket-spacing': 'error',
            'vue/arrow-spacing': 'error',
            'vue/block-spacing': 'error',
            'vue/brace-style': ['error', '1tbs', { 'allowSingleLine': true }],
            'vue/comma-dangle': 'error',
            'vue/comma-spacing': 'error',
            'vue/comma-style': 'error',
            'vue/dot-location': ['error', 'property'],
            'vue/dot-notation': 'error',
            'vue/eqeqeq': 'error',
            'vue/func-call-spacing': 'error',
            'vue/key-spacing': ['error', {
                'beforeColon': false,
                'afterColon': true,
                'mode': 'strict'
            }],
            'vue/keyword-spacing': 'error',
            'vue/no-constant-condition': 'error',
            'vue/no-empty-pattern': 'error',
            'vue/no-extra-parens': ['error', 'all', {
                'ignoreJSX': 'multi-line',
                'enforceForArrowConditionals': false
            }],
            'vue/no-irregular-whitespace': 'error',
            'vue/no-loss-of-precision': 'error',
            'vue/no-sparse-arrays': 'error',
            'vue/no-useless-concat': 'error',
            'vue/object-curly-newline': ['error', {
                'multiline': true,
                'consistent': true
            }],
            'vue/object-curly-spacing': ['error', 'always'],
            'vue/object-property-newline': ['error', { 'allowAllPropertiesOnSameLine': true }],
            'vue/operator-linebreak': ['error', 'before'],
            'vue/prefer-template': 'error',
            'vue/space-in-parens': 'error',
            'vue/space-infix-ops': ['error', { 'int32Hint': false }],
            'vue/space-unary-ops': ['error', {
                'words': true,
                'nonwords': false
            }],
            'vue/template-curly-spacing': 'error'
        }
    }]
};
