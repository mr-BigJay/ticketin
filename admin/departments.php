<?php

require '../includes/admin_auth.php';
require '../includes/category_helpers.php';

admin_require_super();

category_ensure_schema($pdo);

$message = '';
$error = '';

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
    $parent_id = category_parent_id_value($_POST['parent_id'] ?? null);

    if($name === ''){
        $error = 'نام دسته‌بندی الزامی است';
    }else{

        $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

        if($parentError = category_validate_parent($pdo, $parent_id, $edit_id)){
            $error = $parentError;
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

require '../includes/header.php';

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

.edit-badge{

    background:#fff7ed;

    color:#9a3412;

    border:1px solid #fed7aa;

    padding:10px 14px;

    border-radius:14px;

    font-size:13px;

    font-weight:700;

    margin-bottom:16px;

    display:inline-block;

}

.field-label{

    display:block;

    font-size:13px;

    font-weight:800;

    color:#334155;

    margin-bottom:8px;

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

    position:relative;

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

</style>

<div class="page-box">

<?php if($editMode): ?>

<div class="edit-badge">
✏️ حالت ویرایش فعال است
</div>

<?php endif; ?>

<?php if($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">

<form method="POST">

<label class="field-label" for="categoryName">نام دسته‌بندی</label>

<input
type="text"
id="categoryName"
name="name"
class="form-control"
placeholder="مثلاً کامپیوتر و لپ‌تاپ"
required
value="<?= $editMode ? htmlspecialchars($editItem['name'], ENT_QUOTES, 'UTF-8') : '' ?>">

<label class="field-label" for="parentId">دسته اصلی</label>

<select name="parent_id" id="parentId" class="form-control">

<option value="">
دسته اصلی (بدون والد)
</option>

<?php foreach($rootCategories as $root): ?>

<?php if($editMode && (int)$root['id'] === (int)$editItem['id']){
    continue;
} ?>

<option
value="<?= (int)$root['id'] ?>"
<?= $editMode && (int)($editItem['parent_id'] ?? 0) === (int)$root['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($root['name'], ENT_QUOTES, 'UTF-8') ?>

</option>

<?php endforeach; ?>

</select>

<label class="field-label" for="sortOrder">ترتیب نمایش</label>

<input
type="number"
id="sortOrder"
name="sort_order"
class="form-control"
value="<?= $editMode ? (int)$editItem['sort_order'] : 0 ?>">

<?php if($editMode): ?>

<input type="hidden" name="edit_id" value="<?= (int)$editItem['id'] ?>">

<?php endif; ?>

<button type="submit" class="btn-custom">
<?= $editMode ? 'ذخیره ویرایش' : 'ثبت دسته‌بندی' ?>
</button>

</form>

</div>

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

<?php include '../includes/footer.php'; ?>
