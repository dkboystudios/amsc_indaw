<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Application Requirements - AMSC InDaw";

// Ensure applicant profile exists
$applicantID = $userInfo['ApplicantID'] ?? 0;
if (!$applicantID) {
    redirectWithMessage('error', 'Please complete your personal profile first.', 'profile?');
}

// Full alphabetized list of document types
$documentTypes = [
    "Alien Certificate of Registration",
    "Birth Certificate",
    "Certificate of Good Moral",
    "Certificate of Graduation",
    "Certificate of Indigency",
    "Certificate of Transfer Credential",
    "College Admission Test",
    "Course description / syllabi (for crediting)",
    "Diploma",
    "English Proficiency Test results",
    "Honorable Dismissal",
    "Income Tax Return (ITR) of parents",
    "Letters of Recommendation",
    "National Career Assessment Examination (NCAE) results",
    "Official Transcript of Records",
    "Others",
    "Senior High School Report Card",
    "Senior High School Transcript of Records (Form 137 / SF 10)",
    "Student Visa / Permit"
];

// -------------------------------------------------------------------------
// POST / ACTION HANDLERS
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        // 1. Submit Application for Review
        if ($action === 'submit_application') {
            // Check if at least one document is uploaded
            $chkDocs = mysqli_query($conn, "SELECT COUNT(*) AS total FROM applicant_documents WHERE ApplicantID = $applicantID");
            $docRow = mysqli_fetch_assoc($chkDocs);
            if (($docRow['total'] ?? 0) == 0) {
                throw new Exception("Please upload at least one requirement before submitting your application.");
            }

            $stmt = mysqli_prepare($conn, "UPDATE applicants SET ApplicationStatus = 'Under Review', UpdatedAt = NOW() WHERE ApplicantID = ?");
            mysqli_stmt_bind_param($stmt, 'i', $applicantID);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            logActivity($conn, $userID, 'Application Submitted', 'Applicant submitted their application for review.');
            redirectWithMessage('success', 'Application successfully submitted for review!', 'documents?');
        }

        // 2. Upload / Add Document
        elseif ($action === 'upload_document') {
            $selectedType = trim($_POST['document_type'] ?? '');
            $customType = trim($_POST['custom_document_type'] ?? '');
            
            $docType = ($selectedType === 'Others' && !empty($customType)) ? $customType : $selectedType;

            if (empty($docType)) {
                throw new Exception("Please select or specify a valid document type.");
            }

            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception("Please select a file to upload.");
            }

            $file = $_FILES['document_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload failed with error code: " . $file['error']);
            }

            // Validate file size (Max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File size exceeds the 5MB limit.");
            }

            $fileName = basename($file['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (!in_array($fileExtension, $allowedExtensions, true)) {
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedMimeTypes, true)) {
                    throw new Exception("Invalid file format. Only images (JPG, PNG, WEBP) and PDF files are allowed.");
                }
            }

            // Construct Custom Filename: LASTNAME_FIRSTNAME_DocumentType_DATE.EXTENSION
            $lastName = preg_replace('/[^a-zA-Z0-9]/', '', $userInfo['LastName'] ?? 'Applicant');
            $firstName = preg_replace('/[^a-zA-Z0-9]/', '', $userInfo['FirstName'] ?? 'User');
            $cleanDocType = preg_replace('/[^a-zA-Z0-9]/', '', $docType);
            $dateCode = date('dmY');

            $newFileName = "{$lastName}_{$firstName}_{$cleanDocType}_{$dateCode}.{$fileExtension}";
            $targetDir = '../assets/uploads/documents/';
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $destPath = $targetDir . $newFileName;

            // If file already exists with same name, append unique suffix
            $counter = 1;
            while (file_exists($destPath)) {
                $newFileName = "{$lastName}_{$firstName}_{$cleanDocType}_{$dateCode}_{$counter}.{$fileExtension}";
                $destPath = $targetDir . $newFileName;
                $counter++;
            }

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new Exception("Failed to move uploaded file to server directory.");
            }

            // Save filename only to DB
            $insStmt = mysqli_prepare($conn, "INSERT INTO applicant_documents (ApplicantID, DocumentType, FilePath, VerificationStatus) VALUES (?, ?, ?, 'Pending')");
            mysqli_stmt_bind_param($insStmt, 'iss', $applicantID, $docType, $newFileName);
            mysqli_stmt_execute($insStmt);
            mysqli_stmt_close($insStmt);

            logActivity($conn, $userID, 'Document Uploaded', "Uploaded requirement: {$docType} ({$newFileName}).");
            redirectWithMessage('success', 'Document successfully uploaded.', 'documents?');
        }

        // 3. Update Existing Document (Replace file)
        elseif ($action === 'update_document') {
            $docID = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
            if (!$docID) throw new Exception("Invalid document reference.");

            // Fetch old file path
            $oldRes = mysqli_query($conn, "SELECT FilePath, DocumentType FROM applicant_documents WHERE DocumentID = $docID AND ApplicantID = $applicantID LIMIT 1");
            $oldData = mysqli_fetch_assoc($oldRes);
            if (!$oldData) throw new Exception("Document record not found.");

            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception("Please select a new file to replace the existing one.");
            }

            $file = $_FILES['document_file'];
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File size exceeds the 5MB limit.");
            }

            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (!in_array($fileExtension, $allowedExtensions, true)) {
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mimeType, $allowedMimeTypes, true)) {
                    throw new Exception("Invalid file format.");
                }
            }

            // Construct new file name
            $lastName = preg_replace('/[^a-zA-Z0-9]/', '', $userInfo['LastName'] ?? 'Applicant');
            $firstName = preg_replace('/[^a-zA-Z0-9]/', '', $userInfo['FirstName'] ?? 'User');
            $cleanDocType = preg_replace('/[^a-zA-Z0-9]/', '', $oldData['DocumentType']);
            $dateCode = date('dmY');

            $newFileName = "{$lastName}_{$firstName}_{$cleanDocType}_{$dateCode}.{$fileExtension}";
            $targetDir = '../assets/uploads/documents/';
            $destPath = $targetDir . $newFileName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new Exception("Failed to upload replacement file.");
            }

            // Delete old physical file
            $oldFilePath = $targetDir . $oldData['FilePath'];
            if (!empty($oldData['FilePath']) && file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }

            // Update DB record and reset verification status to Pending
            $updStmt = mysqli_prepare($conn, "UPDATE applicant_documents SET FilePath = ?, VerificationStatus = 'Pending', Remarks = NULL, UpdatedAt = NOW() WHERE DocumentID = ?");
            mysqli_stmt_bind_param($updStmt, 'si', $newFileName, $docID);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);

            logActivity($conn, $userID, 'Document Updated', "Replaced document ID #{$docID} with new file: {$newFileName}.");
            redirectWithMessage('success', 'Document successfully updated and re-submitted for verification.', 'documents?');
        }

        // 4. Delete Document
        elseif ($action === 'delete_document') {
            $docID = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
            if (!$docID) throw new Exception("Invalid document reference.");

            // Fetch file path
            $res = mysqli_query($conn, "SELECT FilePath, DocumentType FROM applicant_documents WHERE DocumentID = $docID AND ApplicantID = $applicantID LIMIT 1");
            $data = mysqli_fetch_assoc($res);
            if (!$data) throw new Exception("Document not found.");

            // Remove physical file
            $targetDir = '../assets/uploads/documents/';
            $filePath = $targetDir . $data['FilePath'];
            if (!empty($data['FilePath']) && file_exists($filePath)) {
                @unlink($filePath);
            }

            // Delete DB record
            $delStmt = mysqli_prepare($conn, "DELETE FROM applicant_documents WHERE DocumentID = ?");
            mysqli_stmt_bind_param($delStmt, 'i', $docID);
            mysqli_stmt_execute($delStmt);
            mysqli_stmt_close($delStmt);

            logActivity($conn, $userID, 'Document Deleted', "Deleted requirement: {$data['DocumentType']}.");
            redirectWithMessage('success', 'Document securely removed from system.', 'documents?');
        }

    } catch (Exception $e) {
        redirectWithMessage('error', $e->getMessage(), 'documents?');
    }
}

