// Parsley Localization for ebroker-admin
// This file handles dynamic localization of Parsley validation messages

function initParsleyLocalization() {
    // Wait for translations to be loaded
    if (typeof window.trans === 'undefined' || typeof Parsley === 'undefined') {
        setTimeout(initParsleyLocalization, 50);
        return;
    }
    
    var currentLang = window.currentLocale || 'en';
    
    // Helper function to get translation
    function getTrans(key, fallback = key) {
        return window.trans && window.trans[key] ? window.trans[key] : fallback;
    }
    
    // Override Parsley's default messages
    Parsley.addMessages(currentLang, {
        defaultMessage: getTrans('This field is invalid'),
        type: {
            email: getTrans('Please enter a valid email address'),
            url: getTrans('Please enter a valid URL'),
            number: getTrans('Please enter a valid number'),
            integer: getTrans('Please enter a valid integer'),
            digits: getTrans('Please enter only digits'),
            alphanum: getTrans('Please enter alphanumeric characters only')
        },
        required: getTrans('This field is required'),
        pattern: getTrans('This field is invalid'),
        min: getTrans('This value should be greater than or equal to %s'),
        max: getTrans('This value should be lower than or equal to %s'),
        range: getTrans('This value should be between %s and %s'),
        minlength: getTrans('This field is too short (minimum %s characters)'),
        maxlength: getTrans('This field is too long (maximum %s characters)'),
        length: getTrans('This field should be between %s and %s characters'),
        mincheck: getTrans('Please select at least %s options'),
        maxcheck: getTrans('Please select no more than %s options'),
        check: getTrans('Please select between %s and %s options'),
        equalto: getTrans('This field should match the previous field')
    });
    
    // Custom validation for your field labels
    Parsley.on('field:validated', function(fieldInstance) {
        var $field = fieldInstance.$element;
        var $formGroup = $field.closest('.form-group');
        var $label = $formGroup.find('.form-label:first');
        
        if (!fieldInstance.isValid() && $label.length > 0) {
            var labelText = $label.text().replace('*', '').trim();
            var requiredError = fieldInstance.validationResult.find(function(result) {
                return result.assert.name === 'required';
            });
            
            if (requiredError && labelText) {
                var $errorContainer = $formGroup.find('.parsley-error span');
                if ($errorContainer.length > 0) {
                    var isRequiredText = getTrans('is required');
                    $errorContainer.html(labelText + ' ' + isRequiredText);
                }
            }
        }
    });
}

// Initialize when DOM is ready
$(document).ready(function() {
    initParsleyLocalization();
}); 