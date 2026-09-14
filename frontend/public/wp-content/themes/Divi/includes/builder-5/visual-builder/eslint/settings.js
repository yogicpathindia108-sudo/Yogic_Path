const path = require('path');

const settings = {
  'import/resolver': {
    'eslint-import-resolver-custom-alias': {
      alias: {
        '@langchain/langgraph/web': path.resolve(__dirname, '../node_modules/@langchain/langgraph/dist/web.js'),
        '@langchain/langgraph/prebuilt': path.resolve(
          __dirname,
          '../node_modules/@langchain/langgraph/dist/prebuilt/index.js',
        ),
        '@langchain/core/singletons': path.resolve(
          __dirname,
          '../node_modules/@langchain/core/dist/singletons/index.js',
        ),
        langsmith$: path.resolve(__dirname, '../node_modules/langsmith/dist/index.js'),
      },
    },
    node: {
      extensions: ['.js', '.jsx', '.ts', '.tsx'],
      moduleDirectory: ['node_modules', 'src/'],
    },
    webpack: {
      config: path.resolve(__dirname, '../webpack.config.babel.js'),
    },
  },
  'import/extensions': ['.js', '.jsx', '.ts', '.tsx'],
};

module.exports = { settings };
