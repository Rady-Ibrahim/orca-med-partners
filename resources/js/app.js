import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');

    toggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));
});
