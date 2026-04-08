<?php
/**
 * Document API - Handle AJAX requests for document operations
 * Supports: save, load, delete, list, update_status
 */

// Suppress HTML error output - we need clean JSON responses
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header early
header('Content-Type: application/json');

// Custom error handler to return JSON errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit();
});

// Custom exception handler
set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    exit();
});

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get username from session (try both possible keys)
$username = null;
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
} elseif (isset($_SESSION['admin_username'])) {
    $username = $_SESSION['admin_username'];
} else {
    echo json_encode(['success' => false, 'message' => 'Username not found in session']);
    exit();
}

// Get user role and department from session
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
$userDepartment = isset($_SESSION['user_department']) ? trim($_SESSION['user_department']) : '';
$isPurchasingUser = ($userRole === 'user' && strtolower($userDepartment) === 'purchasing');
$isAdminUser = ($userRole === 'admin' && strtolower($userDepartment) === 'admin');

// Try to include required files with error handling
try {
    require_once __DIR__ . '/document_manager.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load document manager: ' . $e->getMessage()]);
    exit();
}

// Activity logger is optional - don't fail if it doesn't work
@include_once 'activity_logger_db.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get JSON input for POST requests
if ($method === 'POST') {
    $input = file_get_contents('php://input');
    
    // Check if input is empty or null
    if (empty($input)) {
        echo json_encode(['success' => false, 'message' => 'No input data received']);
        exit();
    }
    
    $data = json_decode($input, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit();
    }
    
    if (!$data || !isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request - missing action']);
        exit();
    }
    
    $action = $data['action'];
    
    switch ($action) {
        case 'save':
            // Save or update document
            if (!isset($data['documentName']) || !isset($data['documentType']) || !isset($data['items'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit();
            }
            
            $result = saveDocument(
                $data['documentName'],
                $data['documentType'],
                $data['items'],
                $username,
                isset($data['status']) ? $data['status'] : 'saved'
            );
            
            if ($result['success']) {
                logActivity('save', "Document: {$data['documentName']}", 'document_api.php');
            }
            
            echo json_encode($result);
            break;
            
        case 'load':
            // Load a specific document
            if (!isset($data['documentName'])) {
                echo json_encode(['success' => false, 'message' => 'Document name required']);
                exit();
            }
            
            $doc = getDocument($data['documentName'], $username);
            
            if ($doc) {
                echo json_encode(['success' => true, 'document' => $doc]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Document not found']);
            }
            break;
            
        case 'list':
            // List documents based on user role and department:
            // - Admin sees ALL documents
            // - Purchasing sees pending/posted for price editing
            // - Users with department see their own + same department files + unposted
            // - Users without department see only their own + unposted
            $status = isset($data['status']) ? $data['status'] : null;
            
            if ($isAdminUser) {
                // Admin users see ALL documents on the server
                $docs = getAllDocuments();
            } else if ($isPurchasingUser) {
                // Purchasing users see all documents available for price editing
                $docs = getDocumentsForPurchasing();
            } else if (!empty($userDepartment)) {
                // Users with a department see their department's files + unposted
                $docs = getDocumentsByDepartment($userDepartment, $username);
            } else {
                // Users without department see their own documents + all unposted
                $docs = getUserDocumentsWithUnposted($username, $status);
            }
            
            echo json_encode(['success' => true, 'documents' => $docs]);
            break;
            
        case 'delete':
            // Delete document
            if (!isset($data['documentName'])) {
                echo json_encode(['success' => false, 'message' => 'Document name required']);
                exit();
            }
            
            // Admin and Purchasing users can delete any document
            // Same department users can delete each other's documents
            if ($isAdminUser || $isPurchasingUser) {
                $success = deleteDocumentByName($data['documentName']);
            } else if (!empty($userDepartment) && canUserAccessDocument($data['documentName'], $username, $userDepartment)) {
                // User can delete if they have access (same department or own doc)
                $success = deleteDocumentByName($data['documentName']);
            } else {
                $success = deleteDocument($data['documentName'], $username);
            }
            
            if ($success) {
                logActivity('delete', "Document: {$data['documentName']}", 'document_api.php');
                echo json_encode(['success' => true, 'message' => 'Document deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete document']);
            }
            break;
            
        case 'update_status':
            // Update document status
            if (!isset($data['documentName']) || !isset($data['status'])) {
                echo json_encode(['success' => false, 'message' => 'Document name and status required']);
                exit();
            }
            
            // Admin and Purchasing users can update status of any document
            // Same department users can update each other's document status
            if ($isAdminUser || $isPurchasingUser) {
                $success = updateDocumentStatusByName($data['documentName'], $data['status']);
            } else if (!empty($userDepartment) && canUserAccessDocument($data['documentName'], $username, $userDepartment)) {
                // User can update if they have access (same department or own doc)
                $success = updateDocumentStatusByName($data['documentName'], $data['status']);
            } else {
                $success = updateDocumentStatus($data['documentName'], $username, $data['status']);
            }
            
            if ($success) {
                logActivity($data['status'], "Document: {$data['documentName']}", 'document_api.php');
                echo json_encode(['success' => true, 'message' => 'Status updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            break;
        
        case 'save_price_edit':
            // Save price edit changes (for Admin, Purchasing, and same department users)
            if (!isset($data['documentName']) || !isset($data['items'])) {
                echo json_encode(['success' => false, 'message' => 'Document name and items required']);
                exit();
            }
            
            $status = isset($data['status']) ? $data['status'] : 'pending_price_edit';
            $targetDepartment = isset($data['targetDepartment']) ? $data['targetDepartment'] : null;
            
            // Admin and Purchasing users can update any document
            // Same department users can update each other's documents
            if ($isAdminUser || $isPurchasingUser) {
                $result = updateDocumentByName($data['documentName'], $data['items'], $status, $targetDepartment);
            } else if (!empty($userDepartment) && canUserAccessDocument($data['documentName'], $username, $userDepartment)) {
                // User can edit if they have access (same department or own doc)
                $result = updateDocumentByName($data['documentName'], $data['items'], $status, $targetDepartment);
            } else {
                // Check if document exists - if so, user doesn't have permission
                // If not, they can create their own
                require_once __DIR__ . '/document_manager.php';
                global $db;
                $checkStmt = $db->prepare("SELECT id FROM documents WHERE name = ?");
                $checkStmt->bind_param('s', $data['documentName']);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Document exists but user doesn't have access
                    echo json_encode(['success' => false, 'message' => 'Access denied: You do not have permission to modify this document']);
                    exit();
                }
                
                // Document doesn't exist, create it
                $result = saveDocument(
                    $data['documentName'],
                    isset($data['documentType']) ? $data['documentType'] : 'bom',
                    $data['items'],
                    $username,
                    $status
                );
            }
            
            if ($result['success']) {
                logActivity('save_price_edit', "Document: {$data['documentName']}" . ($targetDepartment ? " to department: $targetDepartment" : ""), 'document_api.php');
            }
            
            echo json_encode($result);
            break;
            
        case 'get_count':
            // Get document count
            $status = isset($data['status']) ? $data['status'] : null;
            $count = getDocumentCount($username, $status);
            
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'sync':
            // Sync documents from database to localStorage
            $docs = getUserDocuments($username);
            
            // Organize by status for localStorage
            $organized = [
                'saved' => [],
                'posted' => [],
                'pending_price_edit' => [],
                'unposted' => []
            ];
            
            foreach ($docs as $doc) {
                $organized[$doc['status']][$doc['name']] = [
                    'type' => $doc['type'],
                    ($doc['type'] === 'bom' ? 'bomItems' : 'boqItems') => $doc['items']
                ];
            }
            
            echo json_encode(['success' => true, 'documents' => $organized]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
