/**
 * 前端交互 JS
 * 导航、弹窗、灯箱、AJAX 搜索、留言提交
 */

// ============================================
// 导航
// ============================================
function toggleNav() {
  document.getElementById('navLinks').classList.toggle('open');
}

// 滚动时添加阴影
window.addEventListener('scroll', () => {
  const navbar = document.getElementById('navbar');
  if (navbar) {
    navbar.classList.toggle('scrolled', window.scrollY > 20);
  }
});

// 点击导航链接后关闭移动菜单
document.addEventListener('click', (e) => {
  const navLinks = document.getElementById('navLinks');
  if (navLinks && navLinks.classList.contains('open')) {
    if (!e.target.closest('.navbar-toggle') && !e.target.closest('.navbar-links')) {
      navLinks.classList.remove('open');
    }
  }
});

// ============================================
// 弹窗
// ============================================
function openModal(id) {
  // 先从缓存数据中查找基本信息（不含隐私字段）
  const data = window.__studentsData;
  if (!data) return;

  const student = data.find(s => s.id === parseInt(id));
  if (!student) return;

  // 先显示基本信息
  document.getElementById('modalAvatar').src = student.avatar_url;
  document.getElementById('modalAvatar').alt = student.name;
  document.getElementById('modalName').textContent = student.name;
  document.getElementById('modalCity').textContent = '📍 ' + (student.city || '未知');
  document.getElementById('modalMotto').textContent = student.motto || '暂无座右铭';
  document.getElementById('modalPhone').textContent = '加载中...';
  document.getElementById('modalEmail').textContent = '加载中...';
  document.getElementById('modalWechat').textContent = '加载中...';
  document.getElementById('modalBio').textContent = '加载中...';

  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';

  // 通过 AJAX 按需获取联系方式等隐私信息
  fetch('api/student.php?id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(res => {
      if (res.success && res.student) {
        const s = res.student;
        if (s.show_contact) {
          document.getElementById('modalPhone').textContent = s.phone || '未填写';
          document.getElementById('modalEmail').textContent = s.email || '未填写';
          document.getElementById('modalWechat').textContent = s.wechat || '未填写';
        } else {
          document.getElementById('modalPhone').textContent = '该同学未公开';
          document.getElementById('modalEmail').textContent = '该同学未公开';
          document.getElementById('modalWechat').textContent = '该同学未公开';
        }
        document.getElementById('modalBio').textContent = s.bio || '暂无个人简介';
      } else {
        document.getElementById('modalPhone').textContent = '获取失败';
        document.getElementById('modalEmail').textContent = '';
        document.getElementById('modalWechat').textContent = '';
        document.getElementById('modalBio').textContent = '';
      }
    })
    .catch(() => {
      document.getElementById('modalPhone').textContent = '获取失败';
      document.getElementById('modalEmail').textContent = '';
      document.getElementById('modalWechat').textContent = '';
      document.getElementById('modalBio').textContent = '';
    });
}

function closeModal(e) {
  if (e && e.target !== e.currentTarget && !e.target.classList.contains('modal-close')) return;
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// ============================================
// 灯箱
// ============================================
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}

// ESC 关闭弹窗和灯箱
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeModal();
    closeLightbox();
  }
});

// ============================================
// Toast 提示
// ============================================
function showToast(message, duration = 3000) {
  let toast = document.getElementById('toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add('show');
  setTimeout(() => {
    toast.classList.remove('show');
  }, duration);
}

// ============================================
// AJAX 搜索同学（实时过滤）
// ============================================
let searchTimer = null;
function searchStudents(query, city) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    const grid = document.getElementById('classmatesGrid');
    const paginationEl = document.getElementById('pagination');
    if (!grid) return;

    const params = new URLSearchParams();
    if (query) params.set('q', query);
    if (city && city !== '全部') params.set('city', city);

    fetch('api/search.php?' + params.toString())
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // 更新全局数据
          window.__studentsData = data.students;
          renderStudentCards(data.students);
          if (paginationEl) paginationEl.innerHTML = '';
        }
      })
      .catch(() => {
        showToast('搜索失败，请稍后重试');
      });
  }, 300);
}

