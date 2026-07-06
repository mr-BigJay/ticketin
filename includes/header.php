<?php

// Canonical Ticketin header (Design B). Replace includes/header.php on deploy;
// do not keep legacy header copies elsewhere on the server.

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
    !empty($auth_page)
    ||
    empty($auth_page);

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

$headerHomeUrl = !empty($auth_page)
    ? '/login.php'
    : (
        (($_SESSION['role'] ?? '') === 'admin')
            ? '/admin/index.php'
            : '/dashboard.php'
    );

$topbar_guest = empty($_SESSION['user_id']);

$topbar_class = 'topbar';

if($is_user_portal){
    $topbar_class .= ' topbar-brand';
}

if($is_user_portal && $topbar_guest){
    $topbar_class .= ' topbar-guest';
}

$page_header_icon = '';
$page_header_text = '';

if(!empty($page_title)){

    $page_title_raw = trim($page_title);

    if(preg_match('/^(\p{Extended_Pictographic}+)\s*(.*)$/us', $page_title_raw, $page_title_parts)){

        $page_header_icon = $page_title_parts[1];
        $page_header_text = trim($page_title_parts[2]);

    }else{

        $page_header_text = $page_title_raw;

    }

    $dashboard_page_icons = [
        'new-ticket.php' => '🎫',
        'tickets.php' => '📂',
        'closed-tickets.php' => '✅',
        'announcements.php' => '📢',
        'announcement-view.php' => '📢',
        'profile.php' => '👤',
        'view-ticket.php' => '📂',
    ];

    $admin_page_icons = [
        'index.php' => '🏠',
        'departments.php' => '📂',
        'categories.php' => '📂',
        'tickets.php' => '🎫',
        'closed-tickets.php' => '✅',
        'view-ticket.php' => '🎫',
        'users.php' => '👥',
        'user-view.php' => '👤',
        'user-edit.php' => '✏️',
        'pending-users.php' => '📝',
        'announcements.php' => '📢',
        'announcement-list.php' => '📢',
        'announcement-create.php' => '📢',
        'announcement-categories.php' => '📂',
        'reminders.php' => '⏰',
        'trainings.php' => '🎓',
        'upload-settings.php' => '📤',
        'organization' => '🏥',
        'job-titles.php' => '🏷️',
        'admins.php' => '👑',
        'change-password.php' => '🔐',
    ];

    $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $is_admin_area = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;

    if($is_admin_area && isset($admin_page_icons[$current_script])){

        $page_header_icon = $admin_page_icons[$current_script];

    }elseif(isset($dashboard_page_icons[$current_script])){

        $page_header_icon = $dashboard_page_icons[$current_script];

    }

    if($page_header_text === ''){

        $page_header_text = $page_title_raw;

    }

}

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

<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
<link rel="apple-touch-icon" sizes="192x192" href="/assets/icons/icon-192.png">
<meta name="theme-color" content="#0284c7">

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

    background-color:#f4f7fb;

    background-image:url('/assets/bg-pattern.svg');

    background-repeat:repeat;

    background-size:420px 420px;

    background-position:center top;

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

    justify-content:flex-start;

    padding:20px;

}

body.auth-page.user-portal .auth-container{

    padding:20px;

}

body.auth-page .form-control{

    padding:12px 14px;

    margin-bottom:10px;

    border-radius:14px;

}

body.auth-page .input-icon-box,
body.auth-page .password-box{

    position:relative;

}

body.auth-page .input-icon-box .form-control,
body.auth-page .password-box .form-control{

    margin-bottom:0;

    padding-right:46px;

}

body.auth-page .password-box .form-control{

    padding-left:48px;

}

body.auth-page .input-field-icon{

    position:absolute;

    right:14px;

    top:50%;

    transform:translateY(-50%);

    z-index:2;

    color:#64748b;

    display:flex;

    align-items:center;

    justify-content:center;

    pointer-events:none;

}

