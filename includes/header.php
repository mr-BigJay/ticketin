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

$persianTime = str_replace(
    ['0','1','2','3','4','5','6','7','8','9'],
    ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
    date('H:i')
);

$is_user_portal =
    empty($auth_page)
    &&
    (($_SESSION['role'] ?? '') !== 'admin');

$body_classes = [];

if(!empty($auth_page)){
    $body_classes[] = 'auth-page';
}

if($is_user_portal){
    $body_classes[] = 'user-portal';
}

$body_class_attr = $body_classes
    ? ' class="' . implode(' ', $body_classes) . '"'
    : '';

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

    min-height:100dvh;

}

body.auth-page{

    overflow-x:hidden;

}

body.auth-page .auth-container{

    min-height:100vh;

    min-height:100dvh;

    display:flex;

    flex-direction:column;

    justify-content:center;

    padding:10px 14px 14px;

}

body.auth-page .topbar{

    padding:10px 14px;

    margin-bottom:10px;

    border-radius:18px;

    flex-shrink:0;

}

body.auth-page .topbar::before{

    width:130px;

}

body.auth-page .topbar-logo-title{

    font-size:18px;

}

body.auth-page .topbar-logo-sub{

    font-size:11px;

    margin-top:2px;

    line-height:1.35;

}

body.auth-page .header-date-box{

    font-size:12px;

    line-height:22px;

    padding-right:28px;

}

body.auth-page .form-control{

    padding:12px 14px;

    margin-bottom:10px;

    border-radius:14px;

}

body.auth-page .btn-custom{

    padding:13px;

    border-radius:14px;

}

body.auth-page .alert{

    padding:10px 12px;

    margin-bottom:10px;

    font-size:13px;

    line-height:24px;

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

    display:flex;

    align-items:center;

    gap:8px;

}

.topbar-logo-icon{

    width:22px;

    height:22px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

}

.topbar-logo-icon svg{

    width:22px;

    height:22px;

    display:block;

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

.user-avatar{

    width:34px;

    height:34px;

    border-radius:50%;

    background:rgba(255,255,255,0.22);

    border:2px solid rgba(255,255,255,0.35);

    color:white;

    display:none;

    align-items:center;

    justify-content:center;

    font-size:14px;

    font-weight:800;

    flex-shrink:0;

}

/* User portal header - Design B */

body.user-portal .topbar{

    background:linear-gradient(
        135deg,
        #0284c7 0%,
        #0369a1 52%,
        #0ea5e9 100%
    );

    border:none;

    box-shadow:0 10px 28px rgba(2,132,199,.22);

}

body.user-portal .topbar::before,
body.user-portal .topbar::after{

    display:none;

}

body.user-portal .topbar-logo-title{

    color:white;

}

body.user-portal .topbar-logo-sub{

    color:rgba(255,255,255,0.92);

}

body.user-portal .header-date-box{

    background:rgba(255,255,255,0.14);

    backdrop-filter:blur(8px);

    border:1px solid rgba(255,255,255,0.22);

    border-radius:14px;

    padding:10px 16px;

    color:white;

}

body.user-portal .header-date-box div:first-child{

    color:white;

}

body.user-portal .user-avatar{

    display:flex;

}

body.user-portal .user-box{

    gap:8px;

    max-width:190px;

}

body.user-portal .user-name{

    background:rgba(255,255,255,0.16);

    color:white;

    border:1px solid rgba(255,255,255,0.24);

    font-size:12px;

    line-height:1.35;

    white-space:normal;

    word-break:break-word;

    text-align:center;

    max-width:130px;

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

    body.user-portal .container{

        padding:12px 12px 16px;

    }

    body.user-portal .topbar{

        border-radius:20px;

        padding:12px 14px;

        min-height:auto;

        display:grid;

        grid-template-columns:minmax(0,1fr) auto minmax(0,1.1fr);

        align-items:center;

        gap:8px;

        margin-bottom:16px;

    }

    body.user-portal .topbar-logo{

        position:static;

        transform:none;

        grid-column:1;

        justify-self:start;

        align-items:flex-start;

    }

    body.user-portal .topbar-logo-title{

        font-size:18px;

        gap:6px;

    }

    body.user-portal .topbar-logo-icon,
    body.user-portal .topbar-logo-icon svg{

        width:18px;

        height:18px;

    }

    body.user-portal .topbar-logo-sub{

        display:none;

    }

    body.user-portal .header-date-box{

        grid-column:2;

        justify-self:center;

        margin:0;

        padding:8px 12px;

        font-size:11px;

        line-height:20px;

        min-width:108px;

        padding-right:0;

    }

    body.user-portal .header-date-box div:first-child{

        font-size:12px;

        font-weight:700;

    }

    body.user-portal .header-date-box .header-time-label{

        display:none;

    }

    body.user-portal .user-box{

        position:static;

        grid-column:3;

        justify-self:end;

        left:auto;

        top:auto;

        gap:6px;

        flex-direction:column;

        align-items:center;

        min-width:0;

    }

    body.user-portal .user-avatar{

        width:30px;

        height:30px;

        font-size:12px;

    }

    body.user-portal .user-name{

        padding:4px 8px;

        font-size:10px;

        line-height:1.35;

        font-weight:700;

        max-width:96px;

        white-space:normal;

        text-align:center;

        word-break:break-word;

    }

    body.user-portal .page-header-bar{

        margin-bottom:14px;

    }

    body.user-portal .page-header-title{

        font-size:19px;

    }

    body.user-portal .back-btn-top{

        background:white;

        font-size:13px;

        padding:7px 12px;

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

<body<?= $body_class_attr ?>>

<div class="container<?= !empty($auth_page) ? ' auth-container' : '' ?>">

<?php if(empty($auth_page)): ?>

<div class="topbar<?= $is_user_portal ? ' topbar-brand' : '' ?>">

<div class="topbar-logo">

<div class="topbar-logo-title">

تیکتین

<span class="topbar-logo-icon" aria-hidden="true">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v1H3V9Z"/><path d="M3 10h18v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-8Z"/><path d="M8 14h.01"/><path d="M12 14h4"/></svg>

</span>

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

<span class="header-time-label">ساعت </span><?= $persianTime ?>

</div>

</div>

<?php if(isset($_SESSION['user_id'])): ?>

<?php
$userDisplayName = trim($_SESSION['fullname'] ?? '');
$userInitial = $userDisplayName !== ''
    ? mb_substr($userDisplayName, 0, 1, 'UTF-8')
    : 'ک';
?>

<div class="user-box">

<div class="user-avatar" aria-hidden="true"><?= htmlspecialchars($userInitial) ?></div>

<div class="user-name">

<?= htmlspecialchars($userDisplayName) ?>

</div>

</div>

<?php endif; ?>

</div>

<?php endif; ?>

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