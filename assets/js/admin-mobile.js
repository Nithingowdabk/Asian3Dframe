(function () {
  var initialized = false;

  function setupMobileSidebar() {
    if (initialized) return;
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    initialized = true;

    var existingBtn = document.querySelector('.admin-mobile-menu-btn');
    if (!existingBtn) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'admin-mobile-menu-btn';
      btn.setAttribute('aria-label', 'Open menu');
      btn.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
      document.body.appendChild(btn);

      btn.addEventListener('click', function () {
        document.body.classList.toggle('admin-sidebar-open');
      });
    }

    var existingBackdrop = document.querySelector('.admin-mobile-backdrop');
    if (!existingBackdrop) {
      var backdrop = document.createElement('div');
      backdrop.className = 'admin-mobile-backdrop';
      document.body.appendChild(backdrop);

      backdrop.addEventListener('click', function () {
        document.body.classList.remove('admin-sidebar-open');
      });
    }

    document.querySelectorAll('.sidebar a').forEach(function (link) {
      link.addEventListener('click', function () {
        document.body.classList.remove('admin-sidebar-open');
      });
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) {
        document.body.classList.remove('admin-sidebar-open');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupMobileSidebar);
  } else {
    setupMobileSidebar();
  }

  document.addEventListener('adminSidebarReady', setupMobileSidebar);
})();
