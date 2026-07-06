<?php

require 'includes/auth.php';
require 'includes/db.php';
require_once 'includes/ticket_helpers.php';
require_once 'includes/pagination_helpers.php';
require_once 'includes/ticket_status_helpers.php';

$user_id = (int)$_SESSION['user_id'];
$pagination = pagination_parse_request();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$search = trim($_GET['search'] ?? '');
$where = 'WHERE user_id=? AND ' . ticket_sql_closed_scope();
$params = [$user_id];

if($search){
    $where .= " AND (title LIKE ? OR tracking_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
$totalPages = pagination_total_pages($total, $limit);
$page = pagination_clamp_page($page, $totalPages);

$stmt = $pdo->prepare("
    SELECT *
    FROM tickets
    $where
    ORDER BY closed_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$closedTicketsFilterQuery = [];

if($search !== ''){
    $closedTicketsFilterQuery['search'] = $search;
}

$back_url = 'dashboard.php';
$page_title = '✅ تیکت‌های رفع شده';
$page_header_menu_type = 'list-search';
$page_header_menu_label = 'منوی تیکت‌های رفع شده';
$page_header_search_open = 'openClosedTicketsSearchModal';

require 'includes/header.php';

ticket_list_print_layout_styles();

?>

<style>

.ticket-page{

    max-width:950px;

    margin:auto;

}

.card{

    background:white;

    border-radius:24px;

    padding:22px;

    margin-bottom:20px;

    box-shadow:0 0 20px rgba(0,0,0,.05);

}

.ticket-card{

    background:#fff;

    border-radius:24px;

    padding:22px;

    margin-bottom:18px;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

.ticket-title-box{

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:16px;

    min-height:72px;

    display:flex;

    align-items:center;

    justify-content:flex-start;

    font-size:15px;

    font-weight:700;

    color:#0f172a;

    line-height:32px;

    margin-bottom:18px;

}

a.ticket-title-box{
    text-decoration:none;
    cursor:pointer;
    transition:background .2s,border-color .2s;
}

a.ticket-title-box:hover{
    background:#eff6ff;
    border-color:#bfdbfe;
}

.ticket-bottom{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:12px;

}

.ticket-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:6px;

    background:#f8fafc;

    color:#334155;

    text-decoration:none;

    padding:11px 18px;

    border-radius:14px;

    border:1px solid #e2e8f0;

    font-size:13px;

    font-weight:700;

    transition:.2s;

    flex-shrink:0;

    white-space:nowrap;

}

.ticket-btn:hover{

    background:#eff6ff;

    border-color:#bfdbfe;

    color:#0369a1;

    transform:translateY(-1px);

}

.empty-box{

    background:#fff;

    border-radius:24px;

    padding:30px;

    text-align:center;

    color:#64748b;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

.list-search-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.45);
    backdrop-filter:blur(8px);
    z-index:100000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.list-search-modal-overlay.show{
    display:flex;
}

.list-search-modal{
    width:100%;
    max-width:460px;
    background:#ffffff;
    border-radius:24px;
    padding:24px 22px;
    box-shadow:0 20px 50px rgba(15,23,42,.18);
    position:relative;
}

.list-search-modal-title{
    font-size:20px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:18px;
    padding-left:36px;
}

.list-search-modal-close{
    position:absolute;
    left:16px;
    top:16px;
    width:34px;
    height:34px;
    border:none;
    border-radius:12px;
    background:#f1f5f9;
    color:#64748b;
    font-size:22px;
    line-height:1;
    cursor:pointer;
}

.search-field-label{
    display:block;
    font-size:13px;
    font-weight:800;
    color:#334155;
    margin-bottom:8px;
}

.search-field-group{
    margin-bottom:14px;
}

@media(max-width:768px){

    .ticket-bottom{

        flex-direction:row;

        align-items:center;

    }

    .ticket-btn{

        width:auto;

    }

}

</style>

<div class="ticket-page ticket-list-page">

<div class="ticket-list-shell">

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<div class="ticket-card">

<?php ticket_render_top_bar($ticket, ['menu' => 'none', 'datetime_at' => 'closed_at']); ?>

<a
href="view-ticket.php?id=<?= (int)$ticket['id'] ?>"
class="ticket-title-box">

<?= htmlspecialchars((string)$ticket['title'], ENT_QUOTES, 'UTF-8') ?>

</a>

<div class="ticket-bottom">

<?php ticket_status_render_ticket_badges($ticket, 'user'); ?>

<a
href="view-ticket.php?id=<?= (int)$ticket['id'] ?>"
class="ticket-btn">

مشاهده و تغییر وضعیت

</a>

</div>

</div>

<?php endforeach; ?>

<?php
pagination_render_bar(
    $page,
    $limit,
    $total,
    $totalPages,
    $closedTicketsFilterQuery
);
?>

<?php else: ?>

<div class="empty-box">
تیکت بسته‌شده‌ای وجود ندارد
</div>

<?php endif; ?>

</div>

</div>

<div
class="list-search-modal-overlay"
id="closedTicketsSearchModalOverlay"
aria-hidden="true">

<div class="list-search-modal" role="dialog" aria-modal="true">

<button
type="button"
class="list-search-modal-close"
onclick="closeClosedTicketsSearchModal()"
aria-label="بستن">

×

</button>

<h2 class="list-search-modal-title">جستجوی تیکت‌های رفع شده</h2>

<form method="GET" id="closedTicketsSearchForm">

<div class="search-field-group">
<label class="search-field-label" for="closedTicketsSearchInput">شماره پیگیری یا عنوان</label>
<input
type="text"
id="closedTicketsSearchInput"
name="search"
class="form-control"
placeholder="جستجو بر اساس شماره پیگیری یا عنوان"
value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
</div>

<button type="submit" class="btn-custom">جستجو</button>

</form>

</div>

</div>

<script>

const closedTicketsSearchModalOverlay =
document.getElementById('closedTicketsSearchModalOverlay');

function closePageHeaderDropdown(){

    const dropdown =
    document.getElementById('pageHeaderDropdown');

    const menuBtn =
    document.getElementById('pageHeaderMenuBtn');

    if(dropdown){
        dropdown.classList.remove('show');
    }

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }

}

function closeClosedTicketsSearchModal(){

    if(!closedTicketsSearchModalOverlay){
        return;
    }

    closedTicketsSearchModalOverlay.classList.remove('show');
    closedTicketsSearchModalOverlay.setAttribute('aria-hidden', 'true');
    closePageHeaderDropdown();

}

function openClosedTicketsSearchModal(){

    if(!closedTicketsSearchModalOverlay){
        return;
    }

    closedTicketsSearchModalOverlay.classList.add('show');
    closedTicketsSearchModalOverlay.setAttribute('aria-hidden', 'false');
    closePageHeaderDropdown();

    const searchInput =
    document.getElementById('closedTicketsSearchInput');

    if(searchInput){
        searchInput.focus();
    }

}

if(closedTicketsSearchModalOverlay){

    closedTicketsSearchModalOverlay.addEventListener('click', function(event){

        if(event.target === closedTicketsSearchModalOverlay){
            closeClosedTicketsSearchModal();
        }

    });

}

document.addEventListener('keydown', function(event){

    if(
        event.key === 'Escape' &&
        closedTicketsSearchModalOverlay &&
        closedTicketsSearchModalOverlay.classList.contains('show')
    ){
        closeClosedTicketsSearchModal();
    }

});

</script>

<?php include 'includes/footer.php'; ?>
