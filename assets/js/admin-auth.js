(function () {
  async function fetchSession() {
    try {
      const res = await fetch('../php/admin_session.php', { cache: 'no-store' });
      if (!res.ok) return { authenticated: false };
      return await res.json();
    } catch (_) {
      return { authenticated: false };
    }
  }

  async function logoutAndRedirect() {
    try {
      await fetch('../php/admin_logout.php', { method: 'POST' });
    } catch (_) {
      // Ignore network issues and still redirect.
    }
    window.location.href = 'login.html';
  }

  function injectLogoutButton(username) {
    const topbar = document.querySelector('.topbar');
    if (!topbar || topbar.querySelector('[data-admin-logout]')) return;

    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.alignItems = 'center';
    wrap.style.gap = '10px';

    const name = document.createElement('span');
    name.textContent = username ? ('Admin: ' + username) : 'Admin';
    name.style.fontSize = '.82rem';
    name.style.color = 'var(--mid)';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Logout';
    btn.setAttribute('data-admin-logout', '1');
    btn.style.padding = '8px 12px';
    btn.style.borderRadius = '8px';
    btn.style.border = '1px solid rgba(0,0,0,.12)';
    btn.style.background = '#fff';
    btn.style.fontSize = '.82rem';
    btn.style.fontWeight = '600';
    btn.style.cursor = 'pointer';
    btn.addEventListener('click', logoutAndRedirect);

    wrap.appendChild(name);
    wrap.appendChild(btn);
    topbar.appendChild(wrap);
  }

  async function initLoginPage() {
    const form = document.getElementById('adminLoginForm');
    if (!form) return;

    const session = await fetchSession();
    if (session && session.authenticated) {
      window.location.href = 'dashboard.html';
      return;
    }

    const userInput = document.getElementById('adminUsername');
    const passInput = document.getElementById('adminPassword');
    const status = document.getElementById('loginStatus');
    const submit = document.getElementById('adminLoginBtn');

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (!userInput || !passInput || !status || !submit) return;

      const username = String(userInput.value || '').trim();
      const password = String(passInput.value || '').trim();

      if (!username || !password) {
        status.textContent = 'Please enter username and password.';
        status.style.color = '#b91c1c';
        return;
      }

      submit.disabled = true;
      submit.textContent = 'Signing in...';
      status.textContent = '';

      try {
        const res = await fetch('../php/admin_login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username: username, password: password }),
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          status.textContent = data.message || 'Login failed.';
          status.style.color = '#b91c1c';
          return;
        }

        const next = new URLSearchParams(window.location.search).get('next') || 'dashboard.html';
        window.location.href = next;
      } catch (_) {
        status.textContent = 'Network error. Please try again.';
        status.style.color = '#b91c1c';
      } finally {
        submit.disabled = false;
        submit.textContent = 'Login';
      }
    });
  }

  async function initAdminPageGuard() {
    const session = await fetchSession();
    if (!session || !session.authenticated) {
      const next = encodeURIComponent((window.location.pathname.split('/').pop() || 'dashboard.html') + window.location.search);
      window.location.href = 'login.html?next=' + next;
      return;
    }

    injectLogoutButton(String(session.username || 'admin'));
  }

  const path = window.location.pathname.toLowerCase();
  const isAdminPath = path.indexOf('/admin/') !== -1;
  const isLogin = path.endsWith('/admin/login.html');

  if (!isAdminPath) return;

  if (isLogin) {
    initLoginPage();
  } else {
    initAdminPageGuard();
  }
})();
