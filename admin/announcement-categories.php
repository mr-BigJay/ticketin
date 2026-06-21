<?php

require '../includes/auth.php';
require '../includes/db.php';

if($_SESSION['role'] != 'admin'){

    die("دسترسی غیر مجاز");

}

$message = "";

if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM announcement_categories
        WHERE id=?
    ");

    $stmt->execute([$id]);

    header(
        "Location: announcement-categories.php"
    );

    exit;

}

$editMode = false;

$editItem = null;

if(isset($_GET['edit'])){

    $editMode = true;

    $id = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM announcement_categories
        WHERE id=?
    ");

    $stmt->execute([$id]);

    $editItem = $stmt->fetch();

}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $name =
    trim($_POST['name']);

    $parent_id =
    !empty($_POST['parent_id'])
    ? (int)$_POST['parent_id']
    : null;

    $sort_order =
    (int)$_POST['sort_order'];

    if($editMode){

        $stmt = $pdo->prepare("
            UPDATE announcement_categories
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
            $editItem['id']

        ]);

        header(
            "Location: announcement-categories.php"
        );

        exit;

    }else{

        $stmt = $pdo->prepare("
            INSERT INTO
            announcement_categories
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
            $sort_order

        ]);

        $message =
        "دسته بندی ثبت شد";

    }

}

$categories = $pdo->query("
    SELECT *
    FROM announcement_categories
    ORDER BY sort_order ASC,id ASC
")->fetchAll();

function renderTree($items,$parent=null){

    foreach($items as $item){

        if($item['parent_id'] == $parent){

            ?>

            <div class="tree-item">

                <div class="tree-content">

                    <div class="tree-title">

                        📂

                        <?= htmlspecialchars($item['name']) ?>

                    </div>

                    <div class="actions">

                        <a
                        href="?edit=<?= $item['id'] ?>"
                        class="btn btn-edit">

                        ویرایش

                        </a>

                        <a
                        href="?delete=<?= $item['id'] ?>"
                        class="btn btn-delete"
                        onclick="return confirm('حذف شود؟')">

                        حذف

                        </a>

                    </div>

                </div>

                <div class="children">

                    <?php

                    renderTree(
                        $items,
                        $item['id']
                    );

                    ?>

                </div>

            </div>

            <?php

        }

    }

}

require '../includes/header.php';

?>

<style>

.page-box{

    max-width:1000px;

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

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.tree-item{

    margin-bottom:12px;

}

.tree-content{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    flex-wrap:wrap;

}

.tree-title{

    font-weight:bold;

    font-size:15px;

}

.children{

    margin-right:30px;

    margin-top:10px;

}

.actions{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.btn{

    text-decoration:none;

    padding:8px 12px;

    border-radius:10px;

    color:white;

    font-size:13px;

    font-weight:bold;

}

.btn-edit{

    background:#2563eb;

}

.btn-delete{

    background:#ef4444;

}

.edit-badge{

    background:#f59e0b;

    color:white;

    padding:8px 14px;

    border-radius:12px;

    margin-bottom:15px;

    display:inline-block;

}

.back-box{

    margin-bottom:20px;

}

@media(max-width:768px){

    .tree-content{

        flex-direction:column;

        align-items:flex-start;

    }

}

</style>

<div class="page-box">

<div class="back-box">

<a
href="javascript:history.back()"
class="back-btn-top">

← بازگشت

</a>

</div>

<div class="page-title">

📂 دسته بندی اطلاعیه ها

</div>

<div class="card">

<?php if($editMode): ?>

<div class="edit-badge">

✏️ حالت ویرایش فعال است

</div>

<?php endif; ?>

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<form method="POST">

<input
type="text"
name="name"
class="form-control"
placeholder="نام دسته بندی"
required
value="<?= $editMode ? htmlspecialchars($editItem['name']) : '' ?>">

<select
name="parent_id"
class="form-control">

<option value="">

بدون زیر مجموعه

</option>

<?php foreach($categories as $cat): ?>

<option
value="<?= $cat['id'] ?>"

<?php

if(
$editMode &&
$editItem['parent_id'] ==
$cat['id']
){

echo 'selected';

}

?>

>

<?= htmlspecialchars($cat['name']) ?>

</option>

<?php endforeach; ?>

</select>

<input
type="number"
name="sort_order"
class="form-control"
placeholder="ترتیب نمایش"
value="<?= $editMode ? $editItem['sort_order'] : 0 ?>">

<button
type="submit"
class="btn-custom">

<?= $editMode ? 'ذخیره ویرایش' : 'ثبت دسته بندی' ?>

</button>

</form>

</div>

<div class="card">

<?php renderTree($categories); ?>

</div>

</div>

<?php include '../includes/footer.php'; ?>
