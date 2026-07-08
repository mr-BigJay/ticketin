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

function category_child_label(int $depth, bool $hasChildren): string
{
    if($depth === 0){
        return 'دسته اصلی';
    }

    return $hasChildren ? 'دارای زیرمجموعه' : 'زیرمجموعه';
}

function category_render_inline_add(int $parentId, string $key = ''): void
{
    if($key === ''){
        $key = (string)$parentId;
    }
    ?>
<div class="inline-add-row" id="add-row-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">

<button
type="button"
class="inline-add-btn"
onclick="showCategoryInlineAdd('<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>', <?= $parentId ?>)"
title="افزودن">

+

</button>

</div>

<div
class="inline-add-form hidden"
id="add-form-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">

<input
type="text"
class="form-control inline-add-input"
id="add-input-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
placeholder="نام را وارد کنید"
onkeydown="categoryInlineAddKeydown(event, '<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>', <?= $parentId ?>)">

<button
type="button"
class="inline-add-action inline-add-save"
onclick="confirmCategoryInlineAdd('<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>', <?= $parentId ?>)"
title="تایید">

✓

</button>

<button
type="button"
class="inline-add-action inline-add-cancel"
onclick="cancelCategoryInlineAdd('<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>')"
title="انصراف">

✕

</button>

</div>
    <?php
}

function category_render_tree(array $childrenMap, int $parentId = 0, int $depth = 0): void
{
    foreach($childrenMap[$parentId] ?? [] as $category){
        $id = (int)$category['id'];
        $hasChildren = !empty($childrenMap[$id]);
        $isRoot = $depth === 0;
        $name = htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8');
        $jsonName = json_encode($category['name'], JSON_UNESCAPED_UNICODE);
        $parentIdValue = json_encode((string)($category['parent_id'] ?? ''), JSON_UNESCAPED_UNICODE);
        $deleteConfirm = $hasChildren
            ? 'این دسته و زیرمجموعه‌هایش حذف شوند؟'
            : 'حذف شود؟';

        if(!$hasChildren && $depth > 0){
            ?>
            <div class="item" id="category-item-<?= $id ?>">

                <div
                class="item-name editable-item"
                data-id="<?= $id ?>"
                data-name="<?= $name ?>"
                title="دابل‌کلیک برای ویرایش">

                    <span class="tree-prefix">├──</span>

                    <span class="item-label"><?= $name ?></span>

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

                        <a
                        href="?delete=<?= $id ?>"
                        onclick="return confirm('<?= $deleteConfirm ?>')">

                        🗑 حذف

                        </a>

                    </div>

                </div>

            </div>
            <?php
            continue;
        }

        $boxClass = $isRoot ? 'center-box' : 'section-box';
        $headerClass = $isRoot ? 'center-header' : 'section-header';
        ?>
        <div class="<?= $boxClass ?>" id="category-node-<?= $id ?>">

            <div class="<?= $headerClass ?>">

                <?php if($hasChildren): ?>

                <button
                type="button"
                class="toggle"
                onclick="toggleCategoryNode(<?= $id ?>)"
                aria-label="نمایش زیرمجموعه"
                aria-expanded="false">

                <span id="category-icon-<?= $id ?>">+</span>

                </button>

                <?php else: ?>

                <span class="toggle-spacer" aria-hidden="true"></span>

                <?php endif; ?>

                <?php if($isRoot): ?>

                <div class="center-info">

                    <div
                    class="center-title editable-item"
                    data-id="<?= $id ?>"
                    data-name="<?= $name ?>"
                    title="دابل‌کلیک برای ویرایش">

                    📁 <span class="item-label"><?= $name ?></span>

                    </div>

                    <div class="center-type">

                    <?= category_child_label($depth, $hasChildren) ?>

                    </div>

                </div>

                <?php else: ?>

                <div
                class="section-title-text editable-item"
                data-id="<?= $id ?>"
                data-name="<?= $name ?>"
                title="دابل‌کلیک برای ویرایش">

                📄 <span class="item-label"><?= $name ?></span>

                </div>

                <?php endif; ?>

                <div class="menu-wrapper">

                    <button
                    type="button"
                    class="menu-btn"
                    onclick="toggleMenu(event, <?= $id ?>)"
                    aria-label="عملیات دسته‌بندی">

                    ⋮

                    </button>

                    <div class="dropdown-menu" id="menu<?= $id ?>">

                        <?php if($hasChildren || $isRoot): ?>

                        <button
                        type="button"
                        onclick="openCategorySubAdd(<?= $id ?>)">

                        ➕ افزودن زیرمجموعه

                        </button>

                        <?php endif; ?>

                        <a
                        href="?delete=<?= $id ?>"
                        onclick="return confirm('<?= $deleteConfirm ?>')">

                        🗑 حذف

                        </a>

                    </div>

                </div>

            </div>

            <?php if($hasChildren): ?>

            <div
            class="category-content"
            id="category-content-<?= $id ?>">

            <?php category_render_tree($childrenMap, $id, $depth + 1); ?>

            <?php category_render_inline_add($id); ?>

            </div>

            <?php endif; ?>

        </div>
        <?php
    }
}

