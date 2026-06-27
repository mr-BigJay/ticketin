</div>

<style>

.main-footer{

    margin-top:35px;

    background:
    linear-gradient(
        135deg,
        #0284c7 0%,
        #0369a1 45%,
        #0ea5e9 100%
    );

    border-radius:30px 30px 0 0;

    overflow:hidden;

    box-shadow:
    0 -10px 35px rgba(2,132,199,.18);

    border-top:
    1px solid rgba(255,255,255,.10);

    position:relative;

}

.main-footer::before{

    content:'';

    position:absolute;

    top:-80px;

    left:-80px;

    width:220px;

    height:220px;

    border-radius:50%;

    background:
    rgba(255,255,255,.05);

}

.main-footer::after{

    content:'';

    position:absolute;

    bottom:-100px;

    right:-100px;

    width:260px;

    height:260px;

    border-radius:50%;

    background:
    rgba(255,255,255,.04);

}

.footer-top{

    height:6px;

    background:
    linear-gradient(
        90deg,
        rgba(255,255,255,.20),
        rgba(255,255,255,.05)
    );

}

.footer-inner{

    position:relative;

    z-index:2;

    padding:26px 24px 22px;

    text-align:center;

    color:white;

}

.footer-title{

    font-size:22px;

    font-weight:800;

    color:white;

    line-height:1.5;

}

.footer-brand{

    display:inline-block;

    font-size:22px;

    font-weight:800;

    color:white;

    text-decoration:none;

    line-height:1.5;

    margin-bottom:6px;

    transition:opacity .2s;

}

.footer-brand:hover{

    opacity:.88;

}

.footer-line{

    font-size:14px;

    color:rgba(255,255,255,.88);

    line-height:1.9;

}

.footer-line-accent{

    font-size:17px;

    font-weight:700;

    color:white;

}

.footer-divider{

    width:min(280px,70%);

    height:1px;

    margin:16px auto;

    background:rgba(255,255,255,.22);

}

.footer-copy{

    font-size:12px;

    color:rgba(255,255,255,.75);

    line-height:1.9;

}

@media(max-width:768px){

    .main-footer{

        border-radius:24px 24px 0 0;

    }

    .footer-inner{

        padding:22px 18px 18px;

    }

    .footer-title{

        font-size:20px;

    }

    .footer-brand{

        font-size:20px;

    }

    .footer-line{

        font-size:13px;

        line-height:1.85;

    }

    .footer-line-accent{

        font-size:16px;

    }

}

</style>

<?php
$footerDashboardUrl = (($_SESSION['role'] ?? '') === 'admin')
    ? '/admin/index.php'
    : '/dashboard.php';
?>

<footer class="main-footer">

<div class="footer-top"></div>

<div class="footer-inner">

<a href="<?= htmlspecialchars($footerDashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="footer-brand">تیکتین</a>

<div class="footer-title">سامانه پشتیبانی IT</div>

<div class="footer-line footer-line-accent">سازوکاری آنلاین</div>

<div class="footer-line">جهت پیگیری مشکلات مرتبط به واحد فناوری اطلاعات</div>

<div class="footer-line">شبکه بهداشت و درمان رودسر</div>

<div class="footer-divider"></div>

<div class="footer-copy">

طراحی و توسعه سامانه توسط صادق جعفری و علیرضا محمدرضایی

</div>

</div>

</footer>

</body>

</html>
