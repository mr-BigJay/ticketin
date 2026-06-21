<?php

require 'includes/auth.php';
require 'includes/db.php';

$user_id =
$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id=?
");

$stmt->execute([$user_id]);

$user = $stmt->fetch();

$message = "";

if(isset($_GET['delete_rel'])){

    $rel_id =
    (int)$_GET['delete_rel'];

    $stmt = $pdo->prepare("
        DELETE FROM user_organization_rel
        WHERE
        id=?
        AND user_id=?
    ");

    $stmt->execute([

        $rel_id,
        $user_id

    ]);

    header("Location: profile.php");

    exit;

}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $job_title =
    trim($_POST['job_title']);

    $center_id =
    (int)$_POST['center_id'];

    $sub_items =
    $_POST['sub_items'] ?? [];

    if(
        !$job_title ||
        !$center_id
    ){

        $message =
        "فیلدهای الزامی را تکمیل کنید";

    }else{

        $stmt = $pdo->prepare("
            UPDATE users
            SET
            job_title=?
            WHERE id=?
        ");

        $stmt->execute([

            $job_title,
            $user_id

        ]);

        $_SESSION['job_title'] =
        $job_title;

        foreach($sub_items as $node_id){

            $check = $pdo->prepare("
                SELECT id
                FROM user_organization_rel
                WHERE
                user_id=?
                AND node_id=?
            ");

            $check->execute([

                $user_id,
                $node_id

            ]);

            if(!$check->fetch()){

                $stmt = $pdo->prepare("
                    INSERT INTO
                    user_organization_rel
                    (
                        user_id,
                        center_id,
                        node_id
                    )
                    VALUES
                    (
                        ?,?,?
                    )
                ");

                $stmt->execute([

                    $user_id,
                    $center_id,
                    $node_id

                ]);

            }

        }

        $message =
        "پروفایل بروزرسانی شد";

    }

}

$centers = $pdo->query("
    SELECT *
    FROM organization_nodes
    WHERE type='center'
    ORDER BY sort_order ASC,id ASC
")->fetchAll();

$userNodes = $pdo->prepare("
    SELECT

    user_organization_rel.id as rel_id,

    child.name as child_name,

    center.name as center_name,

    child.type as child_type

    FROM user_organization_rel

    LEFT JOIN organization_nodes child
    ON user_organization_rel.node_id =
    child.id

    LEFT JOIN organization_nodes center
    ON user_organization_rel.center_id =
    center.id

    WHERE user_organization_rel.user_id=?

    ORDER BY user_organization_rel.id DESC
");

$userNodes->execute([$user_id]);

$currentNodes =
$userNodes->fetchAll();

require 'includes/header.php';

?>

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

    border-radius:24px;

    padding:24px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.info-grid{

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:14px;

    margin-bottom:20px;

}

.info-item{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    line-height:34px;

}

.info-label{

    font-size:13px;

    color:#64748b;

}

.info-value{

    font-size:15px;

    font-weight:bold;

    color:#111827;

}

.plus-btn{

    width:100%;

    border:none;

    background:#2563eb;

    color:white;

    padding:16px;

    border-radius:18px;

    font-size:16px;

    cursor:pointer;

    margin-top:12px;

    margin-bottom:20px;

    font-family:'Vazirmatn',sans-serif;

}

.hidden{

    display:none;

}

.checkbox-wrapper{

    background:#f8fafc;

    border:1px solid #dbeafe;

    border-radius:16px;

    padding:14px;

    margin-bottom:15px;

}

.checkbox-item{

    display:block;

    padding:10px;

    border-bottom:1px solid #e2e8f0;

    font-size:14px;

}

.checkbox-item:last-child{

    border-bottom:none;

}

.current-units{

    margin-top:25px;

}

.unit-title{

    font-size:18px;

    font-weight:bold;

    margin-bottom:14px;

}

.unit-card{

    background:linear-gradient(
        135deg,
        #eff6ff,
        #dbeafe
    );

    border-radius:18px;

    padding:16px;

    margin-bottom:12px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

}

.unit-name{

    font-size:15px;

    font-weight:bold;

    color:#1e3a8a;

}

.dropdown{

    position:relative;

}

.dropdown-btn{

    width:42px;

    height:42px;

    border:none;

    border-radius:14px;

    background:white;

    cursor:pointer;

    font-size:20px;

    box-shadow:0 0 10px rgba(0,0,0,0.05);

}

.dropdown-menu{

    position:absolute;

    top:48px;

    left:0;

    background:white;

    border-radius:14px;

    min-width:130px;

    box-shadow:0 0 20px rgba(0,0,0,0.08);

    overflow:hidden;

    display:none;

    z-index:999;

}

.dropdown-menu a{

    display:block;

    padding:12px;

    text-decoration:none;

    color:#333;

    font-size:13px;

    border-bottom:1px solid #f1f5f9;

}

.dropdown-menu a:last-child{

    border-bottom:none;

}

.dropdown-menu a:hover{

    background:#f8fafc;

}

.show-dropdown{

    display:block;

}

@media(max-width:768px){

    .info-grid{

        grid-template-columns:1fr;

    }

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

👤 پروفایل کاربری

</div>

<div class="card">

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<div class="info-grid">

<div class="info-item">

<div class="info-label">

نام و نام خانوادگی

</div>

<div class="info-value">

<?= htmlspecialchars($user['fullname']) ?>

</div>

</div>

<div class="info-item">

<div class="info-label">

شماره موبایل

</div>

<div class="info-value">

<?= htmlspecialchars($user['mobile']) ?>

</div>

</div>

<div class="info-item">

<div class="info-label">

کد ملی

</div>

<div class="info-value">

<?= htmlspecialchars($user['national_code']) ?>

</div>

</div>

<div class="info-item">

<div class="info-label">

پست سازمانی

</div>

<div class="info-value">

<?= htmlspecialchars($user['job_title'] ?? '-') ?>

</div>

</div>

</div>

<button
type="button"
class="plus-btn"
id="showFormBtn">

➕ اضافه کردن محل خدمت

</button>

<form
method="POST"
id="serviceForm"
class="hidden">

<input
type="text"
name="job_title"
class="form-control"
placeholder="پست سازمانی"
required
value="<?= htmlspecialchars($user['job_title'] ?? '') ?>">

<select
name="center_id"
id="centerSelect"
class="form-control"
required>

<option value="">
انتخاب مرکز
</option>

<?php foreach($centers as $center): ?>

<option
value="<?= $center['id'] ?>">

<?= htmlspecialchars($center['name']) ?>

</option>

<?php endforeach; ?>

</select>

<select
name="sub_type"
id="subTypeSelect"
class="form-control">

<option value="">
انتخاب نوع زیر مجموعه
</option>

<option value="health_house">

خانه بهداشت

</option>

<option value="unit">

واحد مستقر در مرکز

</option>

</select>

<div
id="subItemsBox"
class="hidden">

<div
id="subItemsSelect"
class="checkbox-wrapper">

</div>

</div>

<button
type="submit"
class="btn-custom">

ذخیره تغییرات

</button>

</form>

<div class="current-units">

<div class="unit-title">

🏢 واحدهای ثبت شده

</div>

<?php if(count($currentNodes)): ?>

<?php foreach($currentNodes as $node): ?>

<div class="unit-card">

<div>

<div class="unit-name">

<?= htmlspecialchars($node['center_name']) ?>

-

<?= htmlspecialchars($node['child_name']) ?>

</div>

<div
style="
margin-top:6px;
font-size:13px;
color:#64748b;
">

<?php

if($node['child_type'] == 'unit'){

    echo 'واحد مستقر در مرکز';

}else{

    echo 'خانه بهداشت';

}

?>

</div>

</div>

<div class="dropdown">

<button
type="button"
class="dropdown-btn">

⋮

</button>

<div class="dropdown-menu">

<a
href="?edit_rel=<?= $node['rel_id'] ?>">

✏️ ویرایش

</a>

<a
href="?delete_rel=<?= $node['rel_id'] ?>"
onclick="return confirm('حذف شود؟')">

🗑 حذف

</a>

</div>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="info-item">

هیچ محل خدمتی ثبت نشده

</div>

<?php endif; ?>

</div>

</div>

</div>

<script>

document
.getElementById(
    'showFormBtn'
)
.addEventListener(
    'click',
    function(){

        document
        .getElementById(
            'serviceForm'
        )
        .classList
        .toggle('hidden');

    }
);

let centerSelect =
document.getElementById(
    'centerSelect'
);

let subTypeSelect =
document.getElementById(
    'subTypeSelect'
);

let subItemsBox =
document.getElementById(
    'subItemsBox'
);

let subItemsSelect =
document.getElementById(
    'subItemsSelect'
);

subTypeSelect.addEventListener(
    'change',
    function(){

        let centerId =
        centerSelect.value;

        let type =
        this.value;

        if(!centerId || !type){

            return;

        }

        fetch(
            'tickets.php?action=subs'
            + '&center_id=' + centerId
            + '&type=' + type
        )

        .then(response =>
            response.json()
        )

        .then(data => {

            subItemsSelect.innerHTML = '';

            data.forEach(item => {

                subItemsSelect.innerHTML +=

                '<label class="checkbox-item">' +

                '<input type="checkbox" ' +

                'name="sub_items[]" ' +

                'value="' + item.id + '"> '

                +

                item.name +

                '</label>';

            });

            subItemsBox
            .classList
            .remove('hidden');

        });

    }
);

document
.querySelectorAll(
    '.dropdown-btn'
)
.forEach(btn => {

    btn.addEventListener(
        'click',
        function(e){

            e.stopPropagation();

            document
            .querySelectorAll(
                '.dropdown-menu'
            )
            .forEach(menu => {

                if(
                    menu !==
                    this.nextElementSibling
                ){

                    menu.classList.remove(
                        'show-dropdown'
                    );

                }

            });

            this
            .nextElementSibling
            .classList
            .toggle(
                'show-dropdown'
            );

        }
    );

});

document.addEventListener(
    'click',
    function(){

        document
        .querySelectorAll(
            '.dropdown-menu'
        )
        .forEach(menu => {

            menu.classList.remove(
                'show-dropdown'
            );

        });

    }
);

</script>

<?php include 'includes/footer.php'; ?>
