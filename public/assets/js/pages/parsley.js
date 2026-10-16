$.extend( window.Parsley.options, {
    focus: "first",
    excluded: "input[type=button], input[type=submit], input[type=reset], .search, .ignore",
    triggerAfterFailure: "change blur",
    errorsContainer: function ( element ) {},
    trigger: "change",
    successClass: "is-valid",
    errorClass: "is-invalid",
    classHandler: function ( el ) {
        return el.$element.closest( ".form-group" )
    },
    errorsContainer: function ( el ) {
        return el.$element.closest( ".form-group" )
    },
    errorsWrapper: '<div class="parsley-error"></div>',
    errorTemplate: "<span></span>",
} )

// Helper function to get translation safely
function getTrans(key, fallback = key) {
    return window.trans && window.trans[key] ? window.trans[key] : fallback;
}

Parsley.on( "field:validated", function ( el ) {
    var elNode = $( el )[ 0 ]
    if ( elNode && !elNode.isValid() ) {
        var rqeuiredValResult = elNode.validationResult.filter( function ( vr ) {
            return vr.assert.name === "required"
        } )
        if ( rqeuiredValResult.length > 0 ) {
            var fieldNode = $( elNode.element )
            var formGroupNode = fieldNode.closest( ".form-group" )
            var lblNode = formGroupNode.find( ".form-label:first" )
            if ( lblNode.length > 0 ) {
                // change default error message to include field label with translation
                var errorNode = formGroupNode.find(
                    "div.parsley-error span[class*=parsley-]"
                )
                if ( errorNode.length > 0 ) {
                    var lblText = lblNode.text()
                    if ( lblText ) {
                        // Use translation for "is required" message
                        var isRequiredText = getTrans('is required', 'is required');
                        errorNode.html( lblText + " " + isRequiredText + "." )
                    }
                }
            }
        }
    }
} )

Parsley.addValidator( "restrictedCity", {
    requirementType: "string",
    validateString: function ( value, requirement ) {
        value = ( value || "" ).trim()
        return value === "" || value.toLowerCase() === requirement.toLowerCase()
    },
    messages: {
        en: 'You have to live in <a href="https://www.google.com/maps/place/Jakarta">Jakarta</a>.',
    },
} )



//has uppercase
Parsley.addValidator( 'uppercase', {
    requirementType: 'number',
    validateString: function ( value, requirement ) {
        var uppercases = value.match( /[A-Z]/g ) || [];
        return uppercases.length >= requirement;
    },
    messages: {
        en: 'Your password must contain at least (%s) uppercase letter.' + '<br>'
    }
} );

//has lowercase
Parsley.addValidator( 'lowercase', {
    requirementType: 'number',
    validateString: function ( value, requirement ) {
        var lowecases = value.match( /[a-z]/g ) || [];
        return lowecases.length >= requirement;
    },
    messages: {
        en: 'Your password must contain at least (%s) lowercase letter.' + '<br>'
    }
} );

//has number
Parsley.addValidator( 'number', {
    requirementType: 'number',
    validateString: function ( value, requirement ) {
        var numbers = value.match( /[0-9]/g ) || [];
        return numbers.length >= requirement;
    },
    messages: {
        en: 'Your password must contain at least (%s) number.' + '<br>'
    }
} );

//has special char
Parsley.addValidator( 'special', {
    requirementType: 'number',
    validateString: function ( value, requirement ) {
        var specials = value.match( /[^a-zA-Z0-9]/g ) || [];
        return specials.length >= requirement;
    },
    messages: {
        en: 'Your password must contain at least (%s) special characters.' + '<br>'
    }
} );




Parsley.addValidator( 'minSelect', function ( value, requirement ) {
        return value.split( ',' ).length >= parseInt( requirement, 10 );
    }, 32 )
    .addMessage( 'en', 'minSelect', 'You must select at least %s.' );

// Add translations for Parsley messages
$(document).ready(function() {
    // Wait for translations to load
    var attempts = 0;
    var maxAttempts = 100;
    
    var initParsleyTranslations = function() {
        attempts++;
        
        if (typeof window.trans !== 'undefined') {
            // Override Parsley's default messages with translations
            Parsley.addMessages('en', {
                defaultMessage: getTrans('This value seems to be invalid', 'This value seems to be invalid'),
                type: {
                    email: getTrans('This value should be a valid email', 'This value should be a valid email'),
                    url: getTrans('This value should be a valid url', 'This value should be a valid url'),
                    number: getTrans('This value should be a valid number', 'This value should be a valid number'),
                    integer: getTrans('This value should be a valid integer', 'This value should be a valid integer'),
                    digits: getTrans('This value should be digits', 'This value should be digits'),
                    alphanum: getTrans('This value should be alphanumeric', 'This value should be alphanumeric')
                },
                required: getTrans('This value is required', 'This value is required'),
                pattern: getTrans('This value seems to be invalid', 'This value seems to be invalid'),
                min: getTrans('This value should be greater than or equal to %s', 'This value should be greater than or equal to %s'),
                max: getTrans('This value should be lower than or equal to %s', 'This value should be lower than or equal to %s'),
                range: getTrans('This value should be between %s and %s', 'This value should be between %s and %s'),
                minlength: getTrans('This value is too short. It should have %s characters or more', 'This value is too short. It should have %s characters or more'),
                maxlength: getTrans('This value is too long. It should have %s characters or fewer', 'This value is too long. It should have %s characters or fewer'),
                length: getTrans('This value length is invalid. It should be between %s and %s characters long', 'This value length is invalid. It should be between %s and %s characters long'),
                mincheck: getTrans('You must select at least %s choices', 'You must select at least %s choices'),
                maxcheck: getTrans('You must select %s choices or fewer', 'You must select %s choices or fewer'),
                check: getTrans('You must select between %s and %s choices', 'You must select between %s and %s choices'),
                equalto: getTrans('This value should be the same', 'This value should be the same')
            });
        } else if (attempts < maxAttempts) {
            setTimeout(initParsleyTranslations, 50);
        }
    };
    
    initParsleyTranslations();
});
