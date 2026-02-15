<?php
/**
 * Branch Management Page
 * This page allows administrators to manage organization branches
 */
$page = 'branches';
// Include necessary files
require_once 'includes/header.php';
require_once 'includes/db_connection.php';
require_once 'includes/utilities.php';
require_once 'includes/csrf_protection.php';

// Check if user is logged in and has admin privileges
if (!is_logged_in() || !is_admin()) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: index.php');
    exit();
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message('branches.php', 'error', 'Invalid security token. Please try again.');
    }

    // Handle different form actions
    $action = $_POST['action'] ?? '';

    // Add new branch
    if ($action === 'add_branch') {
        $name = trim($_POST['name'] ?? '');
        $default_ssid = trim($_POST['default_ssid'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_m = trim($_POST['radius_m'] ?? '');
        $geofence_enabled = isset($_POST['geofence_enabled']) ? 1 : 0;
        
        if (empty($name)) {
            $_SESSION['error'] = 'Branch name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO branches (name, default_ssid, latitude, longitude, radius_m, geofence_enabled) 
                                       VALUES (:name, :default_ssid, :latitude, :longitude, :radius_m, :geofence_enabled)");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindValue(':default_ssid', $default_ssid !== '' ? $default_ssid : null);
                $stmt->bindValue(':latitude', $latitude !== '' ? $latitude : null);
                $stmt->bindValue(':longitude', $longitude !== '' ? $longitude : null);
                $stmt->bindValue(':radius_m', $radius_m !== '' ? (int)$radius_m : null, PDO::PARAM_INT);
                $stmt->bindValue(':geofence_enabled', $geofence_enabled, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'branch_created', "Created new branch: $name");
                $_SESSION['success'] = 'Branch created successfully.';
                header('Location: branches.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error creating branch: ' . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = 'Error creating branch. Please try again.';
            }
        }
    }
    
    // Update existing branch
    elseif ($action === 'edit_branch') {
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $default_ssid = trim($_POST['default_ssid'] ?? '');
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_m = trim($_POST['radius_m'] ?? '');
        $geofence_enabled = isset($_POST['geofence_enabled']) ? 1 : 0;
        
        if (empty($name) || $branch_id <= 0) {
            $_SESSION['error'] = 'Invalid branch data.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE branches 
                                       SET name = :name,
                                           default_ssid = :default_ssid,
                                           latitude = :latitude,
                                           longitude = :longitude,
                                           radius_m = :radius_m,
                                           geofence_enabled = :geofence_enabled,
                                           updated_at = NOW()
                                       WHERE id = :id");
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindValue(':default_ssid', $default_ssid !== '' ? $default_ssid : null);
                $stmt->bindValue(':latitude', $latitude !== '' ? $latitude : null);
                $stmt->bindValue(':longitude', $longitude !== '' ? $longitude : null);
                $stmt->bindValue(':radius_m', $radius_m !== '' ? (int)$radius_m : null, PDO::PARAM_INT);
                $stmt->bindValue(':geofence_enabled', $geofence_enabled, PDO::PARAM_INT);
                $stmt->bindParam(':id', $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'branch_updated', "Updated branch ID: $branch_id");
                $_SESSION['success'] = 'Branch updated successfully.';
                header('Location: branches.php');
                exit();
            } catch (PDOException $e) {
                error_log('Error updating branch: ' . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = 'Error updating branch. Please try again.';
            }
        }
    }
    
    // Delete branch
    elseif ($action === 'delete_branch') {
        $branch_id = (int)($_POST['branch_id'] ?? 0);
        
        // Check if the branch is in use
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE branch = :branch_id");
            $stmt->bindParam(':branch_id', $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['error'] = 'This branch cannot be deleted because it is assigned to employees.';
            } else {
                // Delete the branch
                $stmt = $pdo->prepare("DELETE FROM branches WHERE id = :id");
                $stmt->bindParam(':id', $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                
                log_activity($pdo, 'branch_deleted', "Deleted branch ID: $branch_id");
                $_SESSION['success'] = 'Branch deleted successfully.';
            }
            
            header('Location: branches.php');
            exit();
        } catch (PDOException $e) {
            error_log('Error deleting branch: ' . $e->getMessage(), 3, 'error_log.txt');
            $_SESSION['error'] = 'Error deleting branch. Please try again.';
        }
    }
}

// Get all branches
try {
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching branches: ' . $e->getMessage(), 3, 'error_log.txt');
    $branches = [];
    $_SESSION['error'] = 'Error loading branches. Please try again.';
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1">Branch Management</h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="fas fa-plus me-2"></i> Add New Branch
        </button>
    </div>

    <!-- Branches Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="branches-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Name</th>
                            <th class="text-center">Created At</th>
                            <th class="text-center">Last Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo htmlspecialchars($branch['id']); ?></td>
                            <td class="align-middle fw-bold"><?php echo htmlspecialchars($branch['name']); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($branch['created_at'])); ?></td>
                            <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($branch['updated_at'])); ?></td>
                            <td class="text-center align-middle">
                                <div class="dropdown">
                                    <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $branch['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $branch['id']; ?>">
                                        <li>
                                            <a class="dropdown-item edit-branch-btn" href="#" 
                                                data-id="<?php echo $branch['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($branch['name']); ?>"
                                                data-latitude="<?php echo htmlspecialchars($branch['latitude'] ?? ''); ?>"
                                                data-longitude="<?php echo htmlspecialchars($branch['longitude'] ?? ''); ?>"
                                                data-radius="<?php echo htmlspecialchars($branch['radius_m'] ?? ''); ?>"
                                                data-default-ssid="<?php echo htmlspecialchars($branch['default_ssid'] ?? ''); ?>"
                                                data-geofence="<?php echo htmlspecialchars($branch['geofence_enabled'] ?? 0); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editBranchModal">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-branch-btn" href="#"
                                                data-id="<?php echo $branch['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($branch['name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteBranchModal">
                                                <i class="fas fa-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div> <!-- /.container-fluid -->

<!-- Modals remain outside the main content flow, before the final footer include -->
<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="branches.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add_branch">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addBranchModalLabel">Add New Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add-branch-name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add-branch-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-default-ssid" class="form-label">Default Wi-Fi SSID</label>
                        <input type="text" class="form-control" id="add-default-ssid" name="default_ssid" placeholder="e.g., Branch-Office-WiFi">
                        <small class="text-muted">If set, mobile clock requires this SSID along with geofence.</small>
                    </div>
                    <div class="mb-3">
                        <label for="add-branch-lat" class="form-label">Latitude</label>
                        <input type="number" step="0.0000001" class="form-control" id="add-branch-lat" name="latitude" placeholder="e.g., 27.7172">
                    </div>
                    <div class="mb-3">
                        <label for="add-branch-lon" class="form-label">Longitude</label>
                        <input type="number" step="0.0000001" class="form-control" id="add-branch-lon" name="longitude" placeholder="e.g., 85.3240">
                    </div>
                    <div class="mb-3">
                        <label for="add-branch-radius" class="form-label">Radius (meters)</label>
                        <input type="number" step="1" class="form-control" id="add-branch-radius" name="radius_m" placeholder="e.g., 200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Set Branch Location</label>
                        <div id="addBranchMap" class="branch-map"></div>
                        <small class="text-muted d-block mt-1">Click on the map to set the branch location.</small>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addBranchUseLocation">
                            <i class="fas fa-location-crosshairs me-1"></i> Use My Location
                        </button>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="add-branch-geofence" name="geofence_enabled" value="1">
                        <label class="form-check-label" for="add-branch-geofence">Enable Geofence</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="branches.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="edit_branch">
                <input type="hidden" name="branch_id" id="edit-branch-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editBranchModalLabel">Edit Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-branch-name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit-branch-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-default-ssid" class="form-label">Default Wi-Fi SSID</label>
                        <input type="text" class="form-control" id="edit-default-ssid" name="default_ssid" placeholder="e.g., Branch-Office-WiFi">
                        <small class="text-muted">If set, mobile clock requires this SSID along with geofence.</small>
                    </div>
                    <div class="mb-3">
                        <label for="edit-branch-lat" class="form-label">Latitude</label>
                        <input type="number" step="0.0000001" class="form-control" id="edit-branch-lat" name="latitude" placeholder="e.g., 27.7172">
                    </div>
                    <div class="mb-3">
                        <label for="edit-branch-lon" class="form-label">Longitude</label>
                        <input type="number" step="0.0000001" class="form-control" id="edit-branch-lon" name="longitude" placeholder="e.g., 85.3240">
                    </div>
                    <div class="mb-3">
                        <label for="edit-branch-radius" class="form-label">Radius (meters)</label>
                        <input type="number" step="1" class="form-control" id="edit-branch-radius" name="radius_m" placeholder="e.g., 200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Set Branch Location</label>
                        <div id="editBranchMap" class="branch-map"></div>
                        <small class="text-muted d-block mt-1">Click on the map to update the branch location.</small>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="editBranchUseLocation">
                            <i class="fas fa-location-crosshairs me-1"></i> Use My Location
                        </button>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="edit-branch-geofence" name="geofence_enabled" value="1">
                        <label class="form-check-label" for="edit-branch-geofence">Enable Geofence</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Branch Modal -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1" aria-labelledby="deleteBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="branches.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete_branch">
                <input type="hidden" name="branch_id" id="delete-branch-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBranchModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the branch <strong id="delete-branch-name"></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. If the branch is assigned to any employees, the deletion will fail.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Leaflet (map picker) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .branch-map {
        height: 260px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
</style>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const branchesTable = new DataTable('#branches-table', {
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        order: [[0, 'asc']], // Sort by ID by default
        pageLength: 10,
        language: {
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i>',
                next: '<i class="fas fa-chevron-right"></i>'
            }
        }
    });
    
    // Edit Branch Modal Handler
    const editBranchModal = document.getElementById('editBranchModal');
    if (editBranchModal) {
        editBranchModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const lat = button.getAttribute('data-latitude');
            const lon = button.getAttribute('data-longitude');
            const radius = button.getAttribute('data-radius');
            const defaultSsid = button.getAttribute('data-default-ssid');
            const geofence = button.getAttribute('data-geofence');
            
            document.getElementById('edit-branch-id').value = id;
            document.getElementById('edit-branch-name').value = name;
            document.getElementById('edit-branch-lat').value = lat || '';
            document.getElementById('edit-branch-lon').value = lon || '';
            document.getElementById('edit-branch-radius').value = radius || '';
            document.getElementById('edit-default-ssid').value = defaultSsid || '';
            document.getElementById('edit-branch-geofence').checked = geofence === '1';
        });
    }
    
    // Delete Branch Modal Handler
    const deleteBranchModal = document.getElementById('deleteBranchModal');
    if (deleteBranchModal) {
        deleteBranchModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            
            document.getElementById('delete-branch-id').value = id;
            document.getElementById('delete-branch-name').textContent = name;
        });
    }

    // Map picker for branch geofence
    let addMap, editMap, addMarker, editMarker, addCircle, editCircle;

    const initMap = (mapId, latInputId, lonInputId, radiusInputId, useLocationBtnId, isEdit) => {
        const mapEl = document.getElementById(mapId);
        if (!mapEl || typeof L === 'undefined') return;

        const latInput = document.getElementById(latInputId);
        const lonInput = document.getElementById(lonInputId);
        const radiusInput = document.getElementById(radiusInputId);
        const useLocationBtn = document.getElementById(useLocationBtnId);

        const latVal = parseFloat(latInput.value) || 27.7172;
        const lonVal = parseFloat(lonInput.value) || 85.3240;
        const radiusVal = parseInt(radiusInput.value || '200', 10);

        const map = L.map(mapId).setView([latVal, lonVal], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker = L.marker([latVal, lonVal], { draggable: true }).addTo(map);
        let circle = L.circle([latVal, lonVal], { radius: radiusVal, color: '#0d6efd', fillOpacity: 0.1 }).addTo(map);

        const updateInputs = (lat, lon) => {
            latInput.value = lat.toFixed(7);
            lonInput.value = lon.toFixed(7);
        };

        marker.on('dragend', function() {
            const pos = marker.getLatLng();
            updateInputs(pos.lat, pos.lng);
            circle.setLatLng(pos);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            circle.setLatLng(e.latlng);
            updateInputs(e.latlng.lat, e.latlng.lng);
        });

        radiusInput.addEventListener('input', function() {
            const r = parseInt(radiusInput.value || '0', 10);
            if (!Number.isNaN(r)) circle.setRadius(r);
        });

        if (useLocationBtn && navigator.geolocation) {
            useLocationBtn.addEventListener('click', function() {
                useLocationBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(function(pos) {
                    const lat = pos.coords.latitude;
                    const lon = pos.coords.longitude;
                    marker.setLatLng([lat, lon]);
                    circle.setLatLng([lat, lon]);
                    map.setView([lat, lon], 17);
                    updateInputs(lat, lon);
                    useLocationBtn.disabled = false;
                }, function() {
                    useLocationBtn.disabled = false;
                }, { enableHighAccuracy: true, timeout: 10000 });
            });
        }

        // Store references for later resize
        if (isEdit) {
            editMap = map; editMarker = marker; editCircle = circle;
        } else {
            addMap = map; addMarker = marker; addCircle = circle;
        }

        setTimeout(() => map.invalidateSize(), 250);
    };

    const addBranchModalEl = document.getElementById('addBranchModal');
    if (addBranchModalEl) {
        addBranchModalEl.addEventListener('shown.bs.modal', function() {
            if (!addMap) {
                initMap('addBranchMap', 'add-branch-lat', 'add-branch-lon', 'add-branch-radius', 'addBranchUseLocation', false);
            } else {
                setTimeout(() => addMap.invalidateSize(), 250);
            }
        });
    }

    if (editBranchModal) {
        editBranchModal.addEventListener('shown.bs.modal', function() {
            if (!editMap) {
                initMap('editBranchMap', 'edit-branch-lat', 'edit-branch-lon', 'edit-branch-radius', 'editBranchUseLocation', true);
            } else {
                setTimeout(() => editMap.invalidateSize(), 250);
            }
        });
    }
});
</script>