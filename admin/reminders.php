<?php

require '../includes/admin_auth.php';
require_once '../includes/pagination_helpers.php';

$reminderLimits = [30, 50, 100];
$listMonth = jalali_parse_month_request();
$listYear = $listMonth['year'];
$listMonthNum = $listMonth['month'];
$prevMonth = jalali_shift_month($listYear, $listMonthNum, -1);
$nextMonth = jalali_shift_month($listYear, $listMonthNum, 1);
$monthPrefixEn = jalali_month_prefix($listYear, $listMonthNum);
$monthPrefixFa = toPersianNumbers($monthPrefixEn);
$todayJalali = jalali_today_for_db();

$pagination = pagination_parse_request($reminderLimits, 30);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$filterQuery = [
    'jy' => $listYear,
    'jm' => $listMonthNum,
];

function reminders_build_redirect_url(
    int $year,
    int $month,
    int $page,
    int $limit
): string {
    return 'reminders.php?' . http_build_query([
        'jy' => $year,
        'jm' => $month,
        'page' => $page,
        'per_page' => $limit,
    ]);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    if(isset($_POST['delete_id'])){

        $stmt = $pdo->prepare("DELETE FROM reminders WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_id']]);

        header('Location: ' . reminders_build_redirect_url($listYear, $listMonthNum, $page, $limit));
        exit;
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $date = toEnglishNumbers(trim((string)($_POST['reminder_date'] ?? '')));

    if($title !== '' && $date !== ''){

        if(!empty($_POST['edit_id'])){

            $editId = (int)$_POST['edit_id'];
            $existing = $pdo->prepare("SELECT reminder_date FROM reminders WHERE id = ?");
            $existing->execute([$editId]);
            $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

            if(
                $existingRow
                &&
                !reminder_is_expired((string)$existingRow['reminder_date'])
            ){
                $stmt = $pdo->prepare("
                    UPDATE reminders
                    SET title = ?, reminder_date = ?
                    WHERE id = ?
                ");

                $stmt->execute([$title, $date, $editId]);
            }

        }else{

            $stmt = $pdo->prepare("
                INSERT INTO reminders (title, reminder_date, created_by)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $title,
                $date,
                (int)$_SESSION['user_id'],
            ]);
        }
    }

    header('Location: ' . reminders_build_redirect_url($listYear, $listMonthNum, 1, $limit));
    exit;
}

$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM reminders
    WHERE reminder_date LIKE ?
       OR reminder_date LIKE ?
");
$countStmt->execute([
    $monthPrefixEn . '%',
    $monthPrefixFa . '%',
]);
$total = (int)$countStmt->fetchColumn();
$totalPages = pagination_total_pages($total, $limit);
$page = pagination_clamp_page($page, $totalPages);
$offset = ($page - 1) * $limit;

$listStmt = $pdo->prepare("
    SELECT *
    FROM reminders
    WHERE reminder_date LIKE ?
       OR reminder_date LIKE ?
    ORDER BY reminder_date ASC, id DESC
    LIMIT $limit OFFSET $offset
");
$listStmt->execute([
    $monthPrefixEn . '%',
    $monthPrefixFa . '%',
]);
$reminders = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$back_url = 'index.php';
$page_title = '⏰ مدیریت یادآوری‌ها';
$page_header_menu_type = 'action-menu';
$page_header_menu_label = 'منوی یادآوری‌ها';
$page_header_menu_items = [
    [
        'label' => 'ثبت یادآوری',
        'onclick' => 'openAddReminderModal()',
    ],
];

require '../includes/header.php';

?>

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css"/>

<style>
.reminder-page{
    max-width:900px;
    margin:auto;
}
.reminder-card{
    background:#fff;
    border-radius:24px;
    padding:22px;
    margin-bottom:20px;
    box-shadow:0 8px 30px rgba(15,23,42,.05);
    border:1px solid #eef2f7;
}
.month-nav{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:18px;
    padding:12px 14px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:18px;
}
.month-nav-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:40px;
    height:40px;
    border-radius:12px;
    border:1px solid #dbeafe;
    background:#fff;
    color:#0284c7;
    text-decoration:none;
    font-size:20px;
    font-weight:800;
    transition:.2s;
}
.month-nav-btn:hover{
    background:#eff6ff;
}
.month-nav-title{
    font-size:16px;
    font-weight:800;
    color:#0f172a;
    text-align:center;
}
.reminder-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:18px;
    padding:16px;
    margin-bottom:12px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
}
.reminder-item.is-expired{
    opacity:.78;
}
.reminder-body{
    flex:1;
    min-width:0;
}
.reminder-date{
    color:#0284c7;
    font-weight:800;
    margin-bottom:8px;
    font-size:14px;
}
.reminder-text{
    line-height:30px;
    color:#0f172a;
    font-weight:600;
    word-break:break-word;
}
.reminder-menu{
    position:relative;
    flex-shrink:0;
}
.menu-btn{
    width:40px;
    height:40px;
    border:none;
    border-radius:12px;
    background:#e2e8f0;
    color:#334155;
    font-size:20px;
    cursor:pointer;
    line-height:1;
}
.dropdown-menu{
    position:absolute;
    top:calc(100% + 6px);
    left:0;
    min-width:150px;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:14px;
    box-shadow:0 12px 30px rgba(15,23,42,.12);
    display:none;
    overflow:hidden;
    z-index:20;
}
.dropdown-menu.show{
    display:block;
}
.dropdown-menu button{
    display:block;
    width:100%;
    border:none;
    background:#fff;
    padding:12px 14px;
    text-align:right;
    font-family:inherit;
    font-size:13px;
    font-weight:700;
    color:#334155;
    cursor:pointer;
}
.dropdown-menu button:hover{
    background:#f8fafc;
}
.dropdown-menu button.delete-action{
    color:#dc2626;
}
.empty-box{
    text-align:center;
    padding:35px;
    color:#64748b;
    font-weight:700;
}
.modal-overlay{
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
.modal-overlay.show{
    display:flex;
}
.modal-box{
    width:100%;
    max-width:460px;
    background:#fff;
    border-radius:24px;
    padding:24px 22px;
    box-shadow:0 20px 50px rgba(15,23,42,.18);
    position:relative;
}
.modal-title{
    font-size:20px;
    font-weight:800;
    color:#0f172a;
    margin-bottom:18px;
    padding-left:36px;
}
.modal-close{
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
.form-control{
    width:100%;
    border:1px solid #dbeafe;
    border-radius:16px;
    padding:14px 16px;
    margin-bottom:14px;
    font-family:inherit;
    font-size:14px;
    outline:none;
    background:#fff;
}
.form-control:focus{
    border-color:#0284c7;
    box-shadow:0 0 0 4px rgba(2,132,199,.08);
}
.modal-actions{
    display:flex;
    gap:10px;
    margin-top:8px;
}
.modal-btn{
    flex:1;
    border:none;
    border-radius:14px;
    padding:13px 16px;
    font-family:inherit;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
}
.save-btn{
    background:linear-gradient(135deg,#0284c7,#06b6d4);
    color:#fff;
}
.cancel-btn{
    background:#f1f5f9;
    color:#64748b;
}
.delete-confirm{
    background:#ef4444;
    color:#fff;
}
.pwt-datepicker-container{
    z-index:1000001 !important;
    position:fixed !important;
}
.pwt-datepicker{
    font-family:inherit !important;
    border-radius:22px !important;
    overflow:hidden !important;
    box-shadow:0 20px 50px rgba(15,23,42,.18) !important;
}
.pwt-btn{
    border-radius:12px !important;
}
.pwt-calendar{
    direction:rtl !important;
}
</style>

<div class="reminder-page">

<div class="reminder-card">

<div class="month-nav">

<a
class="month-nav-btn"
href="<?= htmlspecialchars(pagination_build_url(array_merge($filterQuery, [
    'jy' => $prevMonth['year'],
    'jm' => $prevMonth['month'],
    'page' => 1,
    'per_page' => $limit,
])), ENT_QUOTES, 'UTF-8') ?>"
aria-label="ماه قبل">

