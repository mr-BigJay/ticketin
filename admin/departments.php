<?php

require '../includes/admin_auth.php';

admin_require_super();

$message = '';
$error = '';
$reopenModal = '';
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

    if(!$editItem){
        header('Location: departments.php');
        exit;
    }

}

if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM categories
        WHERE id=?
    ");

    $stmt->execute([$id]);
    header('Location: departments.php');
    exit;

}

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $form_type = trim($_POST['form_type'] ?? 'create');
    $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if($name === ''){
        $error = 'نام دسته‌بندی الزامی است';
        $reopenModal = $form_type;
    }else{

        if($edit_id){

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
                $edit_id,
            ]);

            header('Location: departments.php');
            exit;

        }

        $stmt = $pdo->prepare("
            INSERT INTO categories
            (name, sort_order)
            VALUES
            (?, ?)
        ");

        $stmt->execute([
            $name,
            $sort_order,
        ]);

        $message = 'دسته بندی ثبت شد';

    }

}

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$back_url = 'index.php';
$page_title = '📂 دسته بندی ها';
$page_header_menu_type = 'category';

require '../includes/header.php';

$modalDefaults = [
    'create' => [
        'name' => '',
        'sort_order' => 0,
    ],
    'edit' => [
        'name' => $editItem['name'] ?? '',
        'sort_order' => (int)($editItem['sort_order'] ?? 0),
        'edit_id' => (int)($editItem['id'] ?? 0),
    ],
];

if($reopenModal && isset($_POST['name'])){

    $modalDefaults[$reopenModal]['name'] = trim($_POST['name']);
    $modalDefaults[$reopenModal]['sort_order'] = (int)($_POST['sort_order'] ?? 0);

    if($reopenModal === 'edit'){
        $modalDefaults['edit']['edit_id'] = (int)($_POST['edit_id'] ?? 0);
    }

}

$autoOpenModal = $editMode
    ? 'edit'
    : $reopenModal;

?>

<style>

.page-box{

    max-width:850px;

    margin:auto;

}

.card{

    background:white;

    border-radius:24px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 10px 30px rgba(15,23,42,.05);

    border:1px solid #eef2f7;

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

    font-weight:800;

    color:#0f172a;

}

.menu-wrapper{

    position:relative;

}

.menu-btn{

    cursor:pointer;

    font-size:22px;

    padding:5px 10px;

    border-radius:10px;

    border:none;

    background:transparent;

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

    box-shadow:0 12px 30px rgba(15,23,42,.12);

    display:none;

    overflow:hidden;

    z-index:9999;

    min-width:130px;

    border:1px solid #e2e8f0;

}

.dropdown-menu a{

    display:block;

    padding:12px 14px;

    text-decoration:none;

    color:#333;

    font-size:14px;

    font-weight:700;

}

.dropdown-menu a:hover{

    background:#f3f4f6;

}

.empty-box{

    text-align:center;

    color:#64748b;

    padding:28px 16px;

}

.category-modal-overlay{

    position:fixed;

    inset:0;

    background:rgba(15,23,42,.45);

    backdrop-filter:blur(8px);

    z-index:100000;

    display:none;

    align-items:center;

    justify-content:center;

    padding:20px;

}

.category-modal-overlay.show{

    display:flex;

}

.category-modal{

    width:100%;

    max-width:460px;

    background:#ffffff;

    border-radius:24px;

    padding:24px 22px;

    box-shadow:0 20px 50px rgba(15,23,42,.18);

    position:relative;

}

.category-modal-title{

    font-size:20px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:18px;

    padding-left:36px;

}

.category-modal-close{

    position:absolute;

    left:16px;

    top:16px;

    width:34px;

    height:34px;

    border:none;

    border-radius:12px;

    background:#f1f5f9;

    color:#64748b;

    font-size:22px;

    line-height:1;

    cursor:pointer;

}

.field-label{

    display:block;

    font-size:13px;

    font-weight:800;

    color:#334155;

    margin-bottom:8px;

}

</style>

<div class="page-box">

