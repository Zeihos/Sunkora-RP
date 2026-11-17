(() => {
  'use strict';
  const toggle = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('.header-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    });
  }
  document.querySelectorAll('[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!window.confirm(form.dataset.confirm || 'Подтвердить действие?')) event.preventDefault();
    });
  });
  document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      const input = button.parentElement?.querySelector('[data-copy-source]');
      if (!input) return;
      try {
        await navigator.clipboard.writeText(input.value);
        const old = button.textContent;
        button.textContent = '✓ Скопировано';
        setTimeout(() => button.textContent = old, 1600);
      } catch {
        input.select();
        button.textContent = 'Выделено — нажмите Ctrl+C';
      }
    });
  });
  document.querySelectorAll('[data-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
  });
})();
