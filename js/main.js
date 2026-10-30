/* =========================================
   RIPPLE – main.js
   Shared utility functions across all pages
   ========================================= */

/**
 * Show a Bootstrap-style alert inside a container element
 * @param {HTMLElement} container  - The element to inject the alert into
 * @param {string}      type       - 'success' | 'danger' | 'warning' | 'info'
 * @param {string}      message    - The message to display
 * @param {number}      duration   - Auto-dismiss after ms (0 = never)
 */
function showAlert(container, type, message, duration = 4000) {
  container.innerHTML = `
    <div class="alert alert-${type} d-flex align-items-center gap-2 fade show" role="alert">
      ${message}
    </div>`;
  if (duration > 0) {
    setTimeout(() => {
      const alert = container.querySelector('.alert');
      if (alert) alert.classList.remove('show');
      setTimeout(() => { container.innerHTML = ''; }, 300);
    }, duration);
  }
}

/**
 * Get initials from a full name (max 2 chars)
 * @param {string} name
 * @returns {string}
 */
function getInitials(name) {
  if (!name) return '?';
  return name.split(' ')
    .map(w => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

/**
 * Format a MySQL datetime string to a friendly relative time
 * @param {string} datetime - e.g. "2024-10-15 14:23:00"
 * @returns {string}
 */
function timeAgo(datetime) {
  const now  = new Date();
  const then = new Date(datetime);
  const diff = Math.floor((now - then) / 1000); // seconds

  if (diff < 60)         return 'just now';
  if (diff < 3600)       return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400)      return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 2592000)    return `${Math.floor(diff / 86400)}d ago`;
  return then.toLocaleDateString();
}

/**
 * Logout – clear session and redirect to login
 */
function logout() {
  sessionStorage.removeItem('ripple_user');
  window.location.href = 'login.html';
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth() {
  const user = sessionStorage.getItem('ripple_user');
  if (!user) {
    window.location.href = 'login.html';
    return null;
  }
  try { return JSON.parse(user); }
  catch { window.location.href = 'login.html'; return null; }
}