function renderStudentCards(students) {
  const grid = document.getElementById('classmatesGrid');
  if (!grid) return;
  grid.textContent = '';

  if (!students || students.length === 0) {
    var empty = document.createElement('div');
    empty.className = 'empty-state';
    var emptyIcon = document.createElement('div');
    emptyIcon.className = 'empty-state-icon';
    emptyIcon.textContent = '🔍';
    var emptyText = document.createElement('div');
    emptyText.className = 'empty-state-text';
    emptyText.textContent = '没有找到匹配的同学...';
    empty.appendChild(emptyIcon);
    empty.appendChild(emptyText);
    grid.appendChild(empty);
    return;
  }

  students.forEach(function(s, i) {
    var card = document.createElement('div');
    card.className = 'classmate-card';
    card.style.animationDelay = (i * 0.06) + 's';
    card.addEventListener('click', function() { openModal(s.id); });

    var avatarWrap = document.createElement('div');
    avatarWrap.className = 'card-avatar';
    var img = document.createElement('img');
    img.src = s.avatar_url;
    img.alt = s.name;
    img.loading = 'lazy';
    img.dataset.name = s.name;
    img.addEventListener('error', avatarFallback);
    avatarWrap.appendChild(img);

    var nameEl = document.createElement('div');
    nameEl.className = 'card-name';
    nameEl.textContent = s.name;

    var cityEl = document.createElement('div');
    cityEl.className = 'card-city';
    cityEl.textContent = '\uD83D\uDCCD ' + (s.city || '未知');

    var mottoEl = document.createElement('div');
    mottoEl.className = 'card-motto';
    mottoEl.textContent = '\u201C' + (s.motto || '') + '\u201D';

    card.appendChild(avatarWrap);
    card.appendChild(nameEl);
    card.appendChild(cityEl);
    card.appendChild(mottoEl);
    grid.appendChild(card);
  });
}

/**
 * 头像加载失败时回退到 ui-avatars.com
 * 优先读取 data-name，其次读取 alt
 */
function avatarFallback() {
  var name = this.dataset.name || this.alt || '同学';
  this.onerror = null;
  this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=5D4037&color=fff&size=200';
}

/**
 * 初始化页面中的头像 onerror 回退（用于 PHP 渲染的静态 HTML）
 */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('img[data-name]').forEach(function(img) {
    img.addEventListener('error', avatarFallback);
  });
});

// ============================================
// 城市筛选
// ============================================
let currentCity = '全部';
function filterByCity(el, city) {
  document.querySelectorAll('.city-tag').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  currentCity = city;
  const query = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
  searchStudents(query, city);
}

// 搜索输入框
function onSearchInput() {
  const query = document.getElementById('searchInput').value;
  searchStudents(query, currentCity);
}

// ============================================
// 留言提交
// ============================================
function submitMessage() {
  const nameInput = document.getElementById('messageName');
  const contentInput = document.getElementById('messageContent');
  const name = nameInput.value.trim();
  const content = contentInput.value.trim();

  if (!name) {
    showToast('请填写你的名字');
    nameInput.focus();
    return;
  }
  if (!content) {
    showToast('请写下你的留言');
    contentInput.focus();
    return;
  }

  const formData = new FormData();
  formData.append('student_name', name);
  formData.append('content', content);
  formData.append('_token', document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '');

  fetch('api/message.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      // 清空表单
      contentInput.value = '';
      showToast('留言成功！✨');
      // 刷新留言列表
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(data.message || '留言失败，请稍后重试');
    }
  })
  .catch(() => {
    showToast('网络错误，请稍后重试');
  });
}

// ============================================
// 确认删除（通用）
// ============================================
function confirmDelete(url, message) {
  if (confirm(message || '确定要删除吗？此操作不可撤销。')) {
    window.location.href = url;
  }
}

// ============================================
// 全选/取消全选（管理后台）
// ============================================
function toggleAll(el) {
  document.querySelectorAll('input[name="ids[]"]').forEach(cb => cb.checked = el.checked);
}

// ============================================
// 图片上传预览
// ============================================
function previewImage(input, previewId) {
  const preview = document.getElementById(previewId);
  if (!preview) return;

  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
