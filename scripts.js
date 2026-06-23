/* Enhanced scripts.js with animations and multiple image upload support */

// Scroll animations using IntersectionObserver
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fadeInUp');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all animatable elements
    document.querySelectorAll('.hostel-card, .search-filter, .header, .auth-container, .booking-section, .hostel-info-card').forEach(el => {
        observer.observe(el);
    });
}

// Multiple image preview and validation
function initImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="file"][multiple]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const previewContainer = document.getElementById('imagePreview') || createPreviewContainer(input);
            previewContainer.innerHTML = '';

            if (files.length === 0) return;

            // Validate min 2 images
            if (files.length < 2) {
                showValidationError('Please upload at least 2 images for the hostel gallery.');
                return;
            }

            // Preview images
            files.slice(0, 6).forEach((file, index) => { // Limit to 6 previews
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '80px';
                        img.style.height = '80px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '8px';
                        img.style.margin = '5px';
                        img.style.border = '2px solid #2e8b57';
                        img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                        previewContainer.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Show success
            previewContainer.style.display = 'flex';
            showValidationSuccess(`${files.length} images selected (showing first 6)`);
        });
    });
}

function createPreviewContainer(input) {
    const container = document.createElement('div');
    container.id = 'imagePreview';
    container.style.cssText = `
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
        padding: 15px;
        background: linear-gradient(145deg, #f8fafc, #ffffff);
        border: 2px dashed #2e8b57;
        border-radius: 12px;
        min-height: 100px;
    `;
    input.parentNode.insertBefore(container, input.nextSibling);
    return container;
}

function showValidationError(message) {
    let errorDiv = document.getElementById('imageValidationError');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'imageValidationError';
        errorDiv.className = 'error';
        document.querySelector('form')?.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
    setTimeout(() => errorDiv.remove(), 5000);
}

function showValidationSuccess(message) {
    let successDiv = document.getElementById('imageValidationSuccess');
    if (!successDiv) {
        successDiv = document.createElement('div');
        successDiv.id = 'imageValidationSuccess';
        successDiv.className = 'success';
        document.querySelector('form')?.appendChild(successDiv);
    }
    successDiv.textContent = message;
    setTimeout(() => successDiv.remove(), 3000);
}

// Search and filter functionality (existing)
document.addEventListener('DOMContentLoaded', function() {
    // Initialize animations
    initScrollAnimations();
    
    // Initialize image preview
    initImagePreview();

    // Existing search functionality
    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const filterValue = document.getElementById('filterSelect').value;
            const hostelCards = document.querySelectorAll('.hostel-card');

            hostelCards.forEach(card => {
                const hostelName = card.querySelector('h3').textContent.toLowerCase();
                const location = card.querySelector('p').textContent.toLowerCase();

                const matchesSearch = hostelName.includes(searchInput);
                const matchesFilter = filterValue === '' || location.includes(filterValue.toLowerCase());

                if (matchesSearch && matchesFilter) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Form validation enhancements
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const imageInput = form.querySelector('input[type="file"][multiple]');
            if (imageInput && imageInput.files.length > 0 && imageInput.files.length < 2) {
                e.preventDefault();
                showValidationError('Please upload at least 2 images.');
                return false;
            }
        });
    });
});
