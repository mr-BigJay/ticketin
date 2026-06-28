<?php
require '../includes/admin_auth.php';
require '../includes/user_helpers.php';
require_once '../includes/pagination_helpers.php';

user_ensure_schema($pdo);

// جستجو
$search = trim($_GET['search'] ?? '');

// صفحه بندی
$pagination = pagination_parse_request();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

// کوئری کاربران Pending
$where = "WHERE status='pending'";
$params = [];

if($search){
    $where .= " AND (fullname LIKE ? OR national_code LIKE ? OR mobile LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// شمارش کل
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
$totalPages = pagination_total_pages($total, $limit);
$page = pagination_clamp_page($page, $totalPages);

// گرفتن کاربران Pending
$stmt = $pdo->prepare("
    SELECT *
    FROM users
    $where
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pendingUsersFilterQuery = [];

if($search !== ''){
    $pendingUsersFilterQuery['search'] = $search;
}

// گرفتن لیست Job Titles برای مودال تایید
$jobTitles = $pdo->query("SELECT * FROM job_titles ORDER BY id ASC")->fetchAll();

if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];

    if(!user_delete_account($pdo, $id)){
        die('حذف کاربر انجام نشد');
    }

    header('Location: pending-users.php');
    exit;
}

if(isset($_POST['approve_user'])){

    $user_id =
    (int)$_POST['user_id'];

    $job_title_id =
    (int)$_POST['job_title_id'];

    $jobStmt = $pdo->prepare("
        SELECT title
        FROM job_titles
        WHERE id=?
    ");

    $jobStmt->execute([
        $job_title_id
    ]);

    $job =
    $jobStmt->fetch();

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            status='active',
            job_title_id=?,
            job_title=?
        WHERE id=?
    ");

    $stmt->execute([

        $job_title_id,
        $job['title'],
        $user_id

    ]);

    header(
        "Location: users.php"
    );

    exit;
}
$back_url = 'index.php';

require '../includes/header.php';
?>

<div class="page-box">
    <div class="page-title">⏳ کاربران در انتظار تایید</div>

    <div class="card">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" class="form-control"
                placeholder="جستجو بر اساس نام، موبایل یا کد ملی"
                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit" class="btn-custom">جستجو</button>
        </form>
    </div>

    <div class="card">
        <?php if(count($users)): ?>
            <?php foreach($users as $idx => $user): ?>
                <div class="user-item" id="row-<?= $user['id'] ?>">
                    <div class="user-info">
                        <div>
                            <div class="user-name"><?= htmlspecialchars($user['fullname']) ?></div>
                            <div class="user-national">کد ملی: <?= htmlspecialchars($user['national_code']) ?></div>
                        </div>
                    </div>
                    <div class="job-menu">
                        <button class="menu-btn" type="button" onclick="toggleMenu(<?= $user['id'] ?>)">⋮</button>
                        <div id="menu-<?= $user['id'] ?>" class="dropdown-menu">
                            <button type="button" onclick="openApproveModal(<?= $user['id'] ?>,'<?= htmlspecialchars($user['fullname'],ENT_QUOTES) ?>')">✔ تایید</button>
                            <button type="button" onclick="rejectUser(<?= $user['id'] ?>)">❌ رد</button>
                            <button type="button" onclick="openDeleteModal(<?= $user['id'] ?>,'<?= htmlspecialchars($user['fullname'],ENT_QUOTES) ?>')">🗑 حذف</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
            pagination_render_bar(
                $page,
                $limit,
                $total,
                $totalPages,
                $pendingUsersFilterQuery
            );
            ?>
        <?php else: ?>
            <div class="empty-box">کاربر در انتظار تایید وجود ندارد</div>
        <?php endif; ?>
    </div>
</div>

<!-- مودال تایید کاربر -->
<div id="approveModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-title">تایید کاربر</div>
        <form method="POST" id="approveForm">
            <input type="hidden" name="user_id" id="approve_id">
			<input type="hidden" name="approve_user" value="1">
            <label>پست سازمانی:</label>
            <select name="job_title_id" id="approve_job" class="form-control" required>
                <option value="">انتخاب کنید</option>
                <?php foreach($jobTitles as $job): ?>
                    <option value="<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="modal-actions">
                <button type="submit" class="modal-btn save-btn">تایید</button>
                <button type="button" onclick="closeApproveModal()" class="modal-btn cancel-btn">بازگشت</button>
            </div>
        </form>
    </div>
</div>

<!-- مودال حذف کاربر -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-title">حذف کاربر</div>
        <div id="deleteText"></div>
        <div class="modal-actions">
            <a id="deleteLink" href="#" class="modal-btn delete-confirm">حذف</a>
            <button type="button" onclick="closeDeleteModal()" class="modal-btn cancel-btn">انصراف</button>
        </div>
    </div>
</div>

<style>
.card{
    overflow:visible;
}
.user-item{
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#f8fafc;
    border-radius:18px;
    padding:14px;
    margin-bottom:12px;
    position:relative;
    overflow:visible;
    z-index:1;
}
.user-item.menu-open{
    z-index:100;
}
.user-info{
    display:flex;
    gap:12px;
    align-items:center;
}
.user-row-number{
    font-weight:700;
}
.job-menu{position:relative;}
.menu-btn{
    width:40px; height:40px; border:none; border-radius:12px;
    background:#f1f5f9; font-size:22px; cursor:pointer;
}
.menu-btn:hover{background:#e2e8f0;}
.dropdown-menu{
    position:absolute; top:45px; left:0; min-width:160px;
    background:#fff; border-radius:16px; border:1px solid #eef2f7;
    box-shadow:0 12px 35px rgba(15,23,42,.15);
    display:none; overflow:hidden; z-index:9999;
}
.dropdown-menu.show{display:block;}
.dropdown-menu button{
    width:100%; border:none; background:none; text-align:right;
    padding:10px 16px; font-size:14px; font-weight:700; cursor:pointer;
}
.dropdown-menu button:hover{background:#f8fafc;}

.modal-overlay{
    position:fixed; inset:0;
    background:rgba(15,23,42,.35); backdrop-filter:blur(8px);
    display:none; justify-content:center; align-items:center; z-index:9999;
}
.modal-overlay.show{display:flex;}
.modal-box{
    width:90%; max-width:480px; background:#fff; border-radius:20px; padding:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.15); animation:modalIn .2s ease;
}
@keyframes modalIn{from{opacity:0; transform:translateY(15px);}to{opacity:1; transform:none;}}
.modal-title{font-size:20px; font-weight:800; margin-bottom:18px; color:#0f172a;}
.modal-actions{display:flex; gap:10px; margin-top:20px;}
.modal-btn{flex:1; border:none; padding:14px; border-radius:16px; cursor:pointer; font-family:'Vazirmatn'; font-weight:700;}
.save-btn{background:linear-gradient(135deg,#0284c7,#06b6d4); color:white;}
.cancel-btn{background:#f1f5f9; color:#334155;}
.delete-confirm{background:#ef4444; color:white;}

.pagination a.page-link{display:inline-block;background:#fff; padding:10px 14px; border-radius:12px; margin:4px;text-decoration:none; color:#333; box-shadow:0 0 10px rgba(0,0,0,0.05);}
.pagination a.active-page{background:linear-gradient(135deg,#0284c7,#06b6d4); color:#fff; border:none;}
</style>

<script>
function toggleMenu(id){
    document.querySelectorAll('.dropdown-menu').forEach(menu=>{
        if(menu.id!=='menu-'+id) menu.classList.remove('show');
    });
    document.querySelectorAll('.user-item').forEach(row=>{
        row.classList.remove('menu-open');
    });
    const menu = document.getElementById('menu-'+id);
    const row = document.getElementById('row-'+id);
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
document.addEventListener('click',function(e){
    if(!e.target.closest('.job-menu')){
        document.querySelectorAll('.dropdown-menu').forEach(menu=>{
            menu.classList.remove('show');
            menu.style.top = '45px';
            menu.style.bottom = 'auto';
        });
        document.querySelectorAll('.user-item').forEach(row=>{
            row.classList.remove('menu-open');
        });
    }
});
function openApproveModal(id,name){
    document.getElementById('approve_id').value = id;
    document.getElementById('approveModal').classList.add('show');
}
function closeApproveModal(){
    document.getElementById('approveModal').classList.remove('show');
}
function openDeleteModal(id,name){
    document.getElementById('deleteText').innerHTML = 'آیا از حذف <b>'+name+'</b> مطمئن هستید؟';
    document.getElementById('deleteLink').href = '?delete='+id;
    document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal(){
    document.getElementById('deleteModal').classList.remove('show');
}
function rejectUser(id){
    if(confirm("آیا از رد این کاربر مطمئن هستید؟")){
        window.location.href="?reject="+id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>