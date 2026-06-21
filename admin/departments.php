<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$message = "";

$editMode = false;

$editItem = null;

if(isset($_GET['edit'])){

    $editMode = true;

    $id = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM categories
        WHERE id=?
    ");

    $stmt->execute([$id]);

    $editItem = $stmt->fetch();

}

if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM categories
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header("Location: departments.php");

    exit;

}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $name =
    trim($_POST['name']);

    $sort_order =
    (int)$_POST['sort_order'];

    if(isset($_POST['edit_id'])){

        $edit_id =
        (int)$_POST['edit_id'];

        $stmt = $pdo->prepare("
            UPDATE categories
            SET
            name=?,
            sort_order=?
            WHERE id=?
        ");

        $stmt->execute([

            $name,
            $sort_order,
            $edit_id

        ]);

        header("Location: departments.php");

        exit;

    }else{

        if($name){

            $stmt = $pdo->prepare("
                INSERT INTO categories
                (
                    name,
                    sort_order
                )
                VALUES
                (
                    ?,?
                )
            ");

            $stmt->execute([

                $name,
                $sort_order

            ]);

            $message =
            "دسته بندی ثبت شد";

        }

    }

}

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC,id ASC
")->fetchAll();

$back_url = 'index.php';

require '../includes/header.php';

?>

<div class="page-box">

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

    border-radius:22px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.category-item{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    margin-bottom:12px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

}

.category-name{

    font-size:15px;

    font-weight:bold;

}

.menu-wrapper{

    position:relative;

}

.menu-btn{

    cursor:pointer;

    font-size:22px;

    padding:5px 10px;

    border-radius:10px;

}

.menu-btn:hover{

    background:#e2e8f0;

}

.dropdown-menu{

    position:absolute;

    left:0;

    top:38px;

    background:white;

    border-radius:14px;

    box-shadow:0 0 20px rgba(0,0,0,0.08);

    display:none;

    overflow:hidden;

    z-index:9999;

    min-width:130px;

}

.dropdown-menu a{

    display:block;

    padding:12px;

    text-decoration:none;

    color:#333;

    font-size:14px;

}

.dropdown-menu a:hover{

    background:#f3f4f6;

}

.edit-badge{

    background:#f59e0b;

    color:white;

    padding:8px 14px;

    border-radius:12px;

    font-size:13px;

    margin-bottom:15px;

    display:inline-block;

}

.empty-box{

    text-align:center;

    color:#777;

    padding:25px;

}

</style>

<div class="page-box">

<div class="page-title">

📂 دسته بندی ها

</div>

<?php if($editMode): ?>

<div class="edit-badge">

✏️ حالت ویرایش فعال است

</div>

<?php endif; ?>

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<div class="card">

<form method="POST">

<input
type="text"
name="name"
class="form-control"
placeholder="نام دسته بندی"
required
value="<?= $editMode ? htmlspecialchars($editItem['name']) : '' ?>">

<input
type="number"
name="sort_order"
class="form-control"
placeholder="ترتیب نمایش"
value="<?= $editMode ? $editItem['sort_order'] : 0 ?>">

<?php if($editMode): ?>

<input
type="hidden"
name="edit_id"
value="<?= $editItem['id'] ?>">

<?php endif; ?>

<button
type="submit"
class="btn-custom">

<?= $editMode ? 'ذخیره ویرایش' : 'ثبت دسته بندی' ?>

</button>

</form>

</div>

<div class="card">

<?php if(count($categories)): ?>

<?php foreach($categories as $category): ?>

<div class="category-item">

<div class="category-name">

<?= htmlspecialchars($category['name']) ?>

</div>

<div class="menu-wrapper">

<div
class="menu-btn"
onclick="toggleMenu(event,<?= $category['id'] ?>)">

⋮

</div>

<div
class="dropdown-menu"
id="menu<?= $category['id'] ?>">

<a href="?edit=<?= $category['id'] ?>">

✏️ ویرایش

</a>

<a
href="?delete=<?= $category['id'] ?>"
onclick="return confirm('حذف شود؟')">

🗑 حذف

</a>

</div>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-box">

دسته بندی ثبت نشده

</div>

<?php endif; ?>

</div>

</div>

<script>

function closeAllMenus(){

    document
    .querySelectorAll('.dropdown-menu')
    .forEach(menu => {

        menu.style.display = 'none';

    });

}

function toggleMenu(event,id){

    event.stopPropagation();

    let menu =
    document.getElementById(
        'menu'+id
    );

    let isOpen =
    menu.style.display === 'block';

    closeAllMenus();

    if(!isOpen){

        menu.style.display = 'block';

    }

}

document.addEventListener(
    'click',
    function(){

        closeAllMenus();

    }
);

</script>

<?php include '../includes/footer.php'; ?>
