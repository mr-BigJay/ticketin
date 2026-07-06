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

.footer-content{

    position:relative;

    z-index:2;

    padding:34px 30px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    flex-wrap:wrap;

    color:white;

}

.footer-right{

    display:flex;

    flex-direction:column;

    gap:10px;

}

.footer-title{

    font-size:22px;

    font-weight:800;

    color:white;

}

.footer-subtitle{

    font-size:13px;

    color:rgba(255,255,255,.82);

    line-height:30px;

}

.footer-left{

    text-align:left;

}

.footer-badge{

    display:inline-flex;

    align-items:center;

    gap:10px;

    background:rgba(255,255,255,.12);

    backdrop-filter:blur(10px);

    color:white;

    padding:13px 18px;

    border-radius:18px;

    font-size:13px;

    font-weight:700;

    border:
    1px solid rgba(255,255,255,.10);

}

.footer-bottom{

    position:relative;

    z-index:2;

    padding:18px 30px;

    border-top:
    1px solid rgba(255,255,255,.10);

    display:flex;

    align-items:center;

    justify-content:space-between;

    flex-wrap:wrap;

    gap:10px;

}

.footer-copy{

    font-size:12px;

    color:rgba(255,255,255,.75);

}

.footer-links{

    display:flex;

    gap:14px;

    flex-wrap:wrap;

}

.footer-links a{

    text-decoration:none;

    font-size:12px;

    color:rgba(255,255,255,.85);

    transition:.2s;

}

.footer-links a:hover{

    color:white;

    opacity:.9;

}

@media(max-width:768px){

    .main-footer{

        border-radius:24px 24px 0 0;

    }

    .footer-content{

        padding:26px 18px;

        flex-direction:column;

        text-align:center;

    }

    .footer-right{

        align-items:center;

    }

    .footer-left{

        text-align:center;

    }

    .footer-bottom{

        padding:16px 18px;

        flex-direction:column;

        text-align:center;

    }

    .footer-links{

        justify-content:center;

    }

}

</style>

<footer class="main-footer">

<div class="footer-top"></div>

<div class="footer-content">

<div class="footer-right">

<div class="footer-title">

سامانه پشتیبانی IT

</div>

<div class="footer-subtitle">

<span style="font-size:18px;font-weight:700;display:block;margin-bottom:8px;">

سازوکاری آنلاین

</span>
جهت پیگیری مشکلات مرتبط به واحد فناوری اطلاعات
<br>
شبکه بهداشت و درمان رودسر

</div>

</div>

<div class="footer-left">

<div class="footer-badge">

🛡️ سامانه داخلی سازمانی

</div>

</div>

</div>

<div class="footer-bottom">

<div class="footer-copy">

© <?= date('Y') ?>

طراحی و توسعه سامانه توسط صادق جعفری و علیرضا محمدرضایی

</div>

</div>

</footer>

<?php if(!empty($admin_pwa_enabled)): ?>
<?php require __DIR__ . '/admin_pwa_foot.php'; ?>
<?php endif; ?>

</body>

</html>