const path = require('path');
const defaults = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaults,
  entry: {
    'bundle.admin': path.resolve(process.cwd(), 'src', 'admin', 'index.js'),
    'bundle.client': path.resolve(process.cwd(), 'src', 'client', 'index.js'),
    'bundle.feedback': path.resolve(process.cwd(), 'src', 'feedback', 'index.js'),
  },
  output: {
    filename: '[name].js',
    path: path.resolve(process.cwd(), 'public'),
  },
  externals: {
    react: 'React',
    'react-dom': 'ReactDOM',
  },
  resolve: {
    alias: {
      common: path.resolve(__dirname, 'src', 'admin', 'common'),
      components: path.resolve(__dirname, 'src', 'admin', 'components'),
      hooks: path.resolve(__dirname, 'src', 'admin', 'hooks'),
      store: path.resolve(__dirname, 'src', 'admin', 'store'),
    },
    extensions: [...(defaults.resolve ? defaults.resolve.extensions || ['.js', 'jsx'] : [])],
  },
};
