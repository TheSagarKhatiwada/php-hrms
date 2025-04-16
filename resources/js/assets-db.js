// IndexedDB setup for assets management
const dbName = 'HRMSAssetsDB';
const dbVersion = 1;

// Open or create the database
async function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, dbVersion);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            // Create object store for assets
            if (!db.objectStoreNames.contains('assets')) {
                const assetsStore = db.createObjectStore('assets', { keyPath: 'AssetID' });
                assetsStore.createIndex('categoryId', 'CategoryID', { unique: false });
            }
            
            // Create object store for categories
            if (!db.objectStoreNames.contains('categories')) {
                const categoriesStore = db.createObjectStore('categories', { keyPath: 'CategoryID' });
            }
            
            // Create object store for offline changes
            if (!db.objectStoreNames.contains('offlineChanges')) {
                const changesStore = db.createObjectStore('offlineChanges', { keyPath: 'id', autoIncrement: true });
                changesStore.createIndex('type', 'type', { unique: false });
                changesStore.createIndex('status', 'status', { unique: false });
            }
        };
    });
}

// Save or update an asset
async function saveAsset(asset) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['assets'], 'readwrite');
        const store = transaction.objectStore('assets');
        const request = store.put(asset);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Get all assets
async function getAllAssets() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['assets'], 'readonly');
        const store = transaction.objectStore('assets');
        const request = store.getAll();
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Get asset by ID
async function getAssetById(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['assets'], 'readonly');
        const store = transaction.objectStore('assets');
        const request = store.get(id);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Delete an asset
async function deleteAsset(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['assets'], 'readwrite');
        const store = transaction.objectStore('assets');
        const request = store.delete(id);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Save or update a category
async function saveCategory(category) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['categories'], 'readwrite');
        const store = transaction.objectStore('categories');
        const request = store.put(category);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Get all categories
async function getAllCategories() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['categories'], 'readonly');
        const store = transaction.objectStore('categories');
        const request = store.getAll();
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Queue an offline change
async function queueOfflineChange(change) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['offlineChanges'], 'readwrite');
        const store = transaction.objectStore('offlineChanges');
        const request = store.add({
            ...change,
            status: 'pending',
            timestamp: new Date().toISOString()
        });
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Get all pending changes
async function getPendingChanges() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['offlineChanges'], 'readonly');
        const store = transaction.objectStore('offlineChanges');
        const index = store.index('status');
        const request = index.getAll('pending');
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Mark a change as synced
async function markChangeAsSynced(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['offlineChanges'], 'readwrite');
        const store = transaction.objectStore('offlineChanges');
        const request = store.get(id);
        
        request.onsuccess = () => {
            const change = request.result;
            if (change) {
                change.status = 'synced';
                const updateRequest = store.put(change);
                updateRequest.onsuccess = () => resolve();
                updateRequest.onerror = () => reject(updateRequest.error);
            } else {
                resolve();
            }
        };
        request.onerror = () => reject(request.error);
    });
}

// Initialize the database with server data
async function initializeDB(assets, categories) {
    const db = await openDB();
    
    // Clear existing data
    const clearAssets = new Promise((resolve, reject) => {
        const transaction = db.transaction(['assets'], 'readwrite');
        const store = transaction.objectStore('assets');
        const request = store.clear();
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
    
    const clearCategories = new Promise((resolve, reject) => {
        const transaction = db.transaction(['categories'], 'readwrite');
        const store = transaction.objectStore('categories');
        const request = store.clear();
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
    
    await Promise.all([clearAssets, clearCategories]);
    
    // Add new data
    const addAssets = Promise.all(assets.map(asset => saveAsset(asset)));
    const addCategories = Promise.all(categories.map(category => saveCategory(category)));
    
    await Promise.all([addAssets, addCategories]);
}

// Check if online
function isOnline() {
    return navigator.onLine;
}

// Export the functions
window.assetsDB = {
    openDB,
    saveAsset,
    getAllAssets,
    getAssetById,
    deleteAsset,
    saveCategory,
    getAllCategories,
    queueOfflineChange,
    getPendingChanges,
    markChangeAsSynced,
    initializeDB,
    isOnline
}; 