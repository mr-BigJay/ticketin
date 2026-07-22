<?php
require 'includes/auth.php';
require 'includes/header.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$backUrl = $isAdmin ? '/admin/' : 'dashboard.php';
?>

<link rel="stylesheet" href="assets/persian-datepicker/persian-datepicker.min.css">

<style>
.attendance-tool {
    max-width: 1180px;
    margin: 0 auto;
}

.tool-card {
    background: #ffffff;
    border-radius: 30px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
    padding: 28px;
    margin-bottom: 22px;
}

.tool-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.tool-title {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}

.tool-subtitle {
    color: #64748b;
    line-height: 30px;
    font-size: 14px;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #e0f2fe;
    color: #0c4a6e;
    padding: 12px 18px;
    border-radius: 18px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid #bae6fd;
}

.drop-shell {
    min-height: 62vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.drop-zone {
    width: 100%;
    max-width: 760px;
    min-height: 380px;
    border-radius: 34px;
    border: 2px dashed #7dd3fc;
    background: linear-gradient(135deg, #f8fdff 0%, #eef9ff 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 36px;
    transition: .25s ease;
    cursor: pointer;
}

.drop-zone.dragover {
    transform: translateY(-4px);
    border-color: #0284c7;
    background: linear-gradient(135deg, #ecfeff 0%, #dff7ff 100%);
    box-shadow: 0 22px 45px rgba(2, 132, 199, 0.12);
}

.drop-icon {
    width: 92px;
    height: 92px;
    border-radius: 28px;
    background: linear-gradient(135deg, #0284c7, #06b6d4);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    margin-bottom: 22px;
    box-shadow: 0 18px 35px rgba(2, 132, 199, 0.22);
}

.drop-title {
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 14px;
}

.drop-text {
    color: #475569;
    line-height: 34px;
    margin-bottom: 22px;
    font-size: 15px;
}

.drop-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.ghost-btn,
.danger-btn,
.secondary-btn,
.save-as-btn {
    border: none;
    border-radius: 18px;
    padding: 14px 22px;
    font-family: inherit;
    font-weight: 800;
    font-size: 14px;
    cursor: pointer;
    transition: .2s ease;
}

.ghost-btn {
    background: #ffffff;
    color: #0369a1;
    border: 1px solid #bae6fd;
}

.secondary-btn {
    background: #f1f5f9;
    color: #334155;
}

.save-as-btn {
    background: linear-gradient(135deg, #0284c7, #06b6d4);
    color: #ffffff;
    box-shadow: 0 16px 28px rgba(2, 132, 199, 0.18);
}

.danger-btn {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    box-shadow: 0 16px 28px rgba(239, 68, 68, 0.16);
}

.ghost-btn:hover,
.danger-btn:hover,
.secondary-btn:hover,
.save-as-btn:hover {
    transform: translateY(-2px);
}

.hidden {
    display: none !important;
}

.status-bar {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.status-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 22px;
    padding: 18px;
}

.status-label {
    color: #64748b;
    font-size: 12px;
    margin-bottom: 8px;
}

.status-value {
    color: #0f172a;
    font-size: 15px;
    font-weight: 800;
    line-height: 28px;
    word-break: break-word;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    align-items: end;
}

.field-group label {
    display: block;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
}

.field-help {
    color: #64748b;
    font-size: 12px;
    line-height: 24px;
    margin-top: -6px;
    margin-bottom: 12px;
}

.conversion-box {
    margin-top: 18px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 14px;
    font-weight: 700;
    line-height: 28px;
}

.section-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 16px;
}

.section-subtitle {
    color: #64748b;
    line-height: 28px;
    font-size: 13px;
    margin-bottom: 18px;
}

.added-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 14px;
}

.added-item,
.record-row {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 18px;
}

.added-item-title,
.record-code {
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}

.added-item-meta,
.record-meta {
    color: #475569;
    font-size: 13px;
    line-height: 28px;
}

.record-list {
    max-height: 460px;
    overflow-y: auto;
    padding-left: 6px;
}

.record-list-inner {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.record-row {
    transition: .3s ease;
}

.record-row.is-added {
    border-color: #7dd3fc;
}

.record-row.is-highlighted {
    border-color: #0284c7;
    background: #ecfeff;
    box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.10);
}

.record-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.mini-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
}

.mini-badge.file {
    background: #dbeafe;
    color: #1d4ed8;
}

.mini-badge.added {
    background: #dcfce7;
    color: #166534;
}

.empty-state {
    text-align: center;
    padding: 34px 18px;
    color: #94a3b8;
    font-weight: 700;
    line-height: 30px;
    border-radius: 20px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
}

.bottom-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.bottom-actions-note {
    color: #64748b;
    line-height: 28px;
    font-size: 13px;
    flex: 1;
    min-width: 240px;
}

.bottom-actions-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

@media (max-width: 992px) {
    .status-bar,
    .form-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .tool-card {
        padding: 20px;
        border-radius: 24px;
    }

    .tool-title,
    .drop-title {
        font-size: 24px;
    }

    .drop-zone {
        min-height: 320px;
        padding: 24px;
    }

    .status-bar,
    .form-grid,
    .added-list {
        grid-template-columns: 1fr;
    }

    .bottom-actions {
        align-items: stretch;
    }

    .bottom-actions-buttons {
        width: 100%;
    }

    .bottom-actions-buttons button {
        flex: 1 1 100%;
    }
}
</style>

<div class="attendance-tool">
    <div class="tool-card">
        <div class="tool-header">
            <div>
                <div class="tool-title">ابزار ثبت ورود و خروج</div>
                <div class="tool-subtitle">
                    فایل رکورد را بکشید و داخل صفحه رها کنید، رکورد جدید ثبت کنید، تاریخ شمسی را به میلادی تبدیل کنید
                    و در پایان فایل را با نام جدید کنار فایل اصلی ذخیره کنید.
                </div>
            </div>

            <a href="<?= htmlspecialchars($backUrl) ?>" class="back-btn">← بازگشت</a>
        </div>

        <div id="dropView" class="drop-shell">
            <div id="dropZone" class="drop-zone" tabindex="0" role="button" aria-label="انتخاب یا رها کردن فایل رکورد">
                <div>
                    <div class="drop-icon">📄</div>
                    <div class="drop-title">فایل رکورد را اینجا رها کنید</div>
                    <div class="drop-text">
                        فرمت هر خط می‌تواند شامل کد پرسنلی، تاریخ و ساعت باشد. اگر ستون‌های بیشتری هم در فایل باشد
                        حفظ می‌شود و فقط سه ستون اصلی در فرم و لیست نمایش داده می‌شود.
                    </div>
                    <div class="drop-actions">
                        <button type="button" id="pickFileButton" class="save-as-btn">انتخاب فایل</button>
                        <button type="button" id="resetIntroButton" class="ghost-btn">بازنشانی صفحه</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="workspaceView" class="hidden">
            <div class="status-bar">
                <div class="status-box">
                    <div class="status-label">فایل مبدا</div>
                    <div id="sourceFileName" class="status-value">-</div>
                </div>

                <div class="status-box">
                    <div class="status-label">وضعیت ذخیره</div>
                    <div id="savePathHint" class="status-value">بعد از ثبت رکوردها می‌توانید با Save As ذخیره کنید.</div>
                </div>

                <div class="status-box">
                    <div class="status-label">آمار</div>
                    <div id="recordStats" class="status-value">۰ رکورد</div>
                </div>
            </div>

            <div class="tool-card" style="padding:22px; margin-bottom:20px;">
                <div class="section-title">فرم ثبت رکورد</div>
                <div class="section-subtitle">
                    کد ۶ رقمی، تاریخ و ساعت را وارد کنید. اگر تاریخ را شمسی وارد کنید، قبل از ثبت به میلادی تبدیل می‌شود.
                </div>

                <form id="recordForm">
                    <div class="form-grid">
                        <div class="field-group">
                            <label for="personCode">کد ۶ رقمی</label>
                            <input id="personCode" name="personCode" class="form-control" inputmode="numeric" minlength="6" maxlength="6" pattern="\d{6}" title="کد پرسنلی باید دقیقاً ۶ رقم باشد." placeholder="مثلاً 308590" required>
                        </div>

                        <div class="field-group">
                            <label for="dateMode">نوع تاریخ</label>
                            <select id="dateMode" name="dateMode" class="form-control">
                                <option value="jalali">شمسی</option>
                                <option value="gregorian">میلادی</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label for="recordDate">تاریخ</label>
                            <input id="recordDate" name="recordDate" class="form-control" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="1405/04/01 یا 2026/06/22" required>
                            <div id="dateFieldHelp" class="field-help">با تایپ ۴ رقم اول، / به صورت خودکار اضافه می‌شود. در حالت شمسی می‌توانید از تقویم هم استفاده کنید.</div>
                        </div>

                        <div class="field-group">
                            <label for="recordTime">ساعت تهران</label>
                            <input id="recordTime" name="recordTime" type="text" inputmode="numeric" maxlength="8" class="form-control" placeholder="07:39:23" required>
                        </div>
                    </div>

                    <div id="conversionPreview" class="conversion-box hidden"></div>

                    <button type="submit" class="btn-custom">ثبت رکورد</button>
                </form>
            </div>

            <div class="tool-card" style="padding:22px; margin-bottom:20px;">
                <div class="section-title">رکوردهای اضافه شده</div>
                <div class="section-subtitle">
                    هر موردی که همین‌جا اضافه شود ابتدا در این بخش نمایش داده می‌شود و همزمان به انتهای لیست اصلی هم می‌رود.
                </div>
                <div id="addedRecordsContainer" class="added-list"></div>
            </div>

            <div class="tool-card" style="padding:22px;">
                <div class="section-title">لیست رکوردها</div>
                <div class="section-subtitle">
                    بعد از هر ثبت، لیست به صورت خودکار روی همان رکورد اسکرول می‌شود.
                </div>
                <div id="recordList" class="record-list">
                    <div id="recordListInner" class="record-list-inner"></div>
                </div>
            </div>

            <div class="tool-card" style="padding:22px;">
                <div class="bottom-actions">
                    <div id="saveNote" class="bottom-actions-note">
                        برای جلوگیری از تغییر فایل اصلی، ذخیره فقط به صورت Save As انجام می‌شود.
                    </div>

                    <div class="bottom-actions-buttons">
                        <button type="button" id="saveButton" class="save-as-btn">ذخیره</button>
                        <button type="button" id="exitButton" class="danger-btn">خروج</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="file" id="fallbackFileInput" accept=".txt,.log,.csv,text/plain" class="hidden">

<script src="assets/persian-datepicker/jquery.min.js"></script>
<script src="assets/persian-datepicker/persian-date.min.js"></script>
<script src="assets/persian-datepicker/persian-datepicker.min.js"></script>
<script>
const state = {
    originalText: '',
    lineEnding: '\n',
    sourceFileName: '',
    sourceHandle: null,
    originalRecords: [],
    addedRecords: [],
    highlightedRecordId: null,
    invalidCount: 0,
    highlightTimerId: null
};

const dropView = document.getElementById('dropView');
const workspaceView = document.getElementById('workspaceView');
const dropZone = document.getElementById('dropZone');
const pickFileButton = document.getElementById('pickFileButton');
const resetIntroButton = document.getElementById('resetIntroButton');
const fallbackFileInput = document.getElementById('fallbackFileInput');
const sourceFileName = document.getElementById('sourceFileName');
const savePathHint = document.getElementById('savePathHint');
const recordStats = document.getElementById('recordStats');
const recordForm = document.getElementById('recordForm');
const dateMode = document.getElementById('dateMode');
const recordDate = document.getElementById('recordDate');
const recordTime = document.getElementById('recordTime');
const personCode = document.getElementById('personCode');
const conversionPreview = document.getElementById('conversionPreview');
const dateFieldHelp = document.getElementById('dateFieldHelp');
const addedRecordsContainer = document.getElementById('addedRecordsContainer');
const recordListInner = document.getElementById('recordListInner');
const saveButton = document.getElementById('saveButton');
const saveNote = document.getElementById('saveNote');
const exitButton = document.getElementById('exitButton');

function toEnglishDigits(value) {
    const map = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
        '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9'
    };

    return String(value || '').replace(/[۰-۹]/g, function(match) {
        return map[match] || match;
    });
}

function normalizeDateInput(value) {
    return toEnglishDigits(value).trim().replace(/\./g, '/').replace(/-/g, '/');
}

function digitsOnly(value) {
    return toEnglishDigits(value).replace(/\D/g, '');
}

function setFormattedValue(input, formatter) {
    const formatted = formatter(input.value);
    if (input.value !== formatted) {
        input.value = formatted;
    }
}

function formatDateFieldValue(value) {
    const digits = digitsOnly(value).slice(0, 8);
    if (digits.length <= 4) {
        return digits;
    }

    if (digits.length <= 6) {
        return digits.slice(0, 4) + '/' + digits.slice(4);
    }

    return digits.slice(0, 4) + '/' + digits.slice(4, 6) + '/' + digits.slice(6, 8);
}

function formatTimeFieldValue(value) {
    const digits = digitsOnly(value).slice(0, 6);
    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return digits.slice(0, 2) + ':' + digits.slice(2);
    }

    return digits.slice(0, 2) + ':' + digits.slice(2, 4) + ':' + digits.slice(4, 6);
}

