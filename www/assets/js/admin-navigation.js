(() => {
  const sidebar = document.querySelector('.admin-sidebar');
  const toggle = sidebar?.querySelector('.admin-sidebar__toggle');
  const navigation = sidebar?.querySelector('.admin-sidebar__nav');

  if (!sidebar || !toggle || !navigation) {
    return;
  }

  const layout = document.querySelector('.admin-layout');
  const desktop = window.matchMedia('(min-width: 901px)');
  const icon = toggle.querySelector('span');
  const storageKey = 'maringotka-admin-sidebar-collapsed';

  let collapsed = false;

  try {
    collapsed = window.localStorage.getItem(storageKey) === '1';
  } catch (_) {
    // Storage may be disabled; navigation remains usable.
  }

  const update = () => {
    const isDesktop = desktop.matches;
    const mobileOpen = sidebar.classList.contains('admin-sidebar--open');

    layout?.classList.toggle('admin-layout--collapsed', isDesktop && collapsed);

    if (isDesktop) {
      toggle.setAttribute('aria-expanded', String(!collapsed));
      toggle.setAttribute('aria-label', collapsed ? 'Rozbaliť bočné menu' : 'Zbaliť bočné menu');
      icon.textContent = collapsed ? '›' : '‹';
    } else {
      toggle.setAttribute('aria-expanded', String(mobileOpen));
      toggle.setAttribute('aria-label', mobileOpen ? 'Zavrieť menu' : 'Otvoriť menu');
      icon.textContent = mobileOpen ? '×' : '☰';
    }
  };

  const closeMobile = () => {
    sidebar.classList.remove('admin-sidebar--open');
    update();
  };

  toggle.addEventListener('click', () => {
    if (desktop.matches) {
      collapsed = !collapsed;

      try {
        window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
      } catch (_) {
        // Keep the state for this page even if storage is unavailable.
      }
    } else {
      sidebar.classList.toggle('admin-sidebar--open');
    }

    update();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !desktop.matches) {
      closeMobile();
    }
  });

  document.addEventListener('click', (event) => {
    if (!desktop.matches && !sidebar.contains(event.target)) {
      closeMobile();
    }
  });

  desktop.addEventListener('change', () => {
    sidebar.classList.remove('admin-sidebar--open');
    update();
  });

  const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
  for (const link of navigation.querySelectorAll('a')) {
    const path = new URL(link.href).pathname.replace(/\/$/, '') || '/';
    const isExact = !link.hash && currentPath === path;
    const isSection = !link.hash && path !== '/admin' && currentPath.startsWith(path + '/');

    if (isExact || isSection) {
      link.setAttribute('aria-current', 'page');
    }
  }

  update();
  sidebar.classList.add('admin-sidebar--ready');
})();
