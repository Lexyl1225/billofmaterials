<?php
/**
 * Document Manager - Phase 2 Database Integration
 * Handles BOM/BOQ document storage and retrieval from MySQL database
 */

require_once __DIR__ . '/db-config.php';

/**
 * Save or update a document in the database
 * @param string $documentName Document name
 * @param string $documentType 'bom' or 'boq'
 * @param array $items Array of document items
 * @param string $username User who owns the document
 * @param string $status Document status (saved/posted/pending_price_edit/unposted)
 * @return array Result with success status and message
 */
function saveDocument($documentName, $documentType, $items, $username, $status = 'saved') {
    global $db;
    
    // Validate inputs
    if (empty($documentName) || empty($documentType) || empty($username)) {
        return ['success' => false, 'message' => 'Missing required fields'];
    }
    
    // Convert items array to JSON
    $itemsJson = json_encode($items);

    // Extract html_content if available (first item's htmlContent) to satisfy DB schema
    $htmlContent = '';
    if (is_array($items) && count($items) > 0) {
        $first = $items[0];
        if (is_array($first) && isset($first['htmlContent'])) {
            $htmlContent = $first['htmlContent'];
        }
    }
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("saveDocument: User not found - $username");
        return ['success' => false, 'message' => "User '$username' not found in database. Please contact administrator."];
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    // Check if document already exists
    $stmt = $db->prepare("SELECT id FROM documents WHERE name = ? AND created_by = ?");
    $stmt->bind_param('si', $documentName, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing document
        $row = $result->fetch_assoc();
        $docId = $row['id'];
        
        $stmt = $db->prepare("UPDATE documents SET type = ?, items = ?, status = ?, html_content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssssi', $documentType, $itemsJson, $status, $htmlContent, $docId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Document updated successfully', 'id' => $docId];
        } else {
            return ['success' => false, 'message' => 'Failed to update document: ' . $stmt->error];
        }
    } else {
        // Insert new document - generate ref_no
        $refNo = 'DOC-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $stmt = $db->prepare("INSERT INTO documents (name, ref_no, type, items, html_content, created_by, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('sssssis', $documentName, $refNo, $documentType, $itemsJson, $htmlContent, $userId, $status);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Document saved successfully', 'id' => $db->insert_id];
        } else {
            return ['success' => false, 'message' => 'Failed to save document: ' . $stmt->error];
        }
    }
}

/**
 * Get a specific document by name and username
 * @param string $documentName Document name
 * @param string $username Document owner
 * @return array|null Document data or null if not found
 */
function getDocument($documentName, $username) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE d.name = ? AND d.created_by = ?");
    $stmt->bind_param('si', $documentName, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $doc = $result->fetch_assoc();
        // Decode items JSON back to array (handle null/empty)
        $doc['items'] = !empty($doc['items']) ? json_decode($doc['items'], true) : [];
        return $doc;
    }
    
    return null;
}

/**
 * Get all documents for a specific user
 * @param string $username Document owner
 * @param string $status Optional status filter
 * @return array Array of documents
 */