category_ensure_schema($pdo);

$message = '';

if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    !empty($_POST['inline_rename'])
){
    header('Content-Type: application/json; charset=utf-8');

    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if(!$id || $name === ''){
        echo json_encode(['ok' => false, 'error' => 'اطلاعات نامعتبر']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);

    echo json_encode(['ok' => true, 'name' => $name]);
    exit;
}

if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    !empty($_POST['inline_add'])
){
    header('Content-Type: application/json; charset=utf-8');

    $name = trim($_POST['name'] ?? '');
    $parentRaw = $_POST['parent_id'] ?? null;
    $parent_id = ($parentRaw === '' || $parentRaw === '0' || $parentRaw === null)
        ? null
        : category_normalize_parent_id($parentRaw);

    if($name === ''){
        echo json_encode(['ok' => false, 'error' => 'نام دسته‌بندی الزامی است']);
        exit;
    }

    if($parent_id){
        $parentCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $parentCheck->execute([$parent_id]);

        if(!$parentCheck->fetch()){
            echo json_encode(['ok' => false, 'error' => 'دسته والد معتبر نیست']);
            exit;
        }
    }

    $sortStmt = $pdo->prepare("
        SELECT COALESCE(MAX(sort_order), 0) + 1
        FROM categories
        WHERE " . ($parent_id === null ? 'parent_id IS NULL' : 'parent_id = ?')
    );

    if($parent_id === null){
        $sortStmt->execute();
    }else{
        $sortStmt->execute([$parent_id]);
    }

    $sort_order = (int)$sortStmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO categories (name, parent_id, sort_order)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$name, $parent_id, $sort_order]);

    echo json_encode([
        'ok' => true,
        'id' => (int)$pdo->lastInsertId(),
        'name' => $name,
        'parent_id' => $parent_id,
    ]);
    exit;
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

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

$childrenMap = category_children_map($categories);

$back_url = 'index.php';
$page_title = '📂 دسته بندی ها';
$page_header_menu_type = 'category';

$rootCategories = $childrenMap[0] ?? [];

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
    display:none;
}

.hidden{
    display:none !important;
}

.empty-box{
    text-align:center;
    color:#64748b;
    padding:25px;
}

.center-box{
    background:#f8fafc;
    border-radius:24px;
    margin-bottom:16px;
    overflow:visible;
    border:1px solid #e2e8f0;
    position:relative;
    z-index:1;
    transition:.2s;
}

.center-box.menu-open,
.section-box.menu-open,
.item.menu-open{
    z-index:200;
}

.center-box:hover{
    box-shadow:0 10px 30px rgba(15,23,42,.05);
}

.center-header{
    padding:16px 18px;
    display:flex;
    align-items:center;
    gap:12px;
    position:relative;
    overflow:visible;
}

.center-info{
    flex:1;
    min-width:0;
}

.center-title{
    font-size:16px;
    font-weight:800;
    color:#0f172a;
    line-height:1.6;
    word-break:break-word;
}