‹

</a>

<div class="month-nav-title">

<?= htmlspecialchars(jalali_month_title($listYear, $listMonthNum), ENT_QUOTES, 'UTF-8') ?>

</div>

<a
class="month-nav-btn"
href="<?= htmlspecialchars(pagination_build_url(array_merge($filterQuery, [
    'jy' => $nextMonth['year'],
    'jm' => $nextMonth['month'],
    'page' => 1,
    'per_page' => $limit,
])), ENT_QUOTES, 'UTF-8') ?>"
aria-label="ماه بعد">

›

</a>

</div>

<?php if(count($reminders)): ?>

<?php foreach($reminders as $item): ?>
<?php
$itemId = (int)$item['id'];
$isExpired = reminder_is_expired((string)$item['reminder_date']);
?>

<div class="reminder-item<?= $isExpired ? ' is-expired' : '' ?>">

<div class="reminder-body">

<div class="reminder-date">

<?= htmlspecialchars(format_stored_jalali_date($item['reminder_date']), ENT_QUOTES, 'UTF-8') ?>

</div>

<div class="reminder-text">

<?= nl2br(htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8')) ?>

</div>

</div>

<div class="reminder-menu">

<button
type="button"
class="menu-btn"
onclick="toggleReminderMenu(<?= $itemId ?>)"
aria-label="عملیات یادآوری">

⋮

</button>

<div id="menu-<?= $itemId ?>" class="dropdown-menu">

<?php if(!$isExpired): ?>

<button
type="button"
onclick="openEditReminderModal(
<?= $itemId ?>,
<?= json_encode((string)$item['title'], JSON_UNESCAPED_UNICODE) ?>,
<?= json_encode(toEnglishNumbers((string)$item['reminder_date']), JSON_UNESCAPED_UNICODE) ?>
)">

