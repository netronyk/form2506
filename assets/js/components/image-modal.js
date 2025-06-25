// assets/js/components/image-modal.js
// מערכת Modal מתקדמת לצפייה בתמונות במסך מלא

class ImageModal {
    constructor(options = {}) {
        this.options = {
            modalId: 'imageModal',
            enableKeyboard: true,
            enableTouch: true,
            enableZoom: true,
            enableFullscreen: true,
            autoFocus: true,
            closeOnClickOutside: true,
            showNavigationArrows: true,
            showImageCounter: true,
            showCloseButton: true,
            animationDuration: 300,
            zoomStep: 0.25,
            maxZoom: 3,
            minZoom: 0.5,
            ...options
        };
        
        this.images = [];
        this.currentIndex = 0;
        this.isOpen = false;
        this.isZoomed = false;
        this.zoomLevel = 1;
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.translateX = 0;
        this.translateY = 0;
        
        this.init();
    }
    
    init() {
        this.createModal();
        this.setupEventListeners();
    }
    
    createModal() {
        // בדיקה אם Modal כבר קיים
        let modal = document.getElementById(this.options.modalId);
        if (modal) {
            this.modal = modal;
            return;
        }
        
        // יצירת המבנה
        modal = document.createElement('div');
        modal.id = this.options.modalId;
        modal.className = 'image-modal';
        modal.innerHTML = this.getModalHTML();
        
        document.body.appendChild(modal);
        this.modal = modal;
        
        // קבלת אלמנטים
        this.modalImage = modal.querySelector('.modal-image');
        this.modalCaption = modal.querySelector('.modal-caption');
        this.modalCounter = modal.querySelector('.modal-indicator');
        this.prevBtn = modal.querySelector('.modal-nav.prev');
        this.nextBtn = modal.querySelector('.modal-nav.next');
        this.closeBtn = modal.querySelector('.modal-close');
    }
    
    getModalHTML() {
        return `
            ${this.options.showCloseButton ? '<button class="modal-close" aria-label="סגור">&times;</button>' : ''}
            
            <div class="modal-content">
                <img class="modal-image" src="" alt="" />
                
                ${this.options.showNavigationArrows ? `
                    <button class="modal-nav prev" aria-label="תמונה קודמת">‹</button>
                    <button class="modal-nav next" aria-label="תמונה הבאה">›</button>
                ` : ''}
                
                ${this.options.showImageCounter ? '<div class="modal-indicator"></div>' : ''}
                
                <div class="modal-caption">
                    <h3 class="modal-title"></h3>
                    <p class="modal-description"></p>
                </div>
                
                <div class="modal-controls">
                    ${this.options.enableZoom ? `
                        <button class="modal-control-btn zoom-in" title="הגדל">🔍+</button>
                        <button class="modal-control-btn zoom-out" title="הקטן">🔍-</button>
                        <button class="modal-control-btn zoom-reset" title="איפוס זום">⌂</button>
                    ` : ''}
                    ${this.options.enableFullscreen ? '<button class="modal-control-btn fullscreen" title="מסך מלא">⛶</button>' : ''}
                </div>
                
                <div class="modal-loading" style="display: none;">
                    <div class="spinner"></div>
                    <p>טוען תמונה...</p>
                </div>
            </div>
        `;
    }
    
    setupEventListeners() {
        // כפתור סגירה
        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', () => this.close());
        }
        
