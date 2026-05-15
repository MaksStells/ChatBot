(function() {
  // Create the toast container
  function getContainer() {
    var el = document.getElementById('achievement-toast-container');
    if (!el) {
      el = document.createElement('div');
      el.id = 'achievement-toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  // Show one toast
  window.showAchievementToast = function(badge) {
    var container = getContainer();
    var toast = document.createElement('div');
    toast.className = 'achievement-toast';
    toast.innerHTML =
      '<div class="achievement-toast-icon">' + (badge.icon || '🏆') + '</div>' +
      '<div class="achievement-toast-body">' +
        '<div class="achievement-toast-label">Achievement Unlocked</div>' +
        '<div class="achievement-toast-name">' + (badge.name || 'Badge') + '</div>' +
        '<div class="achievement-toast-desc">' + (badge.desc || '') + '</div>' +
      '</div>';

    container.appendChild(toast);

    // Auto-dismiss after 4.5s
    var dismissTimer = setTimeout(function() { dismissToast(toast); }, 4500);

    // Click to dismiss
    toast.addEventListener('click', function() {
      clearTimeout(dismissTimer);
      dismissToast(toast);
    });
  };

  function dismissToast(toast) {
    if (toast.classList.contains('hiding')) return;
    toast.classList.add('hiding');
    toast.addEventListener('animationend', function() {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    });
  }

  // Show multiple toasts with stagger
  window.showAchievementToasts = function(badges) {
    if (!badges || !badges.length) return;
    badges.forEach(function(badge, i) {
      setTimeout(function() {
        window.showAchievementToast(badge);
      }, i * 700);
    });
  };

  // all daily_login to pick up new badges
  document.addEventListener('DOMContentLoaded', function() {
    fetch('stats.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'daily_login' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data && data.new_badges && data.new_badges.length) {
        window.showAchievementToasts(data.new_badges);
      }
    })
    .catch(function() {});
  });
})();
