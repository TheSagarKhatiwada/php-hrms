<?php
$page = 'Calender';
require_once '../../includes/session_config.php';
require_once '../../includes/date_conversion.php';
require_once '../../includes/calendar_service.php';
require_once '../../includes/forex_service.php';
require_once '../../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$currentAdYear = (int)date('Y');
$currentAdMonth = (int)date('n');
$todayAdDate = date('Y-m-d');
$initialCalendarError = null; // will be populated if remote API is not configured or other errors occur
try {
    $todayBs = get_bs_for_ad_date($todayAdDate) ?? [];
} catch (Throwable $e) {
    // Avoid fatal error during page load. Capture message for admin visibility and continue with AD-based defaults.
    $initialCalendarError = 'Calendar initialization error: ' . $e->getMessage() . ' — set HRMS_NEPALI_CALENDAR_API_URL or use the test stub.';
    $todayBs = [];
}
$currentBsYear = (int)($todayBs['bs_year'] ?? $currentAdYear);
$currentBsMonth = (int)($todayBs['bs_month'] ?? $currentAdMonth);

$initialCalendarPayload = null;
$initialCalendarError = null;
try {
    $initialCalendarPayload = get_calendar_payload('bs', $currentBsYear, $currentBsMonth);
} catch (Throwable $e) {
    $initialCalendarError = $e->getMessage();
}

$initialForexSnapshot = null;
$initialForexError = null;
try {
    $initialForexSnapshot = get_latest_forex_snapshot(7);
} catch (Throwable $e) {
    $initialForexError = $e->getMessage();
}

if (!function_exists('calendar_hex_to_rgb_components')) {
    function calendar_hex_to_rgb_components(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
        }
        if (strlen($hex) !== 6) {
            return [108, 117, 125];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return [$r, $g, $b];
    }
}

$calendarSecondaryColor = defined('SECONDARY_COLOR') ? SECONDARY_COLOR : '#6c757d';
$calendarSecondaryRgbComponents = calendar_hex_to_rgb_components($calendarSecondaryColor);
$calendarSecondaryRgb = implode(', ', $calendarSecondaryRgbComponents);
?>
<div class="container-fluid p-4" id="dualCalendarApp"
    data-current-ad-year="<?php echo $currentAdYear; ?>"
    data-current-ad-month="<?php echo $currentAdMonth; ?>"
    data-current-bs-year="<?php echo $currentBsYear; ?>"
    data-current-bs-month="<?php echo $currentBsMonth; ?>"
    data-today-ad-date="<?php echo $todayAdDate; ?>">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-calendar-alt me-2"></i>Dual AD / BS Calendar</h1>
            <p class="text-muted mb-0">View Gregorian and Bikram Sambat dates side-by-side with Nepali month labels.</p>
            <?php if (!empty($initialCalendarError)): ?>
                <div class="mt-2 alert alert-danger py-1 px-2" role="alert">
                    <strong>Calendar configuration error:</strong>
                    <small><?php echo htmlspecialchars($initialCalendarError, ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            <?php endif; ?>
        </div>
        <div class="btn-group" role="group" aria-label="Calendar Mode">
            <button type="button" class="btn btn-outline-primary" data-mode="ad">AD</button>
            <button type="button" class="btn btn-primary" data-mode="bs">BS</button>
        </div>
    </div>

    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-xl-9 col-xxl-10">
            <div class="card border-0 shadow-sm mb-4 mb-xl-0 h-100">
                <div class="card-header">
                    <div class="calendar-toolbar d-flex align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button class="btn btn-outline-primary fw-semibold" id="btnToday">आज</button>
                            <div class="btn-group view-toggle-group" role="group" aria-label="View Modes">
                                <button class="btn btn-outline-secondary view-toggle active" data-view="grid"><i class="fas fa-th-large"></i></button>
                                <button class="btn btn-outline-secondary view-toggle" data-view="list"><i class="fas fa-list"></i></button>
                            </div>
                            <div class="input-group input-group-sm ms-2" style="max-width:220px; align-items:center;">
                                <input type="text" id="bsDateInput" class="form-control form-control-sm" placeholder="BS date (YYYY-MM-DD)" aria-label="Bikram Sambat date">
                                <input type="hidden" id="selectedAdHidden" name="selected_ad_date" value=""> <!-- value intentionally blank; client controls selection -->
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap mx-sm-auto calendar-nav-controls">
                            <button class="btn btn-outline-secondary nav-btn" id="btnPrev"><i class="fas fa-angle-double-left"></i></button>
                            <select class="form-select form-select-sm w-auto" id="yearSelect"></select>
                            <select class="form-select form-select-sm w-auto" id="monthSelect"></select>
                            <button class="btn btn-outline-secondary nav-btn" id="btnNext"><i class="fas fa-angle-double-right"></i></button>
                        </div>
                        <div class="text-sm-end ms-sm-auto">
                            <div class="fw-bold fs-4 text-primary" id="nepaliHeadline">Loading...</div>
                            <small class="text-muted d-block" id="englishHeadline"></small>
                            <small class="text-muted d-block" id="calendarSource" title="Source of AD/BS data"></small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0 calendar-table">
                        <thead class="bg-primary text-white text-center">
                            <tr>
                                <th>Sunday</th>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                                <th>Saturday</th>
                            </tr>
                        </thead>
                        <tbody id="calendarGrid"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-3 col-xxl-2">
            <div class="card border-0 shadow-sm forex-card h-100">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="">Forex Snapshot</h5>
                        <small class="text-muted" id="forexUpdatedAt">Connecting to Nepal Rastra Bank...</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-refresh-forex ms-auto" id="btnRefreshForex" aria-label="Refresh forex rates">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="forexRatesStatus" class="text-muted small mb-3">Fetching latest forex rates...</div>
                    <ul class="list-unstyled mb-0 forex-rate-list" id="forexRatesList"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Celebration Details Modal -->
<div class="modal fade" id="celebrationModal" tabindex="-1" aria-labelledby="celebrationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="celebrationModalLabel">Celebrations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="celebrationModalBody">
                <!-- Dynamic content will be inserted here -->
            </div>
        </div>
    </div>
</div>

<style>
#dualCalendarApp {
    --calendar-secondary-rgb: <?php echo htmlspecialchars($calendarSecondaryRgb, ENT_QUOTES, 'UTF-8'); ?>;
}
.calendar-toolbar .form-select {
}

/* Debug visuals */
.calendar-table td.mismatch {
    outline: 3px solid rgba(220,53,69,0.85);
    position: relative;
}
.calendar-table .mismatch-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: rgba(220,53,69,0.95);
    color: #fff;
    font-size: 12px;
    line-height: 12px;
    padding: 2px 6px;
    border-radius: 10px;
}

    min-width: 80px;
    height: 44px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    font-weight: 600;
    padding: 0 2.25rem 0 0.85rem;
    appearance: none;
    background-position: right 0.7rem center;
    background-size: 12px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.calendar-toolbar .calendar-nav-controls {
    gap: 0.1rem;
}
.calendar-toolbar .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.15rem rgba(var(--primary-rgb), 0.25);
    color: #fff;
}
.calendar-toolbar .nav-btn {
    width: 40px;
    height: 40px;
    border-radius: 10%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: transparent;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}
