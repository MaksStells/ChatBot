
(function () {
  'use strict';

  var drawer  = document.getElementById('mobile-drawer');
  var overlay = document.getElementById('drawer-overlay');
  var openBtn = document.getElementById('hamburger-btn');
  var closeBtn = document.getElementById('drawer-close');

  function openDrawer() {
    if (!drawer || !overlay) return;
    drawer.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (!drawer || !overlay) return;
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (openBtn)  openBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (overlay)  overlay.addEventListener('click', closeDrawer);

  var touchStartX = 0;

  if (drawer) {
    drawer.addEventListener('touchstart', function(e) {
      touchStartX = e.touches[0].clientX;
    }, { passive: true });

    drawer.addEventListener('touchmove', function(e) {
      var dx = e.touches[0].clientX - touchStartX;
      if (dx < -40) {
        closeDrawer();
      }
    }, { passive: true });
  }

  window.mobileOpenDrawer  = openDrawer;
  window.mobileCloseDrawer = closeDrawer;

})();