.center-type{
    margin-top:6px;
    font-size:12px;
    color:#0284c7;
    font-weight:700;
}

.category-content{
    display:none;
    padding:0 18px 18px;
}

.section-box{
    margin-top:12px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    overflow:visible;
    background:white;
    position:relative;
    z-index:1;
}

.section-header{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 14px;
    background:#f8fafc;
}

.section-title-text{
    flex:1;
    min-width:0;
    font-size:13px;
    font-weight:800;
    color:#475569;
}

.item{
    background:white;
    border-radius:18px;
    padding:12px 15px;
    margin-bottom:10px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    border:1px solid #eef2f7;
    transition:.2s;
    position:relative;
}

.item:hover{
    border-color:#bae6fd;
    background:#fafdff;
}

.item-name{
    font-size:14px;
    font-weight:600;
    color:#334155;
    display:flex;
    align-items:center;
    gap:8px;
    flex:1;
    min-width:0;
}

.tree-prefix{
    flex-shrink:0;
    color:#94a3b8;
}

.item-label{
    word-break:break-word;
}

.editable-item{
    cursor:text;
    user-select:none;
}

.editable-item.editing{
    display:flex;
    align-items:center;
    gap:8px;
    flex:1;
    min-width:0;
}

.toggle,
.toggle-spacer{
    width:36px;
    height:36px;
    flex-shrink:0;
}

.toggle{
    font-size:22px;
    color:#0284c7;
    font-weight:bold;
    cursor:pointer;
    border:none;
    background:transparent;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:12px;
    transition:.2s;
    user-select:none;
    padding:0;
}

.toggle:hover{
    background:#dbeafe;
}

.menu-wrapper{
    position:relative;
    z-index:20;
    flex-shrink:0;
    margin-inline-start:auto;
}

.menu-btn{
    cursor:pointer;
    width:38px;
    height:38px;
    border:none;
    border-radius:12px;
    background:transparent;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    transition:.2s;
    padding:0;
}

.menu-btn:hover{
    background:#dbeafe;
}

.dropdown-menu{
    position:absolute;
    left:0;
    top:44px;
    background:white;
    border-radius:18px;
    box-shadow:0 15px 40px rgba(15,23,42,.14);
    min-width:170px;
    border:1px solid #eef2f7;
    z-index:100;
    display:none;
    overflow:hidden;
}

.dropdown-menu.show{
    display:block;
}

.dropdown-menu.drop-down{
    top:auto;
    bottom:calc(100% + 8px);
}

.dropdown-menu a,
.dropdown-menu button{
    display:flex;
    align-items:center;
    gap:10px;
    width:100%;
    padding:13px 15px;
    text-decoration:none;
    color:#334155;
    font-size:14px;
    font-weight:700;
    border:none;
    background:none;
    text-align:right;
    cursor:pointer;
    font-family:'Vazirmatn',sans-serif;
}

.dropdown-menu a:hover,
.dropdown-menu button:hover{
    background:#f8fafc;
}

.inline-add-row{
    display:flex;
    justify-content:center;
    padding:6px 0 2px;
}

.inline-add-btn{
    width:34px;
    height:34px;
    border:1px dashed #0284c7;
    background:#eff6ff;
    color:#0284c7;
    border-radius:10px;
    font-size:20px;
    font-weight:700;
    line-height:1;
    cursor:pointer;
    font-family:'Vazirmatn',sans-serif;
}

.inline-add-btn:hover{
    background:#dbeafe;
}

.inline-add-form{
    display:flex;
    gap:8px;
    align-items:center;
    margin-top:8px;
}

#add-form-root{
    flex-direction:column;
    align-items:stretch;
}

#add-form-root .inline-add-input{
    width:100%;
}

.inline-add-input{
    flex:1;
    margin:0 !important;
}

.inline-add-action{
    width:38px;
    height:38px;
    border:none;
    border-radius:12px;
    font-size:18px;
    line-height:1;
    cursor:pointer;
    flex-shrink:0;
}

