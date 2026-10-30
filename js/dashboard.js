/* =========================================
   RIPPLE – dashboard.js  (v2 – full replies, DB likes, real notifications)
   ========================================= */

// ─── SafePost: Banned Word List ──────────────────────────────────────────────
const bannedWords = [
  "abuse", "abusive", "hate", "threat", "threats",
  "harass", "harassment", "violence", "offensive",
  "slur", "racist", "stupid", "idiot", "moron",
  "kill", "murder", "attack", "bully", "bullying",
  "badword1", "badword2"
];

// ─── State ─────────────────────────────────────────────────────────────────
let currentUser     = null;
let selectedTopic   = 'general';
let currentFilter   = 'all';
let allPosts        = [];
let activeReplyPost = null;

// ─── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  currentUser = requireAuth();
  if (!currentUser) return;

  document.getElementById('userDisplayName').textContent = currentUser.name;
  document.getElementById('userAvatar').textContent      = getInitials(currentUser.name);
  document.getElementById('createAvatar').textContent    = getInitials(currentUser.name);

  fetchPosts();
  loadNotificationBadge();
  initPresence();
});

// ─── Topic tag selection ────────────────────────────────────────────────────
function selectTopic(btn) {
  document.querySelectorAll('.topic-tag').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  selectedTopic = btn.dataset.topic;
}

// ─── SafePost Check ─────────────────────────────────────────────────────────
function safePostCheck(text) {
  const lower = text.toLowerCase();
  const found = bannedWords.filter(word => {
    const regex = new RegExp(`\\b${word}\\b`, 'i');
    return regex.test(lower);
  });
  return { clean: found.length === 0, found };
}

// ─── Fetch Notification Badge from API ──────────────────────────────────────
async function loadNotificationBadge() {
  try {
    const res  = await fetch(`api/fetch_notifications.php?user_id=${currentUser.id}`);
    const data = await res.json();
    if (data.success && data.unread_count > 0) {
      const badge = document.getElementById('notifBadge');
      badge.textContent   = data.unread_count > 99 ? '99+' : data.unread_count;
      badge.style.display = 'block';
    }
  } catch (e) { /* silently fail */ }
}

// ─── Fetch Posts from API ───────────────────────────────────────────────────
async function fetchPosts() {
  const loader  = document.getElementById('postsLoader');
  const feed    = document.getElementById('postsFeed');
  const noPosts = document.getElementById('noPosts');

  loader.style.display = 'flex';
  feed.innerHTML       = '';
  noPosts.classList.add('d-none');

  try {
    const response = await fetch('api/fetch_posts.php');
    const data     = await response.json();

    loader.style.display = 'none';

    if (data.success) {
      allPosts = data.posts;
      renderFeed(currentFilter);
      updateTotalCount(allPosts.length);
    } else {
      feed.innerHTML = `<div class="text-center py-5 text-muted">
        <i class="bi bi-exclamation-circle display-4 d-block mb-3"></i>
        Could not load posts: ${data.message}
      </div>`;
    }
  } catch (err) {
    loader.style.display = 'none';
    feed.innerHTML = `<div class="text-center py-5" style="color:var(--text-muted)">
      <i class="bi bi-wifi-off display-4 d-block mb-3"></i>
      <strong>Cannot connect to server.</strong><br/>
      <small>Make sure XAMPP (Apache + MySQL) is running and the ripple database is imported.</small>
    </div>`;
  }
}

// ─── Render Posts Feed ──────────────────────────────────────────────────────
function renderFeed(filter) {
  const feed    = document.getElementById('postsFeed');
  const noPosts = document.getElementById('noPosts');
  const label   = document.getElementById('feedLabel');

  const posts = filter === 'all'
    ? allPosts
    : allPosts.filter(p => p.topic === filter);

  label.innerHTML = `<i class="bi bi-rss-fill"></i> ${filter === 'all' ? 'All Posts' : '#' + filter}
    <span class="posts-count-badge" id="postsCountBadge">${posts.length} post${posts.length !== 1 ? 's' : ''}</span>`;

  feed.innerHTML = '';

  if (posts.length === 0) {
    noPosts.classList.remove('d-none');
    return;
  }
  noPosts.classList.add('d-none');

  posts.forEach((post, i) => {
    const card = createPostCard(post, i);
    feed.appendChild(card);
  });
}

