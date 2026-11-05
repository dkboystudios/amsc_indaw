<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Certificate of Registration - AMSC InDaw";

// 1. Fetch Student Information Fallback Check
// $studentInfo = null;
// if (isset($userInfo['ApplicantID'])) {
//     $sQuery = mysqli_prepare($conn, "
//         SELECT s.*, p.ProgramName, c.CurriculumName 
//         FROM students s 
//         JOIN programs p ON s.ProgramID = p.ID 
//         JOIN curriculum c ON s.CurriculumID = c.ID 
//         WHERE s.ApplicantID = ? LIMIT 1
//     ");
//     mysqli_stmt_bind_param($sQuery, 'i', $userInfo['ApplicantID']);
//     mysqli_stmt_execute($sQuery);
//     $sRes = mysqli_stmt_get_result($sQuery);
//     if ($sRow = mysqli_fetch_assoc($sRes)) {
//         $studentInfo = $sRow;
//     }
//     mysqli_stmt_close($sQuery);
// }

// 2. Fetch All Available Academic Terms the student has enrollment records for
$availableTerms = [];
$studentID = $studentInfo['StudentID'] ?? 0;
if ($studentID > 0) {
    $termsQuery = mysqli_query($conn, "
        SELECT DISTINCT ay.ID as AcademicYearID, ay.YearCode, ay.Semester, ay.IsActive
        FROM academic_years ay
        JOIN classes cl ON cl.AcademicYearID = ay.ID
        JOIN enrollments e ON e.ClassID = cl.ID
        WHERE e.StudentID = $studentID
        ORDER BY ay.YearCode DESC, ay.Semester DESC
    ");
    while ($tRow = mysqli_fetch_assoc($termsQuery)) {
        $availableTerms[] = $tRow;
    }
}

// Fallback: If no enrollments found yet, fetch active academic year
$activeTermQuery = mysqli_query($conn, "SELECT * FROM academic_years WHERE IsActive = 1 LIMIT 1");
$currentActiveTerm = mysqli_fetch_assoc($activeTermQuery);

// Selected Academic Term ID from GET request or default to active/latest term
$selectedTermID = intval($_GET['term_id'] ?? ($currentActiveTerm['ID'] ?? 0));

// Fetch selected term details
$selectedTermDetails = null;
if ($selectedTermID > 0) {
    $stQuery = mysqli_prepare($conn, "SELECT * FROM academic_years WHERE ID = ? LIMIT 1");
    mysqli_stmt_bind_param($stQuery, 'i', $selectedTermID);
    mysqli_stmt_execute($stQuery);
    $selectedTermDetails = mysqli_fetch_assoc(mysqli_stmt_get_result($stQuery));
    mysqli_stmt_close($stQuery);
}

// 3. Fetch Enrolled Subjects for the Selected Term
$enrolledClasses = [];
$totalUnits = 0;
$studentTermEnrollmentStatus = 'Pre-Enrolled'; // Default fallback

if ($studentID > 0 && $selectedTermID > 0) {
    $enClassQuery = mysqli_prepare($conn, "
        SELECT cl.*, cs.Units, s.SubjectCode, s.SubjectTitle, e.Status as EnrollmentStatus, e.EnrolledOn
        FROM enrollments e
        JOIN classes cl ON e.ClassID = cl.ID
        JOIN curriculum_subjects cs ON cl.CurriculumSubjectID = cs.ID
        JOIN subjects s ON cs.SubjectID = s.ID
        WHERE e.StudentID = ? AND cl.AcademicYearID = ? AND e.Status IN ('Draft', 'Enrolled')
    ");
    mysqli_stmt_bind_param($enClassQuery, 'ii', $studentID, $selectedTermID);
    mysqli_stmt_execute($enClassQuery);
    $enRes = mysqli_stmt_get_result($enClassQuery);
    while ($eRow = mysqli_fetch_assoc($enRes)) {
        $studentTermEnrollmentStatus = $eRow['EnrollmentStatus'];

        // Fetch schedule and rooms for this class
        $schedQuery = mysqli_prepare($conn, "SELECT * FROM class_schedules WHERE ClassID = ?");
        mysqli_stmt_bind_param($schedQuery, 'i', $eRow['ID']);
        mysqli_stmt_execute($schedQuery);
        $schedRes = mysqli_stmt_get_result($schedQuery);
        
        $schedules = [];
        $rooms = [];
        while ($sRow = mysqli_fetch_assoc($schedRes)) {
            $schedules[] = $sRow['DayOfWeek'] . ' ' . date('h:i A', strtotime($sRow['StartTime'])) . '-' . date('h:i A', strtotime($sRow['EndTime']));
            if (!empty($sRow['RoomAssignment'])) {
                $rooms[] = trim($sRow['RoomAssignment']);
            }
        }
        mysqli_stmt_close($schedQuery);

        $eRow['ScheduleStr'] = !empty($schedules) ? implode(' | ', $schedules) : 'TBA';

        // Room processing: Deduplicate identical rooms or join with a slash for different rooms
        $uniqueRooms = array_unique($rooms);
        if (empty($uniqueRooms)) {
            $eRow['RoomStr'] = 'TBA';
        } else {
            $eRow['RoomStr'] = implode(' / ', $uniqueRooms);
        }

        $enrolledClasses[] = $eRow;
        $totalUnits += floatval($eRow['Units']);
    }
    mysqli_stmt_close($enClassQuery);
}

// Check student table global status as additional check
$isOfficiallyEnrolled = ($studentInfo && strtolower(trim($studentInfo['EnrollmentStatus'] ?? '')) === 'enrolled') || strtolower(trim($studentTermEnrollmentStatus)) === 'enrolled';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <!-- Include html2pdf library for direct PDF downloading -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .com-container { max-width: 1050px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .com-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
        .com-header-toolbar { padding: 16px 20px; background-color: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .toolbar-left { display: flex; align-items: center; gap: 12px; }
        .toolbar-left label { font-size: 0.9rem; font-weight: 600; color: var(--text-color); }
        .com-body { padding: 30px; display: flex; justify-content: center; background: #e2e8f0; overflow-x: auto; }

        /* Printable Document Sheet (Landscape Proportion) with Watermark */
        .print-sheet { 
            position: relative;
            background: #ffffff; 
            width: 1056px; 
            min-height: 750px; 
            padding: 40px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            color: #000000; 
            font-family: 'Times New Roman', Times, serif; 
            overflow: hidden;
        }

        /* Watermark Styling */
        .print-sheet::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 480px;
            height: 480px;
            background-image: url('../assets/images/site/amsc.webp');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.07;
            pointer-events: none;
            z-index: 0;
        }

        .print-content-wrapper { position: relative; z-index: 1; }

        .print-header { display: flex; align-items: center; justify-content: space-between; text-align: center; margin-bottom: 15px; border-bottom: 2px solid #1b4332; padding-bottom: 12px; }
        .print-logo { width: 70px; height: 70px; object-fit: contain; }
        .print-header-center { flex: 1; line-height: 1.3; }
        .print-header-center h4 { font-size: 0.9rem; font-weight: normal; margin: 0; }
        .print-header-center h3 { font-size: 1.05rem; font-weight: bold; margin: 2px 0; }
        .print-header-center h2 { font-size: 1.2rem; font-weight: bold; color: #1b4332; margin: 3px 0; }
        .print-header-center p { font-size: 0.85rem; margin: 2px 0; }

        .print-title { text-align: center; margin: 10px 0 20px 0; }
        .print-title h3 { font-size: 1.15rem; font-weight: bold; text-decoration: underline; margin: 0 0 3px 0; letter-spacing: 0.5px; }
        .print-title p { font-size: 0.95rem; font-weight: bold; margin: 0; }

        .student-info-grid { display: grid; grid-template-columns: 2fr 2fr; gap: 10px; margin-bottom: 20px; font-size: 0.95rem; border-bottom: 1px solid #000; padding-bottom: 10px; }

        .print-section-title { font-size: 1rem; font-weight: bold; margin-bottom: 8px; color: #1b4332; text-transform: uppercase; }

        .print-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 0.9rem; margin-bottom: 20px; }
        .print-table th, .print-table td { border: 1px solid #000; padding: 8px 6px; }
        .print-table th { background-color: #f1f5f9; font-weight: bold; }

        .rules-box { font-size: 0.8rem; line-height: 1.4; border: 1px dashed #64748b; padding: 10px 14px; margin-bottom: 20px; background-color: #fdfbf7; }
        .rules-box h5 { margin: 0 0 4px 0; font-size: 0.85rem; font-weight: bold; color: #1b4332; }
        .rules-box ol { margin: 0; padding-left: 18px; }

        .signature-footer { display: flex; justify-content: flex-end; margin-top: 20px; padding-right: 40px; }
        .sig-box { text-align: center; width: 250px; }
        .sig-line { border-bottom: 1px solid #000; margin-bottom: 4px; height: 35px; }
        .sig-title { font-size: 0.85rem; font-weight: bold; }

        .fallback-banner { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 35px 20px; border-radius: 8px; margin: 30px auto; max-width: 750px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        @media print {
            @page { size: landscape; margin: 10mm; }
            body * { visibility: hidden !important; }
            #printSheetContent, #printSheetContent * { visibility: visible !important; }
            #printSheetContent { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%; 
                box-shadow: none !important; 
                padding: 0 !important; 
                margin: 0 !important;
            }
            .print-sheet::before { 
                opacity: 0.08 !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Certificate of Registration</h2>
                    <p class="text-muted">View, print, or download your official Certificate of Registration (COR) across academic terms.</p>
                </div>
                <div class="application-id-badge">
                    <span>Student No:</span> <strong><?= htmlspecialchars($studentInfo['StudentNumber'] ?? 'NOT YET REGISTERED') ?></strong>
                </div>
            </div>

            <div class="com-container">
                <div class="com-card">
                    <div class="com-header-toolbar">
                        <div class="toolbar-left">
                            <label for="termSelect"><i class="fa-solid fa-calendar-days"></i> Select Academic Term:</label>
                            <select id="termSelect" class="form-control" style="width: 320px;" onchange="location = this.value;">
                                <?php if (empty($availableTerms)): ?>
                                    <option value="">No enrollment history found</option>
                                <?php else: ?>
                                    <?php foreach ($availableTerms as $term): 
                                        $url = "certificate_of_registration?term_id=" . $term['AcademicYearID'];
                                        $isSelected = ($term['AcademicYearID'] == $selectedTermID);
                                        $termLabel = "AY " . $term['YearCode'] . " : " . $term['Semester'] . ($term['IsActive'] ? ' (Current)' : '');
                                    ?>
                                        <option value="<?= $url ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($termLabel) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php if ($isOfficiallyEnrolled): ?>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" id="printComBtn" class="btn btn-secondary" onclick="window.print();">
                                    <i class="fa-solid fa-print"></i> Print COR
                                </button>
                                <button type="button" id="downloadPdfBtn" class="btn btn-accent" onclick="downloadCORPDF();">
                                    <i class="fa-solid fa-download"></i> Download PDF
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- FALLBACK DISPLAY: Student is 'Pre-Enrolled' or Pending Approval -->
                    <?php if (!$isOfficiallyEnrolled): ?>
                        <div class="fallback-banner">
                            <i class="fa-solid fa-clock-rotate-left" style="font-size: 2.5rem; color: #b45309; margin: auto; display: block;"></i>
                            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 8px; margin-top: 8px;">Enrollment Verification Pending</h3>
                            <p style="font-size: 0.95rem; line-height: 1.5; color: #78350f;">
                                Your current enrollment status for this term is <strong>Pre-Enrolled</strong>. Official Certificates of Registration are generated only after your schedule and application are validated and approved by the College Registrar.
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- COR Preview Container with Watermark -->
                        <div class="com-body">
                            <div id="printSheetContent" class="print-sheet">
                                <div class="print-content-wrapper">
                                    <!-- Official Letterhead -->
                                    <div class="print-header">
                                        <img src="../assets/images/site/amsc.webp" alt="AMSC Logo" class="print-logo" onerror="this.style.visibility='hidden'">
                                        <div class="print-header-center">
                                            <h4>Republic of the Philippines</h4>
                                            <h4>Commission on Higher Education</h4>
                                            <h3>ADIONG MEMORIAL STATE COLLEGE</h3>
                                            <p>Ditsaan-Ramain, Lanao del Sur</p>
                                        </div>
                                        <img src="../assets/images/site/bp.jpeg" alt="Secondary Logo" class="print-logo" onerror="this.style.visibility='hidden'">
                                    </div>

                                    <div class="print-title">
                                        <h3>CERTIFICATE OF REGISTRATION</h3>
                                        <p>
                                            <?= htmlspecialchars($selectedTermDetails['Semester'] ?? 'Semester') ?>, 
                                            Academic Year <?= htmlspecialchars($selectedTermDetails['YearCode'] ?? '2026-2027') ?>
                                        </p>
                                    </div>

                                    <!-- Student Details Header -->
                                    <div class="student-info-grid">
                                        <div><strong>Student Name:</strong> <?= htmlspecialchars(($userInfo['FirstName'] ?? 'N/A') . ' ' . ($userInfo['MiddleName'] ?? '') . ' ' . ($userInfo['LastName'] ?? '') . ' ' . ($userInfo['ExtName'])) ?></div>
                                        <div><strong>ID Number:</strong> <?= htmlspecialchars($studentInfo['StudentNumber'] ?? '201122007') ?></div>
                                        <div><strong>Degree Program:</strong> <?= htmlspecialchars($studentInfo['ProgramName'] ?? 'Bachelor of Science in Information Technology') ?></div>
                                        <div><strong>Curriculum:</strong> <?= htmlspecialchars($studentInfo['CurriculumName'] ?? 'BSIT Standard Curriculum') ?></div>
                                    </div>

                                    <!-- Enrolled Subjects Table -->
                                    <div>
                                        <div class="print-section-title">Enrolled Course Schedule</div>
                                        <table class="print-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 15%;">Course Code</th>
                                                    <th style="width: 35%;">Descriptive Title</th>
                                                    <th style="width: 10%;">Units</th>
                                                    <th style="width: 25%;">Day / Time</th>
                                                    <th style="width: 15%;">Room Assigned</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($enrolledClasses)): ?>
                                                    <tr>
                                                        <td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">No official enrolled classes found for this term.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($enrolledClasses as $cls): ?>
                                                        <tr>
                                                            <td><strong><?= htmlspecialchars($cls['SubjectCode']) ?></strong></td>
                                                            <td style="text-align: left; padding-left: 8px;"><?= htmlspecialchars_decode($cls['SubjectTitle']) ?></td>
                                                            <td><?= htmlspecialchars($cls['Units']) ?></td>
                                                            <td><?= htmlspecialchars($cls['ScheduleStr']) ?></td>
                                                            <!-- Room Assignment Display -->
                                                            <td><strong><?= htmlspecialchars($cls['RoomStr']) ?></strong></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="2" style="font-weight: bold; text-align: right; padding-right: 12px;">Total Academic Units:</td>
                                                    <td colspan="3" style="font-weight: bold; text-align: left; padding-left: 12px;"><?= $totalUnits ?> Units</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Rules & Statements -->
                                    <div class="rules-box">
                                        <h5>Institutional Enrollment Rules & Regulations:</h5>
                                        <ol>
                                            <li>This Certificate of Registration (COR) serves as official proof of enrollment in Adiong Memorial State College for the specified semester.</li>
                                            <li>Any unauthorized adding, dropping, or substitution of subjects outside the official adjustment period shall be considered null and void.</li>
                                            <li>Students enjoying CHED subsidies must maintain good academic standing in accordance with institutional retention policies.</li>
                                        </ol>
                                    </div>

                                    <!-- Signatory Area -->
                                    <div class="signature-footer">
                                        <div class="sig-box">
                                            <div class="sig-line"></div>
                                            <div class="sig-title">College Registrar</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function downloadCORPDF() {
            const element = document.getElementById('printSheetContent');
            const studentNo = "<?= htmlspecialchars($studentInfo['StudentNumber'] ?? 'COR') ?>";
            const opt = {
                margin:       5,
                filename:     `COR_${studentNo}.pdf`,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            // Trigger HTML2PDF export
            html2pdf().from(element).set(opt).save();
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>