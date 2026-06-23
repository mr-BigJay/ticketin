<?php

require '../includes/admin_auth.php';

$user_id = (int)($_GET['id'] ?? 0);

if(!$user_id){

    header("Location: users.php");

    exit;

}

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id=? AND role='user'
");

$stmt->execute([$user_id]);

$user = $stmt->fetch();

if(!$user){

    header("Location: users.php");

    exit;

}

$userNodes = $pdo->prepare("
    SELECT

    user_organization_rel.id as rel_id,

    child.name as child_name,

    center.name as center_name,

    child.type as child_type

    FROM user_organization_rel

    LEFT JOIN organization_nodes child
    ON user_organization_rel.node_id = child.id

    LEFT JOIN organization_nodes center
    ON user_organization_rel.center_id = center.id

    WHERE user_organization_rel.user_id=?

    ORDER BY user_organization_rel.id DESC
");

$userNodes->execute([$user_id]);

$currentNodes = $userNodes->fetchAll();

$back_url = 'users.php';
$page_title = '👤 پروفایل کاربر';

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:850px;

    margin:auto;

}

.page-title{

    font-size:26px;

    font-weight:bold;

    margin-bottom:20px;

}

.card{

    background:white;

    border-radius:24px;

    padding:24px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.info-grid{

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:14px;

    margin-bottom:20px;

}

.info-item{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    line-height:34px;

}

.info-label{

    font-size:13px;

    color:#64748b;

}

.info-value{

    font-size:15px;

    font-weight:bold;

    color:#111827;

}

.status{

    display:inline-block;

    padding:7px 14px;

    border-radius:30px;

    font-size:12px;

    color:white;

}

.active{background:#10b981;}

.inactive{background:#ef4444;}

.pending{background:#f59e0b;}

.unit-title{

    font-size:18px;

    font-weight:bold;

    margin-bottom:14px;

}

.unit-card{

    background:linear-gradient(135deg,#eff6ff,#dbeafe);

    border-radius:18px;

    padding:16px;

    margin-bottom:12px;

}

.unit-name{

    font-size:15px;

    font-weight:bold;

    color:#1e3a8a;

}

.actions{

    display:flex;

    gap:10px;

    margin-top:20px;

}

.btn-link{

    text-decoration:none;

    padding:12px 18px;

    border-radius:14px;

    font-size:14px;

    font-weight:700;

}

.btn-edit{

    background:#2563eb;

    color:white;

}

.btn-back{

    background:#e2e8f0;

    color:#334155;

}

@media(max-width:768px){

    .info-grid{

        grid-template-columns:1fr;

    }

}

</style>

<div class="page-box">

<div class="card">

<div class="info-grid">

<div class="info-item">

<div class="info-label">نام و نام خانوادگی</div>

<div class="info-value"><?= htmlspecialchars($user['fullname']) ?></div>

</div>

<div class="info-item">

<div class="info-label">شماره موبایل</div>

<div class="info-value"><?= htmlspecialchars($user['mobile']) ?></div>

</div>

<div class="info-item">

<div class="info-label">کد ملی</div>

<div class="info-value"><?= htmlspecialchars($user['national_code']) ?></div>

</div>

<div class="info-item">

<div class="info-label">پست سازمانی</div>

<div class="info-value"><?= htmlspecialchars($user['job_title'] ?: '-') ?></div>

</div>

<div class="info-item">

<div class="info-label">وضعیت</div>

<div class="info-value">

<span class="status <?= $user['status'] ?>">

<?php

if($user['status'] == 'active') echo 'فعال';
elseif($user['status'] == 'inactive') echo 'غیرفعال';
else echo 'در انتظار تایید';

?>

</span>

</div>

</div>

</div>

<div class="unit-title">🏢 محل‌های خدمت</div>

<?php if(count($currentNodes)): ?>

<?php foreach($currentNodes as $node): ?>

<div class="unit-card">

<div class="unit-name">

<?= htmlspecialchars($node['center_name']) ?>

-

<?= htmlspecialchars($node['child_name']) ?>

</div>

<div style="margin-top:6px;font-size:13px;color:#64748b;">

<?= $node['child_type'] == 'unit' ? 'واحد مستقر در مرکز' : 'خانه بهداشت' ?>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="info-item">هیچ محل خدمتی ثبت نشده</div>

<?php endif; ?>

<div class="actions">

<?php if(!admin_users_readonly()): ?>
<a href="user-edit.php?id=<?= $user['id'] ?>" class="btn-link btn-edit">✏️ ویرایش</a>
<?php endif; ?>

<a href="users.php" class="btn-link btn-back">بازگشت به لیست</a>

</div>

</div>

</div>

<?php include '../includes/footer.php'; ?>
