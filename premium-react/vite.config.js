import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/gift4you/premium-react/dist/' : '/',
  plugins: [react()],
  server: {
    proxy: {
      '/php': {
        target: 'http://localhost/gift4you',
        changeOrigin: true,
      },
      '/assets': {
        target: 'http://localhost/gift4you',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://localhost/gift4you',
        changeOrigin: true,
      },
    },
  },
}));
