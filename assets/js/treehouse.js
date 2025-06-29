/**
 * TreeHouse Framework JavaScript Library
 * 
 * Core library providing module system, event handling, and framework utilities.
 * Serves as the foundation for all TreeHouse JavaScript functionality.
 * 
 * @package LengthOfRope\TreeHouse\Assets
 * @author  Bas de Kort <bdekort@proton.me>
 * @since   1.0.0
 */

// TreeHouse Framework - wrapped in IIFE to keep internals private
const TreeHouse = (() => {
    class TreeHouseFramework {
        constructor() {
            this.modules = new Map();
            this.config = {};
            this.isReady = false;
            this.eventListeners = new Map();
            this.readyCallbacks = [];
        }

        /**
         * Register a module with the TreeHouse framework
         *
         * @param {string} name Module name
         * @param {Object} module Module definition
         */
        register(name, module) {
            if (this.modules.has(name)) {
                console.warn(`TreeHouse: Module '${name}' is already registered`);
                return this;
            }

            // Validate module structure
            if (!module.init || typeof module.init !== 'function') {
                throw new Error(`TreeHouse: Module '${name}' must have an init() method`);
            }

            module.name = name;
            module.dependencies = module.dependencies || [];
            module.loaded = false;
            
            this.modules.set(name, module);
            
            console.log(`TreeHouse: Module '${name}' registered`);
            return this;
        }

        /**
         * Load and initialize a module
         *
         * @param {string} name Module name
         * @returns {Promise} Module initialization promise
         */
        async use(name) {
            if (!this.modules.has(name)) {
                throw new Error(`TreeHouse: Module '${name}' is not registered`);
            }

            const module = this.modules.get(name);
            
            if (module.loaded) {
                return module;
            }

            // Load dependencies first
            for (const dependency of module.dependencies) {
                await this.use(dependency);
            }

            try {
                console.log(`TreeHouse: Loading module '${name}'`);
                await module.init.call(module, this.config);
                module.loaded = true;
                
                this.emit('module:loaded', { name, module });
                console.log(`TreeHouse: Module '${name}' loaded successfully`);
                
                return module;
            } catch (error) {
                console.error(`TreeHouse: Failed to load module '${name}':`, error);
                throw error;
            }
        }

        /**
         * Configure the TreeHouse framework
         *
         * @param {Object} options Configuration options
         */
        configure(options) {
            this.config = { ...this.config, ...options };
            this.emit('config:updated', this.config);
            return this;
        }

        /**
         * Add event listener
         *
         * @param {string} event Event name
         * @param {Function} callback Event callback
         */
        on(event, callback) {
            if (!this.eventListeners.has(event)) {
                this.eventListeners.set(event, []);
            }
            this.eventListeners.get(event).push(callback);
            return this;
        }

        /**
         * Remove event listener
         *
         * @param {string} event Event name
         * @param {Function} callback Event callback to remove
         */
        off(event, callback) {
            if (!this.eventListeners.has(event)) {
                return this;
            }
            
            const listeners = this.eventListeners.get(event);
            const index = listeners.indexOf(callback);
            if (index > -1) {
                listeners.splice(index, 1);
            }
            
            return this;
        }

        /**
         * Emit event
         *
         * @param {string} event Event name
         * @param {*} data Event data
         */
        emit(event, data) {
            if (!this.eventListeners.has(event)) {
                return this;
            }
            
            const listeners = this.eventListeners.get(event);
            listeners.forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`TreeHouse: Error in event listener for '${event}':`, error);
                }
            });
            
            return this;
        }

        /**
         * Execute callback when TreeHouse is ready
         *
         * @param {Function} callback Callback to execute when ready
         */
        ready(callback) {
            if (this.isReady) {
                callback();
            } else {
                this.readyCallbacks.push(callback);
            }
            return this;
        }

        /**
         * Mark TreeHouse as ready and execute callbacks
         */
        markReady() {
            if (this.isReady) {
                return this;
            }
            
            this.isReady = true;
            console.log('TreeHouse: Framework ready');
            
            this.readyCallbacks.forEach(callback => {
                try {
                    callback();
                } catch (error) {
                    console.error('TreeHouse: Error in ready callback:', error);
                }
            });
            
            this.readyCallbacks = [];
            this.emit('ready');
            
            return this;
        }

        /**
         * Get registered module
         *
         * @param {string} name Module name
         * @returns {Object|null} Module or null if not found
         */
        getModule(name) {
            return this.modules.get(name) || null;
        }

        /**
         * Check if module is loaded
         *
         * @param {string} name Module name
         * @returns {boolean} True if module is loaded
         */
        isModuleLoaded(name) {
            const module = this.modules.get(name);
            return module ? module.loaded : false;
        }

        /**
         * Get all registered module names
         *
         * @returns {Array} Array of module names
         */
        getModuleNames() {
            return Array.from(this.modules.keys());
        }

        /**
         * Utility method for DOM ready
         *
         * @param {Function} callback Callback to execute when DOM is ready
         */
        static domReady(callback) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
            } else {
                callback();
            }
        }

        /**
         * Utility method for deep object merging
         *
         * @param {Object} target Target object
         * @param {Object} source Source object
         * @returns {Object} Merged object
         */
        static merge(target, source) {
            const result = { ...target };
            
            for (const key in source) {
                if (source.hasOwnProperty(key)) {
                    if (typeof source[key] === 'object' && source[key] !== null && !Array.isArray(source[key])) {
                        result[key] = TreeHouseFramework.merge(result[key] || {}, source[key]);
                    } else {
                        result[key] = source[key];
                    }
                }
            }
            
            return result;
        }
    }

    // Create TreeHouse instance
    const instance = new TreeHouseFramework();

    // Auto-initialize when DOM is ready
    TreeHouseFramework.domReady(() => {
        instance.markReady();
    });

    // Return the instance as the global TreeHouse object
    return instance;
})();

// Make TreeHouse immediately available globally when imported as ES module
if (typeof window !== 'undefined') {
    window.TreeHouse = TreeHouse;
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TreeHouse;
}