        // ניווט
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.previousImage();
            });
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.nextImage();
            });
        }
        
        // לחיצה מחוץ למודל
        if (this.options.closeOnClickOutside) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            });
        }
        
        // מקלדת
        if (this.options.enableKeyboard) {
            document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        }
        
        // זום וגרירה
        if (this.options.enableZoom && this.modalImage) {
            this.setupZoomAndDrag();
        }
        
        // כפתורי בקרה
        this.setupControlButtons();
        
        // מגע (נייד)
        if (this.options.enableTouch) {
            this.setupTouchEvents();
        }
    }
    
    setupZoomAndDrag() {
        // גלגלת עכבר לזום
        this.modalImage.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -1 : 1;
            this.zoom(delta * this.options.zoomStep);
        });
        
        // דאבל קליק לזום
        this.modalImage.addEventListener('dblclick', () => {
            if (this.zoomLevel === 1) {
                this.zoom(1); // זום פנימה
            } else {
                this.resetZoom();
            }
        });
        
        // גרירה
        this.modalImage.addEventListener('mousedown', (e) => this.startDrag(e));
        document.addEventListener('mousemove', (e) => this.drag(e));
        document.addEventListener('mouseup', () => this.endDrag());
    }
    
    setupControlButtons() {
        const controls = this.modal.querySelector('.modal-controls');
        if (!controls) return;
        
        // זום
        const zoomInBtn = controls.querySelector('.zoom-in');
        const zoomOutBtn = controls.querySelector('.zoom-out');
        const zoomResetBtn = controls.querySelector('.zoom-reset');
        
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', () => this.zoom(this.options.zoomStep));
        }
        
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', () => this.zoom(-this.options.zoomStep));
        }
        
        if (zoomResetBtn) {
            zoomResetBtn.addEventListener('click', () => this.resetZoom());
        }
        
        // מסך מלא
        const fullscreenBtn = controls.querySelector('.fullscreen');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
        }
    }
    
    setupTouchEvents() {
        let startDistance = 0;
        let startScale = 1;
        let touchStartX = 0;
        let touchStartY = 0;
        
        this.modalImage.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                // Pinch zoom
                e.preventDefault();
                startDistance = this.getTouchDistance(e.touches);
                startScale = this.zoomLevel;
            } else if (e.touches.length === 1) {
                // Single touch - swipe
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }
        });
        
        this.modalImage.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2) {
                e.preventDefault();
                const currentDistance = this.getTouchDistance(e.touches);
                const scale = (currentDistance / startDistance) * startScale;
                this.setZoom(scale);
            }
        });
        
        this.modalImage.addEventListener('touchend', (e) => {
            if (e.changedTouches.length === 1 && !this.isZoomed) {
                const touchEndX = e.changedTouches[0].clientX;
                const touchEndY = e.changedTouches[0].clientY;
                const diffX = touchStartX - touchEndX;
                const diffY = touchStartY - touchEndY;
                
                // בדיקה שזה swipe אופקי
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                    if (diffX > 0) {
                        this.nextImage();
                    } else {
                        this.previousImage();
                    }
                }
            }
        });
    }
    
    getTouchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }
    
    open(images, startIndex = 0) {
        this.images = Array.isArray(images) ? images : [images];
        this.currentIndex = Math.max(0, Math.min(startIndex, this.images.length - 1));
        
        this.modal.classList.add('show');
        this.isOpen = true;
        
        // מניעת גלילה ברקע
        document.body.style.overflow = 'hidden';
        
        this.loadImage(this.currentIndex);
        this.updateNavigation();
        
        if (this.options.autoFocus) {
            this.modal.focus();
        }
        
        this.trigger('opened', { index: this.currentIndex, image: this.getCurrentImage() });
    }
    
    close() {
        if (!this.isOpen) return;
        
        this.modal.classList.add('closing');
        this.modal.classList.remove('show');
        
        setTimeout(() => {
            this.modal.classList.remove('closing');
            this.isOpen = false;
            
            // החזרת גלילה
            document.body.style.overflow = '';
            
            // איפוס זום
            this.resetZoom();
            
            this.trigger('closed');
        }, this.options.animationDuration);
    }
    
    loadImage(index) {
        if (index < 0 || index >= this.images.length) return;
        
        const image = this.images[index];
        this.showLoading();
        
        // יצירת תמונה חדשה לטעינה
        const img = new Image();
        
        img.onload = () => {
            this.modalImage.src = img.src;
            this.modalImage.alt = image.alt || '';
            this.updateCaption(image);
            this.updateCounter();
            this.hideLoading();
            this.resetZoom();
            
            this.trigger('imageLoaded', { index, image });
        };
        
        img.onerror = () => {
            this.hideLoading();
            this.showError('שגיאה בטעינת התמונה');
        };
        
        img.src = image.src;
    }
    
    updateCaption(image) {
        if (!this.modalCaption) return;
        
        const title = this.modalCaption.querySelector('.modal-title');
        const description = this.modalCaption.querySelector('.modal-description');
        
        if (title) {
            title.textContent = image.title || image.alt || '';
        }
        
        if (description) {
            description.textContent = image.description || '';
        }
        
        // הסתרת caption אם אין תוכן
        const hasContent = (image.title || image.alt || image.description);
        this.modalCaption.style.display = hasContent ? 'block' : 'none';
    }
    
    updateCounter() {
        if (!this.modalCounter || this.images.length <= 1) return;
        
        this.modalCounter.textContent = `${this.currentIndex + 1} / ${this.images.length}`;
    }
    
    updateNavigation() {
        if (this.prevBtn) {
            this.prevBtn.disabled = this.images.length <= 1 || this.currentIndex === 0;
        }
        
        if (this.nextBtn) {
            this.nextBtn.disabled = this.images.length <= 1 || this.currentIndex === this.images.length - 1;
        }
        
        // הסתרת ניווט אם יש רק תמונה אחת
        if (this.images.length <= 1) {
            if (this.prevBtn) this.prevBtn.style.display = 'none';
            if (this.nextBtn) this.nextBtn.style.display = 'none';
        } else {
            if (this.prevBtn) this.prevBtn.style.display = 'flex';
            if (this.nextBtn) this.nextBtn.style.display = 'flex';
        }
    }
    
    nextImage() {
        if (this.currentIndex < this.images.length - 1) {
            this.currentIndex++;
            this.loadImage(this.currentIndex);
            this.updateNavigation();
        }
    }
    
    previousImage() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.loadImage(this.currentIndex);
            this.updateNavigation();
        }
    }
    
    goToImage(index) {
        if (index >= 0 && index < this.images.length && index !== this.currentIndex) {
            this.currentIndex = index;
            this.loadImage(this.currentIndex);
            this.updateNavigation();
        }
    }
    
    // פונקציות זום
    zoom(delta) {
        const newZoom = Math.max(this.options.minZoom, Math.min(this.options.maxZoom, this.zoomLevel + delta));
        this.setZoom(newZoom);
    }
    
    setZoom(level) {
        this.zoomLevel = Math.max(this.options.minZoom, Math.min(this.options.maxZoom, level));
        this.applyZoom();
    }
    
    applyZoom() {
        if (!this.modalImage) return;
        
        this.modalImage.style.transform = `scale(${this.zoomLevel}) translate(${this.translateX}px, ${this.translateY}px)`;
        this.isZoomed = this.zoomLevel !== 1;
        
        // שינוי cursor
        this.modalImage.style.cursor = this.isZoomed ? 'grab' : 'pointer';
        
        // איפוס תרגום אם חזרנו לזום רגיל
        if (!this.isZoomed) {
            this.translateX = 0;
            this.translateY = 0;
        }
        
        this.trigger('zoomChanged', { level: this.zoomLevel });
    }
    
    resetZoom() {
        this.zoomLevel = 1;
        this.translateX = 0;
        this.translateY = 0;
        this.applyZoom();
    }
    
    // פונקציות גרירה
    startDrag(e) {
        if (!this.isZoomed) return;
        
        this.isDragging = true;
        this.startX = e.clientX - this.translateX;
        this.startY = e.clientY - this.translateY;
        this.modalImage.style.cursor = 'grabbing';
        e.preventDefault();
    }
    
    drag(e) {
        if (!this.isDragging || !this.isZoomed) return;
        
        this.translateX = e.clientX - this.startX;
        this.translateY = e.clientY - this.startY;
        this.applyZoom();
    }
    
    endDrag() {
        if (this.isDragging) {
            this.isDragging = false;
            this.modalImage.style.cursor = this.isZoomed ? 'grab' : 'pointer';
        }
    }
    
    handleKeyboard(e) {
        if (!this.isOpen) return;
        
        switch (e.key) {
            case 'Escape':
                this.close();
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.nextImage();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.previousImage();
                break;
            case 'Home':
                e.preventDefault();
                this.goToImage(0);
                break;
            case 'End':
                e.preventDefault();
                this.goToImage(this.images.length - 1);
                break;
            case '+':
            case '=':
                e.preventDefault();
                this.zoom(this.options.zoomStep);
                break;
            case '-':
                e.preventDefault();
                this.zoom(-this.options.zoomStep);
                break;
            case '0':
                e.preventDefault();
                this.resetZoom();
                break;
            case 'f':
            case 'F11':
                e.preventDefault();
                this.toggleFullscreen();
                break;
        }
    }
    
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            this.modal.requestFullscreen().then(() => {
                this.modal.classList.add('fullscreen');
                this.trigger('fullscreenEntered');
            });
        } else {
            document.exitFullscreen().then(() => {
                this.modal.classList.remove('fullscreen');
                this.trigger('fullscreenExited');
            });
        }
    }
    
    showLoading() {
        const loading = this.modal.querySelector('.modal-loading');
        if (loading) {
            loading.style.display = 'block';
        }
    }
    
    hideLoading() {
        const loading = this.modal.querySelector('.modal-loading');
        if (loading) {
            loading.style.display = 'none';
        }
    }
    
    showError(message) {
        this.modalImage.alt = message;
        this.modalImage.src = 'data:image/svg+xml;base64,' + btoa(`
            <svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
                <rect width="400" height="300" fill="#f8f9fa"/>
                <text x="200" y="150" text-anchor="middle" font-family="Arial" font-size="16" fill="#666">
                    ${message}
                </text>
            </svg>
        `);
    }
    
    // פונקציות API
    getCurrentImage() {
        return this.images[this.currentIndex];
    }
    
    getCurrentIndex() {
        return this.currentIndex;
    }
    
    getImageCount() {
        return this.images.length;
    }
    
    isModalOpen() {
        return this.isOpen;
    }
    
    trigger(eventName, data = {}) {
        const event = new CustomEvent(`modal:${eventName}`, {
            detail: { ...data, modal: this }
        });
        document.dispatchEvent(event);
    }
    
    destroy() {
        if (this.modal && this.modal.parentNode) {
            this.modal.parentNode.removeChild(this.modal);
        }
        
        document.body.style.overflow = '';
        this.trigger('destroyed');
    }
}

