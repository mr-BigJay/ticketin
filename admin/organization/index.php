<?php

require '../../includes/auth.php';
require '../../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$message = "";
$search =
trim(
$_GET['search']
?? ''
);

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

<div class="card">

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

<div class="section-title">

🏢 واحد های ستادی

</div>

<?php if(count($units)): ?>

<?php foreach($units as $unit): ?>

<div class="item">

<div class="item-name">

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

<div class="item-name">

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

<div class="item-name">

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