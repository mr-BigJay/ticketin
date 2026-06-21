<?php

require '../includes/auth.php';

require '../includes/db.php';

$categories = $pdo->query("

    SELECT *

    FROM announcement_categories

    ORDER BY name ASC

")->fetchAll();

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$message = "";

$editData = null;

if(isset($_GET['edit'])){

    $edit_id =
    (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM announcements
        WHERE id=?
    ");

    $stmt->execute([$edit_id]);

    $editData =
    $stmt->fetch();

}

if(isset($_POST['submit'])){

    $edit_id =
    $_POST['edit_id'] ?? null;

    $title =
    trim($_POST['title']);

    $summary =
    trim($_POST['summary']);

    $content =
    trim($_POST['content']);

    $image =
    $_POST['old_image'] ?? '';

    if(
        isset($_FILES['image'])
        &&
        !empty($_FILES['image']['name'])
    ){

        $uploadDir =
        "/var/www/ticketin/uploads/";

        if(!file_exists($uploadDir)){

            mkdir(
                $uploadDir,
                0755,
                true
            );

        }

        $ext =
        pathinfo(
            $_FILES['image']['name'],
            PATHINFO_EXTENSION
        );

        $filename =
        time() .
        rand(1000,9999) .
        "." .
        $ext;

        $uploadPath =
        $uploadDir .
        $filename;

        if(
            move_uploaded_file(

                $_FILES['image']['tmp_name'],

                $uploadPath

            )
        ){

            $image =
            $filename;

        }

    }

    if($edit_id){

        $stmt = $pdo->prepare("
            UPDATE announcements
            SET
            title=?,
            summary=?,
            content=?,
            image=?
            WHERE id=?
        ");

        $stmt->execute([

            $title,
            $summary,
            $content,
            $image,
            $edit_id

        ]);

        header(
            "Location: /admin/announcement-list.php"
        );

        exit;

    }else{

        $stmt = $pdo->prepare("
            INSERT INTO announcements
            (
                title,
                summary,
                content,
                image,
                is_archived,
                created_at
            )
            VALUES
            (
                ?,?,?,?,0,NOW()
            )
        ");

        $stmt->execute([

            $title,
            $summary,
            $content,
            $image

        ]);

        $message =
        "اطلاعیه ثبت شد";

    }

}

$back_url = 'announcement-list.php';

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:1000px;

    margin:auto;

}

.page-title{

    font-size:28px;

    font-weight:bold;

    margin-bottom:20px;

}

.card{

    background:white;

    border-radius:24px;

    padding:24px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.preview-image{

    width:100%;

    max-height:320px;

    object-fit:cover;

    border-radius:18px;

    margin-bottom:18px;

}

</style>

<div class="page-box">

<div class="page-title">

<?= $editData ? '✏️ ویرایش اطلاعیه' : '📰 ثبت اطلاعیه جدید' ?>

</div>

<div class="card">

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<form
method="POST"
enctype="multipart/form-data">

<?php if($editData): ?>

<input
type="hidden"
name="edit_id"
value="<?= $editData['id'] ?>">

<?php endif; ?>

<input
type="hidden"
name="old_image"
value="<?= $editData['image'] ?? '' ?>">

<input
type="text"
name="title"
class="form-control"
placeholder="عنوان اطلاعیه"
required
value="<?= htmlspecialchars($editData['title'] ?? '') ?>">

<div style="margin-bottom:20px;">

<label
style="
display:block;
margin-bottom:10px;
font-weight:bold;
">

دسته‌بندی اطلاعیه

</label>

<div style="
border:1px solid #e2e8f0;
border-radius:18px;
padding:15px;
margin-bottom:20px;
background:#fff;
">

<?php foreach($categories as $cat): ?>

<label style="
display:flex;
align-items:center;
gap:10px;
padding:10px 0;
cursor:pointer;
border-bottom:1px solid #f1f5f9;
">

<input
type="checkbox"
name="categories[]"
value="<?= $cat['id'] ?>">

<span>

<?= htmlspecialchars(
$cat['name']
) ?>

</span>

</label>

<?php endforeach; ?>

</div>

<div style="
font-size:12px;
color:#64748b;
margin-top:8px;
">

امکان انتخاب چند دسته‌بندی وجود دارد

</div>

</div>

<textarea
name="summary"
class="form-control"
placeholder="خلاصه اطلاعیه"
required
style="min-height:120px;"><?= htmlspecialchars($editData['summary'] ?? '') ?></textarea>

<textarea
name="content"
class="form-control"
placeholder="متن کامل اطلاعیه"
required
style="min-height:260px;"><?= htmlspecialchars($editData['content'] ?? '') ?></textarea>

<?php if(
    $editData
    &&
    !empty($editData['image'])
): ?>

<img
src="/uploads/<?= htmlspecialchars($editData['image']) ?>"
class="preview-image">

<?php endif; ?>

<input
type="file"
name="image"
class="form-control">

<button
type="submit"
name="submit"
class="btn-custom">

<?= $editData ? 'ذخیره تغییرات' : 'ثبت اطلاعیه' ?>

</button>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>