.inline-add-save{
    background:#dcfce7;
    color:#166534;
}

.inline-add-cancel{
    background:#fee2e2;
    color:#991b1b;
}

.inline-edit-input{
    flex:1;
    margin:0 !important;
}

.root-add-parent{
    margin-bottom:8px;
}

@media(max-width:768px){

    .center-header,
    .section-header{
        align-items:flex-start;
    }

    .category-content{
        padding:0 14px 14px;
    }

}

</style>

<div class="page-box">

<?php if($message): ?>

<div class="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>

<?php endif; ?>

<div class="card">

<?php if(count($categories)): ?>

<?php category_render_tree($childrenMap); ?>

<?php else: ?>

<div class="empty-box">دسته بندی ثبت نشده</div>

<?php endif; ?>

<div class="inline-add-row" id="add-row-root">

<button
type="button"
class="inline-add-btn"
onclick="showCategoryInlineAdd('root', 0)"
title="افزودن">

+

</button>

</div>

<div class="inline-add-form hidden" id="add-form-root">

<div class="root-add-parent hidden" id="root-add-parent-wrap">

<select id="root-add-parent" class="form-control">

<option value="">انتخاب دسته اصلی</option>

<?php foreach($rootCategories as $rootCategory): ?>

<option value="<?= (int)$rootCategory['id'] ?>">
<?= htmlspecialchars($rootCategory['name'], ENT_QUOTES, 'UTF-8') ?>
</option>

<?php endforeach; ?>

</select>

</div>

<input
type="text"
class="form-control inline-add-input"
id="add-input-root"
placeholder="نام را وارد کنید"
onkeydown="categoryInlineAddKeydown(event, 'root', 0)">

<button
type="button"
class="inline-add-action inline-add-save"
onclick="confirmCategoryInlineAdd('root', 0)"
title="تایید">

✓

</button>

<button
type="button"
class="inline-add-action inline-add-cancel"
onclick="cancelCategoryInlineAdd('root')"
title="انصراف">

✕

</button>

</div>

</div>

</div>

<script>

let inlineEditBusy = false;
let lastEditableTap = { id: null, time: 0 };
let rootAddMode = 'main';

function closePageHeaderDropdown(){

    const dropdown = document.getElementById('pageHeaderDropdown');
    const menuBtn = document.getElementById('pageHeaderMenuBtn');

    if(dropdown){
        dropdown.classList.remove('show');
    }

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }

}

function escapeHtml(text){

    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

}

