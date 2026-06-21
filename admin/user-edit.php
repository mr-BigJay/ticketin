<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$page_title = '✏️ ویرایش کاربر';
$back_url = 'users.php';

$userId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if(!$userId){

    die("کاربر نامعتبر است");

}

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id=? AND role='user'
");

$stmt->execute([$userId]);
$user = $stmt->fetch();

if(!$user){

    die("کاربر یافت نشد");

}

$message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $fullname = trim($_POST['fullname'] ?? '');
    $nationalCode = trim($_POST['national_code'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $jobTitleId = (int)($_POST['job_title_id'] ?? 0);

    if(
        !$fullname
        ||
        !$nationalCode
        ||
        !$mobile
        ||
        !in_array($status, ['active','inactive'], true)
    ){

        $message = 'فیلدهای الزامی را کامل کنید';

    }else{

        $jobTitle = '';

        if($jobTitleId){

            $jobStmt = $pdo->prepare("
                SELECT title
                FROM job_titles
                WHERE id=?
            ");

            $jobStmt->execute([$jobTitleId]);
            $job = $jobStmt->fetch();

            if($job){

                $jobTitle = $job['title'];

            }

        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                fullname=?,
                national_code=?,
                mobile=?,
                status=?,
                job_title_id=?,
                job_title=?
            WHERE id=? AND role='user'
        ");

        $stmt->execute([
            $fullname,
            $nationalCode,
            $mobile,
            $status,
            $jobTitleId ?: null,
            $jobTitle,
            $userId
        ]);

        header("Location: users.php");
        exit;

    }

}

$jobTitles = $pdo->query("
    SELECT *
    FROM job_titles
    ORDER BY id ASC
")->fetchAll();

$serviceStmt = $pdo->prepare("
    SELECT
        child.name as child_name,
        child.type as child_type,
        center.name as center_name
    FROM user_organization_rel rel
    LEFT JOIN organization_nodes child ON rel.node_id = child.id
    LEFT JOIN organization_nodes center ON rel.center_id = center.id
    WHERE rel.user_id=?
    ORDER BY rel.id ASC
");

$serviceStmt->execute([$userId]);
$services = $serviceStmt->fetchAll();

require '../includes/header.php';

?>

<style>
.page-box{max-width:900px;margin:auto;}
.page-title{font-size:26px;font-weight:800;margin-bottom:20px;color:#0f172a;}
.card{background:white;border-radius:24px;padding:24px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,.05);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.service-list{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:18px;
    padding:16px 24px;
    line-height:34px;
    color:#334155;
}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;}
.save-btn{
    border:none;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
    padding:14px 22px;
    border-radius:16px;
    font-family:inherit;
    font-weight:800;
    cursor:pointer;
}
.cancel-link{
    text-decoration:none;
    background:#f1f5f9;
    color:#334155;
    padding:14px 22px;
    border-radius:16px;
    font-weight:800;
}
@media(max-width:768px){
    .form-grid{grid-template-columns:1fr;}
}
</style>

<div class="page-box">

<div class="page-title">✏️ ویرایش کاربر</div>

<?php if($message): ?>
<div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
<form method="POST">
    <input type="hidden" name="id" value="<?= $userId ?>">

    <div class="form-grid">
        <input
        type="text"
        name="fullname"
        class="form-control"
        placeholder="نام و نام خانوادگی"
        required
        value="<?= htmlspecialchars($user['fullname']) ?>">

        <input
        type="text"
        name="national_code"
        class="form-control"
        placeholder="کد ملی"
        required
        maxlength="10"
        value="<?= htmlspecialchars($user['national_code']) ?>">

        <input
        type="text"
        name="mobile"
        class="form-control"
        placeholder="شماره موبایل"
        required
        value="<?= htmlspecialchars($user['mobile']) ?>">

        <select name="job_title_id" class="form-control">
            <option value="">بدون پست سازمانی</option>
            <?php foreach($jobTitles as $job): ?>
            <option
            value="<?= $job['id'] ?>"
            <?= (int)$user['job_title_id'] === (int)$job['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($job['title']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-control">
            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
        </select>
    </div>

    <div class="actions">
        <button type="submit" class="save-btn">ذخیره تغییرات</button>
        <a href="users.php" class="cancel-link">انصراف</a>
    </div>
</form>
</div>

<div class="card">
    <div class="page-title" style="font-size:20px;">محل خدمت ثبت شده</div>
    <?php if(count($services)): ?>
    <ul class="service-list">
        <?php foreach($services as $service): ?>
        <?php
        $typeText = [
            'unit' => 'واحد',
            'health_house' => 'خانه بهداشت',
            'center' => 'مرکز'
        ][$service['child_type'] ?? ''] ?? '';
        $parts = array_filter([
            $service['center_name'] ?? '',
            $service['child_name'] ?? ''
        ]);
        ?>
        <li>
            <?= htmlspecialchars($parts ? implode(' - ', $parts) : '-') ?>
            <?= $typeText ? '(' . htmlspecialchars($typeText) . ')' : '' ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="empty-box">محل خدمتی ثبت نشده است</div>
    <?php endif; ?>
</div>

</div>

<?php include '../includes/footer.php'; ?>
