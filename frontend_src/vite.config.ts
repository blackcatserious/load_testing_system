import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

const backendTarget = process.env.VITE_API_PROXY_TARGET || 'http://localhost:4000'
const isHttpsTarget = backendTarget.startsWith('https://')

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: backendTarget,
        changeOrigin: isHttpsTarget,
        secure: isHttpsTarget
      }
    }
  },
  build: {
    outDir: 'dist',
    sourcemap: true
  }
})