function hasCompleteDate(value) {
    return digitsOnly(value).length === 8;
}

function hasCompleteTime(value) {
    return digitsOnly(value).length === 6;
}

function attachMaskedInput(input, formatter) {
    input.addEventListener('input', function() {
        setFormattedValue(input, formatter);
    });

    input.addEventListener('paste', function() {
        window.setTimeout(function() {
            setFormattedValue(input, formatter);
        }, 0);
    });

    input.addEventListener('blur', function() {
        setFormattedValue(input, formatter);
    });
}

function parseLine(line, index, source) {
    const trimmed = line.trim();
    if (!trimmed) {
        return null;
    }

    const columns = trimmed.split(/\s+/);
    if (columns.length < 3) {
        return {
            id: 'invalid-' + source + '-' + index,
            source: source,
            rawLine: line,
            isValid: false
        };
    }

    return {
        id: 'record-' + source + '-' + index,
        source: source,
        rawLine: line,
        code: columns[0],
        date: columns[1],
        time: columns[2],
        extraColumns: columns.slice(3),
        isValid: true
    };
}

function parseFileContent(text) {
    const lineEnding = text.indexOf('\r\n') !== -1 ? '\r\n' : '\n';
    const lines = text.split(/\r?\n/);
    const records = [];
    let invalidCount = 0;

    lines.forEach(function(line, index) {
        const parsed = parseLine(line, index, 'file');
        if (!parsed) {
            return;
        }

        if (!parsed.isValid) {
            invalidCount += 1;
            return;
        }

        records.push(parsed);
    });

    return {
        lineEnding: lineEnding,
        records: records,
        invalidCount: invalidCount
    };
}

