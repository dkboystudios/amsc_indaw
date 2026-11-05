<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Employee Directory & Management - AMSC InnDaw";

// Ensure $userID is safely available from session
$userID = $userID ?? ($user['ID'] ?? 0);

// Calculate the date exactly 18 years ago or current max date
$maxDate = date('Y-m-d');
$defaultProfilePhoto = '/amsc_indaw/assets/images/defaults/default-photo.avif';

// Upload directory configuration
$uploadDir = '../assets/uploads/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Secure Helper to handle File Uploads
function handleImageUpload($conn, $userID, $firstName, $lastName, $employeeNumber, $fileInputName) {
    $targetDir = '../assets/uploads/profiles/';

    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fileInputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }

    $fileName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($fileExtension, $allowedExtensions, true)) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return false;
    }

    $newFileName = strtolower(str_replace(' ', '_', $firstName) . '_' . str_replace(' ', '_', $lastName) . '-admin-' . date('YmdHis') . '.' . $fileExtension);
    $destPath = $targetDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        logActivity($conn, $userID, 'Profile Picture Update Successful', "Updated profile picture successfully for {$firstName} {$lastName} [ID: $employeeNumber].");
        return $newFileName;
    }

    return false;
}

// Handle AJAX Endpoints (Designations, Teaching Load, Auto Employee Number Generation)
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'];

    if ($action === 'get_next_employee_number') {
        $nextID = 1;
        $autoIncRes = mysqli_query($conn, "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_members'");
        if ($autoIncRes && $row = mysqli_fetch_assoc($autoIncRes)) {
            $nextID = intval($row['AUTO_INCREMENT'] ?? 1);
        } else {
            $maxRes = mysqli_query($conn, "SELECT MAX(StaffID) AS max_id FROM staff_members");
            if ($maxRes && $mRow = mysqli_fetch_assoc($maxRes)) {
                $nextID = intval($mRow['max_id'] ?? 0) + 1;
            }
        }
        
        $currentYear = date('Y');
        $calculatedCode = ($currentYear * 10000) + $nextID;
        $employeeNumber = "AMSC-EMP-" . $calculatedCode;

        echo json_encode(['success' => true, 'employee_number' => $employeeNumber]);
        exit;
    }

    if ($action === 'get_designations') {
        $staffID = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
        $designations = [];
        $stmt = mysqli_prepare($conn, "SELECT sd.*, o.OfficeName, o.OfficeCode FROM staff_designations sd INNER JOIN offices o ON sd.OfficeID = o.ID WHERE sd.StaffID = ? ORDER BY sd.IsPrimary DESC, sd.StartDate DESC");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $staffID);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $designations[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        echo json_encode(['success' => true, 'designations' => $designations]);
        exit;
    }

    if ($action === 'save_designation') {
        $desID = filter_input(INPUT_POST, 'designation_id', FILTER_VALIDATE_INT);
        $staffID = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
        $officeID = filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT) ?: null;
        $jobTitle = trim($_POST['job_title'] ?? '');
        $appointmentType = trim($_POST['appointment_type'] ?? 'Academic');
        $isPrimary = filter_input(INPUT_POST, 'is_primary', FILTER_VALIDATE_INT) ? 1 : 0;
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));

        if (!$staffID || empty($jobTitle)) {
            echo json_encode(['success' => false, 'message' => 'Staff ID and Job Title are required fields.']);
            exit;
        }

        if ($isPrimary) {
            $unstmt = mysqli_prepare($conn, "UPDATE staff_designations SET IsPrimary = 0 WHERE StaffID = ?");
            mysqli_stmt_bind_param($unstmt, "i", $staffID);
            mysqli_stmt_execute($unstmt);
            mysqli_stmt_close($unstmt);
        }

        if ($desID) {
            $stmt = mysqli_prepare($conn, "UPDATE staff_designations SET OfficeID = ?, JobTitle = ?, AppointmentType = ?, IsPrimary = ?, StartDate = ? WHERE ID = ? AND StaffID = ?");
            mysqli_stmt_bind_param($stmt, "issisii", $officeID, $jobTitle, $appointmentType, $isPrimary, $startDate, $desID, $staffID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            logActivity($conn, $userID, 'Designation Updated', "Updated designation ID {$desID} for staff #{$staffID}.");
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO staff_designations (StaffID, OfficeID, JobTitle, AppointmentType, IsPrimary, StartDate) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iissis", $staffID, $officeID, $jobTitle, $appointmentType, $isPrimary, $startDate);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            logActivity($conn, $userID, 'Designation Added', "Added new designation for staff #{$staffID}.");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_designation') {
        $desID = filter_input(INPUT_POST, 'designation_id', FILTER_VALIDATE_INT);
        if ($desID) {
            $stmt = mysqli_prepare($conn, "DELETE FROM staff_designations WHERE ID = ?");
            mysqli_stmt_bind_param($stmt, "i", $desID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            logActivity($conn, $userID, 'Designation Deleted', "Deleted designation ID {$desID}.");
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    if ($action === 'get_teaching_load') {
        $staffID = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
        $academicYearID = filter_input(INPUT_GET, 'academic_year', FILTER_VALIDATE_INT) ?: 1;
        $semester = trim($_GET['semester'] ?? '1st Semester');

        $loads = [];
        $totalUnits = 0;

        $sql = "SELECT c.ID as ClassID, c.ClassName, s.SubjectCode, s.SubjectTitle, cs.Units as TotalUnits,
                       GROUP_CONCAT(CONCAT(csch.DayOfWeek, ' ', TIME_FORMAT(csch.StartTime, '%h:%i %p'), ' - ', TIME_FORMAT(csch.EndTime, '%h:%i %p'), ' (', csch.RoomAssignment, ')') SEPARATOR '; ') as ScheduleDisplay
                FROM classes c
                INNER JOIN curriculum_subjects cs ON c.CurriculumSubjectID = cs.ID
                INNER JOIN subjects s ON cs.SubjectID = s.ID
                LEFT JOIN class_schedules csch ON c.ID = csch.ClassID
                WHERE c.InstructorID = ? AND c.AcademicYearID = ? AND cs.Semester = ?
                GROUP BY c.ID";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iis", $staffID, $academicYearID, $semester);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $loads[] = $row;
                $totalUnits += floatval($row['TotalUnits']);
            }
            mysqli_stmt_close($stmt);
        }

        echo json_encode(['success' => true, 'loads' => $loads, 'totalUnits' => $totalUnits]);
        exit;
    }
}

