/**
 * Channel Code Management
 * 
 * Provides functionality for the channel code display and customization screen
 */

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const prefixInput = document.getElementById('custom_prefix');
    const randomizeBtn = document.getElementById('generate-random');
    const suffixSpan = document.querySelector('.channel-code-suffix');
    const channelCodeDisplay = document.querySelector('.channel-code-preview');
    
    // Characters allowed in channel code prefix
    const validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
    // Generate random prefix
    function generateRandomPrefix() {
        let result = '';
        for (let i = 0; i < 4; i++) {
            result += validChars.charAt(Math.floor(Math.random() * validChars.length));
        }
        return result;
    }
    
    // Update channel code display
    function updateChannelCodeDisplay() {
        if (channelCodeDisplay) {
            const prefix = prefixInput.value || 'XXXX';
            const suffix = suffixSpan ? suffixSpan.textContent : '';
            channelCodeDisplay.textContent = prefix + suffix;
        }
    }
    
    // Handle random generation
    if (randomizeBtn) {
        randomizeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (prefixInput) {
                const randomPrefix = generateRandomPrefix();
                prefixInput.value = randomPrefix;
                updateChannelCodeDisplay();
                
                // Add animation effect
                prefixInput.classList.add('animate-pulse');
                setTimeout(() => {
                    prefixInput.classList.remove('animate-pulse');
                }, 1000);
            }
        });
    }
    
    // Handle input validation and formatting
    if (prefixInput) {
        prefixInput.addEventListener('input', function() {
            // Force uppercase
            this.value = this.value.toUpperCase();
            
            // Remove invalid characters
            this.value = this.value.split('')
                .filter(char => validChars.includes(char))
                .join('');
            
            // Limit to 4 characters
            if (this.value.length > 4) {
                this.value = this.value.substring(0, 4);
            }
            
            // Update display
            updateChannelCodeDisplay();
        });
        
        // Validate on blur
        prefixInput.addEventListener('blur', function() {
            if (this.value.length < 4) {
                // Pad with X if needed
                while (this.value.length < 4) {
                    this.value += 'X';
                }
            }
            
            updateChannelCodeDisplay();
        });
    }
    
    // Initialize
    updateChannelCodeDisplay();
});