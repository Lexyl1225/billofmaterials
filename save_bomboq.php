<?php
// Bill of Materials PHP version
session_start();

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

// Handle AJAX logging requests (before login check to allow logging)
if (isset($_POST['log_action']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $action = $_POST['log_action'];
    $details = isset($_POST['log_details']) ? $_POST['log_details'] : '';
    $page = isset($_POST['log_page']) ? $_POST['log_page'] : 'save_bomboq.php';
    
    logActivity($action, $details, $page);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
// Block Purchasing Department from accessing this page
if (isset($_SESSION['user_department']) && $_SESSION['user_department'] === 'Purchasing') {
    header('Location: price_edit_bomboq.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity('logout', 'User logged out', 'save_bomboq.php');
    session_destroy();
    header('Location: login.php');
    exit();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved BOM & BOQ Documents</title>
    <script>try{var t=localStorage.getItem('bom_theme');if(t&&t!=='default')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="stylesheet" href="themes.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            font-size: 12px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .button-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #666;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .back-button:hover {
            background-color: #000;
        }
        .documents-section {
            margin-top: 20px;
        }
        .section-title {
            background-color: #FFFF00 !important;
            color: #000 !important;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold !important;
            font-size: 14px;
            opacity: 1 !important;
            visibility: visible !important;
            border: 1px solid #000;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table thead {
            background-color: #f5f5f5;
        }
        table th {
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: bold;
            color: #333;
            font-size: 13px;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: normal;
        }
        table tbody tr:hover {
            background-color: #f9f9f9;
        }
        .view-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .view-btn:hover {
            background-color: #45a049;
        }
        .delete-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .delete-btn:hover {
            background-color: #da190b;
        }
        .print-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .print-btn:hover {
            background-color: #1976D2;
        }
        .post-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #FF9800;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .post-btn:hover {
            background-color: #F57C00;
        }
        .post-btn.posted {
            background-color: #9E9E9E;
            cursor: not-allowed;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #FFF3E0;
            color: #E65100;
        }
        .status-posted {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        .status-completed {
            background-color: #E3F2FD;
            color: #1565C0;
        }
        .post-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #FF9800;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .post-btn:hover {
            background-color: #F57C00;
        }
        .post-btn.posted {
            background-color: #9E9E9E;
            cursor: not-allowed;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #FFF3E0;
            color: #E65100;
        }
        .status-posted {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        .status-completed {
            background-color: #E3F2FD;
            color: #1565C0;
        }
        .no-documents {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }
        .post-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #FF9800;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .post-btn:hover {
            background-color: #F57C00;
        }
        .post-btn.posted {
            background-color: #9E9E9E;
            cursor: not-allowed;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #FFF3E0;
            color: #E65100;
        }
        .status-posted {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        .status-completed {
            background-color: #E3F2FD;
            color: #1565C0;
        }
        .post-btn {
            display: inline-flex;
            padding: 8px 16px;
            background-color: #FF9800;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        .post-btn:hover {
            background-color: #F57C00;
        }
        .post-btn.posted {
            background-color: #9E9E9E;
            cursor: not-allowed;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #FFF3E0;
            color: #E65100;
        }
        .status-posted {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        .status-completed {
            background-color: #E3F2FD;
            color: #1565C0;
        }
        .document-date {
            color: #999;
            font-size: 12px;
        }
        .button-cell {
            display: flex;
            flex-direction: row;
            gap: 10px;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: nowrap;
        }
        /* Modal Styles for View */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        .modal.show {
            display: block;
        }
        .modal-content {
            background-color: white;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
            box-shadow: none;
            position: relative;
        }
        .modal-header {
            background-color: #2196F3;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
        }
        .modal-body {
            padding: 20px;
            height: calc(100% - 120px);
            overflow-y: auto;
            background-color: #f9f9f9;
        }
        .modal-footer {
            background-color: #f5f5f5;
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }
        .modal-close-btn {
            background-color: #666;
            color: white;
        }
        .modal-close-btn:hover {
            background-color: #000;
        }
        .modal-pdf-btn {
            background-color: #f44336;
            color: white;
        }
        .modal-pdf-btn:hover {
            background-color: #da190b;
        }
        .modal-print-btn {
            background-color: #2196F3;
            color: white;
        }
        .modal-print-btn:hover {
            background-color: #1976D2;
        }
        .document-content {
            background-color: white;
            padding: 30px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .document-content table tbody tr td:first-child,
        .document-content table tbody tr td:nth-child(1) {
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
            overflow: visible;
            max-width: 100%;
        }
        .document-content textarea {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
            max-width: 100%;
            overflow: visible;
        }
        .document-content input[type="text"],
        .document-content input[type="number"],
        .document-content input[type="date"] {
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            overflow: visible;
        }
        @media print {
            .back-button, .view-btn, .delete-btn, .modal-footer, .modal-header {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="button-container">
            <button class="back-button" onclick="goBack()">&#8592; Back</button>
            <button class="back-button" onclick="backToHome()">Back to Home</button>
            <button class="back-button" style="background-color: #d32f2f; margin-left: auto;" onclick="logout()">&#128682; Logout</button>
        </div>
        <div class="header">
            <h1>Saved Documents</h1>
            <p>View and manage your saved BOM and BOQ documents</p>
            <?php if (isset($_SESSION['admin_username'])): ?>
                <p style="color: #1f5a96; font-size: 12px; margin-top: 5px;">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></p>
            <?php endif; ?>
        </div>
        <?php $dept = isset($_SESSION["user_department"]) ? $_SESSION["user_department"] : ""; ?>
        <?php if ($dept !== "Purchasing"): ?>
        <!-- BOM Documents Section -->
        <div class="documents-section" id="bomSection">
            <div class="section-title">Bill of Materials (BOM)</div>
            <table id="bomTable">
                <thead>
                    <tr>
                        <th style="width: 25%;">Document Name</th>
                        <th style="width: 15%;">Reference No</th>
                        <th style="width: 12%;">Date Created</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 38%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="bomBody">
                </tbody>
            </table>
        </div>
        <!-- BOQ Documents Section -->
        <div class="documents-section">
            <div class="section-title">Bill of Quantities (BOQ)</div>
            <table id="boqTable">
                <thead>
                    <tr>
                        <th style="width: 25%;">Document Name</th>
                        <th style="width: 15%;">Reference No</th>
                        <th style="width: 12%;">Date Created</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 38%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="boqBody">
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Unposted Documents Section (From Price Edit) -->
        <div class="documents-section">
            <div class="section-title" style="background-color: #FF9800 !important; color: white !important;">Unposted files for Editing / Correction (From Price Edit)</div>
            <table id="unpostedTable">
                <thead>
                    <tr>
                        <th style="width: 20%;">Document Name</th>
                        <th style="width: 12%;">Reference No</th>
                        <th style="width: 8%;">Type</th>
                        <th style="width: 12%;">Date Created</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 38%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="unpostedBody">
                </tbody>
            </table>
        </div>
        
        <!-- Posted Documents from Price Edit Section -->
        <div class="documents-section">
            <div class="section-title" style="background-color: #4CAF50 !important; color: white !important;">Posted Documents (From Price Edit - Read Only)</div>
            <table id="postedTable">
                <thead>
                    <tr>
                        <th style="width: 20%;">Document Name</th>
                        <th style="width: 12%;">Reference No</th>
                        <th style="width: 8%;">Type</th>
                        <th style="width: 12%;">Date Created</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 38%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="postedBody">
                </tbody>
            </table>
        </div>
    </div>
    <!-- Document Viewer Modal -->
    <div id="viewerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Document Viewer</h2>
                <span id="closeModalBtn" style="cursor: pointer; font-size: 28px; font-weight: bold;">&times;</span>
            </div>
            <div class="modal-body">
                <div id="documentContent" class="document-content"></div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-print-btn" onclick="printDocument()">Print</button>
                <button class="modal-btn modal-pdf-btn" onclick="downloadPDF()">Download PDF</button>
                <button class="modal-btn modal-close-btn" onclick="closeViewer()">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Include Document Sync Library -->
    <script src="document_sync.js"></script>
    
    <script>
        // Helper function to log activity to server
        function logActivityToServer(action, details, page = 'save_bomboq.php') {
            const formData = new FormData();
            formData.append('log_action', action);
            formData.append('log_details', details);
            formData.append('log_page', page);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Activity logged:', action, details);
                }
            })
            .catch(err => console.error('Logging error:', err));
        }
        
        // Load documents from localStorage
        let savedDocuments = {
            bom: [],
            boq: []
        };
        let postedDocuments = [];
        let unpostedDocuments = [];
        const userRole = '<?php echo isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "guest"; ?>';
        const userDepartment = '<?php echo isset($_SESSION["user_department"]) ? $_SESSION["user_department"] : ""; ?>';
        const isAdmin = userRole === 'admin';
        const isAdminUser = userRole === 'admin' && userDepartment === 'Admin';
        // Load documents on page load - ONLY from server, not localStorage
        window.addEventListener('DOMContentLoaded', async function() {
            try {
                console.log('Loading documents from server...');
                await loadDocumentsFromServer();
                console.log('Documents loaded successfully from server');
            } catch (error) {
                console.error('Error loading from server:', error);
                alert('Failed to load documents from server. Please refresh the page.');
            }
            
            displayBOMDocuments();
            displayBOQDocuments();
            displayUnpostedDocuments();
            displayPostedDocuments();
        });

        // Load documents directly from server (database) - NO localStorage
        async function loadDocumentsFromServer() {
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'list'
                })
            });
            
            // Check if response is ok
            if (!response.ok) {
                throw new Error('Server returned status: ' + response.status);
            }
            
            // Get response text first to debug any issues
            const responseText = await response.text();
            
            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Invalid JSON response:', responseText.substring(0, 500));
                throw new Error('Server returned invalid response. Check console for details.');
            }
            
            if (result.success && result.documents) {
                // Clear existing data
                savedDocuments.bom = [];
                savedDocuments.boq = [];
                postedDocuments = [];
                unpostedDocuments = [];
                
                // Organize documents from server
                result.documents.forEach(doc => {
                    // Extract the first item which contains all the document data
                    const firstItem = (doc.items && doc.items.length > 0) ? doc.items[0] : {};
                    
                    const formattedDoc = {
                        name: doc.name,
                        refNo: firstItem.refNo || doc.ref_no || doc.refNo || '',
                        type: doc.type,
                        date: firstItem.date || (doc.created_at ? new Date(doc.created_at).toLocaleDateString() : new Date().toLocaleDateString()),
                        status: doc.status,
                        items: doc.items || [],
                        bomItems: doc.type === 'bom' ? (doc.items || []) : [],
                        boqItems: doc.type === 'boq' ? (doc.items || []) : [],
                        // Extract additional fields from first item
                        htmlContent: firstItem.htmlContent || doc.html_content || '',
                        department: firstItem.department || doc.department || '',
                        projectName: firstItem.projectName || doc.project_name || '',
                        location: firstItem.location || doc.location || '',
                        floorArea: firstItem.floorArea || '',
                        dateValue: firstItem.dateValue || doc.date_value || '',
                        // Add creator info for visibility
                        createdBy: doc.username || doc.created_by || ''
                    };
                    
                    if (doc.status === 'saved') {
                        if (doc.type === 'bom') {
                            savedDocuments.bom.push(formattedDoc);
                        } else if (doc.type === 'boq') {
                            savedDocuments.boq.push(formattedDoc);
                        }
                    } else if (doc.status === 'posted') {
                        postedDocuments.push(formattedDoc);
                    } else if (doc.status === 'unposted' || doc.status === 'pending_price_edit') {
                        if (doc.status === 'unposted') {
                            unpostedDocuments.push(formattedDoc);
                        }
                    }
                });
                
                console.log('Server data loaded:', {
                    bom: savedDocuments.bom.length,
                    boq: savedDocuments.boq.length,
                    posted: postedDocuments.length,
                    unposted: unpostedDocuments.length
                });
            } else {
                throw new Error(result.message || 'Failed to load documents');
            }
        }
        function displayBOMDocuments() {
            const tbody = document.getElementById('bomBody');
            
            if (savedDocuments.bom.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="no-documents">No saved BOM documents</td></tr>';
                return;
            }
            tbody.innerHTML = savedDocuments.bom.map((doc, index) => {
                const isPosted = doc.status === 'posted';
                const statusBadge = isPosted ? 
                    '<span class="status-badge status-posted">Posted</span>' : 
                    '<span class="status-badge status-pending">Pending</span>';
                const postBtn = isPosted ? 
                    `<button class="post-btn posted" disabled>Posted</button>` :
                    `<button class="post-btn" data-type="bom" data-index="${index}">Post</button>`;
                
                // Delete button logic: show for non-posted files OR for posted files if user is Admin
                const deleteBtn = (!isPosted || isAdmin) ? 
                    `<button class="delete-btn" data-type="bom" data-index="${index}">Delete</button>` : '';
                
                return `
                <tr>
                    <td><strong>${doc.name}</strong></td>
                    <td>${doc.refNo}</td>
                    <td><span class="document-date">${doc.date}</span></td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="button-cell">
                            <button class="view-btn" data-type="bom" data-index="${index}">View</button>
                            <button class="print-btn" data-type="bom" data-index="${index}">Print</button>
                            ${postBtn}
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>
            `}).join('');
            
            // Add event listeners to BOM view buttons
            document.querySelectorAll('#bomBody .view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    viewSavedDocument(savedDocuments.bom[index]);
                });
            });
            
            // Add event listeners to BOM print buttons
            document.querySelectorAll('#bomBody .print-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    printSavedDocument(savedDocuments.bom[index]);
                });
            });
            // Add event listeners to BOM delete buttons
            document.querySelectorAll('#bomBody .delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    deleteSavedDocument('bom', index);
                });
            });
            // Add event listeners to BOM post buttons
            document.querySelectorAll('#bomBody .post-btn:not(.posted)').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    postDocument('bom', index);
                });
            });
        }
        // Display BOQ documents
        function displayBOQDocuments() {
            const tbody = document.getElementById('boqBody');
            
            if (savedDocuments.boq.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="no-documents">No saved BOQ documents</td></tr>';
                return;
            }
            tbody.innerHTML = savedDocuments.boq.map((doc, index) => {
                const isPosted = doc.status === 'posted';
                const statusBadge = isPosted ? 
                    '<span class="status-badge status-posted">Posted</span>' : 
                    '<span class="status-badge status-pending">Pending</span>';
                const postBtn = isPosted ? 
                    `<button class="post-btn posted" disabled>Posted</button>` :
                    `<button class="post-btn" data-type="boq" data-index="${index}">Post</button>`;
                
                // Delete button logic: show for non-posted files OR for posted files if user is Admin
                const deleteBtn = (!isPosted || isAdmin) ? 
                    `<button class="delete-btn" data-type="boq" data-index="${index}">Delete</button>` : '';
                
                return `
                <tr>
                    <td><strong>${doc.name}</strong></td>
                    <td>${doc.refNo}</td>
                    <td><span class="document-date">${doc.date}</span></td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="button-cell">
                            <button class="view-btn" data-type="boq" data-index="${index}">View</button>
                            <button class="print-btn" data-type="boq" data-index="${index}">Print</button>
                            ${postBtn}
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>
            `}).join('');
            
            // Add event listeners to BOQ view buttons
            document.querySelectorAll('#boqBody .view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    viewSavedDocument(savedDocuments.boq[index]);
                });
            });
            
            // Add event listeners to BOQ print buttons
            document.querySelectorAll('#boqBody .print-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    printSavedDocument(savedDocuments.boq[index]);
                });
            });
            // Add event listeners to BOQ delete buttons
            document.querySelectorAll('#boqBody .delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    deleteSavedDocument('boq', index);
                });
            });
            
            // Add event listeners to BOQ post buttons
            document.querySelectorAll('#boqBody .post-btn:not(.posted)').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    postDocument('boq', index);
                });
            });
        }

        // Print saved document directly
        function printSavedDocument(doc) {
            const printWindow = window.open('', '', 'width=1000,height=800');
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html>');
            printWindow.document.write('<head>');
            printWindow.document.write('<meta charset="UTF-8">');
            printWindow.document.write('<title>' + doc.name + '</title>');
            printWindow.document.write('<style>');
            
            // Professional Print Styling
            printWindow.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
            printWindow.document.write('body { font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 13px; line-height: 1.4; color: #333; background-color: white; padding: 30px 25px; font-weight: 500; }');
            
            // Header styling
            printWindow.document.write('.page-header { border-bottom: 3px solid #1f5a96; padding-bottom: 20px; margin-bottom: 25px; }');
            printWindow.document.write('h1 { font-size: 28px; color: #000; margin-bottom: 5px; font-weight: 700; letter-spacing: 0.5px; }');
            printWindow.document.write('.document-type { font-size: 15px; color: #000; font-weight: 600; margin-bottom: 15px; }');
            
            // Header info grid
            printWindow.document.write('.header-info { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; background-color: #f5f7fa; padding: 15px 20px; border-radius: 4px; margin-bottom: 25px; border-left: 4px solid #1f5a96; }');
            printWindow.document.write('.info-item { }');
            printWindow.document.write('.info-label { font-size: 11px; color: #000; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px; }');
            printWindow.document.write('.info-value { font-size: 13px; color: #1f5a96; font-weight: 600; }');
            
            // Section styling
            printWindow.document.write('.bom-section { margin-bottom: 25px; page-break-inside: avoid; }');
            printWindow.document.write('h2, h3, h4, h5, h6 { color: #000 !important; }'); printWindow.document.write('.section-title { background: linear-gradient(135deg, #1f5a96 0%, #2d7ab8 100%); color: white; padding: 12px 15px; font-weight: 700; font-size: 13px; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 3px 3px 0 0; }');
            
            // Table styling
            printWindow.document.write('table { width: 100%; border-collapse: collapse; background-color: white; table-layout: fixed; margin-bottom: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 0 0 3px 3px; overflow: hidden; }');
            printWindow.document.write('table th { background-color: #2d7ab8; color: white; font-weight: 700; text-align: center; border: none; padding: 10px 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }');
            printWindow.document.write('table td { border: 1px solid #e0e4e8; padding: 8px 6px; text-align: left; vertical-align: middle; background-color: white; font-weight: 500; }');
            printWindow.document.write('table tbody tr:nth-child(odd) { background-color: #fafbfc; }');
            printWindow.document.write('table tbody tr:hover { background-color: #f0f4f8; }');
            
            // Column widths
            printWindow.document.write('table tr:first-child th:nth-child(1) { width: 25% !important; text-align: left; }');
            printWindow.document.write('table tr:first-child th:nth-child(2) { width: 8% !important; }');
            printWindow.document.write('table tr:first-child th:nth-child(3) { width: 7% !important; }');
            printWindow.document.write('table tr:first-child th:nth-child(4) { width: 10% !important; }');
            printWindow.document.write('table tr:first-child th:nth-child(5) { width: 10% !important; }');
            printWindow.document.write('table tr:first-child th:nth-child(6) { width: 10% !important; }');
            printWindow.document.write('table tr:first-child th:nth-child(7) { width: 10% !important; }');
            printWindow.document.write('table tr:first-child th:nth-child(8) { width: 12% !important; }');
            
            printWindow.document.write('table tbody tr td:nth-child(1) { width: 25% !important; text-align: left; overflow: visible !important; max-width: 25% !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: normal !important; white-space: pre-wrap !important; vertical-align: top !important; hyphens: auto !important; }');
            printWindow.document.write('table tbody tr td:nth-child(2) { width: 8% !important; text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(3) { width: 7% !important; text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(4) { width: 10% !important; text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(5) { width: 10% !important; text-align: right; }');
            printWindow.document.write('table tbody tr td:nth-child(6) { width: 10% !important; text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(7) { width: 10% !important; text-align: right; }');
            printWindow.document.write('table tbody tr td:nth-child(8) { width: 12% !important; text-align: right; font-weight: 700; color: #1f5a96; }');
            
            // Total row styling
            printWindow.document.write('.total-row { background-color: #e8f0f7 !important; font-weight: 700; color: #1f5a96; }');
            printWindow.document.write('.total-row td { border-top: 2px solid #1f5a96; border-bottom: 2px solid #1f5a96; padding: 12px 8px; }');
            
            printWindow.document.write('.description-cell { max-width: 100% !important; overflow: hidden !important; word-break: break-all !important; line-height: 1.2 !important; }');
            printWindow.document.write('table tbody tr td:first-child * { max-width: 100% !important; overflow: visible !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: normal !important; display: block !important; white-space: pre-wrap !important; }');
            
            // Form elements
            printWindow.document.write('textarea { display: block !important; width: 100% !important; min-height: auto !important; max-width: 100% !important; border: none !important; background: transparent !important; color: #222 !important; font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 11px; padding: 0 !important; margin: 0 !important; white-space: pre-wrap !important; overflow: visible !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: normal !important; line-height: 1.3; opacity: 1 !important; visibility: visible !important; resize: none !important; height: auto !important; font-weight: 600; }');
            printWindow.document.write('input[type="text"], input[type="number"], input[type="date"] { display: block !important; width: 100% !important; border: none !important; background: transparent !important; color: #222 !important; font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 11px; padding: 0 !important; margin: 0 !important; opacity: 1 !important; visibility: visible !important; font-weight: 600; overflow: visible !important; max-width: 100% !important; word-wrap: break-word !important; overflow-wrap: break-word !important; }');
            
            // Button styling (for on-screen)
            printWindow.document.write('.button-group { margin-bottom: 15px; text-align: center; }');
            printWindow.document.write('.print-button, .close-button { background-color: #1f5a96; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 12px; font-weight: 500; transition: background-color 0.3s; }');
            printWindow.document.write('.print-button:hover, .close-button:hover { background-color: #0f3d6b; }');
            
            // Footer styling
            printWindow.document.write('.page-footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e0e4e8; }');
            printWindow.document.write('.signatures { margin-top: 30px; font-size: 10px; display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 30px; row-gap: 30px; }');
            printWindow.document.write('.signature-row { display: contents; }');
            printWindow.document.write('.signature-block { text-align: center; display: flex; flex-direction: column; align-items: center; min-height: 140px; justify-content: flex-start; }');
            printWindow.document.write('.signature-label { font-weight: 700; color: #000; margin-bottom: 20px; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; line-height: 1.4; }');
            printWindow.document.write('.signature-line { border-top: 1.5px solid #000; width: 140px; margin: 0 auto 5px auto; }');
            printWindow.document.write('.signature-name { font-weight: 700; margin-top: 8px; color: #000; font-size: 11px; }');
            printWindow.document.write('.signature-title { font-size: 9px; margin-top: 2px; color: #000; font-weight: 500; }');
            
            // Force all section-title elements to black
            printWindow.document.write('.section-title, .section-title * { color: #000 !important; background: #f0f0f0 !important; }');
            
            // Color overrides for all gray text
            printWindow.document.write('[style*="color: #999"] { color: #000 !important; }');
            printWindow.document.write('[style*="color: #666"] { color: #000 !important; }');
            printWindow.document.write('[style*="color: #555"] { color: #000 !important; }');
            // Universal gray text override
            printWindow.document.write('* { color: inherit; }');
            printWindow.document.write('*:not(a):not(.total-row):not(.info-value) { color: #000 !important; }');
            // Print media
            printWindow.document.write('@media print { body { margin: 0; padding: 20px; } .button-group { display: none; } .page-header { border-bottom-color: #1f5a96; } .signatures { margin-top: 40px; } }');
            
            printWindow.document.write('</style>');
            printWindow.document.write('</head>');
            printWindow.document.write('<body>');
            
            // Determine document type for header display
            const docType1 = doc.type || 'boq';
            const isBOM1 = docType1.toLowerCase() === 'bom';
            const headerTitle1 = isBOM1 ? 'BILL OF MATERIALS' : 'BILL OF QUANTITIES';
            const docTypeLabel1 = isBOM1 ? 'BOM Document' : 'BOQ Document';
            
            // Add professional header
            printWindow.document.write('<div class="page-header">');
            printWindow.document.write('<h1>' + headerTitle1 + '</h1>');
            printWindow.document.write('<div class="document-type">' + docTypeLabel1 + ' - ' + doc.name + '</div>');
            printWindow.document.write('</div>');
            
            // Add project information header
            printWindow.document.write('<div class="header-info">');
            printWindow.document.write('<div class="info-item"><div class="info-label">Department</div><div class="info-value">' + (doc.department || 'N/A') + '</div></div>');
            printWindow.document.write('<div class="info-item"><div class="info-label">Project</div><div class="info-value">' + (doc.projectName || 'N/A') + '</div></div>');
            printWindow.document.write('<div class="info-item"><div class="info-label">Location</div><div class="info-value">' + (doc.location || 'N/A') + '</div></div>');
            printWindow.document.write('<div class="info-item"><div class="info-label">Date</div><div class="info-value">' + (doc.dateValue || doc.date || 'N/A') + '</div></div>');
            printWindow.document.write('</div>');
            
            // Add action buttons
            printWindow.document.write('<div class="button-group">');
            printWindow.document.write('<button class="print-button" onclick="window.print()">Print Document</button>');
            printWindow.document.write('<button class="close-button" onclick="window.close()">Close</button>');
            printWindow.document.write('</div>');
            
            // Add document content
            if (doc.htmlContent) {
                // Remove duplicate project-info section if it exists
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = doc.htmlContent;
                const projectInfoElements = tempDiv.querySelectorAll('.project-info');
                projectInfoElements.forEach(el => el.remove());
                
                // CRITICAL: Strip all inline width styles from table headers and cells
                // This removes the hardcoded widths from bom_labor.php so our CSS can take effect
                const allTableElements = tempDiv.querySelectorAll('th, td');
                allTableElements.forEach(function(el) {
                    // Get the style attribute and remove width-related properties
                    const style = el.getAttribute('style') || '';
                    const newStyle = style
                        .replace(/width:\s*[\d.]+%[^;]*/gi, '')  // Remove width: X%
                        .replace(/width:\s*[\d.]+px[^;]*/gi, '') // Remove width: Xpx
                        .replace(/;\s*;/g, ';')                   // Remove double semicolons
                        .trim();
                    
                    if (newStyle && newStyle !== ';') {
                        el.setAttribute('style', newStyle);
                    } else {
                        el.removeAttribute('style');
                    }
                });
                
                printWindow.document.write(tempDiv.innerHTML);
            } else {
                printWindow.document.write('<p>No content available for this document.</p>');
            }
            
            printWindow.document.write('</body>');
            printWindow.document.write('</html>');
            printWindow.document.close();
            
            // Fix textareas to show their content
            setTimeout(function() {
                try {
                    const textareas = printWindow.document.querySelectorAll('textarea');
                    textareas.forEach(function(ta) {
                        // Try to get value from value attribute first, then textContent
                        const textValue = ta.value || ta.textContent || ta.innerText || ta.innerHTML || '';
                        if (textValue && textValue.trim()) {
                            ta.style.display = 'block';
                            ta.style.color = '#000';
                            ta.style.background = 'white';
                            ta.style.overflow = 'visible';
                            ta.style.whiteSpace = 'pre-wrap';
                            ta.style.wordWrap = 'break-word';
                            ta.style.opacity = '1';
                            ta.style.visibility = 'visible';
                            // Set both value and textContent to ensure visibility
                            ta.value = textValue;
                            ta.textContent = textValue;
                            ta.innerHTML = textValue;
                        }
                    });
                    
                    const inputs = printWindow.document.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]');
                    inputs.forEach(function(inp) {
                        const inputValue = inp.value || inp.getAttribute('value') || '';
                        if (inputValue && inputValue.trim()) {
                            inp.style.color = '#000';
                            inp.style.background = 'white';
                            inp.style.opacity = '1';
                            inp.style.visibility = 'visible';
                            inp.value = inputValue;
                        }
                    });
                    
                    // Fix select elements to show selected value as text
                    const selects = printWindow.document.querySelectorAll('select');
                    selects.forEach(function(sel) {
                        const selectedOption = sel.options[sel.selectedIndex];
                        const selectedText = selectedOption ? selectedOption.text : sel.value || '';
                        if (selectedText) {
                            // Create a span to display the selected value as text
                            const span = printWindow.document.createElement('span');
                            span.textContent = selectedText;
                            span.style.color = '#000';
                            span.style.background = 'white';
                            span.style.padding = '2px 4px';
                            span.style.display = 'inline-block';
                            span.style.whiteSpace = 'normal';
                            span.style.wordWrap = 'break-word';
                            span.style.overflow = 'visible';
                            // Replace select with span showing the value
                            sel.parentNode.replaceChild(span, sel);
                        }
                    });
                    
                    // Auto-size textareas to fit their content and convert to div for better print display
                    const allTextareas = printWindow.document.querySelectorAll('textarea');
                    allTextareas.forEach(function(ta) {
                        const textContent = ta.value || ta.textContent || '';
                        if (textContent.trim()) {
                            // Create a div to replace textarea with proper text display
                            const div = printWindow.document.createElement('div');
                            div.textContent = textContent;
                            div.style.display = 'block';
                            div.style.color = '#000';
                            div.style.background = 'transparent';
                            div.style.padding = '0';
                            div.style.margin = '0';
                            div.style.border = 'none';
                            div.style.fontFamily = '"Segoe UI", Calibri, Arial, sans-serif';
                            div.style.fontSize = '11px';
                            div.style.lineHeight = '1.3';
                            div.style.whiteSpace = 'pre-wrap';
                            div.style.wordWrap = 'break-word';
                            div.style.overflowWrap = 'break-word';
                            div.style.overflow = 'visible';
                            div.style.overflowY = 'visible';
                            div.style.position = 'relative';
                            div.style.opacity = '1';
                            div.style.visibility = 'visible';
                            div.style.minHeight = 'auto';
                            div.style.maxWidth = '100%';
                            div.style.width = '100%';
                            div.style.boxSizing = 'border-box';
                            // Replace textarea with div
                            ta.parentNode.replaceChild(div, ta);
                        }
                    });
                } catch(e) {
                    // Error silently if unable to access print window
                }
            }, 100);
            
            printWindow.focus();
            
            // Automatically trigger print dialog
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        // Delete saved document
        async function deleteSavedDocument(type, index) {
            // Check if user is Guest
            if (userRole === 'guest') {
                alert('Guests are not allowed to delete files. Please contact an administrator or user.');
                return;
            }
            
            const doc = type === 'bom' ? savedDocuments.bom[index] : savedDocuments.boq[index];
            
            if (!doc) {
                alert('Document not found');
                return;
            }
            
            // Check if file is posted and user is not Admin
            if (doc.status === 'posted' && !isAdmin) {
                alert('Only administrators can delete posted files.');
                return;
            }
            
            if (!confirm('Are you sure you want to delete "' + doc.name + '"?')) {
                return;
            }
            
            try {
                // Sync delete to server first
                const serverResult = await DocumentSync.deleteDocument(doc.name, doc.status || 'saved');
                
                if (!serverResult.success) {
                    console.warn('Server delete warning:', serverResult.message);
                }
                
                // Remove document from local array
                if (type === 'bom') {
                    savedDocuments.bom.splice(index, 1);
                    localStorage.setItem('bomDocuments', JSON.stringify(savedDocuments.bom));
                } else {
                    savedDocuments.boq.splice(index, 1);
                    localStorage.setItem('boqDocuments', JSON.stringify(savedDocuments.boq));
                }
                
                // Refresh display
                displayBOMDocuments();
                displayBOQDocuments();
                
                // Log activity
                const fileType = type.toUpperCase();
                const status = doc.status === 'posted' ? 'posted ' : '';
                logActivityToServer('delete_file', `Deleted ${status}${fileType} file: ${doc.name} (Ref: ${doc.refNo})`, 'save_bomboq.php');
                
                alert('Document "' + doc.name + '" has been deleted and synced to server.');
            } catch (error) {
                console.error('Error deleting document:', error);
                alert('Error deleting document: ' + error.message);
            }
        }
        // Show saved document in print format on new page
        function viewSavedDocument(doc) {
            // Open in a full browser tab for clean document viewing
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write('<html>');
            printWindow.document.write('<head>');
            printWindow.document.write('<meta charset="UTF-8">');
            printWindow.document.write('<title>' + doc.name + '</title>');
            printWindow.document.write('<style>');
            
            // Professional Print Styling
            printWindow.document.write('* { margin: 0; padding: 0; box-sizing: border-box; }');
            printWindow.document.write('body { font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 13px; line-height: 1.4; color: #333; background-color: white; padding: 30px 25px; font-weight: 500; }');
            
            // Header styling
            printWindow.document.write('.page-header { border-bottom: 3px solid #1f5a96; padding-bottom: 20px; margin-bottom: 25px; }');
            printWindow.document.write('h1 { font-size: 28px; color: #000; margin-bottom: 5px; font-weight: 700; letter-spacing: 0.5px; }');
            printWindow.document.write('.document-type { font-size: 15px; color: #000; font-weight: 600; margin-bottom: 15px; }');
            
            // Header info grid
            printWindow.document.write('.header-info { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; background-color: #f5f7fa; padding: 15px 20px; border-radius: 4px; margin-bottom: 25px; border-left: 4px solid #1f5a96; }');
            printWindow.document.write('.info-item { }');
            printWindow.document.write('.info-label { font-size: 11px; color: #000; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 4px; }');
            printWindow.document.write('.info-value { font-size: 13px; color: #1f5a96; font-weight: 600; }');
            
            // Section styling
            printWindow.document.write('.bom-section { margin-bottom: 25px; page-break-inside: avoid; }');
            printWindow.document.write('h2, h3, h4, h5, h6 { color: #000 !important; }'); printWindow.document.write('.section-title { background: linear-gradient(135deg, #1f5a96 0%, #2d7ab8 100%); color: white; padding: 12px 15px; font-weight: 700; font-size: 13px; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 3px 3px 0 0; }');
            
            // Table styling
            printWindow.document.write('table { width: 100%; border-collapse: collapse; background-color: white; table-layout: fixed; margin-bottom: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 0 0 3px 3px; overflow: hidden; }');
            printWindow.document.write('table th { background-color: #2d7ab8; color: white; font-weight: 700; text-align: center; border: none; padding: 10px 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }');
            printWindow.document.write('table td { border: 1px solid #e0e4e8; padding: 8px 6px; text-align: left; vertical-align: middle; background-color: white; font-weight: 500; }');
            printWindow.document.write('table tbody tr:nth-child(odd) { background-color: #fafbfc; }');
            printWindow.document.write('table tbody tr:hover { background-color: #f0f4f8; }');
            
            // Column widths - with aggressive overrides for all cells
            printWindow.document.write('table th { min-width: 0 !important; max-width: none !important; }');
            printWindow.document.write('table td { min-width: 0 !important; max-width: none !important; }');
            
            // Header column widths
            printWindow.document.write('table thead tr th:nth-child(1) { width: 25% !important; }');
            printWindow.document.write('table thead tr th:nth-child(2) { width: 5% !important; }');
            printWindow.document.write('table thead tr th:nth-child(3) { width: 5% !important; }');
            printWindow.document.write('table thead tr th:nth-child(4) { width: 12.5% !important; }');
            printWindow.document.write('table thead tr th:nth-child(5) { width: 12.5% !important; }');
            printWindow.document.write('table thead tr th:nth-child(6) { width: 12.5% !important; }');
            printWindow.document.write('table thead tr th:nth-child(7) { width: 12.5% !important; }');
            printWindow.document.write('table thead tr th:nth-child(8) { width: 15% !important; }');
            
            // Body column widths
            printWindow.document.write('table tbody tr td:nth-child(1) { width: 25% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(2) { width: 5% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(3) { width: 5% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(4) { width: 12.5% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(5) { width: 12.5% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(6) { width: 12.5% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(7) { width: 12.5% !important; }');
            printWindow.document.write('table tbody tr td:nth-child(8) { width: 15% !important; }');
            
            // Specific positioning and overflow for description column
            printWindow.document.write('table tbody tr td:nth-child(1) { text-align: left; overflow: visible !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: normal !important; white-space: pre-wrap !important; vertical-align: top !important; hyphens: auto !important; }');
            printWindow.document.write('table tbody tr td:nth-child(2) { text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(3) { text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(4) { text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(5) { text-align: right; }');
            printWindow.document.write('table tbody tr td:nth-child(6) { text-align: center; }');
            printWindow.document.write('table tbody tr td:nth-child(7) { text-align: right; }');
            printWindow.document.write('table tbody tr td:nth-child(8) { text-align: right; font-weight: 700; color: #1f5a96; }');
            
            // Total row styling
            printWindow.document.write('.total-row { background-color: #e8f0f7 !important; font-weight: 700; color: #1f5a96; }');
            printWindow.document.write('.total-row td { border-top: 2px solid #1f5a96; border-bottom: 2px solid #1f5a96; padding: 12px 8px; }');
            
            printWindow.document.write('.description-cell { max-width: 100% !important; overflow: hidden !important; word-break: break-all !important; line-height: 1.2 !important; }');
            printWindow.document.write('table tbody tr td:first-child * { max-width: 100% !important; overflow: visible !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: normal !important; display: block !important; white-space: pre-wrap !important; }');
            
            // Form elements
            printWindow.document.write('textarea { display: block !important; width: 100% !important; min-height: auto !important; max-width: 100% !important; border: none !important; background: transparent !important; color: #222 !important; font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 11px; padding: 0 !important; margin: 0 !important; white-space: pre-wrap !important; overflow: visible !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: normal !important; line-height: 1.3; opacity: 1 !important; visibility: visible !important; resize: none !important; height: auto !important; font-weight: 600; }');
            printWindow.document.write('input[type="text"], input[type="number"], input[type="date"] { display: block !important; width: 100% !important; border: none !important; background: transparent !important; color: #222 !important; font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 11px; padding: 0 !important; margin: 0 !important; opacity: 1 !important; visibility: visible !important; font-weight: 600; overflow: visible !important; max-width: 100% !important; word-wrap: break-word !important; overflow-wrap: break-word !important; }');
            
            // Button styling (for on-screen)
            printWindow.document.write('.button-group { margin-bottom: 15px; text-align: center; }');
            printWindow.document.write('.print-button, .close-button { background-color: #1f5a96; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 12px; font-weight: 500; transition: background-color 0.3s; }');
            printWindow.document.write('.print-button:hover, .close-button:hover { background-color: #0f3d6b; }');
            
            // Footer styling
            printWindow.document.write('.page-footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e0e4e8; }');
            printWindow.document.write('.signatures { margin-top: 30px; font-size: 10px; display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 30px; row-gap: 30px; }');
            printWindow.document.write('.signature-row { display: contents; }');
            printWindow.document.write('.signature-block { text-align: center; display: flex; flex-direction: column; align-items: center; min-height: 140px; justify-content: flex-start; }');
            printWindow.document.write('.signature-label { font-weight: 700; color: #000; margin-bottom: 20px; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; line-height: 1.4; }');
            printWindow.document.write('.signature-line { border-top: 1.5px solid #000; width: 140px; margin: 0 auto 5px auto; }');
            printWindow.document.write('.signature-name { font-weight: 700; margin-top: 8px; color: #000; font-size: 11px; }');
            printWindow.document.write('.signature-title { font-size: 9px; margin-top: 2px; color: #000; font-weight: 500; }');
            
            // Force all section-title elements to black
            printWindow.document.write('.section-title, .section-title * { color: #000 !important; background: #f0f0f0 !important; }');
            
            // Color overrides for all gray text
            printWindow.document.write('[style*="color: #999"] { color: #000 !important; }');
            printWindow.document.write('[style*="color: #666"] { color: #000 !important; }');
            printWindow.document.write('[style*="color: #555"] { color: #000 !important; }');
            // Universal gray text override
            printWindow.document.write('* { color: inherit; }');
            printWindow.document.write('*:not(a):not(.total-row):not(.info-value) { color: #000 !important; }');
            // Print media
            printWindow.document.write('@media print { body { margin: 0; padding: 20px; } .button-group { display: none; } .page-header { border-bottom-color: #1f5a96; } .signatures { margin-top: 40px; } }');
            
            // AGGRESSIVE width overrides - force table layout and specific column widths
            printWindow.document.write('table { table-layout: fixed !important; width: 100% !important; border-collapse: collapse !important; }');
            
            // Force remove all inline width constraints
            printWindow.document.write('table th, table td { width: auto !important; min-width: auto !important; max-width: none !important; }');
            
            // Set specific column widths by nth-child - targeting tbody cells for 1-to-1 column mapping
            // 8 columns: Description(25%), Unit(5%), Qty(5%), Mat Unit(12%), Mat Total(12%), Lab Unit(12%), Lab Total(12%), Total(8%)
            printWindow.document.write('table > tbody > tr > td:nth-child(1) { width: 25% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(2) { width: 5% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(3) { width: 5% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(4) { width: 12% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(5) { width: 12% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(6) { width: 12% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(7) { width: 12% !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(8) { width: 8% !important; }');
            
            // Also set thead th widths for colspan headers - use first row only
            printWindow.document.write('table > thead > tr:first-child > th:nth-child(1) { width: 25% !important; }');
            printWindow.document.write('table > thead > tr:first-child > th:nth-child(2) { width: 5% !important; }');
            printWindow.document.write('table > thead > tr:first-child > th:nth-child(3) { width: 5% !important; }');
            printWindow.document.write('table > thead > tr:first-child > th[colspan="2"]:nth-child(4) { width: 24% !important; }');
            printWindow.document.write('table > thead > tr:first-child > th[colspan="2"]:nth-child(5) { width: 24% !important; }');
            printWindow.document.write('table > thead > tr:first-child > th:nth-child(6) { width: 8% !important; }');
            
            // Colgroup styling for definitive width enforcement
            printWindow.document.write('col:nth-child(1) { width: 25% !important; }');
            printWindow.document.write('col:nth-child(2) { width: 5% !important; }');
            printWindow.document.write('col:nth-child(3) { width: 5% !important; }');
            printWindow.document.write('col:nth-child(4) { width: 12% !important; }');
            printWindow.document.write('col:nth-child(5) { width: 12% !important; }');
            printWindow.document.write('col:nth-child(6) { width: 12% !important; }');
            printWindow.document.write('col:nth-child(7) { width: 12% !important; }');
            printWindow.document.write('col:nth-child(8) { width: 8% !important; }');
            
            // Fix text wrapping and overflow issues in cells
            printWindow.document.write('table td, table th { overflow: visible !important; word-wrap: break-word !important; word-break: break-word !important; white-space: normal !important; }');
            printWindow.document.write('table > tbody > tr > td:nth-child(1) { vertical-align: top !important; padding: 8px !important; }');
            printWindow.document.write('textarea { display: none !important; }');
            printWindow.document.write('input { display: none !important; }');
            printWindow.document.write('button { display: none !important; }');
            printWindow.document.write('html, body { background-color: #eef1f5 !important; padding: 0 !important; margin: 0 !important; }');
            printWindow.document.write('.toolbar { position: sticky; top: 0; z-index: 999; background: #1f5a96; color: white; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.25); min-height: 52px; }');
            printWindow.document.write('.toolbar-title { font-size: 14px; font-weight: 700; letter-spacing: 0.3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; }');
            printWindow.document.write('.toolbar-buttons { display: flex; gap: 8px; flex-shrink: 0; }');
            printWindow.document.write('.toolbar-btn { padding: 7px 18px; border: 2px solid rgba(255,255,255,0.55); border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 700; color: white; background: transparent; }');
            printWindow.document.write('.toolbar-btn.print-btn { background: #4CAF50; border-color: #388E3C; }');
            printWindow.document.write('.page-wrapper { max-width: 1280px; margin: 0 auto; background: white; padding: 40px 50px; min-height: calc(100vh - 52px); }');
            printWindow.document.write('@media print { .toolbar { display: none !important; } .page-wrapper { max-width: 100%; padding: 20px; } html, body { background: white !important; } }');
            
            printWindow.document.write('</style>');
            printWindow.document.write('</head>');
            printWindow.document.write('<body>');
            
            // Document type detection for viewer (with auto-detect fallback for old documents)
            const _docTypeRaw2 = (doc.type || '').toLowerCase();
            let isBOM2;
            if (_docTypeRaw2 === 'bom' || _docTypeRaw2 === 'boq') {
                isBOM2 = _docTypeRaw2 === 'bom';
            } else {
                // Auto-detect: BOM has 1 header row, BOQ has 2 header rows
                const _autoDiv2 = document.createElement('div');
                _autoDiv2.innerHTML = doc.htmlContent || '';
                const _autoThead2 = _autoDiv2.querySelector('table thead');
                isBOM2 = _autoThead2 ? _autoThead2.querySelectorAll('tr').length < 2 : false;
            }
            const headerTitle2 = isBOM2 ? 'BILL OF MATERIALS' : 'BILL OF QUANTITIES';
            const docTypeLabel2 = isBOM2 ? 'BOM Document' : 'BOQ Document';
            
            // Sticky toolbar
            printWindow.document.write('<div class="toolbar">');
            printWindow.document.write('<span class="toolbar-title">' + docTypeLabel2 + ' \u2014 ' + doc.name + '</span>');
            printWindow.document.write('<div class="toolbar-buttons">');
            printWindow.document.write('<button class="toolbar-btn print-btn" onclick="window.print()">&#128438; Print</button>');
            printWindow.document.write('<button class="toolbar-btn" onclick="window.close()">&#10005; Close</button>');
            printWindow.document.write('</div></div>');
            
            // Page wrapper
            printWindow.document.write('<div class="page-wrapper">');
            
            // Add professional header
            printWindow.document.write('<div class="page-header">');
            printWindow.document.write('<h1>' + headerTitle2 + '</h1>');
            printWindow.document.write('<div class="document-type">' + docTypeLabel2 + ' \u2014 ' + doc.name + '</div>');
            printWindow.document.write('</div>');
            
            // Add project information header
            printWindow.document.write('<div class="header-info">');
            printWindow.document.write('<div class="info-item"><div class="info-label">Department</div><div class="info-value">' + (doc.department || 'N/A') + '</div></div>');
            printWindow.document.write('<div class="info-item"><div class="info-label">Project</div><div class="info-value">' + (doc.projectName || 'N/A') + '</div></div>');
            printWindow.document.write('<div class="info-item"><div class="info-label">Location</div><div class="info-value">' + (doc.location || 'N/A') + '</div></div>');
            printWindow.document.write('<div class="info-item"><div class="info-label">Date</div><div class="info-value">' + (doc.dateValue || doc.date || 'N/A') + '</div></div>');
            printWindow.document.write('</div>');
            
            // Add document content
            if (doc.htmlContent) {
                // Remove duplicate project-info section if it exists
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = doc.htmlContent;
                const projectInfoElements = tempDiv.querySelectorAll('.project-info');
                projectInfoElements.forEach(el => el.remove());
                
                // Convert textareas to plain text divs for print
                const textareas = tempDiv.querySelectorAll('textarea');
                textareas.forEach(function(ta) {
                    const text = ta.value || ta.textContent || '';
                    const div = document.createElement('div');
                    div.textContent = text;
                    div.style.whiteSpace = 'pre-wrap';
                    div.style.wordWrap = 'break-word';
                    ta.parentNode.replaceChild(div, ta);
                });
                
                // Remove all input elements (unit, qty, costs) and replace with their values
                const inputs = tempDiv.querySelectorAll('input[type="text"], input[type="number"]');
                inputs.forEach(function(inp) {
                    const text = inp.value || '';
                    const span = document.createElement('span');
                    span.textContent = text;
                    inp.parentNode.replaceChild(span, inp);
                });
                
                // CRITICAL: Strip all inline width styles from table headers and cells
                // This removes the hardcoded widths from bom_labor.php so our CSS can take effect
                const allTableElements = tempDiv.querySelectorAll('th, td');
                allTableElements.forEach(function(el) {
                    // Get the style attribute and parse each CSS property
                    const style = el.getAttribute('style') || '';
                    if (style) {
                        // Split by semicolon and filter out width properties
                        const rules = style.split(';')
                            .map(rule => rule.trim())
                            .filter(rule => rule && !rule.toLowerCase().startsWith('width'))
                            .join('; ');
                        
                        if (rules && rules.trim()) {
                            el.setAttribute('style', rules);
                        } else {
                            el.removeAttribute('style');
                        }
                    }
                    
                    // Also explicitly clear any width attributes
                    el.removeAttribute('width');
                    el.style.width = '';
                });

                // --- Synchronous column-structure fix (runs before writing to print window) ---
                // Step 1: Remove all colgroups to eliminate phantom-column artifacts
                tempDiv.querySelectorAll('colgroup').forEach(function(cg) { cg.remove(); });

                // Step 2: Fix total-row tables (no thead) — extend last cell colspan to cover all expected columns
                var _expectedCols2 = isBOM2 ? 5 : 8;
                tempDiv.querySelectorAll('table').forEach(function(tbl) {
                    if (!tbl.querySelector('thead')) {
                        tbl.querySelectorAll('tr').forEach(function(row) {
                            var cells = Array.from(row.querySelectorAll('td'));
                            if (!cells.length) return;
                            var covered = 0;
                            cells.forEach(function(c) { covered += parseInt(c.getAttribute('colspan') || 1); });
                            if (covered < _expectedCols2) {
                                var last = cells[cells.length - 1];
                                last.setAttribute('colspan', parseInt(last.getAttribute('colspan') || 1) + (_expectedCols2 - covered));
                            }
                        });
                    }
                });

                // Step 3: Inject correct-width style block into the output HTML
                // BOM (5-col): 45+10+10+17+18 = 100%
                // BOQ (8-col): 25+8+7+12+12+12+12+12 = 100%  (matches bom_labor.php inline header widths)
                var _colStyle2 = document.createElement('style');
                _colStyle2.textContent = isBOM2
                    ? 'table{table-layout:fixed!important;width:100%!important}' +
                      'table>thead>tr>th:nth-child(1){width:45%!important}' +
                      'table>thead>tr>th:nth-child(2){width:10%!important}' +
                      'table>thead>tr>th:nth-child(3){width:10%!important}' +
                      'table>thead>tr>th:nth-child(4){width:17%!important}' +
                      'table>thead>tr>th:nth-child(5){width:18%!important}' +
                      'table>tbody>tr>td:nth-child(1){width:45%!important}' +
                      'table>tbody>tr>td:nth-child(2){width:10%!important}' +
                      'table>tbody>tr>td:nth-child(3){width:10%!important}' +
                      'table>tbody>tr>td:nth-child(4){width:17%!important}' +
                      'table>tbody>tr>td:nth-child(5){width:18%!important}'
                    : 'table{table-layout:fixed!important;width:100%!important}' +
                      'table>thead>tr:first-child>th:nth-child(1){width:25%!important}' +
                      'table>thead>tr:first-child>th:nth-child(2){width:8%!important}' +
                      'table>thead>tr:first-child>th:nth-child(3){width:7%!important}' +
                      'table>thead>tr:first-child>th:nth-child(4){width:24%!important}' +
                      'table>thead>tr:first-child>th:nth-child(5){width:24%!important}' +
                      'table>thead>tr:first-child>th:nth-child(6){width:12%!important}' +
                      'table>tbody>tr>td:nth-child(1){width:25%!important}' +
                      'table>tbody>tr>td:nth-child(2){width:8%!important}' +
                      'table>tbody>tr>td:nth-child(3){width:7%!important}' +
                      'table>tbody>tr>td:nth-child(4){width:12%!important}' +
                      'table>tbody>tr>td:nth-child(5){width:12%!important}' +
                      'table>tbody>tr>td:nth-child(6){width:12%!important}' +
                      'table>tbody>tr>td:nth-child(7){width:12%!important}' +
                      'table>tbody>tr>td:nth-child(8){width:12%!important}';
                tempDiv.insertBefore(_colStyle2, tempDiv.firstChild);
                // --- End synchronous fix ---

                printWindow.document.write(tempDiv.innerHTML);
            } else {
                printWindow.document.write('<p>No content available for this document.</p>');
            }
            
            printWindow.document.write('</div>'); // close .page-wrapper
            printWindow.document.write('</div>'); // close .page-wrapper
            printWindow.document.write('</body>');
            printWindow.document.write('</html>');
            printWindow.document.close();
            
            // Safety-net: reinforce correct column widths and strip any stray colgroups after render
            setTimeout(function() {
                try {
                    // Remove any colgroups that survived or were re-inserted
                    printWindow.document.querySelectorAll('colgroup').forEach(function(cg) { cg.remove(); });
                    // Append a final reinforcing style (BOM: 100% total, BOQ: 100% total)
                    var _safeStyle2 = printWindow.document.createElement('style');
                    _safeStyle2.textContent = isBOM2
                        ? 'table{table-layout:fixed!important;width:100%!important}' +
                          'table>tbody>tr>td:nth-child(1){width:45%!important}' +
                          'table>tbody>tr>td:nth-child(2){width:10%!important}' +
                          'table>tbody>tr>td:nth-child(3){width:10%!important}' +
                          'table>tbody>tr>td:nth-child(4){width:17%!important}' +
                          'table>tbody>tr>td:nth-child(5){width:18%!important}'
                        : 'table{table-layout:fixed!important;width:100%!important}' +
                          'table>tbody>tr>td:nth-child(1){width:25%!important}' +
                          'table>tbody>tr>td:nth-child(2){width:8%!important}' +
                          'table>tbody>tr>td:nth-child(3){width:7%!important}' +
                          'table>tbody>tr>td:nth-child(4){width:12%!important}' +
                          'table>tbody>tr>td:nth-child(5){width:12%!important}' +
                          'table>tbody>tr>td:nth-child(6){width:12%!important}' +
                          'table>tbody>tr>td:nth-child(7){width:12%!important}' +
                          'table>tbody>tr>td:nth-child(8){width:12%!important}';
                    printWindow.document.head.appendChild(_safeStyle2);
                } catch(e) {}
            }, 50);
            
            // Fix textareas to show their content
            setTimeout(function() {
                try {
                    const textareas = printWindow.document.querySelectorAll('textarea');
                    textareas.forEach(function(ta) {
                        // Try to get value from value attribute first, then textContent
                        const textValue = ta.value || ta.textContent || ta.innerText || '';
                        if (textValue && textValue.trim()) {
                            ta.style.display = 'block';
                            ta.style.color = '#000';
                            ta.style.background = 'white';
                            ta.style.overflow = 'visible';
                            ta.style.whiteSpace = 'pre-wrap';
                            ta.style.wordWrap = 'break-word';
                            ta.style.opacity = '1';
                            ta.style.visibility = 'visible';
                            // Set both value and textContent to ensure visibility
                            ta.value = textValue;
                            ta.textContent = textValue;
                            ta.innerHTML = textValue;
                        }
                    });
                    
                    const inputs = printWindow.document.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]');
                    inputs.forEach(function(inp) {
                        const inputValue = inp.value || inp.getAttribute('value') || '';
                        if (inputValue && inputValue.trim()) {
                            inp.style.color = '#000';
                            inp.style.background = 'white';
                            inp.style.opacity = '1';
                            inp.style.visibility = 'visible';
                            inp.value = inputValue;
                        }
                    });
                    
                    // Fix select elements to show selected value as text
                    const selects = printWindow.document.querySelectorAll('select');
                    selects.forEach(function(sel) {
                        const selectedOption = sel.options[sel.selectedIndex];
                        const selectedText = selectedOption ? selectedOption.text : sel.value || '';
                        if (selectedText) {
                            // Create a span to display the selected value as text
                            const span = printWindow.document.createElement('span');
                            span.textContent = selectedText;
                            span.style.color = '#000';
                            span.style.background = 'white';
                            span.style.padding = '2px 4px';
                            span.style.display = 'inline-block';
                            span.style.whiteSpace = 'normal';
                            span.style.wordWrap = 'break-word';
                            span.style.overflow = 'visible';
                            // Replace select with span showing the value
                            sel.parentNode.replaceChild(span, sel);
                        }
                    });
                    
                    // Auto-size textareas to fit their content and convert to div for better print display
                    const allTextareas = printWindow.document.querySelectorAll('textarea');
                    allTextareas.forEach(function(ta) {
                        const textContent = ta.value || ta.textContent || '';
                        if (textContent.trim()) {
                            // Create a div to replace textarea with proper text display
                            const div = printWindow.document.createElement('div');
                            div.textContent = textContent;
                            div.style.display = 'block';
                            div.style.color = '#000';
                            div.style.background = 'white';
                            div.style.padding = '0';
                            div.style.margin = '0';
                            div.style.border = 'none';
                            div.style.fontFamily = '"Segoe UI", Calibri, Arial, sans-serif';
                            div.style.fontSize = '11px';
                            div.style.lineHeight = '1.3';
                            div.style.whiteSpace = 'pre-wrap';
                            div.style.wordWrap = 'break-word';
                            div.style.overflowWrap = 'break-word';
                            div.style.overflow = 'visible';
                            div.style.opacity = '1';
                            div.style.visibility = 'visible';
                            div.style.minHeight = 'auto';
                            // Calculate approximate height based on line count
                            const lineCount = textContent.split('\n').length;
                            const calculatedHeight = Math.max(lineCount * 16, 40);
                            div.style.height = calculatedHeight + 'px';
                            // Replace textarea with div
                            ta.parentNode.replaceChild(div, ta);
                        }
                    });
                } catch(e) {
                    // Error silently if unable to access print window
                }
            }, 100);
            
            printWindow.focus();
        }
        // Close viewer modal
        function closeViewer() {
            const modal = document.getElementById('viewerModal');
            modal.classList.remove('show');
        }
        // Print document
        function printDocument() {
            const modalBody = document.querySelector('.modal-body');
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Document</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; font-size: 11px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }');
            printWindow.document.write('table th, table td { border: 1px solid #000; padding: 5px 8px; text-align: left; }');
            printWindow.document.write('table th { background-color: #FFFF00; font-weight: bold; text-align: center; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(document.getElementById('documentContent').innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
        // Download PDF
        function downloadPDF() {
            const element = document.getElementById('documentContent');
            const docName = window.currentDocument ? window.currentDocument.name : 'document';
            
            // Create a simple HTML to PDF conversion using browser's print to PDF
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>' + docName + '</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }');
            printWindow.document.write('table th, table td { border: 1px solid #000; padding: 5px 8px; text-align: left; }');
            printWindow.document.write('table th { background-color: #FFFF00; font-weight: bold; text-align: center; }');
            printWindow.document.write('.total-row { background-color: #FFFF00; font-weight: bold; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write(element.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            // Trigger print dialog which allows saving as PDF
            setTimeout(function() {
                printWindow.focus();
                printWindow.print();
            }, 250);
        }
        
        // Display Unposted Documents
        function displayUnpostedDocuments() {
            const tbody = document.getElementById('unpostedBody');
            
            if (!tbody) return;
            
            // Use unpostedDocuments loaded from server (not localStorage)
            // unpostedDocuments is populated by loadDocumentsFromServer()
            
            if (unpostedDocuments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-documents">No unposted documents</td></tr>';
                return;
            }
            
            tbody.innerHTML = unpostedDocuments.map((doc, index) => {
                // Show creator info so users know who created the unposted document
                const creatorInfo = doc.createdBy ? ` <small style="color: #666;">(by: ${doc.createdBy})</small>` : '';
                return `
                <tr>
                    <td><strong>${doc.name}</strong>${creatorInfo}</td>
                    <td>${doc.refNo}</td>
                    <td><span class="status-badge" style="background-color: #FFF3E0; color: #E65100;">${doc.type || doc.originalType?.toUpperCase() || 'BOM'}</span></td>
                    <td><span class="document-date">${doc.date}</span></td>
                    <td><span class="status-badge" style="background-color: #FFE0B2; color: #E65100;">Unposted</span></td>
                    <td>
                        <div class="button-cell">
                            <button class="view-btn" data-type="unposted" data-index="${index}">View</button>
                            <button class="print-btn" data-type="unposted" data-index="${index}">Print</button>
                            <button class="post-btn" data-type="unposted" data-index="${index}" style="background-color: #4CAF50;">Edit and Repost</button>
                        </div>
                    </td>
                </tr>
            `}).join('');
            
            // Add event listeners to unposted view buttons
            document.querySelectorAll('#unpostedBody .view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    viewSavedDocument(unpostedDocuments[index]);
                });
            });
            
            // Add event listeners to unposted print buttons
            document.querySelectorAll('#unpostedBody .print-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    printSavedDocument(unpostedDocuments[index]);
                });
            });
            
            // Add event listeners to unposted edit and repost buttons
            document.querySelectorAll('#unpostedBody .post-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    editAndRepostDocument(index);
                });
            });
        }
        
        // Display Posted documents from price_edit_bomboq.php
        // Display Posted documents from price_edit_bomboq.php
        function displayPostedDocuments() {
            const tbody = document.getElementById('postedBody');
            
            if (!tbody) return;
            
            if (postedDocuments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-documents">No posted documents yet</td></tr>';
                return;
            }
            tbody.innerHTML = postedDocuments.map((doc, index) => {
                const deleteBtn = isAdmin ? `<button class="delete-btn" data-type="posted" data-index="${index}" style="background-color: #f44336;">Delete</button>` : '';
                return `
                <tr>
                    <td><strong>${doc.name}</strong></td>
                    <td>${doc.refNo}</td>
                    <td><span class="status-badge status-completed">${doc.type || 'BOM'}</span></td>
                    <td><span class="document-date">${doc.date}</span></td>
                    <td><span class="status-badge status-completed">Completed</span></td>
                    <td>
                        <div class="button-cell">
                            <button class="view-btn" data-type="posted" data-index="${index}">View</button>
                            <button class="print-btn" data-type="posted" data-index="${index}">Print</button>
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>
            `}).join('');
            // Add event listeners to posted view buttons
            document.querySelectorAll('#postedBody .view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    viewSavedDocument(postedDocuments[index]);
                });
            });
            // Add event listeners to posted print buttons
            document.querySelectorAll('#postedBody .print-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    printSavedDocument(postedDocuments[index]);
                });
            });
            // Add event listeners to posted delete buttons (Admin only)
            if (isAdmin) {
                document.querySelectorAll('#postedBody .delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const index = parseInt(this.getAttribute('data-index'));
                        deletePostedDocument(index);
                    });
                });
            }
        }

        // Delete posted document (Admin only)
        async function deletePostedDocument(index) {
            if (!isAdmin) {
                alert('Only administrators can delete posted documents.');
                return;
            }
            
            const doc = postedDocuments[index];
            if (!doc) {
                alert('Document not found');
                return;
            }
            
            if (!confirm('Are you sure you want to delete "' + doc.name + '" from Posted Documents?')) {
                return;
            }
            
            try {
                // Sync delete to server first
                const serverResult = await DocumentSync.deleteDocument(doc.name, 'posted');
                
                if (!serverResult.success) {
                    console.warn('Server delete warning:', serverResult.message);
                }
                
                // Remove from postedDocuments array
                postedDocuments.splice(index, 1);
                localStorage.setItem('postedDocuments', JSON.stringify(postedDocuments));
                
                // Refresh display
                displayPostedDocuments();
                
                // Log activity
                logActivityToServer('delete_posted_file', `Deleted posted file: ${doc.name} (Ref: ${doc.refNo})`, 'save_bomboq.php');
                
                alert('Document "' + doc.name + '" has been deleted from Posted Documents and synced to server.');
            } catch (error) {
                console.error('Error deleting posted document:', error);
                alert('Error deleting document: ' + error.message);
            }
        }
        // Post document to price_edit_bomboq.php for pricing
        async function postDocument(type, index) {
            // Check if user is Guest
            if (userRole === 'guest') {
                alert('Guests are not allowed to post files. Please contact an administrator or user.');
                return;
            }
            
            const doc = type === 'bom' ? savedDocuments.bom[index] : savedDocuments.boq[index];
            
            if (!doc) {
                alert('Document not found');
                return;
            }
            
            try {
                // Update status on server first
                const serverResult = await DocumentSync.updateStatus(doc.name, doc.status || 'saved', 'pending_price_edit');
                
                if (!serverResult.success) {
                    console.warn('Server update warning:', serverResult.message);
                }
                
                // Mark document as posted locally
                doc.status = 'posted';
                doc.postedDate = new Date().toLocaleDateString();
                
                // Save to pendingPriceEdit storage for price_edit_bomboq.php
                let pendingDocs = JSON.parse(localStorage.getItem('pendingPriceEdit') || '[]');
                doc.originalType = type;
                doc.originalIndex = index;
                pendingDocs.push(doc);
                localStorage.setItem('pendingPriceEdit', JSON.stringify(pendingDocs));
                
                // Update original storage
                if (type === 'bom') {
                    localStorage.setItem('bomDocuments', JSON.stringify(savedDocuments.bom));
                } else {
                    localStorage.setItem('boqDocuments', JSON.stringify(savedDocuments.boq));
                }
                
                // Refresh display
                displayBOMDocuments();
                displayBOQDocuments();
                
                // Log activity
                const fileType = type.toUpperCase();
                logActivityToServer('post_file', `Posted ${fileType} file: ${doc.name} (Ref: ${doc.refNo})`, 'save_bomboq.php');
                
                alert('Document "' + doc.name + '" has been posted to Price Edit page and synced to server.');
            } catch (error) {
                console.error('Error posting document:', error);
                alert('Error posting document: ' + error.message);
            }
        }
        
        // Edit and Repost unposted document
        function editAndRepostDocument(index) {
            const doc = unpostedDocuments[index];
            
            if (!doc) {
                alert('Document not found');
                return;
            }
            
            // Store current document index for saving
            window.currentEditingUnpostedDoc = {
                index: index,
                doc: doc
            };
            
            // Open modal with editable content
            const modal = document.getElementById('viewerModal');
            const modalTitle = document.getElementById('modalTitle');
            const documentContent = document.getElementById('documentContent');
            
            modalTitle.textContent = 'Edit Document: ' + doc.name;
            
            // Display document content in editable form
            if (doc.htmlContent) {
                documentContent.innerHTML = doc.htmlContent;
                
                // Make all inputs, textareas, and selects editable
                documentContent.querySelectorAll('input, textarea, select').forEach(function(element) {
                    element.removeAttribute('readonly');
                    element.removeAttribute('disabled');
                    element.style.backgroundColor = 'white';
                    element.style.border = '1px solid #ddd';
                });
                
                // Remove any existing save button and add new one
                const existingSaveBtn = document.getElementById('saveAndRepostBtn');
                if (existingSaveBtn) {
                    existingSaveBtn.remove();
                }
                
                // Add Save and Repost button to modal footer
                const modalFooter = document.querySelector('.modal-footer');
                const saveAndRepostBtn = document.createElement('button');
                saveAndRepostBtn.id = 'saveAndRepostBtn';
                saveAndRepostBtn.className = 'modal-btn';
                saveAndRepostBtn.style.backgroundColor = '#4CAF50';
                saveAndRepostBtn.style.color = 'white';
                saveAndRepostBtn.textContent = 'Save and Repost';
                saveAndRepostBtn.onclick = saveAndRepost;
                
                // Insert before the close button
                const closeBtn = modalFooter.querySelector('.modal-close-btn');
                modalFooter.insertBefore(saveAndRepostBtn, closeBtn);
                
            } else {
                documentContent.innerHTML = '<p>No content available for editing.</p>';
            }
            
            modal.classList.add('show');
        }
        
        // Save and Repost edited document
        async function saveAndRepost() {
            if (!window.currentEditingUnpostedDoc) {
                alert('Error: No document being edited');
                return;
            }
            
            const { index, doc } = window.currentEditingUnpostedDoc;
            const documentContent = document.getElementById('documentContent');
            
            // Sync all form element values to serializable attributes before capturing HTML
            documentContent.querySelectorAll('textarea').forEach(ta => {
                ta.textContent = ta.value;
                ta.innerHTML = ta.value;
            });
            
            documentContent.querySelectorAll('input[type="text"], input[type="number"], input[type="date"]').forEach(inp => {
                inp.setAttribute('value', inp.value);
            });
            
            // Sync select elements (e.g., UNIT dropdowns) so chosen values are preserved in HTML
            documentContent.querySelectorAll('select').forEach(sel => {
                Array.from(sel.options).forEach(opt => {
                    if (opt.value === sel.value) {
                        opt.setAttribute('selected', 'selected');
                    } else {
                        opt.removeAttribute('selected');
                    }
                });
            });
            
            // Capture the edited HTML content (includes updated UNIT, QTY, and all inputs)
            const updatedHtmlContent = documentContent.innerHTML;
            doc.htmlContent = updatedHtmlContent;
            doc.lastEdited = new Date().toLocaleString();
            
            // Embed the updated htmlContent into items[0] so the server stores it correctly
            if (doc.items && doc.items.length > 0) {
                doc.items[0].htmlContent = updatedHtmlContent;
            } else {
                doc.items = [{ htmlContent: updatedHtmlContent }];
            }
            
            // Status must be 'pending_price_edit' so price_edit_bomboq.php shows the document
            doc.status = 'pending_price_edit';
            delete doc.unpostedDate;
            
            // Disable button while saving to prevent double-clicks
            const saveBtn = document.getElementById('saveAndRepostBtn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }
            
            try {
                // Persist changes to server database (price_edit_bomboq.php reads from server)
                const response = await fetch('document_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_price_edit',
                        documentName: doc.name,
                        documentType: doc.type,
                        items: doc.items,
                        status: 'pending_price_edit'
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    console.error('Server save failed:', result.message);
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save and Repost';
                    }
                    alert('Failed to save to server: ' + result.message);
                    return;
                }
                
                // Server confirmed — remove from local unposted list
                unpostedDocuments.splice(index, 1);
                
                // Close modal and clean up
                closeViewer();
                if (saveBtn) saveBtn.remove();
                delete window.currentEditingUnpostedDoc;
                
                // Refresh displays
                displayUnpostedDocuments();
                
                // Log activity
                logActivityToServer('repost_file', `Reposted file after editing: ${doc.name} (Ref: ${doc.refNo})`, 'save_bomboq.php');
                
                alert('Document "' + doc.name + '" has been saved and is ready for price editing again.\n\nYou can now go to the Price Edit page to continue.');
                
                if (confirm('Would you like to go to the Price Edit page now?')) {
                    window.location.href = 'price_edit_bomboq.php';
                }
                
            } catch (error) {
                console.error('Error saving document to server:', error);
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save and Repost';
                }
                alert('Error saving document: ' + error.message);
            }
        }
        
        // Go back to previous page
        function goBack() {
            window.history.back();
        }
        function backToHome() {
            window.location.href = 'index.php';
        }
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'save_bomboq.php?logout=1';
            }
        }
        // Close modal when clicking the X button
        document.addEventListener('DOMContentLoaded', function() {
            const closeBtn = document.getElementById('closeModalBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeViewer);
            }
            
            // Close modal when clicking outside of it
            const modal = document.getElementById('viewerModal');
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeViewer();
                }
            });
        });
    </script>
    <script src="themes.js"></script>
</body>
</html>