<?php
$pageTitle = "Employee Designations - AMSC InnDaw";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        /* --- Designations Page Layout --- */
        .designations-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Tabs Navigation Header */
        .desig-tabs-header {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 5px;
        }

        /* Section Cards */
        .section-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            display: none;
        }

        .section-card.active {
            display: block;
        }

        .section-header {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h3 {
            font-size: 1.05rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Tables */
        .desig-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .desig-table th, .desig-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .desig-table th {
            background-color: #f8fafc;
            color: var(--primary);
            font-weight: 700;
        }

        .desig-table tr:hover {
            background-color: #f8fafc;
        }

        .badge-primary-role {
            background-color: #fef3c7;
            color: #d97706;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 10px;
            border: 1px solid #fde68a;
            display: inline-block;
            margin-top: 4px;
        }

        .action-btns {
            display: flex;
            gap: 6px;
        }

        /* --- MODAL STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            width: 90%;
            max-width: 550px;
            border-radius: 10px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-header {
            padding: 16px 20px;
            background: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            margin: 0;
            color: #ffffff;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 24px 20px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body class="dashboard-body">

    <?php include '../includes/header.php'; ?>

    <div class="dashboard-wrapper">
        <!-- Admin Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="dashboard-content">
            <div class="content-header">
                <div>
                    <h2>Employee Designations & Assignments</h2>
                    <p class="text-muted">Manage administrative office postings and academic faculty college roles.</p>
                </div>
                <div>
                    <a href="employees.php" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left"></i> Back to Employees
                    </a>
                </div>
            </div>

            <!-- Dynamic Alert Box -->
            <div id="alertBox" class="alert alert-success hidden">
                <i class="fa-solid fa-circle-check"></i>
                <span id="alertText">Designation updated successfully!</span>
            </div>

            <div class="designations-container">

                <!-- Tab Navigation Header -->
                <div class="desig-tabs-header">
                    <button type="button" id="tabAdminBtn" class="btn btn-primary btn-sm" onclick="switchDesigTab('admin')">
                        <i class="fa-solid fa-building-columns"></i> Administrative Designations
                    </button>
                    <button type="button" id="tabAcademicBtn" class="btn btn-secondary btn-sm" onclick="switchDesigTab('academic')">
                        <i class="fa-solid fa-graduation-cap"></i> Academic Designations
                    </button>
                </div>

                <!-- 1. ADMINISTRATIVE DESIGNATIONS SECTION -->
                <div id="sectionAdmin" class="section-card active">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-building-columns" style="color: var(--primary-accent);"></i> Administrative Office Postings</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openDesigModal('admin', 'add')">
                            <i class="fa-solid fa-plus"></i> Designate Employee to Administrative Tasks
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="desig-table" id="adminDesigTable">
                            <thead>
                                <tr>
                                    <th>Employee Name (ID)</th>
                                    <th>Office Assigned</th>
                                    <th>Designation Title</th>
                                    <th>Primary Role?</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="adminDesigBody">
                                <tr data-id="1">
                                    <td>
                                        <strong>Prof. M. Alonto</strong>
                                        <span class="text-muted" style="display:block; font-size:0.8rem;">EMP-2023-012</span>
                                    </td>
                                    <td>Office of the Registrar</td>
                                    <td>ICT Coordinator & Systems Officer</td>
                                    <td><span class="badge-primary-role"><i class="fa-solid fa-star"></i> Primary Job</span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openDesigModal('admin', 'edit', '1', 'Prof. M. Alonto', 'Office of the Registrar', 'ICT Coordinator & Systems Officer', true)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="removeDesig(this)" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. ACADEMIC DESIGNATIONS SECTION -->
                <div id="sectionAcademic" class="section-card">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-graduation-cap" style="color: var(--primary-accent);"></i> Academic College Postings</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openDesigModal('academic', 'add')">
                            <i class="fa-solid fa-plus"></i> Designate Employee to Academic Tasks
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="desig-table" id="academicDesigTable">
                            <thead>
                                <tr>
                                    <th>Employee Name (ID)</th>
                                    <th>College Assigned</th>
                                    <th>Designation Title</th>
                                    <th>Primary Role?</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="academicDesigBody">
                                <tr data-id="2">
                                    <td>
                                        <strong>Dr. S. Lomondot</strong>
                                        <span class="text-muted" style="display:block; font-size:0.8rem;">EMP-2021-008</span>
                                    </td>
                                    <td>College of Information Technology (CIT)</td>
                                    <td>Program Head / Associate Professor</td>
                                    <td><span class="badge-primary-role"><i class="fa-solid fa-star"></i> Primary Job</span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openDesigModal('academic', 'edit', '2', 'Dr. S. Lomondot', 'College of Information Technology (CIT)', 'Program Head', true)" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="removeDesig(this)" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- DESIGNATE / EDIT MODAL -->
    <div id="desigModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fa-solid fa-user-tag"></i> Assign Designation</h3>
                <button type="button" class="modal-close-btn" onclick="closeDesigModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="desigForm">
                    <input type="hidden" id="desigCategory" value="">
                    <input type="hidden" id="desigRowId" value="">

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="selectEmployee" style="font-size: 0.85rem; font-weight: 600;">Select Employee <span class="required">*</span></label>
                        <select id="selectEmployee" class="form-control" required>
                            <option value="">-- Choose Employee --</option>
                            <option value="Prof. M. Alonto (EMP-2023-012)">Prof. M. Alonto (EMP-2023-012)</option>
                            <option value="Dr. S. Lomondot (EMP-2021-008)">Dr. S. Lomondot (EMP-2021-008)</option>
                            <option value="Prof. A. Sarip (EMP-2024-045)">Prof. A. Sarip (EMP-2024-045)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;" id="unitGroupContainer">
                        <label for="selectUnit" id="unitLabel" style="font-size: 0.85rem; font-weight: 600;">Office / College <span class="required">*</span></label>
                        <select id="selectUnit" class="form-control" required>
                            <option value="">-- Select Unit --</option>
                            <!-- Dynamically populated via JS -->
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="desigTitle" style="font-size: 0.85rem; font-weight: 600;">Designation Title <span class="required">*</span></label>
                        <input type="text" id="desigTitle" class="form-control" required placeholder="e.g. Committee Chair, Assistant Professor">
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-bottom: 0;">
                        <input type="checkbox" id="isPrimaryJob" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="isPrimaryJob" style="font-size: 0.85rem; font-weight: 600; cursor: pointer;">Mark as Primary Job Assignment</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDesigModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDesignation()">Save Designation</button>
            </div>
        </div>
    </div>

    <!-- Vanilla JS Interactivity -->
    <script>
        const desigModal = document.getElementById('desigModal');
        const modalTitle = document.getElementById('modalTitle');
        const alertBox = document.getElementById('alertBox');
        const alertText = document.getElementById('alertText');
        const selectUnit = document.getElementById('selectUnit');
        const unitLabel = document.getElementById('unitLabel');

        const tabAdminBtn = document.getElementById('tabAdminBtn');
        const tabAcademicBtn = document.getElementById('tabAcademicBtn');
        const sectionAdmin = document.getElementById('sectionAdmin');
        const sectionAcademic = document.getElementById('sectionAcademic');

        const adminUnits = [
            "Office of the President",
            "Office of the Vice President for Academic Affairs",
            "Office of the Registrar",
            "Accounting & Finance Department",
            "Human Resource Management Office",
            "ICT Center / Server Administration"
        ];

        const academicUnits = [
            "College of Information Technology (CIT)",
            "College of Business Administration (CBA)",
            "College of Agriculture (COA)",
            "College of Education (CED)"
        ];

        function switchDesigTab(tab) {
            if (tab === 'admin') {
                tabAdminBtn.className = "btn btn-primary btn-sm";
                tabAcademicBtn.className = "btn btn-secondary btn-sm";
                sectionAdmin.classList.add('active');
                sectionAcademic.classList.remove('active');
            } else {
                tabAcademicBtn.className = "btn btn-primary btn-sm";
                tabAdminBtn.className = "btn btn-secondary btn-sm";
                sectionAcademic.classList.add('active');
                sectionAdmin.classList.remove('active');
            }
        }

        function openDesigModal(category, mode, id = '', employee = '', unit = '', title = '', isPrimary = false) {
            document.getElementById('desigCategory').value = category;
            document.getElementById('desigRowId').value = id;

            selectUnit.innerHTML = '<option value="">-- Select Unit --</option>';
            const unitsList = (category === 'admin') ? adminUnits : academicUnits;
            unitLabel.textContent = (category === 'admin') ? 'Office Assigned *' : 'College Assigned *';

            unitsList.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u;
                opt.textContent = u;
                selectUnit.appendChild(opt);
            });

            if (mode === 'add') {
                modalTitle.innerHTML = `<i class="fa-solid fa-user-tag"></i> Designate Employee to ${category === 'admin' ? 'Administrative' : 'Academic'} Tasks`;
                document.getElementById('desigForm').reset();
                document.getElementById('desigCategory').value = category;
            } else {
                modalTitle.innerHTML = `<i class="fa-solid fa-pen"></i> Edit Designation`;
                document.getElementById('selectEmployee').value = employee;
                selectUnit.value = unit;
                document.getElementById('desigTitle').value = title;
                document.getElementById('isPrimaryJob').checked = isPrimary;
            }

            desigModal.classList.add('active');
        }

        function closeDesigModal() {
            desigModal.classList.remove('active');
        }

        function saveDesignation() {
            const category = document.getElementById('desigCategory').value;
            const employee = document.getElementById('selectEmployee').value;
            const unit = selectUnit.value;
            const title = document.getElementById('desigTitle').value.trim();

            if (!employee || !unit || !title) {
                alert("Please fill in all required designation fields.");
                return;
            }

            showAlert(`Designation successfully saved under ${category === 'admin' ? 'Administrative' : 'Academic'} records.`);
            closeDesigModal();
        }

        function removeDesig(btn) {
            if (confirm("Are you sure you want to remove this employee designation?")) {
                const row = btn.closest('tr');
                row.remove();
                showAlert("Designation successfully removed.", "danger");
            }
        }

        function showAlert(message, type = 'success') {
            alertText.textContent = message;
            alertBox.className = `alert alert-${type}`;
            alertBox.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(() => {
                alertBox.classList.add('hidden');
            }, 4000);
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>