function div(a, b) {
    return Math.floor(a / b);
}

function jalaliToGregorian(jYear, jMonth, jDay) {
    const gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    const jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    let jy = jYear - 979;
    let jm = jMonth - 1;
    let jd = jDay - 1;

    let jDayNo = 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4);
    for (let i = 0; i < jm; i += 1) {
        jDayNo += jDaysInMonth[i];
    }

    jDayNo += jd;

    let gDayNo = jDayNo + 79;
    let gy = 1600 + 400 * div(gDayNo, 146097);
    gDayNo = gDayNo % 146097;

    let leap = true;
    if (gDayNo >= 36525) {
        gDayNo -= 1;
        gy += 100 * div(gDayNo, 36524);
        gDayNo = gDayNo % 36524;

        if (gDayNo >= 365) {
            gDayNo += 1;
        } else {
            leap = false;
        }
    }

    gy += 4 * div(gDayNo, 1461);
    gDayNo = gDayNo % 1461;

    if (gDayNo >= 366) {
        leap = false;
        gDayNo -= 1;
        gy += div(gDayNo, 365);
        gDayNo = gDayNo % 365;
    }

    let gm = 0;
    while (gDayNo >= gDaysInMonth[gm] + (gm === 1 && leap ? 1 : 0)) {
        gDayNo -= gDaysInMonth[gm] + (gm === 1 && leap ? 1 : 0);
        gm += 1;
    }

    return {
        year: gy,
        month: gm + 1,
        day: gDayNo + 1
    };
}

