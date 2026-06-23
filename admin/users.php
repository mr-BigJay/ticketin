<?php

require '../includes/admin_auth.php';

function users_redirect(){
    $params = $_GET;
    unset($params['deactivate'], $params['activate'], $params['delete']);
    $qs = http_build_query($params);
    header('Location: users.php' . ($qs ? '?' . $qs : ''));
    exit;
}

if(isset($_POST['approve_user_id'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

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
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $id = (int)$_GET['deactivate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='inactive'
        WHERE id=?
    ");

    $stmt->execute([$id]);

    users_redirect();

}

if(isset($_GET['activate'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $id = (int)$_GET['activate'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET status='active'
        WHERE id=?
    ");

    $stmt->execute([$id]);

    users_redirect();

}

if(isset($_GET['delete'])){
    if(admin_users_readonly()){
        die('دسترسی غیر مجاز');
    }

    $id = (int)$_GET['delete'];

    $pdo->prepare("DELETE FROM user_organization_rel WHERE user_id=?")->execute([$id]);

    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE id=?
    ");

    $stmt->execute([$id]);

    users_redirect();

}

$where = [];

$params = [];

$where[] = "u.role='user'";
$where[] = "u.status!='pending'";

if(!empty($_GET['status'])){

    $where[] = "u.status=?";

    $params[] = $_GET['status'];

}

if(!empty($_GET['search'])){

    $where[] = "(
        u.fullname LIKE ?
        OR u.job_title LIKE ?
    )";

    $search =
    "%" . $_GET['search'] . "%";

    $params[] = $search;
    $params[] = $search;

}

if(!empty($_GET['job_title_id'])){

    $where[] = "u.job_title_id=?";

    $params[] = (int)$_GET['job_title_id'];

}

$center_id = (int)($_GET['center_id'] ?? 0);
$sub_type = trim($_GET['sub_type'] ?? '');
$node_id = (int)($_GET['node_id'] ?? 0);

$joins = "";

if($center_id || $node_id || $sub_type){

    $joins = "
        INNER JOIN user_organization_rel uor
        ON u.id = uor.user_id
    ";

    if($node_id){

        $where[] = "uor.node_id=?";
        $params[] = $node_id;

    }elseif($center_id && $sub_type){

        $joins .= "
            INNER JOIN organization_nodes org_node
            ON uor.node_id = org_node.id
        ";

        $where[] = "uor.center_id=?";
        $where[] = "org_node.type=?";

        $params[] = $center_id;
        $params[] = $sub_type;

    }elseif($center_id){

        $where[] = "uor.center_id=?";
        $params[] = $center_id;

    }

}

$whereSql =
"WHERE " . implode(" AND ",$where);

$stmt = $pdo->prepare("
    SELECT DISTINCT u.*
    FROM users u
    $joins
    $whereSql
    ORDER BY u.id DESC
");

$stmt->execute($params);

$users = $stmt->fetchAll();

$jobTitles =
$pdo->query("
SELECT *
FROM job_titles
ORDER BY title ASC
")->fetchAll();

$centers = $pdo->query("
    SELECT *
    FROM organization_nodes
    WHERE type='center'
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$filterQuery = $_GET;
unset($filterQuery['deactivate'], $filterQuery['activate'], $filterQuery['delete']);
$filterQs = http_build_query($filterQuery);
$filterPrefix = $filterQs ? '?' . $filterQs . '&' : '?';

$back_url = 'index.php';
$page_title = '👥 مدیریت کاربران';

require '../includes/header.php';

?>

<div class="page-box">

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

    overflow:visible;

}

.filter-grid{

    display:grid;

    grid-template-columns:1fr 1fr 1fr;

    gap:12px;

}

.filter-grid .form-control{

    margin-bottom:0;

}

.users-table-wrap{

    overflow:visible;

    position:relative;

}

.users-table-header,
.user-row{

    display:grid;

    grid-template-columns:70px 110px 1.2fr 1fr;

    gap:12px;

    align-items:center;

    padding:14px 16px;

}

.users-table-header{

    font-size:13px;

    font-weight:700;

    color:#64748b;

    border-bottom:2px solid #eef2f7;

    margin-bottom:8px;

}

.user-row{

    background:#f8fafc;

    border-radius:18px;

    margin-bottom:10px;

    position:relative;

    overflow:visible;

    z-index:1;

}

.user-row.menu-open{

    z-index:100;

}

.status{

    display:inline-block;

    padding:7px 14px;

    border-radius:30px;

    font-size:12px;

    color:white;

    text-align:center;

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

.user-cell{

    font-size:14px;

    color:#334155;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;

}

.user-cell.name{

    font-weight:700;

    color:#0f172a;

}

.job-menu{

    position:relative;

}

.menu-btn{

    width:40px;

    height:40px;

    border:none;

    border-radius:12px;

    background:#f1f5f9;

    color:#334155;

    font-size:22px;

    cursor:pointer;

    transition:.2s;

}

.menu-btn:hover{

    background:#e2e8f0;

}

.dropdown-menu{

    position:absolute;

    top:45px;

    left:0;

    min-width:160px;

    background:#fff;

    border-radius:16px;

    border:1px solid #eef2f7;

    box-shadow:0 12px 35px rgba(15,23,42,.15);

    display:none;

    overflow:hidden;

    z-index:9999;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu a{

    display:flex;

    align-items:center;

    gap:8px;

    padding:12px 16px;

    text-decoration:none;

    color:#334155;

    font-size:13px;

    font-weight:700;

    transition:.2s;

}

.dropdown-menu a:hover{

    background:#f8fafc;

}

.dropdown-menu a.danger{

    color:#ef4444;

}

.empty-box{

    text-align:center;

    color:#777;

    padding:25px;

}

@media(max-width:768px){

    .filter-grid{

        grid-template-columns:1fr;

    }

    .users-table-header{

        display:none;

    }

    .user-row{

        grid-template-columns:1fr auto;

        grid-template-rows:auto auto auto;

        gap:8px;

    }

    .user-row .user-cell.name{

        grid-column:1;

        grid-row:1;

    }

    .user-row .user-cell.job{

        grid-column:1;

        grid-row:2;

    }

    .user-row .status{

        grid-column:2;

        grid-row:1;

    }

    .user-row .job-menu{

        grid-column:2;

        grid-row:2;

    }

}

</style>

<div class="card">

<form method="GET">

<div class="filter-grid">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجو نام یا پست سازمانی"
value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

<select
name="status"
class="form-control">

<option value="">
همه وضعیت ها
</option>

<option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>

در انتظار تایید

</option>

<option value="active" <?= ($_GET['status'] ?? '') == 'active' ? 'selected' : '' ?>>

فعال

</option>

<option value="inactive" <?= ($_GET['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>

غیرفعال

</option>

</select>

<select
name="job_title_id"
class="form-control">

<option value="">
همه پست‌های سازمانی
</option>

<?php foreach($jobTitles as $job): ?>

<option
value="<?= $job['id'] ?>"
<?= (int)($_GET['job_title_id'] ?? 0) === (int)$job['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($job['title']) ?>

</option>

<?php endforeach; ?>

</select>

<select
name="center_id"
id="centerSelect"
class="form-control">

<option value="">
همه مراکز
</option>

<?php foreach($centers as $center): ?>

<option
value="<?= $center['id'] ?>"
<?= $center_id === (int)$center['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($center['name']) ?>

</option>

<?php endforeach; ?>

</select>

<select
name="sub_type"
id="subTypeSelect"
class="form-control">

<option value="">
نوع محل خدمت
</option>

<option
value="health_house"
<?= $sub_type === 'health_house' ? 'selected' : '' ?>>

خانه بهداشت
</option>

<option
value="unit"
<?= $sub_type === 'unit' ? 'selected' : '' ?>>

واحد مستقر در مرکز
</option>

</select>

<select
name="node_id"
id="nodeSelect"
class="form-control">

<option value="">
همه واحدها
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

<div class="users-table-wrap">

<div class="users-table-header">

<div>منو</div>

<div>وضعیت</div>

<div>نام و نام خانوادگی</div>

<div>پست سازمانی</div>

</div>

<?php foreach($users as $user): ?>

<div class="user-row" id="row-<?= $user['id'] ?>">

<div class="job-menu">

<button
class="menu-btn"
type="button"
onclick="toggleMenu(event, <?= $user['id'] ?>)">

⋮

</button>

<div
id="menu-<?= $user['id'] ?>"
class="dropdown-menu">

<a href="user-view.php?id=<?= $user['id'] ?>">

👤 مشاهده پروفایل

</a>

<?php if(!admin_users_readonly()): ?>

<a href="user-edit.php?id=<?= $user['id'] ?>">

✏️ ویرایش

</a>

<?php if($user['status'] == 'active'): ?>

<a href="<?= $filterPrefix ?>deactivate=<?= $user['id'] ?>">

⏸ غیرفعال

</a>

<?php endif; ?>

<?php if($user['status'] == 'inactive'): ?>

<a href="<?= $filterPrefix ?>activate=<?= $user['id'] ?>">

▶️ فعال سازی

</a>

<?php endif; ?>

<a
href="<?= $filterPrefix ?>delete=<?= $user['id'] ?>"
class="danger"
onclick="return confirm('کاربر حذف شود؟')">

🗑 حذف

</a>

<?php endif; ?>

</div>

</div>

<div>

<span class="status <?= $user['status'] ?>">

<?php

if($user['status'] == 'pending'){

    echo 'در انتظار تایید';

}elseif($user['status'] == 'active'){

    echo 'فعال';

}else{

    echo 'غیرفعال';

}

?>

</span>

</div>

<div class="user-cell name">

<?= htmlspecialchars($user['fullname'] ?: '-') ?>

</div>

<div class="user-cell job">

<?= htmlspecialchars($user['job_title'] ?: '-') ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="empty-box">

کاربری یافت نشد

</div>

<?php endif; ?>

</div>

</div>

<script>

function toggleMenu(event, id){

    event.stopPropagation();

    document.querySelectorAll('.dropdown-menu').forEach(menu => {

        if(menu.id !== 'menu-' + id){

            menu.classList.remove('show');

        }

    });

    document.querySelectorAll('.user-row').forEach(row => {

        row.classList.remove('menu-open');

    });

    const menu = document.getElementById('menu-' + id);
    const row = document.getElementById('row-' + id);

    menu.classList.toggle('show');

    if(menu.classList.contains('show')){

        row.classList.add('menu-open');

        const rect = menu.getBoundingClientRect();

        if(rect.bottom > window.innerHeight){

            menu.style.top = 'auto';

            menu.style.bottom = '45px';

        }else{

            menu.style.top = '45px';

            menu.style.bottom = 'auto';

        }

    }

}

document.addEventListener('click', function(e){

    if(!e.target.closest('.job-menu')){

        document.querySelectorAll('.dropdown-menu').forEach(menu => {

            menu.classList.remove('show');

            menu.style.top = '45px';

            menu.style.bottom = 'auto';

        });

        document.querySelectorAll('.user-row').forEach(row => {

            row.classList.remove('menu-open');

        });

    }

});

const centerSelect = document.getElementById('centerSelect');
const subTypeSelect = document.getElementById('subTypeSelect');
const nodeSelect = document.getElementById('nodeSelect');
const selectedNodeId = <?= (int)$node_id ?>;

function loadOrgNodes(){

    const centerId = centerSelect.value;
    const type = subTypeSelect.value;

    nodeSelect.innerHTML = '<option value="">همه واحدها</option>';

    if(!centerId || !type){
        return;
    }

    fetch('../tickets.php?action=subs&center_id=' + centerId + '&type=' + type)
    .then(response => response.json())
    .then(data => {

        data.forEach(item => {

            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;

            if(parseInt(item.id, 10) === selectedNodeId){
                option.selected = true;
            }

            nodeSelect.appendChild(option);

        });

    });

}

centerSelect.addEventListener('change', loadOrgNodes);
subTypeSelect.addEventListener('change', loadOrgNodes);

if(centerSelect.value && subTypeSelect.value){
    loadOrgNodes();
}

</script>

<?php include '../includes/footer.php'; ?>
