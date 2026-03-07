// Testimonial Image Cropper - Standalone Version
(function() {
    'use strict';
    
    let cropper = null;
    let currentCropField = '';
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initCropper();
    });
    
    function initCropper() {
        var beforeInput = document.getElementById('before_image');
        var afterInput = document.getElementById('after_image');
        
        if (beforeInput) {
            beforeInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    openCropModal(this.files[0], 'before');
                }
            });
        }
        
        if (afterInput) {
            afterInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    openCropModal(this.files[0], 'after');
                }
            });
        }
        
        // Modal buttons
        var cancelBtn = document.getElementById('cropCancelBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeCropModal);
        }
        
        var saveBtn = document.getElementById('cropSaveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveCrop);
        }
        
        // Close on backdrop click
        var modal = document.getElementById('cropModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeCropModal();
                }
            });
        }
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('cropModal');
                if (modal && modal.classList.contains('active')) {
                    closeCropModal();
                }
            }
        });
    }
    
    window.openCropModal = function(file, fieldName) {
        currentCropField = fieldName;
        
        var reader = new FileReader();
        reader.onload = function(e) {
            var imageContainer = document.getElementById('cropImageContainer');
            if (!imageContainer) {
                console.error('Image container not found');
                return;
            }
            
            // Clear previous image and create new one
            imageContainer.innerHTML = '';
            var img = document.createElement('img');
            img.id = 'cropImage';
            img.style.maxWidth = '100%';
            img.src = e.target.result;
            imageContainer.appendChild(img);
            
            var modal = document.getElementById('cropModal');
            if (!modal) {
                console.error('Modal not found');
                return;
            }
            modal.classList.add('active');
            
            // Destroy existing cropper
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            
            // Initialize Cropper
            if (typeof Cropper === 'undefined') {
                alert('Cropper.js not loaded. Please refresh the page.');
                return;
            }
            
            cropper = new Cropper(img, {
                aspectRatio: 4 / 5,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.9,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                ready: function() {
                    setupZoomSlider();
                }
            });
        };
        
        reader.onerror = function() {
            alert('Failed to read file');
        };
        
        reader.readAsDataURL(file);
    };
    
    function setupZoomSlider() {
        var zoomSlider = document.getElementById('zoomSlider');
        if (zoomSlider && cropper) {
            zoomSlider.value = 1;
            zoomSlider.oninput = function() {
                if (cropper) {
                    cropper.zoomTo(this.value);
                }
            };
        }
    }
    
    window.closeCropModal = function() {
        var modal = document.getElementById('cropModal');
        if (modal) {
            modal.classList.remove('active');
        }
        
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        
        // Clear the file input
        var inputName = currentCropField === 'before' ? 'before_image' : 'after_image';
        var input = document.getElementById(inputName);
        if (input) {
            input.value = '';
        }
    };
    
    window.saveCrop = function() {
        if (!cropper) {
            alert('No cropper initialized');
            return;
        }
        
        // Get cropped canvas
        var canvas = cropper.getCroppedCanvas({
            maxWidth: 1200,
            maxHeight: 1500,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        if (!canvas) {
            alert('Failed to crop image');
            return;
        }
        
        // Convert to base64
        var croppedDataUrl = canvas.toDataURL('image/jpeg', 0.92);
        
        // Store in hidden field
        var hiddenFieldName = currentCropField + '_cropped';
        var hiddenField = document.getElementById(hiddenFieldName);
        
        if (!hiddenField) {
            hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = hiddenFieldName;
            hiddenField.id = hiddenFieldName;
            
            var form = document.querySelector('form');
            if (form) {
                form.appendChild(hiddenField);
            }
        }
        
        hiddenField.value = croppedDataUrl;
        
        // Show preview
        var previewContainer = document.getElementById(currentCropField + 'Preview');
        if (previewContainer) {
            previewContainer.innerHTML = '<img src="' + croppedDataUrl + '" class="cropped-preview" style="max-width:100px;max-height:100px;border-radius:4px;border:2px solid #28a745;margin-top:10px;"> <span class="pending-badge" style="display:inline-block;background:#ffc107;color:#333;padding:2px 8px;border-radius:3px;font-size:11px;margin-left:8px;">Cropped - Ready to save</span>';
        }
        
        closeCropModal();
    };
    
})();
