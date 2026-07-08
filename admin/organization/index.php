<?php

require '../../includes/auth.php';
require '../../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

function org_render_section_content(
    int $centerId,
    string $sectionKey,
    array $items,
    string $nodeType,
    string $emptyLabel
): void {
    ?>
<div
class="section-content"
id="box-<?= $sectionKey ?>-<?= $centerId ?>">

<div
class="items-list"
id="items-<?= $sectionKey ?>-<?= $centerId ?>">

<?php if(count($items)): ?>

<?php foreach($items as $item): ?>

<div
class="item"
data-id="<?= (int)$item['id'] ?>">

<div
class="item-name editable-item"
data-id="<?= (int)$item['id'] ?>"
data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
title="دابل‌کلیک برای ویرایش">

<span class="tree-prefix">├──</span>

<span class="item-label"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div
class="empty empty-hint"
id="empty-<?= $sectionKey ?>-<?= $centerId ?>">

<?= htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8') ?>

</div>

<?php endif; ?>

</div>

<div
class="inline-add-row"
id="add-row-<?= $sectionKey ?>-<?= $centerId ?>">

<button
type="button"
class="inline-add-btn"
onclick="showInlineAdd('<?= $sectionKey ?>', <?= $centerId ?>, '<?= $nodeType ?>')"
title="افزودن">

+

</button>

</div>

<div
class="inline-add-form hidden"
id="add-form-<?= $sectionKey ?>-<?= $centerId ?>">

<input
type="text"
class="form-control inline-add-input"
id="add-input-<?= $sectionKey ?>-<?= $centerId ?>"
placeholder="نام را وارد کنید"
onkeydown="inlineAddKeydown(event, '<?= $sectionKey ?>', <?= $centerId ?>, '<?= $nodeType ?>')">

<button
type="button"
class="inline-add-action inline-add-save"
onclick="confirmInlineAdd('<?= $sectionKey ?>', <?= $centerId ?>, '<?= $nodeType ?>')"
title="تایید">

✓

</button>

<button
type="button"
class="inline-add-action inline-add-cancel"
onclick="cancelInlineAdd('<?= $sectionKey ?>', <?= $centerId ?>)"
title="انصراف">

✕

</button>

</div>

</div>
    <?php
}

function org_render_section_box(
    int $centerId,
    string $sectionKey,
    string $title,
    array $items,
    string $nodeType,
    string $emptyLabel
): void {
    $sectionId = $sectionKey . '-' . $centerId;
    ?>
<div class="section-box" id="section-box-<?= $sectionId ?>">

<div class="section-header">

<button
type="button"
class="toggle"
onclick="toggleSection('<?= $sectionId ?>')"
aria-label="نمایش موارد ثبت شده"
aria-expanded="false">

<span id="icon-<?= $sectionId ?>">+</span>

</button>

<div class="section-title-text">

<?= $title ?>

</div>

<div class="menu-wrapper">

<button
type="button"
class="menu-btn"
onclick="toggleSectionMenu(event, '<?= $sectionId ?>')"
aria-label="منوی افزودن">

⋮

</button>

<div
class="dropdown-menu"
id="section-menu-<?= $sectionId ?>">

<button
type="button"
onclick="openSectionAdd('<?= $sectionKey ?>', <?= $centerId ?>, '<?= $nodeType ?>')">

افزودن

</button>

</div>

</div>

</div>

<?php org_render_section_content($centerId, $sectionKey, $items, $nodeType, $emptyLabel); ?>

</div>
    <?php
}

