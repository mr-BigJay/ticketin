<?php

require '../includes/admin_auth.php';
require_once '../includes/ticket_helpers.php';
require_once '../includes/pagination_helpers.php';

if(
isset($_GET['action'])
&&
isset($_GET['id'])
){

    $id = (int)$_GET['id'];

    if($_GET['action'] === 'delete'){

        admin_require_super();

        ticket_delete($pdo, $id);

        header('Location: tickets.php');
        exit;

    }

    if($_GET['action']=='close'){

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET
            status='closed',
            closed_at=NOW()
            WHERE id=?
        ");

        $stmt->execute([$id]);

    }

    if($_GET['action']=='pending'){

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET status='pending'
            WHERE id=?
        ");

        $stmt->execute([$id]);

    }

    header("Location: tickets.php");

    exit;

}

$pagination = pagination_parse_request();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$where = [];
$params = [];

$where[] = "t.status != 'closed'";

if(!empty($_GET['status'])){
    $where[] = "t.status=?";
    $params[] = $_GET['status'];
}

if(!empty($_GET['category'])){
    $where[] = "t.category=?";
    $params[] = $_GET['category'];
}

$searchQuery = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

if($searchQuery !== ''){
    $where[] = "(t.title LIKE ? OR u.fullname LIKE ? OR t.tracking_code LIKE ?)";
    $searchPattern = '%' . $searchQuery . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$whereSql = "WHERE " . implode(" AND ",$where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
");

$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
$totalPages = pagination_total_pages($total, $limit);
$page = pagination_clamp_page($page, $totalPages);

$stmt = $pdo->prepare("
    SELECT t.*, u.fullname
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$tickets = $stmt->fetchAll();

$categoryOptions = $pdo->query("
    SELECT DISTINCT category
    FROM tickets
    WHERE category IS NOT NULL
    ORDER BY category ASC
")->fetchAll();

$ticketsFilterQuery = [];

if($searchQuery !== ''){
    $ticketsFilterQuery['search'] = $searchQuery;
}

if($categoryFilter !== ''){
    $ticketsFilterQuery['category'] = $categoryFilter;
}

if($statusFilter !== ''){
    $ticketsFilterQuery['status'] = $statusFilter;
}

$back_url = 'index.php';
$page_title = '🎫 تیکت‌های جاری';
$page_header_menu_type = 'ticket-search';

require '../includes/header.php';
?>

<style>
.page-box{max-width:1100px;margin:auto;}
.page-title{font-size:26px;font-weight:800;margin-bottom:20px;}
.card{background:white;border-radius:24px;padding:22px;margin-bottom:20px;box-shadow:0 0 20px rgba(0,0,0,.05);}

.ticket-search-modal-overlay{
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

.ticket-search-modal-overlay.show{
    display:flex;
}

.ticket-search-modal{
    width:100%;
    max-width:460px;
    background:#ffffff;
    border-radius:24px;
    padding:24px 22px;
    box-shadow:0 20px 50px rgba(15,23,42,.18);
    position:relative;
}

.ticket-search-modal-title{
    font-size:20px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:18px;
    padding-left:36px;
}

.ticket-search-modal-close{
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


.ticket-title{
    font-size:15px;
    font-weight:800;
    line-height:28px;
    color:#0f172a;
    margin-bottom:10px;
}

.ticket-meta{
    color:#64748b;
    line-height:28px;
    font-size:13px;
}

.status{
    display:inline-block;
    padding:8px 14px;
    border-radius:30px;
    color:white;
    font-size:12px;
    margin-top:14px;
    font-weight:700;
}

.open{background:#2563eb;}
.pending{background:#f59e0b;}
.admin_reply{background:#0f766e;}
.user_reply{background:#7c3aed;}

.ticket-actions{margin-top:16px;}

.ticket-btn{
    display:block;
    width:100%;
    text-align:center;
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:white;
    text-decoration:none;
    padding:12px;
    border-radius:14px;
    font-size:13px;
    font-weight:700;
}

.empty-box{text-align:center;padding:35px;color:#777;}

@media(max-width:768px){
    .ticket-card{padding:14px;}
    .ticket-title{font-size:14px;line-height:26px;}
    .ticket-meta{font-size:12px;line-height:24px;}
    .ticket-btn{padding:11px;}
}


.menu-btn{

    position:relative;

    z-index:10001;

    border:none;

    background:none;

    font-size:24px;

    cursor:pointer;

}

.dropdown{

    position:relative;

}

.dropdown-menu{

    display:none;

    position:absolute;

    left:0;

    top:35px;

    background:white;

    min-width:180px;

    border-radius:12px;

    box-shadow:0 8px 30px rgba(0,0,0,.15);

    overflow:hidden;

    z-index:9999;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu.show{

    display:block;

}

.dropdown-menu a{

    display:block;

    padding:12px;

    color:#333;

    text-decoration:none;

}

.dropdown-menu a:hover{

    background:#f1f5f9;

}

.dropdown-menu a.delete-link{

    color:#dc2626;

}

.dropdown-menu a.delete-link:hover{

    background:#fef2f2;

}

.status-ticket,
.status-reply{

    display:inline-block;

    padding:6px 12px;

    border-radius:999px;

    color:#fff;

    font-size:12px;

    font-weight:700;

    margin-left:6px;

}

.open{
    background:#2563eb;
}

.pending{
    background:#f59e0b;
}

.closed{
    background:#111827;
}

.admin_reply{
    background:#16a34a;
}

.user_reply{
    background:#dc2626;
}
.ticket-row{

    position:relative;

    background:#fff;

    border-radius:24px;

    padding:22px;

    margin-bottom:16px;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

.ticket-menu{

    position:absolute;

    top:12px;

    left:12px;

    z-index:10000;

}

.ticket-top{

    display:flex;

    justify-content:center;

    align-items:center;

    gap:14px;

    flex-wrap:wrap;

    margin-bottom:16px;

    color:#64748b;

    font-size:13px;

}

.tracking-code{

    background:#eff6ff;

    color:#2563eb;

    border:1px solid #bfdbfe;

    border-radius:999px;

    padding:6px 14px;

    font-weight:800;

}

.ticket-title-box{

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:18px;

    padding:16px;

    min-height:72px;

    margin-bottom:16px;

    font-weight:700;

    line-height:30px;

}

.ticket-bottom{

    display:flex;

    justify-content:space-between;

    align-items:center;

}

.ticket-statuses{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

.ticket-user{

    font-weight:700;

    color:#334155;

}

.user-badge{

    display:inline-flex;

    align-items:center;

    gap:8px;

    padding:10px 18px;

    border-radius:999px;

    background:linear-gradient(
        180deg,
        #f8fafc,
        #eef6ff
    );

    border:1px solid #bfdbfe;

    box-shadow:
    inset 0 1px 0 rgba(255,255,255,.8),
    0 4px 12px rgba(37,99,235,.08);

    color:#111827;

    font-size:13px;

    font-weight:800;

    white-space:nowrap;

}

</style>

<div class="page-box">

<div class="card">

<?php if(count($tickets)): ?>

<?php foreach($tickets as $ticket): ?>

<div class="ticket-row">

    <div class="ticket-menu">

        <button
        type="button"
        class="menu-btn"
        onclick="toggleMenu(this)">

            ⋮

        </button>

        <div class="dropdown-menu">

            <a
            href="view-ticket.php?id=<?= $ticket['id'] ?>">

                پاسخ

            </a>

            <a
            href="?action=pending&id=<?= $ticket['id'] ?>">

                درحال بررسی

            </a>

            <a
            href="?action=close&id=<?= $ticket['id'] ?>">

                بستن تیکت

            </a>

            <?php if(admin_is_super()): ?>

            <a
            href="?action=delete&id=<?= $ticket['id'] ?>"
            class="delete-link"
            onclick="return confirm('آیا از حذف این تیکت اطمینان دارید؟ این عمل غیرقابل بازگشت است.');">

                حذف تیکت

            </a>

            <?php endif; ?>

        </div>

    </div>

    <div class="ticket-top">

        <span class="tracking-code">

            <?= $ticket['tracking_code'] ?>

        </span>

        <span>

            🕒 <?= fa_datetime($ticket['created_at']) ?>

        </span>

        <span>

            📂 <?= htmlspecialchars($ticket['category']) ?>

        </span>

    </div>

    <div class="ticket-title-box">

        <?= htmlspecialchars($ticket['title']) ?>

    </div>

    <?php

    $statusText = [

        'open'    => 'باز',
        'pending' => 'درحال بررسی',
        'closed'  => 'بسته'

    ];

    $replyText = [

        'admin_reply' => 'پاسخ ادمین',
        'user_reply'  => 'پاسخ کاربر'

    ];

    ?>

    <div class="ticket-bottom">

        <div class="ticket-statuses">

            <span
            class="status-ticket <?= $ticket['status'] ?>">

                <?= $statusText[$ticket['status']] ?? '-' ?>

            </span>

            <span
            class="status-reply <?= $ticket['last_reply_by'] ?>">

                <?= $replyText[$ticket['last_reply_by']] ?? '-' ?>

            </span>

        </div>

<div class="user-badge">

    👤

    <?= htmlspecialchars($ticket['fullname']) ?>

</div>

    </div>

</div>
<?php endforeach; ?>

<?php
pagination_render_bar(
    $page,
    $limit,
    $total,
    $totalPages,
    $ticketsFilterQuery
);
?>

<?php else: ?>

<div class="empty-box">
تیکت جاری وجود ندارد
</div>

<?php endif; ?>

</div>
</div>

<div
class="ticket-search-modal-overlay"
id="ticketSearchModalOverlay"
aria-hidden="true">

<div class="ticket-search-modal" role="dialog" aria-modal="true">

<button
type="button"
class="ticket-search-modal-close"
onclick="closeTicketSearchModal()"
aria-label="بستن">

×

</button>

<h2 class="ticket-search-modal-title">جستجوی تیکت‌ها</h2>

<form method="GET" id="ticketSearchForm">

<div class="search-field-group">
<label class="search-field-label" for="ticketSearchInput">عنوان، کاربر یا کد پیگیری</label>
<input
type="text"
id="ticketSearchInput"
name="search"
class="form-control"
placeholder="جستجوی عنوان، کاربر یا کد پیگیری"
value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
</div>

<div class="search-field-group">
<label class="search-field-label" for="ticketCategoryFilter">دسته‌بندی</label>
<select name="category" id="ticketCategoryFilter" class="form-control">
<option value="">همه دسته‌بندی‌ها</option>
<?php foreach($categoryOptions as $cat): ?>
<option
value="<?= htmlspecialchars($cat['category'], ENT_QUOTES, 'UTF-8') ?>"
<?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['category'], ENT_QUOTES, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="search-field-group">
<label class="search-field-label" for="ticketStatusFilter">وضعیت</label>
<select name="status" id="ticketStatusFilter" class="form-control">
<option value="">همه وضعیت‌ها</option>
<option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>باز</option>
<option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>درحال بررسی</option>
<option value="admin_reply" <?= $statusFilter === 'admin_reply' ? 'selected' : '' ?>>پاسخ ادمین</option>
<option value="user_reply" <?= $statusFilter === 'user_reply' ? 'selected' : '' ?>>پاسخ کاربر</option>
</select>
</div>

<button type="submit" class="btn-custom">جستجو</button>

</form>

</div>

</div>

<script>

const ticketSearchModalOverlay =
document.getElementById('ticketSearchModalOverlay');

function closeTicketSearchModal(){

    if(!ticketSearchModalOverlay){
        return;
    }

    ticketSearchModalOverlay.classList.remove('show');
    ticketSearchModalOverlay.setAttribute('aria-hidden', 'true');

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

function openTicketSearchModal(){

    if(!ticketSearchModalOverlay){
        return;
    }

    ticketSearchModalOverlay.classList.add('show');
    ticketSearchModalOverlay.setAttribute('aria-hidden', 'false');

    const searchInput =
    document.getElementById('ticketSearchInput');

    if(searchInput){
        searchInput.focus();
    }

}

if(ticketSearchModalOverlay){

    ticketSearchModalOverlay.addEventListener('click', function(event){

        if(event.target === ticketSearchModalOverlay){
            closeTicketSearchModal();
        }

    });

}

document.addEventListener('keydown', function(event){

    if(
        event.key === 'Escape' &&
        ticketSearchModalOverlay &&
        ticketSearchModalOverlay.classList.contains('show')
    ){
        closeTicketSearchModal();
    }

});

function toggleMenu(btn){

    const menu = btn.nextElementSibling;

    document
    .querySelectorAll('.dropdown-menu')
    .forEach(item => {

        if(item !== menu){

            item.classList.remove('show');

        }

    });

    menu.classList.toggle('show');

}

document.addEventListener('click', function(e){

    if(!e.target.closest('.dropdown-menu') &&
       !e.target.closest('.menu-btn')){

        document
        .querySelectorAll('.dropdown-menu')
        .forEach(menu => {

            menu.classList.remove('show');

        });

    }

});

</script>
<?php include '../includes/footer.php'; ?>
