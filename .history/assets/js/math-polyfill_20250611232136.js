// Math functions polyfill for older browsers
(function() {
    'use strict';
    
    // Math.trunc polyfill
    if (!Math.trunc) {
        Math.trunc = function(v) {
            return v < 0 ? Math.ceil(v) : Math.floor(v);
        };
    }
    
    // Math.sign polyfill
    if (!Math.sign) {
        Math.sign = function(x) {
            return ((x > 0) - (x < 0)) || +x;
        };
    }
    
    // Math.log2 polyfill
    if (!Math.log2) {
        Math.log2 = function(x) {
            return Math.log(x) * Math.LOG2E;
        };
    }
    
    // Math.log10 polyfill
    if (!Math.log10) {
        Math.log10 = function(x) {
            return Math.log(x) * Math.LOG10E;
        };
    }
    
    // Math.sinh polyfill
    if (!Math.sinh) {
        Math.sinh = function(x) {
            return (Math.exp(x) - Math.exp(-x)) / 2;
        };
    }
    
    // Math.cosh polyfill
    if (!Math.cosh) {
        Math.cosh = function(x) {
            return (Math.exp(x) + Math.exp(-x)) / 2;
        };
    }
    
    // Math.tanh polyfill
    if (!Math.tanh) {
        Math.tanh = function(x) {
            if (x === Infinity) return 1;
            if (x === -Infinity) return -1;
            return (Math.exp(x) - Math.exp(-x)) / (Math.exp(x) + Math.exp(-x));
        };
    }
    
    // Math.asinh polyfill
    if (!Math.asinh) {
        Math.asinh = function(x) {
            if (x === -Infinity) return x;
            return Math.log(x + Math.sqrt(x * x + 1));
        };
    }
    
    // Math.acosh polyfill
    if (!Math.acosh) {
        Math.acosh = function(x) {
            return Math.log(x + Math.sqrt(x * x - 1));
        };
    }
    
    // Math.atanh polyfill
    if (!Math.atanh) {
        Math.atanh = function(x) {
            return Math.log((1 + x) / (1 - x)) / 2;
        };
    }
    
    // Math.hypot polyfill
    if (!Math.hypot) {
        Math.hypot = function() {
            var y = 0, i = arguments.length;
            while (i--) y += arguments[i] * arguments[i];
            return Math.sqrt(y);
        };
    }
    
    console.log('Math polyfills loaded');
})();