<?php

require '../../includes/admin_auth.php';

function org_used_sort_orders(PDO $pdo, ?int $parentId, ?string $centerCategory = null): array
{
    if($parentId === null){
        if($centerCategory){
            $stmt = $pdo->prepare("
                SELECT sort_order
                FROM organization_nodes
                WHERE type='center' AND center_category=?
            ");
            $stmt->execute([$centerCategory]);
        }else{
            $stmt = $pdo->query("
                SELECT sort_order
                FROM organization_nodes
                WHERE type='center'
            ");
        }
    }else{
        $stmt = $pdo->prepare("
            SELECT sort_order
            FROM organization_nodes
            WHERE parent_id=?
        ");
        $stmt->execute([$parentId]);
    }

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function org_next_sort_order(PDO $pdo, ?int $parentId, ?string $centerCategory = null): int
{
    $used = org_used_sort_orders($pdo, $parentId, $centerCategory);

    if(!$used){
        return 1;
    }

    $max = max($used);

    for($i = 1; $i <= $max + 1; $i++){
        if(!in_array($i, $used, true)){
            return $i;
        }
    }

    return $max + 1;
}

if(isset($_GET['action']) && $_GET['action'] === 'next_sort'){

    header('Content-Type: application/json; charset=utf-8');

    $parentId = (int)($_GET['parent_id'] ?? 0);
    $category = trim($_GET['category'] ?? '');

    if($parentId > 0){
        echo json_encode([
            'sort_order' => org_next_sort_order($pdo, $parentId),
        ]);
    }else{
        echo json_encode([
            'sort_order' => org_next_sort_order(
                $pdo,
                null,
                $category !== '' ? $category : null
            ),
        ]);
    }

    exit;
}

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

    $stmt = $pdo->prepare("
        UPDATE organization_nodes
        SET name=?
        WHERE id=? AND type IN ('unit','health_house')
    ");
    $stmt->execute([$name, $id]);

    echo json_encode(['ok' => $stmt->rowCount() > 0]);
    exit;
}

$message = "";
$search =
trim(
$_GET['search']
?? ''
);

if($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['inline_rename'])){

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
        $node_mode === 'child'
        &&
        $parent_id
        &&
        $type === 'health_house'
    ){
        $parentStmt = $pdo->prepare("
            SELECT center_category
            FROM organization_nodes
            WHERE id=?
            AND type='center'
        ");
        $parentStmt->execute([$parent_id]);
        $parentCenter = $parentStmt->fetch();

        if(
            $parentCenter
            &&
            ($parentCenter['center_category'] ?? '') === 'administrative'
        ){
            die('مرکز ستادی زیرمجموعه خانه بهداشت ندارد');
        }
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

        $applyAllTreatment =
        !empty($_POST['apply_all_treatment'])
        &&
        $node_mode === 'child'
        &&
        $type === 'unit';

        if($applyAllTreatment){

            $treatmentCenters = $pdo->query("
                SELECT id
                FROM organization_nodes
                WHERE type='center'
                AND center_category='treatment'
                ORDER BY sort_order ASC, id ASC
            ")->fetchAll();

            if(!count($treatmentCenters)){
                die('مرکز درمانی برای ثبت گروهی یافت نشد');
            }

            $insert = $pdo->prepare("
                INSERT INTO organization_nodes
                (parent_id, type, center_category, name, sort_order)
                VALUES (?, 'unit', NULL, ?, ?)
            ");

            $count = 0;

            foreach($treatmentCenters as $center){
                $childSort = $sort_order >= 1
                    ? $sort_order
                    : org_next_sort_order($pdo, (int)$center['id']);

                $insert->execute([
                    (int)$center['id'],
                    $name,
                    $childSort,
                ]);

                $count++;
            }

            $message = "واحد در {$count} مرکز درمانی ثبت شد";

        }else{

            if(!$parent_id && $node_mode === 'child'){
                die('مرکز بالادستی را انتخاب کنید یا تیک «تمام مراکز درمانی» را بزنید');
            }

            if($sort_order < 1 && $node_mode === 'main'){
                $sort_order = org_next_sort_order($pdo, null, $center_category ?: null);
            }elseif($sort_order < 1 && $parent_id){
                $sort_order = org_next_sort_order($pdo, $parent_id);
            }

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

$firstTreatmentCenterId = (int)$pdo->query("
    SELECT id
    FROM organization_nodes
    WHERE type='center'
    AND center_category='treatment'
    ORDER BY sort_order ASC, id ASC
    LIMIT 1
")->fetchColumn();

$back_url = '../index.php';

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

}

.page-title{

    font-size:28px;

    font-weight:800;

    margin-bottom:24px;

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

    transition:.2s;

}

.center-box:hover{

    box-shadow:
    0 10px 30px rgba(15,23,42,.05);

}

.center-header{

    padding:16px 18px;

    display:flex;

    justify-content:space-between;

    align-items:center;

}

.center-click{

    flex:1;

    cursor:pointer;

}

.center-title{

    font-size:16px;

    font-weight:800;

    color:#0f172a;

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

.item-name{

    font-size:14px;

    font-weight:600;

    color:#334155;

}

.toggle{

    font-size:22px;

    color:#0284c7;

    font-weight:bold;

    cursor:pointer;

    width:36px;

    height:36px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    transition:.2s;

    user-select:none;

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

    z-index:10;

}

.menu-btn{

    cursor:pointer;

    width:38px;

    height:38px;

    border-radius:12px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:22px;

    transition:.2s;

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

    box-shadow:
    0 15px 40px rgba(15,23,42,.14);

    min-width:160px;

    border:
    1px solid #eef2f7;

    z-index:100;

    display:none;

    overflow:hidden;

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

        flex-direction:column;

        align-items:flex-start;

        gap:12px;

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

.item-name.editing{

    padding:0;

}

.item-name input{

    width:100%;

    border:1px solid #93c5fd;

    border-radius:10px;

    padding:8px 10px;

    font-size:14px;

    font-family:inherit;

    outline:none;

}

.editable-item{

    cursor:text;

}

.apply-all-box{

    display:none;

    margin:12px 0;

    padding:12px 14px;

    background:#eff6ff;

    border-radius:14px;

    border:1px solid #bfdbfe;

}

.apply-all-box label{

    display:flex;

    align-items:center;

    gap:10px;

    font-weight:700;

    color:#1e3a8a;

    cursor:pointer;

}

.hint-text{

    font-size:12px;

    color:#64748b;

    margin-top:8px;

}

.apply-all-box.visible{

    display:block;

}

.apply-all-box input[type="checkbox"]{

    width:18px;

    height:18px;

    flex-shrink:0;

}

</style>

<div class="page-box">

<div class="card">

<div class="page-title">

🏢 ساختار سازمانی

</div>

<?php if($message): ?>

<div class="alert alert-success">

<?= $message ?>

</div>

<?php endif; ?>

<div style="
display:flex;
gap:10px;
align-items:center;
flex-wrap:wrap;
">

<form
method="GET"
style="
flex:1;
display:flex;
gap:10px;
">

<input
type="text"
name="search"
class="form-control"
placeholder="جستجو در ساختار سازمانی"
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

افزودن ساختار سازمانی

</button>

</div>

</div>

<div class="card">

<div class="page-title">

📂 لیست مراکز

</div>

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

<div class="center-box">

<div class="center-header">

<div
class="center-click"
onclick="toggleBox(<?= $center['id'] ?>)">

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

<div
style="
display:flex;
align-items:center;
gap:10px;
">

<div
class="toggle"
onclick="toggleBox(<?= $center['id'] ?>)">

<span id="icon<?= $center['id'] ?>">

+

</span>

</div>

<div class="menu-wrapper">

<div
class="menu-btn"
onclick="toggleMenu(event,<?= $center['id'] ?>)">

⋮

</div>

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

</div>

<div
class="center-content"
id="box<?= $center['id'] ?>">

<?php if(
$center['center_category']
== 'administrative'
): ?>

<div class="section-title">

🏢 واحد های ستادی

</div>

<?php if(count($units)): ?>

<?php foreach($units as $unit): ?>

<div class="item">

<div
class="item-name editable-item"
data-id="<?= (int)$unit['id'] ?>"
data-name="<?= htmlspecialchars($unit['name'], ENT_QUOTES) ?>"
title="دابل‌کلیک برای ویرایش نام">

├── <?= htmlspecialchars(
$unit['name']
) ?>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">

واحدی ثبت نشده

</div>

<?php endif; ?>

<?php else: ?>

<div class="section-title">

🏢 واحد های مستقر

</div>

<?php if(count($units)): ?>

<?php foreach($units as $unit): ?>

<div class="item">

<div
class="item-name editable-item"
data-id="<?= (int)$unit['id'] ?>"
data-name="<?= htmlspecialchars($unit['name'], ENT_QUOTES) ?>"
title="دابل‌کلیک برای ویرایش نام">

├── <?= htmlspecialchars(
$unit['name']
) ?>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">

واحدی ثبت نشده

</div>

<?php endif; ?>

<div class="section-title">

🏡 خانه های بهداشت

</div>

<?php if(count($healths)): ?>

<?php foreach($healths as $health): ?>

<div class="item">

<div
class="item-name editable-item"
data-id="<?= (int)$health['id'] ?>"
data-name="<?= htmlspecialchars($health['name'], ENT_QUOTES) ?>"
title="دابل‌کلیک برای ویرایش نام">

├── <?= htmlspecialchars(
$health['name']
) ?>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">

خانه بهداشتی ثبت نشده

</div>

<?php endif; ?>

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

    if(
        box.style.display === 'block'
    ){

        box.style.display = 'none';

        icon.innerHTML = '+';

    }else{

        box.style.display = 'block';

        icon.innerHTML = '−';

    }

}

function closeAllMenus(){

    document
    .querySelectorAll(
        '.dropdown-menu'
    )
    .forEach(menu => {

        menu.classList.remove(
            'show'
        );

    });

}

function toggleMenu(event,id){

    event.preventDefault();

    event.stopPropagation();

    let menu =
    document.getElementById(
        'menu'+id
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

    }

}

window.addEventListener(
    'click',
    function(){

        closeAllMenus();

    }
);

let parentSelect = document.getElementById('parentSelect');
let childType = document.getElementById('childType');
let childTypeWrapper = document.getElementById('childTypeWrapper');
let mainCategorySelect = document.getElementById('mainCategorySelect');
let sortOrderInput = document.getElementById('sortOrderInput');
let applyAllBox = document.getElementById('applyAllBox');
const firstTreatmentCenterId = <?= $firstTreatmentCenterId ?>;

function fetchNextSort(params){

    if(!sortOrderInput){
        return;
    }

    const query = new URLSearchParams(params);

    fetch('index.php?action=next_sort&' + query.toString())
    .then(response => response.json())
    .then(data => {
        if(typeof data.sort_order !== 'undefined'){
            sortOrderInput.value = data.sort_order;
        }
    })
    .catch(() => {});

}

function updateChildTypeOptions(){

    if(!parentSelect || !childType){
        return;
    }

    const selected = parentSelect.options[parentSelect.selectedIndex];
    const category = selected ? selected.getAttribute('data-category') : '';
    const applyAllChecked = document.getElementById('applyAllCheckbox')?.checked;

    if(childTypeWrapper){
        childTypeWrapper.style.display = 'block';
    }

    if(category === 'administrative'){
        childType.innerHTML = `<option value="unit">واحد ستادی</option>`;
        setApplyAllVisible(false);
    }else{
        childType.innerHTML = `
            <option value="unit">واحد مستقر</option>
            <option value="health_house">خانه بهداشت</option>
        `;
        updateApplyAllVisibility();
    }

    if(applyAllChecked){
        if(parentSelect){
            parentSelect.disabled = true;
        }
        return;
    }

    if(parentSelect){
        parentSelect.disabled = false;
    }

    if(parentSelect.value){
        fetchNextSort({ parent_id: parentSelect.value });
    }

}

function setApplyAllVisible(show){

    if(!applyAllBox){
        return;
    }

    if(show){
        applyAllBox.classList.add('visible');
    }else{
        applyAllBox.classList.remove('visible');
    }

}

function updateApplyAllVisibility(){

    const childBox = document.getElementById('childCenterBox');
    const applyAllCheckbox = document.getElementById('applyAllCheckbox');

    if(!childBox || childBox.style.display === 'none'){
        setApplyAllVisible(false);
        return;
    }

    const isUnit = childType && childType.value === 'unit';
    const selected = parentSelect?.options[parentSelect.selectedIndex];
    const category = selected ? selected.getAttribute('data-category') : '';
    const isAdministrativeParent = parentSelect?.value && category === 'administrative';

    setApplyAllVisible(isUnit && !isAdministrativeParent);

    if(applyAllCheckbox?.checked && parentSelect){
        parentSelect.disabled = true;
    }else if(parentSelect){
        parentSelect.disabled = false;
    }

}

function onApplyAllToggle(){

    const applyAllCheckbox = document.getElementById('applyAllCheckbox');

    if(applyAllCheckbox?.checked){
        if(parentSelect){
            parentSelect.disabled = true;
            parentSelect.value = '';
        }
        if(firstTreatmentCenterId > 0){
            fetchNextSort({ parent_id: firstTreatmentCenterId });
        }
    }else if(parentSelect){
        parentSelect.disabled = false;
        updateChildTypeOptions();
    }

}

function updateMainSort(){

    if(!mainCategorySelect || !sortOrderInput){
        return;
    }

    if(!mainCategorySelect.value){
        return;
    }

    fetchNextSort({ category: mainCategorySelect.value });
}

if(parentSelect){
    parentSelect.addEventListener('change', updateChildTypeOptions);
}

if(childType){
    childType.addEventListener('change', function(){
        const applyAllCheckbox = document.getElementById('applyAllCheckbox');
        if(childType.value !== 'unit' && applyAllCheckbox){
            applyAllCheckbox.checked = false;
            if(parentSelect){
                parentSelect.disabled = false;
            }
        }
        updateApplyAllVisibility();
        if(parentSelect && parentSelect.value && !applyAllCheckbox?.checked){
            fetchNextSort({ parent_id: parentSelect.value });
        }
    });
}

if(mainCategorySelect){
    mainCategorySelect.addEventListener('change', updateMainSort);
}

function openAddModal(){

    document.getElementById('addModal').classList.add('show');

    changeNodeMode(
        document.querySelector('input[name="node_mode"]:checked')?.value || 'main'
    );

}

function closeAddModal(){

    document.getElementById('addModal').classList.remove('show');

}

function openEditModal(id, name, sortOrder, category){

    closeAllMenus();

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_sort_order').value = sortOrder;

    let categoryField = document.getElementById('edit_center_category');

    if(categoryField){
        categoryField.value = category;
    }

    document.getElementById('editModal').classList.add('show');

}

function closeEditModal(){

    document.getElementById('editModal').classList.remove('show');

}

function changeNodeMode(mode){

    let mainBox = document.getElementById('mainCenterBox');
    let childBox = document.getElementById('childCenterBox');
    let mainType = document.getElementById('mainType');
    let childTypeEl = document.getElementById('childType');

    if(mode === 'main'){

        mainBox.style.display = 'block';
        childBox.style.display = 'none';
        mainType.disabled = false;
        childTypeEl.disabled = true;

        if(applyAllBox){
            setApplyAllVisible(false);
        }

        updateMainSort();

    }else{

        mainBox.style.display = 'none';
        childBox.style.display = 'block';
        mainType.disabled = true;
        childTypeEl.disabled = false;

        if(childTypeWrapper){
            childTypeWrapper.style.display = 'block';
        }

        childTypeEl.innerHTML = `
            <option value="unit">واحد مستقر</option>
            <option value="health_house">خانه بهداشت</option>
        `;

        const applyAllCheckbox = document.getElementById('applyAllCheckbox');
        if(applyAllCheckbox){
            applyAllCheckbox.checked = false;
        }

        if(parentSelect){
            parentSelect.disabled = false;
            parentSelect.value = '';
        }

        updateApplyAllVisibility();

    }

}

let inlineEditBusy = false;

document.addEventListener('dblclick', function(event){

    const item = event.target.closest('.editable-item');

    if(!item || inlineEditBusy){
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    if(item.classList.contains('editing')){
        return;
    }

    const id = item.getAttribute('data-id');
    const currentName = item.getAttribute('data-name') || '';
    const prefix = '├── ';

    item.classList.add('editing');
    item.innerHTML = '';

    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    item.appendChild(document.createTextNode(prefix));
    item.appendChild(input);

    input.focus();
    input.select();

    let closed = false;

    function closeEdit(savedName){

        if(closed){
            return;
        }

        closed = true;
        inlineEditBusy = false;
        item.classList.remove('editing');
        item.setAttribute('data-name', savedName);
        item.textContent = prefix + savedName;

    }

    function saveInline(){

        if(closed){
            return;
        }

        const newName = input.value.trim();

        if(!newName || newName === currentName){
            closeEdit(currentName);
            return;
        }

        closed = true;
        inlineEditBusy = true;

        const body = new URLSearchParams();
        body.append('inline_rename', '1');
        body.append('id', id);
        body.append('name', newName);

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString()
        })
        .then(response => response.json())
        .then(data => {
            if(data.ok){
                closeEdit(newName);
            }else{
                alert('ویرایش انجام نشد');
                closeEdit(currentName);
            }
        })
        .catch(() => {
            alert('خطا در ویرایش');
            closeEdit(currentName);
        });

    }

    input.addEventListener('keydown', function(e){
        if(e.key === 'Enter'){
            e.preventDefault();
            saveInline();
        }
        if(e.key === 'Escape'){
            e.preventDefault();
            closeEdit(currentName);
        }
    });

    input.addEventListener('blur', function(){
        setTimeout(saveInline, 120);
    });

    input.addEventListener('mousedown', function(e){
        e.stopPropagation();
    });

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
id="mainCategorySelect"
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

<div class="hint-text">
با انتخاب نوع مرکز، شماره ترتیب آزاد به‌صورت خودکار پیشنهاد می‌شود.
</div>

</div>

<input
type="hidden"
name="type"
id="mainType"
value="center">

<div
id="childCenterBox"
style="display:none;">

<div id="childTypeWrapper">

<select
name="type"
id="childType"
class="form-control">

<option value="unit">
واحد مستقر
</option>

<option value="health_house">
خانه بهداشت
</option>

</select>

</div>

<div id="applyAllBox" class="apply-all-box">

<label>
<input type="checkbox" name="apply_all_treatment" id="applyAllCheckbox" value="1" onchange="onApplyAllToggle()">
☑️ افزودن این واحد به تمام مراکز درمانی
</label>

<div class="hint-text">
اگر این واحد در همه مراکز درمانی تکرار می‌شود، تیک بزنید — نیازی به انتخاب تک‌تک مراکز نیست.
</div>

</div>

<select
name="parent_id"
id="parentSelect"
class="form-control">

<option value="">
مرکز بالادستی (در صورت عدم انتخاب «تمام مراکز»)
</option>

<?php foreach($centers as $center): ?>

<option
value="<?= $center['id'] ?>"
data-category="<?= $center['center_category'] ?? '' ?>">

<?= htmlspecialchars($center['name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<input
type="number"
name="sort_order"
id="sortOrderInput"
class="form-control"
placeholder="ترتیب نمایش (شماره آزاد)"
min="1"
value="1">

<div class="hint-text">
اولین شماره آزاد به‌صورت خودکار پر می‌شود؛ در صورت نیاز می‌توانید تغییر دهید.
</div>

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