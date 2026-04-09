import { useState } from 'react';
import { placeOrder } from '../services/api';
import { useCart } from '../store/CartContext';
import { formatInr } from '../utils/money';

export default function CheckoutPage() {
  const { items, amount, clearCart } = useCart();
  const [status, setStatus] = useState('');
  const [loading, setLoading] = useState(false);

  const submitOrder = async (event) => {
    event.preventDefault();
    if (!items.length) return;

    const form = new FormData(event.currentTarget);
    const payload = {
      name: String(form.get('name') || '').trim(),
      phone: String(form.get('phone') || '').trim(),
      address: String(form.get('address') || '').trim(),
      items: items.map((item) => ({ id: item.id, quantity: item.quantity })),
    };

    setLoading(true);
    setStatus('Placing order...');

    try {
      await placeOrder(payload);
      clearCart();
      setStatus('Order placed successfully. We will contact you shortly.');
      event.currentTarget.reset();
    } catch (err) {
      setStatus(err.message || 'Order failed.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <section className="shell checkout-layout">
      <article>
        <h1>Checkout</h1>
        <p className="checkout-total">Payable: {formatInr(amount)}</p>
        <form className="checkout-form" onSubmit={submitOrder}>
          <input name="name" required placeholder="Full name" />
          <input name="phone" required placeholder="Phone number" />
          <textarea name="address" required placeholder="Delivery address" rows={4} />
          <button className="btn-solid" type="submit" disabled={loading || !items.length}>
            {loading ? 'Processing...' : 'Place Order'}
          </button>
        </form>
        {status && <p className="status-line">{status}</p>}
      </article>

      <aside className="checkout-summary">
        <h3>Order Summary</h3>
        {items.map((item) => (
          <div className="summary-line" key={item.id}>
            <span>{item.name} x {item.quantity}</span>
            <strong>{formatInr(item.price * item.quantity)}</strong>
          </div>
        ))}
      </aside>
    </section>
  );
}
