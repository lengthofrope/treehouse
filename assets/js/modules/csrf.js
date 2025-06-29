/**
 * TreeHouse CSRF Token Management Module
 * 
 * Provides JavaScript utilities for dynamic CSRF token injection,
 * making pages cache-friendly while maintaining security.
 * 
 * @package LengthOfRope\TreeHouse\Assets
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */

class TreeHouseCsrf {
    constructor(options = {}) {
        this.tokenEndpoint = options.tokenEndpoint || '/_csrf/token';
        this.tokenField = options.tokenField || '_token';
        this.metaName = options.metaName || 'csrf-token';
        this.cache = {
            token: null,
            expires: 0
        };
        this.cacheDuration = options.cacheDuration || 300000; // 5 minutes
        this.retryAttempts = options.retryAttempts || 3;
        this.retryDelay = options.retryDelay || 1000;
    }

    /**
     * Get a fresh CSRF token from the server
     * 
     * @returns {Promise<string>} The CSRF token
     */
    async fetchToken() {
        const now = Date.now();
        
        // Return cached token if still valid
        if (this.cache.token && now < this.cache.expires) {
            return this.cache.token;
        }

        let lastError;
        
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                const response = await fetch(this.tokenEndpoint, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-cache'
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                
                if (!data.token) {
                    throw new Error('Invalid response: missing token');
                }

                // Cache the token
                this.cache.token = data.token;
                this.cache.expires = now + this.cacheDuration;

                return data.token;
            } catch (error) {
                lastError = error;
                console.warn(`CSRF token fetch attempt ${attempt} failed:`, error.message);
                
                if (attempt < this.retryAttempts) {
                    await this.delay(this.retryDelay * attempt);
                }
            }
        }

