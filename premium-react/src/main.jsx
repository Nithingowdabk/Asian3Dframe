import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App.jsx';
import { CartProvider } from './store/CartContext';
import './index.css';
import './styles/theme.css';

function getRouterBaseName() {
  const raw = String(import.meta.env.BASE_URL || '/');
  const trimmed = raw.replace(/^\/+|\/+$/g, '');
  return trimmed ? `/${trimmed}` : '/';
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter basename={getRouterBaseName()}>
      <CartProvider>
        <App />
      </CartProvider>
    </BrowserRouter>
  </StrictMode>
);