// ─── Create Post Card DOM Element ────────────────────────────────────────────
function createPostCard(post, index) {
  const div = document.createElement('div');
  div.className = 'post-card glass-card';
  div.id = `post-card-${post.id}`;
  div.style.animationDelay = `${index * 0.06}s`;

  const initials  = getInitials(post.author_name);
  const roleBadge = post.role === 'faculty'
    ? `<span class="post-role-badge faculty">Faculty</span>`
    : `<span class="post-role-badge">Student</span>`;

  div.innerHTML = `
    <div class="post-header">
      <div class="post-avatar">${initials}</div>
      <div class="post-meta">
        <div>
          <span class="post-author">${escapeHtml(post.author_name)}</span>
          ${roleBadge}
        </div>
        <div class="d-flex align-items-center gap-2 mt-1">
          <span class="post-topic">#${escapeHtml(post.topic)}</span>
          <span class="post-time">${timeAgo(post.created_at)}</span>
        </div>
      </div>
    </div>
    <div class="post-content">${escapeHtml(post.content)}</div>
    <div class="post-actions">
      <button class="action-btn" id="like-btn-${post.id}" onclick="toggleLike(${post.id}, this)">
        <i class="bi bi-heart"></i>
        <span id="like-count-${post.id}">${post.likes}</span>
      </button>
      <button class="action-btn" id="reply-toggle-${post.id}" onclick="toggleReplies(${post.id}, this)">
        <i class="bi bi-chat-dots"></i> <span id="reply-label-${post.id}">Replies</span>
      </button>
      <button class="action-btn ms-auto" onclick="sharePost(${post.id})">
        <i class="bi bi-share"></i> Share
      </button>
    </div>

    <!-- Inline reply section (hidden by default) -->
    <div class="replies-section d-none" id="replies-section-${post.id}">
      <div class="replies-loader d-none" id="replies-loader-${post.id}">
        <span class="spinner-border spinner-border-sm me-2"></span> Loading replies...
      </div>
      <div class="replies-list" id="replies-list-${post.id}"></div>
      <div class="reply-write-box mt-3">
        <div class="d-flex gap-2 align-items-start">
          <div class="post-avatar" style="width:32px;height:32px;font-size:0.7rem;flex-shrink:0">${escapeHtml(getInitials(currentUser.name))}</div>
          <div class="flex-grow-1">
            <textarea class="form-control create-input" id="reply-input-${post.id}" rows="2"
              placeholder="Write a reply..." style="font-size:0.88rem;min-height:60px"></textarea>
            <div id="reply-alert-${post.id}" class="mt-1"></div>
            <div class="d-flex justify-content-end mt-2">
              <button class="btn btn-neon btn-sm px-3" onclick="submitInlineReply(${post.id})">
                <i class="bi bi-reply-fill me-1"></i> Reply
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  return div;
}

// ─── Toggle Replies Inline ───────────────────────────────────────────────────
async function toggleReplies(postId, btn) {
  const section = document.getElementById(`replies-section-${postId}`);
  const label   = document.getElementById(`reply-label-${postId}`);

  if (!section.classList.contains('d-none')) {
    section.classList.add('d-none');
    label.textContent = 'Replies';
    return;
  }

  section.classList.remove('d-none');
  label.textContent = 'Hide Replies';
  await loadReplies(postId);
}

// ─── Load Replies for a Post ─────────────────────────────────────────────────
async function loadReplies(postId) {
  const loader = document.getElementById(`replies-loader-${postId}`);
  const list   = document.getElementById(`replies-list-${postId}`);

  loader.classList.remove('d-none');
  list.innerHTML = '';

  try {
    const res  = await fetch(`api/fetch_replies.php?post_id=${postId}`);
    const data = await res.json();
    loader.classList.add('d-none');

    if (!data.success || data.replies.length === 0) {
      list.innerHTML = `<p class="text-muted" style="font-size:0.85rem;padding:8px 0">No replies yet. Be the first!</p>`;
      return;
    }

    data.replies.forEach(reply => {
      const r = document.createElement('div');
      r.className = 'reply-item';
      const roleBadge = reply.role === 'faculty'
        ? `<span class="post-role-badge faculty" style="font-size:0.65rem;padding:1px 6px">Faculty</span>`
        : '';
      r.innerHTML = `
        <div class="d-flex gap-2 align-items-start">
          <div class="post-avatar" style="width:30px;height:30px;font-size:0.68rem;flex-shrink:0">${getInitials(reply.author_name)}</div>
          <div class="reply-bubble">
            <div class="reply-author">
              ${escapeHtml(reply.author_name)} ${roleBadge}
              <span class="post-time ms-2">${timeAgo(reply.created_at)}</span>
            </div>
            <div class="reply-text">${escapeHtml(reply.content)}</div>
          </div>
        </div>`;
      list.appendChild(r);
    });

    document.getElementById(`reply-label-${postId}`).textContent =
      `Hide Replies (${data.replies.length})`;

  } catch (err) {
    loader.classList.add('d-none');
    list.innerHTML = `<p class="text-muted" style="font-size:0.85rem">Failed to load replies.</p>`;
  }
}

// ─── Submit Inline Reply ─────────────────────────────────────────────────────
async function submitInlineReply(postId) {
  const textarea  = document.getElementById(`reply-input-${postId}`);
  const alertBox  = document.getElementById(`reply-alert-${postId}`);
  const content   = textarea.value.trim();

  if (!content) { showAlert(alertBox, 'danger', 'Reply cannot be empty.'); return; }

  const check = safePostCheck(content);
  if (!check.clean) {
    showAlert(alertBox, 'warning', `🚫 Inappropriate content: "${check.found.join('", "')}". Please revise.`);
    return;
  }

  try {
    const res  = await fetch('api/create_reply.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId, user_id: currentUser.id, content })
    });
    const data = await res.json();

    if (data.success) {
      textarea.value = '';
      showAlert(alertBox, 'success', '✅ Reply posted!');
      // find post author name for activity log
      const _post = allPosts.find(p => p.id == postId);
      logActivity('reply', postId, _post ? _post.author_name : null, content);
      loadLiveActivity();
      await loadReplies(postId);
    } else {
      showAlert(alertBox, 'danger', data.message || 'Failed to post reply.');
    }
  } catch (err) {
    showAlert(alertBox, 'danger', '❌ Server error. Is XAMPP running?');
  }
}

// ─── Submit New Post ─────────────────────────────────────────────────────────
async function submitPost() {
  const textarea  = document.getElementById('postContent');
  const alertBox  = document.getElementById('postAlert');
  const warning   = document.getElementById('safePostWarning');
  const warnMsg   = document.getElementById('safePostMsg');
  const btn       = document.getElementById('submitPostBtn');
  const content   = textarea.value.trim();

  warning.classList.add('d-none');

  if (!content) { showAlert(alertBox, 'danger', 'Please write something before posting.'); return; }

  const check = safePostCheck(content);
  if (!check.clean) {
    warning.classList.remove('d-none');
    warnMsg.textContent = `🚫 Inappropriate content detected: "${check.found.join('", "')}". Please revise your post.`;
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Posting...';

  try {
    const response = await fetch('api/create_post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: currentUser.id, content, topic: selectedTopic })
    });
    const data = await response.json();

    if (data.success) {
      textarea.value = '';
      showAlert(alertBox, 'success', '✅ Post published successfully!');
      logActivity('post', data.post_id || null, null, content);
      fetchPosts();
    } else {
      showAlert(alertBox, 'danger', data.message || 'Failed to post. Try again.');
    }
  } catch (err) {
    showAlert(alertBox, 'danger', '❌ Server error. Is XAMPP running?');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Post';
  }
}

// ─── Like / Unlike (DB-persisted) ───────────────────────────────────────────
async function toggleLike(postId, btn) {
  btn.disabled = true;
  try {
    const res  = await fetch('api/toggle_like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_id: postId, user_id: currentUser.id })
    });
    const data = await res.json();

    if (data.success) {
      const countEl = document.getElementById(`like-count-${postId}`);
      const iconEl  = btn.querySelector('i');
      countEl.textContent = data.likes;

      const _likedPost = allPosts.find(p => p.id == postId);
      if (data.action === 'liked') { logActivity('like', postId, _likedPost ? _likedPost.author_name : null, null); }
      if (data.action === 'liked') {
        btn.classList.add('liked');
        iconEl.className = 'bi bi-heart-fill';
        btn.style.transform  = 'scale(1.3)';
        btn.style.transition = 'transform 0.15s ease';
        setTimeout(() => { btn.style.transform = 'scale(1)'; }, 180);
      } else {
        btn.classList.remove('liked');
        iconEl.className = 'bi bi-heart';
      }

      const post = allPosts.find(p => p.id == postId);
      if (post) post.likes = data.likes;
    }
  } catch (e) { /* silently fail */ }
  btn.disabled = false;
}

// ─── Filter Posts by Topic ───────────────────────────────────────────────────
function filterPosts(topic) {
  currentFilter = topic;
  document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
  event.target.closest('.sidebar-link').classList.add('active');
  renderFeed(topic);
}

// ─── Share Post ──────────────────────────────────────────────────────────────
function sharePost(postId) {
  const url = `${window.location.origin}${window.location.pathname}#post-${postId}`;
  navigator.clipboard.writeText(url).then(() => {
    showAlert(document.getElementById('postAlert'), 'success', '🔗 Post link copied to clipboard!');
  }).catch(() => {
    showAlert(document.getElementById('postAlert'), 'info', '🔗 Share: ' + url);
  });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function updateTotalCount(n) {
  document.getElementById('totalPostsCount').textContent = n;
}

function escapeHtml(str) {
  if (typeof str !== 'string') return '';
  return str
    .replace(/&/g,  '&amp;')
    .replace(/</g,  '&lt;')
    .replace(/>/g,  '&gt;')
    .replace(/"/g,  '&quot;')
    .replace(/'/g,  '&#039;');
}

/* =========================================
   PRESENCE – Online Users + Live Activity
   ========================================= */

let presenceInterval  = null;
let activityInterval  = null;

// Called once on DOMContentLoaded (after currentUser is set)
function initPresence() {
  pingPresence();                        // immediate first ping
  loadOnlineUsers();
  loadLiveActivity();

  presenceInterval = setInterval(() => {
    pingPresence();
    loadOnlineUsers();
  }, 15000);                             // refresh online list every 15s

  activityInterval = setInterval(() => {
    loadLiveActivity();
  }, 10000);                             // refresh activity every 10s
}

// Send heartbeat to server
async function pingPresence() {
  if (!currentUser) return;
  try {
    await fetch('api/presence.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        user_id: currentUser.id,
        name:    currentUser.name,
        role:    currentUser.role,
        page:    'dashboard'
      })
    });
  } catch (e) { /* silent */ }
}

