<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$back_url = 'index.php';

require '../includes/header.php';

?>

<div class="page-box">

<style>

.page-box{

    max-width:1100px;

    margin:auto;

}

.page-title{

    font-size:28px;

    font-weight:bold;

    margin-bottom:25px;

}

.grid-menu{

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:18px;

}

.menu-card{

    background:white;

    border-radius:24px;

    padding:35px 25px;

    text-align:center;

    text-decoration:none;

    color:#222;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

    transition:.2s;

}

.menu-card:hover{

    transform:translateY(-4px);

}

.menu-icon{

    font-size:48px;

    margin-bottom:18px;

}

.menu-title{

    font-size:18px;

    font-weight:bold;

    margin-bottom:10px;

}

.menu-desc{

    color:#64748b;

    line-height:32px;

    font-size:14px;

}

@media(max-width:768px){

    .grid-menu{

        grid-template-columns:1fr;

    }

}

</style>

<div class="page-box">

<div class="page-title">

📰 مدیریت اطلاعیه ها

</div>

<div class="grid-menu">

<a
href="announcement-create.php"
class="menu-card">

<div class="menu-icon">

➕

</div>

<div class="menu-title">

ثبت اطلاعیه جدید

</div>

<div class="menu-desc">

ایجاد اطلاعیه جدید همراه با تصویر، دسته بندی و متن کامل

</div>

</a>

<a
href="announcement-list.php"
class="menu-card">

<div class="menu-icon">

🗂

</div>

<div class="menu-title">

لیست اطلاعیه ها

</div>

<div class="menu-desc">

مشاهده، جستجو، ویرایش و حذف اطلاعیه ها

</div>

</a>

<a
href="announcement-categories.php"
class="menu-card">

<div class="menu-icon">

📂

</div>

<div class="menu-title">

دسته بندی اطلاعیه

</div>

<div class="menu-desc">

مدیریت دسته بندی ها و زیرمجموعه های اطلاعیه

</div>

</a>

<a
href="announcement-archive.php"
class="menu-card">

<div class="menu-icon">

🗃

</div>

<div class="menu-title">

بایگانی

</div>

<div class="menu-desc">

مشاهده اطلاعیه های بایگانی شده و بازگردانی آن ها

</div>

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>
