import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

// https://vite.dev/config/
export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const deployBase = env.VITE_DEPLOY_BASE || '/premium-react/';

  return {
    base: command === 'build' ? deployBase : '/',
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
  };
});