// Load and render online users
async function loadOnlineUsers() {
  const list  = document.getElementById('onlineUsersList');
  const badge = document.getElementById('onlineCountBadge');
  if (!list) return;

  try {
    const res  = await fetch('api/presence.php?action=online');
    const data = await res.json();
    if (!data.success) return;

    const users = data.online;
    if (badge) badge.textContent = users.length;

    if (users.length === 0) {
      list.innerHTML = `<div class="online-empty">No one online right now.</div>`;
      return;
    }

    list.innerHTML = '';
    users.forEach(u => {
      const row = document.createElement('div');
      row.className = 'online-user-row';
      const avClass = u.role === 'faculty' ? 'online-avatar faculty-av' : 'online-avatar';
      const roleLabel = u.role === 'faculty' ? '<span class="online-user-role faculty">Faculty</span>' : '<span class="online-user-role">Student</span>';
      const isYou = currentUser && u.user_id === currentUser.id ? ' <span style="color:var(--neon);font-size:0.65rem">(you)</span>' : '';
      row.innerHTML = `
        <div class="${avClass}">${getInitials(u.name)}</div>
        <div class="online-user-info">
          <div class="online-user-name">${escapeHtml(u.name)}${isYou}</div>
          ${roleLabel}
        </div>`;
      list.appendChild(row);
    });
  } catch (e) { /* silent */ }
}

