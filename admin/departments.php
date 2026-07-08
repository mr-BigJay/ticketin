<?php

require '../includes/auth.php';
require '../includes/db.php';

if(($_SESSION['role'] ?? '') !== 'admin'){
    die('دسترسی غیر مجاز');
}

function category_ensure_schema(PDO $pdo): void
{
    static $done = false;

    if($done){
        return;
    }

    $done = true;

    try{
        $pdo->exec("
            ALTER TABLE categories
            ADD COLUMN parent_id INT NULL DEFAULT NULL
        ");
    }catch(PDOException $e){
    }

    try{
        $pdo->exec("
            ALTER TABLE categories
            ADD KEY idx_categories_parent (parent_id)
        ");
    }catch(PDOException $e){
    }
}

function category_normalize_parent_id($value): ?int
{
    if($value === null || $value === ''){
        return null;
    }

    $id = (int)$value;

    return $id > 0 ? $id : null;
}

function category_children_map(array $categories): array
{
    $map = [];

    foreach($categories as $category){
        $parentId = category_normalize_parent_id($category['parent_id'] ?? null);
        $key = $parentId ?? 0;
        $map[$key][] = $category;
    }

    return $map;
}

function category_collect_descendant_ids(array $categories, int $rootId): array
{
    $childrenMap = category_children_map($categories);
    $ids = [];
    $stack = [$rootId];

    while($stack){
        $current = array_pop($stack);

        foreach($childrenMap[$current] ?? [] as $child){
            $childId = (int)$child['id'];
            $ids[] = $childId;
            $stack[] = $childId;
        }
    }

    return $ids;
}

function category_parent_options(
    array $categories,
    ?int $selectedId = null,
    ?int $excludeId = null
): array
{
    $childrenMap = category_children_map($categories);
    $excluded = [];

    if($excludeId){
        $excluded[$excludeId] = true;

        foreach(category_collect_descendant_ids($categories, $excludeId) as $descendantId){
            $excluded[$descendantId] = true;
        }
    }

    $options = [];
    $walk = static function(int $parentKey, int $depth) use (
        &$walk,
        $childrenMap,
        &$options,
        $excluded,
        $selectedId
    ): void {
        foreach($childrenMap[$parentKey] ?? [] as $category){
            $id = (int)$category['id'];

            if(isset($excluded[$id])){
                continue;
            }

            $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
            $options[] = [
                'id' => $id,
                'label' => $prefix . $category['name'],
                'selected' => $selectedId === $id,
            ];

            $walk($id, $depth + 1);
        }
    };

    $walk(0, 0);

    return $options;
}

function category_render_tree(array $childrenMap, int $parentId = 0, int $depth = 0): void
{
    foreach($childrenMap[$parentId] ?? [] as $category){
        $id = (int)$category['id'];
        $hasChildren = !empty($childrenMap[$id]);
        $isRoot = $depth === 0;
        ?>
        <div class="tree-node<?= $isRoot ? ' tree-node-root' : ' tree-node-child' ?>">

            <div class="tree-row">

                <?php if($hasChildren): ?>

                <button
                type="button"
                class="tree-toggle"
                data-target="tree-children-<?= $id ?>"
                aria-expanded="false"
                aria-label="نمایش زیرمجموعه">

                +

                </button>

                <?php else: ?>

                <span class="tree-toggle-spacer" aria-hidden="true"></span>

                <?php endif; ?>

                <div class="tree-label">

                    <span class="tree-icon" aria-hidden="true"><?= $isRoot ? '📁' : '📄' ?></span>

                    <span class="tree-name"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></span>

                </div>

                <div class="menu-wrapper">

                    <button
                    type="button"
                    class="menu-btn"
                    onclick="toggleMenu(event, <?= $id ?>)"
                    aria-label="عملیات دسته‌بندی">

                    ⋮

                    </button>

                    <div class="dropdown-menu" id="menu<?= $id ?>">

                        <a href="?edit=<?= $id ?>">✏️ ویرایش</a>

                        <a
                        href="?delete=<?= $id ?>"
                        onclick="return confirm('<?= $hasChildren ? 'این دسته و زیرمجموعه‌هایش حذف شوند؟' : 'حذف شود؟' ?>')">

                        🗑 حذف

                        </a>

                    </div>

                </div>

            </div>

            <?php if($hasChildren): ?>

            <div class="tree-children is-collapsed" id="tree-children-<?= $id ?>">

                <?php category_render_tree($childrenMap, $id, $depth + 1); ?>

            </div>

            <?php endif; ?>

        </div>
        <?php
    }
}

category_ensure_schema($pdo);

$message = '';
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

    $allCategories = $pdo->query("
        SELECT id
        FROM categories
    ")->fetchAll(PDO::FETCH_ASSOC);

    $deleteIds = array_merge([$id], category_collect_descendant_ids($allCategories, $id));
    $deleteIds = array_values(array_unique(array_map('intval', $deleteIds)));

    if($deleteIds){
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));

        $stmt = $pdo->prepare("
            DELETE FROM categories
            WHERE id IN ($placeholders)
        ");

        $stmt->execute($deleteIds);
    }

    header('Location: departments.php');
    exit;

}

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $parent_id = category_normalize_parent_id($_POST['parent_id'] ?? null);
    $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if($name === ''){
        $message = 'نام دسته‌بندی الزامی است';
    }elseif($edit_id && $parent_id === $edit_id){
        $message = 'دسته‌بندی نمی‌تواند والد خودش باشد';
    }else{

        if($edit_id){
            $allCategories = $pdo->query("
                SELECT id, parent_id
                FROM categories
            ")->fetchAll(PDO::FETCH_ASSOC);

            $invalidParents = array_merge([$edit_id], category_collect_descendant_ids($allCategories, $edit_id));

            if($parent_id !== null && in_array($parent_id, $invalidParents, true)){
                $message = 'انتخاب این دسته به عنوان والد مجاز نیست';
            }else{
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

        }else{
            $stmt = $pdo->prepare("
                INSERT INTO categories
                (
                    name,
                    parent_id,
                    sort_order
                )
                VALUES
                (
                    ?,?,?
                )
            ");

            $stmt->execute([
                $name,
                $parent_id,
                $sort_order,
            ]);

            $message = 'دسته بندی ثبت شد';
        }

    }

}

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$childrenMap = category_children_map($categories);
$parentOptions = category_parent_options(
    $categories,
    category_normalize_parent_id($editItem['parent_id'] ?? null),
    $editMode ? (int)$editItem['id'] : null
);

$back_url = 'index.php';
$page_title = '📂 دسته بندی ها';

require '../includes/header.php';

?>

<style>

.page-box{
    max-width:900px;
    margin:auto;
}

.card{
    background:white;
    border-radius:24px;
    padding:22px;
    margin-bottom:20px;
    box-shadow:0 10px 30px rgba(15,23,42,.05);
    border:1px solid #eef2f7;
    overflow:visible;
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
    color:#64748b;
    padding:25px;
}

.tree-node{
    margin-bottom:10px;
}

.tree-node-child .tree-row{
    background:#ffffff;
    border:1px solid #e2e8f0;
}

.tree-row{
    background:#f8fafc;
    border-radius:18px;
    padding:14px 16px;
    display:flex;
    align-items:center;
    gap:12px;
}

.tree-toggle,
.tree-toggle-spacer{
    width:34px;
    height:34px;
    flex-shrink:0;
}

.tree-toggle{
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
    font-size:22px;
    line-height:1;
    font-weight:700;
    cursor:pointer;
    transition:.2s;
}

.tree-toggle:hover{
    transform:translateY(-1px);
}

.tree-toggle.is-open{
    background:#0f172a;
}

.tree-label{
    flex:1;
    min-width:0;
    display:flex;
    align-items:center;
    gap:8px;
}

.tree-name{
    font-size:15px;
    font-weight:800;
    color:#0f172a;
    line-height:1.6;
    word-break:break-word;
}

.tree-icon{
    font-size:16px;
    line-height:1;
}

.tree-children{
    margin-top:10px;
    margin-right:22px;
    padding-right:14px;
    border-right:2px dashed #cbd5e1;
}

.tree-children.is-collapsed{
    display:none;
}

.menu-wrapper{
    position:relative;
    flex-shrink:0;
}

.menu-btn{
    cursor:pointer;
    font-size:22px;
    padding:5px 10px;
    border:none;
    border-radius:10px;
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
    box-shadow:0 12px 35px rgba(15,23,42,.15);
    display:none;
    overflow:hidden;
    z-index:9999;
    min-width:140px;
    border:1px solid #eef2f7;
}

.dropdown-menu a{
    display:block;
    padding:12px 14px;
    text-decoration:none;
    color:#0f172a;
    font-size:14px;
    font-weight:700;
}

.dropdown-menu a:hover{
    background:#f8fafc;
}

@media(max-width:768px){

    .tree-row{
        align-items:flex-start;
    }

    .tree-children{
        margin-right:14px;
        padding-right:10px;
    }

}

</style>

<div class="page-box">

<?php if($editMode): ?>

<div class="edit-badge">✏️ حالت ویرایش فعال است</div>

<?php endif; ?>

<?php if($message): ?>

<div class="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>

<?php endif; ?>

<div class="card">

<form method="POST">

<input
type="text"
name="name"
class="form-control"
placeholder="نام دسته بندی"
required
value="<?= $editMode ? htmlspecialchars($editItem['name'], ENT_QUOTES, 'UTF-8') : '' ?>">

<select name="parent_id" class="form-control">

<option value="">دسته‌بندی اصلی (بدون والد)</option>

<?php foreach($parentOptions as $option): ?>

<option
value="<?= (int)$option['id'] ?>"
<?= !empty($option['selected']) ? 'selected' : '' ?>>

<?= htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8') ?>

</option>

<?php endforeach; ?>

</select>

<input
type="number"
name="sort_order"
class="form-control"
placeholder="ترتیب نمایش"
value="<?= $editMode ? (int)$editItem['sort_order'] : 0 ?>">

<?php if($editMode): ?>

<input type="hidden" name="edit_id" value="<?= (int)$editItem['id'] ?>">

<?php endif; ?>

<button type="submit" class="btn-custom">

<?= $editMode ? 'ذخیره ویرایش' : 'ثبت دسته بندی' ?>

</button>

</form>

</div>

<div class="card">

<?php if(count($categories)): ?>

<?php category_render_tree($childrenMap); ?>

<?php else: ?>

<div class="empty-box">دسته بندی ثبت نشده</div>

<?php endif; ?>

</div>

</div>

<script>

function closeAllMenus(){
    document.querySelectorAll('.dropdown-menu').forEach(function(menu){
        menu.style.display = 'none';
    });
}

function toggleMenu(event, id){
    event.stopPropagation();

    const menu = document.getElementById('menu' + id);
    const isOpen = menu.style.display === 'block';

    closeAllMenus();

    if(!isOpen){
        menu.style.display = 'block';
    }
}

document.addEventListener('click', function(){
    closeAllMenus();
});

document.querySelectorAll('.tree-toggle').forEach(function(button){
    button.addEventListener('click', function(){
        const target = document.getElementById(button.dataset.target);

        if(!target){
            return;
        }

        const isOpen = !target.classList.contains('is-collapsed');

        if(isOpen){
            target.classList.add('is-collapsed');
            button.classList.remove('is-open');
            button.textContent = '+';
            button.setAttribute('aria-expanded', 'false');
        }else{
            target.classList.remove('is-collapsed');
            button.classList.add('is-open');
            button.textContent = '−';
            button.setAttribute('aria-expanded', 'true');
        }
    });
});

</script>

<?php include '../includes/footer.php'; ?>
