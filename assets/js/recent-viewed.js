/* Asian3DFrames - Floating Recently Viewed panel (products only) */
(() => {
  'use strict';

  const STORAGE_KEY = 'g4y_recent_seen';
  const STYLE_ID = 'g4y-recent-viewed-style';

  const readItems = () => {
    try {
      const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      if (!Array.isArray(raw)) return [];
      return raw
        .filter((item) => item && String(item.type || '') === 'product' && Number(item.id || 0) > 0)
        .sort((a, b) => Number(b.seen_at || 0) - Number(a.seen_at || 0));
    } catch (_err) {
      return [];
    }
  };

  const writeItems = (items) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, 12)));
  };

  const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const ensureStyles = () => {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = `
      .g4y-recent-btn {
        position: fixed;
        right: 18px;
        bottom: 22px;
        z-index: 10020;
        border: 0;
        border-radius: 999px;
        width: 50px;
        height: 50px;
        padding: 0;
        color: #fff;
        background: linear-gradient(135deg, #1a1510, #3d2912);
        box-shadow: 0 12px 30px rgba(0,0,0,.25);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        overflow: hidden;
      }
      .g4y-recent-ring {
        position: absolute;
        inset: -11px;
        pointer-events: none;
        z-index: 0;
      }
      .g4y-recent-ring svg {
        width: 100%;
        height: 100%;
        display: block;
      }
      .g4y-recent-ring text {
        font: 700 5px/1 Inter, Arial, sans-serif;
        letter-spacing: .95px;
        fill: rgba(255,255,255,.9);
      }
      .g4y-recent-icon {
        position: absolute;
        inset: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        opacity: .35;
        z-index: 1;
        pointer-events: none;
      }
      .g4y-recent-count {
        position: relative;
        z-index: 2;
        min-width: 26px;
        height: 26px;
        border-radius: 999px;
        font: 700 12px/1 Inter, Arial, sans-serif;
        background: rgba(200,149,108,.92);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.28);
      }
      .g4y-recent-panel {
        position: fixed;
        top: 0;
        right: 0;
        width: min(360px, 92vw);
        height: 100vh;
        z-index: 10030;
        background: #fff;
        box-shadow: -16px 0 34px rgba(0,0,0,.2);
        transform: translateX(104%);
        transition: transform .28s ease;
        display: flex;
        flex-direction: column;
      }
      .g4y-recent-panel.open {
        transform: translateX(0);
      }
      .g4y-recent-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.32);
        z-index: 10025;
        opacity: 0;
        pointer-events: none;
        transition: opacity .22s ease;
      }
      .g4y-recent-overlay.open {
        opacity: 1;
        pointer-events: auto;
      }
      .g4y-recent-head {
        padding: 14px 14px;
        border-bottom: 1px solid rgba(0,0,0,.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
      }
      .g4y-recent-title {
        margin: 0;
        font: 700 16px/1.2 'Playfair Display', Georgia, serif;
        color: #1a1510;
      }
      .g4y-recent-actions {
        display: inline-flex;
        gap: 8px;
      }
      .g4y-recent-action {
        border: 1px solid rgba(0,0,0,.14);
        border-radius: 8px;
        background: #fff;
        color: #374151;
        font: 600 12px/1 Inter, Arial, sans-serif;
        padding: 7px 10px;
        cursor: pointer;
      }
      .g4y-recent-list {
        padding: 12px;
        overflow: auto;
        display: grid;
        gap: 10px;
      }
      .g4y-recent-item {
        display: grid;
        grid-template-columns: 60px 1fr;
        gap: 10px;
        align-items: center;
        text-decoration: none;
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 10px;
        padding: 8px;
      }
      .g4y-recent-item img {
        width: 60px;
        height: 75px;
        border-radius: 8px;
        object-fit: cover;
        background: #f3f4f6;
      }
      .g4y-recent-name {
        margin: 0;
        font: 600 13px/1.35 Inter, Arial, sans-serif;
        color: #111827;
      }
      .g4y-recent-sub {
        margin: 2px 0 0;
        font: 500 11px/1.25 Inter, Arial, sans-serif;
        color: #6b7280;
      }
      .g4y-recent-empty {
        color: #6b7280;
        font: 500 13px/1.4 Inter, Arial, sans-serif;
        padding: 12px;
      }
      @media (max-width: 760px) {
        .g4y-recent-btn {
          left: 12px;
          right: auto;
          bottom: calc(96px + env(safe-area-inset-bottom));
          width: 46px;
          height: 46px;
        }
        .g4y-recent-ring {
          inset: -10px;
        }
        .g4y-recent-ring text {
          font-size: 4.6px;
          letter-spacing: .86px;
        }
      }
    `;
    document.head.appendChild(style);
  };

  const createUi = () => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'g4y-recent-btn';
    button.innerHTML = '<span class="g4y-recent-ring" aria-hidden="true"><svg viewBox="0 0 100 100" focusable="false"><defs><path id="g4yRecentRingPath" d="M50,50 m-38,0 a38,38 0 1,1 76,0 a38,38 0 1,1 -76,0"></path></defs><text><textPath href="#g4yRecentRingPath" startOffset="0%">RECENTLY VIEWED • RECENTLY VIEWED • </textPath></text></svg></span><span class="g4y-recent-icon" aria-hidden="true"><i class="fas fa-clock-rotate-left"></i></span><span class="g4y-recent-count" id="g4yRecentCount">0</span>';

    const overlay = document.createElement('div');
    overlay.className = 'g4y-recent-overlay';

    const panel = document.createElement('aside');
    panel.className = 'g4y-recent-panel';
    panel.innerHTML = `
      <div class="g4y-recent-head">
        <h3 class="g4y-recent-title">Recently Viewed</h3>
        <div class="g4y-recent-actions">
          <button type="button" class="g4y-recent-action" id="g4yRecentClear">Clear</button>
          <button type="button" class="g4y-recent-action" id="g4yRecentClose">Close</button>
        </div>
      </div>
      <div class="g4y-recent-list" id="g4yRecentList"></div>
    `;

    document.body.appendChild(button);
    document.body.appendChild(overlay);
    document.body.appendChild(panel);

    return {
      button,
      overlay,
      panel,
      count: panel.ownerDocument.getElementById('g4yRecentCount'),
      list: panel.ownerDocument.getElementById('g4yRecentList'),
      close: panel.ownerDocument.getElementById('g4yRecentClose'),
      clear: panel.ownerDocument.getElementById('g4yRecentClear'),
    };
  };

  const init = () => {
    ensureStyles();
    const ui = createUi();

    const render = () => {
      const items = readItems();
      const deduped = [];
      const seen = new Set();
      items.forEach((item) => {
        const id = Number(item.id || 0);
        if (!id || seen.has(id)) return;
        seen.add(id);
        deduped.push(item);
      });

      const visible = deduped.slice(0, 10);
      if (ui.count) ui.count.textContent = String(visible.length);

      if (!visible.length) {
        ui.list.innerHTML = '<div class="g4y-recent-empty">No recently viewed products yet.</div>';
        return;
      }

      ui.list.innerHTML = visible.map((item) => {
        const id = Number(item.id || 0);
        const name = escapeHtml(item.title || 'Product');
        const sub = escapeHtml(item.subtitle || 'Viewed product');
        const img = escapeHtml(item.image || 'https://placehold.co/120x150/f8f8f8/b7b7b7?text=Product');
        const href = `product.html?id=${encodeURIComponent(id)}`;

        return `
          <a class="g4y-recent-item" href="${href}">
            <img src="${img}" alt="${name}" onerror="this.src='https://placehold.co/120x150/f8f8f8/b7b7b7?text=Product';" />
            <div>
              <p class="g4y-recent-name">${name}</p>
              <p class="g4y-recent-sub">${sub}</p>
            </div>
          </a>
        `;
      }).join('');
    };

    const open = () => {
      ui.panel.classList.add('open');
      ui.overlay.classList.add('open');
      render();
    };

    const close = () => {
      ui.panel.classList.remove('open');
      ui.overlay.classList.remove('open');
    };

    ui.button.addEventListener('click', () => {
      if (ui.panel.classList.contains('open')) close();
      else open();
    });

    ui.close.addEventListener('click', close);
    ui.overlay.addEventListener('click', close);

    ui.clear.addEventListener('click', () => {
      const current = readItems();
      const nonProducts = current.filter((item) => String(item?.type || '') !== 'product');
      writeItems(nonProducts);
      render();
      window.dispatchEvent(new Event('g4y-recent-updated'));
    });

    window.addEventListener('g4y-recent-updated', render);
    window.addEventListener('storage', (e) => {
      if (e.key === STORAGE_KEY) render();
    });

    render();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
