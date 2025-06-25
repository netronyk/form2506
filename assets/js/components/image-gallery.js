// assets/js/components/image-gallery.js
// מערכת גלריית תמונות מתקדמת לצפייה ברכבים

class ImageGallery {
    constructor(options = {}) {
        this.options = {
            container: '.vehicle-images-gallery',
            mainImageSelector: '.main-image',
            thumbnailSelector: '.thumbnail-image',
            autoPlay: false,
            autoPlayInterval: 5000,
            enableKeyboard: true,
            enableTouch: true,
            enableZoom: true,
            ...options
        };
        
        this.images = [];
        this.currentImageIndex = 0;
        this.isAutoPlaying = false;
        this.autoPlayTimer = null;
        
        this.init();
    }
    
    init() {
        this.loadImages();
        this.setupEventListeners();
        this.initializeTouch();
        
        if (this.options.autoPlay && this.images.length > 1) {
            this.startAutoPlay();
        }
    }
    
    loadImages() {
        const container = document.querySelector(this.options.container);
        if (!container) return;
        
        // טעינת תמונות מהדום
        const imageElements = container.querySelectorAll('img[src]');
        this.images = Array.from(imageElements).map((img, index) => ({
            id: index,
            src: img.src,
            alt: img.alt || `תמונת רכב ${index + 1}`,
            title: img.title || '',
            element: img
        }));
        
        // הגדרת התמונה הראשונה כפעילה
        if (this.images.length > 0) {
            this.setActiveImage(0);
        }
    }
    
