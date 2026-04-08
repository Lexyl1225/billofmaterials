/**
 * Document Sync Library - Phase 2
 * Handles synchronization between localStorage and database
 */

const DocumentSync = {
    /**
     * Save document to both localStorage and database
     */
    async saveDocument(documentName, documentType, items, status = 'saved') {
        try {
            // Save to database
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save',
                    documentName: documentName,
                    documentType: documentType,
                    items: items,
                    status: status
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Also save to localStorage as cache
                this.saveToLocalStorage(documentName, documentType, items, status);
                return { success: true, message: 'Document saved successfully' };
            } else {
                // Fallback to localStorage only
                this.saveToLocalStorage(documentName, documentType, items, status);
                return { success: false, message: 'Saved to localStorage only: ' + result.message };
            }
        } catch (error) {
            // Fallback to localStorage on error
            this.saveToLocalStorage(documentName, documentType, items, status);
            return { success: false, message: 'Saved to localStorage only: ' + error.message };
        }
    },
    
    /**
     * Load document from database (with localStorage fallback)
     */
    async loadDocument(documentName) {
        try {
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'load',
                    documentName: documentName
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                return { success: true, document: result.document };
            } else {
                // Fallback to localStorage
                const localDoc = this.loadFromLocalStorage(documentName);
                if (localDoc) {
                    return { success: true, document: localDoc, source: 'localStorage' };
                }
                return { success: false, message: 'Document not found' };
            }
        } catch (error) {
            // Fallback to localStorage
            const localDoc = this.loadFromLocalStorage(documentName);
            if (localDoc) {
                return { success: true, document: localDoc, source: 'localStorage' };
            }
            return { success: false, message: error.message };
        }
    },
    
    /**
     * Delete document from both localStorage and database
     */
    async deleteDocument(documentName, status) {
        try {
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete',
                    documentName: documentName
                })
            });
            
            const result = await response.json();
            
            // Also delete from localStorage
            this.deleteFromLocalStorage(documentName, status);
            
            return result;
        } catch (error) {
            // Delete from localStorage anyway
            this.deleteFromLocalStorage(documentName, status);
            return { success: false, message: error.message };
        }
    },
    
    /**
     * Update document status in both localStorage and database
     */
    async updateStatus(documentName, oldStatus, newStatus) {
        try {
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update_status',
                    documentName: documentName,
                    status: newStatus
                })
            });
            
            const result = await response.json();
            
            // Also update localStorage
            this.updateLocalStorageStatus(documentName, oldStatus, newStatus);
            
            return result;
        } catch (error) {
            // Update localStorage anyway
            this.updateLocalStorageStatus(documentName, oldStatus, newStatus);
            return { success: false, message: error.message };
        }
    },
    
    /**
     * Sync all documents from database to localStorage
     */
    async syncFromDatabase() {
        try {
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'sync'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update localStorage with database documents
                const docs = result.documents;
                
                localStorage.setItem('savedBOMBOQDocuments', JSON.stringify(docs.saved));
                localStorage.setItem('postedDocuments', JSON.stringify(docs.posted));
                localStorage.setItem('pendingPriceEditDocuments', JSON.stringify(docs.pending_price_edit));
                localStorage.setItem('unpostedDocuments', JSON.stringify(docs.unposted));
                
                return { success: true, message: 'Documents synced successfully' };
            }
            
            return result;
        } catch (error) {
            return { success: false, message: error.message };
        }
    },
    
    /**
     * List documents from database
     */
    async listDocuments(status = null) {
        try {
            const response = await fetch('document_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'list',
                    status: status
                })
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            return { success: false, message: error.message };
        }
    },
    
    // ==================== localStorage Helper Methods ====================
    
    saveToLocalStorage(documentName, documentType, items, status) {
        const storageKeys = {
            'saved': 'savedBOMBOQDocuments',
            'posted': 'postedDocuments',
            'pending_price_edit': 'pendingPriceEditDocuments',
            'unposted': 'unpostedDocuments'
        };
        
        const key = storageKeys[status] || 'savedBOMBOQDocuments';
        const documents = JSON.parse(localStorage.getItem(key) || '{}');
        
        documents[documentName] = {
            type: documentType,
            [documentType === 'bom' ? 'bomItems' : 'boqItems']: items
        };
        
        localStorage.setItem(key, JSON.stringify(documents));
    },
    
    loadFromLocalStorage(documentName) {
        const storageKeys = [
            'savedBOMBOQDocuments',
            'postedDocuments',
            'pendingPriceEditDocuments',
            'unpostedDocuments'
        ];
        
        for (let key of storageKeys) {
            const documents = JSON.parse(localStorage.getItem(key) || '{}');
            if (documents[documentName]) {
                const status = key.replace('Documents', '').replace('savedBOMBOQ', 'saved')
                    .replace('posted', 'posted')
                    .replace('pendingPriceEdit', 'pending_price_edit')
                    .replace('unposted', 'unposted');
                    
                return {
                    name: documentName,
                    type: documents[documentName].type,
                    items: documents[documentName].type === 'bom' ? 
                           documents[documentName].bomItems : 
                           documents[documentName].boqItems,
                    status: status
                };
            }
        }
        return null;
    },
    
    deleteFromLocalStorage(documentName, status) {
        const storageKeys = {
            'saved': 'savedBOMBOQDocuments',
            'posted': 'postedDocuments',
            'pending_price_edit': 'pendingPriceEditDocuments',
            'unposted': 'unpostedDocuments'
        };
        
        const key = storageKeys[status];
        if (key) {
            const documents = JSON.parse(localStorage.getItem(key) || '{}');
            delete documents[documentName];
            localStorage.setItem(key, JSON.stringify(documents));
        }
    },
    
    updateLocalStorageStatus(documentName, oldStatus, newStatus) {
        const storageKeys = {
            'saved': 'savedBOMBOQDocuments',
            'posted': 'postedDocuments',
            'pending_price_edit': 'pendingPriceEditDocuments',
            'unposted': 'unpostedDocuments'
        };
        
        const oldKey = storageKeys[oldStatus];
        const newKey = storageKeys[newStatus];
        
        if (oldKey && newKey) {
            const oldDocs = JSON.parse(localStorage.getItem(oldKey) || '{}');
            const newDocs = JSON.parse(localStorage.getItem(newKey) || '{}');
            
            if (oldDocs[documentName]) {
                newDocs[documentName] = oldDocs[documentName];
                delete oldDocs[documentName];
                
                localStorage.setItem(oldKey, JSON.stringify(oldDocs));
                localStorage.setItem(newKey, JSON.stringify(newDocs));
            }
        }
    }
};

// Auto-sync on page load (optional)
window.addEventListener('load', function() {
    // Uncomment to enable auto-sync on every page load
    // DocumentSync.syncFromDatabase();
});
