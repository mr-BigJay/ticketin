<?php

require 'includes/auth.php';
require 'includes/db.php';

// صفحه جاری
$page_title = '🎫 تیکت های جاری';
$back_url = 'dashboard.php';

// جستجو
$search = trim($_GET['search'] ?? '');
$where = "WHERE user_id=?";
$params = [$_SESSION['user_id']];

if($search){
    $where .= " AND (title LIKE ? OR tracking_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// پاسخ JSON برای AJAX
if(isset($_GET['action']) && $_GET['action'] == 'subs'){

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

// گرفتن تیکت‌های کاربر
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT *
    FROM tickets
    WHERE user_id=? AND status != 'closed'
    ORDER BY id DESC
");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll();

// بعد از تمام پردازش‌های PHP، هدر را اضافه کن
require 'includes/header.php';

?>

<style>
.page-box{

    max-width:950px;

    margin:auto;

}

.page-title{

    font-size:26px;

    font-weight:800;

    margin-bottom:20px;

    color:#0f172a;

}

/* کارت تیکت */

.ticket-card{

    background:#fff;

    border-radius:24px;

    padding:22px;

    margin-bottom:18px;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

/* ردیف اول */

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

/* شماره پیگیری */

.tracking-code{

    background:#eff6ff;

    color:#1d4ed8;

    padding:8px 14px;

    border-radius:999px;

    font-size:14px;

    font-weight:800;

    border:1px solid #bfdbfe;

}

/* عنوان */

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

/* ردیف سوم */

.ticket-bottom{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:12px;

}

.ticket-statuses{

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}

/* وضعیت ها */

.status{

    display:inline-block;

    padding:8px 14px;

    border-radius:999px;

    color:#fff;

    font-size:12px;

    font-weight:700;

}

/* وضعیت تیکت */

.open{

    background:#2563eb;

}

.pending{

    background:#f59e0b;

}

.closed{

    background:#111827;

}

/* آخرین پاسخ */

.admin_reply{

    background:#16a34a;

}

.user_reply{

    background:#dc2626;

}

/* دکمه مشاهده */

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

}

.ticket-btn:hover{

    background:#eff6ff;

    border-color:#bfdbfe;

    color:#0369a1;

    transform:translateY(-1px);

}

/* جستجو */

.search-box{

    display:flex;

    gap:10px;

    align-items:center;

    margin-bottom:20px;

}

.search-box .form-control{

    margin-bottom:0;

}

.search-box .btn-custom{

    width:auto;

    min-width:120px;

}

/* خالی */

.empty-box{

    background:#fff;

    border-radius:24px;

    padding:30px;

    text-align:center;

    color:#64748b;

    border:1px solid #eef2f7;

    box-shadow:0 8px 30px rgba(15,23,42,.05);

}

/* موبایل */

@media(max-width:768px){

    .ticket-bottom{

        flex-direction:column;

        align-items:stretch;

    }

    .ticket-btn{

        width:100%;

    }

    .ticket-statuses{

        justify-content:center;

    }

    .search-box{

        flex-direction:column;

    }

    .search-box .btn-custom{

        width:100%;

    }

}


</style>

<div class="ticket-box">

<div class="card">

<form method="GET" class="search-box">

    <input
    type="text"
    name="search"
    value="<?= htmlspecialchars($search) ?>"
    class="form-control"
    placeholder="جستجو بر اساس شماره پیگیری یا عنوان">

    <button
    type="submit"
    class="btn-custom">

        جستجو

    </button>

</form>

<?php if(count($tickets)): ?>

<?php

$statusText = [

    'open'    => 'باز',

    'pending' => 'درحال بررسی',

    'closed'  => 'بسته'

];

$replyText = [

    'admin_reply' => 'پاسخ ادمین',

    'user_reply'  => 'پاسخ شما'

];


?>
<?php foreach($tickets as $ticket): ?>

<div class="ticket-card">

    <!-- ردیف اول -->

    <div class="ticket-top">

        <span class="tracking-code">

            #<?= $ticket['tracking_code'] ?>

        </span>

        <span>

            📂 <?= htmlspecialchars($ticket['category']) ?>

        </span>

        <span>

            🕒 <?= fa_datetime($ticket['created_at']) ?>

        </span>

    </div>

    <!-- ردیف دوم -->

    <div class="ticket-title-box">

        <?= htmlspecialchars($ticket['title']) ?>

    </div>

    <!-- ردیف سوم -->

    <div class="ticket-bottom">

        <div class="ticket-statuses">

            <span
            class="status status-ticket <?= $ticket['status'] ?>">

                <?= $statusText[$ticket['status']] ?? '-' ?>

            </span>

            <span
            class="status status-reply <?= $ticket['last_reply_by'] ?>">

                <?= $replyText[$ticket['last_reply_by']] ?? '-' ?>

            </span>

        </div>

        <a
        href="view-ticket.php?id=<?= $ticket['id'] ?>"
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

<?php include 'includes/footer.php'; ?>
