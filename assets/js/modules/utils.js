/**
 * TreeHouse Utilities Module
 * 
 * Common utility functions and helpers for TreeHouse applications.
 * 
 * @package LengthOfRope\TreeHouse\Assets
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */

class TreeHouseUtils {
    constructor() {
        this.debounceTimers = new Map();
        this.throttleTimers = new Map();
    }

    /**
     * Debounce function execution
     * 
     * @param {Function} func Function to debounce
     * @param {number} delay Delay in milliseconds
     * @param {string} key Unique key for this debounce instance
     * @returns {Function} Debounced function
     */
    debounce(func, delay, key = 'default') {
        return (...args) => {
            if (this.debounceTimers.has(key)) {
                clearTimeout(this.debounceTimers.get(key));
            }
            
            const timer = setTimeout(() => {
                func.apply(this, args);
                this.debounceTimers.delete(key);
            }, delay);
            
            this.debounceTimers.set(key, timer);
        };
    }

    /**
     * Throttle function execution
     * 
     * @param {Function} func Function to throttle
     * @param {number} delay Delay in milliseconds
     * @param {string} key Unique key for this throttle instance
     * @returns {Function} Throttled function
     */
    throttle(func, delay, key = 'default') {
        return (...args) => {
            if (this.throttleTimers.has(key)) {
                return;
            }
            
            func.apply(this, args);
            
            const timer = setTimeout(() => {
                this.throttleTimers.delete(key);
            }, delay);
            
            this.throttleTimers.set(key, timer);
        };
    }

    /**
     * Deep clone an object
     * 
     * @param {*} obj Object to clone
     * @returns {*} Cloned object
     */
    deepClone(obj) {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }

        if (obj instanceof Date) {
            return new Date(obj.getTime());
        }

        if (obj instanceof Array) {
            return obj.map(item => this.deepClone(item));
        }

        if (typeof obj === 'object') {
            const cloned = {};
            Object.keys(obj).forEach(key => {
                cloned[key] = this.deepClone(obj[key]);
            });
            return cloned;
        }

