<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$page_title = '👥 مدیریت کاربران';
$back_url = 'index.php';
$message = '';
$messageType = 'success';

if(isset($_POST['change_password_user_id'])){

    $id = (int)$_POST['change_password_user_id'];
    $password = trim($_POST['new_password'] ?? '');
    $passwordConfirm = trim($_POST['new_password_confirm'] ?? '');

    if(strlen($password) < 6){

        $message = 'رمز عبور باید حداقل ۶ کاراکتر باشد';
        $messageType = 'danger';

    }elseif($password !== $passwordConfirm){

        $message = 'تکرار رمز عبور با رمز جدید یکسان نیست';
        $messageType = 'danger';

    }else{

        $stmt = $pdo->prepare("
            UPDATE users
            SET password=?
            WHERE id=? AND role='user' AND status!='pending'
        ");

        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $id
        ]);

        $message =
        $stmt->rowCount()
        ? 'رمز عبور کاربر تغییر کرد'
        : 'کاربر تایید شده یافت نشد';

        $messageType =
        $stmt->rowCount()
        ? 'success'
        : 'danger';

    }

}

if(isset($_GET['deactivate'])){

    $id = (int)$_GET['deactivate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='inactive'
        WHERE id=? AND role='user'
    ");

    $stmt->execute([$id]);

    header("Location: users.php");
    exit;

}

if(isset($_GET['activate'])){

    $id = (int)$_GET['activate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='active'
        WHERE id=? AND role='user'
    ");

    $stmt->execute([$id]);

    header("Location: users.php");
    exit;

}

if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM user_organization_rel
        WHERE user_id=?
    ");

    $stmt->execute([$id]);

    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE id=? AND role='user'
    ");

    $stmt->execute([$id]);

    header("Location: users.php");
    exit;

}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [
    "role='user'",
    "status!='pending'"
];
$params = [];

if(in_array($status, ['active','inactive'], true)){

    $where[] = "status=?";
    $params[] = $status;

}

if($search){

    $where[] = "(
        fullname LIKE ?
        OR mobile LIKE ?
        OR national_code LIKE ?
        OR job_title LIKE ?
    )";

    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

}

