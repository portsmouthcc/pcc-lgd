document.querySelectorAll('.menu-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const container = btn.closest('.menu-item');
    const children = container.querySelector('.menu-children');

    const isOpen = children.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', isOpen);
  });
});