body.auth-page .input-field-icon svg{

    width:20px;

    height:20px;

    display:block;

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

@font-face{

    font-family:'Digi Lalezar Plus';

    src:url('/assets/fonts/DIGI%20LALEZAR%20PLUS.TTF') format('truetype');

    font-weight:700;

    font-style:normal;

    font-display:swap;

}

body.auth-page .auth-title{

    font-family:'Digi Lalezar Plus','Vazirmatn',sans-serif;

    font-weight:700;

}

@media(max-width:768px){

    body.auth-page.user-portal .auth-container{

        padding:0 12px 16px;

    }

    body.user-portal .topbar.topbar-guest{

        grid-template-columns:minmax(0,1fr) auto;

    }

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

    text-decoration:none;

    cursor:pointer;

    transition:opacity .2s;

}

.topbar-logo:hover{

    opacity:.92;

}

.topbar-logo-title{

    font-size:25px;

    font-weight:800;

    line-height:1;

    display:flex;

    align-items:center;

    gap:8px;

    flex-direction:row;

}

.topbar-logo-icon{

    width:26px;

    height:16px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    flex-shrink:0;

}

.topbar-logo-icon svg{

    width:26px;

    height:16px;

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

.header-time-value{

    font-size:14px;

    font-weight:700;

    color:#334155;

    margin-top:2px;

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

body.user-portal .header-time-value{

    color:white;

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

    position:relative;

    display:flex;

    align-items:center;

    justify-content:center;

    min-height:52px;

    padding:6px 56px;

    margin-bottom:20px;

    border-radius:999px;

    background:#0284c7;

    box-shadow:0 8px 22px rgba(2,132,199,.18);

    border:none;

}

.page-header-bar.no-back{

    padding:6px 18px;

}

.page-header-bar.has-actions{

    padding-left:56px;

}

.page-header-actions{

    position:absolute;

    left:6px;

    top:50%;

    transform:translateY(-50%);

    z-index:3;

}

.page-header-menu-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    width:42px;

    height:42px;

    padding:0;

    background:#ffffff;

    border:none;

    border-radius:50%;

    font-size:24px;

    line-height:1;

    color:#0f172a;

    cursor:pointer;

    box-shadow:0 4px 12px rgba(15,23,42,.12);

    transition:.2s;

}

.page-header-menu-btn:hover{

    background:#f8fafc;

}

.page-header-dropdown{

    position:absolute;

    left:0;

    top:calc(100% + 8px);

    min-width:220px;

    background:#ffffff;

    border-radius:16px;

    overflow:hidden;

    box-shadow:0 12px 30px rgba(15,23,42,.16);

    border:1px solid #e2e8f0;

    display:none;

}

.page-header-dropdown.show{

    display:block;

}

.page-header-dropdown button{

    display:block;

    width:100%;

    padding:14px 16px;

    border:none;

    background:#ffffff;

    color:#0f172a;

    font-family:'Vazirmatn',sans-serif;

    font-size:14px;

    font-weight:700;

    text-align:right;

    cursor:pointer;

}

.page-header-dropdown button:hover{

    background:#f8fafc;

}

.page-header-title{

    position:relative;

    z-index:1;

    flex:0 1 auto;

    max-width:calc(100% - 20px);

    margin:0 auto;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    padding:4px 6px;

    background:transparent;

    border:none;

    box-shadow:none;

    font-size:17px;

    font-weight:800;

    color:#ffffff;

    line-height:1.35;

    text-align:center;

}

.page-header-icon{

    font-size:20px;

    line-height:1;

    flex-shrink:0;

}

.page-header-text{

    min-width:0;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;

}

.back-btn-top{

    position:absolute;

    right:6px;

    top:50%;

    transform:translateY(-50%);

    z-index:2;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    width:42px;

    height:42px;

    padding:0;

    background:#ffffff;

    border:none;

    border-radius:50%;

    text-decoration:none;

    font-weight:700;

    font-size:18px;

    line-height:1;

    color:#0f172a;

    box-shadow:0 4px 12px rgba(15,23,42,.12);

    transition:.2s;

    box-sizing:border-box;

}

.back-btn-top:hover{

    background:#f8fafc;

}

.header-date-box .header-time-value{

    display:inline;

}

.header-date-box .header-date-line{

    display:block;

}

/* Mobile */

@media(max-width:768px){

    .container{

        padding:14px;

    }

    body.user-portal .container{

        padding:0 12px 16px;

    }

    body.user-portal .topbar{

        border-radius:0 0 28px 28px;

        padding:16px 14px 18px;

        min-height:auto;

        display:grid;

        grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);

        align-items:center;

        gap:10px;

        margin:0 0 14px;

        background:linear-gradient(
            180deg,
            #0284c7 0%,
            #0369a1 48%,
            #0ea5e9 100%
        );

        box-shadow:0 12px 28px rgba(2,132,199,.24);

        border:none;

    }

    body.user-portal .topbar.topbar-guest{

        grid-template-columns:minmax(0,1fr) auto;

    }

    body.user-portal .topbar-logo{

        position:static;

        transform:none;

        grid-column:1;

        justify-self:start;

        align-items:flex-end;

    }

    body.user-portal .topbar-logo-title{

        font-size:19px;

        font-weight:800;

        gap:7px;

        color:white;

    }

    body.user-portal .topbar-logo-icon,
    body.user-portal .topbar-logo-icon svg{

        width:24px;

        height:15px;

    }

    body.user-portal .topbar-logo-sub{

        display:none;

    }

    body.user-portal .header-date-box{

        grid-column:2;

        justify-self:center;

        margin:0;

        padding:10px 14px 8px;

        min-width:118px;

        padding-right:14px;

        text-align:center;

    }

    body.user-portal .header-date-line{

        font-size:11px;

        font-weight:600;

        line-height:1.35;

        color:rgba(255,255,255,0.95);

    }

    body.user-portal .header-date-box div:first-child{

        font-size:11px;

        font-weight:600;

        color:rgba(255,255,255,0.95);

    }

    body.user-portal .header-time-value{

        display:block;

        font-size:22px;

        font-weight:800;

        line-height:1.1;

        color:white;

        margin-top:4px;

        letter-spacing:.5px;

    }

    body.user-portal .header-time-label{

        display:none;

    }

    body.user-portal .user-box{

        position:static;

        grid-column:3;

        justify-self:end;

        left:auto;

        top:auto;

        flex-direction:row;

        align-items:center;

        gap:0;

        min-width:0;

        max-width:none;

        background:#ffffff;

        border-radius:999px;

        padding:6px 12px;

        box-shadow:0 4px 14px rgba(15,23,42,.12);

    }

    body.user-portal .user-name{

        background:transparent;

        border:none;

        color:#0f172a;

        padding:0;

        font-size:11px;

        line-height:1.35;

        font-weight:700;

        white-space:normal;

        word-break:break-word;

        text-align:right;

    }

    body.user-portal .page-header-bar{

        margin-bottom:14px;

        min-height:48px;

        padding:5px 50px;

    }

    body.user-portal .page-header-bar.no-back{

        padding:5px 14px;

    }

    body.user-portal .page-header-title{

        font-size:15px;

        padding:4px 4px;

        gap:6px;

    }

    body.user-portal .page-header-icon{

        font-size:18px;

    }

    body.user-portal .back-btn-top{

        width:38px;

        height:38px;

        right:5px;

        font-size:16px;

    }

    body:not(.user-portal) .topbar{

        padding:16px;

        min-height:95px;

    }

    body:not(.user-portal) .topbar::before{

        width:120px;

    }

    body:not(.user-portal) .topbar::after{

        right:55px;

        width:45px;

    }

    body:not(.user-portal) .topbar-logo{

        right:14px;

    }

    body:not(.user-portal) .topbar-logo-title{

        font-size:19px;

    }

    body:not(.user-portal) .topbar-logo-sub{

        font-size:11px;

    }

    body:not(.user-portal) .user-box{

        left:14px;

        top:14px;

    }

    body:not(.user-portal) .user-name{

        font-size:11px;

        padding:6px 10px;

    }

    body:not(.user-portal) .header-date-box{

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

<?php if(!empty($admin_pwa_enabled)): ?>
<?php require __DIR__ . '/admin_pwa_head.php'; ?>
<?php endif; ?>

</head>

<body<?= $body_class_attr ?>>

<div class="container<?= !empty($auth_page) ? ' auth-container' : '' ?>">

<div class="<?= htmlspecialchars($topbar_class, ENT_QUOTES, 'UTF-8') ?>">

<a
href="<?= htmlspecialchars($headerHomeUrl, ENT_QUOTES, 'UTF-8') ?>"
class="topbar-logo"
aria-label="بازگشت به داشبورد">

<div class="topbar-logo-title">

<span class="topbar-logo-icon" aria-hidden="true">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 28" fill="currentColor"><path d="M12 4H34C36.2 4 38 5.8 38 8V9.3C36.3 9.8 35 11.3 35 13.1C35 14.9 36.3 16.4 38 16.9V20C38 22.2 36.2 24 34 24H12C9.8 24 8 22.2 8 20V16.9C9.7 16.4 11 14.9 11 13.1C11 11.3 9.7 9.8 8 9.3V8C8 5.8 9.8 4 12 4ZM30.5 9.8C30.1 9.8 29.8 10.2 29.8 10.6V11.4C29.8 11.8 30.1 12.2 30.5 12.2H31.5C31.9 12.2 32.2 11.8 32.2 11.4V10.6C32.2 10.2 31.9 9.8 31.5 9.8H30.5ZM30.5 14.8C30.1 14.8 29.8 15.2 29.8 15.6V16.4C29.8 16.8 30.1 17.2 30.5 17.2H31.5C31.9 17.2 32.2 16.8 32.2 16.4V15.6C32.2 15.2 31.9 14.8 31.5 14.8H30.5Z"/></svg>

</span>

تیکتین

</div>

<div class="topbar-logo-sub">

سازوکاری آنلاین
<br>
در پاسخگویی

</div>

</a>

<div class="header-date-box">

<div class="header-date-line">

<?= $days[date('l')] ?>

<?= $jDate ?>

</div>

<div class="header-time-value" id="headerLiveTime"><?= $persianTime ?></div>

</div>

<?php if(isset($_SESSION['user_id'])): ?>

<?php
$userDisplayName = trim($_SESSION['fullname'] ?? '');
?>

<div class="user-box">

<div class="user-name">

<?= htmlspecialchars($userDisplayName) ?>

</div>

</div>

<?php endif; ?>

</div>

<script>
(function(){
    const clockEl = document.getElementById('headerLiveTime');

    if(!clockEl){
        return;
    }

    const persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

    const toPersian = function(value){
        return String(value).replace(/\d/g, function(digit){
            return persianDigits[digit];
        });
    };

    const updateClock = function(){
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Tehran',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        }).formatToParts(new Date());

        const hour = parts.find(function(part){
            return part.type === 'hour';
        })?.value ?? '00';

        const minute = parts.find(function(part){
            return part.type === 'minute';
        })?.value ?? '00';

        clockEl.textContent = toPersian(hour + ':' + minute);
    };

    updateClock();
    setInterval(updateClock, 1000);
})();
</script>

<?php if(!empty($back_url) || !empty($page_title)): ?>

<div class="page-header-bar<?= empty($back_url) ? ' no-back' : '' ?><?= !empty($page_header_menu_type) ? ' has-actions' : '' ?>">

<?php if(($page_header_menu_type ?? '') === 'category'): ?>

<div class="page-header-actions">

<button
type="button"
class="page-header-menu-btn"
id="pageHeaderMenuBtn"
aria-label="منوی دسته‌بندی"
aria-expanded="false">

⋮

</button>

<div
class="page-header-dropdown"
id="pageHeaderDropdown">

<button
type="button"
onclick="openCategoryModal('create')">

ثبت دسته بندی

</button>

</div>

</div>

<?php elseif(in_array($page_header_menu_type ?? '', ['ticket-search', 'list-search'], true)): ?>

<div class="page-header-actions">

<button
type="button"
class="page-header-menu-btn"
id="pageHeaderMenuBtn"
aria-label="<?= htmlspecialchars($page_header_menu_label ?? 'منوی صفحه', ENT_QUOTES, 'UTF-8') ?>"
aria-expanded="false">

⋮

</button>

<div
class="page-header-dropdown"
id="pageHeaderDropdown">

<button
type="button"
onclick="<?= htmlspecialchars($page_header_search_open ?? 'openTicketSearchModal', ENT_QUOTES, 'UTF-8') ?>()">

جستجو

</button>

</div>

</div>

<?php elseif(($page_header_menu_type ?? '') === 'action-menu'): ?>

<div class="page-header-actions">

<button
type="button"
class="page-header-menu-btn"
id="pageHeaderMenuBtn"
aria-label="<?= htmlspecialchars($page_header_menu_label ?? 'منوی صفحه', ENT_QUOTES, 'UTF-8') ?>"
aria-expanded="false">

⋮

</button>

<div
class="page-header-dropdown"
id="pageHeaderDropdown">

<?php foreach(($page_header_menu_items ?? []) as $menuItem): ?>

<button
type="button"
onclick="<?= htmlspecialchars($menuItem['onclick'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

<?= htmlspecialchars($menuItem['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>

</button>

<?php endforeach; ?>

</div>

</div>

<?php endif; ?>

<?php if(!empty($back_url)): ?>

<a
href="<?= htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8') ?>"
class="back-btn-top"
aria-label="<?= htmlspecialchars($back_label ?? 'بازگشت', ENT_QUOTES, 'UTF-8') ?>">

→

</a>

<?php endif; ?>

<?php if(!empty($page_title)): ?>

<h1 class="page-header-title">

<?php if($page_header_icon !== ''): ?>

<span class="page-header-icon" aria-hidden="true"><?= $page_header_icon ?></span>

<?php endif; ?>

<span class="page-header-text"><?= htmlspecialchars($page_header_text, ENT_QUOTES, 'UTF-8') ?></span>

</h1>

<?php endif; ?>

</div>

<?php if(in_array($page_header_menu_type ?? '', ['category', 'ticket-search', 'list-search', 'action-menu'], true)): ?>

<script>

(function(){

    const menuBtn =
    document.getElementById('pageHeaderMenuBtn');

    const dropdown =
    document.getElementById('pageHeaderDropdown');

    if(!menuBtn || !dropdown){
        return;
    }

    menuBtn.addEventListener('click', function(event){

        event.stopPropagation();

        const isOpen =
        dropdown.classList.contains('show');

        dropdown.classList.toggle('show', !isOpen);
        menuBtn.setAttribute(
            'aria-expanded',
            isOpen ? 'false' : 'true'
        );

    });

    document.addEventListener('click', function(){

        dropdown.classList.remove('show');
        menuBtn.setAttribute('aria-expanded', 'false');

    });

})();

</script>

<?php endif; ?>

<?php endif; ?>