function pad(value) {
    return String(value).padStart(2, '0');
}

function formatGregorianDate(parts) {
    return parts.year + '-' + pad(parts.month) + '-' + pad(parts.day);
}

function validateGregorianDate(parts) {
    const date = new Date(Date.UTC(parts.year, parts.month - 1, parts.day));
    return (
        date.getUTCFullYear() === parts.year &&
        date.getUTCMonth() === parts.month - 1 &&
        date.getUTCDate() === parts.day
    );
}

function convertDateToGregorian(inputValue, selectedMode) {
    const normalized = normalizeDateInput(inputValue);
    const dateMatch = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);

    if (!dateMatch) {
        throw new Error(selectedMode === 'jalali'
            ? 'تاریخ شمسی را با فرمت 1405/04/01 وارد کنید.'
            : 'تاریخ میلادی را با فرمت 2026-06-22 یا 2026/06/22 وارد کنید.');
    }

    const year = Number(dateMatch[1]);
    const month = Number(dateMatch[2]);
    const day = Number(dateMatch[3]);

    if (month < 1 || month > 12 || day < 1 || day > 31) {
        throw new Error('تاریخ وارد شده معتبر نیست.');
    }

    if (selectedMode === 'jalali') {
        const maxDay = month <= 6 ? 31 : (month <= 11 ? 30 : 30);
        if (day > maxDay) {
            throw new Error('تاریخ شمسی وارد شده معتبر نیست.');
        }
        const converted = jalaliToGregorian(year, month, day);
        return formatGregorianDate(converted);
    }

    if (!validateGregorianDate({ year: year, month: month, day: day })) {
        throw new Error('تاریخ میلادی وارد شده معتبر نیست.');
    }

    return year + '-' + pad(month) + '-' + pad(day);
}

