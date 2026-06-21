<?php
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/header.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// آمار تیکت‌ها
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=?");
$stmt->execute([$user_id]);
$totalTickets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status='open'");
$stmt->execute([$user_id]);
$openTickets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status='pending'");
$stmt->execute([$user_id]);
$pendingTickets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status='closed'");
$stmt->execute([$user_id]);
$closedTickets = $stmt->fetchColumn();

// بررسی نیاز به انتخاب محل خدمت
$stmt = $pdo->prepare("SELECT organization_node_id FROM users WHERE id=?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();
$needsDepartment = empty($userData['organization_node_id']);

// مراکز اصلی
$centers = $pdo->query("SELECT * FROM organization_nodes WHERE type='center' ORDER BY sort_order ASC, name ASC")->fetchAll();
?>

<div class="dashboard">

<div class="welcome-card">
    <div class="welcome-top">
        <div>
            <h2>👋 خوش آمدید</h2>
            <p><?= htmlspecialchars($_SESSION['fullname'] ?? 'کاربر عزیز') ?></p>
        </div>
        <div class="welcome-icon">🌟</div>
    </div>

    <div class="stats-grid">
        <div class="stat-box"><div class="stat-number"><?= $totalTickets ?></div><div class="stat-title">کل تیکت‌ها</div></div>
        <div class="stat-box"><div class="stat-number"><?= $openTickets ?></div><div class="stat-title">جاری</div></div>
        <div class="stat-box"><div class="stat-number"><?= $pendingTickets ?></div><div class="stat-title">درحال بررسی</div></div>
        <div class="stat-box"><div class="stat-number"><?= $closedTickets ?></div><div class="stat-title">حل شده</div></div>
    </div>
</div>

<!-- منو داشبورد: ۳ تایی روی دسکتاپ - ۲ تایی روی گوشی -->
<div class="grid-menu">
    <a href="new-ticket.php" class="menu-card"><div class="menu-icon">🎫</div><div class="menu-title">ثبت درخواست جدید</div></a>
    <a href="tickets.php" class="menu-card"><div class="menu-icon">📂</div><div class="menu-title">درخواست‌های جاری</div></a>
    <a href="closed-tickets.php" class="menu-card"><div class="menu-icon">✅</div><div class="menu-title">درخواست‌های حل شده</div></a>
    <a href="trainings.php" class="menu-card"><div class="menu-icon">🎓</div><div class="menu-title">آموزش‌ها</div></a>
    <a href="announcements.php" class="menu-card"><div class="menu-icon">📢</div><div class="menu-title">اطلاعیه‌ها</div></a>
    <a href="profile.php" class="menu-card"><div class="menu-icon">👤</div><div class="menu-title">ویرایش پروفایل</div></a>
</div>

<a href="logout.php" class="logout-btn">خروج از سامانه</a>

</div>

<!-- مودال انتخاب محل خدمت -->
<?php if($needsDepartment): ?>
<div id="departmentModal" class="modal-overlay show">
    <div class="modal-box">
        <div class="modal-title">انتخاب محل خدمت</div>
        <p style="color:#64748b;margin-bottom:20px;">محل خدمت خود را انتخاب کنید (امکان ثبت چندین محل وجود دارد)</p>

        <form method="POST" action="save-organization.php" id="orgForm">
            <input type="hidden" name="organization_nodes" id="selected_nodes" value="">

            <div id="selectedList" class="selected-list"></div>

            <div class="add-form">
                <select id="centerSelect" class="form-control" onchange="loadTypes()">
                    <option value="">انتخاب مرکز اصلی</option>
                    <?php foreach($centers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="typeSelect" class="form-control" onchange="loadUnits()" style="margin-top:10px;display:none;">
                    <option value="">نوع محل خدمت</option>
                    <option value="unit">واحد مستقر</option>
                    <option value="health_house">خانه بهداشت</option>
                </select>

                <select id="unitSelect" class="form-control" style="margin-top:10px;display:none;" onchange="enableAddButton()">
                    <option value="">انتخاب واحد / خانه بهداشت</option>
                </select>

                <button type="button" id="addBtn" class="btn-custom" onclick="addCurrentSelection()" style="margin-top:12px;display:none;" disabled>
                    + افزودن این محل خدمت
                </button>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="submitSelections()" class="modal-btn save-btn">تایید نهایی</button>
                <button type="button" onclick="window.location.href='logout.php'" class="modal-btn cancel-btn">خروج از سامانه</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
/* استایل‌های اصلی dashboard2 */
.dashboard { max-width:1100px; margin:auto; }

.welcome-card {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 50%, #0ea5e9 100%);
    color: white;
    padding: 28px;
    border-radius: 30px;
    margin-bottom: 24px;
    box-shadow: 0 15px 40px rgba(2,132,199,.18);
    position: relative;
    overflow: hidden;
}
.welcome-card::before {
    content: ''; position: absolute; top: -90px; left: -90px;
    width: 240px; height: 240px; border-radius: 50%;
    background: rgba(255,255,255,.05);
}
.welcome-top { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.welcome-card h2 { margin:0 0 10px; font-size:30px; font-weight:800; }
.welcome-card p { margin:0; opacity:.94; font-size:15px; }
.welcome-icon { font-size:68px; opacity:.92; }

.stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap:14px; }
.stat-box { background: rgba(255,255,255,.12); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,.10); border-radius:24px; padding:20px 12px; text-align:center; }
.stat-number { font-size:30px; font-weight:900; margin-bottom:8px; }
.stat-title { font-size:13px; opacity:.94; }

/* منو: ۳ تایی روی دسکتاپ - ۲ تایی روی گوشی */
.grid-menu {
    display: grid;
    grid-template-columns: repeat(3, 1fr);   /* ۳ تایی روی کامپیوتر */
    gap: 16px;
}

.menu-card {
    background: white;
    border-radius:26px;
    padding:28px 18px;
    text-align:center;
    text-decoration:none;
    color:#222;
    box-shadow: 0 10px 30px rgba(15,23,42,.05);
    transition:.25s;
    border:1px solid #eef2f7;
}
.menu-card:hover { transform:translateY(-4px); box-shadow: 0 18px 40px rgba(15,23,42,.08); }
.menu-icon { font-size:42px; margin-bottom:14px; }
.menu-title { font-size:15px; font-weight:800; color:#0f172a; }

.logout-btn {
    display:block;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color:white;
    text-align:center;
    padding:17px;
    border-radius:22px;
    text-decoration:none;
    margin-top:24px;
    font-weight:800;
    box-shadow: 0 10px 25px rgba(239,68,68,.18);
}

/* مودال */
.modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.35); backdrop-filter:blur(8px); display:flex; justify-content:center; align-items:center; z-index:9999; }
.modal-box { width:90%; max-width:680px; background:white; border-radius:24px; padding:28px; box-shadow:0 20px 60px rgba(0,0,0,.15); max-height:90vh; overflow-y:auto; }
.modal-title { font-size:23px; font-weight:800; margin-bottom:10px; color:#0f172a; }
.modal-actions { display:flex; gap:12px; margin-top:30px; }
.modal-btn { flex:1; padding:15px; border:none; border-radius:16px; font-weight:700; cursor:pointer; }
.save-btn { background:linear-gradient(135deg,#0284c7,#06b6d4); color:white; }
.cancel-btn { background:#f1f5f9; color:#334155; }

.selected-list { margin:15px 0; }
.selected-item { background:#f1f5f9; padding:12px 16px; border-radius:12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
.selected-item button { background:#ef4444; color:white; border:none; padding:5px 12px; border-radius:8px; cursor:pointer; font-size:13px; }

/* ریسپانسیو */
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .grid-menu { grid-template-columns: repeat(2, 1fr); gap:14px; } /* ۲ تایی روی گوشی */
    .menu-card { padding:24px 12px; }
    .menu-icon { font-size:38px; }
}
</style>

<script>
// اسکریپت مودال
let selections = [];

async function loadUnits() {
    const centerId = document.getElementById('centerSelect').value;
    const type = document.getElementById('typeSelect').value;
    const unitSelect = document.getElementById('unitSelect');

    if(!centerId || !type) return;

    const res = await fetch(`get-children.php?center_id=${encodeURIComponent(centerId)}&type=${encodeURIComponent(type)}`);
    const data = await res.json();

    unitSelect.innerHTML = '<option value="">انتخاب واحد / خانه بهداشت</option>';
    data.forEach(item => {
        unitSelect.innerHTML += `<option value="${item.id}">${item.name}</option>`;
    });
    unitSelect.style.display = 'block';
}

function loadTypes() {
    const centerId = document.getElementById('centerSelect').value;
    const typeSelect = document.getElementById('typeSelect');
    typeSelect.style.display = centerId ? 'block' : 'none';
    document.getElementById('unitSelect').style.display = 'none';
    document.getElementById('addBtn').style.display = 'none';
}

function enableAddButton() {
    document.getElementById('addBtn').style.display = 'block';
    document.getElementById('addBtn').disabled = false;
}

function addCurrentSelection() {
    const centerName = document.getElementById('centerSelect').options[document.getElementById('centerSelect').selectedIndex].text;
    const unitName = document.getElementById('unitSelect').options[document.getElementById('unitSelect').selectedIndex].text;
    const unitId = document.getElementById('unitSelect').value;

    if(!unitId) return;

    if(selections.some(item => item.id === unitId)){
        alert('این محل خدمت قبلاً اضافه شده است');
        return;
    }

    selections.push({id: unitId, name: `${centerName} — ${unitName}`});
    renderSelectedList();

    document.getElementById('unitSelect').value = '';
    document.getElementById('addBtn').disabled = true;
}

function renderSelectedList() {
    const container = document.getElementById('selectedList');
    let html = '';

    selections.forEach((item, i) => {
        html += `
            <div class="selected-item">
                <span>${item.name}</span>
                <button type="button" onclick="removeSelection(${i})">حذف</button>
            </div>
        `;
    });

    container.innerHTML = html;

    // ایجاد رشته کاما جدا با تبدیل id به عدد صحیح
    const nodeIds = selections
        .map(item => parseInt(item.id, 10))
        .filter(id => !isNaN(id));

    document.getElementById('selected_nodes').value = nodeIds.join(',');
}

function removeSelection(index) {
    selections.splice(index, 1);
    renderSelectedList();
}

function submitSelections() {
    if(selections.length === 0) {
        alert('لطفاً حداقل یک محل خدمت انتخاب کنید');
        return;
    }
    document.getElementById('orgForm').submit();
}
</script>

<?php include 'includes/footer.php'; ?>