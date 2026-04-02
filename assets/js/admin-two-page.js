(function () {
  var file = (window.location.pathname.split('/').pop() || '').toLowerCase();
  if (!file || file === 'dashboard.html' || file === 'login.html') return;

  var viewMap = {
    'orders.html': 'orders',
    'add-product.html': 'add',
    'products.html': 'products',
    'albums.html': 'albums',
    'categories.html': 'categories',
    'manage-prices.html': 'manageprices',
    'admin-manage.html': 'admins'
  };

  var params = new URLSearchParams(window.location.search);
  var embedded = params.get('embedded') === '1';

  function applyEmbeddedModeClass() {
    document.documentElement.classList.add('admin-embedded');
    if (document.body) {
      document.body.classList.add('admin-embedded');
    }
  }

  if (!embedded && window.top === window.self) {
    var view = viewMap[file] || 'dashboard';
    window.location.replace('dashboard.html?view=' + encodeURIComponent(view));
    return;
  }

  if (embedded || window.top !== window.self) {
    applyEmbeddedModeClass();
    document.addEventListener('DOMContentLoaded', applyEmbeddedModeClass);
    window.addEventListener('load', applyEmbeddedModeClass);
  }
})();
