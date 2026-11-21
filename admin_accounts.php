<?php
include 'db.php';
require_once 'admin_auth.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการบัญชี Admin</title>
<?php include 'tailwind-config.php'; ?>
</head>
<body>
<div class="container-md">
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold" style="color:#1f2937;">👤 จัดการบัญชี Admin</h1>
        <p class="text-gray text-sm">เพิ่ม ลบ และเปลี่ยนรหัสผ่านบัญชีผู้ดูแลระบบ</p>
    </div>

    <div id="alertBox" style="display:none;" class="notice mb-6"></div>

    <div class="card mb-8">
        <div class="card-body">
            <h2 class="font-bold mb-4" style="font-size:18px;">เพิ่มบัญชี Admin ใหม่</h2>
            <form id="createForm">
                <label class="form-label">Username</label>
                <input class="form-input mb-4" type="text" id="newUsername" required maxlength="50" autocomplete="off">
                <label class="form-label">Password (อย่างน้อย 6 ตัวอักษร)</label>
                <input class="form-input mb-4" type="password" id="newPassword" required minlength="6" autocomplete="new-password">
                <button class="btn btn-primary" type="submit">+ เพิ่มบัญชี</button>
            </form>
        </div>
    </div>

    <div class="table-container mb-8">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>สร้างเมื่อ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody id="adminTableBody"></tbody>
        </table>
    </div>

    <div class="text-center mb-8">
        <a href="homeadmin.php" class="btn-back">🏠 Admin Home</a>
    </div>
</div>

<script>
const csrfToken = <?= json_encode(csrf_token()) ?>;
const currentAdminId = <?= json_encode((int)$_SESSION['admin_id']) ?>;

function showAlert(message, isError) {
    const box = document.getElementById('alertBox');
    box.textContent = message;
    box.style.display = 'block';
    box.style.color = isError ? '#dc2626' : '#059669';
    box.style.borderColor = isError ? '#fca5a5' : '#a7f3d0';
}

function hideAlert() {
    document.getElementById('alertBox').style.display = 'none';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function renderAdmins(admins) {
    const tbody = document.getElementById('adminTableBody');
    tbody.innerHTML = '';

    if (admins.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-gray">ไม่พบบัญชี Admin</td></tr>';
        return;
    }

    admins.forEach((admin) => {
        const tr = document.createElement('tr');
        const isSelf = admin.id === currentAdminId;
        tr.innerHTML = `
            <td>${escapeHtml(admin.username)}${isSelf ? ' <span style="color:#6366f1;font-size:12px;">(บัญชีนี้)</span>' : ''}</td>
            <td>${escapeHtml(admin.created_at)}</td>
            <td>
                <button type="button" class="btn-back" style="padding:8px 14px;font-size:13px;" data-action="change_password" data-id="${admin.id}" data-username="${escapeHtml(admin.username)}">เปลี่ยนรหัสผ่าน</button>
                ${isSelf ? '' : `<button type="button" class="btn-back" style="padding:8px 14px;font-size:13px;color:#dc2626;" data-action="delete" data-id="${admin.id}" data-username="${escapeHtml(admin.username)}">ลบ</button>`}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function api(fields) {
    const body = new FormData();
    body.append('csrf', csrfToken);
    Object.entries(fields).forEach(([k, v]) => body.append(k, v));
    try {
        const res = await fetch('api/admins.php', { method: 'POST', body });
        return await res.json();
    } catch (e) {
        return { status: 'error', message: 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้' };
    }
}

async function loadAdmins() {
    const res = await fetch('api/admins.php');
    const data = await res.json();
    if (data.status === 'success') renderAdmins(data.admins);
    else showAlert(data.message || 'โหลดรายชื่อไม่สำเร็จ', true);
}

document.getElementById('createForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();
    const username = document.getElementById('newUsername').value.trim();
    const password = document.getElementById('newPassword').value;

    const data = await api({ action: 'create', username, password });
    if (data.status === 'success') {
        showAlert('เพิ่มบัญชี "' + username + '" สำเร็จ', false);
        document.getElementById('createForm').reset();
        renderAdmins(data.admins);
    } else {
        showAlert(data.message || 'เพิ่มบัญชีไม่สำเร็จ', true);
    }
});

document.getElementById('adminTableBody').addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;

    hideAlert();
    const action = btn.dataset.action;
    const adminId = btn.dataset.id;
    const username = btn.dataset.username;

    if (action === 'delete') {
        if (!confirm(`ต้องการลบบัญชี "${username}" หรือไม่?`)) return;
        const data = await api({ action: 'delete', admin_id: adminId });
        if (data.status === 'success') {
            showAlert(`ลบบัญชี "${username}" แล้ว`, false);
            renderAdmins(data.admins);
        } else {
            showAlert(data.message || 'ลบไม่สำเร็จ', true);
        }
    }

    if (action === 'change_password') {
        const newPassword = prompt(`ตั้งรหัสผ่านใหม่สำหรับ "${username}" (อย่างน้อย 6 ตัวอักษร)`);
        if (!newPassword) return;
        const data = await api({ action: 'change_password', admin_id: adminId, password: newPassword });
        if (data.status === 'success') {
            showAlert(`เปลี่ยนรหัสผ่านของ "${username}" แล้ว`, false);
        } else {
            showAlert(data.message || 'เปลี่ยนรหัสผ่านไม่สำเร็จ', true);
        }
    }
});

loadAdmins();
</script>
</body>
</html>