function showCategoryInlineAdd(key, parentId){

    if(parentId > 0){
        const content = document.getElementById('category-content-' + parentId);
        const icon = document.getElementById('category-icon-' + parentId);
        const toggle = document.querySelector('#category-node-' + parentId + ' .toggle');

        if(content && content.style.display !== 'block'){
            content.style.display = 'block';

            if(icon){
                icon.textContent = '−';
            }

            if(toggle){
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }

    closeAllMenus();
    closePageHeaderDropdown();

    document.querySelectorAll('.inline-add-form').forEach(function(form){
        if(form.id !== 'add-form-' + key){
            form.classList.add('hidden');
        }
    });

    document.querySelectorAll('.inline-add-row').forEach(function(row){
        if(row.id !== 'add-row-' + key){
            row.classList.remove('hidden');
        }
    });

    const addRow = document.getElementById('add-row-' + key);
    const addForm = document.getElementById('add-form-' + key);
    const input = document.getElementById('add-input-' + key);

    if(addRow){
        addRow.classList.add('hidden');
    }

    if(addForm){
        addForm.classList.remove('hidden');
    }

    if(input){
        input.value = '';
        input.focus();
    }

    if(key === 'root'){
        const parentWrap = document.getElementById('root-add-parent-wrap');

        if(parentWrap){
            parentWrap.classList.toggle('hidden', rootAddMode !== 'sub');
        }
    }
}

function openCategorySubAdd(parentId){

    showCategoryInlineAdd(String(parentId), parentId);

}

function showRootCategoryAdd(mode){

    rootAddMode = mode === 'sub' ? 'sub' : 'main';
    showCategoryInlineAdd('root', 0);

}

function cancelCategoryInlineAdd(key){

    const addForm = document.getElementById('add-form-' + key);
    const addRow = document.getElementById('add-row-' + key);

    if(addForm){
        addForm.classList.add('hidden');
    }

    if(addRow){
        addRow.classList.remove('hidden');
    }

    if(key === 'root'){
        const parentWrap = document.getElementById('root-add-parent-wrap');

        if(parentWrap){
            parentWrap.classList.add('hidden');
        }
    }

}

function categoryInlineAddKeydown(event, key, parentId){

    if(event.key === 'Enter'){
        event.preventDefault();
        confirmCategoryInlineAdd(key, parentId);
    }

    if(event.key === 'Escape'){
        event.preventDefault();
        cancelCategoryInlineAdd(key);
    }

}

async function confirmCategoryInlineAdd(key, parentId){

    const input = document.getElementById('add-input-' + key);
    const name = input ? input.value.trim() : '';

    if(!name){
        alert('نام را وارد کنید');

        if(input){
            input.focus();
        }

        return;
    }

    let resolvedParentId = parentId > 0 ? parentId : null;

    if(key === 'root' && rootAddMode === 'sub'){
        const parentSelect = document.getElementById('root-add-parent');
        resolvedParentId = parentSelect ? parseInt(parentSelect.value, 10) : 0;

        if(!resolvedParentId){
            alert('دسته اصلی را انتخاب کنید');

            if(parentSelect){
                parentSelect.focus();
            }

            return;
        }
    }

    const body = new URLSearchParams();
    body.append('inline_add', '1');
    body.append('name', name);
    body.append('parent_id', resolvedParentId ? String(resolvedParentId) : '');

    try{
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
            credentials: 'same-origin',
        });

        const data = await response.json();

        if(!data.ok){
            alert(data.error || 'خطا در ثبت');
            return;
        }

        window.location.reload();

    }catch(error){
        alert('خطا در ارتباط با سرور');
    }

}

function getEditablePrefixHtml(item){

    if(item.classList.contains('center-title')){
        return '📁 ';
    }

    if(item.classList.contains('section-title-text')){
        return '📄 ';
    }

    return '<span class="tree-prefix">├──</span>';
}

function restoreInlineEdit(item, itemId, name){

    item.classList.remove('editing');
    item.setAttribute('data-name', name);

    const prefix = getEditablePrefixHtml(item);

    item.innerHTML = prefix + '<span class="item-label">' + escapeHtml(name) + '</span>';

    delete item.dataset.originalName;
    inlineEditBusy = false;

}

function startInlineEdit(item){

    const itemId = item.getAttribute('data-id');
    const label = item.querySelector('.item-label');
    const nameText = label ? label.textContent.trim() : (item.getAttribute('data-name') || '');

    inlineEditBusy = true;
    item.classList.add('editing');
    item.dataset.originalName = nameText;

    const prefix = getEditablePrefixHtml(item);

    item.innerHTML =
        prefix +
        '<input type="text" class="form-control inline-edit-input" value="' + escapeHtml(nameText) + '">' +
        '<button type="button" class="inline-add-action inline-add-save" title="تایید">✓</button>' +
        '<button type="button" class="inline-add-action inline-add-cancel" title="انصراف">✕</button>';

    const input = item.querySelector('.inline-edit-input');
    const saveBtn = item.querySelector('.inline-add-save');
    const cancelBtn = item.querySelector('.inline-add-cancel');

    if(input){
        input.focus();
        input.select();
    }

    if(saveBtn){
        saveBtn.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            confirmInlineEdit(item, itemId);
        });
    }

    if(cancelBtn){
        cancelBtn.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            restoreInlineEdit(item, itemId, item.dataset.originalName || nameText);
        });
    }

    if(input){
        input.addEventListener('keydown', function(event){
            if(event.key === 'Enter'){
                event.preventDefault();
                confirmInlineEdit(item, itemId);
            }

            if(event.key === 'Escape'){
                event.preventDefault();
                restoreInlineEdit(item, itemId, item.dataset.originalName || nameText);
            }
        });
    }

}

