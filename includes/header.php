<?php

if(session_status() == PHP_SESSION_NONE){

    session_start();

}

date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/jalali.php';

$days = [

    'Saturday'   => 'شنبه',
    'Sunday'     => 'یکشنبه',
    'Monday'     => 'دوشنبه',
    'Tuesday'    => 'سه‌شنبه',
    'Wednesday'  => 'چهارشنبه',
    'Thursday'   => 'پنجشنبه',
    'Friday'     => 'جمعه'

];

$jDate = explode(
    ' ',
    jalali_date(
        date('Y-m-d H:i:s')
    )
)[0];

?>

<!DOCTYPE html>

<html lang="fa" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

سامانه پشتیبانی و ثبت تیکت

</title>

<link
href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700;800&display=swap"
rel="stylesheet">

<style>

*{

    margin:0;
    padding:0;
    box-sizing:border-box;

}

body{

    font-family:'Vazirmatn',sans-serif;

    background:#f4f7fb;

    color:#111827;

    min-height:100vh;

}

.container{

    width:100%;

    max-width:1400px;

    margin:auto;

    padding:20px;

}

/* Header */

.topbar{

    position:relative;

    overflow:hidden;

    background:#ffffff;

    border-radius:26px;

    padding:18px 24px;

    margin-bottom:25px;

    display:flex;

    align-items:center;

    justify-content:center;

    box-shadow:0 10px 35px rgba(15,23,42,0.06);

    border:1px solid #eef2f7;

}

.topbar::before{

    content:'';

    position:absolute;

    top:0;

    right:0;

    width:170px;

    height:100%;

    background:linear-gradient(
        135deg,
        #0284c7 0%,
        #06b6d4 55%,
        #67e8f9 100%
    );

    clip-path:polygon(
        35% 0,
        100% 0,
        100% 100%,
        0 100%
    );

}

.topbar::after{

    content:'';

    position:absolute;

    top:0;

    right:80px;

    width:70px;

    height:100%;

    background:rgba(255,255,255,0.10);

    transform:skewX(-35deg);

}

.topbar-logo{

    position:absolute;

    right:14px;

    top:50%;

    transform:translateY(-50%);

    z-index:3;

    display:flex;

    flex-direction:column;

    align-items:flex-end;

    justify-content:center;

    text-align:right;

    color:white;

}

.topbar-logo-title{

    font-size:22px;

    font-weight:800;

    line-height:1;

}

.topbar-logo-sub{

    font-size:13px;

    opacity:.95;

    margin-top:5px;

    font-weight:600;

}

.header-date-box{

    position:relative;

    z-index:2;

    text-align:center;

    line-height:30px;

    color:#334155;

    font-size:14px;

    font-weight:500;

    padding-right:40px;

}

.header-date-box div:first-child{

    font-size:15px;

    font-weight:bold;

    color:#0f172a;

}

.user-box{

    position:absolute;

    left:22px;

    display:flex;

    align-items:center;

    gap:10px;

    z-index:3;

}

.user-name{

    background:#eff6ff;

    color:#0369a1;

    padding:8px 14px;

    border-radius:14px;

    font-size:13px;

    font-weight:bold;

}

/* Cards */

.card{

    background:white;

    border-radius:26px;

    padding:24px;

    margin-bottom:20px;

    box-shadow:0 10px 35px rgba(15,23,42,0.05);

    border:1px solid #edf2f7;

}

/* Menu Cards */

.menu-card{

    position:relative;

    overflow:hidden;

    background:white;

    border-radius:24px;

    padding:24px;

    text-decoration:none;

    color:#111827;

    box-shadow:0 10px 30px rgba(15,23,42,0.05);

    transition:.25s;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    gap:12px;

    min-height:150px;

    border:1px solid #eef2f7;

    background-image:
    radial-gradient(
        rgba(255,255,255,.7) 1px,
        transparent 1px
    );

    background-size:18px 18px;

}

.menu-card::after{

    content:'';

    position:absolute;

    inset:0;

    background:
    linear-gradient(
        135deg,
        rgba(255,255,255,.08),
        transparent 45%
    );

    pointer-events:none;

}

.menu-card:hover{

    transform:translateY(-4px);

    box-shadow:0 15px 35px rgba(15,23,42,0.08);

}

.menu-icon{

    font-size:40px;

    position:relative;

    z-index:2;

}

.menu-title{

    font-size:15px;

    font-weight:700;

    text-align:center;

    position:relative;

    z-index:2;

}

/* Forms */

.form-control{

    width:100%;

    padding:15px 18px;

    border-radius:18px;

    border:1px solid #dbeafe;

    margin-bottom:15px;

    font-family:'Vazirmatn',sans-serif;

    font-size:14px;

    background:white;

    transition:.2s;

}