✏️ ویرایش

</button>

<?php endif; ?>

<button
type="button"
class="delete-action"
onclick="openDeleteReminderModal(
<?= $itemId ?>,
<?= json_encode((string)$item['title'], JSON_UNESCAPED_UNICODE) ?>
)">

🗑 حذف

</button>

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
    $filterQuery,
    '',
    $reminderLimits
);
?>

<?php else: ?>

<div class="empty-box">

یادآوری‌ای برای این ماه ثبت نشده

</div>

<?php endif; ?>

</div>

</div>

<div id="addReminderModal" class="modal-overlay" aria-hidden="true">

<div class="modal-box" role="dialog" aria-modal="true">

<button type="button" class="modal-close" onclick="closeAddReminderModal()" aria-label="بستن">×</button>

<div class="modal-title">ثبت یادآوری</div>

<form method="POST">

<textarea
name="title"
class="form-control"
placeholder="متن یادآوری..."
required
rows="4"></textarea>

<input
type="text"
id="add_reminder_date"
name="reminder_date"
class="form-control"
placeholder="انتخاب تاریخ"
required
autocomplete="off">

<div class="modal-actions">

<button type="submit" class="modal-btn save-btn">ثبت یادآوری</button>
<button type="button" class="modal-btn cancel-btn" onclick="closeAddReminderModal()">انصراف</button>

</div>

</form>

</div>

</div>

<div id="editReminderModal" class="modal-overlay" aria-hidden="true">

<div class="modal-box" role="dialog" aria-modal="true">

<button type="button" class="modal-close" onclick="closeEditReminderModal()" aria-label="بستن">×</button>

<div class="modal-title">ویرایش یادآوری</div>

<form method="POST">

<input type="hidden" name="edit_id" id="edit_reminder_id">

<textarea
name="title"
id="edit_reminder_title"
class="form-control"
required
rows="4"></textarea>

<input
type="text"
id="edit_reminder_date"
name="reminder_date"
class="form-control"
required
autocomplete="off">

<div class="modal-actions">

