<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$message = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $name = trim($_POST['name']);

    $sort_order = (int)$_POST['sort_order'];

    if($name){

        $stmt = $pdo->prepare("
            INSERT INTO categories
            (name,sort_order)
            VALUES
            (?,?)
        ");

        $stmt->execute([

            $name,
            $sort_order

        ]);

        $message = "ثبت شد";

    }

}

$categories = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY sort_order ASC,id ASC
")->fetchAll();

$back_url = 'index.php';

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:800px;

    margin:auto;

}

.card{

    background:white;

    border-radius:22px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.category-item{

    background:#f8fafc;

    border-radius:14px;

    padding:14px;

    margin-bottom:10px;

    display:flex;

    justify-content:space-between;

    align-items:center;

}

</style>

<div class="page-box">

<h2 style="margin-bottom:20px;">

📂 دسته بندی ها

</h2>

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<div class="card">

<form method="POST">

<input
type="text"
name="name"
class="form-control"
placeholder="نام دسته بندی"
required>

<input
type="number"
name="sort_order"
class="form-control"
placeholder="ترتیب نمایش">

<button
type="submit"
class="btn-custom">

ثبت دسته بندی

</button>

</form>

</div>

<div class="card">

<?php foreach($categories as $category): ?>

<div class="category-item">

<div>

<?= $category['name'] ?>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<?php include '../includes/footer.php'; ?>