.form-control:focus{

    outline:none;

    border-color:#06b6d4;

    box-shadow:0 0 0 4px rgba(6,182,212,.08);

}

.btn-custom{

    width:100%;

    border:none;

    background:linear-gradient(
        135deg,
        #0284c7,
        #06b6d4
    );

    color:white;

    padding:15px;

    border-radius:18px;

    font-size:15px;

    cursor:pointer;

    font-family:'Vazirmatn',sans-serif;

    font-weight:bold;

    transition:.2s;

}

.btn-custom:hover{

    transform:translateY(-2px);

    opacity:.95;

}

/* Alerts */

.alert{

    padding:15px 18px;

    border-radius:16px;

    margin-bottom:15px;

    font-size:14px;

}

.alert-danger{

    background:#fee2e2;

    color:#991b1b;

}

.alert-success{

    background:#dcfce7;

    color:#166534;

}

/* Tables */

table{

    width:100%;

    border-collapse:collapse;

    overflow:hidden;

    border-radius:18px;

}

table tr{

    border-bottom:1px solid #eef2f7;

}

table th{

    background:#f8fafc;

    color:#334155;

    font-size:13px;

    padding:16px;

}

table td{

    padding:16px;

    font-size:14px;

    color:#475569;

}

/* Badges */

.badge{

    display:inline-block;

    padding:6px 12px;

    border-radius:999px;

    font-size:12px;

    font-weight:bold;

}

.badge-success{

    background:#dcfce7;

    color:#166534;

}

.badge-warning{

    background:#fef3c7;

    color:#92400e;

}

.badge-danger{

    background:#fee2e2;

    color:#991b1b;

}

/* Back button & page header */

.page-header-bar{

    display:flex;

    align-items:center;

    gap:14px;

    margin-bottom:20px;

    flex-wrap:wrap;

}

.back-btn-wrap{

    margin-bottom:0;

}

.page-header-title{

    font-size:22px;

    font-weight:800;

    color:#0f172a;

    margin:0;

}

.back-btn-top{

    display:inline-flex;

    align-items:center;

    gap:8px;

    padding:8px 16px;

    background:#ffffff;

    border:1px solid #e2e8f0;

    border-radius:12px;

    text-decoration:none;

    font-weight:700;

    color:#0f172a;

    transition:.2s;

}

.back-btn-top:hover{

    background:#f8fafc;

}

/* Mobile */

@media(max-width:768px){

    .container{

        padding:14px;

    }

    .topbar{

        padding:16px;

        min-height:95px;

    }

    .topbar::before{

        width:120px;

    }

    .topbar::after{

        right:55px;

        width:45px;

    }

    .topbar-logo{

        right:14px;

    }

    .topbar-logo-title{

        font-size:17px;

    }

    .topbar-logo-sub{

        font-size:11px;

    }

    .user-box{

        left:14px;

        top:14px;

    }

    .user-name{

        font-size:11px;

        padding:6px 10px;

    }

    .header-date-box{

        margin-top:18px;

        font-size:12px;

        line-height:24px;

        padding-right:20px;

    }

    .card{

        padding:18px;

        border-radius:22px;

    }

    .menu-card{

        min-height:130px;

        padding:18px;

    }

    table{

        display:block;

        overflow-x:auto;

        white-space:nowrap;

    }

}


</style>

</head>

<body>

<div class="container">

<div class="topbar">

<div class="topbar-logo">

<div class="topbar-logo-title">

تیکتین

</div>

<div class="topbar-logo-sub">

سازوکاری آنلاین
<br>
در پاسخگویی

</div>

</div>

<div class="header-date-box">

<div>

<?= $days[date('l')] ?>

<?= $jDate ?>

</div>

<div>

ساعت

<?= str_replace(

['0','1','2','3','4','5','6','7','8','9'],

['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],

date('H:i')

) ?>

</div>

</div>

<?php if(isset($_SESSION['user_id'])): ?>

<div class="user-box">

<div class="user-name">

<?= htmlspecialchars(
$_SESSION['fullname']
?? ''
) ?>

</div>

</div>

<?php endif; ?>

</div>

<?php if(!empty($back_url) || !empty($page_title)): ?>

<div class="page-header-bar">

<?php if(!empty($back_url)): ?>

<div class="back-btn-wrap">

<a
href="<?= htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8') ?>"
class="back-btn-top">

<?= htmlspecialchars($back_label ?? '← بازگشت', ENT_QUOTES, 'UTF-8') ?>

</a>

</div>

<?php endif; ?>

<?php if(!empty($page_title)): ?>

<h1 class="page-header-title">

<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>

</h1>

<?php endif; ?>

</div>

<?php endif; ?>