    setupEventListeners() {
        // לחיצה על תמונה ראשית
        const mainImage = document.querySelector(this.options.mainImageSelector);
        if (mainImage) {
            mainImage.addEventListener('click', () => this.openModal());
        }
        
        // לחיצה על תמונות קטנות
        const thumbnails = document.querySelectorAll(this.options.thumbnailSelector);
        thumbnails.forEach((thumb, index) => {
            thumb.addEventListener('click', (e) => {
                e.preventDefault();
                this.setActiveImage(index);
            });
        });
        
        // כפתורי ניווט
        const prevBtn = document.querySelector('.gallery-nav.prev');
        const nextBtn = document.querySelector('.gallery-nav.next');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.previousImage());
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextImage());
        }
        
        // מקלדת
        if (this.options.enableKeyboard) {
            document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        }
        
        // עצירת auto-play על hover
        const container = document.querySelector(this.options.container);
        if (container && this.options.autoPlay) {
            container.addEventListener('mouseenter', () => this.pauseAutoPlay());
            container.addEventListener('mouseleave', () => this.resumeAutoPlay());
        }
    }
    
    initializeTouch() {
        if (!this.options.enableTouch) return;
        
        const mainImage = document.querySelector(this.options.mainImageSelector);
        if (!mainImage) return;
        
        let startX = 0;
        let startY = 0;
        let isDragging = false;
        
        mainImage.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = true;
        });
        
        mainImage.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            e.preventDefault(); // מניעת גלילה
        });
        
        mainImage.addEventListener('touchend', (e) => {
            if (!isDragging) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            const diffX = startX - endX;
            const diffY = startY - endY;
            
            // בדיקה שזה החלקה אופקית ולא אנכית
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    this.nextImage();
                } else {
                    this.previousImage();
                }
            }
            
            isDragging = false;
        });
    }
    
    setActiveImage(index) {
        if (index < 0 || index >= this.images.length) return;
        
        this.currentImageIndex = index;
        const image = this.images[index];
        
        // עדכון התמונה הראשית
        const mainImage = document.querySelector(this.options.mainImageSelector);
        if (mainImage) {
            this.updateMainImage(mainImage, image);
        }
        
        // עדכון thumbnails
        this.updateThumbnails();
        
        // עדכון מחוון
        this.updateIndicator();
        
        // אירוע שינוי
        this.trigger('imageChanged', {
            index: index,
            image: image
        });
    }
    
    updateMainImage(element, image) {
        // אפקט fade
        element.style.opacity = '0.5';
        
        setTimeout(() => {
            element.src = image.src;
            element.alt = image.alt;
            element.style.opacity = '1';
        }, 150);
    }
    
    updateThumbnails() {
        const thumbnails = document.querySelectorAll(this.options.thumbnailSelector);
        thumbnails.forEach((thumb, index) => {
            thumb.classList.toggle('active', index === this.currentImageIndex);
        });
    }
    
    updateIndicator() {
        const indicator = document.querySelector('.gallery-indicator');
        if (indicator && this.images.length > 1) {
            indicator.textContent = `${this.currentImageIndex + 1} / ${this.images.length}`;
        }
    }
    
    nextImage() {
        const nextIndex = (this.currentImageIndex + 1) % this.images.length;
        this.setActiveImage(nextIndex);
    }
    
    previousImage() {
        const prevIndex = this.currentImageIndex === 0 ? this.images.length - 1 : this.currentImageIndex - 1;
        this.setActiveImage(prevIndex);
    }
    
    openModal() {
        // פתיחת Modal עם התמונה הנוכחית
        if (window.imageModal) {
            window.imageModal.open(this.images, this.currentImageIndex);
        } else {
            // fallback - פתיחה בחלון חדש
            window.open(this.images[this.currentImageIndex].src, '_blank');
        }
    }
    
    handleKeyboard(event) {
        // רק אם הגלריה בפוקוס
        const container = document.querySelector(this.options.container);
        if (!container || !this.isInViewport(container)) return;
        
        switch (event.key) {
            case 'ArrowRight':
                event.preventDefault();
                this.nextImage();
                break;
            case 'ArrowLeft':
                event.preventDefault();
                this.previousImage();
                break;
            case 'Home':
                event.preventDefault();
                this.setActiveImage(0);
                break;
            case 'End':
                event.preventDefault();
                this.setActiveImage(this.images.length - 1);
                break;
            case ' ':
            case 'Enter':
                event.preventDefault();
                this.openModal();
                break;
        }
    }
    
    startAutoPlay() {
        if (this.images.length <= 1) return;
        
        this.isAutoPlaying = true;
        this.autoPlayTimer = setInterval(() => {
            this.nextImage();
        }, this.options.autoPlayInterval);
        
        this.trigger('autoPlayStarted');
    }
    
    stopAutoPlay() {
        this.isAutoPlaying = false;
        if (this.autoPlayTimer) {
            clearInterval(this.autoPlayTimer);
            this.autoPlayTimer = null;
        }
        
        this.trigger('autoPlayStopped');
    }
    
    pauseAutoPlay() {
        if (this.isAutoPlaying && this.autoPlayTimer) {
            clearInterval(this.autoPlayTimer);
            this.autoPlayTimer = null;
        }
    }
    
    resumeAutoPlay() {
        if (this.isAutoPlaying && !this.autoPlayTimer) {
            this.autoPlayTimer = setInterval(() => {
                this.nextImage();
            }, this.options.autoPlayInterval);
        }
    }
    
    toggleAutoPlay() {
        if (this.isAutoPlaying) {
            this.stopAutoPlay();
        } else {
            this.startAutoPlay();
        }
    }
    
    // פונקציות עזר
    isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    trigger(eventName, data = {}) {
        const event = new CustomEvent(`gallery:${eventName}`, {
            detail: data
        });
        document.dispatchEvent(event);
    }
    
    // API ציבורי
    goToImage(index) {
        this.setActiveImage(index);
    }
    
    getCurrentImage() {
        return this.images[this.currentImageIndex];
    }
    
    getCurrentIndex() {
        return this.currentImageIndex;
    }
    
    getImageCount() {
        return this.images.length;
    }
    
    addImage(imageData) {
        this.images.push({
            id: this.images.length,
            src: imageData.src,
            alt: imageData.alt || `תמונת רכב ${this.images.length + 1}`,
            title: imageData.title || ''
        });
        
        this.render();
        this.trigger('imageAdded', { image: imageData });
    }
    
    removeImage(index) {
        if (index < 0 || index >= this.images.length) return false;
        
        const removedImage = this.images.splice(index, 1)[0];
        
        // התאמת האינדקס הנוכחי
        if (this.currentImageIndex >= this.images.length) {
            this.currentImageIndex = Math.max(0, this.images.length - 1);
        }
        
        this.render();
        this.trigger('imageRemoved', { image: removedImage });
        
        return true;
    }
    
    updateImage(index, imageData) {
        if (index < 0 || index >= this.images.length) return false;
        
        this.images[index] = {
            ...this.images[index],
            ...imageData
        };
        
        if (index === this.currentImageIndex) {
            this.setActiveImage(index);
        }
        
        this.trigger('imageUpdated', { index, image: this.images[index] });
        return true;
    }
    
    render() {
        // רינדור מחדש של הגלריה
        this.updateThumbnails();
        this.updateIndicator();
        
        if (this.images.length === 0) {
            this.showNoImagesMessage();
        }
    }
    
    showNoImagesMessage() {
        const container = document.querySelector(this.options.container);
        if (!container) return;
        
        container.innerHTML = `
            <div class="no-images">
                <div class="icon">📷</div>
                <h3>אין תמונות להצגה</h3>
                <p>לא נמצאו תמונות לרכב זה</p>
            </div>
        `;
    }
    
    destroy() {
        this.stopAutoPlay();
        
        // הסרת event listeners
        if (this.options.enableKeyboard) {
            document.removeEventListener('keydown', this.handleKeyboard);
        }
        
        // ניקוי
        this.images = [];
        this.currentImageIndex = 0;
        
        this.trigger('destroyed');
    }
}

