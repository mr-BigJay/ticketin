<?php

require 'includes/auth.php';
require 'includes/db.php';
require_once 'includes/ticket_organization_helpers.php';

$user_id =
$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id=?
");

$stmt->execute([$user_id]);

$user = $stmt->fetch();

$userNodes = $pdo->prepare("
    SELECT

    user_organization_rel.id as rel_id,

    child.name as child_name,

    center.name as center_name,

    center.center_category as center_category,

    child.type as child_type

    FROM user_organization_rel

    LEFT JOIN organization_nodes child
    ON user_organization_rel.node_id =
    child.id

    LEFT JOIN organization_nodes center
    ON user_organization_rel.center_id =
    center.id

    WHERE user_organization_rel.user_id=?

    ORDER BY user_organization_rel.id DESC
");

$userNodes->execute([$user_id]);

$currentNodes =
$userNodes->fetchAll();

$back_url = 'dashboard.php';
$page_title = '👤 نمایش پروفایل';

require 'includes/header.php';

?>

<style>

.page-box{

    max-width:850px;

    margin:auto;

}

.card{

    background:white;

    border-radius:24px;

    padding:24px;

    box-shadow:0 0 20px rgba(0,0,0,0.05);

}

.info-grid{

    display:grid;

    grid-template-columns:1fr 1fr;

    gap:14px;

    margin-bottom:20px;

}

.info-item{

    background:#f8fafc;

    border-radius:18px;

    padding:16px;

    line-height:34px;

}

.info-label{

    font-size:13px;

    color:#64748b;

}

.info-value{

    font-size:15px;

    font-weight:bold;

    color:#111827;

}

.current-units{

    margin-top:25px;

}

.unit-title{

    font-size:18px;

    font-weight:bold;

    margin-bottom:14px;

}

.unit-card{

    background:linear-gradient(
        135deg,
        #eff6ff,
        #dbeafe
    );

    border-radius:18px;

    padding:16px;

    margin-bottom:12px;

}

.unit-name{

    font-size:15px;

    font-weight:bold;

    color:#1e3a8a;

}

@media(max-width:768px){

    .info-grid{

        grid-template-columns:1fr;

    }

}

</style>

<div class="page-box">

<div class="card">

<div class="info-grid">

<div class="info-item">

<div class="info-label">

نام و نام خانوادگی

</div>

<div class="info-value">

<?= htmlspecialchars($user['fullname']) ?>

</div>

</div>

<div class="info-item">

<div class="info-label">

شماره موبایل

</div>

<div class="info-value">

<?= htmlspecialchars($user['mobile']) ?>

</div>

</div>

<div class="info-item">

<div class="info-label">

کد ملی

</div>

<div class="info-value">

<?= htmlspecialchars($user['national_code']) ?>

</div>

</div>

<div class="info-item">

<div class="info-label">

پست سازمانی

</div>

<div class="info-value">

<?= htmlspecialchars($user['job_title'] ?? '-') ?>

</div>

</div>

</div>

<div class="current-units">

<div class="unit-title">

🏢 محل‌های خدمت

</div>

<?php if(count($currentNodes)): ?>

<?php foreach($currentNodes as $node): ?>

<div class="unit-card">

<div class="unit-name">

<?= htmlspecialchars(
    ticket_org_location_label($node),
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

<div
style="
margin-top:6px;
font-size:13px;
color:#64748b;
">

<?= htmlspecialchars(
    ticket_org_location_type_label($node),
    ENT_QUOTES,
    'UTF-8'
) ?>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="info-item">

هیچ محل خدمتی ثبت نشده

</div>

<?php endif; ?>

</div>

</div>

</div>

<?php include 'includes/footer.php'; ?>
