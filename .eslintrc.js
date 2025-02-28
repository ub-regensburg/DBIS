module.exports = {
  env: {
    browser: true,
    es2021: true,
  },
  extends: [
    'airbnb-base',
  ],
  parserOptions: {
    ecmaVersion: 13,
    sourceType: 'module',
  },
  rules: {
      // this solves an issue, where js would be an unexpected file type on 
      // webpack imports
      'import/extensions': [0, {"js": "always"}] 
  },
  settings: {
      'import/resolver': {
          'webpack': {
              'extensions': ['js'],
              'config': './dbis_server/webpack.common.js'
          }
      },
      'import/extensions': ['js']
  }
};