// פונקציות עזר גלובליות
function changeMainImage(src, element) {
    const mainImage = document.getElementById('mainImage');
    if (mainImage) {
        mainImage.src = src;
        
        // עדכון סטטוס active של thumbnails
        document.querySelectorAll('.thumbnail-image').forEach(img => {
            img.classList.remove('active');
        });
        
        if (element) {
            element.classList.add('active');
        }
    }
    
    // עדכון הגלריה אם קיימת
    if (window.vehicleGallery) {
        const images = window.vehicleGallery.images;
        const index = images.findIndex(img => img.src === src);
        if (index !== -1) {
            window.vehicleGallery.setActiveImage(index);
        }
    }
}

// אתחול אוטומטי
document.addEventListener('DOMContentLoaded', function() {
    // חיפוש גלריות בדף
    const galleryContainers = document.querySelectorAll('.vehicle-images-gallery');
    
    galleryContainers.forEach((container, index) => {
        const galleryId = `gallery_${index}`;
        container.setAttribute('data-gallery-id', galleryId);
        
        // יצירת מופע גלריה
        const gallery = new ImageGallery({
            container: `[data-gallery-id="${galleryId}"]`,
            autoPlay: container.hasAttribute('data-autoplay'),
            autoPlayInterval: parseInt(container.dataset.autoplayInterval) || 5000
        });
        
        // שמירה למופע ראשון כגלובלי
        if (index === 0) {
            window.vehicleGallery = gallery;
        }
        
        // שמירת המופע על האלמנט
        container._galleryInstance = gallery;
    });
});

// האזנה לאירועי גלריה
document.addEventListener('gallery:imageChanged', function(e) {
    console.log('Image changed:', e.detail);
});

document.addEventListener('gallery:autoPlayStarted', function() {
    console.log('Auto-play started');
});

document.addEventListener('gallery:autoPlayStopped', function() {
    console.log('Auto-play stopped');
});

// ייצוא עבור שימוש במודולים
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImageGallery;
}