<?php

require '../includes/admin_auth.php';
require '../includes/category_helpers.php';

admin_require_super();

category_ensure_schema($pdo);

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

    if(category_has_children($pdo, $id)){
        $error = 'ابتدا زیرمجموعه‌های این دسته را حذف کنید';
    }else{

        $stmt = $pdo->prepare("
            DELETE FROM categories
            WHERE id=?
        ");

        $stmt->execute([$id]);
        header('Location: departments.php');
        exit;

    }

}

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $form_type = trim($_POST['form_type'] ?? 'main');
    $parent_id = null;

    if($form_type === 'sub' || $form_type === 'edit'){
        $parent_id = category_parent_id_value($_POST['parent_id'] ?? null);
    }

    if($name === ''){
        $error = 'نام دسته‌بندی الزامی است';
        $reopenModal = $form_type;
    }elseif($form_type === 'sub' && $parent_id === null){
        $error = 'دسته اصلی را انتخاب کنید';
        $reopenModal = 'sub';
    }else{

        $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

        if($parentError = category_validate_parent($pdo, $parent_id, $edit_id)){
            $error = $parentError;
            $reopenModal = $form_type;
        }else{

            if($edit_id){

                $stmt = $pdo->prepare("
                    UPDATE categories
                    SET
                    name=?,
                    parent_id=?,
                    sort_order=?
                    WHERE id=?
                ");

                $stmt->execute([
                    $name,
                    $parent_id,
                    $sort_order,
                    $edit_id,
                ]);

                header('Location: departments.php');
                exit;

            }

            $stmt = $pdo->prepare("
                INSERT INTO categories
                (name, parent_id, sort_order)
                VALUES
                (?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $parent_id,
                $sort_order,
            ]);

            $message = $parent_id
                ? 'زیرمجموعه ثبت شد'
                : 'دسته اصلی ثبت شد';

        }

    }

}

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$rootCategories = category_get_roots($categories);

$back_url = 'index.php';
$page_title = '📂 دسته بندی ها';
$page_header_menu_type = 'category';

require '../includes/header.php';

