<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$message = "";
$page =
max(
1,
(int)($_GET['page'] ?? 1)
);

$limit = 20;

$offset =
($page - 1) * $limit;

if(isset($_POST['add'])){

    $title =
    trim($_POST['title']);

    if($title){

        $stmt = $pdo->prepare("
            INSERT INTO job_titles
            (title)
            VALUES
            (?)
        ");

        $stmt->execute([$title]);

        $message =
        "پست سازمانی ثبت شد";

    }

}

if(isset($_GET['delete'])){

    $id =
    (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM job_titles
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header(
        "Location: job-titles.php"
    );

    exit;

}

if(isset($_POST['edit_save'])){

    $id =
    (int)$_POST['id'];

    $title =
    trim($_POST['title']);

    $stmt = $pdo->prepare("
        UPDATE job_titles
        SET title=?
        WHERE id=?
    ");

    $stmt->execute([

        $title,
        $id

    ]);

    $message =
    "ویرایش انجام شد";

}

$editData = null;

if(isset($_GET['edit'])){

    $id =
    (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM job_titles
        WHERE id=?
    ");

    $stmt->execute([$id]);

    $editData =
    $stmt->fetch();

}

$totalRows =
$pdo->query("
    SELECT COUNT(*)
    FROM job_titles
")->fetchColumn();

$totalPages =
ceil(
    $totalRows / $limit
);

$jobs =
$pdo->query("
    SELECT *
    FROM job_titles
    ORDER BY id DESC
    LIMIT $limit
    OFFSET $offset
")->fetchAll();

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:900px;

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

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.job-item{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    margin-bottom:12px;

    display:flex;

    justify-content:space-between;

    align-items:center;

}

.job-name{

    font-size:15px;

    font-weight:bold;

}

.actions{

    display:flex;

    gap:8px;

}

.btn-sm{

    padding:8px 12px;

    border-radius:10px;

    text-decoration:none;

    color:white;

    font-size:12px;

}

.edit-btn{

    background:#2563eb;

}

.delete-btn{

    background:#ef4444;

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

    min-width:140px;

    background:#fff;

    border-radius:16px;

    box-shadow:
    0 12px 35px rgba(15,23,42,.15);

    border:1px solid #eef2f7;

    overflow:hidden;

    display:none;

    z-index:999;

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
.modal-overlay{

    position:fixed;

    inset:0;

    background:rgba(15,23,42,.35);

    backdrop-filter:blur(8px);

    display:none;

    justify-content:center;

    align-items:center;

    z-index:9999;

}

.modal-overlay.show{

    display:flex;

}

.modal-box{

    width:90%;

    max-width:500px;

    background:#fff;

    border-radius:24px;

    padding:24px;

    box-shadow:0 20px 60px rgba(0,0,0,.15);

    animation:modalIn .2s ease;

}

@keyframes modalIn{

    from{

        opacity:0;

        transform:translateY(15px);

    }

    to{

        opacity:1;

        transform:none;

    }

}

.modal-title{

    font-size:20px;

    font-weight:800;

    margin-bottom:18px;

    color:#0f172a;

}

.modal-actions{

    display:flex;

    gap:10px;

    margin-top:20px;

}

.modal-btn{

    flex:1;

    border:none;

    padding:14px;

    border-radius:16px;

    cursor:pointer;

    font-family:'Vazirmatn';

    font-weight:700;

}

.save-btn{

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

}

.cancel-btn{

    background:#f1f5f9;

    color:#334155;

}

.delete-confirm{

    background:#ef4444;

    color:white;

}
.pagination{

    display:flex;

    justify-content:center;

    gap:8px;

    margin-top:25px;

}

.page-link{

    width:42px;

    height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:14px;

    text-decoration:none;

    background:#fff;

    color:#0284c7;

    font-weight:800;

    border:1px solid #dbeafe;

}

.active-page{

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:#fff;

    border:none;

}

</style>

<div class="page-box">

<div style="margin-bottom:20px;">

<a
href="javascript:history.back()"
class="back-btn-top">

← بازگشت

</a>

</div>

<div class="page-title">

🏷 مدیریت پست های سازمانی

</div>

<div class="card">

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">

<form method="GET" style="flex:1;display:flex;gap:10px;">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجوی پست سازمانی"
value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

<button
type="submit"
class="btn-custom">

جستجو

</button>

</form>

<button
type="button"
class="btn-custom"
onclick="openAddModal()">

افزودن پست سازمانی

</button>

</div>

</div>

<div class="card">

<?php if(count($jobs)): ?>

<?php foreach($jobs as $job): ?>

<div class="job-item">

<div class="job-name">

<?= htmlspecialchars($job['title']) ?>

</div>

<div class="job-menu">

<button
type="button"
class="menu-btn"
onclick="toggleMenu(<?= $job['id'] ?>)">

⋮

</button>

<div
id="menu-<?= $job['id'] ?>"
class="dropdown-menu">

<button
type="button"
onclick="openEditModal(
<?= $job['id'] ?>,
'<?= htmlspecialchars(
$job['title'],
ENT_QUOTES
) ?>'
)">

✏️ ویرایش

</button>

<button
type="button"
onclick="openDeleteModal(
<?= $job['id'] ?>,
'<?= htmlspecialchars(
$job['title'],
ENT_QUOTES
) ?>'
)">

🗑 حذف

</button>

</div>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="alert">

هیچ پست سازمانی ثبت نشده

</div>

<?php endif; ?>

<?php if($totalPages > 1): ?>

<div class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<a
href="?page=<?= $i ?>"
class="page-link <?= $i==$page ? 'active-page' : '' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

</div>

<?php endif; ?>
</div>

</div>
<script>

function toggleMenu(id){

    document
    .querySelectorAll('.dropdown-menu')
    .forEach(menu => {

        if(menu.id !== 'menu-'+id){

            menu.classList.remove('show');

        }

    });

    document
    .getElementById('menu-'+id)
    .classList.toggle('show');

}

document.addEventListener(
'click',
function(e){

    if(
        !e.target.closest('.job-menu')
    ){

        document
        .querySelectorAll('.dropdown-menu')
        .forEach(menu => {

            menu.classList.remove('show');

        });

    }

});

</script>
<div
id="addModal"
class="modal-overlay">

<div class="modal-box">

<div class="modal-title">

افزودن پست سازمانی

</div>

<form method="POST">

<input
type="text"
name="title"
class="form-control"
placeholder="عنوان پست سازمانی"
required>

<div class="modal-actions">

<button
type="submit"
name="add"
class="modal-btn save-btn">

افزودن

</button>

<button
type="button"
onclick="closeAddModal()"
class="modal-btn cancel-btn">

انصراف

</button>

</div>

</form>

</div>

</div>
<div
id="editModal"
class="modal-overlay">

    <div class="modal-box">

        <div class="modal-title">

            ویرایش پست سازمانی

        </div>

        <form method="POST">

            <input
            type="hidden"
            name="id"
            id="edit_id">

            <input
            type="text"
            name="title"
            id="edit_title"
            class="form-control"
            required>

            <div class="modal-actions">

                <button
                type="submit"
                name="edit_save"
                class="modal-btn save-btn">

                    ذخیره تغییرات

                </button>

                <button
                type="button"
                onclick="closeEditModal()"
                class="modal-btn cancel-btn">

                    بازگشت

                </button>

            </div>

        </form>

    </div>

</div>
<div
id="deleteModal"
class="modal-overlay">

    <div class="modal-box">

        <div class="modal-title">

            حذف پست سازمانی

        </div>

        <div id="deleteText">

        </div>

        <div class="modal-actions">

            <a
            id="deleteLink"
            href="#"
            class="modal-btn delete-confirm"
            style="
            text-decoration:none;
            text-align:center;">

                حذف

            </a>

            <button
            type="button"
            onclick="closeDeleteModal()"
            class="modal-btn cancel-btn">

                انصراف

            </button>

        </div>

    </div>

</div>
<script>
function openAddModal(){

    document
    .getElementById('addModal')
    .classList.add('show');

}

function closeAddModal(){

    document
    .getElementById('addModal')
    .classList.remove('show');

}
function openEditModal(id,title){

    document
    .getElementById('edit_id')
    .value=id;

    document
    .getElementById('edit_title')
    .value=title;

    document
    .getElementById('editModal')
    .classList.add('show');

}

function closeEditModal(){

    document
    .getElementById('editModal')
    .classList.remove('show');

}

function openDeleteModal(id,title){

    document
    .getElementById('deleteText')
    .innerHTML=
    'آیا از حذف <b>'+title+'</b> مطمئن هستید؟';

    document
    .getElementById('deleteLink')
    .href='?delete='+id;

    document
    .getElementById('deleteModal')
    .classList.add('show');

}

function closeDeleteModal(){

    document
    .getElementById('deleteModal')
    .classList.remove('show');

}

window.onclick=function(e){

    if(
        e.target.classList.contains(
        'modal-overlay'
        )
    ){

        e.target.classList.remove('show');

    }

}

</script>
<?php include '../includes/footer.php'; ?>
