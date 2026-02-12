import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    lib: {
      entry: resolve(__dirname, 'src/index.ts'),
      name: 'BotovisWidget',
      formats: ['es', 'umd', 'iife'],
      fileName: (format) => {
        if (format === 'iife') return 'botovis-widget.iife.js';
        if (format === 'umd') return 'botovis-widget.umd.cjs';
        return 'botovis-widget.js';
      },
    },
    minify: 'esbuild',
    sourcemap: true,
    outDir: 'dist',
  },
});
