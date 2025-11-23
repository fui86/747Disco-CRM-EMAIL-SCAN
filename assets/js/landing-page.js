/**
 * 747 Disco - Landing Page JavaScript
 * Modern, performant, accessible interactions
 */

(function() {
    'use strict';
    
    // ============================================================================
    // CONFIGURATION
    // ============================================================================
    const config = {
        scrollThreshold: 300,
        animationDuration: 600,
        formSubmitDelay: 1000,
        debounceDelay: 150
    };
    
    // ============================================================================
    // UTILITY FUNCTIONS
    // ============================================================================
    
    /**
     * Debounce function to limit event frequency
     */
    const debounce = (func, delay) => {
        let timeoutId;
        return function(...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    };
    
    /**
     * Throttle function for scroll events
     */
    const throttle = (func, limit) => {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    };
    
    /**
     * Check if element is in viewport
     */
    const isInViewport = (element) => {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    };
    
    /**
     * Smooth scroll to element
     */
    const smoothScroll = (target) => {
        const element = document.querySelector(target);
        if (element) {
            const offsetTop = element.offsetTop - 80;
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
        }
    };
    
    /**
     * Format phone number
     */
    const formatPhoneNumber = (value) => {
        const cleaned = value.replace(/\D/g, '');
        const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
        if (match) {
            return `${match[1]} ${match[2]} ${match[3]}`;
        }
        return value;
    };
    
    // ============================================================================
    // BACK TO TOP BUTTON
    // ============================================================================
    const initBackToTop = () => {
        const backToTopBtn = document.getElementById('back-to-top');
        
        if (!backToTopBtn) return;
        
        const toggleBackToTop = throttle(() => {
            if (window.scrollY > config.scrollThreshold) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        }, 100);
        
        window.addEventListener('scroll', toggleBackToTop);
        
        backToTopBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Initial check
        toggleBackToTop();
    };
    
    // ============================================================================
    // SMOOTH SCROLL FOR NAVIGATION LINKS
    // ============================================================================
    const initSmoothScroll = () => {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip if it's just "#"
                if (href === '#') {
                    e.preventDefault();
                    return;
                }
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    smoothScroll(href);
                }
            });
        });
    };
    
    // ============================================================================
    // SCROLL ANIMATIONS (INTERSECTION OBSERVER)
    // ============================================================================
    const initScrollAnimations = () => {
        // Check if Intersection Observer is supported
        if (!('IntersectionObserver' in window)) {
            return;
        }
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Observe elements
        const animateElements = document.querySelectorAll(
            '.service-card, .menu-card, .why-card, .testimonial-card, .gallery-item'
        );
        
        animateElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = `all ${config.animationDuration}ms ease`;
            observer.observe(el);
        });
    };
    
    // ============================================================================
    // FORM VALIDATION
    // ============================================================================
    const initFormValidation = () => {
        const form = document.getElementById('preventivo-form');
        
        if (!form) return;
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => {
                if (input.classList.contains('error')) {
                    validateField(input);
                }
            });
        });
        
        // Phone number formatting
        const phoneInput = document.getElementById('telefono');
        if (phoneInput) {
            phoneInput.addEventListener('input', debounce((e) => {
                const formatted = formatPhoneNumber(e.target.value);
                if (formatted !== e.target.value) {
                    e.target.value = formatted;
                }
            }, config.debounceDelay));
        }
        
        // Date validation (must be future date)
        const dateInput = document.getElementById('data-evento');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
        }
        
        // Form submission
        form.addEventListener('submit', handleFormSubmit);
    };
    
    /**
     * Validate single field
     */
    const validateField = (field) => {
        const value = field.value.trim();
        const fieldType = field.type;
        const isRequired = field.hasAttribute('required');
        let isValid = true;
        let errorMessage = '';
        
        // Required field check
        if (isRequired && !value) {
            isValid = false;
            errorMessage = 'Questo campo ? obbligatorio';
        }
        
        // Email validation
        else if (fieldType === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Inserisci un indirizzo email valido';
            }
        }
        
        // Phone validation (Italian format)
        else if (field.id === 'telefono' && value) {
            const phoneRegex = /^[0-9\s]{10,15}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = 'Inserisci un numero di telefono valido';
            }
        }
        
        // Number validation
        else if (fieldType === 'number' && value) {
            const min = field.getAttribute('min');
            if (min && parseInt(value) < parseInt(min)) {
                isValid = false;
                errorMessage = `Il valore minimo ? ${min}`;
            }
        }
        
        // Date validation
        else if (fieldType === 'date' && value) {
            const selectedDate = new Date(value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                isValid = false;
                errorMessage = 'La data deve essere futura';
            }
        }
        
        // Update field state
        if (isValid) {
            field.classList.remove('error');
            hideFieldError(field);
        } else {
            field.classList.add('error');
            showFieldError(field, errorMessage);
        }
        
        return isValid;
    };
    
    /**
     * Show field error message
     */
    const showFieldError = (field, message) => {
        const errorElement = field.parentElement.querySelector('.form-error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    };
    
    /**
     * Hide field error message
     */
    const hideFieldError = (field) => {
        const errorElement = field.parentElement.querySelector('.form-error');
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    };
    
    /**
     * Handle form submission
     */
    const handleFormSubmit = async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText.textContent;
        
        // Validate all fields
        const inputs = form.querySelectorAll('input:required, select:required, textarea:required');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        // Check privacy checkbox
        const privacyCheckbox = document.getElementById('privacy');
        if (privacyCheckbox && !privacyCheckbox.checked) {
            isValid = false;
            alert('Devi accettare la Privacy Policy per procedere');
        }
        
        if (!isValid) {
            // Scroll to first error
            const firstError = form.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        btnText.textContent = 'Invio in corso...';
        
        // Get form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            // Simulate API call (replace with actual endpoint)
            await simulateFormSubmission(data);
            
            // Success
            showSuccessMessage();
            form.reset();
            
            // Scroll to success message
            setTimeout(() => {
                smoothScroll('#preventivo');
            }, 500);
            
        } catch (error) {
            console.error('Form submission error:', error);
            showErrorMessage('Si ? verificato un errore. Riprova pi? tardi.');
        } finally {
            // Re-enable submit button
            setTimeout(() => {
                submitBtn.disabled = false;
                btnText.textContent = originalText;
            }, config.formSubmitDelay);
        }
    };
    
    /**
     * Simulate form submission (replace with actual API call)
     */
    const simulateFormSubmission = (data) => {
        return new Promise((resolve, reject) => {
            console.log('Form data to submit:', data);
            
            // Simulate network delay
            setTimeout(() => {
                // Simulate success (90% success rate)
                if (Math.random() > 0.1) {
                    resolve({ success: true, message: 'Richiesta inviata con successo!' });
                } else {
                    reject(new Error('Network error'));
                }
            }, 2000);
        });
    };
    
    /**
     * Show success message
     */
    const showSuccessMessage = () => {
        const successHTML = `
            <div class="success-message" style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 3rem;
                border-radius: 16px;
                box-shadow: 0 16px 48px rgba(43, 30, 26, 0.3);
                text-align: center;
                max-width: 500px;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            ">
                <div style="font-size: 4rem; margin-bottom: 1rem;">?</div>
                <h3 style="color: var(--color-dark); margin-bottom: 1rem;">Richiesta Inviata!</h3>
                <p style="color: var(--color-grey); margin-bottom: 2rem;">
                    Grazie per averci contattato! Riceverai un preventivo personalizzato entro 24 ore.
                </p>
                <button onclick="this.parentElement.remove()" class="btn btn-primary">
                    OK
                </button>
            </div>
            <div class="overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                animation: fadeIn 0.3s ease;
            " onclick="this.nextElementSibling.remove(); this.remove();"></div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', successHTML);
    };
    
    /**
     * Show error message
     */
    const showErrorMessage = (message) => {
        const errorHTML = `
            <div class="error-message" style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 3rem;
                border-radius: 16px;
                box-shadow: 0 16px 48px rgba(43, 30, 26, 0.3);
                text-align: center;
                max-width: 500px;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            ">
                <div style="font-size: 4rem; margin-bottom: 1rem;">?</div>
                <h3 style="color: var(--color-dark); margin-bottom: 1rem;">Errore</h3>
                <p style="color: var(--color-grey); margin-bottom: 2rem;">${message}</p>
                <button onclick="this.parentElement.remove(); document.querySelector('.overlay').remove();" class="btn btn-primary">
                    Chiudi
                </button>
            </div>
            <div class="overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9999;
                animation: fadeIn 0.3s ease;
            " onclick="this.nextElementSibling.remove(); this.remove();"></div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', errorHTML);
    };
    
    // ============================================================================
    // FAQ ACCORDION
    // ============================================================================
    const initFAQ = () => {
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                // Close other items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.hasAttribute('open')) {
                        otherItem.removeAttribute('open');
                    }
                });
            });
        });
    };
    
    // ============================================================================
    // LAZY LOADING IMAGES
    // ============================================================================
    const initLazyLoading = () => {
        if (!('IntersectionObserver' in window)) {
            return;
        }
        
        const lazyImages = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    };
    
    // ============================================================================
    // PERFORMANCE MONITORING
    // ============================================================================
    const monitorPerformance = () => {
        if ('performance' in window) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        const loadTime = perfData.loadEventEnd - perfData.fetchStart;
                        console.log(`Page load time: ${(loadTime / 1000).toFixed(2)}s`);
                        
                        // Log to analytics (if available)
                        if (window.gtag) {
                            gtag('event', 'timing_complete', {
                                'name': 'page_load',
                                'value': Math.round(loadTime),
                                'event_category': 'Performance'
                            });
                        }
                    }
                }, 0);
            });
        }
    };
    
    // ============================================================================
    // KEYBOARD NAVIGATION ENHANCEMENT
    // ============================================================================
    const initKeyboardNav = () => {
        // Add keyboard support for custom interactive elements
        document.querySelectorAll('[role="button"]').forEach(element => {
            element.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    element.click();
                }
            });
        });
    };
    
    // ============================================================================
    // ANALYTICS TRACKING
    // ============================================================================
    const initAnalytics = () => {
        // Track button clicks
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const btnText = btn.textContent.trim();
                const btnHref = btn.getAttribute('href');
                
                if (window.gtag) {
                    gtag('event', 'button_click', {
                        'event_category': 'Engagement',
                        'event_label': btnText,
                        'value': btnHref
                    });
                }
                
                console.log('Button clicked:', btnText);
            });
        });
        
        // Track scroll depth
        let maxScroll = 0;
        const trackScrollDepth = throttle(() => {
            const scrollPercentage = Math.round(
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            );
            
            if (scrollPercentage > maxScroll) {
                maxScroll = scrollPercentage;
                
                if (window.gtag && [25, 50, 75, 90].includes(scrollPercentage)) {
                    gtag('event', 'scroll_depth', {
                        'event_category': 'Engagement',
                        'event_label': `${scrollPercentage}%`,
                        'value': scrollPercentage
                    });
                }
            }
        }, 500);
        
        window.addEventListener('scroll', trackScrollDepth);
        
        // Track time on page
        const startTime = Date.now();
        window.addEventListener('beforeunload', () => {
            const timeOnPage = Math.round((Date.now() - startTime) / 1000);
            
            if (window.gtag) {
                gtag('event', 'time_on_page', {
                    'event_category': 'Engagement',
                    'value': timeOnPage
                });
            }
        });
    };
    
    // ============================================================================
    // MOBILE MENU TOUCH ENHANCEMENTS
    // ============================================================================
    const initTouchEnhancements = () => {
        // Add touch feedback for buttons on mobile
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn, .service-card, .menu-card').forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                element.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 100);
                });
            });
        }
    };
    
    // ============================================================================
    // SERVICE WORKER REGISTRATION (PWA)
    // ============================================================================
    const initServiceWorker = () => {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('ServiceWorker registered:', registration);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed:', err);
                    });
            });
        }
    };
    
    // ============================================================================
    // INITIALIZE ALL
    // ============================================================================
    const init = () => {
        console.log('?? 747 Disco Landing Page - Initializing...');
        
        // Core functionality
        initBackToTop();
        initSmoothScroll();
        initScrollAnimations();
        initFormValidation();
        initFAQ();
        initLazyLoading();
        initKeyboardNav();
        initTouchEnhancements();
        
        // Analytics & Performance
        monitorPerformance();
        initAnalytics();
        
        // Progressive Web App
        // initServiceWorker(); // Uncomment when sw.js is ready
        
        console.log('? 747 Disco Landing Page - Ready!');
    };
    
    // ============================================================================
    // DOM READY
    // ============================================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // ============================================================================
    // EXPOSE PUBLIC API
    // ============================================================================
    window.Disco747 = {
        smoothScroll,
        validateField,
        showSuccessMessage,
        showErrorMessage
    };
    
})();
