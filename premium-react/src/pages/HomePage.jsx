import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import ProductCard from '../components/ProductCard';
import { fetchCategories, fetchProducts } from '../services/api';

export default function HomePage() {
  const [categories, setCategories] = useState([]);
  const [products, setProducts] = useState([]);

  useEffect(() => {
    fetchCategories().then(setCategories).catch(() => setCategories([]));
    fetchProducts()
      .then((items) => setProducts(items.slice(0, 4)))
      .catch(() => setProducts([]));
  }, []);

  return (
    <>
      <section className="hero-premium shell">
        <div className="hero-copy">
          <p className="eyebrow">Luxury Display Frames</p>
          <h1>Turn your best moments into gallery-grade statement pieces.</h1>
          <p>
            A complete premium redesign in React with fluid interactions, rich typography,
            and a modern storefront feel.
          </p>
          <div className="hero-actions">
            <Link className="btn-solid" to="/products">
              Explore Collection
            </Link>
            <Link className="btn-ghost" to="/checkout">
              Quick Checkout
            </Link>
          </div>
        </div>

        <div className="hero-stack">
          <div className="frame-card floating-a" />
          <div className="frame-card floating-b" />
          <div className="frame-card floating-c" />
        </div>
      </section>

      <section className="shell">
        <div className="section-head-row">
          <h2>Shop by Category</h2>
          <Link to="/products">View all</Link>
        </div>
        <div className="category-row">
          {categories.map((cat) => (
            <Link key={cat.id} to={`/products?category=${cat.id}`} className="category-chip-card">
              <img src={cat.image} alt={cat.name} loading="lazy" />
              <span>{cat.name}</span>
            </Link>
          ))}
        </div>
      </section>

      <section className="shell showcase-grid">
        <div className="section-head-row">
          <h2>Featured Picks</h2>
        </div>
        <div className="tiles-grid">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>
    </>
  );
}
