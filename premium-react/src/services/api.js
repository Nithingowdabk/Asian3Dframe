const APP_BASE = (import.meta.env.VITE_APP_BASE || '/gift4you').replace(/\/$/, '');
const API_BASE = import.meta.env.DEV ? '/php' : `${APP_BASE}/php`;

function asArrayImage(imageField) {
  if (Array.isArray(imageField)) return imageField.filter(Boolean);
  const raw = String(imageField || '').trim();
  if (!raw) return [];
  if (raw.startsWith('[')) {
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) return parsed.filter(Boolean);
    } catch (_err) {}
  }
  return [raw];
}

export function resolveMedia(path) {
  const raw = String(path || '').trim();
  if (!raw) return 'https://placehold.co/900x1200/f7f4ef/8e775c?text=Frame';
  if (/^https?:\/\//i.test(raw)) return raw;
  const cleaned = raw.replace(/^\/+/, '');
  return import.meta.env.DEV ? `/${cleaned}` : `${APP_BASE}/${cleaned}`;
}

async function getJson(url) {
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(`Request failed: ${res.status}`);
  return res.json();
}

function normalizeProduct(product) {
  const images = asArrayImage(product?.images?.length ? product.images : product?.image);
  const categoryName = String(product?.category_name || product?.category || 'Frame').trim();

  return {
    id: Number(product?.id || 0),
    name: String(product?.name || 'Untitled Frame'),
    description: String(product?.description || ''),
    price: Number(product?.price || 0),
    oldPrice: Number(product?.old_price || 0),
    categoryId: Number(product?.category_id || 0),
    category: categoryName,
    isBestSeller: Boolean(product?.is_best_seller),
    image: resolveMedia(images[0]),
    images: images.map(resolveMedia),
  };
}

export async function fetchProducts() {
  const data = await getJson(`${API_BASE}/get_products.php`);
  if (!data.success) throw new Error(data.message || 'Unable to load products');
  return (data.products || []).map(normalizeProduct);
}

export async function fetchProductById(id) {
  const data = await getJson(`${API_BASE}/get_products.php?id=${encodeURIComponent(id)}`);
  if (!data.success || !data.product) throw new Error(data.message || 'Product not found');
  return normalizeProduct(data.product);
}

export async function fetchCategories() {
  const data = await getJson(`${API_BASE}/get_categories.php`);
  if (!data.success) return [];
  return (data.categories || []).map((item) => ({
    id: Number(item?.id || 0),
    name: String(item?.name || 'Category'),
    image: resolveMedia(item?.image),
  }));
}

export async function placeOrder(payload) {
  const response = await fetch(`${API_BASE}/place_order.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!data.success) throw new Error(data.message || 'Unable to place order');
  return data;
}
