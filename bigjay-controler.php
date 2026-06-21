<?php

session_start();

require 'includes/db.php';

$message = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $mobile = trim($_POST['mobile']);

    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE mobile=?
        AND role='admin'
    ");

    $stmt->execute([$mobile]);

    $user = $stmt->fetch();

    if($user){

        if(password_verify($password,$user['password'])){

            $_SESSION['user_id'] = $user['id'];

            $_SESSION['fullname'] = $user['fullname'];

            $_SESSION['role'] = $user['role'];

            header("Location: admin");

            exit;

        }else{

            $message = "رمز عبور اشتباه است";

        }

    }else{

        $message = "ادمین یافت نشد";

    }

}

include 'includes/header.php';

?>

<div class="auth-box">

<div class="title">

<h2>
🛠 ورود مدیریت
</h2>

<p class="text-muted">
پنل مدیریت سامانه تیکتینگ
</p>

</div>

<?php if($message): ?>

<div class="alert">

<?= $message ?>

</div>

<?php endif; ?>

<form method="POST">

<input
type="text"
name="mobile"
class="form-control"
placeholder="شماره موبایل"
required>

<input
type="password"
name="password"
class="form-control"
placeholder="رمز عبور"
required>

<button
type="submit"
class="btn-custom">

ورود به مدیریت

</button>

</form>

</div>

<?php include 'includes/footer.php'; ?>