<?php if($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if($error && !$reopenModal): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">

<?php if(count($categories)): ?>

<?php foreach($categories as $category): ?>

<div class="category-item">

<div class="category-name">
<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="menu-wrapper">

<button
type="button"
class="menu-btn"
onclick="toggleMenu(event,<?= (int)$category['id'] ?>)"
aria-label="عملیات">

⋮

</button>

<div
class="dropdown-menu"
id="menu<?= (int)$category['id'] ?>">

<a href="?edit=<?= (int)$category['id'] ?>">

✏️ ویرایش

</a>

<a
href="?delete=<?= (int)$category['id'] ?>"
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

<div
class="category-modal-overlay"
id="categoryModalOverlay"
aria-hidden="true">

<div class="category-modal" role="dialog" aria-modal="true">

<button
type="button"
class="category-modal-close"
onclick="closeCategoryModal()"
aria-label="بستن">

×

</button>

<h2 class="category-modal-title" id="categoryModalTitle"></h2>

<form method="POST" id="categoryModalForm">

<input type="hidden" name="form_type" id="categoryFormType" value="create">
<input type="hidden" name="edit_id" id="categoryEditId" value="">

<label class="field-label" for="categoryName">نام دسته‌بندی</label>

<input
type="text"
id="categoryName"
name="name"
class="form-control"
placeholder="نام دسته بندی"
required>

<label class="field-label" for="categorySortOrder">ترتیب نمایش</label>

<input
type="number"
id="categorySortOrder"
name="sort_order"
class="form-control"
value="0">

<div
class="alert alert-danger"
id="categoryModalError"
style="display:none;margin-top:12px;margin-bottom:0;"></div>

<button type="submit" class="btn-custom" id="categoryModalSubmit">
ثبت دسته بندی
</button>

</form>

</div>

</div>

<script>

const categoryModalOverlay =
document.getElementById('categoryModalOverlay');

const categoryModalTitle =
document.getElementById('categoryModalTitle');

const categoryFormType =
document.getElementById('categoryFormType');

const categoryEditId =
document.getElementById('categoryEditId');

const categoryName =
document.getElementById('categoryName');

const categorySortOrder =
document.getElementById('categorySortOrder');

const categoryModalSubmit =
document.getElementById('categoryModalSubmit');

const categoryModalError =
document.getElementById('categoryModalError');

const categoryModalDefaults = <?= json_encode(
    $modalDefaults,
    JSON_UNESCAPED_UNICODE
) ?>;

const categoryModalErrorText = <?= json_encode(
    $reopenModal ? $error : '',
    JSON_UNESCAPED_UNICODE
) ?>;

function closeCategoryModal(){

    categoryModalOverlay.classList.remove('show');
    categoryModalOverlay.setAttribute('aria-hidden', 'true');
    categoryModalError.style.display = 'none';
    categoryModalError.textContent = '';

    const dropdown =
    document.getElementById('pageHeaderDropdown');

    const menuBtn =
    document.getElementById('pageHeaderMenuBtn');

    if(dropdown){
        dropdown.classList.remove('show');
    }

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }

}

function openCategoryModal(type, defaults){

    const data = defaults || categoryModalDefaults[type] || {};

    categoryFormType.value = type;
    categoryName.value = data.name || '';
    categorySortOrder.value = data.sort_order ?? 0;
    categoryEditId.value = data.edit_id || '';

    if(type === 'edit'){
        categoryModalTitle.textContent = 'ویرایش دسته بندی';
        categoryModalSubmit.textContent = 'ذخیره ویرایش';
    }else{
        categoryModalTitle.textContent = 'ثبت دسته بندی';
        categoryModalSubmit.textContent = 'ثبت دسته بندی';
    }

    if(categoryModalErrorText){
        categoryModalError.textContent = categoryModalErrorText;
        categoryModalError.style.display = 'block';
    }else{
        categoryModalError.style.display = 'none';
        categoryModalError.textContent = '';
    }

    categoryModalOverlay.classList.add('show');
    categoryModalOverlay.setAttribute('aria-hidden', 'false');
    categoryName.focus();

}

categoryModalOverlay.addEventListener('click', function(event){

    if(event.target === categoryModalOverlay){
        closeCategoryModal();
    }

});

document.addEventListener('keydown', function(event){

    if(
        event.key === 'Escape' &&
        categoryModalOverlay.classList.contains('show')
    ){
        closeCategoryModal();
    }

});

function closeAllMenus(){

    document
    .querySelectorAll('.dropdown-menu')
    .forEach(function(menu){
        menu.style.display = 'none';
    });

}

function toggleMenu(event,id){

    event.stopPropagation();

    const menu =
    document.getElementById('menu' + id);

    const isOpen =
    menu.style.display === 'block';

    closeAllMenus();

    if(!isOpen){
        menu.style.display = 'block';
    }

}

document.addEventListener('click', function(){
    closeAllMenus();
});

<?php if($autoOpenModal): ?>

document.addEventListener('DOMContentLoaded', function(){

    openCategoryModal(
        <?= json_encode($autoOpenModal, JSON_UNESCAPED_UNICODE) ?>
    );

});

<?php endif; ?>

</script>

<?php include '../includes/footer.php'; ?>
