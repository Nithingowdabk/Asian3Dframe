// assets/js/admin-manage.js

document.addEventListener('DOMContentLoaded', () => {
  const adminTable = document.getElementById('adminTable').querySelector('tbody');
  const addAdminBtn = document.getElementById('addAdminBtn');
  const adminModal = document.getElementById('adminModal');
  const adminForm = document.getElementById('adminForm');
  const modalTitle = document.getElementById('modalTitle');
  const modalAlert = document.getElementById('modalAlert');
  const cancelBtn = document.getElementById('cancelBtn');
  let editingId = null;

  function showModal(edit = false, admin = null) {
    adminModal.classList.add('active');
    modalAlert.style.display = 'none';
    adminForm.reset();
    editingId = null;
    if (edit && admin) {
      modalTitle.textContent = 'Edit Admin';
      adminForm.username.value = admin.username;
      adminForm.password.value = '';
      editingId = admin.id;
    } else {
      modalTitle.textContent = 'Add Admin';
    }
  }

  function hideModal() {
    adminModal.classList.remove('active');
    editingId = null;
  }

  async function loadAdmins() {
    adminTable.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    try {
      const res = await fetch('../php/get_admin_users.php');
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      if (!data.admins.length) {
        adminTable.innerHTML = '<tr><td colspan="4">No admins found.</td></tr>';
        return;
      }
      adminTable.innerHTML = data.admins.map(a => `
        <tr>
          <td>${a.id}</td>
          <td>${a.username}</td>
          <td>${a.created_at}</td>
          <td class="actions">
            <button class="btn btn-edit" data-id="${a.id}" data-username="${a.username}"><i class="fas fa-edit"></i> Edit</button>
            <button class="btn btn-delete" data-id="${a.id}"><i class="fas fa-trash"></i> Delete</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      adminTable.innerHTML = `<tr><td colspan="4" style="color:#ef4444;">${err.message}</td></tr>`;
    }
  }

  addAdminBtn.addEventListener('click', () => showModal(false));
  cancelBtn.addEventListener('click', hideModal);

  adminTable.addEventListener('click', async (e) => {
    if (e.target.closest('.btn-edit')) {
      const btn = e.target.closest('.btn-edit');
      showModal(true, { id: btn.dataset.id, username: btn.dataset.username });
    } else if (e.target.closest('.btn-delete')) {
      const btn = e.target.closest('.btn-delete');
      if (confirm('Are you sure you want to delete this admin?')) {
        await deleteAdmin(btn.dataset.id);
        loadAdmins();
      }
    }
  });

  adminForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    modalAlert.style.display = 'none';
    const username = adminForm.username.value.trim();
    const password = adminForm.password.value;
    if (!username || (!editingId && !password)) {
      modalAlert.textContent = 'Username and password are required.';
      modalAlert.style.display = 'block';
      return;
    }
    try {
      if (editingId) {
        // Edit admin
        const res = await fetch('../php/edit_admin_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: editingId, username, password })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
      } else {
        // Add admin
        const res = await fetch('../php/add_admin_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
      }
      hideModal();
      loadAdmins();
    } catch (err) {
      modalAlert.textContent = err.message;
      modalAlert.style.display = 'block';
    }
  });

  async function deleteAdmin(id) {
    try {
      const res = await fetch('../php/delete_admin_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
    } catch (err) {
      alert('Failed to delete admin: ' + err.message);
    }
  }

  loadAdmins();
});
