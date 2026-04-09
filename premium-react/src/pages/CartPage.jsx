import { Link } from 'react-router-dom';
import { useCart } from '../store/CartContext';
import { formatInr } from '../utils/money';

export default function CartPage() {
  const { items, amount, removeItem, updateQty } = useCart();

  return (
    <section className="shell cart-layout">
      <h1>Your Cart</h1>
      {!items.length ? (
        <div className="state-box">
          <p>Cart is empty.</p>
          <Link className="btn-solid" to="/products">Continue Shopping</Link>
        </div>
      ) : (
        <>
          <div className="cart-list">
            {items.map((item) => (
              <article key={item.id} className="cart-row">
                <img src={item.image} alt={item.name} />
                <div>
                  <h3>{item.name}</h3>
                  <p>{formatInr(item.price)}</p>
                </div>
                <input
                  type="number"
                  min="1"
                  max="99"
                  value={item.quantity}
                  onChange={(event) => updateQty(item.id, Number(event.target.value))}
                />
                <button className="danger-link" onClick={() => removeItem(item.id)}>Remove</button>
              </article>
            ))}
          </div>
          <aside className="cart-total-card">
            <h3>Total</h3>
            <p>{formatInr(amount)}</p>
            <Link to="/checkout" className="btn-solid">Proceed to Checkout</Link>
          </aside>
        </>
      )}
    </section>
  );
}
