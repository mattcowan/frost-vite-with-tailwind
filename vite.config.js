import { defineConfig } from 'vite';
import path from 'path';
import removeConsole from 'vite-plugin-remove-console';

// Vite passes `{ command, mode }` to this config function:
// - command: "serve" (dev) or "build" (production bundle). We use this to gate console removal.
// - mode: Vite mode string (e.g. "development", "production"). Currently unused, kept for future config.
export default defineConfig(({ command, mode }) => ({
  plugins: [
    // Only remove console statements in production builds
    // Selectively removes console.log/debug/trace while preserving console.error/warn
    ...(command === 'build' ? [
      removeConsole({
        includes: ['log', 'debug', 'trace'], // Remove console.log/debug/trace, keep error/warn
      }),
    ] : []),
  ],
  build: {
    // Output to dist directory
    outDir: 'dist',
    // Empty the directory before building
    emptyOutDir: true,
    // Generate manifest for WordPress
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/js/main.js'),
        style: path.resolve(__dirname, 'src/css/main.css'),
      },
      output: {
        // Output files with hash for cache busting
        entryFileNames: '[name].[hash].js',
        chunkFileNames: '[name].[hash].js',
        assetFileNames: '[name].[hash].[ext]',
      },
    },
    // Minify for production using esbuild (default, faster than terser)
    minify: 'esbuild',
    esbuildOptions: {
      drop: ['debugger'], // Remove debugger statements only (console handled by plugin)
    },
    // Source maps for debugging
    sourcemap: false,
  },
  css: {
    postcss: './postcss.config.js',
  },
  // Dev server settings for Hot Module Replacement (HMR)
  // Run with: npm run dev
  server: {
    host: '127.0.0.1', // Default dev server binding (theme supports IPv6 in production)
    port: 3000,
    strictPort: false, // If port 3000 is busy, try next available port.
    cors: true, // Enable CORS for WordPress integration
    origin: 'http://localhost:3000', // Explicit origin for HMR client
    hmr: {
      host: 'localhost', // HMR websocket host
      protocol: 'ws', // Use WebSocket protocol (not wss for local dev)
    },
  },
}));
