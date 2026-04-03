(function () {
  const unifiedCss = `
    .sidebar {
      width: 260px !important;
      background: linear-gradient(180deg, #1a1510 0%, #2e2010 100%) !important;
      color: #fff !important;
    }

    .sidebar .logo,
    .sidebar .sidebar-logo {
      padding: 28px 24px !important;
      border-bottom: 1px solid rgba(255,255,255,.08) !important;
      font-family: 'Playfair Display', serif !important;
      font-size: 1.4rem !important;
      font-weight: 700 !important;
      color: #fff !important;
      display: flex !important;
      align-items: center !important;
      gap: 8px !important;
      line-height: 1.2;
    }

    .sidebar .logo span,
    .sidebar .sidebar-logo span {
      color: #e8c09a !important;
    }

    .sidebar .nav,
    .sidebar .sidebar-nav {
      padding: 20px 0 !important;
    }

    .sidebar .nav a,
    .sidebar .nav-item a {
      display: flex !important;
      align-items: center !important;
      gap: 12px !important;
      padding: 12px 24px !important;
      font-size: .88rem !important;
      color: rgba(255,255,255,.65) !important;
      border-left: 3px solid transparent !important;
      text-decoration: none !important;
      transition: all .25s ease !important;
    }

    .sidebar .nav a:hover,
    .sidebar .nav-item a:hover,
    .sidebar .nav a.active,
    .sidebar .nav-item a.active {
      color: #fff !important;
      background: rgba(255,255,255,.06) !important;
      border-left-color: #c8956c !important;
    }

    .sidebar .nav a i,
    .sidebar .nav-item a i {
      width: 18px;
      text-align: center;
    }

    .sidebar .sidebar-footer {
      padding: 16px 24px !important;
      border-top: 1px solid rgba(255,255,255,.08) !important;
      font-size: .78rem !important;
      color: rgba(255,255,255,.45) !important;
    }
  `;

  const linkGroups = [
    {
      title: 'Main',
      links: [
        { href: 'dashboard.html', icon: 'fa-th-large', text: 'Dashboard' },
        { href: 'orders.html', icon: 'fa-shopping-bag', text: 'Orders' },
      ],
    },
    {
      title: 'Products',
      links: [
        { href: 'add-product.html', icon: 'fa-plus-circle', text: 'Add Product' },
        { href: 'products.html', icon: 'fa-box', text: 'Manage Products' },
        { href: 'albums.html', icon: 'fa-folder-open', text: 'Manage Albums' },
        { href: 'categories.html', icon: 'fa-tags', text: 'Manage Categories' },
      ],
    },
    {
      title: 'Site',
      links: [
        { href: 'dashboard.html?view=admins', icon: 'fa-user-shield', text: 'Manage Admins', id: 'showAdminManage' },
        { href: '../index.html', icon: 'fa-external-link-alt', text: 'View Site', target: '_blank' },
      ],
    },
  ];

  function ensureCss() {
    if (document.getElementById('adminSidebarUnifyStyle')) return;
    const style = document.createElement('style');
    style.id = 'adminSidebarUnifyStyle';
    style.textContent = unifiedCss;
    document.head.appendChild(style);
  }

  function currentPageName() {
    const file = (location.pathname.split('/').pop() || '').toLowerCase();
    return file || 'dashboard.html';
  }

  function ensureSidebar() {
    let sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      if (!sidebar.id) sidebar.id = 'sidebar';
      return sidebar;
    }

    const created = document.createElement('aside');
    created.className = 'sidebar';
    created.id = 'sidebar';

    const main = document.querySelector('.main, main.main, .container');
    if (main && main.parentNode) {
      main.parentNode.insertBefore(created, main);
    } else {
      document.body.insertBefore(created, document.body.firstChild);
    }

    return created;
  }

  function buildSidebar(sidebar) {
    const page = currentPageName();

    const navHtml = linkGroups.map((group) => {
      const linksHtml = group.links.map((link) => {
        const isAdminsView = link.href.startsWith('dashboard.html?view=admins') && page === 'dashboard.html' && location.search.includes('view=admins');
        const isActive = (link.href === page) || isAdminsView;
        const targetAttr = link.target ? ` target="${link.target}"` : '';
        const idAttr = link.id ? ` id="${link.id}"` : '';
        return `<li class="nav-item"><a href="${link.href}"${targetAttr}${idAttr}${isActive ? ' class="active"' : ''}><i class="fas ${link.icon}"></i> ${link.text}</a></li>`;
      }).join('');

      return `<div class="nav-section-title">${group.title}</div>${linksHtml}`;
    }).join('');

    sidebar.innerHTML = `
      <div class="sidebar-logo">🎁 Asian<span>3D Frames</span></div>
      <nav class="sidebar-nav">${navHtml}</nav>
      <div class="sidebar-footer">Asian3DFrames Admin © 2026</div>
    `;
  }

  function run() {
    const sidebar = ensureSidebar();

    ensureCss();
    buildSidebar(sidebar);

    document.dispatchEvent(new CustomEvent('adminSidebarReady'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
