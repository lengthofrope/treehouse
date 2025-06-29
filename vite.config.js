import { defineConfig } from 'vite'
import tailwindcss from 'tailwindcss'
import autoprefixer from 'autoprefixer'

export default defineConfig({
  // Build configuration
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js'
      }
    }
  },
  
  // Development server configuration
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: false,
    cors: true,
    hmr: {
      host: 'localhost'
    }
  },
  
  // CSS configuration
  css: {
    postcss: {
      plugins: [
        tailwindcss,
        autoprefixer
      ]
    }
  },
  
  // Public directory - set to false to avoid conflict with outDir
  publicDir: false,
  
  // Base URL for assets
  base: process.env.NODE_ENV === 'production' ? '/build/' : '/'
})