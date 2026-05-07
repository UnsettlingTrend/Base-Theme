import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

const target = process.env.COVE_TARGET || 'editor';

const entries = {
  'react-shared': resolve(__dirname, 'src/react-shared-entry.js'),
  editor: resolve(__dirname, 'src/cove-editor-entry.js'),
  renderer: resolve(__dirname, 'src/cove-renderer-entry.js'),
};

const isShared = target === 'react-shared';
const reactPath = resolve(__dirname, 'node_modules/react');
const reactDomPath = resolve(__dirname, 'node_modules/react-dom');

export default defineConfig({
  plugins: [react()],
  resolve: {
    dedupe: ['react', 'react-dom', 'react/jsx-runtime', 'react/jsx-dev-runtime'],
    alias: {
      'react/jsx-runtime': resolve(reactPath, 'jsx-runtime.js'),
      'react/jsx-dev-runtime': resolve(reactPath, 'jsx-dev-runtime.js'),
      'react-dom/client': resolve(reactDomPath, 'client.js'),
      'react-dom': reactDomPath,
      'react': reactPath,
    },
  },
  build: {
    outDir: resolve(__dirname, '../../js/vendor'),
    emptyOutDir: false,
    rollupOptions: {
      input: entries[target],
      output: {
        entryFileNames: 'cove-' + target + '-bundle.js',
        assetFileNames: 'cove-' + target + '-bundle[extname]',
        format: 'iife',
        inlineDynamicImports: true,
        ...(isShared ? {} : {
          globals: {
            'react': 'React',
            'react-dom': 'ReactDOM',
            'react-dom/client': 'ReactDOM',
            'react/jsx-runtime': 'React',
            'react/jsx-dev-runtime': 'React',
          },
        }),
      },
      ...(isShared ? {} : {
        external: (id) => /^react(-dom)?(\/.*)?$/.test(id),
      }),
    },
    minify: true,
    cssCodeSplit: false,
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
});