// Handle Form POST Requests (Add/Edit Employee, Status Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax_action'])) {
    $action = trim($_POST['action'] ?? '');
    $tabTarget = trim($_POST['tab_target'] ?? 'office');
    $activeStatus = !isset($_POST['is_active']) || $_POST['is_active'] === 1 ? 1 : 0;
    $pageTarget = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: 1;

    try {
        if ($action === 'save-employee') {
            $staffID = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
            $employeeNumber = trim($_POST['employee_number'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $middleName = trim($_POST['middle_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $extName = trim($_POST['ext_name'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $empStatus = trim($_POST['emp_status'] ?? '');
            $positionTitle = trim($_POST['position_title'] ?? '');

            if (empty($employeeNumber) || empty($firstName) || empty($lastName)) {
                throw new Exception("Employee number, first name and last name are required fields.");
            }

            $newPhoto = handleImageUpload($conn, $userID, $firstName, $lastName, $employeeNumber, 'profile_picture');

            if ($staffID) {
                if ($newPhoto) {
                    $oldImgSql = "SELECT ProfilePicture FROM staff_members WHERE StaffID = ?";
                    $oldStmt = mysqli_prepare($conn, $oldImgSql);
                    mysqli_stmt_bind_param($oldStmt, 'i', $staffID);
                    mysqli_stmt_execute($oldStmt);
                    $resOld = mysqli_stmt_get_result($oldStmt);
                    if ($oldRow = mysqli_fetch_assoc($resOld)) {
                        $oldPath = $uploadDir . $oldRow['ProfilePicture'];
                        if (!empty($oldRow['ProfilePicture']) && file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    mysqli_stmt_close($oldStmt);

                    $sql = "UPDATE staff_members SET EmployeeNumber = ?, FirstName = ?, MiddleName = ?, LastName = ?, ExtName = ?, Gender = ?, EmploymentStatus = ?, DateOfBirth = ?, ContactNumber = ?, HomeAddress = ?, PositionTitle = ?, ProfilePicture = ? WHERE StaffID = ?";
                    $updateStmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($updateStmt, "ssssssssssssi", $employeeNumber, $firstName, $middleName, $lastName, $extName, $gender, $empStatus, $dob, $contactNumber, $address, $positionTitle, $newPhoto, $staffID);
                } else {
                    $sql = "UPDATE staff_members SET EmployeeNumber = ?, FirstName = ?, MiddleName = ?, LastName = ?, ExtName = ?, Gender = ?, EmploymentStatus = ?, DateOfBirth = ?, ContactNumber = ?, HomeAddress = ?, PositionTitle = ? WHERE StaffID = ?";
                    $updateStmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($updateStmt, "sssssssssssi", $employeeNumber, $firstName, $middleName, $lastName, $extName, $gender, $empStatus, $dob, $contactNumber, $address, $positionTitle, $staffID);
                }

                if (!mysqli_stmt_execute($updateStmt)) {
                    throw new Exception("Execution failed: " . mysqli_stmt_error($updateStmt));
                }
                mysqli_stmt_close($updateStmt);

                logActivity($conn, $userID, 'Employee Record Updated', "Updated profile details for {$firstName} {$lastName} (ID: {$employeeNumber}).");
                redirectWithMessage('success', "Profile details updated successfully.", "employees.php?is_active={$activeStatus}&tab={$tabTarget}&");
            } else {
                $sql = "INSERT INTO staff_members (EmployeeNumber, FirstName, MiddleName, LastName, ExtName, Gender, EmploymentStatus, DateOfBirth, ContactNumber, HomeAddress, ProfilePicture, PositionTitle) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                $picToSave = $newPhoto ?? 'default-photo.avif';
                mysqli_stmt_bind_param($stmt, "ssssssssssss", $employeeNumber, $firstName, $middleName, $lastName, $extName, $gender, $empStatus, $dob, $contactNumber, $address, $picToSave, $positionTitle);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Execution failed: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                logActivity($conn, $userID, 'Employee Record Created', "Added new employee personnel: {$firstName} {$lastName} ({$employeeNumber}).");
                redirectWithMessage('success', 'New employee successfully added.', "employees.php?tab={$tabTarget}&");
            }
        } elseif ($action === 'toggle-status') {
            $staffID = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
            if (!$staffID) throw new Exception("Invalid employee ID specified.");

            $fetchStmt = mysqli_prepare($conn, "SELECT IsActive, FirstName, LastName FROM staff_members WHERE StaffID = ?");
            mysqli_stmt_bind_param($fetchStmt, "i", $staffID);
            mysqli_stmt_execute($fetchStmt);
            $empRes = mysqli_fetch_assoc(mysqli_stmt_get_result($fetchStmt));
            mysqli_stmt_close($fetchStmt);

            if (!$empRes) throw new Exception("Employee record not found.");

            $newStatus = ($empRes['IsActive'] === 1) ? 0 : 1;
            
            $updStmt = mysqli_prepare($conn, "UPDATE staff_members SET IsActive = ? WHERE StaffID = ?");
            mysqli_stmt_bind_param($updStmt, "ii", $newStatus, $staffID);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            $newStatusLabel = ($newStatus === 1) ? 'Active' : 'Inactive';
            logActivity($conn, $userID, 'Employee Status Toggled', "Changed status for {$empRes['FirstName']} {$empRes['LastName']} to {$newStatusLabel}.");
            redirectWithMessage('success', "Employee status updated to {$newStatusLabel}.", "employees.php?is_active={$newStatus}&tab={$tabTarget}&");
        } elseif ($action === 'delete-employee') {
            $staffID = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
            if (!$staffID) throw new Exception("Invalid employee ID specified for deletion.");

            $fetchStmt = mysqli_prepare($conn, "SELECT FirstName, LastName FROM staff_members WHERE StaffID = ?");
            mysqli_stmt_bind_param($fetchStmt, "i", $staffID);
            mysqli_stmt_execute($fetchStmt);
            $empRes = mysqli_fetch_assoc(mysqli_stmt_get_result($fetchStmt));
            mysqli_stmt_close($fetchStmt);

            $delStmt = mysqli_prepare($conn, "DELETE FROM staff_members WHERE StaffID = ?");
            mysqli_stmt_bind_param($delStmt, "i", $staffID);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);

            $empName = $empRes ? "{$empRes['FirstName']} {$empRes['LastName']}" : "ID #$staffID";
            logActivity($conn, $userID, 'Employee Deleted', "Permanently deleted personnel record: {$empName}.");
            redirectWithMessage('success', 'Employee record permanently deleted.', "employees.php?tab={$tabTarget}&");
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        logActivity($conn, $userID, 'Employee Action Failed', $errorMsg);
        redirectWithMessage('error', $errorMsg, "employees.php?tab={$tabTarget}&");
    }
}

// Retrieve URL Query Parameters for State Management
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'office';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$statusFilter = isset($_GET['is_active']) ? intval($_GET['is_active']) : 1;

// Counts for Tabs
$facultyCountQuery = mysqli_query($conn, "SELECT COUNT(DISTINCT s.StaffID) AS cnt FROM staff_members s INNER JOIN staff_designations sd ON s.StaffID = sd.StaffID WHERE sd.AppointmentType = 'Academic' AND s.IsActive = $statusFilter");
$facultyCount = mysqli_fetch_assoc($facultyCountQuery)['cnt'] ?? 0;

$officeCountQuery = mysqli_query($conn, "SELECT COUNT(DISTINCT s.StaffID) AS cnt FROM staff_members s LEFT JOIN staff_designations sd ON s.StaffID = sd.StaffID WHERE s.IsActive = $statusFilter");
$officeCount = mysqli_fetch_assoc($officeCountQuery)['cnt'] ?? 0;

// Build Filter Query for Paginated List
$whereClauses = array();
$params = array();
$types = "";

$whereClauses[] = "(s.FirstName LIKE ? OR s.MiddleName LIKE ? OR s.LastName LIKE ? OR s.EmployeeNumber LIKE ? OR s.HomeAddress LIKE ? OR s.ContactNumber LIKE ? OR s.EmploymentStatus LIKE ? OR s.PositionTitle LIKE ?) AND s.IsActive = ?";
$searchTerm = "%{$search}%";
array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $statusFilter);
$types .= "ssssssssi";

$whereString = implode(" AND ", $whereClauses);

if ($tab === 'faculty') {
    $countSql = "SELECT COUNT(DISTINCT s.StaffID) as total FROM staff_members s INNER JOIN staff_designations sd ON s.StaffID = sd.StaffID WHERE sd.AppointmentType = 'Academic' AND $whereString";
} else {
    $countSql = "SELECT COUNT(DISTINCT s.StaffID) as total FROM staff_members s LEFT JOIN staff_designations sd ON s.StaffID = sd.StaffID WHERE $whereString";
}

$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalRecords = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0;
mysqli_stmt_close($countStmt);

$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Paginated Employees based on Tab
if ($tab === 'faculty') {
    $dataSql = "SELECT DISTINCT s.* FROM staff_members s INNER JOIN staff_designations sd ON s.StaffID = sd.StaffID WHERE sd.AppointmentType = 'Academic' AND $whereString ORDER BY s.LastName, s.FirstName ASC LIMIT ? OFFSET ?";
} else {
    $dataSql = "SELECT DISTINCT s.* FROM staff_members s LEFT JOIN staff_designations sd ON s.StaffID = sd.StaffID WHERE $whereString ORDER BY s.LastName, s.FirstName ASC LIMIT ? OFFSET ?";
}

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = mysqli_prepare($conn, $dataSql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employeesList = [];
while ($row = mysqli_fetch_assoc($result)) {
    $employeesList[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch active offices for administrative designations
$officesQuery = "SELECT ID, OfficeCode, OfficeName, Type FROM offices WHERE IsActive = 1 ORDER BY OfficeName ASC";
$officesResult = mysqli_query($conn, $officesQuery);
$officesList = [];
while ($row = mysqli_fetch_assoc($officesResult)) {
    $officesList[] = $row;
}

// Fetch active academic years for the teaching load filter dropdown
$ayQuery = "SELECT ID, YearCode, Semester FROM academic_years ORDER BY ID DESC";
$ayResult = mysqli_query($conn, $ayQuery);
$academicYearsList = [];
while ($ayRow = mysqli_fetch_assoc($ayResult)) {
    $academicYearsList[] = $ayRow;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .employees-container { display: flex; flex-direction: column; gap: 20px; }
        .employee-tabs { display: flex; gap: 10px; border-bottom: 2px solid var(--border-color); margin-bottom: 5px; }
        .tab-btn { padding: 10px 20px; font-size: 0.95rem; font-weight: 600; background: transparent; border: none; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s ease; }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: #ffffff; border-top-left-radius: 6px; border-top-right-radius: 6px; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-top: 1px solid var(--border-color); }
        .filter-toolbar { display: flex; gap: 15px; background: #ffffff; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .toolbar-left { display: flex; gap: 12px; flex: 1; flex-wrap: wrap; }
        .filter-toolbar input, .filter-toolbar select { padding: 9px 14px; font-size: 0.9rem; border: 1px solid var(--border-color); border-radius: 6px; outline: none; }
        .filter-toolbar input { min-width: 240px; flex: 1; }
        .employees-table-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .employees-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        .employees-table th, .employees-table td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .employees-table th { background-color: #f8fafc; color: var(--primary); font-weight: 700; }
        .employees-table tr:hover { background-color: #f8fafc; }
        .employee-info-cell { display: flex; align-items: center; gap: 12px; }
        .employee-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); background-color: #e2e8f0; flex-shrink: 0; }
        .employee-name { font-weight: 600; color: var(--primary); margin: 0 0 2px 0; }
        .employee-subtext { font-size: 0.8rem; color: var(--text-muted); }
        .badge-status { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .badge-status.active { background-color: #d1fae5; color: #065f46; }
        .badge-status.inactive { background-color: #fee2e2; color: #991b1b; }
        .badge-designation { padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background-color: #f1f5f9; color: var(--primary); border: 1px solid var(--border-color); display: inline-block; margin: 2px 2px 2px 0; }
        .action-btns { display: flex; gap: 6px; }

        /* Improved View Employee Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background: #ffffff; width: 92%; max-width: 900px; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); overflow: hidden; transform: scale(0.95); transition: transform 0.2s ease; display: flex; flex-direction: column; max-height: 92vh; }
        .modal-header { padding: 18px 24px; background: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .modal-header h3 { font-size: 1.15rem; margin: 0; color: #ffffff; display: flex; align-items: center; gap: 10px; }
        .modal-close-btn { background: transparent; border: none; color: #ffffff; font-size: 1.3rem; cursor: pointer; }
        .modal-body { padding: 24px; overflow-y: auto; flex-grow: 1; background: #f8fafc; }
        .modal-footer { padding: 16px 24px; background: #ffffff; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }

        /* Dossier Hero Header Card */
        .dossier-hero { background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 22px; display: flex; gap: 24px; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); flex-wrap: wrap; }
        .dossier-avatar-wrap { position: relative; flex-shrink: 0; }
        .dossier-avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); box-shadow: 0 4px 6px rgba(0,0,0,0.08); }
        .dossier-details { flex: 1; min-width: 280px; }
        .dossier-name { font-size: 1.3rem; font-weight: 700; color: var(--primary); margin: 0 0 4px 0; }
        .dossier-badge-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .dossier-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; font-size: 0.88rem; color: var(--text-color); }
        .dossier-grid-item strong { color: var(--primary); }

        /* Section Card Design Inside Modal */
        .dossier-section-card { background: #ffffff; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-top: 20px; }
        .dossier-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .dossier-section-title { margin: 0; color: var(--primary); font-size: 1.05rem; display: flex; align-items: center; gap: 8px; font-weight: 700; }

        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px; }
        .form-grid-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px; margin-bottom: 12px; }
        .form-grid-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 12px; }
        @media (max-width: 768px) { .form-grid-2, .form-grid-3, .form-grid-4 { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Employee Directory & Management</h2>
                    <p class="text-muted">Manage college personnel, designations, teaching loads, and profiles.</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEmployeeModal('add')">
                        <i class="fa-solid fa-user-plus"></i> Add New Employee
                    </button>
                </div>
            </div>

            <div class="employees-container">
                <div class="employee-tabs">                    
                    <button type="button" class="tab-btn <?= $tab === 'office' ? 'active' : '' ?>" onclick="switchTab('office')">
                        <i class="fa-solid fa-building-user"></i> All Personnel (<?= $officeCount ?>)
                    </button>
                    <button type="button" class="tab-btn <?= $tab === 'faculty' ? 'active' : '' ?>" onclick="switchTab('faculty')">
                        <i class="fa-solid fa-chalkboard-user"></i> Faculty Personnel (<?= $facultyCount ?>)
                    </button>
                </div>

                <form method="GET" action="employees" class="filter-toolbar" id="filterForm">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <div class="toolbar-left">
                        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, ID, or email...">
                        <select name="is_active" id="statusFilter" onchange="this.form.submit()">
                            <option value="1" <?= $statusFilter === 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $statusFilter === 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </form>

                <div class="employees-table-card">
                    <div style="overflow-x: auto;">
                        <table class="employees-table" id="employeesTable">
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Employee ID</th>
                                    <th>Employment Status</th>
                                    <th>Contact Number</th>
                                    <th>Account State</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($employeesList)): ?>
                                    <?php foreach ($employeesList as $emp): 
                                        $empUserID = $emp['UserID'] ?? 0;
                                        $fullName = trim("{$emp['FirstName']} " . (!empty($emp['MiddleName']) ? substr($emp['MiddleName'], 0, 1) . '. ' : '') . "{$emp['LastName']} " . ($emp['ExtName'] ?? ''));
                                        $statusStr = ($emp['IsActive'] === 0) ? 'Inactive' : 'Active';
                                        $statusClass = strtolower($statusStr);
                                        $avatarImg = !empty($emp['ProfilePicture']) ? '/amsc_indaw/assets/uploads/profiles/' . $emp['ProfilePicture'] : $defaultProfilePhoto;

                                        $emailSql = "SELECT Email FROM users WHERE ID = $empUserID LIMIT 1";
                                        $emailResult = mysqli_query($conn, $emailSql);
                                        $email = mysqli_fetch_assoc($emailResult)['Email'] ?? '';

                                        $dateAdded = date('F d, Y | h:i a', strtotime($emp['CreatedAt']));
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="employee-info-cell">
                                                    <img src="<?= htmlspecialchars($avatarImg) ?>" alt="Avatar" class="employee-avatar">
                                                    <div>
                                                        <h4 class="employee-name"><?= htmlspecialchars($fullName) ?></h4>
                                                        <span class="employee-subtext"><?= htmlspecialchars($emp['PositionTitle'] ?? '') ?></span><br>
                                                        <span class="employee-subtext"><?= htmlspecialchars($email ?? '') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><strong><?= htmlspecialchars($emp['EmployeeNumber'] ?? '') ?></strong></td>
                                            <td><span class="badge-designation"><?= ucfirst($emp['EmploymentStatus'] ?? 'Faculty') ?></span></td>
                                            <td><?= htmlspecialchars($emp['ContactNumber'] ?? '') ?></td>
                                            <td>
                                                <span class="badge-status <?= $statusClass ?>">
                                                    <i class="fa-solid <?= $statusClass === 'active' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> 
                                                    <?= ucfirst($statusStr) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="openViewModal(<?= htmlspecialchars(json_encode($emp)) ?>, '<?= $email ?>', '<?= $avatarImg ?>', '<?= $dateAdded ?>')" title="View Dossier"><i class="fa-solid fa-eye"></i></button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEmployeeModal('edit', <?= htmlspecialchars(json_encode($emp)) ?>)" title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                                    
                                                    <form action="employees?tab=<?= $tab ?>&page=<?= $page ?>" method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle-status">
                                                        <input type="hidden" name="staff_id" value="<?= $emp['StaffID'] ?>">
                                                        <input type="hidden" name="tab_target" value="<?= $tab ?>">
                                                        <input type="hidden" name="page" value="<?= $page ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="Toggle Status"><i class="fa-solid fa-power-off"></i></button>
                                                    </form>

                                                    <form action="employees?tab=<?= $tab ?>&page=<?= $page ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                                                        <input type="hidden" name="action" value="delete-employee">
                                                        <input type="hidden" name="staff_id" value="<?= $emp['StaffID'] ?>">
                                                        <input type="hidden" name="tab_target" value="<?= $tab ?>">
                                                        <input type="hidden" name="page" value="<?= $page ?>">
                                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 25px; color: var(--text-muted);">No employee records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-footer" style="padding: 16px 20px; background-color: #f8fafc; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <span class="text-muted" style="font-size: 0.85rem;">Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (Total: <?= $totalRecords ?> records)</span>
                        <div class="pagination-controls" style="display: flex; gap: 5px;">
                            <?php if ($page > 1): ?>
                                <a href="employees?tab=<?= $tab ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&is_active=<?= $statusFilter ?>" class="btn btn-secondary btn-sm">Prev</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">Prev</button>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="employees?tab=<?= $tab ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&is_active=<?= $statusFilter ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="employees?tab=<?= $tab ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&is_active=<?= $statusFilter ?>" class="btn btn-secondary btn-sm">Next</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.5;">Next</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- IMPROVED VIEW EMPLOYEE DOSSIER MODAL -->
    <div id="viewEmployeeModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-id-card"></i> Complete Employee Dossier</h3>
                <button type="button" class="modal-close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentViewStaffID" value="">
                
                <!-- Hero Profile Card -->
                <div class="dossier-hero">
                    <div class="dossier-avatar-wrap">
                        <img id="viewAvatar" src="<?= $defaultProfilePhoto ?>" alt="Profile" class="dossier-avatar">
                    </div>
                    <div class="dossier-details">
                        <h3 id="viewName" class="dossier-name">Employee Name</h3>
                        <div class="dossier-badge-row">
                            <span class="badge-designation" id="viewPositionTitle">Position</span>
                            <span class="badge-designation" id="viewEmploymentStatus" style="background: #e0f2fe; color: #0369a1;">Employment Status</span>
                        </div>
                        <div class="dossier-grid">
                            <div class="dossier-grid-item"><strong>Employee ID:</strong> <span id="viewId">---</span></div>
                            <div class="dossier-grid-item"><strong>Gender:</strong> <span id="viewGender">---</span></div>
                            <div class="dossier-grid-item"><strong>Date of Birth:</strong> <span id="viewDob">---</span></div>
                            <div class="dossier-grid-item"><strong>Contact No:</strong> <span id="viewContact">---</span></div>
                            <div class="dossier-grid-item"><strong>Email Address:</strong> <span id="viewEmail">---</span></div>
                            <div class="dossier-grid-item"><strong>Date Added:</strong> <span id="viewDateAdded">---</span></div>
                            <div class="dossier-grid-item" style="grid-column: span 2;"><strong>Residential Address:</strong> <span id="viewAddress">---</span></div>
                        </div>
                    </div>
                </div>

                <!-- DESIGNATIONS TABLE SECTION -->
                <div class="dossier-section-card">
                    <div class="dossier-section-header">
                        <h4 class="dossier-section-title"><i class="fa-solid fa-briefcase" style="color: var(--primary-accent);"></i> Assigned Designations</h4>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openDesignationFormModal()"><i class="fa-solid fa-plus"></i> Add Designation</button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="employees-table" style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Appointment Type</th>
                                    <th>Office / College Unit</th>
                                    <th>Primary</th>
                                    <th>Start Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="viewDesignationsTableBody">
                                <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Loading designations...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TEACHING LOAD SECTION FOR FACULTY -->
                <div id="facultyLoadSection" class="dossier-section-card" style="display: none;">
                    <div class="dossier-section-header">
                        <h4 class="dossier-section-title">
                            <i class="fa-solid fa-chalkboard" style="color: var(--primary-accent);"></i> Assigned Teaching Load & Schedules
                        </h4>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select id="filterAcademicYear" class="form-control" style="padding: 6px 10px; font-size: 0.85rem;" onchange="loadTeachingLoadData()">
                                <?php foreach ($academicYearsList as $ay): ?>
                                    <option value="<?= $ay['ID'] ?>">AY <?= htmlspecialchars($ay['YearCode']) ?> (<?= htmlspecialchars($ay['Semester']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="employees-table" style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Subject Code & Title</th>
                                    <th>Units</th>
                                    <th>Schedule & Room</th>
                                </tr>
                            </thead>
                            <tbody id="viewTeachingLoadTableBody">
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">Loading teaching load...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 12px; text-align: right; font-weight: bold; font-size: 0.9rem; color: var(--primary);">
                        Total Faculty Load Units: <span id="totalFacultyUnits">0.0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- INLINE ADD/EDIT DESIGNATION MODAL -->
    <div id="designationFormModal" class="modal-overlay" style="z-index: 2500;">
        <div class="modal-card" style="max-width: 480px; max-height: auto;">
            <div class="modal-header">
                <h3 id="desModalTitle"><i class="fa-solid fa-briefcase"></i> Add Designation</h3>
                <button type="button" class="modal-close-btn" onclick="closeDesignationFormModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="designationForm" onsubmit="saveDesignationAjax(event)">
                    <input type="hidden" id="desIdField" value="">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Job Title *</label>
                        <input type="text" id="desTitleInput" class="form-control" required placeholder="e.g. Associate Professor / Registrar">
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 0.85rem; font-weight: 600;">Appointment Type *</label>
                            <select id="desAppointmentType" class="form-control" required onchange="filterOfficesByType()">
                                <option value="Academic">Academic</option>
                                <option value="Administrative">Administrative</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 0.85rem; font-weight: 600;">Start Date *</label>
                            <input type="date" id="desStartDate" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Office / College Unit *</label>
                        <select id="desOfficeID" class="form-control" required>
                            <option value="">-- Select Office --</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" id="desPrimaryCheckbox" value="1"> Is Primary Designation?
                        </label>
                    </div>
                    <div class="modal-footer" style="margin: 20px -24px -24px -24px;">
                        <button type="button" class="btn btn-secondary" onclick="closeDesignationFormModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Designation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CREATE / EDIT EMPLOYEE MODAL -->
    <div id="employeeModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 750px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-user-plus"></i> Add New Employee</h3>
                <button type="button" class="modal-close-btn" onclick="closeEmployeeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employeeForm" action="employees?tab=<?= $tab ?>&page=<?= $page ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save-employee">
                    <input type="hidden" name="staff_id" id="empStaffIdField" value="">
                    <input type="hidden" name="tab_target" value="<?= $tab ?>">
                    <input type="hidden" name="page" value="<?= $page ?>">

                    <div class="form-grid-2">
                        <div style="display: flex; flex-direction: row; align-items: center;">
                            <img src="<?= $defaultProfilePhoto ?>" alt="Preview" class="employee-avatar" id="empProfilePicturePreview" style="width: 70px; height: 70px; object-fit: cover; margin-right: 12px;">
                            <div class="form-group" style="flex: 1;">
                                <label style="font-size: 0.85rem; font-weight: 600;">Profile Picture</label>
                                <input type="file" name="profile_picture" class="form-control" accept="image/*" onchange="previewAvatar(event);">
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Employee Number ID *</label>
                            <input type="text" name="employee_number" id="empNumberId" class="form-control" required placeholder="e.g. AMSC-EMP-202600001">
                        </div>
                    </div>

                    <div class="form-grid-4">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">First Name *</label>
                            <input type="text" name="first_name" id="empFirstName" class="form-control" required placeholder="First Name">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Middle Name</label>
                            <input type="text" name="middle_name" id="empMiddleName" class="form-control" placeholder="Middle Name">
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Last Name *</label>
                            <input type="text" name="last_name" id="empLastName" class="form-control" required placeholder="Last Name">
                        </div>                        
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Extension Name</label>
                            <select name="ext_name" id="empExtName" class="form-control">
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Gender *</label>
                            <select name="gender" id="empGender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Date of Birth *</label>
                            <input type="date" name="dob" id="empDob" max="<?php echo $maxDate; ?>" class="form-control">
                        </div>               
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Contact Number</label>
                            <input type="text" name="contact_number" id="empContact" class="form-control" placeholder="09123456789">
                        </div>
                    </div>

                    <div class="form-grid-2">     
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Position</label>
                            <input type="text" name="position_title" id="empPosition" class="form-control" placeholder="e.g., Admin Officer I">
                        </div>    
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Employment Status *</label>
                            <select name="emp_status" id="empStatus" class="form-control" required>
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Contractual">Contractual</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Residential Address</label>
                        <input type="text" name="address" id="empAddress" class="form-control" placeholder="Street, Barangay, Municipality/City, Province">
                    </div>

                    <div class="modal-footer" style="margin: 20px -24px -24px -24px;">
                        <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "../includes/snackbar.php"; ?>

    <script>
        const allOffices = <?= json_encode($officesList) ?>;

        const employeeModal = document.getElementById('employeeModal');
        const viewEmployeeModal = document.getElementById('viewEmployeeModal');
        const designationFormModal = document.getElementById('designationFormModal');
        const modalTitle = document.getElementById('modalTitle');
        const searchInput = document.getElementById('searchInput');
        const defaultAvatar = '<?= $defaultProfilePhoto ?>';

        function switchTab(tab) {
            window.location.href = `employees?tab=${tab}&page=1&is_active=<?= $statusFilter ?>`;
        }

        function openEmployeeModal(mode, emp = null) {
            if (mode === 'add') {
                modalTitle.innerHTML = `<i class="fa-solid fa-user-plus"></i> Add New Employee`;
                document.getElementById('employeeForm').reset();
                document.getElementById('empStaffIdField').value = '';
                document.getElementById('empProfilePicturePreview').src = defaultAvatar;

                fetch('employees?ajax_action=get_next_employee_number')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.employee_number) {
                            document.getElementById('empNumberId').value = data.employee_number;
                        }
                    })
                    .catch(err => console.error('Error auto-generating Employee Number:', err));

            } else if (emp) {
                modalTitle.innerHTML = `<i class="fa-solid fa-pen"></i> Edit Employee Record (${emp.EmployeeNumber})`;
                document.getElementById('empStaffIdField').value = emp.StaffID;
                document.getElementById('empNumberId').value = emp.EmployeeNumber || '';
                document.getElementById('empFirstName').value = emp.FirstName || '';
                document.getElementById('empMiddleName').value = emp.MiddleName || '';
                document.getElementById('empLastName').value = emp.LastName || '';
                document.getElementById('empExtName').value = emp.ExtName || '';
                document.getElementById('empGender').value = emp.Gender || '';
                document.getElementById('empDob').value = emp.DateOfBirth || '';
                document.getElementById('empContact').value = emp.ContactNumber || '';
                document.getElementById('empAddress').value = emp.HomeAddress || '';
                document.getElementById('empStatus').value = emp.EmploymentStatus || 'Full-Time';
                document.getElementById('empPosition').value = emp.PositionTitle || '';
                document.getElementById('empProfilePicturePreview').src = emp.ProfilePicture ? "/amsc_indaw/assets/uploads/profiles/" + emp.ProfilePicture : defaultAvatar;
            }
            employeeModal.classList.add('active');
        }

        function closeEmployeeModal() {
            employeeModal.classList.remove('active');
        }

        function openViewModal(emp, email, img, dateAdded) {
            let months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            let birthday = 'N/A';
            if (emp.DateOfBirth && emp.DateOfBirth !== '0000-00-00') {
                const d = new Date(emp.DateOfBirth);
                if (!isNaN(d.getTime())) {
                    birthday = months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
                }
            }

            const fullName = `${emp.FirstName} ${emp.MiddleName ? emp.MiddleName + ' ' : ''}${emp.LastName} ${emp.ExtName || ''}`;
            
            document.getElementById('viewName').textContent = fullName;
            document.getElementById('viewId').textContent = emp.EmployeeNumber || 'N/A';
            document.getElementById('viewGender').textContent = emp.Gender || 'N/A';
            document.getElementById('viewDob').textContent = birthday;
            document.getElementById('viewContact').textContent = emp.ContactNumber || 'N/A';
            document.getElementById('viewEmail').textContent = email || 'N/A';
            document.getElementById('viewAddress').textContent = emp.HomeAddress || 'N/A';
            document.getElementById('viewEmploymentStatus').textContent = emp.EmploymentStatus || 'Full-Time';
            document.getElementById('viewAvatar').src = img;
            document.getElementById('currentViewStaffID').value = emp.StaffID;
            document.getElementById('viewDateAdded').textContent = dateAdded || 'N/A';
            document.getElementById('viewPositionTitle').textContent = emp.PositionTitle || 'Academic Staff';

            const facultyLoadSection = document.getElementById('facultyLoadSection');
            const currentTab = "<?= $tab ?>";
            if (currentTab === 'faculty') {
                facultyLoadSection.style.display = 'block';
                loadTeachingLoadData();
            } else {
                facultyLoadSection.style.display = 'none';
            }

            loadDesignationsData(emp.StaffID);
            viewEmployeeModal.classList.add('active');
        }

        function closeViewModal() {
            viewEmployeeModal.classList.remove('active');
        }

        function filterOfficesByType(selectedOfficeID = '') {
            const appointmentType = document.getElementById('desAppointmentType').value;
            const officeSelect = document.getElementById('desOfficeID');
            officeSelect.innerHTML = `<option value="">-- Select Office --</option>`;

            const filtered = allOffices.filter(o => o.Type === appointmentType);

            filtered.forEach(o => {
                const option = document.createElement('option');
                option.value = o.ID;
                option.textContent = `${o.OfficeName} (${o.OfficeCode})`;
                if (selectedOfficeID && o.ID == selectedOfficeID) {
                    option.selected = true;
                }
                officeSelect.appendChild(option);
            });
        }

        function loadDesignationsData(staffID) {
            const tbody = document.getElementById('viewDesignationsTableBody');
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Loading designations...</td></tr>`;

            const xhr = new XMLHttpRequest();
            xhr.open('GET', `employees?ajax_action=get_designations&staff_id=${staffID}`, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success && res.designations.length > 0) {
                            let html = '';
                            res.designations.forEach(d => {
                                const officeLabel = d.OfficeName ? `${d.OfficeName} (${d.OfficeCode})` : 'No Office Assigned';
                                html += `
                                    <tr>
                                        <td><strong>${escapeHtml(d.JobTitle)}</strong></td>
                                        <td>${escapeHtml(d.AppointmentType)}</td>
                                        <td>${escapeHtml(officeLabel)}</td>
                                        <td>${d.IsPrimary == 1 ? '<span class="badge-status active">Yes</span>' : '<span class="badge-status inactive">No</span>'}</td>
                                        <td>${escapeHtml(d.StartDate)}</td>
                                        <td>
                                            <div class="action-btns">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="openDesignationFormModal(${d.ID}, '${escapeHtml(d.JobTitle)}', '${escapeHtml(d.AppointmentType)}', '${d.OfficeID || ''}', ${d.IsPrimary}, '${escapeHtml(d.StartDate)}')"><i class="fa-solid fa-pen"></i></button>
                                                <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="deleteDesignationAjax(${d.ID})"><i class="fa-solid fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            });
                            tbody.innerHTML = html;
                        } else {
                            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No designations assigned yet.</td></tr>`;
                        }
                    } catch (e) {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #dc2626;">Error parsing designations.</td></tr>`;
                    }
                }
            };
            xhr.send();
        }

        function openDesignationFormModal(id = '', title = '', appointmentType = 'Academic', officeID = '', isPrimary = 0, startDate = '') {
            document.getElementById('desIdField').value = id;
            document.getElementById('desTitleInput').value = title;
            document.getElementById('desAppointmentType').value = appointmentType;
            filterOfficesByType(officeID);
            document.getElementById('desPrimaryCheckbox').checked = (isPrimary == 1);
            if (startDate) document.getElementById('desStartDate').value = startDate;
            document.getElementById('desModalTitle').innerHTML = id ? `<i class="fa-solid fa-pen"></i> Edit Designation` : `<i class="fa-solid fa-briefcase"></i> Add Designation`;
            designationFormModal.classList.add('active');
        }

        function closeDesignationFormModal() {
            designationFormModal.classList.remove('active');
        }

        function saveDesignationAjax(event) {
            event.preventDefault();
            const staffID = document.getElementById('currentViewStaffID').value;
            const desID = document.getElementById('desIdField').value;
            const title = document.getElementById('desTitleInput').value.trim();
            const appointmentType = document.getElementById('desAppointmentType').value;
            const officeID = document.getElementById('desOfficeID').value;
            const isPrimary = document.getElementById('desPrimaryCheckbox').checked ? 1 : 0;
            const startDate = document.getElementById('desStartDate').value;

            const formData = new FormData();
            formData.append('designation_id', desID);
            formData.append('staff_id', staffID);
            formData.append('job_title', title);
            formData.append('appointment_type', appointmentType);
            formData.append('office_id', officeID);
            formData.append('is_primary', isPrimary);
            formData.append('start_date', startDate);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'employees?ajax_action=save_designation', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            closeDesignationFormModal();
                            loadDesignationsData(staffID);
                        } else {
                            alert(res.message || 'Failed to save designation.');
                        }
                    } catch (e) {
                        alert('Error saving designation.');
                    }
                }
            };
            xhr.send(formData);
        }

        function deleteDesignationAjax(desID) {
            if (!confirm('Are you sure you want to remove this designation?')) return;
            const staffID = document.getElementById('currentViewStaffID').value;

            const formData = new FormData();
            formData.append('designation_id', desID);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'employees?ajax_action=delete_designation', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            loadDesignationsData(staffID);
                        } else {
                            alert('Failed to delete designation.');
                        }
                    } catch (e) {
                        alert('Error deleting designation.');
                    }
                }
            };
            xhr.send(formData);
        }

        function loadTeachingLoadData() {
            const staffID = document.getElementById('currentViewStaffID').value;
            const ay = document.getElementById('filterAcademicYear').value;
            const tbody = document.getElementById('viewTeachingLoadTableBody');

            tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--text-muted);">Loading teaching load...</td></tr>`;

            const xhr = new XMLHttpRequest();
            xhr.open('GET', `employees?ajax_action=get_teaching_load&staff_id=${staffID}&academic_year=${encodeURIComponent(ay)}`, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success && res.loads.length > 0) {
                            let html = '';
                            res.loads.forEach(l => {
                                html += `
                                    <tr>
                                        <td><strong><a href="class_view?id=${l.ClassID}" style="color: var(--primary); text-decoration: none;" target="_blank">${escapeHtml(l.ClassName)} <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.75rem; margin-left: 3px;"></i></a></strong></td>
                                        <td>${escapeHtml(l.SubjectCode)} - ${escapeHtml(l.SubjectTitle)}</td>
                                        <td><span class="badge-designation">${l.TotalUnits} Units</span></td>
                                        <td>${escapeHtml(l.ScheduleDisplay || 'No Schedule Assigned')}</td>
                                    </tr>
                                `;
                            });
                            tbody.innerHTML = html;
                            document.getElementById('totalFacultyUnits').textContent = parseFloat(res.totalUnits).toFixed(1);
                        } else {
                            tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No teaching load recorded for selected Academic Year & Semester.</td></tr>`;
                            document.getElementById('totalFacultyUnits').textContent = '0.0';
                        }
                    } catch (e) {
                        tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #dc2626;">Error parsing teaching load.</td></tr>`;
                    }
                }
            };
            xhr.send();
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('empProfilePicturePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }

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