        return obj;
    }

    /**
     * Get deeply nested object property
     * 
     * @param {Object} obj Object to search
     * @param {string} path Dot-notation path
     * @param {*} defaultValue Default value if not found
     * @returns {*} Property value or default
     */
    get(obj, path, defaultValue = undefined) {
        if (!obj || typeof obj !== 'object') {
            return defaultValue;
        }

        const keys = path.split('.');
        let current = obj;

        for (const key of keys) {
            if (current[key] === undefined || current[key] === null) {
                return defaultValue;
            }
            current = current[key];
        }

        return current;
    }

    /**
     * Set deeply nested object property
     * 
     * @param {Object} obj Object to modify
     * @param {string} path Dot-notation path
     * @param {*} value Value to set
     * @returns {Object} Modified object
     */
    set(obj, path, value) {
        const keys = path.split('.');
        let current = obj;

        for (let i = 0; i < keys.length - 1; i++) {
            const key = keys[i];
            if (!current[key] || typeof current[key] !== 'object') {
                current[key] = {};
            }
            current = current[key];
        }

        current[keys[keys.length - 1]] = value;
        return obj;
    }

    /**
     * Check if value is empty
     * 
     * @param {*} value Value to check
     * @returns {boolean} True if empty
     */
    isEmpty(value) {
        if (value === null || value === undefined) {
            return true;
        }

        if (typeof value === 'string') {
            return value.trim() === '';
        }

        if (Array.isArray(value)) {
            return value.length === 0;
        }

        if (typeof value === 'object') {
            return Object.keys(value).length === 0;
        }

        return false;
    }

    /**
     * Generate random string
     * 
     * @param {number} length String length
     * @param {string} chars Characters to use
     * @returns {string} Random string
     */
    randomString(length = 8, chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789') {
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    /**
     * Format file size
     * 
     * @param {number} bytes File size in bytes
     * @param {number} decimals Number of decimal places
     * @returns {string} Formatted file size
     */
    formatFileSize(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    /**
     * Format number with separators
     * 
     * @param {number} number Number to format
     * @param {string} separator Thousands separator
     * @param {string} decimal Decimal separator
     * @returns {string} Formatted number
     */
    formatNumber(number, separator = ',', decimal = '.') {
        const parts = number.toString().split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, separator);
        return parts.join(decimal);
    }

    /**
     * Sanitize string for HTML
     * 
     * @param {string} str String to sanitize
     * @returns {string} Sanitized string
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Parse query string
     * 
     * @param {string} queryString Query string to parse
     * @returns {Object} Parsed parameters
     */
    parseQuery(queryString = window.location.search) {
        const params = {};
        const query = queryString.substring(1);
        
        if (!query) {
            return params;
        }

        query.split('&').forEach(param => {
            const [key, value] = param.split('=');
            if (key) {
                params[decodeURIComponent(key)] = value ? decodeURIComponent(value) : '';
            }
        });

        return params;
    }

    /**
     * Build query string from object
     * 
     * @param {Object} params Parameters object
     * @returns {string} Query string
     */
    buildQuery(params) {
        const query = Object.keys(params)
            .filter(key => params[key] !== null && params[key] !== undefined)
            .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
            .join('&');
        
        return query ? `?${query}` : '';
    }

    /**
     * Get cookie value
     * 
     * @param {string} name Cookie name
     * @returns {string|null} Cookie value
     */
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    }

    /**
     * Set cookie
     * 
     * @param {string} name Cookie name
     * @param {string} value Cookie value
     * @param {number} days Days until expiration
     * @param {string} path Cookie path
     */
    setCookie(name, value, days = 7, path = '/') {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=${path}`;
    }

    /**
     * Delete cookie
     * 
     * @param {string} name Cookie name
     * @param {string} path Cookie path
     */
    deleteCookie(name, path = '/') {
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=${path}`;
    }

    /**
     * Wait for element to appear in DOM
     * 
     * @param {string} selector CSS selector
     * @param {number} timeout Timeout in milliseconds
     * @returns {Promise<Element>} Promise resolving to element
     */
    waitForElement(selector, timeout = 5000) {
        return new Promise((resolve, reject) => {
            const element = document.querySelector(selector);
            if (element) {
                resolve(element);
                return;
            }

            const observer = new MutationObserver((mutations, obs) => {
                const element = document.querySelector(selector);
                if (element) {
                    obs.disconnect();
                    resolve(element);
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            setTimeout(() => {
                observer.disconnect();
                reject(new Error(`Element '${selector}' not found within ${timeout}ms`));
            }, timeout);
        });
    }

    /**
     * Smooth scroll to element
     * 
     * @param {Element|string} target Target element or selector
     * @param {number} offset Offset in pixels
     * @param {number} duration Animation duration
     */
    scrollTo(target, offset = 0, duration = 500) {
        const element = typeof target === 'string' ? document.querySelector(target) : target;
        
        if (!element) {
            console.warn('Scroll target not found:', target);
            return;
        }

        const targetPosition = element.offsetTop - offset;
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        
        let startTime = null;

        const animation = (currentTime) => {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const progress = Math.min(timeElapsed / duration, 1);
            
            // Easing function (ease-in-out)
            const ease = progress < 0.5 
                ? 2 * progress * progress 
                : -1 + (4 - 2 * progress) * progress;
            
            window.scrollTo(0, startPosition + distance * ease);
            
            if (progress < 1) {
                requestAnimationFrame(animation);
            }
        };

        requestAnimationFrame(animation);
    }

    /**
     * Check if element is in viewport
     * 
     * @param {Element} element Element to check
     * @param {number} threshold Threshold percentage (0-1)
     * @returns {boolean} True if in viewport
     */
    isInViewport(element, threshold = 0) {
        const rect = element.getBoundingClientRect();
        const height = window.innerHeight || document.documentElement.clientHeight;
        const width = window.innerWidth || document.documentElement.clientWidth;
        
        const verticalThreshold = height * threshold;
        const horizontalThreshold = width * threshold;
        
        return (
            rect.top >= -verticalThreshold &&
            rect.left >= -horizontalThreshold &&
            rect.bottom <= height + verticalThreshold &&
            rect.right <= width + horizontalThreshold
        );
    }

    /**
     * Create event emitter
     * 
     * @returns {Object} Event emitter object
     */
    createEventEmitter() {
        const listeners = new Map();
        
        return {
            on(event, callback) {
                if (!listeners.has(event)) {
                    listeners.set(event, []);
                }
                listeners.get(event).push(callback);
            },
            
            off(event, callback) {
                if (!listeners.has(event)) return;
                const eventListeners = listeners.get(event);
                const index = eventListeners.indexOf(callback);
                if (index > -1) {
                    eventListeners.splice(index, 1);
                }
            },
            
            emit(event, data) {
                if (!listeners.has(event)) return;
                listeners.get(event).forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        console.error(`Error in event listener for '${event}':`, error);
                    }
                });
            }
        };
    }

    /**
     * Load script dynamically
     * 
     * @param {string} src Script source URL
     * @param {Object} options Loading options
     * @returns {Promise<void>} Promise resolving when script loads
     */
    loadScript(src, options = {}) {
        return new Promise((resolve, reject) => {
            // Check if script already exists
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = options.async !== false;
            script.defer = options.defer === true;
            
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
            
            const target = options.target || document.head;
            target.appendChild(script);
        });
    }

    /**
     * Load CSS dynamically
     * 
     * @param {string} href CSS file URL
     * @returns {Promise<void>} Promise resolving when CSS loads
     */
    loadCSS(href) {
        return new Promise((resolve, reject) => {
            // Check if CSS already exists
            if (document.querySelector(`link[href="${href}"]`)) {
                resolve();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            
            link.onload = () => resolve();
            link.onerror = () => reject(new Error(`Failed to load CSS: ${href}`));
            
            document.head.appendChild(link);
        });
    }
}

// Register Utils module with TreeHouse
TreeHouse.register('utils', {
    name: 'utils',
    dependencies: [],
    
    async init(config) {
        this.utils = new TreeHouseUtils();
        
        // Add utility methods to TreeHouse global for easy access
        TreeHouse.utils = this.utils;
        
        TreeHouse.emit('utils:ready', this.utils);
        
        return this;
    }
});

// Export class for direct usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TreeHouseUtils;
}