import { createContext, useContext, useEffect, useMemo, useState } from 'react';

const CartContext = createContext(null);
const STORAGE_KEY = 'gift4you_react_cart';

export function CartProvider({ children }) {
  const [items, setItems] = useState(() => {
    try {
      const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(raw) ? raw : [];
    } catch (_err) {
      return [];
    }
  });

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }, [items]);

  const addItem = (product, quantity = 1) => {
    setItems((prev) => {
      const existing = prev.find((it) => it.id === product.id);
      if (existing) {
        return prev.map((it) =>
          it.id === product.id ? { ...it, quantity: Math.min(99, it.quantity + quantity) } : it
        );
      }
      return [...prev, { ...product, quantity: Math.max(1, quantity) }];
    });
  };

  const removeItem = (id) => setItems((prev) => prev.filter((it) => it.id !== id));
  const clearCart = () => setItems([]);

  const updateQty = (id, quantity) => {
    const next = Math.max(1, Math.min(99, Number(quantity) || 1));
    setItems((prev) => prev.map((it) => (it.id === id ? { ...it, quantity: next } : it)));
  };

  const totals = useMemo(() => {
    const count = items.reduce((sum, it) => sum + Number(it.quantity || 0), 0);
    const amount = items.reduce((sum, it) => sum + Number(it.price || 0) * Number(it.quantity || 0), 0);
    return { count, amount };
  }, [items]);

  const value = useMemo(
    () => ({ items, addItem, removeItem, clearCart, updateQty, ...totals }),
    [items, totals]
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCart must be used inside CartProvider');
  return ctx;
}
