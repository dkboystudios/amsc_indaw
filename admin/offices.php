<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Offices Management - AMSC InnDaw";


// -------------------------------------------------------------------------
// Handle POST Actions (Create/Edit Office, Toggle Status, Delete Office)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $typeFilter = trim($_POST['type_filter'] ?? 'Academic');
    $statusFilter = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    try {
        if ($action === 'save-office') {
            $officeID   = filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT);
            $officeCode = trim($_POST['office_code'] ?? '');
            $officeName = trim($_POST['office_name'] ?? '');
            $type       = trim($_POST['type'] ?? 'Administrative');
            $officeHead = filter_input(INPUT_POST, 'office_head', FILTER_VALIDATE_INT) ?: null;

            if (empty($officeCode) || empty($officeName) || empty($type)) {
                throw new Exception("Office Code, Office Name, and Type are required fields.");
            }

            if ($officeID) {
                // Update existing office
                $sql = "UPDATE offices SET OfficeCode = ?, OfficeName = ?, Type = ?, OfficeHead = ? WHERE ID = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssii", $officeCode, $officeName, $type, $officeHead, $officeID);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update office: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID, 'Office Updated', "Updated office details for {$officeName} ({$officeCode}).");
                redirectWithMessage('success', "Office record successfully updated.", "offices.php?type={$type}&is_active={$statusFilter}");
            } else {
                // Insert new office
                $sql = "INSERT INTO offices (OfficeCode, OfficeName, Type, OfficeHead, IsActive) VALUES (?, ?, ?, ?, 1)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssi", $officeCode, $officeName, $type, $officeHead);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to create office: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID, 'Office Created', "Created new office: {$officeName} ({$officeCode}).");
                redirectWithMessage('success', "New office successfully created.", "offices.php?type={$type}&is_active=1");
            }

        } elseif ($action === 'toggle-status') {
            $officeID = filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT);
            if (!$officeID) throw new Exception("Invalid Office ID.");

            $fetchStmt = mysqli_prepare($conn, "SELECT IsActive, OfficeName, OfficeCode FROM offices WHERE ID = ?");
            mysqli_stmt_bind_param($fetchStmt, "i", $officeID);
            mysqli_stmt_execute($fetchStmt);
            $offRes = mysqli_fetch_assoc(mysqli_stmt_get_result($fetchStmt));
            mysqli_stmt_close($fetchStmt);

            if (!$offRes) throw new Exception("Office record not found.");

            $newStatus = ($offRes['IsActive'] === 1) ? 0 : 1;
            $updStmt = mysqli_prepare($conn, "UPDATE offices SET IsActive = ? WHERE ID = ?");
            mysqli_stmt_bind_param($updStmt, "ii", $newStatus, $officeID);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            $statusLabel = ($newStatus === 1) ? 'Active' : 'Inactive';
            logActivity($conn, $userID, 'Office Status Toggled', "Changed status of {$offRes['OfficeName']} to {$statusLabel}.");
            redirectWithMessage('success', "Office status updated to {$statusLabel}.", "offices.php?type={$typeFilter}&is_active={$newStatus}");

        } elseif ($action === 'delete-office') {
            $officeID = filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT);
            if (!$officeID) throw new Exception("Invalid Office ID for deletion.");

            $delStmt = mysqli_prepare($conn, "DELETE FROM offices WHERE ID = ?");
            mysqli_stmt_bind_param($delStmt, "i", $officeID);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);

            logActivity($conn, $userID, 'Office Deleted', "Deleted office record #{$officeID}.");
            redirectWithMessage('success', "Office record permanently deleted.", "offices.php?type={$typeFilter}&is_active={$statusFilter}");
        }
    } catch (Exception $e) {
        logActivity($conn, $userID, 'Office Action Error', $e->getMessage());
        redirectWithMessage('error', $e->getMessage(), "offices.php?type={$typeFilter}&is_active={$statusFilter}");
    }
}

