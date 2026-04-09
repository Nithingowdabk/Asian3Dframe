import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { fetchProductById } from '../services/api';
import { useCart } from '../store/CartContext';
import { formatInr } from '../utils/money';

const MOBILE_INFO_IMAGES = [
  '/uploads/WhatsApp%20Image%202026-04-09%20at%2010.12.19%20AM.jpeg',
  '/uploads/WhatsApp%20Image%202026-04-09%20at%2010.12.19%20AM%20(1).jpeg',
];

export default function ProductDetailPage() {
  const { id } = useParams();
  const { addItem } = useCart();
  const [product, setProduct] = useState(null);
  const [active, setActive] = useState('');

  useEffect(() => {
    fetchProductById(id)
      .then((item) => {
        setProduct(item);
        setActive(item.images[0] || item.image);
      })
      .catch(() => setProduct(null));
  }, [id]);

  if (!product) {
    return (
      <section className="shell state-box">
        <h2>Loading product...</h2>
      </section>
    );
  }

  return (
    <section className="shell detail-layout">
      <article className="detail-media">
        <img className="detail-main" src={active || product.image} alt={product.name} />
        <div className="detail-thumbs">
          {product.images.map((src) => (
            <button key={src} onClick={() => setActive(src)} className={active === src ? 'is-active' : ''}>
              <img src={src} alt="Preview" />
            </button>
          ))}
        </div>
      </article>

      <article className="detail-copy">
        <p className="eyebrow">{product.category}</p>
        <h1>{product.name}</h1>
        <p className="detail-price">{formatInr(product.price)}</p>
        <p className="detail-desc">{product.description || 'Premium handcrafted frame with refined finish.'}</p>
        <div className="hero-actions">
          <button className="btn-solid" onClick={() => addItem(product, 1)}>Add to Cart</button>
          <Link to="/checkout" className="btn-ghost">Buy Now</Link>
        </div>
      </article>

      <article className="mobile-info-card">
        <h3>What Is Mobile Frame?</h3>
        <div className="mobile-info-grid">
          {MOBILE_INFO_IMAGES.map((src) => (
            <img key={src} src={src} alt="Mobile frame reference" loading="lazy" />
          ))}
        </div>
        <p>LED photo frame with bright screen-like finish, premium glow, and standout modern look.</p>
      </article>
    </section>
  );
}
