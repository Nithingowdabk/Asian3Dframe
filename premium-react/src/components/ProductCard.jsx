import { Link } from 'react-router-dom';
import { formatInr } from '../utils/money';

export default function ProductCard({ product }) {
  return (
    <article className="product-tile">
      <Link to={`/product/${product.id}`} className="tile-link">
        <div className="tile-image-wrap">
          {product.isBestSeller && <span className="badge">Best Seller</span>}
          <img src={product.image} alt={product.name} loading="lazy" />
        </div>
        <div className="tile-body">
          <p className="tile-category">{product.category}</p>
          <h3>{product.name}</h3>
          <div className="tile-price">{formatInr(product.price)}</div>
        </div>
      </Link>
    </article>
  );
}
