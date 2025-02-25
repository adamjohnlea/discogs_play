/**
 * Simple lazy loading implementation for images
 * Looks for images with data-src attributes and loads them when they become visible
 */
document.addEventListener('DOMContentLoaded', function() {
    // Options for the Intersection Observer
    const options = {
        root: null, // Use viewport as root
        rootMargin: '0px',
        threshold: 0.1 // Trigger when 10% of the element is visible
    };

    // Create observer
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    // Replace the src attribute with the data-src value
                    img.src = img.dataset.src;
                    
                    // Add loading animation
                    img.classList.add('loading');
                    
                    // Remove animation and observer once image is loaded
                    img.onload = function() {
                        img.classList.remove('loading');
                        observer.unobserve(img);
                    };
                }
            }
        });
    }, options);

    // Get all images with data-src attribute
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    // Observe each image
    lazyImages.forEach(img => {
        observer.observe(img);
    });
}); 