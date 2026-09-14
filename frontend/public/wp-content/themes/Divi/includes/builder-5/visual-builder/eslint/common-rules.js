const commonRules = {
  'jsdoc/no-types':                        'off',
  'jsdoc/check-example-error':             'off',
  'jsdoc/check-examples':                  'off',
  'jsdoc/check-alignment':                 'off',
  'jsdoc/require-returns-description':     'off',
  'jsx-a11y/label-has-associated-control': 'off',
  'func-style':                            'off',
  'sort-imports':                          'off',
  'import/no-named-as-default-member':     'off',
  'import/prefer-default-export':          'off',
  'jsx-a11y/media-has-caption':            'off',
  'max-classes-per-file':                  'off',
  'react/jsx-props-no-multi-spaces':       'off',
  'import/no-cycle':                       'error',
  'no-loss-of-precision':                  'off',
  'no-unsafe-optional-chaining':           'off',
  'no-useless-backreference':              'off',
  'no-nonoctal-decimal-escape':            'off',
  // NOTE: no-multi-spaces, function-paren-newline, key-spacing, and max-len removed.
  // Prettier now handles all formatting rules.
  'align-assignments/align-assignments': 'off', // Prettier handles formatting.
  'import/newline-after-import':         'off', // Prettier handles formatting.
  yoda: [
    'error',
    'always',
    {
      onlyEquality: true,
    },
  ],
  'no-empty': [
    'error',
    {
      allowEmptyCatch: true,
    },
  ],
  'jsdoc/check-tag-names': [
    'error',
    {
      // Always allow our predefined tags, regardless
      // of the jsdoc/check-tag-names rule defaults.
      definedTags: [
        'see',
        'link',
        'since',
        'deprecated',
        'group',
        'remarks',
        'private',
        'privateRemarks',
        'param',
        'returns',
        'example',
      ],
    },
  ],
  'jsdoc/require-returns':        ['error'],
  'jsdoc/no-undefined-types':     ['error', { definedTypes: ['unknown', 'JQuery'] }],
  'react/jsx-filename-extension': [
    1,
    {
      extensions: [
        '.tsx',
        '.jsx',
      ],
    },
  ],
  'import/extensions': ['error', 'ignorePackages', {
    js:  'never',
    jsx: 'never',
    ts:  'never',
    tsx: 'never',
  }],
  'import/no-extraneous-dependencies': ['error', {
    // Tests deps aren't needed in the build, hence they're listed in devDependencies.
    devDependencies: [
      '/webpack/**/*.{ts,tsx,js,jsx}',
      '/test/**/*.{ts,tsx,js,jsx}',
      '**/test/**/*.{ts,tsx,js,jsx}',
      '**/tests/**/*.{ts,tsx,js,jsx}',
    ],
  }],
  // NOTE: max-len rule removed - Prettier handles line length automatically.
  'simple-import-sort/imports': [
    'error',
    {
      groups: [
        // Node.js builtins.
        [
          '^(assert|buffer|child_process|cluster|console|constants|crypto|dgram|dns|domain|events|fs|http|https|module|net|os|path|punycode|querystring|readline|repl|stream|string_decoder|sys|timers|tls|tty|url|util|vm|zlib|freelist|v8|process|async_hooks|http2|perf_hooks)(/.*|$)',
        ],

        // External dependencies.
        // React related packages come first.
        [
          '^react',
          '^@?\\w',
        ],

        // WordPress dependencies.
        [
          '^@wordpress/(.*)$',
        ],

        // Internal dependencies.
        [
          '^@divi/(.*)$',
        ],

        // Local dependencies.
        // Parent imports.
        // Siblings imports.
        // Styles imports.
        [
          '^\\.\\.(?!/?$)',
          '^\\.\\./?$',
          '^\\./(?=.*/)(?!/?$)',
          '^\\.(?!/?$)',
          '^\\./?$',
          '^.+\\.s?css$',
        ],

        // Side effect imports.
        // Import something that doesn't export anything.
        ['^\\u0000'],
      ],
    },
  ],
  'no-shadow': ['error', { ignoreOnInitialization: true }],
  'function-call-argument-newline': ['error', 'consistent'],
  'align-assignments/align-assignments': 'off', // Prettier handles formatting.
  'import/newline-after-import':         'off', // Prettier handles formatting.
};

module.exports = { commonRules };
