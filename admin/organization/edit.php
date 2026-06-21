<?php

require '../../includes/auth.php';
require '../../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM organization_nodes
    WHERE id=?
");

$stmt->execute([$id]);

$item = $stmt->fetch();

if(!$item){

    die("یافت نشد");

}

$message = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $name = trim($_POST['name']);

    $sort_order = (int)$_POST['sort_order'];

    $stmt = $pdo->prepare("
        UPDATE organization_nodes
        SET
        name=?,
        sort_order=?
        WHERE id=?
    ");

    $stmt->execute([

        $name,
        $sort_order,
        $id

    ]);

    $message = "ویرایش شد";

    $stmt = $pdo->prepare("
        SELECT *
        FROM organization_nodes
        WHERE id=?
    ");

    $stmt->execute([$id]);

    $item = $stmt->fetch();

}

$back_url = 'index.php';

include '../../includes/header.php';

?>

<style>

.page-box{

    max-width:600px;

    margin:auto;

}

.card{

    background:white;

    border-radius:22px;

    padding:22px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.info-box{

    background:#f3f4f6;

    padding:14px;

    border-radius:14px;

    margin-bottom:20px;

    color:#444;

    line-height:28px;

}

</style>

<div class="page-box">

<div class="card">

<h2 style="margin-bottom:20px;">

✏️ ویرایش ساختار

</h2>

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<div class="info-box">

نوع:

<?php

if($item['type'] == 'center'){

    echo "مرکز";

}elseif($item['type'] == 'health_house'){

    echo "خانه بهداشت";

}else{

    echo "واحد";

}

?>

</div>

<form method="POST">

<input
type="text"
name="name"
class="form-control"
value="<?= htmlspecialchars($item['name']) ?>"
required>

<input
type="number"
name="sort_order"
class="form-control"
value="<?= $item['sort_order'] ?>"
placeholder="ترتیب نمایش">

<button
type="submit"
class="btn-custom">

ذخیره تغییرات

</button>

</form>

</div>

</div>

<?php include '../../includes/footer.php'; ?>