$message = "";
$search =
trim(
$_GET['search']
?? ''
);

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

    $check = $pdo->prepare("
        SELECT id, type
        FROM organization_nodes
        WHERE id=?
    ");
    $check->execute([$id]);
    $node = $check->fetch();

    if(!$node || !in_array($node['type'], ['unit', 'health_house'], true)){
        echo json_encode(['ok' => false, 'error' => 'فقط زیرمجموعه قابل ویرایش است']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE organization_nodes
        SET name=?
        WHERE id=?
    ");
    $stmt->execute([$name, $id]);

    echo json_encode(['ok' => true, 'name' => $name]);
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $name =
    trim($_POST['name'] ?? '');

    $sort_order =
    (int)($_POST['sort_order'] ?? 0);

    $center_category =
    $_POST['center_category']
    ?? null;

    $node_mode =
    $_POST['node_mode']
    ?? 'main';

    if($node_mode === 'main'){

        $type = 'center';

        $parent_id = null;

    }else{

        $type =
        $_POST['type']
        ?? 'unit';

        $parent_id =
        !empty($_POST['parent_id'])
        ? (int)$_POST['parent_id']
        : null;

    }

    if(
        !in_array(
            $type,
            [
                'center',
                'unit',
                'health_house'
            ]
        )
    ){

        die(
            'نوع ساختار نامعتبر است'
        );

    }

    if(
        isset($_POST['edit_id'])
        &&
        $_POST['edit_id']
    ){

        $stmt = $pdo->prepare("
            UPDATE organization_nodes
            SET
            parent_id=?,
            type=?,
            center_category=?,
            name=?,
            sort_order=?
            WHERE id=?
        ");

        $stmt->execute([

            $parent_id,
            $type,
            $center_category,
            $name,
            $sort_order,
            (int)$_POST['edit_id']

        ]);

        header(
            "Location:index.php"
        );

        exit;

    }else{

        $stmt = $pdo->prepare("
            INSERT INTO organization_nodes
            (
                parent_id,
                type,
                center_category,
                name,
                sort_order
            )
            VALUES
            (?,?,?,?,?)
        ");

        $stmt->execute([

            $parent_id,
            $type,
            $center_category,
            $name,
            $sort_order

        ]);

        $message =
        "ساختار سازمانی ثبت شد";

    }

}


if($search){

    $stmt =
    $pdo->prepare("
        SELECT *
        FROM organization_nodes
        WHERE type='center'
        AND name LIKE ?
        ORDER BY sort_order ASC,id ASC
    ");

    $stmt->execute([
        "%{$search}%"
    ]);

    $centers =
    $stmt->fetchAll();

}else{

    $centers =
    $pdo->query("
        SELECT *
        FROM organization_nodes
        WHERE type='center'
        ORDER BY sort_order ASC,id ASC
    ")->fetchAll();

}

$back_url = '../index.php';
$page_title = '🏢 ساختار سازمانی';
$page_header_menu_type = 'action-menu';
$page_header_menu_label = 'منوی ساختار سازمانی';
$page_header_menu_items = [
    [
        'label' => 'جستجو',
        'onclick' => 'openOrganizationSearchModal()',
    ],
    [
        'label' => 'افزودن ساختار سازمانی',
        'onclick' => 'openAddModal()',
    ],
];

include '../../includes/header.php';

?>

<style>

.page-box{

    max-width:1100px;

    margin:auto;

}

.card{

    background:white;

    border-radius:28px;

    padding:26px;

    margin-bottom:24px;

    box-shadow:
    0 10px 35px rgba(15,23,42,.05);

    border:
    1px solid #eef2f7;

    overflow:visible;

}

.page-title{

    display:none;

}

.list-search-modal-overlay{
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

.list-search-modal-overlay.show{
    display:flex;
}

.list-search-modal{
    width:100%;
    max-width:460px;
    background:#ffffff;
    border-radius:24px;
    padding:24px 22px;
    box-shadow:0 20px 50px rgba(15,23,42,.18);
    position:relative;
}

.list-search-modal-title{
    font-size:20px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:18px;
    padding-left:36px;
}

.list-search-modal-close{
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

.search-field-label{
    display:block;
    font-size:13px;
    font-weight:800;
    color:#334155;
    margin-bottom:8px;
}

.search-field-group{
    margin-bottom:14px;
}

.section-heading{

    font-size:20px;

    font-weight:800;

    margin-bottom:18px;

    color:#0f172a;

}

.center-box{

    background:#f8fafc;

    border-radius:24px;

    margin-bottom:16px;

    overflow:visible;

    border:
    1px solid #e2e8f0;

    position:relative;

    z-index:1;

    transition:.2s;

}

.center-box.menu-open{

    z-index:200;

}

.center-box:hover{

    box-shadow:
    0 10px 30px rgba(15,23,42,.05);

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

.center-content{

    display:none;

    padding:0 18px 18px;

}

.section-title{

    margin-top:18px;

    margin-bottom:12px;

    font-size:13px;

    font-weight:800;

    color:#475569;

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

.section-box.menu-open{

    z-index:150;

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

.section-content{

    display:none;

    padding:12px 14px 14px;

}

.items-list{

    margin-bottom:4px;

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

    cursor:text;

    user-select:none;

}

.editable-item{

    cursor:pointer;

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

.inline-add-form.hidden,
.hidden{

    display:none !important;

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

.item{

    background:white;

    border-radius:18px;

    padding:12px 15px;

    margin-bottom:10px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    border:
    1px solid #eef2f7;

    transition:.2s;

}

.item:hover{

    border-color:#bae6fd;

    background:#fafdff;

}

.toggle{

    font-size:22px;

    color:#0284c7;

    font-weight:bold;

    cursor:pointer;

    width:36px;

    height:36px;

    flex-shrink:0;

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

.empty{

    color:#94a3b8;

    font-size:13px;

    margin-top:10px;

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

    inset-inline-start:0;

    top:auto;

    bottom:calc(100% + 8px);

    background:white;

    border-radius:18px;

    box-shadow:
    0 15px 40px rgba(15,23,42,.14);

    min-width:160px;

    border:
    1px solid #eef2f7;

    z-index:9999;

    display:none;

    overflow:hidden;

}

.dropdown-menu.drop-down{

    top:calc(100% + 8px);

    bottom:auto;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu a{

    display:flex;

    align-items:center;

    gap:10px;

    padding:13px 15px;

    text-decoration:none;

    color:#334155;

    font-size:14px;

    transition:.2s;

}

.dropdown-menu a:hover{

    background:#f8fafc;

}

@media(max-width:768px){

    .center-header{

        align-items:flex-start;

        gap:10px;

    }

}
.modal-overlay{

    position:fixed;

    inset:0;

    background:
    rgba(15,23,42,.35);

    backdrop-filter:
    blur(8px);

    display:none;

    justify-content:center;

    align-items:center;

    z-index:10000;

}

.modal-overlay.show{

    display:flex;

}

.modal-box{

    width:90%;

    max-width:550px;

    background:white;

    border-radius:24px;

    padding:24px;

    box-shadow:
    0 20px 60px
    rgba(0,0,0,.15);

}

.modal-title{

    font-size:22px;

    font-weight:800;

    margin-bottom:20px;

}

.modal-actions{

    display:flex;

    gap:10px;

    margin-top:20px;

}

.modal-btn{

    flex:1;

    border:none;

    border-radius:16px;

    padding:14px;

    font-weight:700;

    cursor:pointer;

}

.save-btn{

    background:
    linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

}

.cancel-btn{

    background:#f1f5f9;

}
.dropdown-menu button{

    width:100%;

    border:none;

    background:none;

    text-align:right;

    padding:13px 15px;

    cursor:pointer;

    font-size:14px;

    color:#334155;

}

.dropdown-menu button:hover{

    background:#f8fafc;

}

</style>

<div class="page-box">

<?php if($message): ?>

<div class="card">

<div class="alert alert-success">

<?= $message ?>

</div>

</div>

<?php endif; ?>

<div class="card">

<h2 class="section-heading">📂 لیست مراکز</h2>

<?php foreach($centers as $center): ?>

<?php

$stmt = $pdo->prepare("
    SELECT *
    FROM organization_nodes
    WHERE parent_id=?
    ORDER BY sort_order ASC,id ASC
");

$stmt->execute([
    $center['id']
]);

$children =
$stmt->fetchAll();

$units = [];

$healths = [];

foreach($children as $child){

    if(
        $child['type']
        == 'health_house'
    ){

        $healths[] = $child;

    }else{

        $units[] = $child;

    }

}

?>

<div class="center-box" id="center-box-<?= $center['id'] ?>">

<div class="center-header">

<button
type="button"
class="toggle"
id="toggle<?= $center['id'] ?>"
onclick="toggleBox(<?= $center['id'] ?>)"
aria-label="نمایش زیرمجموعه"
aria-expanded="false">

<span id="icon<?= $center['id'] ?>">+</span>

</button>

<div class="center-info">

<div class="center-title">

🏥 <?= htmlspecialchars(
$center['name']
) ?>

</div>

<div class="center-type">

<?=

($center['center_category'] ?? '')
== 'administrative'

? 'مرکز ستادی'
: 'مرکز درمانی'

?>

</div>

</div>

<div class="menu-wrapper">

<button
type="button"
class="menu-btn"
onclick="toggleMenu(event,<?= $center['id'] ?>)"
aria-label="عملیات مرکز">

⋮

</button>

<div
class="dropdown-menu"
id="menu<?= $center['id'] ?>">

<button
type="button"
onclick='openEditModal(
<?= $center["id"] ?>,
<?= json_encode($center["name"]) ?>,
<?= (int)$center["sort_order"] ?>,
<?= json_encode($center["center_category"] ?? "") ?>
)'
>

✏️ ویرایش

</button>

<a
href="delete.php?id=<?= $center['id'] ?>"
onclick="return confirm('حذف شود؟')">

🗑 حذف

</a>

</div>

</div>

</div>

<div
class="center-content"
id="box<?= $center['id'] ?>">

<?php if(
$center['center_category']
== 'administrative'
): ?>

<?php org_render_section_box(
    (int)$center['id'],
    'units',
    '🏢 واحد های ستادی',
    $units,
    'unit',
    'واحدی ثبت نشده'
); ?>

<?php else: ?>

<?php org_render_section_box(
    (int)$center['id'],
    'units',
    '🏢 واحد های مستقر',
    $units,
    'unit',
    'واحدی ثبت نشده'
); ?>

<?php org_render_section_box(
    (int)$center['id'],
    'healths',
    '🏡 خانه های بهداشت',
    $healths,
    'health_house',
    'خانه بهداشتی ثبت نشده'
); ?>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<script>

function toggleBox(id){

    let box =
    document.getElementById(
        'box' + id
    );

    let icon =
    document.getElementById(
        'icon' + id
    );

    let toggle =
    document.getElementById(
        'toggle' + id
    );

    if(
        box.style.display === 'block'
    ){

        box.style.display = 'none';

        icon.innerHTML = '+';

        if(toggle){
            toggle.setAttribute('aria-expanded', 'false');
        }

    }else{

        box.style.display = 'block';

        icon.innerHTML = '−';

        if(toggle){
            toggle.setAttribute('aria-expanded', 'true');
        }

    }

}

function toggleSection(sectionId){

    const box =
    document.getElementById(
        'box-' + sectionId
    );

    const icon =
    document.getElementById(
        'icon-' + sectionId
    );

    const toggle =
    document.querySelector(
        '#section-box-' + sectionId + ' .section-header .toggle'
    );

    if(!box || !icon){
        return;
    }

    if(box.style.display === 'block'){

        box.style.display = 'none';

        icon.innerHTML = '+';

        if(toggle){
            toggle.setAttribute('aria-expanded', 'false');
        }

    }else{

        box.style.display = 'block';

        icon.innerHTML = '−';

        if(toggle){
            toggle.setAttribute('aria-expanded', 'true');
        }

    }

}

let inlineEditBusy = false;
let lastEditableTap = { id: null, time: 0 };

function renderItemName(id, name){

    return (
        '<div class="item-name editable-item" data-id="' + id + '" data-name="' + escapeHtml(name) + '" title="دابل‌کلیک برای ویرایش">' +
        '<span class="tree-prefix">├──</span>' +
        '<span class="item-label">' + escapeHtml(name) + '</span>' +
        '</div>'
    );

}

function escapeHtml(text){

    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

}

function startInlineEdit(item){

    const itemId = item.getAttribute('data-id');
    const currentName = item.getAttribute('data-name') || '';
    const label = item.querySelector('.item-label');
    const nameText = label ? label.textContent.trim() : currentName;

    inlineEditBusy = true;
    item.classList.add('editing');
    item.dataset.originalName = nameText;

    item.innerHTML =
        '<span class="tree-prefix">├──</span>' +
        '<input type="text" class="form-control inline-edit-input" value="' + escapeHtml(nameText) + '">' +
        '<button type="button" class="inline-add-action inline-add-save" title="تایید">✓</button>' +
        '<button type="button" class="inline-add-action inline-add-cancel" title="انصراف">✕</button>';

    const input = item.querySelector('.inline-edit-input');
    const saveBtn = item.querySelector('.inline-add-save');
    const cancelBtn = item.querySelector('.inline-add-cancel');

    input.focus();
    input.select();

    saveBtn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        confirmInlineEdit(item, itemId);
    });

    cancelBtn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        restoreInlineEdit(item, itemId, item.dataset.originalName || nameText);
    });

    input.addEventListener('keydown', function(e){
        if(e.key === 'Enter'){
            e.preventDefault();
            confirmInlineEdit(item, itemId);
        }

        if(e.key === 'Escape'){
            e.preventDefault();
            restoreInlineEdit(item, itemId, item.dataset.originalName || nameText);
        }
    });

}

function restoreInlineEdit(item, itemId, name){

    item.classList.remove('editing');
    item.setAttribute('data-name', name);
    item.innerHTML =
        '<span class="tree-prefix">├──</span>' +
        '<span class="item-label">' + escapeHtml(name) + '</span>';

    delete item.dataset.originalName;
    inlineEditBusy = false;

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
            credentials: 'same-origin'
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

    event.preventDefault();
    event.stopPropagation();

    startInlineEdit(item);

});

document.addEventListener('touchend', function(event){

    const item = event.target.closest('.editable-item');

    if(!item || inlineEditBusy || item.classList.contains('editing')){
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

function showInlineAdd(sectionKey, centerId, nodeType){

    const sectionId = sectionKey + '-' + centerId;
    const box = document.getElementById('box-' + sectionId);
    const icon = document.getElementById('icon-' + sectionId);

    if(box && box.style.display !== 'block'){
        box.style.display = 'block';

        if(icon){
            icon.innerHTML = '−';
        }
    }

    document
    .getElementById('add-row-' + sectionKey + '-' + centerId)
    .classList.add('hidden');

    const form =
    document.getElementById('add-form-' + sectionKey + '-' + centerId);

    form.classList.remove('hidden');

    const input =
    document.getElementById('add-input-' + sectionKey + '-' + centerId);

    input.value = '';
    input.focus();

}

function openSectionAdd(sectionKey, centerId, nodeType){

    closeAllMenus();
    showInlineAdd(sectionKey, centerId, nodeType);

}

function cancelInlineAdd(sectionKey, centerId){

    document
    .getElementById('add-form-' + sectionKey + '-' + centerId)
    .classList.add('hidden');

    document
    .getElementById('add-row-' + sectionKey + '-' + centerId)
    .classList.remove('hidden');

}

function inlineAddKeydown(event, sectionKey, centerId, nodeType){

    if(event.key === 'Enter'){
        event.preventDefault();
        confirmInlineAdd(sectionKey, centerId, nodeType);
    }

    if(event.key === 'Escape'){
        event.preventDefault();
        cancelInlineAdd(sectionKey, centerId);
    }

}

async function confirmInlineAdd(sectionKey, centerId, nodeType){

    const input =
    document.getElementById('add-input-' + sectionKey + '-' + centerId);

    const name = input.value.trim();

    if(!name){
        alert('نام را وارد کنید');
        input.focus();
        return;
    }

    const formData = new FormData();
    formData.append('parent_id', centerId);
    formData.append('type', nodeType);
    formData.append('name', name);

    try{
        const response = await fetch('quick-add.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await response.json();

        if(!data.success){
            alert(data.error || 'خطا در ثبت');
            return;
        }

        const list =
        document.getElementById('items-' + sectionKey + '-' + centerId);

        const emptyHint =
        document.getElementById('empty-' + sectionKey + '-' + centerId);

        if(emptyHint){
            emptyHint.remove();
        }

        const item = document.createElement('div');
        item.className = 'item';
        item.dataset.id = data.id;
        item.innerHTML = renderItemName(data.id, data.name);

        list.appendChild(item);

        cancelInlineAdd(sectionKey, centerId);

    }catch(error){
        alert('خطا در ارتباط با سرور');
    }

}

function closeAllMenus(){

    document
    .querySelectorAll(
        '.dropdown-menu'
    )
    .forEach(menu => {

        menu.classList.remove(
            'show',
            'drop-down'
        );

        menu.style.top = '';
        menu.style.bottom = '';

    });

    document
    .querySelectorAll(
        '.center-box.menu-open'
    )
    .forEach(box => {

        box.classList.remove(
            'menu-open'
        );

    });

    document
    .querySelectorAll(
        '.section-box.menu-open'
    )
    .forEach(box => {

        box.classList.remove(
            'menu-open'
        );

    });

}

function toggleSectionMenu(event, sectionId){

    event.preventDefault();

    event.stopPropagation();

    const menu =
    document.getElementById(
        'section-menu-' + sectionId
    );

    const sectionBox =
    document.getElementById(
        'section-box-' + sectionId
    );

    const opened =
    menu.classList.contains(
        'show'
    );

    closeAllMenus();

    if(!opened){

        menu.classList.add(
            'show'
        );

        if(sectionBox){
            sectionBox.classList.add('menu-open');
        }

        menu.classList.remove('drop-down');
        menu.style.top = '';
        menu.style.bottom = '';

        const rect = menu.getBoundingClientRect();

        if(rect.top < 8){
            menu.classList.add('drop-down');
            menu.style.top = 'calc(100% + 8px)';
            menu.style.bottom = 'auto';
        }

    }

}

function toggleMenu(event,id){

    event.preventDefault();

    event.stopPropagation();

    let menu =
    document.getElementById(
        'menu'+id
    );

    let centerBox =
    document.getElementById(
        'center-box-' + id
    );

    let opened =
    menu.classList.contains(
        'show'
    );

    closeAllMenus();

    if(!opened){

        menu.classList.add(
            'show'
        );

        if(centerBox){
            centerBox.classList.add('menu-open');
        }

        menu.classList.remove('drop-down');
        menu.style.top = '';
        menu.style.bottom = '';

        const rect = menu.getBoundingClientRect();

        if(rect.top < 8){
            menu.classList.add('drop-down');
            menu.style.top = 'calc(100% + 8px)';
            menu.style.bottom = 'auto';
        }

    }

}

window.addEventListener(
    'click',
    function(event){

        if(!event.target.closest('.menu-wrapper')){
            closeAllMenus();
        }

    }
);

let isMainCenter =
document.getElementById(
    'isMainCenter'
);

let parentWrapper =
document.getElementById(
    'parentWrapper'
);

let typeWrapper =
document.getElementById(
    'typeWrapper'
);

let parentSelect =
document.getElementById(
    'parentSelect'
);

let typeSelect =
document.getElementById(
    'typeSelect'
);

isMainCenter.addEventListener(
    'change',
    function(){

        if(this.value == 'yes'){

            parentWrapper.style.display =
            'none';

            typeWrapper.style.display =
            'none';

            typeSelect.innerHTML = `
                <option value="center">
                مرکز
                </option>
            `;

        }else{

            parentWrapper.style.display =
            'block';

        }

    }
);

parentSelect.addEventListener(
    'change',
    function(){

        if(!this.value){

            typeWrapper.style.display =
            'none';

            return;

        }

        typeWrapper.style.display =
        'block';

        let selected =
        this.options[
            this.selectedIndex
        ];

        let category =
        selected.getAttribute(
            'data-category'
        );

        if(
            category
            == 'administrative'
        ){

            typeSelect.innerHTML = `

                <option value="unit">
                واحد ستادی
                </option>

            `;

        }else{

            typeSelect.innerHTML = `

                <option value="unit">
                واحد مستقر
                </option>

                <option value="health_house">
                خانه بهداشت
                </option>

            `;

        }

    }
);
function openAddModal(){

    document
    .getElementById(
        'addModal'
    )
    .classList.add(
        'show'
    );

}

function closeAddModal(){

    document
    .getElementById(
        'addModal'
    )
    .classList.remove(
        'show'
    );

}
function toggleParentSelect(){

    const isMain =
    document.getElementById('is_main');

    const parentBox =
    document.getElementById('parentBox');

    if(isMain.checked){

        parentBox.style.display='none';

    }else{

        parentBox.style.display='block';

    }

}
function openEditModal(
    id,
    name,
    sortOrder,
    category
){

    closeAllMenus();

    document.getElementById(
        'edit_id'
    ).value = id;

    document.getElementById(
        'edit_name'
    ).value = name;

    document.getElementById(
        'edit_sort_order'
    ).value = sortOrder;

    let categoryField =
    document.getElementById(
        'edit_center_category'
    );

    if(categoryField){

        categoryField.value =
        category;

    }

    document.getElementById(
        'editModal'
    ).classList.add(
        'show'
    );

}

function closeEditModal(){

    document
    .getElementById(
        'editModal'
    )
    .classList.remove(
        'show'
    );

}
function changeNodeMode(mode){

    let mainBox =
    document.getElementById(
        'mainCenterBox'
    );

    let childBox =
    document.getElementById(
        'childCenterBox'
    );

    let mainType =
    document.getElementById(
        'mainType'
    );

    let childType =
    document.getElementById(
        'childType'
    );

    if(mode === 'main'){

        mainBox.style.display =
        'block';

        childBox.style.display =
        'none';

        mainType.disabled =
        false;

        childType.disabled =
        true;

    }else{

        mainBox.style.display =
        'none';

        childBox.style.display =
        'block';

        mainType.disabled =
        true;

        childType.disabled =
        false;

    }

}
</script>

<div
class="list-search-modal-overlay"
id="organizationSearchModalOverlay"
aria-hidden="true">

<div class="list-search-modal" role="dialog" aria-modal="true">

<button
type="button"
class="list-search-modal-close"
onclick="closeOrganizationSearchModal()"
aria-label="بستن">

×

</button>

<h2 class="list-search-modal-title">جستجوی ساختار سازمانی</h2>

<form method="GET" id="organizationSearchForm">

<div class="search-field-group">
<label class="search-field-label" for="organizationSearchInput">نام مرکز</label>
<input
type="text"
id="organizationSearchInput"
name="search"
class="form-control"
placeholder="جستجو در ساختار سازمانی"
value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
</div>

<button type="submit" class="btn-custom">جستجو</button>

</form>

</div>

</div>

<script>

const organizationSearchModalOverlay =
document.getElementById('organizationSearchModalOverlay');

function closePageHeaderDropdown(){

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

function closeOrganizationSearchModal(){

    if(!organizationSearchModalOverlay){
        return;
    }

    organizationSearchModalOverlay.classList.remove('show');
    organizationSearchModalOverlay.setAttribute('aria-hidden', 'true');
    closePageHeaderDropdown();

}

function openOrganizationSearchModal(){

    if(!organizationSearchModalOverlay){
        return;
    }

    organizationSearchModalOverlay.classList.add('show');
    organizationSearchModalOverlay.setAttribute('aria-hidden', 'false');
    closePageHeaderDropdown();

    const searchInput =
    document.getElementById('organizationSearchInput');

    if(searchInput){
        searchInput.focus();
    }

}

if(organizationSearchModalOverlay){

    organizationSearchModalOverlay.addEventListener('click', function(event){

        if(event.target === organizationSearchModalOverlay){
            closeOrganizationSearchModal();
        }

    });

}

document.addEventListener('keydown', function(event){

    if(
        event.key === 'Escape' &&
        organizationSearchModalOverlay &&
        organizationSearchModalOverlay.classList.contains('show')
    ){
        closeOrganizationSearchModal();
    }

});

</script>

<div
id="addModal"
class="modal-overlay">

<div class="modal-box">

<div class="modal-title">

افزودن ساختار سازمانی

</div>

<form method="POST">

<input
type="text"
name="name"
class="form-control"
placeholder="نام مرکز / واحد / خانه بهداشت"
required>

<div style="margin:15px 0;">

<label style="
display:flex;
align-items:center;
gap:10px;
font-weight:700;
">

<input
type="radio"
name="node_mode"
value="main"
checked
onclick="changeNodeMode('main')">

مرکز اصلی

</label>

</div>

<div style="margin-bottom:15px;">

<label style="
display:flex;
align-items:center;
gap:10px;
font-weight:700;
">

<input
type="radio"
name="node_mode"
value="child"
onclick="changeNodeMode('child')">

زیرمجموعه

</label>

</div>

<div
id="mainCenterBox">

<select
name="center_category"
class="form-control">

<option value="">
نوع مرکز
</option>

<option value="administrative">
مرکز ستادی
</option>

<option value="treatment">
مرکز درمانی
</option>

</select>


</div>

<input
type="hidden"
name="type"
id="mainType"
value="center">

<div
id="childCenterBox"
style="display:none;">

<select
name="parent_id"
class="form-control">

<option value="">
مرکز بالادستی
</option>

<?php foreach($centers as $center): ?>

<option
value="<?= $center['id'] ?>"
data-category="<?= $center['center_category'] ?? '' ?>">

<?= htmlspecialchars($center['name']) ?>

</option>

<?php endforeach; ?>

</select>

<select
name="type"
id="childType"
class="form-control">

<option value="">
نوع زیرمجموعه
</option>

<option value="unit">
واحد مستقر
</option>

<option value="health_house">
خانه بهداشت
</option>

</select>

</div>

<input
type="number"
name="sort_order"
class="form-control"
placeholder="ترتیب نمایش"
value="0">

<div class="modal-actions">

<button
type="submit"
class="modal-btn save-btn">

ثبت

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

ویرایش ساختار سازمانی

</div>

<form method="POST">

<input
type="hidden"
name="edit_id"
id="edit_id">

<input
type="text"
name="name"
id="edit_name"
class="form-control"
required>

<select
name="center_category"
id="edit_center_category"
class="form-control">

<option value="administrative">

ستادی

</option>

<option value="treatment">

درمانی

</option>

</select>

<input
type="number"
name="sort_order"
id="edit_sort_order"
class="form-control">

<div class="modal-actions">

<button
type="submit"
class="modal-btn save-btn">

ذخیره تغییرات

</button>

<button
type="button"
onclick="closeEditModal()"
class="modal-btn cancel-btn">

انصراف

</button>

</div>

</form>

</div>

</div>
<?php include '../../includes/footer.php'; ?>