// assets/js/components/image-uploader.js
// מערכת העלאת תמונות מתקדמת עם Drag & Drop

class ImageUploader {
    constructor(options = {}) {
        this.options = {
            maxFiles: 3,
            maxFileSize: 5 * 1024 * 1024, // 5MB
            acceptedTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
            apiEndpoint: '../api/vehicle-images/',
            vehicleId: null,
            mode: 'add', // 'add' או 'edit'
            ...options
        };
        
        this.files = [];
        this.uploadedImages = [];
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.initializeDragAndDrop();
        this.loadExistingImages();
    }
    
    setupEventListeners() {
        // לחיצה על אזור העלאה
        const uploadZone = document.querySelector('.upload-zone');
        if (uploadZone) {
            uploadZone.addEventListener('click', () => this.triggerFileInput());
        }
        
        // שינוי בקובץ
        const fileInput = document.querySelector('.upload-input');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => this.handleFileSelection(e));
        }
        
        // אירוע הגשת טופס
        const form = document.getElementById('vehicleForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }
    
    initializeDragAndDrop() {
        const uploadZone = document.querySelector('.upload-zone');
        if (!uploadZone) return;
        
        // מניעת התנהגות ברירת מחדל
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });
        
        // הדגשת אזור בזמן גרירה
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => this.highlight(uploadZone), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => this.unhighlight(uploadZone), false);
        });
        
        // טיפול בנפילת הקובץ
        uploadZone.addEventListener('drop', (e) => this.handleDrop(e), false);
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    highlight(element) {
        element.classList.add('dragover');
    }
    
    unhighlight(element) {
        element.classList.remove('dragover');
    }
    
    triggerFileInput() {
        if (this.canAddMore()) {
            const fileInput = document.querySelector('.upload-input');
            if (fileInput) {
                fileInput.click();
            }
        } else {
            this.showMessage(`ניתן להעלות עד ${this.options.maxFiles} תמונות בלבד`, 'warning');
        }
    }
    
    handleFileSelection(event) {
        const files = Array.from(event.target.files);
        this.processFiles(files);
        // איפוס הקלט
        event.target.value = '';
    }
    
    handleDrop(event) {
        const files = Array.from(event.dataTransfer.files);
        this.processFiles(files);
    }
    
    processFiles(files) {
        files.forEach(file => {
            if (!this.canAddMore()) {
                this.showMessage(`ניתן להעלות עד ${this.options.maxFiles} תמונות בלבד`, 'warning');
                return;
            }
            
            if (this.validateFile(file)) {
                if (this.options.mode === 'edit' && this.options.vehicleId) {
                    this.uploadToServer(file);
                } else {
                    this.addFilePreview(file);
                }
            }
        });
    }
    
    validateFile(file) {
        // בדיקת סוג קובץ
        if (!this.options.acceptedTypes.includes(file.type)) {
            this.showMessage('ניתן להעלות רק קבצי תמונה (JPG, PNG, GIF)', 'error');
            return false;
        }
        
        // בדיקת גודל קובץ
        if (file.size > this.options.maxFileSize) {
            this.showMessage('גודל התמונה חייב להיות עד 5MB', 'error');
            return false;
        }
        
        return true;
    }
    
    canAddMore() {
        const currentCount = this.files.length + this.uploadedImages.length;
        return currentCount < this.options.maxFiles;
    }
    
    addFilePreview(file) {
        const fileId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        const fileData = {
            id: fileId,
            file: file,
            isPrimary: this.files.length === 0 && this.uploadedImages.length === 0,
            isNew: true
        };
        
        this.files.push(fileData);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            this.renderImageItem(fileData, e.target.result);
            this.updateCounter();
        };
        reader.readAsDataURL(file);
    }
    
    async uploadToServer(file) {
        if (!this.options.vehicleId) {
            this.showMessage('שגיאה: מזהה רכב חסר', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('image', file);
        formData.append('vehicle_id', this.options.vehicleId);
        
        // הוספת אינדיקטור טעינה
        const loadingElement = this.createLoadingElement();
        const gallery = document.querySelector('.image-gallery');
        gallery.appendChild(loadingElement);
        
        try {
            const response = await fetch(this.options.apiEndpoint + 'upload.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.uploadedImages.push({
                    id: result.image_id,
                    path: result.image_path,
                    isPrimary: result.is_primary
                });
                
                this.renderUploadedImage(result);
                this.showMessage('התמונה הועלתה בהצלחה', 'success');
            } else {
                this.showMessage(result.message || 'שגיאה בהעלאת התמונה', 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showMessage('שגיאה בהעלאת התמונה', 'error');
        } finally {
            loadingElement.remove();
            this.updateCounter();
        }
    }
    
    renderImageItem(fileData, imageSrc) {
        const gallery = document.querySelector('.image-gallery');
        const imageItem = document.createElement('div');
        imageItem.className = 'image-item';
        imageItem.dataset.imageId = fileData.id;
        imageItem.dataset.isNew = 'true';
        
        imageItem.innerHTML = `
            <img src="${imageSrc}" alt="תמונת רכב">
            <div class="image-overlay">
                <button type="button" class="image-btn primary-btn" onclick="imageUploader.setPrimaryPreview('${fileData.id}')" title="הגדר כתמונה ראשית">
                    ${fileData.isPrimary ? '⭐' : '☆'}
                </button>
                <button type="button" class="image-btn delete-btn" onclick="imageUploader.removePreview('${fileData.id}')" title="מחק תמונה">
                    🗑️
                </button>
            </div>
            ${fileData.isPrimary ? '<div class="primary-badge">תמונה ראשית</div>' : ''}
        `;
        
        gallery.appendChild(imageItem);
    }
    
    renderUploadedImage(imageData) {
        const gallery = document.querySelector('.image-gallery');
        const imageItem = document.createElement('div');
        imageItem.className = 'image-item';
        imageItem.dataset.imageId = imageData.image_id;
        
        imageItem.innerHTML = `
            <img src="../uploads/${imageData.image_path}" alt="תמונת רכב">
            <div class="image-overlay">
                <button type="button" class="image-btn primary-btn" onclick="imageUploader.setPrimaryImage(${imageData.image_id})" title="הגדר כתמונה ראשית">
                    ${imageData.is_primary ? '⭐' : '☆'}
                </button>
                <button type="button" class="image-btn delete-btn" onclick="imageUploader.deleteImage(${imageData.image_id})" title="מחק תמונה">
                    🗑️
                </button>
            </div>
            ${imageData.is_primary ? '<div class="primary-badge">תמונה ראשית</div>' : ''}
        `;
        
        gallery.appendChild(imageItem);
    }
    
    createLoadingElement() {
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'image-item loading';
        loadingDiv.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666; flex-direction: column;">
                <div style="margin-bottom: 0.5rem;">⏳</div>
                <div>מעלה...</div>
            </div>
        `;
        return loadingDiv;
    }
    
    setPrimaryPreview(imageId) {
        // איפוס כל התמונות
        this.files.forEach(file => file.isPrimary = false);
        this.uploadedImages.forEach(img => img.isPrimary = false);
        
        // הגדרת התמונה החדשה כראשית
        const file = this.files.find(f => f.id === imageId);
        if (file) {
            file.isPrimary = true;
        }
        
        this.updatePrimaryDisplay(imageId);
    }
    
    async setPrimaryImage(imageId) {
        try {
            const response = await fetch(this.options.apiEndpoint + 'set-primary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `image_id=${imageId}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                // עדכון המערך המקומי
                this.uploadedImages.forEach(img => img.isPrimary = false);
                const targetImage = this.uploadedImages.find(img => img.id == imageId);
                if (targetImage) {
                    targetImage.isPrimary = true;
                }
                
                this.updatePrimaryDisplay(imageId);
                this.showMessage('התמונה הראשית עודכנה', 'success');
            } else {
                this.showMessage(result.message || 'שגיאה בעדכון התמונה הראשית', 'error');
            }
        } catch (error) {
            console.error('Set primary error:', error);
            this.showMessage('שגיאה בעדכון התמונה הראשית', 'error');
        }
    }
    
    updatePrimaryDisplay(primaryImageId) {
        // איפוס כל התגיות והכפתורים
        document.querySelectorAll('.primary-badge').forEach(badge => badge.remove());
        document.querySelectorAll('.primary-btn').forEach(btn => btn.textContent = '☆');
        
        // הגדרת התמונה החדשה כראשית
        const imageItem = document.querySelector(`[data-image-id="${primaryImageId}"]`);
        if (imageItem) {
            const primaryBtn = imageItem.querySelector('.primary-btn');
            primaryBtn.textContent = '⭐';
            
            const primaryBadge = document.createElement('div');
            primaryBadge.className = 'primary-badge';
            primaryBadge.textContent = 'תמונה ראשית';
            imageItem.appendChild(primaryBadge);
        }
    }
    
    removePreview(imageId) {
        if (!confirm('האם אתה בטוח שברצונך למחוק תמונה זו?')) {
            return;
        }
        
        const imageItem = document.querySelector(`[data-image-id="${imageId}"]`);
        if (imageItem) {
            imageItem.remove();
        }
        
        // הסרה מהמערך
        const fileIndex = this.files.findIndex(f => f.id === imageId);
        if (fileIndex > -1) {
            const wasPrimary = this.files[fileIndex].isPrimary;
            this.files.splice(fileIndex, 1);
            
            // אם זו הייתה התמונה הראשית, הגדר את הראשונה כראשית
            if (wasPrimary && this.files.length > 0) {
                this.files[0].isPrimary = true;
                this.setPrimaryPreview(this.files[0].id);
            }
        }
        
        this.updateCounter();
    }
    
    async deleteImage(imageId) {
        if (!confirm('האם אתה בטוח שברצונך למחוק תמונה זו?')) {
            return;
        }
        
        const imageItem = document.querySelector(`[data-image-id="${imageId}"]`);
        if (imageItem) {
            imageItem.classList.add('loading');
        }
        
        try {
            const response = await fetch(this.options.apiEndpoint + 'delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `image_id=${imageId}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (imageItem) {
                    imageItem.remove();
                }
                
                // הסרה מהמערך
                const imgIndex = this.uploadedImages.findIndex(img => img.id == imageId);
                if (imgIndex > -1) {
                    this.uploadedImages.splice(imgIndex, 1);
                }
                
                this.updateCounter();
                this.showMessage('התמונה נמחקה בהצלחה', 'success');
            } else {
                this.showMessage(result.message || 'שגיאה במחיקת התמונה', 'error');
                if (imageItem) {
                    imageItem.classList.remove('loading');
                }
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.showMessage('שגיאה במחיקת התמונה', 'error');
            if (imageItem) {
                imageItem.classList.remove('loading');
            }
        }
    }
    
    updateCounter() {
        const count = document.querySelectorAll('.image-item').length;
        const counter = document.getElementById('imageCount');
        if (counter) {
            counter.textContent = count;
        }
    }
    
    loadExistingImages() {
        // טעינת תמונות קיימות (במצב עריכה)
        const existingImages = document.querySelectorAll('.image-item[data-image-id]');
        existingImages.forEach(item => {
            const imageId = item.dataset.imageId;
            const isPrimary = item.querySelector('.primary-badge') !== null;
            
            this.uploadedImages.push({
                id: imageId,
                isPrimary: isPrimary
            });
        });
        
        this.updateCounter();
    }
    
    handleFormSubmit(event) {
        // במצב הוספה - בדיקת תמונות
        if (this.options.mode === 'add') {
            const totalImages = this.files.length + this.uploadedImages.length;
            
            if (totalImages === 0) {
                event.preventDefault();
                this.showMessage('יש להעלות לפחות תמונה אחת של הרכב', 'error');
                document.querySelector('.upload-zone').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            // אם יש תמונות חדשות, הכן אותן לשליחה
            if (this.files.length > 0) {
                this.prepareFilesForSubmission(event);
                return false; // מונע שליחה רגילה
            }
        }
        
        return true;
    }
    
    async prepareFilesForSubmission(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // הסרת שדות תמונות קיימים
        formData.delete('images[]');
        
        // הוספת תמונות חדשות
        this.files.forEach(fileData => {
            formData.append('images[]', fileData.file);
        });
        
        try {
            this.showMessage('שומר רכב עם תמונות...', 'info');
            
            const response = await fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                window.location.href = 'vehicles.php';
            } else {
                throw new Error('שגיאה בשמירת הרכב');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showMessage('שגיאה בשמירת הרכב', 'error');
        }
    }
    
    showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `upload-message ${type}`;
        messageDiv.textContent = message;
        
        // הוספה לתחילת האזור העלאה
        const uploadArea = document.querySelector('.image-upload-container');
        if (uploadArea) {
            uploadArea.insertBefore(messageDiv, uploadArea.firstChild);
        }
        
        // הסרה אוטומטית
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }
    
    // פונקציות ציבוריות עבור גישה חיצונית
    getUploadedImages() {
        return this.uploadedImages;
    }
    
    getPreviewFiles() {
        return this.files;
    }
    
    getTotalImageCount() {
        return this.files.length + this.uploadedImages.length;
    }
    
    reset() {
        this.files = [];
        this.uploadedImages = [];
        const gallery = document.querySelector('.image-gallery');
        if (gallery) {
            gallery.innerHTML = '';
        }
        this.updateCounter();
    }
}

// יצירת מופע גלובלי
let imageUploader = null;

// אתחול אוטומטי כשהדף נטען
document.addEventListener('DOMContentLoaded', function() {
    // זיהוי מצב (הוספה/עריכה) ורכב ID
    const mode = document.querySelector('[data-vehicle-mode]')?.dataset.vehicleMode || 'add';
    const vehicleId = document.querySelector('[data-vehicle-id]')?.dataset.vehicleId || null;
    
    // יצירת מופע המעלה
    imageUploader = new ImageUploader({
        mode: mode,
        vehicleId: vehicleId
    });
});

// ייצוא עבור שימוש במודולים
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImageUploader;
}