// -------------------------------------------------------------------------
// Query Filters & State Management
// -------------------------------------------------------------------------
$typeFilter   = isset($_GET['type']) ? trim($_GET['type']) : 'Academic';
$statusFilter = isset($_GET['is_active']) ? intval($_GET['is_active']) : 1;
$search       = trim($_GET['search'] ?? '');

// Fetch Pool of Active Employees to serve as potential Office Heads
$staffQuery = "SELECT StaffID, FirstName, MiddleName, LastName, ExtName, EmployeeNumber, PositionTitle FROM staff_members WHERE IsActive = 1 ORDER BY LastName, FirstName ASC";
$staffResult = mysqli_query($conn, $staffQuery);
$activeStaffList = [];
while ($sRow = mysqli_fetch_assoc($staffResult)) {
    $activeStaffList[] = $sRow;
}

// Fetch Offices with joined Office Head Dossier Information
$whereClauses = ["o.Type = ?", "o.IsActive = ?"];
$params = [$typeFilter, $statusFilter];
$types = "si";

if (!empty($search)) {
    $whereClauses[] = "(o.OfficeCode LIKE ? OR o.OfficeName LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$whereSql = implode(" AND ", $whereClauses);

$officesSql = "
    SELECT 
        o.ID, 
        o.OfficeCode, 
        o.OfficeName, 
        o.Type, 
        o.IsActive, 
        o.OfficeHead,
        sm.FirstName, 
        sm.MiddleName, 
        sm.LastName, 
        sm.ExtName, 
        sm.ProfilePicture,
        sm.PositionTitle,
        sd.JobTitle, 
        sd.StartDate
    FROM offices o
    LEFT JOIN staff_members sm ON o.OfficeHead = sm.StaffID
    LEFT JOIN staff_designations sd ON sm.StaffID = sd.StaffID AND sd.IsPrimary = 1
    WHERE {$whereSql}
    ORDER BY o.OfficeName ASC
";

$stmt = mysqli_prepare($conn, $officesSql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$officesResult = mysqli_stmt_get_result($stmt);
$officesList = [];
while ($row = mysqli_fetch_assoc($officesResult)) {
    $officesList[] = $row;
}
mysqli_stmt_close($stmt);

// Counts for Badges/Tabs
$academicCountRes = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM offices WHERE Type = 'Academic' AND IsActive = {$statusFilter}");
$academicCount = mysqli_fetch_assoc($academicCountRes)['cnt'] ?? 0;

$adminCountRes = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM offices WHERE Type = 'Administrative' AND IsActive = {$statusFilter}");
$adminCount = mysqli_fetch_assoc($adminCountRes)['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .units-container { display: flex; flex-direction: column; gap: 20px; }
        .units-tabs-header { display: flex; gap: 10px; border-bottom: 2px solid var(--border-color); margin-bottom: 5px; }
        .tab-btn { padding: 10px 20px; font-size: 0.95rem; font-weight: 600; background: transparent; border: none; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s ease; }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #ffffff; border-top-left-radius: 6px; border-top-right-radius: 6px; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-top: 1px solid var(--border-color); }
        .filter-toolbar { display: flex; gap: 15px; background: #ffffff; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .toolbar-left { display: flex; gap: 12px; flex: 1; flex-wrap: wrap; }
        .filter-toolbar input, .filter-toolbar select { padding: 9px 14px; font-size: 0.9rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; }
        .filter-toolbar input { min-width: 240px; flex: 1; }
        .section-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .units-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .units-table th, .units-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .units-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .units-table tr:hover { background-color: #f8fafc; }
        .head-info-cell { display: flex; align-items: center; gap: 12px; }
        .head-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); background-color: #e2e8f0; flex-shrink: 0; }
        .head-name { font-weight: 600; color: var(--primary); margin: 0 0 2px 0; }
        .head-subtext { font-size: 0.8rem; color: var(--text-muted); }
        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge-status.active { background-color: #d1fae5; color: #065f46; }
        .badge-status.defunct { background-color: #fee2e2; color: #991b1b; }
        .action-btns { display: flex; gap: 6px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 90%; max-width: 550px; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; }
        .modal-overlay.active .modal-card { transform: scale(1); }
        .modal-header { padding: 16px 20px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { font-size: 1.1rem; margin: 0; color: #ffffff; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.2rem; cursor: pointer; }
        .modal-body { padding: 24px 20px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Offices Directory & Management</h2>
                    <p class="text-muted">Manage academic colleges, administrative departments, codes, and appointed office heads.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openOfficeModal('add')">
                        <i class="fa-solid fa-plus"></i> Add New Office
                    </button>
                </div>
            </div>

            <div class="units-container">

                <!-- Type Tabs Navigation Header -->
                <div class="units-tabs-header">
                    <button type="button" class="tab-btn <?= $typeFilter === 'Academic' ? 'active' : '' ?>" onclick="switchType('Academic')">
                        <i class="fa-solid fa-graduation-cap"></i> Academic Units / Colleges (<?= $academicCount ?>)
                    </button>
                    <button type="button" class="tab-btn <?= $typeFilter === 'Administrative' ? 'active' : '' ?>" onclick="switchType('Administrative')">
                        <i class="fa-solid fa-building-columns"></i> Administrative Offices (<?= $adminCount ?>)
                    </button>
                </div>

                <!-- Filter Toolbar -->
                <form method="GET" action="offices.php" class="filter-toolbar" id="filterForm">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
                    <div class="toolbar-left">
                        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search by office code or name...">
                        
                        <select name="is_active" id="statusFilter" onchange="this.form.submit()">
                            <option value="1" <?= $statusFilter === 1 ? 'selected' : '' ?>>Active Offices</option>
                            <option value="0" <?= $statusFilter === 0 ? 'selected' : '' ?>>Inactive / Defunct Offices</option>
                        </select>
                    </div>
                </form>

                <!-- Offices Table Directory Card -->
                <div class="section-card">
                    <div style="overflow-x: auto;">
                        <table class="units-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Office / College Name</th>
                                    <th>Type</th>
                                    <th>Office Head / In-Charge</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($officesList)): ?>
                                    <?php foreach ($officesList as $office): 
                                        $headName = !empty($office['FirstName']) 
                                            ? trim("{$office['FirstName']} " . (!empty($office['MiddleName']) ? substr($office['MiddleName'], 0, 1) . '. ' : '') . "{$office['LastName']} " . ($office['ExtName'] ?? ''))
                                            : 'No Head Assigned';
                                        
                                        $headAvatar = !empty($office['ProfilePicture']) 
                                            ? '/amsc_indaw/assets/uploads/profiles/' . $office['ProfilePicture'] 
                                            : $defaultProfilePhoto;
                                        
                                        $headPosition = !empty($office['JobTitle']) ? $office['JobTitle'] : 'N/A';
                                        
                                        $startDate = 'N/A';
                                        if (!empty($office['StartDate']) && $office['StartDate'] !== '0000-00-00') {
                                            $startDate = date('M d, Y', strtotime($office['StartDate']));
                                        }

                                        $statusClass = $office['IsActive'] === 1 ? 'active' : 'defunct';
                                        $statusLabel = $office['IsActive'] === 1 ? 'Active' : 'Defunct';
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($office['OfficeCode']) ?></strong></td>
                                            <td><?= htmlspecialchars($office['OfficeName']) ?></td>
                                            <td><span class="badge-designation"><?= htmlspecialchars($office['Type']) ?></span></td>
                                            <td>
                                                <div class="head-info-cell">
                                                    <?php if ($office['OfficeHead']): ?>
                                                        <img src="<?= htmlspecialchars($headAvatar) ?>" alt="Head Avatar" class="head-avatar">
                                                        <div>
                                                            <h4 class="head-name"><?= htmlspecialchars($headName) ?></h4>
                                                            <span class="head-subtext">
                                                                <?= htmlspecialchars($headPosition) ?> &middot;
                                                                <strong>Since</strong> <?= $startDate ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="font-style: italic;">No Office Head Assigned</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-status <?= $statusClass ?>">
                                                    <i class="fa-solid <?= $statusClass === 'active' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> 
                                                    <?= $statusLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openOfficeModal('edit', <?= htmlspecialchars(json_encode($office)) ?>)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                                    
                                                    <!-- Toggle Status Form -->
                                                    <form action="offices.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle-status">
                                                        <input type="hidden" name="office_id" value="<?= $office['ID'] ?>">
                                                        <input type="hidden" name="type_filter" value="<?= $typeFilter ?>">
                                                        <input type="hidden" name="is_active" value="<?= $statusFilter ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm" title="Toggle Active/Defunct"><i class="fa-solid fa-power-off"></i></button>
                                                    </form>

                                                    <!-- Delete Form -->
                                                    <form action="offices.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this office record?');">
                                                        <input type="hidden" name="action" value="delete-office">
                                                        <input type="hidden" name="office_id" value="<?= $office['ID'] ?>">
                                                        <input type="hidden" name="type_filter" value="<?= $typeFilter ?>">
                                                        <input type="hidden" name="is_active" value="<?= $statusFilter ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">No office records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- DYNAMIC CREATE / EDIT OFFICE MODAL -->
    <div id="officeModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-building"></i> Office Form</h3>
                <button type="button" class="modal-close-btn" onclick="closeOfficeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="officeForm" action="offices.php" method="POST">
                    <input type="hidden" name="action" value="save-office">
                    <input type="hidden" name="office_id" id="officeIdField" value="">
                    <input type="hidden" name="type_filter" value="<?= $typeFilter ?>">

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Office Code *</label>
                        <input type="text" name="office_code" id="officeCode" class="form-control" placeholder="e.g. OUR or CIT" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Office Name *</label>
                        <input type="text" name="office_name" id="officeName" class="form-control" placeholder="e.g. Office of the University Registrar" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Type *</label>
                        <select name="type" id="officeType" class="form-control" required>
                            <option value="Academic">Academic</option>
                            <option value="Administrative">Administrative</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Office Head / In-Charge (Optional)</label>
                        <select name="office_head" id="officeHead" class="form-control">
                            <option value="">-- No Head Assigned / Remove --</option>
                            <?php foreach ($activeStaffList as $staff): 
                                $sName = trim("{$staff['LastName']}, {$staff['FirstName']} " . ($staff['MiddleName'] ?? ''));
                            ?>
                                <option value="<?= $staff['StaffID'] ?>"><?= htmlspecialchars($sName) ?> (<?= htmlspecialchars($staff['PositionTitle']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-footer" style="margin: 20px -20px -24px -20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeOfficeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "../includes/snackbar.php"; ?>

    <script>
        const officeModal = document.getElementById('officeModal');
        const modalTitle = document.getElementById('modalTitle');
        const searchInput = document.getElementById('searchInput');

        function switchType(type) {
            window.location.href = `offices.php?type=${type}&is_active=<?= $statusFilter ?>`;
        }

        function openOfficeModal(mode, office = null) {
            if (mode === 'add') {
                modalTitle.innerHTML = `<i class="fa-solid fa-plus"></i> Add New Office`;
                document.getElementById('officeForm').reset();
                document.getElementById('officeIdField').value = '';
                document.getElementById('officeType').value = '<?= $typeFilter ?>';
            } else if (office) {
                modalTitle.innerHTML = `<i class="fa-solid fa-pen"></i> Edit Office (${office.OfficeCode})`;
                document.getElementById('officeIdField').value = office.ID;
                document.getElementById('officeCode').value = office.OfficeCode || '';
                document.getElementById('officeName').value = office.OfficeName || '';
                document.getElementById('officeType').value = office.Type || 'Academic';
                document.getElementById('officeHead').value = office.OfficeHead || '';
            }
            officeModal.classList.add('active');
        }

        function closeOfficeModal() {
            officeModal.classList.remove('active');
        }

        // Live search submit on debounce
        let searchTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>