.calendar-toolbar .nav-btn:hover,
.calendar-toolbar .nav-btn:focus {
    border-color: var(--primary-color);
    color: #fff;
}
.view-toggle-group .btn,
.calendar-toolbar .view-toggle {
    width: 44px;
}
.view-toggle.active {
    background-color: #e9ecef;
    border-color: #ced4da;
    color: #000;
}
.calendar-table thead th:nth-child(7) {
    color: var(--secondary-color) !important;
}
.calendar-table td {
    height: 120px;
    vertical-align: top;
    padding: 26px 12px 18px;
    position: relative;
    text-align: center;
}
.calendar-table td .primary-date {
    font-weight: 600;
    font-size: 1.35rem;
    position: absolute;
    top: 12px;
    left: 12px;
    line-height: 1;
}
.calendar-table td .secondary-date {
    font-size: 0.85rem;
    color: #6c757d;
    position: absolute;
    right: 12px;
    top: 12px;
    text-align: right;
}
.calendar-table td .holiday-badge {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    color: var(--secondary-color);
    background: transparent;
    font-size: 0.7rem;
    border-radius: 999px;
    text-transform: capitalize;
    white-space: nowrap;
    max-width: 70%;
    overflow: hidden;
    text-overflow: ellipsis;
    border: 1px solid transparent;
}
.calendar-table td .holiday-badge--placeholder {
    color: #adb5bd;
    text-transform: none;
}
.calendar-table td.holiday .holiday-badge {
    color: var(--secondary-color);
}
.calendar-table td.holiday .holiday-badge--placeholder {
    border-color: transparent;
}
.calendar-table td .celebration-indicator {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 4px;
    align-items: center;
    z-index: 4;
    cursor: pointer;
}
.calendar-table td .celebration-indicator:focus {
    outline: none;
}
.calendar-table td .celebration-icon {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #fff;
    color: var(--secondary-color);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease;
}
.calendar-table td .celebration-icon:hover {
    transform: scale(1.1);
}
.calendar-table td .celebration-icon--birthday {
    color: #e35d6a;
    border-color: rgba(227, 93, 106, 0.35);
    background: rgba(227, 93, 106, 0.12);
}
.calendar-table td .celebration-icon--anniversary {
    color: #2f8be6;
    border-color: rgba(47, 139, 230, 0.35);
    background: rgba(47, 139, 230, 0.12);
}
.calendar-table td .celebration-icon--default {
    color: var(--secondary-color);
    border-color: rgba(0, 0, 0, 0.08);
    background: rgba(0, 0, 0, 0.02);
}
.calendar-table td .celebration-tooltip {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 0;
    background: rgba(0, 0, 0, 0.85);
    color: #fff;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transform: translateY(4px);
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 10;
    line-height: 1.4;
}
.calendar-table td .celebration-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 12px;
    border: 4px solid transparent;
    border-top-color: rgba(0, 0, 0, 0.85);
}
.calendar-table td .celebration-indicator:hover .celebration-tooltip {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}
#celebrationModal .modal-content {
    border-radius: 16px;
    border: none;
}
#celebrationModal .modal-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    padding: 1.25rem 1.5rem;
}
#celebrationModal .modal-body {
    padding: 0 0 1.5rem 0;
}
#celebrationModal .celebration-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.02);
    margin-bottom: 0.75rem;
    border: 1px solid rgba(0, 0, 0, 0.06);
}
#celebrationModal .celebration-item:last-child {
    margin-bottom: 0;
}
#celebrationModal .celebration-item__icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
#celebrationModal .celebration-item__icon--birthday {
    color: #e35d6a;
    border-color: rgba(227, 93, 106, 0.35);
    background: rgba(227, 93, 106, 0.15);
}
#celebrationModal .celebration-item__icon--anniversary {
    color: #2f8be6;
    border-color: rgba(47, 139, 230, 0.35);
    background: rgba(47, 139, 230, 0.15);
}
#celebrationModal .celebration-item__icon--default {
    color: var(--secondary-color);
    background: rgba(0, 0, 0, 0.08);
}
#celebrationModal .celebration-item__details {
    flex: 1;
}
#celebrationModal .celebration-item__name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}
#celebrationModal .celebration-item__meta {
    font-size: 0.875rem;
    color: #6c757d;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