function normalizeTimeValue(value) {
    const normalized = toEnglishDigits(value).trim();
    if (!normalized) {
        throw new Error('ساعت را وارد کنید.');
    }

    if (!hasCompleteTime(normalized)) {
        throw new Error('ساعت را کامل و با فرمت HH:MM:SS وارد کنید.');
    }

    const match = normalized.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
    if (!match) {
        throw new Error('ساعت را با فرمت HH:MM:SS وارد کنید.');
    }

    const hour = Number(match[1]);
    const minute = Number(match[2]);
    const second = Number(match[3] || '00');

    if (hour > 23 || minute > 59 || second > 59) {
        throw new Error('ساعت وارد شده معتبر نیست.');
    }

    return pad(hour) + ':' + pad(minute) + ':' + pad(second);
}

function buildOutputText() {
    const addedLines = state.addedRecords.map(function(record) {
        return record.code + '\t' + record.date + '\t' + record.time;
    });

    let result = state.originalText;
    if (addedLines.length === 0) {
        return result;
    }

    if (result && !result.endsWith('\n') && !result.endsWith('\r')) {
        result += state.lineEnding;
    }

    result += addedLines.join(state.lineEnding);
    return result;
}

function getAllRecords() {
    return state.originalRecords.concat(state.addedRecords);
}

function updateStats(invalidCount) {
    const allRecords = getAllRecords();
    const parts = [allRecords.length + ' رکورد'];

    if (state.addedRecords.length > 0) {
        parts.push(state.addedRecords.length + ' مورد جدید');
    }

    if (invalidCount > 0) {
        parts.push(invalidCount + ' خط نامعتبر نادیده گرفته شد');
    }

    recordStats.textContent = parts.join(' • ');
}

function renderAddedRecords() {
    if (state.addedRecords.length === 0) {
        addedRecordsContainer.innerHTML = '<div class="empty-state">هنوز رکورد جدیدی اضافه نشده است.</div>';
        return;
    }

    addedRecordsContainer.innerHTML = state.addedRecords.map(function(record) {
        return (
            '<div class="added-item">' +
                '<div class="added-item-title">کد ' + escapeHtml(record.code) + '</div>' +
                '<div class="added-item-meta">تاریخ میلادی: ' + escapeHtml(record.date) + '<br>ساعت تهران: ' + escapeHtml(record.time) + '</div>' +
            '</div>'
        );
    }).join('');
}