        throw new Error(`Failed to fetch CSRF token after ${this.retryAttempts} attempts: ${lastError.message}`);
    }

    /**
     * Inject CSRF tokens into all forms on the page
     * 
     * @returns {Promise<number>} Number of forms updated
     */
    async injectIntoForms() {
        try {
            const token = await this.fetchToken();
            const forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
            let updatedCount = 0;

            forms.forEach(form => {
                // Check if form already has a CSRF token
                let tokenInput = form.querySelector(`input[name="${this.tokenField}"]`);
                
                if (!tokenInput) {
                    // Create new hidden input
                    tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = this.tokenField;
                    form.appendChild(tokenInput);
                }
                
                // Update token value
                tokenInput.value = token;
                updatedCount++;
            });

            return updatedCount;
        } catch (error) {
            console.error('Failed to inject CSRF tokens into forms:', error);
            return 0;
        }
    }

    /**
     * Update CSRF meta tag in document head
     * 
     * @returns {Promise<boolean>} Success status
     */
    async updateMetaTag() {
        try {
            const token = await this.fetchToken();
            let metaTag = document.querySelector(`meta[name="${this.metaName}"]`);
            
            if (!metaTag) {
                // Create new meta tag
                metaTag = document.createElement('meta');
                metaTag.name = this.metaName;
                document.head.appendChild(metaTag);
            }
            
            metaTag.content = token;
            return true;
        } catch (error) {
            console.error('Failed to update CSRF meta tag:', error);
            return false;
        }
    }

    /**
     * Get the current CSRF token (from cache or fetch new)
     * 
     * @returns {Promise<string>} The CSRF token
     */
    async getToken() {
        return await this.fetchToken();
    }

    /**
     * Add CSRF token to AJAX request headers
     * 
     * @param {Object} headers - Request headers object
     * @returns {Promise<Object>} Updated headers
     */
    async addToHeaders(headers = {}) {
        try {
            const token = await this.fetchToken();
            return {
                ...headers,
                'X-CSRF-TOKEN': token
            };
        } catch (error) {
            console.error('Failed to add CSRF token to headers:', error);
            return headers;
        }
    }

    /**
     * Add CSRF token to form data
     * 
     * @param {FormData|Object} data - Form data
     * @returns {Promise<FormData|Object>} Updated form data
     */
    async addToFormData(data) {
        try {
            const token = await this.fetchToken();
            
            if (data instanceof FormData) {
                data.set(this.tokenField, token);
            } else if (typeof data === 'object') {
                data[this.tokenField] = token;
            }
            
            return data;
        } catch (error) {
            console.error('Failed to add CSRF token to form data:', error);
            return data;
        }
    }

    /**
     * Initialize CSRF protection on page load
     * 
     * @param {Object} options - Initialization options
     * @returns {Promise<void>}
     */
    async initialize(options = {}) {
        const {
            injectForms = true,
            updateMeta = true,
            setupAjax = true
        } = options;

        try {
            const promises = [];
            
            if (injectForms) {
                promises.push(this.injectIntoForms());
            }
            
            if (updateMeta) {
                promises.push(this.updateMetaTag());
            }
            
            await Promise.all(promises);
            
            if (setupAjax) {
                this.setupAjaxInterceptor();
            }
            
            console.log('TreeHouse CSRF protection initialized');
        } catch (error) {
            console.error('Failed to initialize CSRF protection:', error);
        }
    }

    /**
     * Setup automatic AJAX request interceptor
     */
    setupAjaxInterceptor() {
        // Store reference to the CSRF instance for XMLHttpRequest
        window._treehouseCsrf = this;
        
        // Intercept fetch requests
        const originalFetch = window.fetch;
        window.fetch = async (url, options = {}) => {
            if (this.shouldAddCsrfToken(options.method)) {
                try {
                    options.headers = await this.addToHeaders(options.headers);
                } catch (error) {
                    console.warn('Failed to add CSRF token to fetch request:', error);
                }
            }
            return originalFetch(url, options);
        };

        // Intercept XMLHttpRequest
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url, ...args) {
            this._method = method;
            return originalOpen.call(this, method, url, ...args);
        };
        
        XMLHttpRequest.prototype.send = async function(data) {
            if (this._method && window._treehouseCsrf.shouldAddCsrfToken(this._method)) {
                try {
                    const token = await window._treehouseCsrf.fetchToken();
                    this.setRequestHeader('X-CSRF-TOKEN', token);
                } catch (error) {
                    console.warn('Failed to add CSRF token to XMLHttpRequest:', error);
                }
            }
            return originalSend.call(this, data);
        };
    }

    /**
     * Check if CSRF token should be added to request
     * 
     * @param {string} method - HTTP method
     * @returns {boolean}
     */
    shouldAddCsrfToken(method) {
        if (!method) return false;
        const stateMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        return stateMethods.includes(method.toUpperCase());
    }

    /**
     * Utility delay function
     * 
     * @param {number} ms - Milliseconds to delay
     * @returns {Promise<void>}
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Clear cached token (force refresh on next request)
     */
    clearCache() {
        this.cache.token = null;
        this.cache.expires = 0;
    }
}

// Register CSRF module with TreeHouse
TreeHouse.register('csrf', {
    name: 'csrf',
    dependencies: [],
    
    async init(config) {
        this.csrf = new TreeHouseCsrf(config.csrf || {});
        
        // Initialize CSRF protection
        await this.csrf.initialize();
        
        // Emit events for other modules
        TreeHouse.emit('csrf:ready', this.csrf);
        
        return this;
    },
    
    // Export main methods for easy access
    getToken() {
        return this.csrf ? this.csrf.getToken() : Promise.reject(new Error('CSRF module not initialized'));
    },
    
    injectForms() {
        return this.csrf ? this.csrf.injectIntoForms() : Promise.reject(new Error('CSRF module not initialized'));
    },
    
    addToHeaders(headers) {
        return this.csrf ? this.csrf.addToHeaders(headers) : Promise.reject(new Error('CSRF module not initialized'));
    },
    
    addToFormData(data) {
        return this.csrf ? this.csrf.addToFormData(data) : Promise.reject(new Error('CSRF module not initialized'));
    },
    
    clearCache() {
        if (this.csrf) {
            this.csrf.clearCache();
        }
    }
});

// Export class for direct usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TreeHouseCsrf;
}