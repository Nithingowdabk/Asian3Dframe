import { Link } from 'react-router-dom';

export default function Footer() {
  return (
    <footer className="footer-band">
      <div className="shell footer-content">
        <div>
          <p>Designed for stories worth framing.</p>
          <nav className="footer-links" aria-label="Footer links">
            <Link to="/">Home</Link>
            <Link to="/products">Products</Link>
            <Link to="/cart">Cart</Link>
            <Link to="/checkout">Checkout</Link>
          </nav>
        </div>
        <small>Gift4You 2026</small>
      </div>
    </footer>
  );
}
