// FilePond Localization for ebroker-admin
// This file handles dynamic localization of FilePond file upload components

function initFilePondLocalization() {
    // Wait for translations to be loaded
    if (typeof window.trans === 'undefined' || typeof FilePond === 'undefined') {
        setTimeout(initFilePondLocalization, 50);
        return;
    }
    
    var currentLang = window.currentLocale || 'en';
    
    // Helper function to get translation
    function getTrans(key) {
        return window.trans && window.trans[key] ? window.trans[key] : key;
    }
    
    // Create localized options
    var localizedOptions = {
        labelIdle: getTrans('Drag & Drop your files or Browse'),
        labelInvalidField: getTrans('Field contains invalid files'),
        labelFileWaitingForSize: getTrans('Waiting for size'),
        labelFileSizeNotAvailable: getTrans('Size not available'),
        labelFileCountSingular: getTrans('file in list'),
        labelFileCountPlural: getTrans('files in list'),
        labelFileLoading: getTrans('Loading'),
        labelFileAdded: getTrans('Added'),
        labelFileLoadError: getTrans('Error during load'),
        labelFileRemoved: getTrans('Removed'),
        labelFileRemoveError: getTrans('Error during remove'),
        labelFileProcessing: getTrans('Uploading'),
        labelFileProcessingComplete: getTrans('Upload complete'),
        labelFileProcessingAborted: getTrans('Upload cancelled'),
        labelFileProcessingError: getTrans('Error during upload'),
        labelFileProcessingRevertError: getTrans('Error during revert'),
        labelTapToCancel: getTrans('tap to cancel'),
        labelTapToRetry: getTrans('tap to retry'),
        labelTapToUndo: getTrans('tap to undo'),
        labelButtonRemoveItem: getTrans('Remove'),
        labelButtonAbortItemLoad: getTrans('Abort'),
        labelButtonRetryItemLoad: getTrans('Retry'),
        labelButtonAbortItemProcessing: getTrans('Cancel'),
        labelButtonUndoItemProcessing: getTrans('Undo'),
        labelButtonRetryItemProcessing: getTrans('Retry'),
        labelButtonProcessItem: getTrans('Upload'),
        labelMaxFileSizeExceeded: getTrans('File is too large'),
        labelMaxFileSize: getTrans('Maximum file size is') + ' {filesize}',
        labelFileTypeNotAllowed: getTrans('File of invalid type'),
        fileValidateTypeLabelExpectedTypes: getTrans('Expects') + ' {allButLastType} ' + getTrans('or') + ' {lastType}',
        labelFileSizeBytes: getTrans('bytes'),
        labelFileSizeKilobytes: getTrans('KB'),
        labelFileSizeMegabytes: getTrans('MB'),
        labelFileSizeGigabytes: getTrans('GB')
    };
    
    // Set default options for FilePond
    FilePond.setOptions(localizedOptions);
    
    return localizedOptions;
}

// Function to update existing FilePond instances
function updateExistingFilePondInstances(options) {
    // Update existing .filepond instances
    $('.filepond').each(function() {
        var pond = FilePond.find(this);
        if (pond) {
            // Update each pond with new options
            Object.keys(options).forEach(function(key) {
                pond.setOptions({[key]: options[key]});
            });
        }
    });
    
    // Update existing .doc-filepond instances  
    $('.doc-filepond').each(function() {
        var pond = FilePond.find(this);
        if (pond) {
            // Update each pond with new options
            Object.keys(options).forEach(function(key) {
                pond.setOptions({[key]: options[key]});
            });
        }
    });
}

// Initialize when document is ready
$(document).ready(function() {
    // Wait a bit for translations to load
    setTimeout(function() {
        var options = initFilePondLocalization();
        
        // Update existing instances after a short delay
        setTimeout(function() {
            updateExistingFilePondInstances(options);
        }, 100);
    }, 1000);
});

// Handle language changes
$(document).on('languageChanged', function(e, newLang) {
    window.currentLocale = newLang;
    var options = initFilePondLocalization();
    updateExistingFilePondInstances(options);
}); 