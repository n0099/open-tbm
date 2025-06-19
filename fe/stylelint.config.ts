import type { Config } from 'stylelint';

export default {
    extends: [
        'stylelint-config-standard',
        'stylelint-config-standard-vue',
    ],
    overrides: [{
        files: ['**/*.css', '**/*.vue'],
        rules: {
            'rule-empty-line-before': null,
            'comment-empty-line-before': null,
            'at-rule-empty-line-before': null,
            'declaration-empty-line-before': 'never',
            'custom-property-empty-line-before': 'never',
        },
    }],
} as Config;