async function confirmInlineEdit(item, itemId){

    const input = item.querySelector('.inline-edit-input');
    const name = input ? input.value.trim() : '';

    if(!name){
        alert('نام را وارد کنید');

        if(input){
            input.focus();
        }

        return;
    }

    const originalName = item.dataset.originalName || '';

    if(name === originalName){
        restoreInlineEdit(item, itemId, name);
        return;
    }

    try{
        const body = new URLSearchParams();
        body.append('inline_rename', '1');
        body.append('id', itemId);
        body.append('name', name);

        const response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
            credentials: 'same-origin',
        });

        const data = await response.json();

        if(!data.ok){
            alert(data.error || 'خطا در ویرایش');
            restoreInlineEdit(item, itemId, originalName);
            return;
        }

        restoreInlineEdit(item, itemId, data.name || name);

    }catch(error){
        alert('خطا در ویرایش. دوباره تلاش کنید.');
        restoreInlineEdit(item, itemId, originalName);
    }

}

document.addEventListener('dblclick', function(event){

    const item = event.target.closest('.editable-item');

    if(!item || inlineEditBusy || item.classList.contains('editing')){
        return;
    }

    if(event.target.closest('.menu-wrapper') || event.target.closest('.toggle')){
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    startInlineEdit(item);

});

document.addEventListener('touchend', function(event){

    const item = event.target.closest('.editable-item');

    if(!item || inlineEditBusy || item.classList.contains('editing')){
        return;
    }

    if(event.target.closest('.menu-wrapper') || event.target.closest('.toggle')){
        return;
    }

    const now = Date.now();
    const itemId = item.getAttribute('data-id');

    if(
        lastEditableTap.id === itemId
        &&
        now - lastEditableTap.time < 400
    ){
        event.preventDefault();
        startInlineEdit(item);
        lastEditableTap = { id: null, time: 0 };
        return;
    }

    lastEditableTap = { id: itemId, time: now };

});

function closeAllMenus(){

    document.querySelectorAll('.dropdown-menu').forEach(function(menu){
        menu.classList.remove('show', 'drop-down');
        menu.style.top = '';
        menu.style.bottom = '';
    });

    document.querySelectorAll('.center-box.menu-open, .section-box.menu-open, .item.menu-open').forEach(function(box){
        box.classList.remove('menu-open');
    });

}

function toggleCategoryNode(id){

    const box = document.getElementById('category-content-' + id);
    const icon = document.getElementById('category-icon-' + id);
    const toggle = document.querySelector('#category-node-' + id + ' .toggle');

    if(!box || !icon){
        return;
    }

    if(box.style.display === 'block'){

        box.style.display = 'none';
        icon.textContent = '+';

        if(toggle){
            toggle.setAttribute('aria-expanded', 'false');
        }

    }else{

        box.style.display = 'block';
        icon.textContent = '−';

        if(toggle){
            toggle.setAttribute('aria-expanded', 'true');
        }

    }

}

function toggleMenu(event, id){

    event.preventDefault();
    event.stopPropagation();

    const menu = document.getElementById('menu' + id);
    const nodeBox =
        document.getElementById('category-node-' + id)
        || document.getElementById('category-item-' + id);

    const opened = menu.classList.contains('show');

    closeAllMenus();

    if(!opened){

        menu.classList.add('show');

        if(nodeBox){
            nodeBox.classList.add('menu-open');
        }

        menu.classList.remove('drop-down');
        menu.style.top = '';
        menu.style.bottom = '';

        const rect = menu.getBoundingClientRect();

        if(rect.top < 8){
            menu.classList.add('drop-down');
            menu.style.top = 'auto';
            menu.style.bottom = 'calc(100% + 8px)';
        }

    }

}

window.addEventListener('click', function(event){

    if(!event.target.closest('.menu-wrapper')){
        closeAllMenus();
    }

});

</script>

<?php include '../includes/footer.php'; ?>