$whereSql = "WHERE " . implode(" AND ", $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM users
    $whereSql
");

$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
$totalPages = max(1, (int)ceil($total / $limit));

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    $whereSql
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$users = $stmt->fetchAll();

$serviceLocations = [];
$userIds = array_column($users, 'id');

if($userIds){

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $serviceStmt = $pdo->prepare("
        SELECT
            rel.user_id,
            child.name as child_name,
            child.type as child_type,
            center.name as center_name
        FROM user_organization_rel rel
        LEFT JOIN organization_nodes child ON rel.node_id = child.id
        LEFT JOIN organization_nodes center ON rel.center_id = center.id
        WHERE rel.user_id IN ($placeholders)
        ORDER BY rel.id ASC
    ");

    $serviceStmt->execute($userIds);

    foreach($serviceStmt->fetchAll() as $row){

        $typeText = [
            'unit' => 'واحد',
            'health_house' => 'خانه بهداشت',
            'center' => 'مرکز'
        ][$row['child_type'] ?? ''] ?? '';

        $parts = array_filter([
            $row['center_name'] ?? '',
            $row['child_name'] ?? ''
        ]);

        $text = $parts ? implode(' - ', $parts) : '-';

        if($typeText){

            $text .= " ({$typeText})";

        }

        $serviceLocations[$row['user_id']][] = $text;

    }

}

$statusText = [
    'active' => 'فعال',
    'inactive' => 'غیرفعال'
];

$profiles = [];

foreach($users as $user){

    $profiles[$user['id']] = [
        'fullname' => $user['fullname'] ?? '',
        'national_code' => $user['national_code'] ?? '',
        'mobile' => $user['mobile'] ?? '',
        'job_title' => $user['job_title'] ?? '',
        'status' => $statusText[$user['status']] ?? $user['status'],
        'services' => $serviceLocations[$user['id']] ?? []
    ];

}

require '../includes/header.php';

?>

<style>
.page-box{max-width:1200px;margin:auto;}
.page-title{font-size:26px;font-weight:800;margin-bottom:20px;color:#0f172a;}
.card{background:white;border-radius:24px;padding:22px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.filter-grid{display:grid;grid-template-columns:1fr 220px;gap:12px;}
.users-table-wrap{overflow-x:auto;}
.users-table{width:100%;border-collapse:separate;border-spacing:0 10px;}
.users-table th{
    text-align:right;
    background:#f8fafc;
    color:#475569;
    font-size:13px;
    padding:14px;
}
.users-table td{
    background:#fff;
    border-top:1px solid #eef2f7;
    border-bottom:1px solid #eef2f7;
    padding:16px 14px;
    color:#334155;
    font-size:14px;
    vertical-align:middle;
}
.users-table tr td:first-child{border-right:1px solid #eef2f7;border-radius:0 18px 18px 0;}
.users-table tr td:last-child{border-left:1px solid #eef2f7;border-radius:18px 0 0 18px;}
.user-fullname{font-weight:800;color:#0f172a;}
.status-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:7px 13px;
    border-radius:999px;
    color:white;
    font-size:12px;
    font-weight:800;
}
.active{background:#10b981;}
.inactive{background:#ef4444;}
.row-menu{position:relative;display:inline-block;}
.menu-btn{
    width:38px;
    height:38px;
    border:none;
    border-radius:12px;
    background:#f1f5f9;
    color:#334155;
    font-size:22px;
    cursor:pointer;
}
.dropdown-menu{
    position:absolute;
    top:44px;
    left:0;
    min-width:180px;
    background:#fff;
    border:1px solid #eef2f7;
    border-radius:16px;
    box-shadow:0 12px 35px rgba(15,23,42,.15);
    display:none;
    overflow:hidden;
    z-index:9999;
}
.dropdown-menu.show{display:block;}
.dropdown-menu a,
.dropdown-menu button{
    width:100%;
    display:block;
    border:none;
    background:none;
    text-align:right;
    padding:12px 16px;
    color:#334155;
    font-family:inherit;
    font-size:13px;
    font-weight:800;
    text-decoration:none;
    cursor:pointer;
}
.dropdown-menu a:hover,
.dropdown-menu button:hover{background:#f8fafc;}
.delete-action{color:#dc2626!important;}
.pagination{
    display:flex;
    justify-content:center;
    gap:8px;
    margin-top:20px;
    flex-wrap:wrap;
}
.page-link{
    min-width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    border-radius:14px;
    background:#fff;
    color:#334155;
    border:1px solid #e2e8f0;
    font-weight:800;
}
.active-page{background:linear-gradient(135deg,#0284c7,#06b6d4);color:white;border:none;}
.empty-box{text-align:center;padding:35px;color:#777;}
.modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.35);
    backdrop-filter:blur(8px);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:99999;
}
.modal-overlay.show{display:flex;}
.profile-modal{
    width:720px;
    max-width:92%;
    background:white;
    border-radius:26px;
    padding:26px;
    box-shadow:0 24px 70px rgba(15,23,42,.2);
}
.modal-title{font-size:22px;font-weight:900;margin-bottom:18px;color:#0f172a;}
.profile-section{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:20px;
    padding:18px;
    margin-bottom:14px;
}
.section-title{font-size:15px;font-weight:900;color:#0369a1;margin-bottom:12px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.info-item{background:white;border-radius:14px;padding:12px;color:#334155;font-size:13px;}
.info-label{display:block;color:#64748b;font-size:12px;margin-bottom:6px;}
.service-list{margin:0;padding-right:18px;line-height:32px;color:#334155;}
.modal-close{
    width:100%;
    border:none;
    border-radius:16px;
    padding:13px;
    background:#f1f5f9;
    color:#334155;
    font-family:inherit;
    font-weight:800;
    cursor:pointer;
}
.password-actions{
    display:flex;
    gap:10px;
    margin-top:10px;
}
.password-save-btn{
    flex:1;
    border:none;
    border-radius:16px;
    padding:13px;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
    font-family:inherit;
    font-weight:800;
    cursor:pointer;
}
.password-actions .modal-close{
    flex:1;
}
@media(max-width:768px){
    .filter-grid{grid-template-columns:1fr;}
    .info-grid{grid-template-columns:1fr;}
    .password-actions{flex-direction:column;}
}
</style>

<div class="page-box">

<div class="page-title">👥 مدیریت کاربران</div>

<?php if($message): ?>
<div class="alert alert-<?= htmlspecialchars($messageType) ?>">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="card">
<form method="GET">
    <div class="filter-grid">
        <input
        type="text"
        name="search"
        class="form-control"
        placeholder="جستجو نام، شماره موبایل، کد ملی یا پست سازمانی"
        value="<?= htmlspecialchars($search) ?>">

        <select name="status" class="form-control">
            <option value="">همه وضعیت ها</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
        </select>
    </div>

    <button type="submit" class="btn-custom">جستجو کاربران</button>
</form>
</div>

<div class="card">
<?php if(count($users)): ?>

<div class="users-table-wrap">
<table class="users-table">
    <thead>
        <tr>
            <th>نام و نام خانوادگی</th>
            <th>پست سازمانی</th>
            <th>شماره موبایل</th>
            <th>وضعیت</th>
            <th>منو</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($users as $user): ?>
        <tr>
            <td><span class="user-fullname"><?= htmlspecialchars($user['fullname']) ?></span></td>
            <td><?= htmlspecialchars($user['job_title'] ?: '-') ?></td>
            <td><?= htmlspecialchars($user['mobile']) ?></td>
            <td>
                <span class="status-badge <?= htmlspecialchars($user['status']) ?>">
                    <?= $statusText[$user['status']] ?? '-' ?>
                </span>
            </td>
            <td>
                <div class="row-menu">
                    <button type="button" class="menu-btn" onclick="toggleUserMenu(<?= $user['id'] ?>)">⋮</button>
                    <div id="user-menu-<?= $user['id'] ?>" class="dropdown-menu">
                        <button type="button" onclick="openProfileModal(<?= $user['id'] ?>)">مشاهده پروفایل</button>
                        <a href="user-edit.php?id=<?= $user['id'] ?>">ویرایش</a>
                        <button type="button" onclick="openPasswordModal(<?= $user['id'] ?>)">تغییر رمز عبور</button>
                        <?php if($user['status'] === 'active'): ?>
                        <a href="?deactivate=<?= $user['id'] ?>">غیرفعال سازی</a>
                        <?php else: ?>
                        <a href="?activate=<?= $user['id'] ?>">فعال سازی</a>
                        <?php endif; ?>
                        <a
                        href="?delete=<?= $user['id'] ?>"
                        class="delete-action"
                        onclick="return confirm('آیا از حذف این کاربر مطمئن هستید؟')">
                            حذف
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if($totalPages > 1): ?>
<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<?php
$pageQuery = $_GET;
$pageQuery['page'] = $i;
?>
<a
href="?<?= htmlspecialchars(http_build_query($pageQuery)) ?>"
class="page-link <?= $page==$i ? 'active-page' : '' ?>">
<?= $i ?>
</a>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-box">کاربری یافت نشد</div>
<?php endif; ?>
</div>

</div>

<div id="profileModal" class="modal-overlay">
    <div class="profile-modal">
        <div class="modal-title">مشاهده پروفایل</div>

        <div class="profile-section">
            <div class="section-title">اطلاعات فردی</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">نام و نام خانوادگی</span>
                    <span id="profile_fullname"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">پست سازمانی</span>
                    <span id="profile_job_title"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">شماره موبایل</span>
                    <span id="profile_mobile"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">کد ملی</span>
                    <span id="profile_national_code"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">وضعیت</span>
                    <span id="profile_status"></span>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <div class="section-title">محل خدمت</div>
            <ul id="profile_services" class="service-list"></ul>
        </div>

        <button type="button" class="modal-close" onclick="closeProfileModal()">بازگشت</button>
    </div>
</div>

<div id="passwordModal" class="modal-overlay">
    <div class="profile-modal">
        <div class="modal-title">تغییر رمز عبور</div>

        <form method="POST" id="passwordForm">
            <input
            type="hidden"
            name="change_password_user_id"
            id="change_password_user_id">

            <div class="profile-section">
                <div class="section-title">کاربر</div>
                <div class="info-item" id="password_user_name">-</div>
            </div>

            <input
            type="password"
            name="new_password"
            id="new_password"
            class="form-control"
            placeholder="رمز عبور جدید"
            minlength="6"
            required>

            <input
            type="password"
            name="new_password_confirm"
            id="new_password_confirm"
            class="form-control"
            placeholder="تکرار رمز عبور جدید"
            minlength="6"
            required>

            <div class="password-actions">
                <button type="submit" class="password-save-btn">ذخیره رمز عبور</button>
                <button type="button" class="modal-close" onclick="closePasswordModal()">بازگشت</button>
            </div>
        </form>
    </div>
</div>

<script>
const userProfiles = <?= json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

function toggleUserMenu(id){
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if(menu.id !== 'user-menu-' + id){
            menu.classList.remove('show');
        }
    });
    document.getElementById('user-menu-' + id).classList.toggle('show');
}

document.addEventListener('click', function(e){
    if(!e.target.closest('.row-menu')){
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));
    }
});

function setText(id, value){
    document.getElementById(id).textContent = value || '-';
}

function openProfileModal(id){
    const profile = userProfiles[id];
    if(!profile){
        return;
    }

    setText('profile_fullname', profile.fullname);
    setText('profile_job_title', profile.job_title);
    setText('profile_mobile', profile.mobile);
    setText('profile_national_code', profile.national_code);
    setText('profile_status', profile.status);

    const servicesList = document.getElementById('profile_services');
    servicesList.innerHTML = '';

    if(profile.services && profile.services.length){
        profile.services.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item;
            servicesList.appendChild(li);
        });
    }else{
        const li = document.createElement('li');
        li.textContent = 'محل خدمتی ثبت نشده است';
        servicesList.appendChild(li);
    }

    document.getElementById('profileModal').classList.add('show');
}

function closeProfileModal(){
    document.getElementById('profileModal').classList.remove('show');
}

document.getElementById('profileModal').addEventListener('click', function(e){
    if(e.target === this){
        closeProfileModal();
    }
});

function openPasswordModal(id){
    const profile = userProfiles[id];
    if(!profile){
        return;
    }

    document.getElementById('change_password_user_id').value = id;
    document.getElementById('password_user_name').textContent = profile.fullname || '-';
    document.getElementById('new_password').value = '';
    document.getElementById('new_password_confirm').value = '';
    document.getElementById('passwordModal').classList.add('show');
}

function closePasswordModal(){
    document.getElementById('passwordModal').classList.remove('show');
}

document.getElementById('passwordModal').addEventListener('click', function(e){
    if(e.target === this){
        closePasswordModal();
    }
});

document.getElementById('passwordForm').addEventListener('submit', function(e){
    const password = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('new_password_confirm').value;

    if(password.length < 6){
        e.preventDefault();
        alert('رمز عبور باید حداقل ۶ کاراکتر باشد');
        return;
    }

    if(password !== confirmPassword){
        e.preventDefault();
        alert('تکرار رمز عبور با رمز جدید یکسان نیست');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