// Load and render live activity
async function loadLiveActivity() {
  const feed = document.getElementById('liveActivityFeed');
  if (!feed) return;

  try {
    const res  = await fetch('api/presence.php?action=activity');
    const data = await res.json();
    if (!data.success) return;

    const acts = data.activities;

    if (acts.length === 0) {
      feed.innerHTML = `<div class="activity-empty">No recent activity.</div>`;
      return;
    }

    feed.innerHTML = '';
    acts.forEach(a => {
      const row = document.createElement('div');
      row.className = `activity-row type-${a.type}`;

      let icon, text;
      if (a.type === 'post') {
        icon = '<i class="bi bi-pen-fill activity-icon type-post"></i>';
        text = `<strong>${escapeHtml(a.actor_name)}</strong> posted`;
      } else if (a.type === 'reply') {
        icon = '<i class="bi bi-reply-fill activity-icon type-reply"></i>';
        text = `<strong>${escapeHtml(a.actor_name)}</strong> replied to <strong>${escapeHtml(a.target_name || 'a post')}</strong>`;
      } else {
        icon = '<i class="bi bi-heart-fill activity-icon type-like"></i>';
        text = `<strong>${escapeHtml(a.actor_name)}</strong> liked <strong>${escapeHtml(a.target_name || 'a post')}</strong>`;
      }

      const preview = a.preview
        ? `<span class="act-preview">"${escapeHtml(a.preview)}"</span>` : '';

      row.innerHTML = `
        ${icon}
        <div class="activity-text">${text}${preview}</div>
        <div class="activity-time">${timeAgo(a.created_at)}</div>`;
      feed.appendChild(row);
    });
  } catch (e) { /* silent */ }
}

// Log an activity event (called after successful post/reply/like)
async function logActivity(type, postId, targetName, preview) {
  if (!currentUser) return;
  try {
    await fetch('api/log_activity.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        actor_id:   currentUser.id,
        actor_name: currentUser.name,
        actor_role: currentUser.role,
        type,
        target_name: targetName || null,
        post_id:    postId    || null,
        preview:    preview   ? preview.substring(0, 100) : null
      })
    });
  } catch (e) { /* silent */ }
}