function getUserDocuments($username, $status = null) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [];
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    if ($status) {
        $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE d.created_by = ? AND d.status = ? ORDER BY d.updated_at DESC");
        $stmt->bind_param('is', $userId, $status);
    } else {
        $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE d.created_by = ? ORDER BY d.updated_at DESC");
        $stmt->bind_param('i', $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Get user documents PLUS all unposted documents from any user
 * This allows all users to see unposted files made by Purchasing department
 * @param string $username Document owner
 * @param string $status Optional status filter
 * @return array Array of documents
 */
function getUserDocumentsWithUnposted($username, $status = null) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [];
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    // Get user's own documents + ALL unposted documents from any user
    if ($status) {
        $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE (d.created_by = ? AND d.status = ?) OR d.status = 'unposted' ORDER BY d.updated_at DESC");
        $stmt->bind_param('is', $userId, $status);
    } else {
        $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE d.created_by = ? OR d.status = 'unposted' ORDER BY d.updated_at DESC");
        $stmt->bind_param('i', $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    $seenNames = []; // Track document names to avoid duplicates
    while ($row = $result->fetch_assoc()) {
        // Avoid duplicates (user's own unposted doc might appear twice)
        if (!in_array($row['name'], $seenNames)) {
            $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
            $documents[] = $row;
            $seenNames[] = $row['name'];
        }
    }
    
    return $documents;
}

/**
 * Get all documents by status (for admin/global view)
 * @param string $status Status filter
 * @return array Array of documents
 */
function getDocumentsByStatus($status) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM documents WHERE status = ? ORDER BY updated_at DESC");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Update document status
 * @param string $documentName Document name
 * @param string $username Document owner
 * @param string $newStatus New status
 * @return bool Success status
 */
function updateDocumentStatus($documentName, $username, $newStatus) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    $stmt = $db->prepare("UPDATE documents SET status = ?, updated_at = NOW() WHERE name = ? AND created_by = ?");
    $stmt->bind_param('ssi', $newStatus, $documentName, $userId);
    
    return $stmt->execute();
}

/**
 * Delete a document
 * @param string $documentName Document name
 * @param string $username Document owner
 * @return bool Success status
 */
function deleteDocument($documentName, $username) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    $stmt = $db->prepare("DELETE FROM documents WHERE name = ? AND created_by = ?");
    $stmt->bind_param('si', $documentName, $userId);
    
    return $stmt->execute();
}

/**
 * Check if document exists
 * @param string $documentName Document name
 * @param string $username Document owner
 * @return bool True if exists
 */
function documentExists($documentName, $username) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE name = ? AND created_by = ?");
    $stmt->bind_param('si', $documentName, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

/**
 * Get document count by status for a user
 * @param string $username Document owner
 * @param string $status Status filter
 * @return int Count of documents
 */
function getDocumentCount($username, $status = null) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return 0;
    }
    
    $userId = $result->fetch_assoc()['id'];
    
    if ($status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE created_by = ? AND status = ?");
        $stmt->bind_param('is', $userId, $status);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE created_by = ?");
        $stmt->bind_param('i', $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return (int)$row['count'];
}

/**
 * Get all documents (for admin/global access)
 * @return array Array of all documents
 */
function getAllDocuments() {
    global $db;
    
    $result = $db->query("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id ORDER BY d.updated_at DESC");
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Get documents for Purchasing department - all pending_price_edit and posted documents
 * @return array Array of documents for price editing
 */
function getDocumentsForPurchasing() {
    global $db;
    
    // Get all documents with status pending_price_edit, posted, or unposted
    $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.created_by = u.id WHERE d.status IN ('pending_price_edit', 'posted', 'unposted') ORDER BY d.updated_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
        $documents[] = $row;
    }
    
    return $documents;
}

/**
 * Update document by name (for Purchasing department to edit any document)
 * @param string $documentName Document name
 * @param array $items Updated items array
 * @param string $status New status
 * @param string $targetDepartment Target department for posting (optional)
 * @return array Result with success status
 */
function updateDocumentByName($documentName, $items, $status, $targetDepartment = null) {
    global $db;
    
    // First check if document exists
    $checkStmt = $db->prepare("SELECT id FROM documents WHERE name = ?");
    $checkStmt->bind_param('s', $documentName);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $documentExists = ($checkResult->num_rows > 0);
    $checkStmt->close();
    
    if (!$documentExists) {
        return ['success' => false, 'message' => 'Document not found in database'];
    }
    
    // Convert items array to JSON
    $itemsJson = json_encode($items);
    
    // Extract html_content if available
    $htmlContent = '';
    if (is_array($items) && count($items) > 0) {
        $first = $items[0];
        if (is_array($first) && isset($first['htmlContent'])) {
            $htmlContent = $first['htmlContent'];
        }
    }
    
    // If target department is provided, update it along with other fields
    if ($targetDepartment !== null) {
        $stmt = $db->prepare("UPDATE documents SET items = ?, status = ?, html_content = ?, department = ?, updated_at = NOW() WHERE name = ?");
        $stmt->bind_param('sssss', $itemsJson, $status, $htmlContent, $targetDepartment, $documentName);
    } else {
        $stmt = $db->prepare("UPDATE documents SET items = ?, status = ?, html_content = ?, updated_at = NOW() WHERE name = ?");
        $stmt->bind_param('ssss', $itemsJson, $status, $htmlContent, $documentName);
    }
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Document updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to update document: ' . $stmt->error];
    }
}

/**
 * Update document status by name (for Purchasing department)
 * @param string $documentName Document name
 * @param string $newStatus New status
 * @return bool Success status
 */
function updateDocumentStatusByName($documentName, $newStatus) {
    global $db;
    
    $stmt = $db->prepare("UPDATE documents SET status = ?, updated_at = NOW() WHERE name = ?");
    $stmt->bind_param('ss', $newStatus, $documentName);
    
    return $stmt->execute();
}

/**
 * Delete document by name (for Purchasing department)
 * @param string $documentName Document name
 * @return bool Success status
 */
function deleteDocumentByName($documentName) {
    global $db;
    
    $stmt = $db->prepare("DELETE FROM documents WHERE name = ?");
    $stmt->bind_param('s', $documentName);
    
    return $stmt->execute();
}

/**
 * Get documents for a specific department - allows same department users to see each other's files
 * @param string $department Department name
 * @param string $username Current user's username (to include their own files + unposted)
 * @return array Array of documents for the department
 */
function getDocumentsByDepartment($department, $username) {
    global $db;
    
    // Get user ID from username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $userId = 0;
    if ($result->num_rows > 0) {
        $userId = $result->fetch_assoc()['id'];
    }
    
    // Get all documents where:
    // 1. Created by any user in the same department, OR
    // 2. Created by current user (regardless of department), OR
    // 3. Status is 'unposted' (visible to all)
    $stmt = $db->prepare("
        SELECT DISTINCT d.*, u.username, u.department as creator_department
        FROM documents d 
        LEFT JOIN users u ON d.created_by = u.id 
        WHERE u.department = ? 
           OR d.created_by = ?
           OR d.status = 'unposted'
        ORDER BY d.updated_at DESC
    ");
    $stmt->bind_param('si', $department, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    $seenNames = []; // Track document names to avoid duplicates
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['name'], $seenNames)) {
            $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
            $documents[] = $row;
            $seenNames[] = $row['name'];
        }
    }
    
    return $documents;
}

/**
 * Check if user can access document (same department or own document)
 * @param string $documentName Document name
 * @param string $username Current user
 * @param string $userDepartment Current user's department
 * @return bool True if user can access
 */
function canUserAccessDocument($documentName, $username, $userDepartment) {
    global $db;
    
    // Get document info with creator's department
    $stmt = $db->prepare("
        SELECT d.*, u.department as creator_department, u.username as creator_username
        FROM documents d 
        LEFT JOIN users u ON d.created_by = u.id 
        WHERE d.name = ?
    ");
    $stmt->bind_param('s', $documentName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $doc = $result->fetch_assoc();
    
    // User can access if:
    // 1. They are the creator
    // 2. They are in the same department as the creator
    // 3. Document is unposted (visible to all)
    return ($doc['creator_username'] === $username) 
        || ($doc['creator_department'] === $userDepartment && !empty($userDepartment))
        || ($doc['status'] === 'unposted');
}
?>

