/**
 * JavaScript Encryption Helper
 * Compatible with PHP Encrypt helper class
 * Requires crypto-js library
 */

class EncryptHelper {
    /**
     * Default constants (must match PHP implementation)
     */
    static DEFAULT_SALT = 'revive_business_2024';

    /**
     * Encrypt data (supports both arrays/objects and strings)
     *
     * @param {*} data - Data to encrypt (string, array, or object)
     * @param {string|null} salt - Salt for encryption
     * @returns {string} Base64 encoded encrypted data
     */
    static encrypt(data, salt = null) {
        try {
            // Use default salt if not provided
            salt = salt || this.DEFAULT_SALT;

            // Convert array/object to JSON string if needed
            if (typeof data === 'object' && data !== null) {
                data = JSON.stringify(data);
            }

            // Ensure data is string
            if (typeof data !== 'string') {
                data = String(data);
            }

            // Generate a random IV for each encryption (16 bytes)
            const iv = CryptoJS.lib.WordArray.random(16);

            // Create key from salt with key stretching (more secure)
            const key = CryptoJS.PBKDF2(salt, 'revive_static_salt_2024', {
                keySize: 256/32,
                iterations: 10000,
                hasher: CryptoJS.algo.SHA256
            });

            // Encrypt the data using AES-256-CBC
            const encrypted = CryptoJS.AES.encrypt(data, key, {
                iv: iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            });

            // Combine IV and encrypted data, then base64 encode
            const combined = iv.concat(encrypted.ciphertext);
            const result = CryptoJS.enc.Base64.stringify(combined);

            return result;

        } catch (error) {
            throw new Error('Encryption error: ' + error.message);
        }
    }

    /**
     * Decrypt data and return original format
     *
     * @param {string} encryptedData - Base64 encoded encrypted data
     * @param {string|null} salt - Salt for decryption
     * @param {boolean} returnObject - Whether to attempt JSON parse to object
     * @returns {*} Decrypted data (string, array, or object)
     */
    static decrypt(encryptedData, salt = null, returnObject = false) {
        try {
            // Use default salt if not provided
            salt = salt || this.DEFAULT_SALT;

            // Decode base64
            const data = CryptoJS.enc.Base64.parse(encryptedData);

            // Extract IV (first 16 bytes) and encrypted data
            if (data.sigBytes < 16) {
                throw new Error('Invalid encrypted data format');
            }

            const iv = CryptoJS.lib.WordArray.create(data.words.slice(0, 4), 16);
            const encrypted = CryptoJS.lib.WordArray.create(
                data.words.slice(4),
                data.sigBytes - 16
            );

            // Create key from salt with key stretching (more secure)
            const key = CryptoJS.PBKDF2(salt, 'revive_static_salt_2024', {
                keySize: 256/32,
                iterations: 10000,
                hasher: CryptoJS.algo.SHA256
            });

            // Decrypt the data
            const decrypted = CryptoJS.AES.decrypt(
                { ciphertext: encrypted },
                key,
                {
                    iv: iv,
                    mode: CryptoJS.mode.CBC,
                    padding: CryptoJS.pad.Pkcs7
                }
            );

            // Convert to string
            const decryptedString = decrypted.toString(CryptoJS.enc.Utf8);

            if (!decryptedString) {
                throw new Error('Decryption failed');
            }

            // Try to parse as JSON if requested or if it looks like JSON
            if (returnObject || this.isJson(decryptedString)) {
                try {
                    return JSON.parse(decryptedString);
                } catch (e) {
                    // If JSON parsing fails, return as string
                }
            }

            return decryptedString;

        } catch (error) {
            throw new Error('Decryption error: ' + error.message);
        }
    }

    /**
     * Check if string is valid JSON
     *
     * @param {string} str
     * @returns {boolean}
     */
    static isJson(str) {
        try {
            JSON.parse(str);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Generate a secure random salt
     *
     * @param {number} length - Length in characters (default: 32)
     * @returns {string}
     */
    static generateSalt(length = 32) {
        return CryptoJS.lib.WordArray.random(length / 2).toString();
    }

    /**
     * Generate a secure random password
     *
     * @param {number} length - Length in characters (default: 32)
     * @returns {string}
     */
    static generatePassword(length = 32) {
        return CryptoJS.lib.WordArray.random(length / 2).toString();
    }
}

// Example usage:
/*
// Include crypto-js library first:
// <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>

// Encrypt string with default salt
const encrypted = EncryptHelper.encrypt("Hello World");
console.log("Encrypted:", encrypted);

// Decrypt string
const decrypted = EncryptHelper.decrypt(encrypted);
console.log("Decrypted:", decrypted);

// Encrypt array/object
const data = { name: "John", age: 30, items: ["item1", "item2"] };
const encryptedObj = EncryptHelper.encrypt(data);
console.log("Encrypted Object:", encryptedObj);

// Decrypt to object
const decryptedObj = EncryptHelper.decrypt(encryptedObj, null, true);
console.log("Decrypted Object:", decryptedObj);

// With custom salt
const customEncrypted = EncryptHelper.encrypt("Secret data", "my_custom_salt");
const customDecrypted = EncryptHelper.decrypt(customEncrypted, "my_custom_salt");
console.log("Custom encrypted:", customEncrypted);
console.log("Custom decrypted:", customDecrypted);
*/

// Export for Node.js if available
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EncryptHelper;
}
