import { Link, NavLink } from 'react-router-dom';
import { useCart } from '../store/CartContext';

export default function NavBar() {
  const { count } = useCart();

  return (
    <header className="top-nav">
      <div className="shell nav-inner">
        <Link to="/" className="brand-mark" aria-label="Gift4You Home">
          <span className="brand-dot" />
          <div>
            <p>Gift4You</p>
            <small>Premium Frames</small>
          </div>
        </Link>

        <nav className="main-links">
          <NavLink to="/">Home</NavLink>
          <NavLink to="/products">Products</NavLink>
          <NavLink to="/checkout">Checkout</NavLink>
        </nav>

        <Link to="/cart" className="cart-pill" aria-label="Open cart">
          Cart <span>{count}</span>
        </Link>
      </div>
    </header>
  );
}
