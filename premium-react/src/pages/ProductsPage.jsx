import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import ProductCard from '../components/ProductCard';
import { fetchCategories, fetchProducts } from '../services/api';

export default function ProductsPage() {
  const [search] = useSearchParams();
  const [all, setAll] = useState([]);
  const [categories, setCategories] = useState([]);
  const [selected, setSelected] = useState(search.get('category') || 'all');

  useEffect(() => {
    fetchProducts().then(setAll).catch(() => setAll([]));
    fetchCategories().then(setCategories).catch(() => setCategories([]));
  }, []);

  const filtered = useMemo(() => {
    if (selected === 'all') return all;
    const byId = Number(selected);
    const cat = categories.find((c) => c.id === byId)?.name?.toLowerCase() || '';
    return all.filter(
      (item) => item.categoryId === byId || item.category.toLowerCase() === cat
    );
  }, [all, selected, categories]);

  return (
    <section className="shell products-page">
      <div className="section-head-row">
        <h1>Premium Collection</h1>
        <p>{filtered.length} items</p>
      </div>

      <div className="filter-bar">
        <button onClick={() => setSelected('all')} className={selected === 'all' ? 'is-active' : ''}>All</button>
        {categories.map((cat) => (
          <button
            key={cat.id}
            onClick={() => setSelected(String(cat.id))}
            className={selected === String(cat.id) ? 'is-active' : ''}
          >
            {cat.name}
          </button>
        ))}
      </div>

      <div className="tiles-grid">
        {filtered.map((product) => (
          <ProductCard key={product.id} product={product} />
        ))}
      </div>
    </section>
  );
}
