<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

if(isset($_POST['approve_user_id'])){

    $userId =
    (int)$_POST['approve_user_id'];

    $jobTitleId =
    (int)$_POST['job_title_id'];

    $stmt = $pdo->prepare("
        SELECT title
        FROM job_titles
        WHERE id=?
    ");

    $stmt->execute([
        $jobTitleId
    ]);

    $job =
    $stmt->fetch();

    if($job){

        $stmt = $pdo->prepare("
            UPDATE users
            SET
            status='active',
            job_title_id=?,
            job_title=?
            WHERE id=?
        ");

        $stmt->execute([

            $jobTitleId,
            $job['title'],
            $userId

        ]);

    }

    header("Location: users.php");

    exit;

}

if(isset($_GET['deactivate'])){

    $id = (int)$_GET['deactivate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='inactive'
        WHERE id=?
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
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header("Location: users.php");

    exit;

}

if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header("Location: users.php");

    exit;

}

$where = [];

$params = [];

$where[] = "role='user'";
$where[] = "status!='pending'";

if(!empty($_GET['status'])){

    $where[] = "status=?";

    $params[] = $_GET['status'];

}

if(!empty($_GET['search'])){

    $where[] = "(
        fullname LIKE ?
        OR mobile LIKE ?
        OR job_title LIKE ?
    )";

    $search =
    "%" . $_GET['search'] . "%";

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

}

$whereSql =
"WHERE " . implode(" AND ",$where);

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    $whereSql
    ORDER BY id DESC
");

$stmt->execute($params);

$users = $stmt->fetchAll();
$jobTitles =
$pdo->query("
SELECT *
FROM job_titles
ORDER BY title ASC
")->fetchAll();

require '../includes/header.php';

?>

<div style="margin-bottom:20px;">

<a
href="javascript:history.back()"
class="back-btn-top">

← بازگشت

</a>

</div>

<style>

.page-box{

    max-width:1100px;

    margin:auto;

}

.page-title{

    font-size:24px;

    font-weight:bold;

    margin-bottom:20px;

}

.card{

    background:white;

    border-radius:22px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.user-card{

    background:#f8fafc;

    border-radius:18px;

    padding:18px;

    margin-bottom:14px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    flex-wrap:wrap;

}

.user-info{

    line-height:34px;

}

.user-name{

    font-size:16px;

    font-weight:bold;

}

.user-meta{

    color:#64748b;

    font-size:14px;

}

.status{

    display:inline-block;

    padding:7px 14px;

    border-radius:30px;

    font-size:12px;

    color:white;

    margin-top:8px;

}

.pending{

    background:#f59e0b;

}

.active{

    background:#10b981;

}

.inactive{

    background:#ef4444;

}

.actions{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.btn{

    text-decoration:none;

    padding:10px 14px;

    border-radius:12px;

    color:white;

    font-size:13px;

    font-weight:bold;

}

.btn-approve{

    background:#10b981;

}

.btn-deactivate{

    background:#f59e0b;

}

.btn-activate{

    background:#2563eb;

}

.btn-delete{

    background:#ef4444;

}

.empty-box{

    text-align:center;

    color:#777;

    padding:25px;

}

.filter-grid{

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:12px;

}

@media(max-width:768px){

    .filter-grid{

        grid-template-columns:1fr;

    }

}

</style>

<div class="page-box">

<div class="page-title">

👥 مدیریت کاربران

</div>

<div class="card">

<form method="GET">

<div class="filter-grid">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجو نام، شماره یا سمت"
value="<?= $_GET['search'] ?? '' ?>">

<select
name="status"
class="form-control">

<option value="">
همه وضعیت ها
</option>

<option value="pending">

در انتظار تایید

</option>

<option value="active">

فعال

</option>

<option value="inactive">

غیرفعال

</option>

</select>

</div>

<button
type="submit"
class="btn-custom">

جستجو کاربران

</button>

</form>

</div>

<div class="card">

<?php if(count($users)): ?>

<?php foreach($users as $user): ?>

<div class="user-card">

<div class="user-info">

<div class="user-name">

<?= htmlspecialchars($user['fullname']) ?>

</div>

<div class="user-meta">

📱 <?= htmlspecialchars($user['mobile']) ?>

<br>

🏢 <?= htmlspecialchars($user['job_title']) ?>

</div>

<div
class="status <?= $user['status'] ?>">

<?php

if($user['status'] == 'pending'){

    echo 'در انتظار تایید';

}elseif($user['status'] == 'active'){

    echo 'فعال';

}else{

    echo 'غیرفعال';

}

?>

</div>

</div>

<div class="actions">

<?php if($user['status'] == 'pending'): ?>

<a
<a
href="#"
class="btn btn-approve"
onclick="openApproveModal(
<?= $user['id'] ?>
)">

تایید

</a>

<?php endif; ?>

<?php if($user['status'] == 'active'): ?>

<a
href="?deactivate=<?= $user['id'] ?>"
class="btn btn-deactivate">

غیرفعال

</a>

<?php endif; ?>

<?php if($user['status'] == 'inactive'): ?>

<a
href="?activate=<?= $user['id'] ?>"
class="btn btn-activate">

فعال سازی

</a>

<?php endif; ?>

<a
href="?delete=<?= $user['id'] ?>"
class="btn btn-delete"
onclick="return confirm('کاربر حذف شود؟')">

حذف

</a>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-box">

کاربری یافت نشد

</div>

<?php endif; ?>

</div>

</div>
<div
class="approve-modal-overlay"
id="approveModal">

<div class="approve-modal">

<form method="POST">

<input
type="hidden"
name="approve_user_id"
id="approve_user_id">

<div class="approve-title">

👤 انتخاب پست سازمانی

</div>

<select
name="job_title_id"
class="form-control"
required>

<option value="">

انتخاب پست سازمانی

</option>

<?php foreach($jobTitles as $job): ?>

<option
value="<?= $job['id'] ?>">

<?= htmlspecialchars(
$job['title']
) ?>

</option>

<?php endforeach; ?>

</select>

<div class="approve-buttons">

<button
type="button"
class="btn-cancel"
onclick="closeApproveModal()">

بازگشت

</button>

<button
type="submit"
class="btn-confirm">

تایید و فعال سازی

</button>

</div>

</form>

</div>

</div>

<style>

.approve-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    backdrop-filter:blur(8px);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:999999;
}

.approve-modal{
    background:white;
    width:100%;
    max-width:520px;
    border-radius:24px;
    padding:24px;
}

.approve-title{
    font-size:22px;
    font-weight:800;
    margin-bottom:20px;
}

.approve-buttons{
    display:flex;
    gap:10px;
    margin-top:20px;
}

.btn-confirm{
    flex:1;
    background:#10b981;
    color:white;
    border:none;
    padding:14px;
    border-radius:14px;
    cursor:pointer;
}

.btn-cancel{
    flex:1;
    background:#e2e8f0;
    border:none;
    padding:14px;
    border-radius:14px;
    cursor:pointer;
}

</style>

<script>

function openApproveModal(id){

    document.getElementById(
        'approve_user_id'
    ).value = id;

    document.getElementById(
        'approveModal'
    ).style.display='flex';

}

function closeApproveModal(){

    document.getElementById(
        'approveModal'
    ).style.display='none';

}

</script>
<?php include '../includes/footer.php'; ?>