// פונקציות עזר גלובליות
function openImageModal(imageSrc, imageAlt = '') {
    if (!window.imageModal) {
        window.imageModal = new ImageModal();
    }
    
    const imageData = {
        src: imageSrc,
        alt: imageAlt,
        title: imageAlt
    };
    
    window.imageModal.open([imageData], 0);
}

function closeImageModal() {
    if (window.imageModal) {
        window.imageModal.close();
    }
}

// אתחול אוטומטי
document.addEventListener('DOMContentLoaded', function() {
    // יצירת מופע Modal גלובלי
    window.imageModal = new ImageModal();
    
    // הוספת event listeners לכל התמונות שצריכות לפתוח modal
    document.querySelectorAll('img[data-modal="true"], .gallery-item img, .vehicle-image-thumbnail').forEach(img => {
        img.addEventListener('click', function(e) {
            e.preventDefault();
            
            // איסוף כל התמונות הקשורות
            const container = this.closest('.vehicle-images-gallery, .image-gallery');
            let images = [];
            let startIndex = 0;
            
            if (container) {
                const allImages = container.querySelectorAll('img[src]');
                images = Array.from(allImages).map(img => ({
                    src: img.src,
                    alt: img.alt,
                    title: img.title || img.alt
                }));
                
                startIndex = Array.from(allImages).indexOf(this);
            } else {
                images = [{
                    src: this.src,
                    alt: this.alt,
                    title: this.title || this.alt
                }];
            }
            
            window.imageModal.open(images, Math.max(0, startIndex));
        });
    });
});

// האזנה לאירועי Modal
document.addEventListener('modal:opened', function(e) {
    console.log('Modal opened:', e.detail);
});

document.addEventListener('modal:closed', function() {
    console.log('Modal closed');
});

// ייצוא עבור שימוש במודולים
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImageModal;
}