export default {
  minification: {
    minifyJS: false,
    minifyCSS: false,
    sameOriginOnly: true,
    exclusions: {
      js: [],
      css: [],
    },
  },
  concatenation: {
    concatenateJS: false,
    concatenateCSS: false,
    sameOriginOnly: true,
    exclusions: {
      js: [],
      css: [],
    },
  },
  imageOptimization: {
    autoOptimize: true,
    keepOriginalFiles: true,
    preferredImageEditor: 'auto',
    formats: {
      'image/jpeg': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/png': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/gif': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/svg+xml': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/webp': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/avif': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/heif': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
      'image/heic': {
        shouldOptimize: true,
        convertTo: 'image/webp',
      },
    },
    quality: 75,
    exclusions: [],
  },
  edge: {
    cacheTagsToPurge: [],
    urisToPurge: [],
  },
  logging: {
    debugMode: false,
  },
};