$modalDefaults = [
    'main' => ['name' => '', 'sort_order' => 0, 'parent_id' => ''],
    'sub' => ['name' => '', 'sort_order' => 0, 'parent_id' => ''],
    'edit' => [
        'name' => $editItem['name'] ?? '',
        'sort_order' => (int)($editItem['sort_order'] ?? 0),
        'parent_id' => (string)($editItem['parent_id'] ?? ''),
        'edit_id' => (int)($editItem['id'] ?? 0),
    ],
];

        if($reopenModal && isset($_POST['name'])){

    $modalDefaults[$reopenModal]['name'] = trim($_POST['name']);
    $modalDefaults[$reopenModal]['sort_order'] = (int)($_POST['sort_order'] ?? 0);
    $modalDefaults[$reopenModal]['parent_id'] = (string)($_POST['parent_id'] ?? '');

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

    max-width:920px;

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

.tree-card-title{

    font-size:18px;

    font-weight:800;

    color:#0f172a;

    margin-bottom:16px;

}

.category-tree{

    display:flex;

    flex-direction:column;

    gap:12px;

}

.category-tree-item.is-root{

    padding-bottom:4px;

}

.category-tree-node{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;

    flex-wrap:wrap;

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:14px 16px;

    margin-right:calc(var(--tree-depth, 0) * 22px);

}

.category-tree-children{

    display:flex;

    flex-direction:column;

    gap:10px;

    margin-top:10px;

    padding-right:18px;

    border-right:2px dashed #dbeafe;

}

.category-tree-label{

    display:flex;

    align-items:center;

    gap:8px;

    flex-wrap:wrap;

    min-width:0;

}

.category-tree-icon{

    font-size:18px;

    line-height:1;

}

.category-tree-name{

    font-size:15px;

    font-weight:800;

    color:#0f172a;

}

.category-tree-badge{

    display:inline-flex;

    align-items:center;

    padding:4px 10px;

    border-radius:999px;

    background:#e2e8f0;

    color:#475569;

    font-size:11px;

    font-weight:800;

}

.category-tree-badge.is-main{

    background:#dbeafe;

    color:#1d4ed8;

}

.category-tree-actions{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.category-tree-btn{

    text-decoration:none;

    padding:8px 12px;

    border-radius:12px;

    color:#fff;

    font-size:12px;

    font-weight:800;

    border:none;

    cursor:pointer;

    font-family:'Vazirmatn',sans-serif;

}

.category-tree-btn.edit{

    background:#2563eb;

}

.category-tree-btn.delete{

    background:#ef4444;

}

.empty-box{

    text-align:center;

    color:#64748b;

    padding:28px 16px;

    background:#f8fafc;

    border:1px dashed #dbe3ee;

    border-radius:18px;

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

.hidden{

    display:none !important;

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

<div class="tree-card-title">
نمودار درختی دسته‌بندی‌ها
</div>

<?php if(count($categories)): ?>

<div class="category-tree">

<?php category_render_tree($categories); ?>

</div>

<?php else: ?>

<div class="empty-box">
دسته‌بندی ثبت نشده است
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

<input type="hidden" name="form_type" id="categoryFormType" value="main">
<input type="hidden" name="edit_id" id="categoryEditId" value="">

<div id="categoryParentField" class="hidden">

<label class="field-label" for="categoryParentId">دسته اصلی</label>

<select name="parent_id" id="categoryParentId" class="form-control">

<option value="">انتخاب دسته اصلی</option>

<?php foreach($rootCategories as $root): ?>

<option value="<?= (int)$root['id'] ?>">
<?= htmlspecialchars($root['name'], ENT_QUOTES, 'UTF-8') ?>
</option>

<?php endforeach; ?>

</select>

</div>

<label class="field-label" for="categoryName">نام دسته‌بندی</label>

<input
type="text"
id="categoryName"
name="name"
class="form-control"
placeholder="مثلاً کامپیوتر و لپ‌تاپ"
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
ثبت
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

const categoryParentField =
document.getElementById('categoryParentField');

const categoryParentId =
document.getElementById('categoryParentId');

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

    const rootOption =
    categoryParentId.querySelector('option[data-root-option]');

    if(rootOption){
        rootOption.remove();
    }

    categoryFormType.value = type;
    categoryName.value = data.name || '';
    categorySortOrder.value = data.sort_order ?? 0;
    categoryEditId.value = data.edit_id || '';

    if(type === 'main'){
        categoryModalTitle.textContent = 'ثبت دسته بندی اصلی';
        categoryParentField.classList.add('hidden');
        categoryParentId.value = '';
        categoryParentId.removeAttribute('required');
        categoryModalSubmit.textContent = 'ثبت دسته اصلی';
    }else if(type === 'sub'){
        categoryModalTitle.textContent = 'ثبت دسته بندی';
        categoryParentField.classList.remove('hidden');
        categoryParentId.value = data.parent_id || '';
        categoryParentId.setAttribute('required', 'required');
        categoryModalSubmit.textContent = 'ثبت زیرمجموعه';
    }else{
        categoryModalTitle.textContent = 'ویرایش دسته بندی';
        categoryParentField.classList.remove('hidden');

        if(!categoryParentId.querySelector('option[data-root-option]')){
            const rootOption = document.createElement('option');
            rootOption.value = '';
            rootOption.textContent = 'دسته اصلی (بدون والد)';
            rootOption.setAttribute('data-root-option', '1');
            categoryParentId.insertBefore(
                rootOption,
                categoryParentId.firstChild
            );
        }

        categoryParentId.value = data.parent_id || '';
        categoryParentId.removeAttribute('required');
        categoryModalSubmit.textContent = 'ذخیره ویرایش';
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

<?php if($autoOpenModal): ?>

document.addEventListener('DOMContentLoaded', function(){

    openCategoryModal(
        <?= json_encode($autoOpenModal, JSON_UNESCAPED_UNICODE) ?>
    );

});

<?php endif; ?>

</script>

<?php include '../includes/footer.php'; ?>