#celebrationModal .celebration-item__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.calendar-table td.today {
    background: rgba(var(--primary-rgb), 0.18);
    border: 2px solid var(--primary-color);
    box-shadow: inset 0 0 8px rgba(var(--primary-rgb), 0.25);
}
.calendar-table td.weekend {
    background: rgba(220,53,69,0.05);
}
.calendar-table td.holiday:not(.today):not(.selected) {
    background: transparent;
    box-shadow: none;
}
.calendar-table td.holiday .primary-date,
.calendar-table td.holiday .secondary-date {
    color: var(--secondary-color);
}
.calendar-table td.selected {
    border: 2px solid var(--primary-color);
    background: rgba(var(--primary-rgb), 0.12);
    box-shadow: inset 0 0 8px rgba(var(--primary-rgb), 0.25);
}
.calendar-table td.holiday.today,
.calendar-table td.holiday.today.selected {
    background: var(--secondary-color);
    border: 2px solid var(--secondary-color);
    box-shadow: inset 0 0 8px rgba(var(--calendar-secondary-rgb, 108, 117, 125), 0.45);
}
.calendar-table td.holiday.today .primary-date,
.calendar-table td.holiday.today .secondary-date {
    color: #fff;
}
.calendar-table td.today.selected {
    border-color: var(--primary-hover);
}
.calendar-table td[data-ad-date]:hover:not(.selected) {
    background: rgba(var(--primary-rgb), 0.12);
    cursor: pointer;
}
.calendar-table td.outside-month .primary-date,
.calendar-table td.outside-month .secondary-date {
    color: #adb5bd;
}
.calendar-table td.outside-month {
    opacity: 0.4;
}
.calendar-table td.outside-month.holiday {
    opacity: 0.4;
}
.calendar-table td.outside-month.holiday .primary-date,
.calendar-table td.outside-month.holiday .secondary-date {
    color: var(--secondary-color);
    opacity: 0.4;
}
.calendar-table td.outside-month.holiday .holiday-badge {
    color: var(--secondary-color);
}
.forex-card .card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.forex-card .btn-refresh-forex {
    color: #6c757d;
}
.forex-card .btn-refresh-forex.is-rotating i {
    animation: spin 0.9s linear infinite;
}
.forex-rate-list {
    max-height: 460px;
    overflow-y: auto;
}
.forex-rate-item {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}
.forex-rate-item:last-child {
    border-bottom: none;
}
.forex-rate-item .currency-code {
    font-weight: 600;
    font-size: 1rem;
}
.forex-rate-item .rate-values {
    font-size: 0.85rem;
}
.forex-rate-item .rate-values span {
    font-weight: 600;
    color: var(--primary-color);
    display: inline-block;
    min-width: 70px;
    text-align: right;
}
.forex-rate-item .rate-buy,
.forex-rate-item .rate-sell {
    line-height: 1.2;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<!-- Local Nepali Datepicker assets (bundled with the project) -->
<link rel="stylesheet" href="../../plugins/nepali.datepicker.v5.0.6/nepali.datepicker.v5.0.6.min.css">
<script src="../../plugins/nepali.datepicker.v5.0.6/nepali.datepicker.v5.0.6.min.js"></script>

<script>
(function() {
    const app = document.getElementById('dualCalendarApp');
    if (!app) { return; }

    const todayAdDate = app.dataset.todayAdDate || null;

    const defaults = {
        ad: {
            year: Number(app.dataset.currentAdYear),
            month: Number(app.dataset.currentAdMonth)
        },
        bs: {
            year: Number(app.dataset.currentBsYear || app.dataset.currentAdYear),
            month: Number(app.dataset.currentBsMonth || app.dataset.currentAdMonth)
        }
    };

    // Hidden AD input is intentionally blank on load; client selection is authoritative
    const hiddenAdInput = document.getElementById('selectedAdHidden');
    const initialSelectedAd = hiddenAdInput && hiddenAdInput.value ? hiddenAdInput.value : null;

    const state = {
        mode: 'bs',
        year: defaults.bs.year,
        month: defaults.bs.month,
        selectedAdDate: initialSelectedAd
    };

    const gridEl = document.getElementById('calendarGrid');
    const modeButtons = Array.from(app.querySelectorAll('[data-mode]'));
    const nepaliHeadlineEl = document.getElementById('nepaliHeadline');
    const englishHeadlineEl = document.getElementById('englishHeadline');
    const yearSelect = document.getElementById('yearSelect');
    const monthSelect = document.getElementById('monthSelect');
    const viewButtons = Array.from(app.querySelectorAll('.view-toggle'));
    const btnToday = document.getElementById('btnToday');
    const weekdayHeaderEls = Array.from(document.querySelectorAll('.calendar-table thead th'));
    const forexListEl = document.getElementById('forexRatesList');
    const forexStatusEl = document.getElementById('forexRatesStatus');
    const forexUpdatedAtEl = document.getElementById('forexUpdatedAt');
    const forexRefreshBtn = document.getElementById('btnRefreshForex');
    const initialCalendarPayload = <?php echo json_encode($initialCalendarPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const initialCalendarError = <?php echo json_encode($initialCalendarError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const initialForexSnapshot = <?php echo json_encode($initialForexSnapshot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const initialForexError = <?php echo json_encode($initialForexError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    let nepaliDigitsObserver = null;
    let nepaliDigitsRetryTimeout = null;
    let isApplyingNepaliDigits = false;
    if (initialCalendarError && typeof console !== 'undefined' && typeof console.warn === 'function') {
        console.warn('Calendar bootstrap error:', initialCalendarError);
    }
    if (initialForexError && typeof console !== 'undefined' && typeof console.warn === 'function') {
        console.warn('Forex bootstrap error:', initialForexError);
    }

    // Initialize toolbar BS date picker (local plugin)
    const bsInput = document.getElementById('bsDateInput');
    function initBsPicker() {
        if (!bsInput) return;
        if (typeof bsInput.NepaliDatePicker !== 'function') {
            // Plugin may not be parsed yet; try again shortly
            setTimeout(initBsPicker, 200);
            return;
        }
        try {
            bsInput.NepaliDatePicker({
                dateFormat: 'YYYY-MM-DD',
                language: 'nepali',
                unicodeDate: true,
                onSelect: function(selected) {
                    let bsValue = null;
                    if (!selected) return;
                    if (Array.isArray(selected)) {
                        bsValue = selected[0] && selected[0].value ? selected[0].value : null;
                    } else if (selected && typeof selected === 'object' && selected.value) {
                        bsValue = selected.value;
                    } else if (typeof selected === 'string') {
                        bsValue = selected;
                    }
                    if (!bsValue) return;
                    try {
                        const adValue = NepaliFunctions.BS2AD(bsValue, 'YYYY-MM-DD', 'YYYY-MM-DD');
                        if (hiddenAdInput) hiddenAdInput.value = adValue;
                        state.selectedAdDate = adValue;
                        const cell = gridEl.querySelector(`td[data-ad-date="${adValue}"]`);
                        if (cell) {
                            gridEl.querySelectorAll('td.selected').forEach(c => c.classList.remove('selected'));
                            cell.classList.add('selected');
                        } else {
                            // Switch to month containing the selected date
                            const bsObj = NepaliFunctions.AD2BS(adValue, 'YYYY-MM-DD');
                            if (bsObj && bsObj.year && bsObj.month) {
                                state.mode = 'bs';
                                state.year = Number(bsObj.year);
                                state.month = Number(bsObj.month);
                                loadCalendar();
                                setTimeout(() => {
                                    const cell2 = gridEl.querySelector(`td[data-ad-date="${adValue}"]`);
                                    if (cell2) {
                                        gridEl.querySelectorAll('td.selected').forEach(c => c.classList.remove('selected'));
                                        cell2.classList.add('selected');
                                    }
                                }, 400);
                            }
                        }
                    } catch (err) {
                        console.error('Failed to convert BS -> AD:', err);
                    }
                }
            });
        } catch (err) {
            console.error('Failed to initialize NepaliDatePicker:', err);
        }
    }
    initBsPicker();

    const nepaliDigitMap = {
        '0': '०',
        '1': '१',
        '2': '२',
        '3': '३',
        '4': '४',
        '5': '५',
        '6': '६',
        '7': '७',
        '8': '८',
        '9': '९'
    };
    const bsMonthScriptByIndex = {
        1: 'बैशाख',
        2: 'जेठ',
        3: 'असार',
        4: 'श्रावण',
        5: 'भाद्र',
        6: 'आश्विन',
        7: 'कार्तिक',
        8: 'मंसिर',
        9: 'पौष',
        10: 'माघ',
        11: 'फाल्गुन',
        12: 'चैत्र'
    };
    const bsRomanToScript = {
        Baisakh: bsMonthScriptByIndex[1],
        Jestha: bsMonthScriptByIndex[2],
        Ashadh: bsMonthScriptByIndex[3],
        Shrawan: bsMonthScriptByIndex[4],
        Bhadra: bsMonthScriptByIndex[5],
        Ashwin: bsMonthScriptByIndex[6],
        Kartik: bsMonthScriptByIndex[7],
        Mangsir: bsMonthScriptByIndex[8],
        Poush: bsMonthScriptByIndex[9],
        Magh: bsMonthScriptByIndex[10],
        Falgun: bsMonthScriptByIndex[11],
        Chaitra: bsMonthScriptByIndex[12]
    };
    const englishWeekdays = {
        0: 'Sunday',
        1: 'Monday',
        2: 'Tuesday',
        3: 'Wednesday',
        4: 'Thursday',
        5: 'Friday',
        6: 'Saturday'
    };

    const nepaliWeekdays = {
        0: 'आइतवार',
        1: 'सोमवार',
        2: 'मंगलवार',
        3: 'बुधवार',
        4: 'बिहीवार',
        5: 'शुक्रवार',
        6: 'शनिवार'
    };
    const celebrationTypeMeta = {
        birthday: {
            icon: 'fa-birthday-cake',
            label: 'Birthday',
            className: 'celebration-icon--birthday'
        },
        anniversary: {
            icon: 'fa-briefcase',
            label: 'Work Anniversary',
            className: 'celebration-icon--anniversary'
        },
        default: {
            icon: 'fa-star',
            label: 'Celebration',
            className: 'celebration-icon--default'
        }
    };
    const englishMonths = {
        1: 'January',
        2: 'February',
        3: 'March',
        4: 'April',
        5: 'May',
        6: 'June',
        7: 'July',
        8: 'August',
        9: 'September',
        10: 'October',
        11: 'November',
        12: 'December'
    };
    const englishShortMonths = {
        1: 'Jan',
        2: 'Feb',
        3: 'Mar',
        4: 'Apr',
        5: 'May',
        6: 'Jun',
        7: 'Jul',
        8: 'Aug',
        9: 'Sep',
        10: 'Oct',
        11: 'Nov',
        12: 'Dec'
    };

    function toNepaliDigits(value) {
        return value
            .toString()
            .split('')
            .map(char => nepaliDigitMap[char] ?? char)
            .join('');
    }

    const applyDigitsIfBs = (value) => (state.mode === 'bs') ? toNepaliDigits(value) : value;

    function enforceNepaliDigitsForFlaggedNodes() {
        if (!gridEl || state.mode !== 'bs' || isApplyingNepaliDigits) {
            return;
        }
        const targets = gridEl.querySelectorAll('[data-nepali-digit="1"]');
        if (!targets.length) {
            return;
        }
        isApplyingNepaliDigits = true;
        targets.forEach(node => {
            if (node.dataset && node.dataset.nepaliApplied === '1') {
                return;
            }
            const currentText = node.textContent || '';
            const converted = toNepaliDigits(currentText);
            if (converted !== currentText) {
                node.textContent = converted;
            }
            if (node.dataset) {
                node.dataset.nepaliApplied = '1';
            }
        });
        isApplyingNepaliDigits = false;
    }

    function enforceNepaliDigitsWithRetry() {
        if (state.mode !== 'bs') {
            return;
        }
        enforceNepaliDigitsForFlaggedNodes();
        if (nepaliDigitsRetryTimeout) {
            clearTimeout(nepaliDigitsRetryTimeout);
        }
        nepaliDigitsRetryTimeout = setTimeout(() => {
            enforceNepaliDigitsForFlaggedNodes();
            nepaliDigitsRetryTimeout = null;
        }, 120);
    }

    if (gridEl && window.MutationObserver) {
        nepaliDigitsObserver = new MutationObserver(() => {
            enforceNepaliDigitsWithRetry();
        });
        nepaliDigitsObserver.observe(gridEl, { childList: true, subtree: true, characterData: true });
    }

    function escapeHtml(value = '') {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return value.toString().replace(/[&<>"']/g, char => map[char] || char);
    }

    function fetchWithTimeout(url, options = {}, timeoutMs = 15000, timeoutMessage = 'Request timed out') {
        const controller = window.AbortController ? new AbortController() : null;
        const fetchOptions = Object.assign({}, options);
        if (controller) {
            fetchOptions.signal = controller.signal;
        }
        let timeoutId;
        const timeoutPromise = new Promise((_, reject) => {
            timeoutId = setTimeout(() => {
                if (controller) {
                    controller.abort();
                }
                reject(new Error(timeoutMessage));
            }, timeoutMs);
        });
        return Promise.race([
            fetch(url, fetchOptions),
            timeoutPromise
        ]).finally(() => {
            clearTimeout(timeoutId);
        });
    }

    function formatForexDisplayDate(dateString) {
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) {
            return dateString || 'Unknown date';
        }
        return applyDigitsIfBs(date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric', year: 'numeric' }));
    }

    function formatForexTimestamp(dateString) {
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) {
            return 'Data unavailable';
        }
        return applyDigitsIfBs(new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Kathmandu',
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date));
    }

    function renderForexRates(rates) {
        if (!forexListEl) { return; }
        const preferredCodes = ['USD', 'EUR', 'GBP', 'INR', 'CNY', 'AUD', 'CAD', 'JPY'];
        const normalizedRates = Array.isArray(rates) ? rates : [];
        const rateByCode = new Map();
        normalizedRates.forEach(rate => {
            const currencyInfo = rate && rate.currency ? rate.currency : {};
            const iso = ((currencyInfo.iso3 || currencyInfo.ISO3) || '').toUpperCase();
            if (!iso || rateByCode.has(iso)) {
                return;
            }
            rateByCode.set(iso, rate);
        });

        const orderedRates = [];
        preferredCodes.forEach(code => {
            if (rateByCode.has(code)) {
                orderedRates.push(rateByCode.get(code));
                rateByCode.delete(code);
            }
        });
        if (orderedRates.length < 5) {
            for (const [, value] of rateByCode) {
                orderedRates.push(value);
                if (orderedRates.length >= 5) {
                    break;
                }
            }
        }

        if (!orderedRates.length) {
            forexListEl.innerHTML = '<li class="text-muted small">No rate details to display.</li>';
            return;
        }

        forexListEl.innerHTML = orderedRates.map(rate => {
            const currencyInfo = rate && rate.currency ? rate.currency : {};
            const iso = ((currencyInfo.iso3 || currencyInfo.ISO3) || '').toUpperCase();
            const name = currencyInfo.name || iso || 'Currency';
            const unitValue = Number(currencyInfo.unit) || 1;
            const unitLabel = `${unitValue} unit${unitValue > 1 ? 's' : ''}`;
            const buy = parseFloat(rate && rate.buy);
            const sell = parseFloat(rate && rate.sell);
            const buyDisplay = Number.isFinite(buy) ? buy.toFixed(2) : '—';
            const sellDisplay = Number.isFinite(sell) ? sell.toFixed(2) : '—';
            return `<li class="forex-rate-item"><div><div class="currency-code">${escapeHtml(iso || '')}</div><small class="text-muted">${escapeHtml(name)} • ${escapeHtml(unitLabel)}</small></div><div class="rate-values text-end"><div class="rate-buy">Buy <span>${buyDisplay}</span></div><div class="rate-sell">Sell <span>${sellDisplay}</span></div></div></li>`;
        }).join('');
    }

    function applyForexSnapshot(snapshot) {
        if (!snapshot) {
            return false;
        }
        renderForexRates(Array.isArray(snapshot.rates) ? snapshot.rates : []);
        const publicationDate = snapshot.published_on || snapshot.modified_on || snapshot.date || '';
        const isStale = Boolean(snapshot.stale);
        if (forexUpdatedAtEl) {
            if (publicationDate) {
                const formatted = formatForexTimestamp(publicationDate);
                forexUpdatedAtEl.textContent = isStale
                    ? `Last verified ${formatted} • cached`
                    : `Updated ${formatted}`;
            } else {
                forexUpdatedAtEl.textContent = isStale ? 'Cached data' : 'Data unavailable';
            }
        }
        const latestDate = snapshot.date || publicationDate;
        if (forexStatusEl) {
            const baseMessage = latestDate
                ? `Showing ${formatForexDisplayDate(latestDate)} rates from Nepal Rastra Bank.`
                : 'Latest forex snapshot loaded.';
            forexStatusEl.textContent = isStale ? `${baseMessage} (cached)` : baseMessage;
        }
        return true;
    }

    function renderInitialForexSnapshot() {
        if (!initialForexSnapshot) {
            return false;
        }
        return applyForexSnapshot(initialForexSnapshot);
    }

    function displayForexError(message) {
        const errMsg = message && message.length ? message : 'Unable to fetch forex rates.';
        if (forexStatusEl) {
            forexStatusEl.textContent = errMsg;
        }
        if (forexUpdatedAtEl) {
            forexUpdatedAtEl.textContent = 'Data unavailable';
        }
        if (forexListEl) {
            forexListEl.innerHTML = '<li class="text-danger small">Please try again shortly.</li>';
        }
    }

    async function loadForexRates() {
        if (!forexListEl || !forexStatusEl) {
            return;
        }
        forexStatusEl.textContent = 'Fetching latest forex rates...';
        forexListEl.innerHTML = '';
        if (forexRefreshBtn) {
            forexRefreshBtn.classList.add('is-rotating');
            forexRefreshBtn.disabled = true;
        }
        const params = new URLSearchParams({ range: '7' });
        try {
            const response = await fetchWithTimeout(
                `fetch_forex.php?${params.toString()}`,
                {},
                15000,
                'Forex request timed out. Please try again later.'
            );
            if (!response.ok) {
                throw new Error(`Forex endpoint responded with ${response.status}`);
            }
            const json = await response.json();
            if (json.status !== 'success' || !json.data) {
                throw new Error(json.message || 'No forex data available at the moment.');
            }
            if (!applyForexSnapshot(json.data)) {
                throw new Error('Forex data unavailable at the moment.');
            }
        } catch (error) {
            const errMsg = error && error.message ? error.message : 'Unable to fetch forex rates.';
            displayForexError(errMsg);
        } finally {
            if (forexRefreshBtn) {
                forexRefreshBtn.classList.remove('is-rotating');
                forexRefreshBtn.disabled = false;
            }
        }
    }

    function updateModeButtons() {
        modeButtons.forEach(btn => {
            if (btn.dataset.mode === state.mode) {
                btn.classList.add('btn-primary');
                btn.classList.remove('btn-outline-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        });
    }

    function updateTodayButtonLabel(mode = state.mode) {
        if (!btnToday) {
            return;
        }
        btnToday.textContent = mode === 'bs' ? 'आज' : 'Today';
    }

    function updateWeekdayHeaders(mode = state.mode) {
        if (!weekdayHeaderEls.length) {
            return;
        }
        const useNepali = mode === 'bs';
        weekdayHeaderEls.forEach((th, index) => {
            const weekdayIndex = index % 7;
            const label = useNepali
                ? (nepaliWeekdays[weekdayIndex] || '')
                : (englishWeekdays[weekdayIndex] || '');
            th.textContent = label;
        });
    }

    updateWeekdayHeaders(state.mode);

    function adjustMonth(delta) {
        state.month += delta;
        if (state.month < 1) {
            state.month = 12;
            state.year -= 1;
        } else if (state.month > 12) {
            state.month = 1;
            state.year += 1;
        }
    }

    function resetToToday(mode, selectToday = false) {
        const fallback = defaults[mode] || defaults.ad;
        state.mode = mode;
        state.year = fallback.year;
        state.month = fallback.month;
        if (selectToday) {
            state.selectedAdDate = todayAdDate;
            if (hiddenAdInput) hiddenAdInput.value = todayAdDate;
        }
        updateTodayButtonLabel(mode);
        if (mode !== 'bs' && nepaliDigitsRetryTimeout) {
            clearTimeout(nepaliDigitsRetryTimeout);
            nepaliDigitsRetryTimeout = null;
        }
    }

    function populateYearOptions(selectedYear, isBsMode) {
        if (!yearSelect) { return; }
        yearSelect.innerHTML = '';
        const start = selectedYear - 5;
        const end = selectedYear + 5;
        for (let year = start; year <= end; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = isBsMode ? toNepaliDigits(year) : year;
            if (year === selectedYear) {
                option.selected = true;
            }
            yearSelect.appendChild(option);
        }
    }

    function populateMonthOptions(selectedMonth, isBsMode) {
        if (!monthSelect) { return; }
        monthSelect.innerHTML = '';
        for (let month = 1; month <= 12; month++) {
            const option = document.createElement('option');
            option.value = month;
            option.textContent = isBsMode ? bsMonthScriptByIndex[month] : englishMonths[month];
            if (month === selectedMonth) {
                option.selected = true;
            }
            monthSelect.appendChild(option);
        }
    }

    function updateDropdowns(meta) {
        const isBsMode = meta.mode === 'bs';
        const displayYear = isBsMode ? meta.bs_year : meta.english_year;
        populateYearOptions(displayYear, isBsMode);
        populateMonthOptions(meta.requested_month, isBsMode);
    }

    function formatEnglishRange(meta) {
        const start = new Date(meta.ad_start_date);
        const end = new Date(meta.ad_end_date);
        const sameYear = start.getFullYear() === end.getFullYear();
        const sameMonth = start.getMonth() === end.getMonth();
        if (sameMonth && sameYear) {
            return `${englishShortMonths[start.getMonth() + 1]} ${start.getFullYear()}`;
        }
        if (sameYear) {
            return `${englishShortMonths[start.getMonth() + 1]}/${englishShortMonths[end.getMonth() + 1]} ${end.getFullYear()}`;
        }
        return `${englishShortMonths[start.getMonth() + 1]} ${start.getFullYear()} - ${englishShortMonths[end.getMonth() + 1]} ${end.getFullYear()}`;
    }

    function formatBsRange(meta) {
        const start = meta.bs_range_start || null;
        const end = meta.bs_range_end || start;
        if (!start) {
            return '';
        }

        const formatPart = (part, includeYear = true) => {
            if (!part) { return ''; }
            const monthIndex = Number(part.month_index);
            const romanName = part.month_name || '';
            const scriptName = (romanName && bsRomanToScript[romanName])
                ? bsRomanToScript[romanName]
                : (bsMonthScriptByIndex[monthIndex] || romanName);
            const yearValue = part.year ? toNepaliDigits(part.year) : '';
            return includeYear && yearValue ? `${scriptName} ${yearValue}` : scriptName;
        };

        const startYear = start.year ? Number(start.year) : null;
        const endYear = end && end.year ? Number(end.year) : startYear;
        const sameYear = startYear !== null && endYear !== null && startYear === endYear;
        const startMonthIndex = start.month_index ? Number(start.month_index) : null;
        const endMonthIndex = end && end.month_index ? Number(end.month_index) : startMonthIndex;
        const sameMonth = startMonthIndex !== null && endMonthIndex !== null && startMonthIndex === endMonthIndex;

        if (!end || (sameMonth && sameYear)) {
            return formatPart(start, true);
        }

        if (sameYear) {
            const startLabel = formatPart(start, false);
            const endLabel = formatPart(end, false);
            if (!startLabel && !endLabel) {
                return '';
            }
            const yearLabel = toNepaliDigits(startYear);
            if (!endLabel || startLabel === endLabel) {
                return yearLabel ? `${startLabel} ${yearLabel}` : startLabel;
            }
            return yearLabel ? `${startLabel} / ${endLabel} ${yearLabel}` : `${startLabel} / ${endLabel}`;
        }

        const startLabelFull = formatPart(start, true);
        const endLabelFull = formatPart(end, true);
        if (!startLabelFull && !endLabelFull) {
            return '';
        }
        if (!endLabelFull || startLabelFull === endLabelFull) {
            return startLabelFull;
        }
        return `${startLabelFull} / ${endLabelFull}`;
    }

    function updateHeadlines(meta) {
        const bsMonthScript = bsRomanToScript[meta.bs_month_name] || bsMonthScriptByIndex[meta.requested_month] || meta.bs_month_name;
        const bsHeadline = `${toNepaliDigits(meta.bs_year)} ${bsMonthScript}`;
        const bsRange = formatBsRange(meta) || bsHeadline;
        const englishRange = formatEnglishRange(meta);
        const adPrimary = meta.english_month_name && meta.english_year
            ? `${meta.english_month_name} ${meta.english_year}`
            : englishRange;
        if (state.mode === 'bs') {
            nepaliHeadlineEl.textContent = bsRange;
            englishHeadlineEl.textContent = englishRange;
        } else {
            nepaliHeadlineEl.textContent = adPrimary;
            englishHeadlineEl.textContent = bsRange;
        }
    }

    function handleCalendarPayload(payload) {
        if (!payload || payload.status !== 'success') {
            return false;
        }
        const meta = payload.meta;
        if (!meta || !Array.isArray(payload.days)) {
            return false;
        }

        // Show source diagnostic (remote / db)
        const sourceEl = document.getElementById('calendarSource');
        if (sourceEl) {
            const src = (meta && meta.source) ? String(meta.source) : 'unknown';
            sourceEl.textContent = `Data source: ${src}`;
            sourceEl.style.display = 'block';
            console.info('Calendar payload source:', src);
        }

        renderCalendar(
            meta,
            payload.days,
            payload.leading_days || [],
            payload.trailing_days || []
        );

        return true;
    }

    function showCalendarError(message) {
        if (!gridEl) {
            return;
        }
        const safeError = escapeHtml(message || 'Unable to load calendar.');
        gridEl.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-5">${safeError}</td></tr>`;
    }

    function renderInitialCalendarIfAvailable() {
        if (!initialCalendarPayload || initialCalendarPayload.status !== 'success') {
            return false;
        }
        const meta = initialCalendarPayload.meta || {};
        const modeMatches = (meta.mode || 'bs') === state.mode;
        const monthMatches = Number(meta.requested_month) === Number(state.month);
        const yearMatches = state.mode === 'bs'
            ? Number(meta.bs_year) === Number(state.year)
            : Number(meta.english_year) === Number(state.year);
        if (!modeMatches || !monthMatches || !yearMatches) {
            return false;
        }
        return handleCalendarPayload(initialCalendarPayload);
    }

    function loadCalendar() {
        if (!gridEl) { return; }
        gridEl.innerHTML = '<tr><td colspan="7" class="text-center py-5">Loading...</td></tr>';
        const calendarUrl = `fetch_dates.php?mode=${state.mode}&year=${state.year}&month=${state.month}`;
        fetchWithTimeout(calendarUrl, {}, 15000, 'Calendar request timed out. Please try again.')
            .then(resp => resp.json())
            .then(payload => {
                if (!handleCalendarPayload(payload)) {
                    throw new Error(payload && payload.message ? payload.message : 'Failed to load calendar');
                }
            })
            .catch(err => {
                showCalendarError(err && err.message ? err.message : 'Unable to load calendar.');
            });
    }

    function getEnglishMonthFromDate(dateString, short = true) {
        const date = new Date(dateString);
        const idx = date.getMonth() + 1;
        return short ? englishShortMonths[idx] : englishMonths[idx];
    }

    function buildDayCell(day, meta) {
        if (!day) {
            return '<td class="outside-month"></td>';
        }

        const isOutside = day.is_current_month === false;
        const classes = [];
        if (day.is_weekend && !isOutside) {
            classes.push('weekend');
        }
        if (day.is_today) {
            classes.push('today');
        }
        if (isOutside) {
            classes.push('outside-month');
        }
        if (day.is_holiday) {
            classes.push('holiday');
        }
        const isSelected = state.selectedAdDate && state.selectedAdDate === day.ad_date;
        if (isSelected) {
            classes.push('selected');
        }

        const isBsMode = meta.mode === 'bs';
        let bsDayNumber = Number(day.bs_day);
        const adDayNumber = Number(day.ad_day ?? new Date(day.ad_date).getDate());

        // Fallback: when in BS mode but server didn't provide bs_day, try AD->BS conversion on client
        let resolvedBsDate = day.bs_date || null;
        if (isBsMode && (!Number.isFinite(bsDayNumber) || bsDayNumber <= 0)) {
            try {
                if (window.NepaliFunctions && typeof window.NepaliFunctions.AD2BS === 'function') {
                    const expected = window.NepaliFunctions.AD2BS(day.ad_date);
                    let expectedBs = null;
                    if (typeof expected === 'string') {
                        expectedBs = expected;
                    } else if (expected && expected.bs) {
                        expectedBs = expected.bs;
                    } else if (expected && expected.ad && expected.ad.bs_date) {
                        expectedBs = expected.ad.bs_date;
                    } else if (expected && expected.bs_date) {
                        expectedBs = expected.bs_date;
                    }
                    if (expectedBs) {
                        resolvedBsDate = expectedBs;
                        const parts = String(expectedBs).split('-');
                        if (parts.length === 3) {
                            const dayPart = Number(parts[2]);
                            if (Number.isFinite(dayPart) && dayPart > 0) {
                                bsDayNumber = dayPart;
                            }
                        }
                    }
                }
            } catch (err) {
                console.warn('AD2BS fallback failed for', day.ad_date, err);
            }
        }

        const bsDayLabel = Number.isFinite(bsDayNumber) && bsDayNumber > 0 ? toNepaliDigits(bsDayNumber) : '';
        const adDayLabel = Number.isFinite(adDayNumber) ? adDayNumber : '';

        const primaryLabel = isBsMode ? bsDayLabel : adDayLabel;
        const primaryNeedsNepaliDigits = isBsMode;
        let secondaryLabel = '';
        if (isBsMode) {
            const showMonth = adDayNumber === 1;
            const monthName = getEnglishMonthFromDate(day.ad_date);
            secondaryLabel = showMonth ? `${monthName} ${adDayNumber}` : `${adDayNumber}`;
        } else {
            const showMonth = bsDayNumber === 1;
            const bsMonthScript = bsRomanToScript[day.bs_month_name] || bsMonthScriptByIndex[day.bs_month] || '';
            secondaryLabel = showMonth ? `${bsMonthScript} ${bsDayLabel}` : bsDayLabel;
        }

        const classAttr = classes.length ? ` class="${classes.join(' ')}"` : '';
        const dataBsDateAttr = resolvedBsDate || day.bs_date || '';
        const bsWasComputed = !!(resolvedBsDate && (!day.bs_day || Number(day.bs_day) <= 0));
        const dataAttrs = ` data-ad-date="${day.ad_date}" data-bs-date="${dataBsDateAttr}" data-bs-computed="${bsWasComputed ? '1' : '0'}"`;

        const rawHolidayName = typeof day.holiday_name === 'string' ? day.holiday_name.trim() : '';
        const hasHolidayName = rawHolidayName.length > 0 && rawHolidayName !== '-';
        const holidayLabel = hasHolidayName ? rawHolidayName : '-';
        const holidayBadgeClasses = ['holiday-badge'];
        if (!hasHolidayName) {
            holidayBadgeClasses.push('holiday-badge--placeholder');
        }
        const holidayBadgeTitle = hasHolidayName ? ` title="${escapeHtml(holidayLabel)}"` : '';
        const holidayBadge = `<div class="${holidayBadgeClasses.join(' ')}"${holidayBadgeTitle}>${escapeHtml(holidayLabel)}</div>`;

        const celebrations = Array.isArray(day.celebrations) ? day.celebrations : [];
        let celebrationMarkup = '';
        if (celebrations.length) {
            const typeOrder = [];
            celebrations.forEach(item => {
                const typeKey = typeof item.celebration_type === 'string' && item.celebration_type.length
                    ? item.celebration_type
                    : 'default';
                if (!typeOrder.includes(typeKey)) {
                    typeOrder.push(typeKey);
                }
            });

        // end buildDayCell
    }


            const iconHtml = typeOrder.map(typeKey => {
                const meta = celebrationTypeMeta[typeKey] || celebrationTypeMeta.default;
                return `<span class="celebration-icon ${meta.className}"><i class="fas ${meta.icon}"></i></span>`;
            }).join('');
            
            // Build simple tooltip with names only
            const tooltipLines = celebrations.map(item => {
                const meta = celebrationTypeMeta[item.celebration_type] || celebrationTypeMeta.default;
                const icon = `<i class="fas ${meta.icon}"></i>`;
                const name = escapeHtml(item.display_name || 'Team member');
                return `${icon} ${name}`;
            }).join('<br>');
            
            const celebrationsJson = escapeHtml(JSON.stringify(celebrations));
            
            celebrationMarkup = `<div class="celebration-indicator" data-celebrations='${celebrationsJson}'>${iconHtml}<div class="celebration-tooltip">${tooltipLines}</div></div>`;
        }

        const primaryDigitAttr = primaryNeedsNepaliDigits ? ' data-nepali-digit="1"' : '';

        return `<td${classAttr}${dataAttrs}>${holidayBadge}${celebrationMarkup}<div class="primary-date"${primaryDigitAttr}>${primaryLabel}</div><div class="secondary-date">${secondaryLabel}</div></td>`;
    }

    // Debug helper: annotate mismatches between server bs_date and client AD2BS conversion
    function annotateDayMismatches(payload) {
        if (!payload || !Array.isArray(payload.days)) return;
        const all = (payload.leading_days || []).concat(payload.days || [], payload.trailing_days || []);
        const mismatches = [];
        all.forEach(row => {
            try {
                const ad = row.ad_date;
                const serverBs = row.bs_date;
                const expected = window.NepaliFunctions && window.NepaliFunctions.AD2BS ? window.NepaliFunctions.AD2BS(ad) : null;
                if (expected && expected.ad && expected.ad.bs) {
                    // AD2BS API returns object; try to read bs_date (plugin may differ)
                    // Fall back to string comparison if AD2BS returns {bs: 'YYYY-MM-DD'} or similar
                    let expectedBs = null;
                    if (typeof expected === 'string') {
                        expectedBs = expected;
                    } else if (expected.bs) {
                        expectedBs = expected.bs;
                    } else if (expected.ad && expected.ad.bs_date) {
                        expectedBs = expected.ad.bs_date;
                    } else if (expected.bs_date) {
                        expectedBs = expected.bs_date;
                    }

                    if (expectedBs && expectedBs !== serverBs) {
                        mismatches.push({ad, serverBs, expectedBs});
                    }
                } else if (expected && expected !== serverBs) {
                    // simple string return
                    mismatches.push({ad, serverBs, expectedBs: expected});
                }
            } catch (err) {
                console.warn('Mismatch check error for row', row, err);
            }
        });

        if (mismatches.length) {
            console.warn('Calendar BS mismatches detected:', mismatches.slice(0, 20));
            mismatches.forEach(m => {
                const el = document.querySelector(`[data-ad-date="${m.ad}"]`);
                if (el) {
                    el.classList.add('mismatch');
                    const badge = document.createElement('div');
                    badge.className = 'mismatch-badge';
                    badge.title = `Server BS: ${m.serverBs} · Expected: ${m.expectedBs}`;
                    badge.textContent = '⚠';
                    el.appendChild(badge);
                }
            });
        } else {
            console.info('Calendar debug: no BS mismatches detected.');
        }
    }

    function renderCalendar(meta, days, leadingDays = [], trailingDays = []) {
        const incomingMode = meta.mode === 'bs' ? 'bs' : 'ad';
        state.mode = incomingMode;
        state.year = incomingMode === 'bs' ? meta.bs_year : meta.english_year;
        state.month = meta.requested_month;

        updateWeekdayHeaders(incomingMode);
        updateTodayButtonLabel(incomingMode);

        updateDropdowns(meta);
        updateHeadlines(meta);

        const rows = [];
        let cells = '';
        let weekdayCounter = 0;

        const appendDayCell = (dayData) => {
            cells += buildDayCell(dayData, meta);
            weekdayCounter++;
            if (weekdayCounter === 7) {
                rows.push(`<tr>${cells}</tr>`);
                cells = '';
                weekdayCounter = 0;
            }
        };

        const appendBlankCell = () => {
            cells += '<td class="outside-month"></td>';
            weekdayCounter++;
            if (weekdayCounter === 7) {
                rows.push(`<tr>${cells}</tr>`);
                cells = '';
                weekdayCounter = 0;
            }
        };

        const targetLeading = meta.first_weekday;
        const leadingCells = targetLeading > 0 ? leadingDays.slice(-targetLeading) : [];
        leadingCells.forEach(day => appendDayCell(day));
        const missingLeading = targetLeading - leadingCells.length;
        for (let i = 0; i < missingLeading; i++) {
            appendBlankCell();
        }

        days.forEach(day => appendDayCell(day));

        const totalFilled = targetLeading + days.length;
        const trailingNeeded = totalFilled % 7 === 0 ? 0 : 7 - (totalFilled % 7);
        const trailingCells = trailingNeeded > 0 ? trailingDays.slice(0, trailingNeeded) : [];
        trailingCells.forEach(day => appendDayCell(day));
        const missingTrailing = trailingNeeded - trailingCells.length;
        for (let i = 0; i < missingTrailing; i++) {
            appendBlankCell();
        }

        if (weekdayCounter > 0) {
            while (weekdayCounter > 0 && weekdayCounter < 7) {
                appendBlankCell();
            }
        }

        gridEl.innerHTML = rows.join('');
        updateModeButtons();
        bindCellSelection();
        bindCelebrationClicks();
        enforceNepaliDigitsWithRetry();
    }

    function showCelebrationModal(celebrations, date) {
        const modal = document.getElementById('celebrationModal');
        const modalBody = document.getElementById('celebrationModalBody');
        const modalTitle = document.getElementById('celebrationModalLabel');
        
        if (!modal || !modalBody || !modalTitle) return;
        
        // Format date for title
        const dateObj = new Date(date);
        const formattedDate = applyDigitsIfBs(dateObj.toLocaleDateString('en-US', { 
            month: 'long', 
            day: 'numeric', 
            year: 'numeric' 
        }));
        modalTitle.textContent = applyDigitsIfBs(`Celebrations on ${formattedDate}`);
        
        // Build detailed celebration cards
        const celebrationItems = celebrations.map(item => {
            const meta = celebrationTypeMeta[item.celebration_type] || celebrationTypeMeta.default;
            const safeName = escapeHtml(item.display_name || 'Team member');
            const yearsValue = Number(item.years_completed);
            
            const metaParts = [];
            metaParts.push(`<span><i class="fas ${meta.icon} me-1"></i>${escapeHtml(meta.label)}</span>`);
            
            if (item.celebration_type === 'anniversary' && Number.isFinite(yearsValue) && yearsValue > 0) {
                const yearsLabel = applyDigitsIfBs(`${yearsValue} ${yearsValue === 1 ? 'year' : 'years'}`);
                metaParts.push(`<span><i class="fas fa-calendar-check me-1"></i>${yearsLabel}</span>`);
            }
            
            if (item.designation_name) {
                metaParts.push(`<span><i class="fas fa-briefcase me-1"></i>${escapeHtml(item.designation_name)}</span>`);
            }
            
            return `
                <div class="celebration-item">
                    <div class="celebration-item__icon celebration-item__icon--${item.celebration_type || 'default'}">
                        <i class="fas ${meta.icon}"></i>
                    </div>
                    <div class="celebration-item__details">
                        <div class="celebration-item__name">${safeName}</div>
                        <div class="celebration-item__meta">${metaParts.join('')}</div>
                    </div>
                </div>
            `;
        }).join('');
        
        modalBody.innerHTML = celebrationItems;
        
        // Show modal using Bootstrap 5
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    function bindCelebrationClicks() {
        const indicators = gridEl.querySelectorAll('.celebration-indicator');
        indicators.forEach(indicator => {
            indicator.addEventListener('click', (e) => {
                e.stopPropagation();
                const celebrationsJson = indicator.dataset.celebrations;
                const cell = indicator.closest('td[data-ad-date]');
                const adDate = cell ? cell.dataset.adDate : null;
                
                if (celebrationsJson && adDate) {
                    try {
                        const celebrations = JSON.parse(celebrationsJson);
                        showCelebrationModal(celebrations, adDate);
                    } catch (err) {
                        console.error('Failed to parse celebrations data:', err);
                    }
                }
            });
        });
    }

    function bindCellSelection() {
        const cells = gridEl.querySelectorAll('td[data-ad-date]');
        const bsInputLocal = document.getElementById('bsDateInput');
        const hiddenAdInputLocal = document.getElementById('selectedAdHidden');
        cells.forEach(cell => {
            cell.addEventListener('click', () => {
                const ad = cell.dataset.adDate;
                state.selectedAdDate = ad;
                gridEl.querySelectorAll('td.selected').forEach(selectedCell => selectedCell.classList.remove('selected'));
                cell.classList.add('selected');
                if (hiddenAdInputLocal) hiddenAdInputLocal.value = ad;
                if (bsInputLocal && typeof NepaliFunctions !== 'undefined' && typeof NepaliFunctions.AD2BS === 'function') {
                    try {
                        const bsStr = NepaliFunctions.AD2BS(ad, 'YYYY-MM-DD', 'YYYY-MM-DD');
                        bsInputLocal.value = bsStr;
                        if (typeof bsInputLocal.NepaliDatePicker === 'function') {
                            try { bsInputLocal.NepaliDatePicker('setDate', bsStr); } catch (e) { /* ignore if unsupported */ }
                        }
                    } catch (e) {
                        console.error('Failed to convert AD -> BS:', e);
                    }
                }
            });
        });
    }

    document.getElementById('btnPrev').addEventListener('click', () => {
        adjustMonth(-1);
        loadCalendar();
    });

    document.getElementById('btnNext').addEventListener('click', () => {
        adjustMonth(1);
        loadCalendar();
    });

    if (btnToday) {
        btnToday.addEventListener('click', () => {
            // when user explicitly asks for Today, select today's date
            resetToToday(state.mode, true);
            loadCalendar();
        });
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', () => {
            state.year = Number(yearSelect.value);
            loadCalendar();
        });
    }

    if (monthSelect) {
        monthSelect.addEventListener('change', () => {
            state.month = Number(monthSelect.value);
            loadCalendar();
        });
    }

    if (forexRefreshBtn) {
        forexRefreshBtn.addEventListener('click', () => {
            loadForexRates();
        });
    }

    viewButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            viewButtons.forEach(innerBtn => innerBtn.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    modeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            if (state.mode === btn.dataset.mode) {
                return;
            }
            resetToToday(btn.dataset.mode);
            loadCalendar();
        });
    });

    // Initialize view to BS month without forcing a selected date from server
    resetToToday('bs', false);
    // If there is a preselected date set by client (hidden input), try to render the month containing it
    if (state.selectedAdDate) {
        try {
            // Convert AD -> BS to determine month/year if possible
            if (typeof NepaliFunctions !== 'undefined' && typeof NepaliFunctions.AD2BS === 'function') {
                const bsObj = NepaliFunctions.AD2BS(state.selectedAdDate, 'YYYY-MM-DD');
                if (bsObj && bsObj.year && bsObj.month) {
                    state.mode = 'bs';
                    state.year = Number(bsObj.year);
                    state.month = Number(bsObj.month);
                }
            }
        } catch (e) {
            // ignore conversion errors and fall back to defaults
        }
    }

    const calendarBootstrapApplied = renderInitialCalendarIfAvailable();
    if (!calendarBootstrapApplied) {
        loadCalendar();
    }
    const forexBootstrapApplied = renderInitialForexSnapshot();
    if (!forexBootstrapApplied) {
        loadForexRates();
    }
})();
</script>

<?php
require_once '../../includes/footer.php';
