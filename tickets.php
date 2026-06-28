<?php

require 'includes/auth.php';
require 'includes/db.php';
require_once 'includes/ticket_helpers.php';
require_once 'includes/ticket_status_helpers.php';

$search = trim($_GET['search'] ?? '');
$user_id = (int)$_SESSION['user_id'];

if(isset($_GET['action']) && $_GET['action'] === 'subs'){

    $center_id = (int)$_GET['center_id'];
    $type = trim($_GET['type']);

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM organization_nodes
        WHERE parent_id=? AND type=?
        ORDER BY sort_order ASC, id ASC
    ");

    $stmt->execute([$center_id, $type]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;

}

$where = 'WHERE user_id=? AND ' . ticket_sql_current_scope();
$params = [$user_id];

if($search){
    $where .= " AND (title LIKE ? OR tracking_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$stmt = $pdo->prepare("
    SELECT *
    FROM tickets
    $where
    ORDER BY id DESC
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$back_url = 'dashboard.php';
$page_title = '🎫 تیکت‌های جاری';
$page_header_menu_type = 'list-search';
$page_header_menu_label = 'منوی تیکت‌ها';
$page_header_search_open = 'openUserTicketsSearchModal';

require 'includes/header.php';

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

.ticket-top{

    display:flex;

    justify-content:center;

    align-items:center;

    flex-wrap:wrap;

    gap:14px;

    margin-bottom:18px;

    color:#64748b;

    font-size:13px;

}

.tracking-code{

    background:#eff6ff;

    color:#1d4ed8;

    padding:8px 14px;

    border-radius:999px;

    font-size:14px;

    font-weight:800;

    border:1px solid #bfdbfe;

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

<div class="ticket-page">

<div class="card">

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<div class="ticket-card">

<div class="ticket-top">

<span class="tracking-code">
<?= htmlspecialchars((string)$ticket['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
</span>

<span>
📂 <?= htmlspecialchars((string)$ticket['category'], ENT_QUOTES, 'UTF-8') ?>
</span>

<span>
🕒 <?= fa_datetime($ticket['created_at']) ?>
</span>

</div>

<div class="ticket-title-box">
<?= htmlspecialchars((string)$ticket['title'], ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="ticket-bottom">

<?php ticket_status_render_ticket_badges($ticket, 'user'); ?>

<a
href="view-ticket.php?id=<?= (int)$ticket['id'] ?>"
class="ticket-btn">

مشاهده و پاسخ

</a>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-box">
تیکت جاری وجود ندارد
</div>

<?php endif; ?>

</div>

</div>

<div
class="list-search-modal-overlay"
id="userTicketsSearchModalOverlay"
aria-hidden="true">

<div class="list-search-modal" role="dialog" aria-modal="true">

<button
type="button"
class="list-search-modal-close"
onclick="closeUserTicketsSearchModal()"
aria-label="بستن">

×

</button>

<h2 class="list-search-modal-title">جستجوی تیکت‌ها</h2>

<form method="GET" id="userTicketsSearchForm">

<div class="search-field-group">
<label class="search-field-label" for="userTicketsSearchInput">شماره پیگیری یا عنوان</label>
<input
type="text"
id="userTicketsSearchInput"
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

const userTicketsSearchModalOverlay =
document.getElementById('userTicketsSearchModalOverlay');

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

function closeUserTicketsSearchModal(){

    if(!userTicketsSearchModalOverlay){
        return;
    }

    userTicketsSearchModalOverlay.classList.remove('show');
    userTicketsSearchModalOverlay.setAttribute('aria-hidden', 'true');
    closePageHeaderDropdown();

}

function openUserTicketsSearchModal(){

    if(!userTicketsSearchModalOverlay){
        return;
    }

    userTicketsSearchModalOverlay.classList.add('show');
    userTicketsSearchModalOverlay.setAttribute('aria-hidden', 'false');
    closePageHeaderDropdown();

    const searchInput =
    document.getElementById('userTicketsSearchInput');

    if(searchInput){
        searchInput.focus();
    }

}

if(userTicketsSearchModalOverlay){

    userTicketsSearchModalOverlay.addEventListener('click', function(event){

        if(event.target === userTicketsSearchModalOverlay){
            closeUserTicketsSearchModal();
        }

    });

}

document.addEventListener('keydown', function(event){

    if(
        event.key === 'Escape' &&
        userTicketsSearchModalOverlay &&
        userTicketsSearchModalOverlay.classList.contains('show')
    ){
        closeUserTicketsSearchModal();
    }

});

</script>

<?php include 'includes/footer.php'; ?>