function renderRecordList() {
    const allRecords = getAllRecords();

    if (allRecords.length === 0) {
        recordListInner.innerHTML = '<div class="empty-state">هیچ رکوردی برای نمایش وجود ندارد.</div>';
        return;
    }

    recordListInner.innerHTML = allRecords.map(function(record) {
        const badges = record.source === 'added'
            ? '<span class="mini-badge added">ثبت جدید</span>'
            : '<span class="mini-badge file">از فایل</span>';

        const highlightClass = state.highlightedRecordId === record.id ? ' is-highlighted' : '';
        const addedClass = record.source === 'added' ? ' is-added' : '';

        return (
            '<div class="record-row' + addedClass + highlightClass + '" data-record-id="' + escapeHtml(record.id) + '">' +
                '<div class="record-code">کد پرسنلی: ' + escapeHtml(record.code) + '</div>' +
                '<div class="record-meta">تاریخ میلادی: ' + escapeHtml(record.date) + '<br>ساعت تهران: ' + escapeHtml(record.time) + '</div>' +
                '<div class="record-badges">' + badges + '</div>' +
            '</div>'
        );
    }).join('');
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function scrollToRecord(recordId) {
    const target = recordListInner.querySelector('[data-record-id="' + recordId + '"]');
    if (!target) {
        return;
    }

    target.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}

function refreshUI(invalidCount) {
    renderAddedRecords();
    renderRecordList();
    updateStats(typeof invalidCount === 'number' ? invalidCount : state.invalidCount);
}

function showWorkspace() {
    dropView.classList.add('hidden');
    workspaceView.classList.remove('hidden');
}

function showIntro() {
    dropView.classList.remove('hidden');
    workspaceView.classList.add('hidden');
}

function resetApp() {
    if (state.highlightTimerId) {
        clearTimeout(state.highlightTimerId);
    }

    state.originalText = '';
    state.lineEnding = '\n';
    state.sourceFileName = '';
    state.sourceHandle = null;
    state.originalRecords = [];
    state.addedRecords = [];
    state.highlightedRecordId = null;
    state.invalidCount = 0;
    state.highlightTimerId = null;

    sourceFileName.textContent = '-';
    savePathHint.textContent = 'بعد از ثبت رکوردها می‌توانید با Save As ذخیره کنید.';
    saveNote.textContent = 'برای جلوگیری از تغییر فایل اصلی، ذخیره فقط به صورت Save As انجام می‌شود.';
    recordForm.reset();
    recordTime.value = '';
    dateMode.value = 'jalali';
    updateDateModeUI();
    conversionPreview.classList.add('hidden');
    conversionPreview.textContent = '';
    refreshUI(0);
    showIntro();
}

function updateDateModeUI() {
    if (dateMode.value === 'jalali') {
        recordDate.setAttribute('placeholder', '1405/04/01');
        dateFieldHelp.textContent = 'با تایپ ۴ رقم اول، / به صورت خودکار اضافه می‌شود. در حالت شمسی می‌توانید از تقویم هم استفاده کنید.';
    } else {
        recordDate.setAttribute('placeholder', '2026/06/22');
        dateFieldHelp.textContent = 'فرمت میلادی هم با همان الگوی YYYY/MM/DD وارد می‌شود و / به صورت خودکار اضافه خواهد شد.';
    }
}

async function openFilePicker() {
    if (window.showOpenFilePicker) {
        const handles = await window.showOpenFilePicker({
            multiple: false,
            types: [{
                description: 'Record files',
                accept: {
                    'text/plain': ['.txt', '.log', '.csv']
                }
            }]
        });

        if (!handles || handles.length === 0) {
            return;
        }

        const handle = handles[0];
        const file = await handle.getFile();
        await loadSourceFile(file, handle);
        return;
    }

    fallbackFileInput.click();
}

async function loadSourceFile(file, handle) {
    const text = await file.text();
    const parsed = parseFileContent(text);

    if (state.highlightTimerId) {
        clearTimeout(state.highlightTimerId);
    }

    state.originalText = text;
    state.lineEnding = parsed.lineEnding;
    state.sourceFileName = file.name || 'record.txt';
    state.sourceHandle = handle || null;
    state.originalRecords = parsed.records;
    state.addedRecords = [];
    state.highlightedRecordId = null;
    state.invalidCount = parsed.invalidCount;
    state.highlightTimerId = null;
    recordForm.reset();
    recordTime.value = '';
    dateMode.value = 'jalali';
    updateDateModeUI();
    conversionPreview.classList.add('hidden');
    conversionPreview.textContent = '';

    sourceFileName.textContent = state.sourceFileName;

    if (state.sourceHandle && window.showSaveFilePicker) {
        savePathHint.textContent = 'پنجره ذخیره در پوشه فایل اصلی باز می‌شود و نام جدید از شما گرفته می‌شود.';
        saveNote.textContent = 'ذخیره از نوع Save As است و فایل اصلی بازنویسی نمی‌شود.';
    } else {
        savePathHint.textContent = 'در این مرورگر ذخیره با نام جدید انجام می‌شود؛ انتخاب پوشه ممکن است دستی باشد.';
        saveNote.textContent = 'اگر مرورگر از File System Access پشتیبانی کند، ذخیره کنار فایل اصلی پیشنهاد می‌شود.';
    }

    showWorkspace();
    refreshUI(parsed.invalidCount);
}

function previewConvertedDate() {
    const rawDate = recordDate.value.trim();
    if (!rawDate || !hasCompleteDate(rawDate)) {
        conversionPreview.classList.add('hidden');
        conversionPreview.textContent = '';
        return;
    }

    try {
        const gregorianDate = convertDateToGregorian(rawDate, dateMode.value);
        conversionPreview.textContent = dateMode.value === 'jalali'
            ? 'تاریخ ثبت‌شونده به میلادی: ' + gregorianDate
            : 'تاریخ میلادی آماده ثبت: ' + gregorianDate;
        conversionPreview.classList.remove('hidden');
    } catch (error) {
        conversionPreview.textContent = error.message;
        conversionPreview.classList.remove('hidden');
    }
}

function createRecordId() {
    return 'record-added-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
}

recordForm.addEventListener('submit', function(event) {
    event.preventDefault();

    try {
        const code = toEnglishDigits(personCode.value).trim();
        if (!/^\d{6}$/.test(code)) {
            throw new Error('کد پرسنلی باید دقیقاً ۶ رقم باشد.');
        }

        const gregorianDate = convertDateToGregorian(recordDate.value, dateMode.value);
        const normalizedTime = normalizeTimeValue(recordTime.value);

        const newRecord = {
            id: createRecordId(),
            source: 'added',
            code: code,
            date: gregorianDate,
            time: normalizedTime,
            isValid: true
        };

        state.addedRecords.push(newRecord);
        state.highlightedRecordId = newRecord.id;
        refreshUI();

        window.requestAnimationFrame(function() {
            scrollToRecord(newRecord.id);
        });

        if (state.highlightTimerId) {
            clearTimeout(state.highlightTimerId);
        }

        state.highlightTimerId = window.setTimeout(function() {
            state.highlightedRecordId = null;
            renderRecordList();
        }, 2600);

        conversionPreview.textContent = 'رکورد با تاریخ میلادی ' + gregorianDate + ' ثبت شد.';
        conversionPreview.classList.remove('hidden');

        personCode.value = '';
        recordDate.value = '';
        recordTime.value = '';
        personCode.focus();
    } catch (error) {
        conversionPreview.textContent = error.message;
        conversionPreview.classList.remove('hidden');
    }
});

dateMode.addEventListener('change', function() {
    updateDateModeUI();
    recordDate.value = '';
    setFormattedValue(recordTime, formatTimeFieldValue);
    previewConvertedDate();
});

attachMaskedInput(recordDate, formatDateFieldValue);
recordDate.addEventListener('input', function() {
    previewConvertedDate();
});

attachMaskedInput(recordTime, formatTimeFieldValue);
recordTime.addEventListener('input', function() {
    conversionPreview.classList.add('hidden');
    conversionPreview.textContent = '';
});

personCode.addEventListener('input', function() {
    personCode.value = digitsOnly(personCode.value).slice(0, 6);
    if (personCode.value.length === 0 || personCode.value.length === 6) {
        personCode.setCustomValidity('');
        return;
    }

    personCode.setCustomValidity('کد پرسنلی باید دقیقاً ۶ رقم باشد.');
});

personCode.addEventListener('blur', function() {
    if (personCode.value.length === 0 || personCode.value.length === 6) {
        personCode.setCustomValidity('');
        return;
    }

    personCode.setCustomValidity('کد پرسنلی باید دقیقاً ۶ رقم باشد.');
    personCode.reportValidity();
});

recordDate.addEventListener('keydown', function(event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
    if (allowedKeys.indexOf(event.key) !== -1) {
        return;
    }

    if (!/^\d$/.test(toEnglishDigits(event.key))) {
        event.preventDefault();
    }
});

recordTime.addEventListener('keydown', function(event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }

    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
    if (allowedKeys.indexOf(event.key) !== -1) {
        return;
    }

    if (!/^\d$/.test(toEnglishDigits(event.key))) {
        event.preventDefault();
    }
});