// Fetch uploaded documents for this applicant
$documentsList = [];
$docRes = mysqli_query($conn, "
    SELECT d.*, u.Username AS VerifierName 
    FROM applicant_documents d 
    LEFT JOIN users u ON d.VerifiedBy = u.ID 
    WHERE d.ApplicantID = $applicantID 
    ORDER BY d.DocumentID DESC
");
while ($dRow = mysqli_fetch_assoc($docRes)) {
    $documentsList[] = $dRow;
}

$appStatus = $userInfo['ApplicationStatus'] ?? 'Pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        .docs-container { display: flex; flex-direction: column; gap: 24px; max-width: 1000px; margin: 0 auto; padding-bottom: 40px; }
        .doc-card { background: #ffffff; border-radius: 10px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .doc-card-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .doc-card-body { padding: 20px; }
        
        .upload-dropzone { border: 2px dashed var(--border-color); padding: 30px; text-align: center; border-radius: 8px; background: #fafafa; cursor: pointer; transition: border-color 0.2s; }
        .upload-dropzone:hover { border-color: var(--primary); }

        .uploaded-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 12px; background: #fff; flex-wrap: wrap; gap: 12px; }
        
        .badge-pending { background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .badge-approved { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .badge-rejected { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }

        /* Document Viewer Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); display: flex; align-items: center; justify-content: center; z-index: 2500; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s ease; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .doc-viewer-card { background: #ffffff; width: 90%; max-width: 900px; height: 85vh; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; }
        .doc-viewer-content { flex: 1; background: #1e293b; display: flex; align-items: center; justify-content: center; position: relative; overflow: auto; }
        .doc-viewer-content img { max-width: 100%; max-height: 100%; transition: transform 0.2s ease; cursor: grab; }
        .doc-viewer-content iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Application Requirements & Credentials</h2>
                    <p class="text-muted">Upload scanned copies of your required academic documents for verification.</p>
                </div>
                <div>
                    <?php if ($appStatus === 'Pending'): ?>
                        <form action="documents" method="POST" onsubmit="return confirmSubmitApplication();" style="display:inline;">
                            <input type="hidden" name="action" value="submit_application">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i> Submit Application
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge badge-approved" style="font-size: 0.9rem; padding: 8px 14px;">
                            Application Status: <strong><?= htmlspecialchars($appStatus) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php include '../includes/snackbar.php'; ?>

            <div class="docs-container">

                <!-- UPLOAD NEW DOCUMENT CARD (Enabled only if Pending) -->
                <?php if ($appStatus === 'Pending'): ?>
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3><i class="fa-solid fa-cloud-arrow-up" style="color: var(--primary);"></i> Upload Requirement</h3>
                    </div>
                    <div class="doc-card-body">
                        <form action="documents" method="POST" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="action" value="upload_document">
                            
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label for="document_type">Document Type <span class="required">*</span></label>
                                    <select id="document_type" name="document_type" class="form-control" required onchange="toggleCustomInput(this.value)">
                                        <option value="">-- Select Document Type --</option>
                                        <?php foreach ($documentTypes as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" id="customTypeGroup" style="display: none;">
                                    <label for="custom_document_type">Specify Other Document <span class="required">*</span></label>
                                    <input type="text" id="custom_document_type" name="custom_document_type" class="form-control" placeholder="Enter document title...">
                                </div>
                                <div class="form-group">
                                    <label for="document_file">Select File (Image or PDF, Max 5MB) <span class="required">*</span></label>
                                    <input type="file" id="document_file" name="document_file" accept=".pdf,image/jpeg,image/png,image/webp" class="form-control" required>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-upload"></i> Upload Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                    <div style="background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; padding: 14px 18px; border-radius: 8px; font-size: 0.92rem;">
                        <i class="fa-solid fa-circle-info"></i> Your application has already been submitted (Status: <strong><?= htmlspecialchars($appStatus) ?></strong>). File uploads and deletions are locked unless returned by the administrator.
                    </div>
                <?php endif; ?>

                <!-- UPLOADED DOCUMENTS DIRECTORY -->
                <div class="doc-card">
                    <div class="doc-card-header">
                        <h3><i class="fa-solid fa-folder-open" style="color: var(--primary);"></i> Submitted Requirements Directory</h3>
                        <span class="text-muted" style="font-size: 0.85rem;">Total Files: <strong><?= count($documentsList) ?></strong></span>
                    </div>
                    <div class="doc-card-body">
                        <?php if (!empty($documentsList)): ?>
                            <?php foreach ($documentsList as $doc): ?>
                                <?php 
                                    $isPdf = strtolower(pathinfo($doc['FilePath'], PATHINFO_EXTENSION)) === 'pdf';
                                    $fileUrl = '../assets/uploads/documents/' . htmlspecialchars($doc['FilePath']);
                                    $status = $doc['VerificationStatus'] ?? 'Pending';
                                    $badgeClass = 'badge-pending';
                                    if ($status === 'Approved') $badgeClass = 'badge-approved';
                                    elseif ($status === 'Rejected') $badgeClass = 'badge-rejected';
                                ?>
                                <div class="uploaded-item">
                                    <div style="flex: 1; min-width: 250px;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                                            <i class="fa-solid <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' ?>" style="font-size: 1.2rem;"></i>
                                            <strong style="font-size: 1rem; color: var(--primary);"><?= htmlspecialchars($doc['DocumentType']) ?></strong>
                                            <span class="<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                        </div>
                                        <span class="text-muted" style="font-size: 0.82rem; display: block; word-break: break-all;">File: <?= htmlspecialchars($doc['FilePath']) ?></span>
                                        
                                        <?php if (!empty($doc['Remarks'])): ?>
                                            <p style="margin: 6px 0 0 0; font-size: 0.85rem; color: #b45309;"><strong>Remarks:</strong> <?= htmlspecialchars($doc['Remarks']) ?> <small class="text-muted">(Verified by: <?= htmlspecialchars($doc['VerifierName'] ?? 'Registrar Staff') ?>)</small></p>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="openDocViewerModal('<?= htmlspecialchars($doc['DocumentType'], ENT_QUOTES) ?>', '<?= $fileUrl ?>', <?= $isPdf ? 'true' : 'false' ?>)">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                        
                                        <a href="<?= $fileUrl ?>" download class="btn btn-secondary btn-sm" style="text-decoration: none;">
                                            <i class="fa-solid fa-download"></i>
                                        </a>

                                        <?php if ($appStatus === 'Pending'): ?>
                                            <!-- Update / Replace Button Trigger -->
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openUpdateModal(<?= $doc['DocumentID'] ?>, '<?= htmlspecialchars($doc['DocumentType'], ENT_QUOTES) ?>')">
                                                <i class="fa-solid fa-rotate"></i> Replace
                                            </button>

                                            <!-- Delete Form -->
                                            <form action="documents" method="POST" onsubmit="return confirm('Are you sure you want to delete this document?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_document">
                                                <input type="hidden" name="document_id" value="<?= $doc['DocumentID'] ?>">
                                                <button type="submit" class="btn btn-sm" style="background: #fee2e2; color: #dc2626;" title="Delete Document">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted" style="text-align: center; padding: 30px; margin: 0;">No documents uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- DOCUMENT VIEWER MODAL (PDF or Image with Zoom & Fullscreen) -->
    <div id="docViewerModal" class="modal-overlay">
        <div class="doc-viewer-card">
            <div class="modal-header" style="padding: 16px 20px; background: var(--primary); color: #fff; display: flex; justify-content: space-between; align-items: center;">
                <h3 id="viewerModalTitle" style="margin: 0; font-size: 1.1rem; color: #fff;"><i class="fa-solid fa-file"></i> Document Viewer</h3>
                <button type="button" class="modal-close-btn" onclick="closeDocViewerModal()" style="background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer;">&times;</button>
            </div>
            <div class="doc-viewer-content" id="viewerContentContainer">
                <!-- Dynamically injected iframe or img -->
            </div>
            <div style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <span class="text-muted" style="font-size: 0.85rem;">AMSC InDaw Secure Viewer (Zoom & Fullscreen supported)</span>
                <div style="display: flex; gap: 10px;" id="viewerControls">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="zoomInDoc()"><i class="fa-solid fa-magnifying-glass-plus"></i> Zoom In</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="zoomOutDoc()"><i class="fa-solid fa-magnifying-glass-minus"></i> Zoom Out</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleFullscreenDoc()"><i class="fa-solid fa-expand"></i> Fullscreen</button>
                    <a id="downloadDocLink" href="#" download class="btn btn-primary btn-sm" style="text-decoration: none;"><i class="fa-solid fa-download"></i> Download</a>
                </div>
            </div>
        </div>
    </div>

    <!-- REPLACE / UPDATE DOCUMENT MODAL -->
    <div id="updateDocModal" class="modal-overlay">
        <div class="modal-card" style="background:#fff; width:100%; max-width:450px; border-radius:10px; overflow:hidden;">
            <div class="modal-header" style="padding: 16px 20px; background: var(--primary); color: #fff; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin:0; font-size:1.1rem; color:#fff;"><i class="fa-solid fa-rotate"></i> Replace Document</h3>
                <button type="button" onclick="closeUpdateModal()" style="background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer;">&times;</button>
            </div>
            <form action="documents" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_document">
                <input type="hidden" name="document_id" id="updateDocID">
                <div class="modal-body" style="padding: 20px; text-align: left;">
                    <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 15px;">Uploading a replacement for: <strong id="updateDocTitleLabel" style="color:var(--primary);"></strong></p>
                    <div class="form-group">
                        <label for="update_file">Select New File (Max 5MB) <span class="required">*</span></label>
                        <input type="file" id="update_file" name="document_file" accept=".pdf,image/jpeg,image/png,image/webp" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Upload & Replace</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Interactivity -->
    <script>
        let currentZoom = 1;

        function toggleCustomInput(val) {
            const group = document.getElementById('customTypeGroup');
            const input = document.getElementById('custom_document_type');
            if (val === 'Others') {
                group.style.display = 'block';
                input.required = true;
            } else {
                group.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }

        function confirmSubmitApplication() {
            return confirm("Are you sure you want to submit your application? Once submitted, you cannot change or delete your uploaded documents unless returned by the admissions office.");
        }

        // Document Viewer Controls
        function openDocViewerModal(title, url, isPdf) {
            document.getElementById('viewerModalTitle').innerHTML = `<i class="fa-solid ${isPdf ? 'fa-file-pdf' : 'fa-file-image'}"></i> ${title}`;
            document.getElementById('downloadDocLink').href = url;
            currentZoom = 1;

            const container = document.getElementById('viewerContentContainer');
            container.innerHTML = '';

            const controls = document.getElementById('viewerControls');

            if (isPdf) {
                if(controls) controls.style.display = 'none'; // hide zoom for pdf iframe
                const iframe = document.createElement('iframe');
                iframe.src = url;
                container.appendChild(iframe);
            } else {
                if(controls) controls.style.display = 'flex';
                const img = document.createElement('img');
                img.id = 'zoomableImage';
                img.src = url;
                img.alt = title;
                container.appendChild(img);
            }

            document.getElementById('docViewerModal').classList.add('active');
        }

        function closeDocViewerModal() {
            document.getElementById('docViewerModal').classList.remove('active');
            document.getElementById('viewerContentContainer').innerHTML = '';
        }

        function zoomInDoc() {
            currentZoom += 0.25;
            const img = document.getElementById('zoomableImage');
            if (img) img.style.transform = `scale(${currentZoom})`;
        }

        function zoomOutDoc() {
            if (currentZoom > 0.5) currentZoom -= 0.25;
            const img = document.getElementById('zoomableImage');
            if (img) img.style.transform = `scale(${currentZoom})`;
        }

        function toggleFullscreenDoc() {
            const card = document.querySelector('.doc-viewer-card');
            if (!document.fullscreenElement) {
                card.requestFullscreen().catch(err => alert(`Fullscreen error: ${err.message}`));
            } else {
                document.exitFullscreen();
            }
        }

        // Replace Modal Controls
        function openUpdateModal(docID, docTitle) {
            document.getElementById('updateDocID').value = docID;
            document.getElementById('updateDocTitleLabel').textContent = docTitle;
            document.getElementById('updateDocModal').classList.add('active');
        }

        function closeUpdateModal() {
            document.getElementById('updateDocModal').classList.remove('active');
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>