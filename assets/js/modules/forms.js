/**
 * TreeHouse Forms Module
 * 
 * Enhances form handling with CSRF protection, validation,
 * and AJAX submission capabilities.
 * 
 * @package LengthOfRope\TreeHouse\Assets
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */

class TreeHouseForms {
    constructor(options = {}) {
        this.config = {
            autoSubmit: options.autoSubmit !== false, // Default true
            showSpinner: options.showSpinner !== false, // Default true
            validateOnSubmit: options.validateOnSubmit !== false, // Default true
            csrfProtection: options.csrfProtection !== false, // Default true
            ...options
        };
        
        this.forms = new Map();
        this.validators = new Map();
    }

    /**
     * Initialize forms module
     */
    initialize() {
        this.setupFormHandlers();
        this.setupValidationRules();
        
        console.log('TreeHouse Forms module initialized');
    }

    /**
     * Setup automatic form handlers
     */
    setupFormHandlers() {
        // Handle form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            
            if (form.tagName === 'FORM' && this.shouldHandleForm(form)) {
                this.handleFormSubmit(e);
            }
        });

        // Handle dynamic form additions
        if (window.MutationObserver) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.tagName === 'FORM') {
                                this.enhanceForm(node);
                            } else if (node.querySelectorAll) {
                                node.querySelectorAll('form').forEach(form => {
                                    this.enhanceForm(form);
                                });
                            }
                        }
                    });
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        // Enhance existing forms
        document.querySelectorAll('form').forEach(form => {
            this.enhanceForm(form);
        });
    }

    /**
     * Check if form should be handled by TreeHouse
     */
    shouldHandleForm(form) {
        // Skip if explicitly disabled
        if (form.hasAttribute('data-treehouse-disable') || 
            form.classList.contains('treehouse-disable')) {
            return false;
        }

        // Handle if explicitly enabled
        if (form.hasAttribute('data-treehouse-enable') || 
            form.classList.contains('treehouse-enable')) {
            return true;
        }

        // Default handling for AJAX forms
        return form.hasAttribute('data-ajax') || 
               form.classList.contains('ajax-form');
    }

    /**
     * Enhance a form with TreeHouse functionality
     */
    enhanceForm(form) {
        if (this.forms.has(form)) {
            return; // Already enhanced
        }

        const formData = {
            element: form,
            config: this.getFormConfig(form),
            validators: []
        };

        this.forms.set(form, formData);

        // Add CSRF protection if enabled
        if (formData.config.csrfProtection && TreeHouse.isModuleLoaded('csrf')) {
            this.addCsrfProtection(form);
        }

        // Setup validation
        if (formData.config.validateOnSubmit) {
            this.setupFormValidation(form);
        }

        // Add loading state capabilities
        this.addLoadingState(form);
    }

    /**
     * Get form configuration from attributes
     */
    getFormConfig(form) {
        const config = { ...this.config };

        // Override with form-specific attributes
        if (form.hasAttribute('data-auto-submit')) {
            config.autoSubmit = form.getAttribute('data-auto-submit') !== 'false';
        }
        if (form.hasAttribute('data-show-spinner')) {
            config.showSpinner = form.getAttribute('data-show-spinner') !== 'false';
        }
        if (form.hasAttribute('data-validate')) {
            config.validateOnSubmit = form.getAttribute('data-validate') !== 'false';
        }
        if (form.hasAttribute('data-csrf')) {
            config.csrfProtection = form.getAttribute('data-csrf') !== 'false';
        }

        return config;
    }

    /**
     * Handle form submission
     */
    async handleFormSubmit(event) {
        const form = event.target;
        const formData = this.forms.get(form);

        if (!formData) {
            return; // Not a TreeHouse form
        }

        // Prevent default if AJAX form
        if (formData.config.autoSubmit && this.isAjaxForm(form)) {
            event.preventDefault();
            await this.submitFormAjax(form);
        }
    }

    /**
     * Check if form should be submitted via AJAX
     */
    isAjaxForm(form) {
        return form.hasAttribute('data-ajax') || 
               form.classList.contains('ajax-form');
    }

    /**
     * Submit form via AJAX
     */
    async submitFormAjax(form) {
        const formData = this.forms.get(form);
        
        try {
            // Show loading state
            if (formData.config.showSpinner) {
                this.setLoadingState(form, true);
            }

            // Validate form
            if (formData.config.validateOnSubmit) {
                const isValid = await this.validateForm(form);
                if (!isValid) {
                    return;
                }
            }

            // Prepare form data
            const data = new FormData(form);
            
            // Add CSRF token if needed
            if (formData.config.csrfProtection && TreeHouse.isModuleLoaded('csrf')) {
                const csrfModule = TreeHouse.getModule('csrf');
                await csrfModule.addToFormData(data);
            }

            // Make request
            const response = await fetch(form.action || window.location.href, {
                method: form.method || 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // Handle response
            await this.handleFormResponse(form, response);

        } catch (error) {
            console.error('Form submission failed:', error);
            this.handleFormError(form, error);
        } finally {
            // Hide loading state
            if (formData.config.showSpinner) {
                this.setLoadingState(form, false);
            }
        }
    }

    /**
     * Handle form response
     */
    async handleFormResponse(form, response) {
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            this.handleJsonResponse(form, data, response);
        } else {
            const html = await response.text();
            this.handleHtmlResponse(form, html, response);
        }
    }

    /**
     * Handle JSON response
     */
    handleJsonResponse(form, data, response) {
        TreeHouse.emit('form:response', { form, data, response, type: 'json' });

        if (response.ok) {
            TreeHouse.emit('form:success', { form, data, response });
            
            // Handle redirect
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            
            // Show success message
            if (data.message) {
                this.showMessage(form, data.message, 'success');
            }
        } else {
            TreeHouse.emit('form:error', { form, data, response });
            
            // Show errors
            if (data.errors) {
                this.showValidationErrors(form, data.errors);
            } else if (data.message) {
                this.showMessage(form, data.message, 'error');
            }
        }
    }

    /**
     * Handle HTML response
     */
    handleHtmlResponse(form, html, response) {
        TreeHouse.emit('form:response', { form, html, response, type: 'html' });

        if (response.ok) {
            TreeHouse.emit('form:success', { form, html, response });
            
            // Replace form with response HTML
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newForm = doc.querySelector('form');
            
            if (newForm) {
                form.innerHTML = newForm.innerHTML;
                this.enhanceForm(form);
            }
        } else {
            TreeHouse.emit('form:error', { form, html, response });
            this.showMessage(form, 'An error occurred while submitting the form.', 'error');
        }
    }

    /**
     * Handle form error
     */
    handleFormError(form, error) {
        TreeHouse.emit('form:error', { form, error });
        this.showMessage(form, 'Network error. Please try again.', 'error');
    }

    /**
     * Add CSRF protection to form
     */
    async addCsrfProtection(form) {
        if (!TreeHouse.isModuleLoaded('csrf')) {
            return;
        }

        const csrfModule = TreeHouse.getModule('csrf');
        await csrfModule.injectForms();
    }

    /**
     * Setup form validation
     */
    setupFormValidation(form) {
        // Add validation event listeners
        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
            }
        });

        // Real-time validation
        form.addEventListener('input', (e) => {
            this.validateField(e.target);
        });

        form.addEventListener('blur', (e) => {
            this.validateField(e.target);
        });
    }

    /**
     * Validate entire form
     */
    async validateForm(form) {
        const fields = form.querySelectorAll('input, select, textarea');
        let isValid = true;

        for (const field of fields) {
            if (!await this.validateField(field)) {
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * Validate single field
     */
    async validateField(field) {
        // Clear previous errors
        this.clearFieldError(field);

        // Skip if no validation rules
        if (!this.hasValidationRules(field)) {
            return true;
        }

        let isValid = true;
        const value = field.value;

        // Required validation
        if (field.hasAttribute('required') && !value.trim()) {
            this.showFieldError(field, 'This field is required.');
            isValid = false;
        }

        // Type validation
        if (isValid && value) {
            isValid = await this.validateFieldType(field, value);
        }

        // Custom validation
        if (isValid && field.hasAttribute('data-validate')) {
            const validatorName = field.getAttribute('data-validate');
            isValid = await this.runCustomValidator(field, validatorName, value);
        }

        return isValid;
    }

    /**
     * Check if field has validation rules
     */
    hasValidationRules(field) {
        return field.hasAttribute('required') ||
               field.type === 'email' ||
               field.type === 'url' ||
               field.hasAttribute('pattern') ||
               field.hasAttribute('data-validate');
    }

    /**
     * Validate field type
     */
    async validateFieldType(field, value) {
        switch (field.type) {
            case 'email':
                if (!this.isValidEmail(value)) {
                    this.showFieldError(field, 'Please enter a valid email address.');
                    return false;
                }
                break;

            case 'url':
                if (!this.isValidUrl(value)) {
                    this.showFieldError(field, 'Please enter a valid URL.');
                    return false;
                }
                break;
        }

        // Pattern validation
        if (field.hasAttribute('pattern')) {
            const pattern = new RegExp(field.getAttribute('pattern'));
            if (!pattern.test(value)) {
                const message = field.getAttribute('title') || 'Please match the requested format.';
                this.showFieldError(field, message);
                return false;
            }
        }

        return true;
    }

    /**
     * Run custom validator
     */
    async runCustomValidator(field, validatorName, value) {
        const validator = this.validators.get(validatorName);
        
        if (!validator) {
            console.warn(`Validator '${validatorName}' not found`);
            return true;
        }

        try {
            const result = await validator(value, field);
            
            if (result !== true) {
                this.showFieldError(field, result || 'Validation failed.');
                return false;
            }
            
            return true;
        } catch (error) {
            console.error(`Validator '${validatorName}' failed:`, error);
            return false;
        }
    }

    /**
     * Register custom validator
     */
    addValidator(name, validator) {
        this.validators.set(name, validator);
    }

    /**
     * Setup default validation rules
     */
    setupValidationRules() {
        // Email validation
        this.addValidator('email', (value) => {
            return this.isValidEmail(value) || 'Please enter a valid email address.';
        });

        // URL validation
        this.addValidator('url', (value) => {
            return this.isValidUrl(value) || 'Please enter a valid URL.';
        });

        // Minimum length
        this.addValidator('minlength', (value, field) => {
            const minLength = parseInt(field.getAttribute('data-minlength') || '0');
            return value.length >= minLength || `Minimum length is ${minLength} characters.`;
        });

        // Maximum length
        this.addValidator('maxlength', (value, field) => {
            const maxLength = parseInt(field.getAttribute('data-maxlength') || '999999');
            return value.length <= maxLength || `Maximum length is ${maxLength} characters.`;
        });
    }

    /**
     * Utility methods
     */
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Show field error
     */
    showFieldError(field, message) {
        field.classList.add('error', 'invalid');
        
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    /**
     * Clear field error
     */
    clearFieldError(field) {
        field.classList.remove('error', 'invalid');
        
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }

    /**
     * Show validation errors from server response
     */
    showValidationErrors(form, errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                const messages = Array.isArray(errors[fieldName]) ? errors[fieldName] : [errors[fieldName]];
                this.showFieldError(field, messages[0]);
            }
        });
    }

    /**
     * Show general message
     */
    showMessage(form, message, type = 'info') {
        TreeHouse.emit('form:message', { form, message, type });
        
        // Create or update message element
        let messageElement = form.querySelector('.form-message');
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.className = 'form-message';
            form.insertBefore(messageElement, form.firstChild);
        }
        
        messageElement.className = `form-message ${type}`;
        messageElement.textContent = message;
        messageElement.style.display = 'block';
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                messageElement.style.display = 'none';
            }, 5000);
        }
    }

    /**
     * Add loading state capabilities
     */
    addLoadingState(form) {
        // Create spinner element
        const spinner = document.createElement('div');
        spinner.className = 'form-spinner';
        spinner.style.display = 'none';
        spinner.innerHTML = '<div class="spinner"></div>';
        
        form.appendChild(spinner);
    }

    /**
     * Set loading state
     */
    setLoadingState(form, loading) {
        const spinner = form.querySelector('.form-spinner');
        const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        
        if (loading) {
            form.classList.add('loading');
            if (spinner) spinner.style.display = 'block';
            
            submitButtons.forEach(button => {
                button.disabled = true;
                button.setAttribute('data-original-text', button.textContent);
                button.textContent = 'Loading...';
            });
        } else {
            form.classList.remove('loading');
            if (spinner) spinner.style.display = 'none';
            
            submitButtons.forEach(button => {
                button.disabled = false;
                const originalText = button.getAttribute('data-original-text');
                if (originalText) {
                    button.textContent = originalText;
                    button.removeAttribute('data-original-text');
                }
            });
        }
    }
}

// Register Forms module with TreeHouse
TreeHouse.register('forms', {
    name: 'forms',
    dependencies: [], // CSRF is optional
    
    async init(config) {
        this.forms = new TreeHouseForms(config.forms || {});
        this.forms.initialize();
        
        TreeHouse.emit('forms:ready', this.forms);
        
        return this;
    },
    
    // Export main methods
    enhanceForm(form) {
        return this.forms ? this.forms.enhanceForm(form) : null;
    },
    
    validateForm(form) {
        return this.forms ? this.forms.validateForm(form) : false;
    },
    
    addValidator(name, validator) {
        if (this.forms) {
            this.forms.addValidator(name, validator);
        }
    },
    
    showMessage(form, message, type) {
        if (this.forms) {
            this.forms.showMessage(form, message, type);
        }
    }
});

// Export class for direct usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TreeHouseForms;
}