pickFileButton.addEventListener('click', function() {
    openFilePicker().catch(function(error) {
        alert(error.message || 'باز کردن فایل انجام نشد.');
    });
});

pickFileButton.addEventListener('click', function(event) {
    event.stopPropagation();
});

resetIntroButton.addEventListener('click', function(event) {
    event.stopPropagation();
    resetApp();
});

exitButton.addEventListener('click', resetApp);

fallbackFileInput.addEventListener('change', function(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) {
        return;
    }

    loadSourceFile(file, null).catch(function(error) {
        alert(error.message || 'خواندن فایل انجام نشد.');
    });

    fallbackFileInput.value = '';
});

['dragenter', 'dragover'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, function(event) {
        event.preventDefault();
        dropZone.classList.add('dragover');
    });
});

['dragleave', 'drop'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, function(event) {
        event.preventDefault();
        if (eventName === 'dragleave' && dropZone.contains(event.relatedTarget)) {
            return;
        }
        dropZone.classList.remove('dragover');
    });
});

dropZone.addEventListener('drop', async function(event) {
    const items = Array.from(event.dataTransfer.items || []);
    const fileItem = items.find(function(item) {
        return item.kind === 'file';
    });

    if (fileItem && fileItem.getAsFileSystemHandle) {
        const handle = await fileItem.getAsFileSystemHandle();
        if (handle && handle.kind === 'file') {
            const file = await handle.getFile();
            await loadSourceFile(file, handle);
            return;
        }
    }

    const file = event.dataTransfer.files && event.dataTransfer.files[0];
    if (!file) {
        alert('فایلی برای خواندن پیدا نشد.');
        return;
    }

    await loadSourceFile(file, null);
});

