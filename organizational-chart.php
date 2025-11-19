<?php
/**
 * Organizational Chart Page - Library Implementation (Robust)
 * Using d3-org-chart for a stable and professional hierarchy display.
 */
$page = 'organizational-chart';

// Include necessary files
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/utilities.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = 'You need to be logged in to access this page.';
    header('Location: index.php');
    exit();
}

// Get Employee Hierarchy for d3-org-chart
function getEmployeeDataForChart($pdo) {
    $sql = "
        SELECT 
            e.emp_id as id,
            e.supervisor_id as parentId,
            e.first_name,
            e.middle_name,
            e.last_name,
            CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) as name,
            d.title as position,
            dept.name as department,
            b.name as branch_name,
            e.user_image as imageUrl
        FROM employees e
        LEFT JOIN designations d ON e.designation = d.id
        LEFT JOIN departments dept ON e.department_id = dept.id
        LEFT JOIN branches b ON e.branch = b.id
        WHERE (e.exit_date IS NULL OR e.exit_date = '0000-00-00' OR e.exit_date = '')
        ORDER BY e.supervisor_id IS NULL DESC, e.supervisor_id, e.first_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        return [];
    }

    $validIds = array_column($employees, 'id');
    $rootNodeIds = [];

    // First pass: identify root nodes and fix image URLs
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;
    foreach ($employees as $i => $employee) {
        $isRoot = false;
        if (empty($employee['parentId']) || $employee['parentId'] == $employee['id']) {
            $isRoot = true;
        } else if (!in_array($employee['parentId'], $validIds)) {
            $isRoot = true;
        }

        if ($isRoot) {
            $rootNodeIds[] = $employee['id'];
        }

    $img = $employee['imageUrl'] ?? '';
    $isHttp = is_string($img) && preg_match('/^https?:\/\//i', $img);
    $fullPath = $isHttp ? '' : $baseDir . ltrim((string)$img, "\\/");
        if (empty($img) || (!$isHttp && !@file_exists($fullPath))) {
            $employees[$i]['imageUrl'] = 'resources/userimg/default-image.jpg';
        }
    }

    // Second pass: if multiple roots, create a virtual root
    if (count($rootNodeIds) > 1) {
        $virtualRootId = 'company-virtual-root';
        $virtualRoot = [
            'id' => $virtualRootId,
            'parentId' => null,
            'name' => 'Company',
            'position' => 'Organizational Root',
            'department' => '',
            'imageUrl' => 'resources/userimg/default-image.jpg'
        ];
        
        // Add the virtual root to the data
        array_unshift($employees, $virtualRoot);

        // Point all former root nodes to the new virtual root
        foreach ($employees as $i => $employee) {
            if (in_array($employee['id'], $rootNodeIds)) {
                $employees[$i]['parentId'] = $virtualRootId;
            }
        }
    }

    return $employees;
}


$employeeData = getEmployeeDataForChart($pdo);
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">
                <i class="fas fa-sitemap me-2 text-primary"></i>Organizational Chart
            </h1>
            <p class="text-muted mb-0">Company hierarchy powered by D3.js</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="window.chart.expandAll()"><i class="fas fa-expand-arrows-alt me-1"></i>Expand All</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.chart.collapseAll()"><i class="fas fa-compress-arrows-alt me-1"></i>Collapse All</button>
            <button class="btn btn-outline-success btn-sm" onclick="window.chart.fit()"><i class="fas fa-sync-alt me-1"></i>Fit to Screen</button>
            <button id="btn-export-pdf" class="btn btn-outline-secondary btn-sm" onclick="exportChartPdf()"><i class="fas fa-file-pdf me-1"></i>Export PDF</button>
        </div>
    </div>

    <!-- Org Chart Container -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div id="org-chart-container" style="width:100%; height:70vh; background-color: #f8f9fa;"></div>
        </div>
    </div>
</div>

