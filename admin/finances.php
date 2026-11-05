<?php
require_once '../config/db.php';
require_once '../config/session_check.php';
require_once '../includes/activity_logs.php';

$pageTitle = "Finance & Billing Management - AMSC InnDaw";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../includes/head.php"; ?>
    <style>
        /* --- Financial Management Layout Styles --- */
        .finances-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Top KPI Stats Grid */
        .finance-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card .stat-info h4 {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-info .stat-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        /* Tab Navigation */
        .finance-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 5px;
        }

        .tab-btn {
            padding: 10px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: #ffffff;
            border-top-left-radius: 6px;
            border-top-right-radius: 6px;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-top: 1px solid var(--border-color);
        }

        /* Section Cards & Tables */
        .finance-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .finance-card-header {
            padding: 16px 20px;
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .finance-card-header h3 {
            font-size: 1.05rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .finance-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.9rem;
        }

        .finance-table th, .finance-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .finance-table th {
            background-color: #f8fafc;
            color: var(--primary);
            font-weight: 700;
        }

        .finance-table tr:hover {
            background-color: #f8fafc;
        }

        /* Status & Badges */
        .badge-type {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #f1f5f9;
            color: var(--primary);
            border: 1px solid var(--border-color);
            display: inline-block;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-status.success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-status.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-status.failed {
            background-color: #fee2e2;
            color: #991b1b;
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
            max-width: 650px;
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

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 12px;
        }

        @media (max-width: 600px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
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
                    <h2>Finance & Billing Management</h2>
                    <p class="text-muted">Set up billable fees, process payment transactions, and monitor college revenue.</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openBillItemModal('add')">
                        <i class="fa-solid fa-plus"></i> Create Bill Item
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openPaymentModal()">
                        <i class="fa-solid fa-receipt"></i> Record Payment
                    </button>
                </div>
            </div>

            <!-- Dynamic Alert Box -->
            <div id="alertBox" class="alert alert-success hidden">
                <i class="fa-solid fa-circle-check"></i>
                <span id="alertText">Record saved successfully!</span>
            </div>

            <div class="finances-container">

                <!-- KPI OVERVIEW STATS -->
                <div class="finance-stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Total Collections (AY 26-27)</h4>
                            <p class="stat-value" style="color: #059669;">₱ 485,250.00</p>
                        </div>
                        <div class="stat-icon" style="background: #d1fae5; color: #059669;">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Pending Balances</h4>
                            <p class="stat-value" style="color: #d97706;">₱ 124,800.00</p>
                        </div>
                        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Active Bill Items</h4>
                            <p class="stat-value">6 Items</p>
                        </div>
                        <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Total Transactions</h4>
                            <p class="stat-value">142 Paid</p>
                        </div>
                        <div class="stat-icon" style="background: #f1f5f9; color: var(--primary);">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                    </div>
                </div>

                <!-- TABS NAVIGATION -->
                <div class="finance-tabs">
                    <button type="button" class="tab-btn active" id="tabOverviewBtn" onclick="switchTab('overview')">
                        <i class="fa-solid fa-chart-line"></i> Financial Dashboard
                    </button>
                    <button type="button" class="tab-btn" id="tabItemsBtn" onclick="switchTab('items')">
                        <i class="fa-solid fa-list-check"></i> Bill Items Setup
                    </button>
                    <button type="button" class="tab-btn" id="tabTransactionsBtn" onclick="switchTab('transactions')">
                        <i class="fa-solid fa-money-bill-transfer"></i> Payment Transactions
                    </button>
                </div>

                <!-- TAB 1: FINANCIAL OVERVIEW DASHBOARD -->
                <div id="overviewSection" class="finance-card">
                    <div class="finance-card-header">
                        <h3><i class="fa-solid fa-chart-pie" style="color: var(--primary-accent);"></i> Revenue Breakdown & Recent Activity</h3>
                    </div>
                    <div style="padding: 24px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                            <!-- Collection Summary Card -->
                            <div style="background: #f8fafc; border: 1px solid var(--border-color); padding: 20px; border-radius: 8px;">
                                <h4 style="margin: 0 0 15px 0; color: var(--primary);"><i class="fa-solid fa-wallet"></i> Fee Distribution Summary</h4>
                                <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem; display: flex; flex-direction: column; gap: 10px;">
                                    <li style="display: flex; justify-content: space-between;"><span>Tuition Fees:</span> <strong>₱ 310,000.00</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span>Laboratory & IT Fees:</span> <strong>₱ 95,250.00</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span>Registration & Entrance:</span> <strong>₱ 52,000.00</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span>Miscellaneous Fees:</span> <strong>₱ 28,000.00</strong></li>
                                </ul>
                            </div>

                            <!-- Payment Channels Card -->
                            <div style="background: #f8fafc; border: 1px solid var(--border-color); padding: 20px; border-radius: 8px;">
                                <h4 style="margin: 0 0 15px 0; color: var(--primary);"><i class="fa-solid fa-building-columns"></i> Payment Channels</h4>
                                <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9rem; display: flex; flex-direction: column; gap: 10px;">
                                    <li style="display: flex; justify-content: space-between;"><span>Over-the-Counter Cashier:</span> <strong>65% (₱ 315,412.50)</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span>GCash Direct:</span> <strong>25% (₱ 121,312.50)</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span>Bank Wire Transfer:</span> <strong>10% (₱ 48,525.00)</strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: BILL ITEMS SETUP TABLE -->
                <div id="itemsSection" class="finance-card" style="display: none;">
                    <div class="finance-card-header">
                        <h3><i class="fa-solid fa-file-invoice-dollar" style="color: var(--primary-accent);"></i> Billable Fee Catalog</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openBillItemModal('add')">
                            <i class="fa-solid fa-plus"></i> Add Bill Item
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="finance-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Fee Description</th>
                                    <th>Category</th>
                                    <th>Amount (PHP)</th>
                                    <th>Frequency / Scope</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="billItemsTableBody">
                                <tr>
                                    <td><strong>FEE-TUIT-01</strong></td>
                                    <td>Tuition Fee (per Credit Unit)</td>
                                    <td><span class="badge-type">Tuition</span></td>
                                    <td><strong>₱ 350.00 / unit</strong></td>
                                    <td>Per Academic Semester</td>
                                    <td>
                                        <div class="action-btns">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openBillItemModal('edit', 'FEE-TUIT-01', 'Tuition Fee (per Credit Unit)', 'Tuition', '350.00', 'Per Semester')" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="deleteBillItem(this)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>FEE-LAB-02</strong></td>
                                    <td>Computer Laboratory Fee</td>
                                    <td><span class="badge-type">Laboratory</span></td>
                                    <td><strong>₱ 1,500.00</strong></td>
                                    <td>Per Subject Course</td>
                                    <td>
                                        <div class="action-btns">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openBillItemModal('edit', 'FEE-LAB-02', 'Computer Laboratory Fee', 'Laboratory', '1500.00', 'Per Subject')" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="deleteBillItem(this)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>FEE-REG-03</strong></td>
                                    <td>Entrance & Registration Fee</td>
                                    <td><span class="badge-type">Administrative</span></td>
                                    <td><strong>₱ 500.00</strong></td>
                                    <td>One-time upon Admission</td>
                                    <td>
                                        <div class="action-btns">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openBillItemModal('edit', 'FEE-REG-03', 'Entrance & Registration Fee', 'Administrative', '500.00', 'One-time')" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="btn btn-sm" style="background:#fee2e2; color:#dc2626;" onclick="deleteBillItem(this)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: PAYMENT TRANSACTIONS LEDGER -->
                <div id="transactionsSection" class="finance-card" style="display: none;">
                    <div class="finance-card-header">
                        <h3><i class="fa-solid fa-receipt" style="color: var(--primary-accent);"></i> Payment Transactions Ledger</h3>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentModal()">
                            <i class="fa-solid fa-plus"></i> Process New Payment
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="finance-table">
                            <thead>
                                <tr>
                                    <th>OR / Ref Number</th>
                                    <th>Student Name & ID</th>
                                    <th>Payment Description</th>
                                    <th>Amount Paid</th>
                                    <th>Method</th>
                                    <th>Date Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody">
                                <tr>
                                    <td><strong>OR-2026-1042</strong></td>
                                    <td>
                                        <strong>Ahmad Kiram A. Grar</strong>
                                        <div style="font-size: 0.8rem; color: var(--primary-accent);">AMSC-2026-0491</div>
                                    </td>
                                    <td>First Semester Tuition & Lab Fee</td>
                                    <td><strong>₱ 5,450.00</strong></td>
                                    <td><span class="badge-type">GCash</span></td>
                                    <td>Oct 28, 2026</td>
                                    <td><span class="badge-status success"><i class="fa-solid fa-circle-check"></i> Paid</span></td>
                                </tr>
                                <tr>
                                    <td><strong>OR-2026-1043</strong></td>
                                    <td>
                                        <strong>Abdul Jabar S. Sarip</strong>
                                        <div style="font-size: 0.8rem; color: var(--primary-accent);">AMSC-2026-0492</div>
                                    </td>
                                    <td>Entrance & Registration Fee</td>
                                    <td><strong>₱ 500.00</strong></td>
                                    <td><span class="badge-type">Cashier OTC</span></td>
                                    <td>Oct 29, 2026</td>
                                    <td><span class="badge-status success"><i class="fa-solid fa-circle-check"></i> Paid</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- CREATE / EDIT BILL ITEM MODAL -->
    <div id="billItemModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="itemModalTitle"><i class="fa-solid fa-plus-circle"></i> Add New Bill Item</h3>
                <button type="button" class="modal-close-btn" onclick="closeBillItemModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="billItemForm">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Item Code *</label>
                            <input type="text" id="itemCode" class="form-control" placeholder="e.g. FEE-TUIT-01" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Fee Category *</label>
                            <select id="itemCategory" class="form-control" required>
                                <option value="Tuition">Tuition Fee</option>
                                <option value="Laboratory">Laboratory Fee</option>
                                <option value="Administrative">Administrative / Registration</option>
                                <option value="Miscellaneous">Miscellaneous Fee</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Fee Description *</label>
                        <input type="text" id="itemDescription" class="form-control" placeholder="e.g. Laboratory Fee - Computer Studies" required>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 0;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Amount (PHP) *</label>
                            <input type="number" step="0.01" id="itemAmount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Billing Scope *</label>
                            <select id="itemScope" class="form-control" required>
                                <option value="Per Semester">Per Semester</option>
                                <option value="Per Subject">Per Subject Course</option>
                                <option value="One-time">One-time Fee</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBillItemModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveBillItem()">Save Bill Item</button>
            </div>
        </div>
    </div>

    <!-- RECORD PAYMENT MODAL -->
    <div id="paymentModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-receipt"></i> Process Student Payment</h3>
                <button type="button" class="modal-close-btn" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Select Student *</label>
                        <select id="payStudent" class="form-control" required>
                            <option value="">-- Select Student --</option>
                            <option value="Ahmad Kiram A. Grar (AMSC-2026-0491)">Ahmad Kiram A. Grar (AMSC-2026-0491)</option>
                            <option value="Abdul Jabar S. Sarip (AMSC-2026-0492)">Abdul Jabar S. Sarip (AMSC-2026-0492)</option>
                        </select>
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 12px;">
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">OR / Reference Number *</label>
                            <input type="text" id="payRefNo" class="form-control" placeholder="e.g. OR-2026-1044" required>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.85rem; font-weight: 600;">Payment Method *</label>
                            <select id="payMethod" class="form-control" required>
                                <option value="Cashier OTC">Cashier Over-The-Counter</option>
                                <option value="GCash">GCash</option>
                                <option value="Bank Transfer">Bank Wire Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Payment Description *</label>
                        <input type="text" id="payDescription" class="form-control" placeholder="e.g. Tuition Fee Partial Payment" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.85rem; font-weight: 600;">Amount Paid (PHP) *</label>
                        <input type="number" step="0.01" id="payAmount" class="form-control" placeholder="0.00" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePaymentTransaction()">Confirm Payment</button>
            </div>
        </div>
    </div>

    <!-- Vanilla JS Interactivity -->
    <script>
        const billItemModal = document.getElementById('billItemModal');
        const paymentModal = document.getElementById('paymentModal');
        const itemModalTitle = document.getElementById('itemModalTitle');
        const alertBox = document.getElementById('alertBox');
        const alertText = document.getElementById('alertText');

        let activeTab = 'overview';
        let currentItemMode = 'add';

        function switchTab(tab) {
            activeTab = tab;
            document.getElementById('tabOverviewBtn').className = tab === 'overview' ? 'tab-btn active' : 'tab-btn';
            document.getElementById('tabItemsBtn').className = tab === 'items' ? 'tab-btn active' : 'tab-btn';
            document.getElementById('tabTransactionsBtn').className = tab === 'transactions' ? 'tab-btn active' : 'tab-btn';

            document.getElementById('overviewSection').style.display = tab === 'overview' ? 'block' : 'none';
            document.getElementById('itemsSection').style.display = tab === 'items' ? 'block' : 'none';
            document.getElementById('transactionsSection').style.display = tab === 'transactions' ? 'block' : 'none';
        }

        // Bill Item Modals
        function openBillItemModal(mode, code = '', desc = '', category = 'Tuition', amount = '', scope = 'Per Semester') {
            currentItemMode = mode;
            if (mode === 'add') {
                itemModalTitle.innerHTML = `<i class="fa-solid fa-plus-circle"></i> Add New Bill Item`;
                document.getElementById('billItemForm').reset();
            } else {
                itemModalTitle.innerHTML = `<i class="fa-solid fa-pen"></i> Edit Bill Item (${code})`;
                document.getElementById('itemCode').value = code;
                document.getElementById('itemDescription').value = desc;
                document.getElementById('itemCategory').value = category;
                document.getElementById('itemAmount').value = amount;
                document.getElementById('itemScope').value = scope;
            }
            billItemModal.classList.add('active');
        }

        function closeBillItemModal() {
            billItemModal.classList.remove('active');
        }

        function saveBillItem() {
            const code = document.getElementById('itemCode').value.trim();
            const desc = document.getElementById('itemDescription').value.trim();
            const amount = document.getElementById('itemAmount').value;

            if (!code || !desc || !amount) {
                alert("Please fill in all required fields.");
                return;
            }

            showAlert(`Bill item "${code}" successfully ${currentItemMode === 'add' ? 'created' : 'updated'}.`);
            closeBillItemModal();
        }

        function deleteBillItem(btn) {
            if (confirm("Are you sure you want to delete this bill item?")) {
                const row = btn.closest('tr');
                row.remove();
                showAlert("Bill item successfully removed.", "danger");
            }
        }

        // Payment Transaction Modals
        function openPaymentModal() {
            document.getElementById('paymentForm').reset();
            paymentModal.classList.add('active');
        }

        function closePaymentModal() {
            paymentModal.classList.remove('active');
        }

        function savePaymentTransaction() {
            const student = document.getElementById('payStudent').value;
            const refNo = document.getElementById('payRefNo').value.trim();
            const amount = document.getElementById('payAmount').value;

            if (!student || !refNo || !amount) {
                alert("Please complete the payment details.");
                return;
            }

            showAlert(`Payment of ₱ ${parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2})} recorded under OR ${refNo}.`);
            closePaymentModal();
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