dropZone.addEventListener('click', function(event) {
    if (event.target.closest('button')) {
        return;
    }
    openFilePicker().catch(function(error) {
        alert(error.message || 'باز کردن فایل انجام نشد.');
    });
});

dropZone.addEventListener('keydown', function(event) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openFilePicker().catch(function(error) {
            alert(error.message || 'باز کردن فایل انجام نشد.');
        });
    }
});

saveButton.addEventListener('click', async function() {
    if (!state.sourceFileName) {
        alert('ابتدا فایل اصلی را انتخاب کنید.');
        return;
    }

    const outputText = buildOutputText();
    const suggestedName = state.sourceFileName.replace(/(\.[^.]+)?$/, '-updated$1');

    try {
        if (window.showSaveFilePicker) {
            const pickerOptions = {
                suggestedName: suggestedName,
                types: [{
                    description: 'Text files',
                    accept: {
                        'text/plain': ['.txt', '.log', '.csv']
                    }
                }]
            };

            if (state.sourceHandle) {
                pickerOptions.startIn = state.sourceHandle;
            }

            const saveHandle = await window.showSaveFilePicker(pickerOptions);
            const writable = await saveHandle.createWritable();
            await writable.write(outputText);
            await writable.close();
            alert('فایل با نام جدید ذخیره شد.');
            return;
        }

        alert('مرورگر فعلی پنجره Save As مستقیم را پشتیبانی نمی‌کند. لطفاً این صفحه را با مرورگری که File System Access دارد باز کنید.');
    } catch (error) {
        if (error && error.name === 'AbortError') {
            return;
        }
        alert(error.message || 'ذخیره فایل انجام نشد.');
    }
});

$(function() {
    if (!$('#recordDate').length) {
        return;
    }

    $('#recordDate').persianDatepicker({
        format: 'YYYY/MM/DD',
        autoClose: true,
        initialValue: false,
        initialValueType: 'persian',
        observer: false,
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
        },
        onSelect: function() {
            setFormattedValue(recordDate, formatDateFieldValue);
            previewConvertedDate();
        }
    });
});

updateDateModeUI();
setFormattedValue(recordDate, formatDateFieldValue);
setFormattedValue(recordTime, formatTimeFieldValue);
renderAddedRecords();
renderRecordList();
</script>

<?php include 'includes/footer.php'; ?>