<style>
/* Print styles for org chart: minimize margins and hide everything except the chart */
@page { margin: 5mm; }
@media print {
    html, body { margin: 0 !important; padding: 0 !important; height: auto !important; }
    body * { display: none !important; }
    /* Show only the chart and its children */
    #org-chart-container, #org-chart-container * { display: initial !important; visibility: visible !important; }
    /* Remove card paddings/borders/shadows to avoid blank areas */
    .card, .card-body { margin: 0 !important; padding: 0 !important; border: 0 !important; box-shadow: none !important; }
    /* Make chart fill the page width with natural height */
    #org-chart-container { position: static !important; width: 100% !important; height: auto !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
    #org-chart-container svg { width: 100% !important; height: auto !important; }
}

/* Export tweaks: force dark text and white backgrounds for high-contrast PDFs */
#org-chart-container.exporting, 
#org-chart-container.exporting * {
    background: #ffffff !important;
    color: #000000 !important;
    opacity: 1 !important;
    filter: none !important;
}
#org-chart-container.exporting svg { background: #ffffff !important; }
#org-chart-container.exporting foreignObject, 
#org-chart-container.exporting text { opacity: 1 !important; }
</style>

<!-- D3 and d3-org-chart library -->
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-org-chart@2.6.0"></script>
<script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"></script>
<!-- PDF export dependencies -->
<script src="https://cdn.jsdelivr.net/npm/dom-to-image-more@3.3.0/dist/dom-to-image-more.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        const employeeData = <?php echo json_encode($employeeData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        console.log("1. Raw Employee Data from PHP:", employeeData);

        const container = document.getElementById('org-chart-container');
        if (!container) {
            console.error("Chart container #org-chart-container not found!");
            return;
        }

        if (!employeeData || employeeData.length === 0) {
            console.log("No employee data found. Displaying message.");
            container.innerHTML = `<div class="text-center py-5"><h4>No Employee Data Found</h4></div>`;
            return;
        }

        // d3-org-chart consumes a flat array with id and parentId
        const data = employeeData;
        console.log("2. Data for OrgChart:", data);

    window.chart = new d3.OrgChart()
            .container('#org-chart-container')
            .data(data)
            .nodeWidth(d => 250)
            .nodeHeight(d => 125)
            .nodeContent(function(d, i, arr, state) {
        const imageUrl = d.data.imageUrl || 'resources/userimg/default-image.jpg';
                                                const displayName = (d.data.name && d.data.name.trim())
                                                    ? d.data.name
                                                    : ([d.data.first_name, d.data.middle_name, d.data.last_name].filter(Boolean).join(' ').trim() || d.data.id || 'Unknown');
                const dept = d.data.department || '';
                const branch = d.data.branch_name || '';
                const deptBranch = (dept && branch) ? `${dept} • ${branch}` : (dept || branch || '');
                return `
                    <div style="background-color:white; border-radius:10px; border:2px solid #34495e; width:100%; height:100%; display:flex; align-items:center; padding:10px; box-sizing:border-box;">
                        <img src="${imageUrl}" style="width:60px; height:60px; border-radius:50%; margin-right:10px;" onerror="this.src='resources/userimg/default-image.jpg';">
                        <div style="text-align:left;">
                            <div style="font-weight:bold; font-size:16px; color:#212529;">${displayName}</div>
                            <div style="font-size:14px; color:#495057;">${d.data.position || 'N/A'}</div>
                            <div style="font-size:12px; color:#0d6efd;">${deptBranch}</div>
                        </div>
                    </div>
                `;
            })
            .render();
            
        console.log("3. Chart rendered successfully.");

    } catch (e) {
        console.error("An error occurred while rendering the org chart:", e);
        const container = document.getElementById('org-chart-container');
        if (container) {
            container.innerHTML = '<div class="text-center py-5"><h4>Error loading organizational chart</h4><p class="text-muted">Please try again later.</p></div>';
        }
    }
    
    // Export chart as a PDF and open in a new tab
    window.exportChartPdf = async function() {
        const container = document.getElementById('org-chart-container');
        if (!container) return;
        const svg = container.querySelector('svg');
        try {
            // Open a tab synchronously to avoid popup blockers
            const pendingWin = window.open('about:blank', '_blank');
            // Ensure fonts render
            if (document.fonts && document.fonts.ready) {
                try { await document.fonts.ready; } catch (_) {}
            }
            // Expand and fit for best capture
            if (window.chart) {
                if (typeof window.chart.expandAll === 'function') window.chart.expandAll();
                if (typeof window.chart.fit === 'function') window.chart.fit();
            }

            // Ensure all images are loaded (and set CORS where possible)
            const imgs = Array.from(container.querySelectorAll('img'));
            await Promise.all(imgs.map(img => {
                try {
                    if (/^https?:/i.test(img.src) && !img.crossOrigin) img.crossOrigin = 'anonymous';
                } catch (_) {}
                if (img.complete) return Promise.resolve();
                return new Promise(res => { img.onload = res; img.onerror = res; });
            }));

            // Compute capture dimensions from SVG viewBox or bbox
            let capW = container.scrollWidth;
            let capH = container.scrollHeight;
            if (svg) {
                const vb = svg.viewBox && svg.viewBox.baseVal ? svg.viewBox.baseVal : null;
                if (vb && vb.width && vb.height) {
                    capW = vb.width;
                    capH = vb.height;
                } else if (typeof svg.getBBox === 'function') {
                    const bb = svg.getBBox();
                    if (bb && bb.width && bb.height) {
                        capW = bb.width + bb.x;
                        capH = bb.height + bb.y;
                    }
                } else {
                    const rect = svg.getBoundingClientRect();
                    capW = rect.width; capH = rect.height;
                }
            }

            // White background and export mode
            const prevBg = container.style.backgroundColor;
            container.style.backgroundColor = '#ffffff';
            container.classList.add('exporting');

            // Allow layout to settle
            await new Promise(r => requestAnimationFrame(() => r()));
            // Wait a bit to ensure d3 transitions complete
            await new Promise(r => setTimeout(r, 350));

            let dataUrl;
            if (svg) {
                // Prefer capturing the SVG itself
                const scale = 2;
                dataUrl = await domtoimage.toPng(svg, {
                    bgcolor: '#ffffff',
                    cacheBust: true,
                    width: Math.max(800, Math.round(capW * scale)),
                    height: Math.max(600, Math.round(capH * scale)),
                    quality: 1
                });
            } else {
                // Fallback to container capture
                const canvas = await html2canvas(container, {
                    backgroundColor: '#ffffff',
                    scale: Math.max(2, window.devicePixelRatio || 2),
                    useCORS: true
                });
                dataUrl = canvas.toDataURL('image/jpeg', 0.95);
            }

            const { jsPDF } = window.jspdf || {};
            if (!jsPDF) return;
            const img = new Image();
            img.src = dataUrl;
            await new Promise(res => { img.onload = res; img.onerror = res; });
            const landscape = img.width >= img.height;
            const doc = new jsPDF({ orientation: landscape ? 'landscape' : 'portrait', unit: 'pt', format: 'a4' });
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 20;
            let imgWidth = pageWidth - margin * 2;
            let imgHeight = (img.height * imgWidth) / img.width;
            if (imgHeight > pageHeight - margin * 2) {
                imgHeight = pageHeight - margin * 2;
                imgWidth = (img.width * imgHeight) / img.height;
            }
            doc.addImage(dataUrl, 'PNG', (pageWidth - imgWidth) / 2, (pageHeight - imgHeight) / 2, imgWidth, imgHeight, undefined, 'FAST');
            const blobUrl = doc.output('bloburl');
            if (pendingWin && !pendingWin.closed) {
                pendingWin.location.href = blobUrl;
            } else {
                window.open(blobUrl, '_blank');
            }
            container.style.backgroundColor = prevBg;
            container.classList.remove('exporting');
        } catch (err) {
            console.error('PDF export failed:', err);
        }
    }

    // Bind click handler (CSP-safe) in case inline onclick is blocked
    const btnExport = document.getElementById('btn-export-pdf');
    if (btnExport) {
        btnExport.addEventListener('click', function(ev) {
            ev.preventDefault();
            if (typeof window.exportChartPdf === 'function') {
                window.exportChartPdf();
            }
        });
    }
});
</script>