<button type="submit" class="modal-btn save-btn">ذخیره تغییرات</button>
<button type="button" class="modal-btn cancel-btn" onclick="closeEditReminderModal()">انصراف</button>

</div>

</form>

</div>

</div>

<div id="deleteReminderModal" class="modal-overlay" aria-hidden="true">

<div class="modal-box" role="dialog" aria-modal="true">

<button type="button" class="modal-close" onclick="closeDeleteReminderModal()" aria-label="بستن">×</button>

<div class="modal-title">حذف یادآوری</div>

<p id="deleteReminderText" style="line-height:30px;color:#334155;font-weight:600;margin-bottom:18px;"></p>

<form method="POST">

<input type="hidden" name="delete_id" id="delete_reminder_id">

<div class="modal-actions">

<button type="submit" class="modal-btn delete-confirm">حذف</button>
<button type="button" class="modal-btn cancel-btn" onclick="closeDeleteReminderModal()">انصراف</button>

</div>

</form>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>

<script>
const addReminderModal = document.getElementById('addReminderModal');
const editReminderModal = document.getElementById('editReminderModal');
const deleteReminderModal = document.getElementById('deleteReminderModal');

const datepickerOptions = {
    format: 'YYYY/MM/DD',
    autoClose: true,
    initialValue: false,
    initialValueType: 'persian',
    calendar: {
        persian: {
            locale: 'fa'
        }
    },
    navigator: {
        scroll: {
            enabled: false
        }
    },
    toolbox: {
        calendarSwitch: {
            enabled: false
        }
    }
};

$(function(){
    $('#add_reminder_date').persianDatepicker(datepickerOptions);
    $('#edit_reminder_date').persianDatepicker(datepickerOptions);
});

function closePageHeaderDropdown(){
    const dropdown = document.getElementById('pageHeaderDropdown');
    const menuBtn = document.getElementById('pageHeaderMenuBtn');

    if(dropdown){
        dropdown.classList.remove('show');
    }

    if(menuBtn){
        menuBtn.setAttribute('aria-expanded', 'false');
    }
}

function closeAllReminderMenus(){
    document.querySelectorAll('.dropdown-menu').forEach(function(menu){
        menu.classList.remove('show');
    });
}

function toggleReminderMenu(id){
    closeAllReminderMenus();

    const menu = document.getElementById('menu-' + id);

    if(menu){
        menu.classList.toggle('show');
    }
}

document.addEventListener('click', function(event){
    if(!event.target.closest('.reminder-menu')){
        closeAllReminderMenus();
    }
});

function openModal(overlay){
    if(!overlay){
        return;
    }

    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
    closePageHeaderDropdown();
    closeAllReminderMenus();
}

function closeModal(overlay){
    if(!overlay){
        return;
    }

    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
}

function openAddReminderModal(){
    openModal(addReminderModal);
}

function closeAddReminderModal(){
    closeModal(addReminderModal);
}

function openEditReminderModal(id, title, date){
    document.getElementById('edit_reminder_id').value = id;
    document.getElementById('edit_reminder_title').value = title;
    document.getElementById('edit_reminder_date').value = date;
    openModal(editReminderModal);
}

function closeEditReminderModal(){
    closeModal(editReminderModal);
}

function openDeleteReminderModal(id, title){
    document.getElementById('delete_reminder_id').value = id;
    document.getElementById('deleteReminderText').textContent = 'آیا یادآوری «' + title + '» حذف شود؟';
    openModal(deleteReminderModal);
}

function closeDeleteReminderModal(){
    closeModal(deleteReminderModal);
}

[addReminderModal, editReminderModal, deleteReminderModal].forEach(function(overlay){
    if(!overlay){
        return;
    }

    overlay.addEventListener('click', function(event){
        if(event.target === overlay){
            closeModal(overlay);
        }
    });
});

document.addEventListener('keydown', function(event){
    if(event.key !== 'Escape'){
        return;
    }

    closeAddReminderModal();
    closeEditReminderModal();
    closeDeleteReminderModal();
});
</script>

<?php include '../includes/footer.php'; ?>
