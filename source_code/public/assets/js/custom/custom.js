// @ts-nocheck

/**
 * Global HTML Sanitization Function
 * Prevents XSS attacks while preserving safe HTML formatting
 * Uses DOMPurify library loaded in footer_script.blade.php
 * 
 * @param {string} html - The HTML content to sanitize
 * @returns {string} - Sanitized HTML safe for display
 */
window.sanitizeHtml = function(html) {
    if (!html) return '';
    
    // Check if DOMPurify is loaded
    if (typeof DOMPurify === 'undefined') {
        console.warn('DOMPurify not loaded, falling back to text-only escaping');
        // Fallback: escape HTML if DOMPurify is not available
        const div = document.createElement('div');
        div.textContent = html;
        return div.innerHTML;
    }
    
    // DOMPurify will remove dangerous elements/attributes but keep safe HTML formatting
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'u', 'p', 'br', 'ul', 'ol', 'li', 'span', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'blockquote', 'code', 'pre'],
        ALLOWED_ATTR: ['href', 'target', 'class', 'style'],
        ALLOW_DATA_ATTR: false,
        KEEP_CONTENT: true
    });
};

/**
 * Global Bootstrap Table Response Handler
 * Automatically sanitizes HTML content in all table cells to prevent XSS attacks
 * Excludes specific columns that need raw HTML (like action buttons)
 */
window.globalTableResponseHandler = function(res) {
    
    // If response is not in the expected format, return as-is
    if (!res || typeof res !== 'object') {
        return res;
    }
    
    // Get the rows array (handle both direct array and {rows: [...]} format)
    let rows = Array.isArray(res) ? res : (res.rows || []);
    
    if (!Array.isArray(rows) || rows.length === 0) {
        return res;
    }
    
    // Columns that should NOT be sanitized (they contain intentional HTML like buttons)
    const excludedColumns = [
        'operate',           // Action buttons
        'edit_status_url',   // URLs for status updates
        'action',            // Generic action column
        'actions'            // Generic actions column
    ];
    
    // Helper function to decode HTML entities
    function decodeHtmlEntities(text) {
        if (typeof text !== 'string') return text;
        
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }
    
    // Sanitize each row
    rows = rows.map(row => {
        if (!row || typeof row !== 'object') {
            return row;
        }
        
        const sanitizedRow = {};
        
        for (const [key, value] of Object.entries(row)) {
            // Skip excluded columns
            if (excludedColumns.includes(key)) {
                sanitizedRow[key] = value;
                continue;
            }
            
            // Only sanitize string values that might contain HTML
            if (typeof value === 'string' && value.length > 0) {
                // Check if the value contains HTML tags (either raw or escaped)
                if (/<[^>]+>/.test(value) || /&lt;[^&]+&gt;/.test(value)) {
                    // First decode HTML entities (in case backend escaped them)
                    const decoded = decodeHtmlEntities(value);
                    // Then sanitize to remove XSS while keeping safe HTML
                    sanitizedRow[key] = sanitizeHtml(decoded);
                } else {
                    sanitizedRow[key] = value;
                }
            } else {
                sanitizedRow[key] = value;
            }
        }
        
        return sanitizedRow;
    });
    
    // Return in the same format as received
    if (Array.isArray(res)) {
        return rows;
    } else {
        return {
            ...res,
            rows: rows
        };
    }
};

$(document).ready(function () {


    $('#table_list').on('load-success.bs.table', function () {
        setTimeout(() => {
            const embeds = document.querySelectorAll('.svg-img');



            embeds.forEach(function (embed) {
                const svgDoc = embed.getSVGDocument();

                if (svgDoc) {
                    const svgElements = svgDoc.querySelectorAll('path');

                    svgElements.forEach(function (svgElement) {
                        svgElement.setAttribute('fill', fillColor);

                    });
                }
            });
        }, 2000);
    });
    setTimeout(() => {
        const embeds = document.querySelectorAll('.svg-img');



        embeds.forEach(function (embed) {
            const svgDoc = embed.getSVGDocument();

            if (svgDoc) {
                const svgElements = svgDoc.querySelectorAll('path');

                svgElements.forEach(function (svgElement) {
                    svgElement.setAttribute('fill', fillColor);

                });
            }
        });
    }, 2000);
});
$(document).ready(function () {

    /// START :: ACTIVE MENU CODE
    $(".menu a").each(function () {
        var pageUrl = window.location.href.split(/[?#]/)[0];

        if (this.href == pageUrl) {
            $(this).parent().parent().addClass("active");
            $(this).parent().addClass("active"); // add active to li of the current link
            $(this).parent().parent().prev().addClass("active"); // add active class to an anchor
            $(this).parent().parent().parent().addClass("active"); // add active class to an anchor
            $(this).parent().parent().parent().parent().addClass("active"); // add active class to an anchor
        }

        var subURL = $("a#subURL").attr("href");
        if (subURL != 'undefined') {
            if (this.href == subURL) {

                $(this).parent().addClass("active"); // add active to li of the current link
                $(this).parent().parent().addClass("active");
                $(this).parent().parent().prev().addClass("active"); // add active class to an anchor

                $(this).parent().parent().parent().addClass("active"); // add active class to an anchor

            }
        }
    });
    /// END :: ACTIVE MENU CODE

});

$(document).ready(function () {

    $('.select2-selection__clear').hide();


});





/// START :: TinyMCE
document.addEventListener("DOMContentLoaded", () => {
    // Enhanced TinyMCE initialization with comprehensive RTL support
    function initializeTinyMCE() {
        // Check if language data is available
        const globalLangData = window.globalLanguageData || {};
        const currentLang = globalLangData.current || {};
        const allLanguages = globalLangData.all || {};

        // Detect RTL from current language or HTML dir attribute
        const isGlobalRTL = currentLang.rtl || document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';

        // Base configuration for all TinyMCE editors
        const baseConfig = {
            height: 400,
            menubar: true,
            plugins: [
                'advlist autolink lists link charmap print preview anchor textcolor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime table contextmenu paste code help wordcount',
                'directionality' // Essential for RTL support
            ],
            toolbar: 'insert | undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ltr rtl | removeformat | help',
            setup: function (editor) {
                // Standard functionality
                editor.on("change keyup", function (e) {
                    editor.save();
                    $(editor.getElement()).trigger('change');
                });

                // Determine RTL for this specific editor
                const elementId = editor.getElement().id;
                let isEditorRTL = isGlobalRTL; // Default to global setting

                // Check for translation fields with specific language IDs
                const translationMatch = elementId.match(/translation-description-(\d+)/);
                if (translationMatch && allLanguages[translationMatch[1]]) {
                    isEditorRTL = allLanguages[translationMatch[1]].rtl;
                }

                // Apply RTL settings if needed
                if (isEditorRTL) {
                    editor.on('init', function () {
                        const body = editor.getBody();
                        body.style.direction = 'rtl';
                        body.style.textAlign = 'right';
                        body.classList.add('rtl-content');

                        // Set RTL for any existing content
                        if (body.innerHTML && body.innerHTML.trim() !== '') {
                            // Wrap existing content in RTL div if not already wrapped
                            if (!body.innerHTML.includes('dir="rtl"')) {
                                body.innerHTML = '<div dir="rtl">' + body.innerHTML + '</div>';
                            }
                        }
                    });
                }
            }
        };

        // Function to create editor configuration based on RTL setting
        function createEditorConfig(selector, isRTL = false) {
            const config = { ...baseConfig, selector };

            if (isRTL) {
                config.directionality = 'rtl';
                config.content_style = `
                    body {
                        direction: rtl;
                        text-align: right;
                        font-family: Arial, sans-serif;
                    }
                    .rtl-content {
                        direction: rtl;
                        text-align: right;
                    }
                    p, div, span {
                        direction: rtl;
                        text-align: right;
                    }
                    ul, ol {
                        padding-right: 20px;
                        padding-left: 0;
                    }
                `;
                // Force RTL content for new content
                config.setup = function (editor) {
                    baseConfig.setup.call(this, editor);

                    editor.on('BeforeSetContent', function (e) {
                        if (e.content && !e.content.includes('dir=')) {
                            e.content = '<div dir="rtl">' + e.content + '</div>';
                        }
                    });
                };
            }

            return config;
        }

        // Initialize main editors (global RTL setting)
        const mainSelectors = [
            '#tinymce_editor',
            '.tinymce_editor:not([id*="translation-description-"])'
        ];

        mainSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            if (elements.length > 0) {
                const config = createEditorConfig(selector, isGlobalRTL);
                tinymce.init(config);
            }
        });

        // Initialize translation editors individually with language-specific RTL

        // If allLanguages is empty, try to find translation elements directly
        let languageIds = Object.keys(allLanguages);
        if (languageIds.length === 0) {
            const translationElements = document.querySelectorAll('[id^="translation-description-"]');
            languageIds = Array.from(translationElements).map(el => el.id.replace('translation-description-', ''));
        }

        languageIds.forEach(languageId => {
            const selector = `#translation-description-${languageId}`;
            const element = document.querySelector(selector);

            if (element) {
                const language = allLanguages[languageId] || { rtl: false };
                const config = createEditorConfig(selector, language.rtl);

                // Ensure content is properly set before initialization
                const initialContent = element.value || '';

                if (initialContent.trim() !== '') {
                    // Override the setup function to include content setting
                    const originalSetup = config.setup;
                    config.setup = function (editor) {
                        // Call original setup if it exists
                        if (originalSetup) {
                            originalSetup.call(this, editor);
                        }

                        // Set content after editor is ready
                        editor.on('init', function () {
                            editor.setContent(initialContent);
                        });
                    };
                }

                tinymce.init(config);
            }
        });

        // Handle any other TinyMCE editors that might be added dynamically
        const otherEditors = document.querySelectorAll('.tinymce_editor:not([data-tinymce-initialized])');
        otherEditors.forEach(element => {
            element.setAttribute('data-tinymce-initialized', 'true');
            const config = createEditorConfig(`#${element.id}`, isGlobalRTL);
            tinymce.init(config);
        });
    }

    // Initialize with a small delay to ensure DOM is ready
    setTimeout(initializeTinyMCE, 500);
});

// Global function to reinitialize TinyMCE if needed (for dynamic content)
window.reinitializeTinyMCE = function () {
    setTimeout(() => {
        const uninitializedEditors = document.querySelectorAll('.tinymce_editor:not([data-tinymce-initialized])');
        uninitializedEditors.forEach(element => {
            element.setAttribute('data-tinymce-initialized', 'true');
            const isRTL = window.globalLanguageData?.current?.rtl || false;
            const initialContent = element.value || '';

            const config = {
                selector: `#${element.id}`,
                height: 400,
                menubar: true,
                plugins: [
                    'advlist autolink lists link charmap print preview anchor textcolor',
                    'searchreplace visualblocks code fullscreen',
                    'insertdatetime table contextmenu paste code help wordcount',
                    'directionality'
                ],
                toolbar: 'insert | undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ltr rtl | removeformat | help',
                directionality: isRTL ? 'rtl' : 'ltr',
                content_style: isRTL ? `
                    body { direction: rtl; text-align: right; font-family: Arial, sans-serif; }
                    p, div, span { direction: rtl; text-align: right; }
                ` : '',
                setup: function (editor) {
                    editor.on("change keyup", function (e) {
                        editor.save();
                        $(editor.getElement()).trigger('change');
                    });

                    if (isRTL) {
                        editor.on('init', function () {
                            const body = editor.getBody();
                            body.style.direction = 'rtl';
                            body.style.textAlign = 'right';
                            body.classList.add('rtl-content');
                        });
                    }
                }
            };

            // Ensure content is properly set before initialization
            if (initialContent.trim() !== '') {
                // Override the setup function to include content setting
                const originalSetup = config.setup;
                config.setup = function (editor) {
                    // Call original setup if it exists
                    if (originalSetup) {
                        originalSetup.call(this, editor);
                    }

                    // Set content after editor is ready
                    editor.on('init', function () {
                        editor.setContent(initialContent);
                    });
                };
            }

            tinymce.init(config);
        });
    }, 100);
};

$('body').append('<div id="loader-container"><div class="loader"></div></div>');
$(window).on('load', function () {
    $('#loader-container').fadeOut('slow');
});

//magnific popup
$(document).on('click', '.image-popup-no-margins', function () {

    $(this).magnificPopup({
        type: 'image',
        closeOnContentClick: true,
        closeBtnInside: false,
        fixedContentPos: true,

        image: {
            verticalFit: true
        },
        zoom: {
            enabled: true,
            duration: 300 // don't foget to change the duration also in CSS
        }

    }).magnificPopup('open');
    return false;
});



setTimeout(function () {
    $(".error-msg").fadeOut(1500)
}, 5000);

$(document).ready(function () {
    $('.select2').select2({
        theme: 'bootstrap-5',
        placeholder: {
            id: '-1', // the value of the option
            text: 'Select an option'
        },
        allowClear: false


    });
});

function show_error() {
    Swal.fire({
        title: 'Modification not allowed',
        icon: 'error',
        showDenyButton: true,

        confirmButtonText: 'Yes',
        denyCanceButtonText: `No`,
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */

    })
}

function confirmationDelete(e) {
    var url = e.currentTarget.getAttribute('href'); //use currentTarget because the click may be on the nested i tag and not a tag causing the href to be empty
    $('#form-del').attr('action', url);
    Swal.fire({
       title: window.trans["Are you sure"],
        text: window.trans["You want to delete it ?"],
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: window.trans["Yes Delete"],
        cancelButtonText: window.trans['Cancel'],
        reverseButtons: true,
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            $("#form-del").submit();
        } else {
            $('#form-del').attr('action', '');
        }
    })
    return false;
}

function chk(checkbox, refreshPageAfterSuccess = false) {
    if (checkbox.checked) {
        active(event.target.id, 1, checkbox.getAttribute('data-url'), refreshPageAfterSuccess);
    } else {
        active(event.target.id, 0, checkbox.getAttribute('data-url'), refreshPageAfterSuccess);
    }
}


function active(id, value, url, refreshPageAfterSuccess = false) {

    $.ajax({
        url: url,
        type: "POST",
        data: {
            '_token': $('meta[name="csrf-token"]').attr('content'),
            "id": id,
            "status": value,
        },
        cache: false,
        success: function (result) {

            if (result.error == false) {
                Toastify({
                    text: result.message,
                    duration: 1500,
                    close: !0,
                    backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                }).showToast();
                $('#table_list').bootstrapTable('refresh');
                if (refreshPageAfterSuccess) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                Toastify({
                    text: result.message,
                    duration: 1500,
                    close: !0,
                    backgroundColor: '#dc3545'
                }).showToast();
                $('#table_list').bootstrapTable('refresh');
            }
        },
        error: function (error) {

        }
    });
}










$("#category").change(function () {
    $('#parameter_type').empty();
    $('#facility').show();

    $('.parsley-error filled,.parsley-required').attr("aria-hidden", "true");

    var parameter_types = $(this).find(':selected').data('parametertypes');

    parameter_data = $.parseJSON($('#parameter_count').val());
    data_arr = (parameter_types + ',').split(",");


    $.each(data_arr, function (key, value) {
        let param = parameter_data.filter(parameter => parameter.id == value);

        var a = "";
        if (param[0]) {
            let mandatory = param[0].is_required == 1 ? "mandatory" : "";
            let isRequired = param[0].is_required == 1 ? "required" : "";
            if (param[0].type_of_parameter == "radiobutton") {

                $('#parameter_type').append(
                    '<div class="form-group ' + mandatory + ' col-md-3 chk"id=' + param[0].id + '><label for="' + param[0].name + '" class="form-label col-12">' + param[0].name + '</label></div>'
                );
                $.each(param[0].type_values, function (k, v) {
                    if (v.translations) {
                        $('#' + param[0].id).append(
                            '<input name="par_' + param[0].id + '" type="radio" value="' + v.value + '" class="form-check-input ml-5" ' + isRequired + '/>' + '<span style="margin-left: 4px;margin-right: 8px;">' + v.value + '</span>'
                        );
                    } else {
                        $('#' + param[0].id).append(
                            '<input name="par_' + param[0].id + '" type="radio" value="' + v + '" class="form-check-input ml-5" ' + isRequired + '/>' + '<span style="margin-left: 4px;margin-right: 8px;">' + v + '</span>'
                        );
                    }
                });
            }
            if (param[0].type_of_parameter == "checkbox") {

                $('#parameter_type').append(
                    '<div class="form-group ' + mandatory + ' col-md-3 chk' + '"id=' + param[0].id + '><label for="' + param[0].name + '" class="form-label col-12">' + param[0].name + '</label></div>'
                );
                $.each(param[0].type_values, function (k, v) {
                    if (v.translations) {
                        $('#' + param[0].id).append(
                            '<input name="par_' + param[0].id + '[]" type="checkbox" value="' + v.value + '" class="form-check-input" ' + isRequired + '/>' + '<span style="margin-left: 4px;margin-right: 8px;">' + v.value + '</span>'
                        );
                    } else {
                        $('#' + param[0].id).append(
                            '<input name="par_' + param[0].id + '[]" type="checkbox" value="' + v + '" class="form-check-input" ' + isRequired + '/>' + '<span style="margin-left: 4px;margin-right: 8px;">' + v + '</span>'
                        );
                    }
                });
            }

            if (param[0].type_of_parameter == "dropdown") {
                $('#parameter_type').append('<div class="form-group ' + mandatory + ' col-md-3"><label for="' + param[0].name + '" class="form-label col-12 ">' + param[0].name + '</label>' + '<select id=' + param[0].id + ' name="par_' + param[0].id + '" class="select2 form-select ' + isRequired + ' form-control-sm" ><option value="">choose option</option></select></div>');
                arr = (param[0].type_values);
                $.each(arr,
                    function (key, val) {
                        if (val.translations) {
                            $('#' + param[0].id).append($(
                                '<option>', {
                                value: val.value,
                                text: val.value
                            }));
                        } else {
                            $('#' + param[0].id).append($(
                                '<option>', {
                                value: val,
                                text: val
                            }));
                        }
                    });
            }
            if (param[0].type_of_parameter == "textbox") {
                $('#parameter_type').append($(
                    '<div class="form-group col-md-3 ' + mandatory + '"><label for="' + param[0].name + '" class="form-label  col-12">' + param[0].name + '</label><input class="form-control" ' + isRequired + ' type="text" id="' + param[0].id + '" name="par_' + param[0].id + '"></div>'
                ));
            }
            if (param[0].type_of_parameter == "number") {
                $('#parameter_type').append($(
                    '<div class="form-group col-md-3 ' + mandatory + '"><label for="' + param[0].name + '" class="form-label  col-12">' + param[0].name + '</label><input class="form-control" ' + isRequired + ' type="number" id="' + param[0].id + '" name="par_' + param[0].id + '" min="1"></div>'
                ));
            }
            if (param[0].type_of_parameter == "textarea") {
                $('#parameter_type').append($(
                    '<div class="form-group col-md-3 ' + mandatory + '"><label for="' + param[0].name + '" class="form-label  col-12">' + param[0].name + '</label><textarea name = "par_' + param[0].id + '" id = "' + param[0].id + '" class= "form-control" ' + isRequired + ' cols = "40" rows = "3"></textarea ></div >'
                ));
            }
            if (param[0].type_of_parameter == "file") {
                $('#parameter_type').append($(
                    '<div class="form-group col-md-3 ' + mandatory + '"><label for="' + param[0].name + '" class="form-label  col-12">' + param[0].name + '</label><input class="form-control" ' + isRequired + ' type="file" id="' + param[0].id + '" name="par_' + param[0].id + '"></div>'
                ));

            }

        }
    });
});


function initMap() {
    var latitude = parseFloat($('#latitude').val());
    var longitude = parseFloat($('#longitude').val());
    var map = new google.maps.Map(document.getElementById('map'), {
        mapId: GOOGLE_MAP_ID, // Required for AdvancedMarkerElement
        center: {
            lat: latitude,
            lng: longitude
        },
        zoom: 13
    });
    var marker = new google.maps.marker.AdvancedMarkerElement({
        position: {
            lat: latitude,
            lng: longitude
        },
        map: map,
        gmpDraggable: true,
        title: 'Marker Title'
    });
    google.maps.event.addListener(marker, 'dragend', function (event) {
        var geocoder = new google.maps.Geocoder();
        geocoder.geocode({
            'latLng': event.latLng
        }, function (results, status) {
            if (status == google.maps.GeocoderStatus.OK) {
                if (results[0]) {
                    var address_components = results[0].address_components;
                    var city, state, country, full_address;

                    for (var i = 0; i < address_components.length; i++) {
                        var types = address_components[i].types;
                        if (types.indexOf('locality') != -1) {
                            city = address_components[i].long_name;
                        } else if (types.indexOf('administrative_area_level_1') != -1) {
                            state = address_components[i].long_name;
                        } else if (types.indexOf('country') != -1) {
                            country = address_components[i].long_name;
                        }
                    }

                    full_address = results[0].formatted_address;

                    // Do something with the city, state, country, and full address

                    $('#city').val(city);
                    $('#country').val(state);
                    $('#state').val(country);
                    $('#address').val(full_address);


                    $('#latitude').val(event.latLng.lat());
                    $('#longitude').val(event.latLng.lng());

                } else {
                    console.log('No results found');
                }
            } else {
                console.log('Geocoder failed due to: ' + status);
            }
        });
    });

    var input = document.getElementById('searchInput');
    map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

    var autocomplete = new google.maps.places.Autocomplete(input);
    autocomplete.bindTo('bounds', map);

    var infowindow = new google.maps.InfoWindow();
    var marker = new google.maps.marker.AdvancedMarkerElement({
        map: map
    });

    autocomplete.addListener('place_changed', function () {
        infowindow.close();
        marker.map = null;
        var place = autocomplete.getPlace();
        if (!place.geometry) {
            window.alert("Autocomplete's returned place contains no geometry");
            return;
        }

        // If the place has a geometry, then present it on a map.
        if (place.geometry.viewport) {
            map.fitBounds(place.geometry.viewport);
        } else {
            map.setCenter(place.geometry.location);
            map.setZoom(17);
        }
        // AdvancedMarkerElement uses PinElement for custom styling
        var pinElement = new google.maps.marker.PinElement({
            glyph: new URL(place.icon)
        });
        marker.content = pinElement.element;
        marker.position = place.geometry.location;

        var address = '';
        if (place.address_components) {
            address = [
                (place.address_components[0] && place.address_components[0].short_name || ''),
                (place.address_components[1] && place.address_components[1].short_name || ''),
                (place.address_components[2] && place.address_components[2].short_name || '')
            ].join(' ');
        }

        infowindow.setContent('<div><strong>' + place.name + '</strong><br>' + address);
        infowindow.open(map, marker);

        // Location details
        for (var i = 0; i < place.address_components.length; i++) {

            if (place.address_components[i].types[0] == 'locality') {
                $('#city').val(place.address_components[i].long_name);


            }
            if (place.address_components[i].types[0] == 'country') {
                $('#country').val(place.address_components[i].long_name);


            }
            if (place.address_components[i].types[0] == 'administrative_area_level_1') {
                $('#state').val(place.address_components[i].long_name);


            }
        }


        var latitude = place.geometry.location.lat();
        var longitude = place.geometry.location.lng();
        $('#address').val(place.formatted_address);


        $('#latitude').val(place.geometry.location.lat());
        $('#longitude').val(place.geometry.location.lng());
    });
}
$(document).ready(function () {

    FilePond.registerPlugin(FilePondPluginImagePreview, FilePondPluginFileValidateSize,
        FilePondPluginFileValidateType);

    $('.filepond').filepond({
        credits: null,
        allowFileSizeValidation: "true",
        maxFileSize: '25MB',
        allowFileTypeValidation: true,
        acceptedFileTypes: ['image/*'],
        storeAsFile: true,
        allowPdfPreview: true,
        pdfPreviewHeight: 320,
        pdfComponentExtraParams: 'toolbar=0&navpanes=0&scrollbar=0&view=fitH',
        allowVideoPreview: true, // default true
        allowAudioPreview: true // default true
    });

    $('.doc-filepond').filepond({
        credits: null,
        allowFileSizeValidation: "true",
        maxFileSize: '25MB',
        storeAsFile: true,
        allowPdfPreview: true,
        pdfPreviewHeight: 320,
        pdfComponentExtraParams: 'toolbar=0&navpanes=0&scrollbar=0&view=fitH',
        allowVideoPreview: true, // default true
        allowAudioPreview: true // default true
    });

    $('.filepond-all').filepond({
        credits: null,
        allowFileSizeValidation: "false",
        allowFileTypeValidation: true,
        acceptedFileTypes: [
            'application/zip',
            'application/x-zip-compressed',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/*',
            'text/*',
            'video/*',
            'audio/*'
        ],
        storeAsFile: true,
        allowPdfPreview: true,
    });
});
$(document).on('click', '.accordion-item-header', function (event) {
    if ($(this).hasClass('active')) {
        // Close the clicked accordion if it's already active
        $(this).removeClass('active');
        $(this).next('.accordion-item-body').css('max-height', 0);
    } else {
        var currentActive = $('.accordion-item-header.active');
        if (currentActive && currentActive !== $(this)) {
            currentActive.removeClass('active');
            currentActive.next('.accordion-item-body').css('max-height', 0);
        }

        $(this).toggleClass('active');
        var accordionBody = $(this).next('.accordion-item-body');
        if ($(this).hasClass('active')) {
            accordionBody.css('max-height', accordionBody.prop('scrollHeight') + 'px');
        } else {
            accordionBody.css('max-height', 0);
        }
    }
});


$('.type-field').on('change', function (e) {
    e.preventDefault();

    const inputValue = $(this).val();
    const optionSection = $('.default-values-section');

    // Show/hide the "default-values-section" based on the selected value using a switch statement
    switch (inputValue) {
        case 'dropdown':
        case 'radio':
        case 'checkbox':
            // Only make the main option input required, not the translation inputs
            optionSection.show(500).find('.option-input').attr('required', true);
            break;
        default:
            optionSection.hide(500).find('.option-input').removeAttr('required');
            break;
    }

});


// Repeater On Default Values section's Option Section
var defaultValuesRepeater = $('.default-values-section').repeater({
    show: function () {
        let optionNumber = parseInt($('.option-section:nth-last-child(2)').find('.option-input-number').text()) + 1;

        if (!optionNumber) {
            optionNumber = 1;
        }

        $(this).find('.option-number').text(optionNumber);

        $(this).slideDown();

        toggleAccessOfDeleteButtons();
        checkDuplicateFeatures('.option-input', '.add-new-option'); // Check duplicates after adding a new row

    },
    hide: function (deleteElement) {
        $(this).slideUp(deleteElement, function () {
            $('.remove-default-option').attr('disabled', true)
            $(this).remove();
            setTimeout(() => {
                toggleAccessOfDeleteButtons();
                checkDuplicateFeatures('.option-input', '.add-new-option'); // Check duplicates after adding a new row
            }, 500);
        });
    }
});

// Repeater On Edit Default Values section's Option Section
var editDefaultValuesRepeater = $('.edit-default-values-section').repeater({
    show: function () {
        let optionNumber = parseInt($('.edit-option-section:nth-last-child(2)').find('.edit-option-input-number').text()) + 1;

        if (!optionNumber) {
            optionNumber = 1;
        }
        $(this).find('.edit-option-input').attr('required', true);
        $(this).find('.edit-option-number').text(optionNumber);


        $(this).slideDown();

        toggleAccessOfDeleteEditButtons();
        checkDuplicateFeatures('.edit-option-input', '.add-new-edit-option'); // Check duplicates after adding a new row

    },
    hide: function (deleteElement) {
        let $this = $(this);
        Swal.fire({
            title: window.trans["Are you sure"],
            text: window.trans["You wants to delete this option ?"],
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: window.trans["Yes Delete"],
            cancelButtonText: window.trans["cancel"],
        }).then((result) => {
            if (result.isConfirmed) {
                $this.slideUp(deleteElement, function () {
                    $('.remove-edit-option').attr('disabled', true)
                    $(this).remove();
                    setTimeout(() => {
                        toggleAccessOfDeleteEditButtons();
                        checkDuplicateFeatures('.edit-option-input', '.add-new-edit-option'); // Check duplicates after adding a new row
                    }, 500);
                });
            }
        });
    }
});

$('.verify-customer-status-form').on('submit', function (e) {
    e.preventDefault();
    let formElement = $(this);
    let submitButtonElement = $(this).find('#btn_submit');
    let url = $(this).attr('action');
    let submitButtonText = submitButtonElement.val();
    submitButtonElement.val('Please Wait...').attr('disabled', true);
    let modalElement = formElement.closest('.modal');
    modalElement.find('.btn-close').attr('disabled', true);
    modalElement.find('.close-btn').attr('disabled', true);
    if (!formElement.parsley().isValid()) {
        submitButtonElement.val(submitButtonText).removeAttr('disabled');
        modalElement.find('.btn-close').removeAttr('disabled');
        modalElement.find('.close-btn').removeAttr('disabled');
        // If the form is not valid, trigger Parsley's validation messages
        formElement.parsley().validate();
    } else {
        setTimeout(() => {
            let data = new FormData(this);
            let preSubmitFunction = $(this).data('pre-submit-function');
            if (preSubmitFunction) {
                //If custom function name is set in the Form tag then call that function using eval
                eval(preSubmitFunction + "()");
            }
            let customSuccessFunction = $(this).data('success-function');
            // noinspection JSUnusedLocalSymbols
            function successCallback(response) {
                if (!$(formElement).hasClass('create-form-without-reset')) {
                    formElement[0].reset();
                }
                $('#table_list').bootstrapTable('refresh');
                $('#editModal').modal('hide');
                modalElement.find('.btn-close').removeAttr('disabled');
                modalElement.find('.close-btn').removeAttr('disabled');
                if (customSuccessFunction) {
                    //If custom function name is set in the Form tag then call that function using eval
                    eval(customSuccessFunction + "(response)");
                }

            }
            function errorCallback(response) {
                modalElement.find('.btn-close').removeAttr('disabled');
                modalElement.find('.close-btn').removeAttr('disabled');

                if (response && response.code === 403) {
                    $('#editModal').modal('hide');
                }
            }
            submitButtonElement.val(submitButtonText).attr('disabled', false);
            formAjaxRequest('POST', url, data, formElement, submitButtonElement, successCallback, errorCallback);

        }, 300);
    }

})

// Fill Currency Symbol in Symbol input based on country
$("#currency-code").on("change", function (e) {
    let value = $(this).val();
    if (value) {
        let url = $("#url-for-currency-symbol").val()

        axios.get(url, {
            params: {
                country_code: value
            }
        }).then(function (response) {
            if (response.data.error == false) {
                $("#currency-symbol").val(response.data.data)
            } else {
                console.log(response);
            }
        }).catch(function (error) {
            console.log(error);
        });
    }
})


// Repeater On Project's Floor Plans
let IdProjectFloorPlanCounter = 0;
var projectFloorPlanRepeater = $('.projects-floor-plans').repeater({
    initEmpty: true, // Ensure the first item is empty
    show: function () {
        let floorNumber = parseInt($('.floor-section:nth-last-child(2)').find('.floor-number').text()) + 1;

        if (!floorNumber) {
            floorNumber = 1;
        }

        let newFloorImageId = 'floor-image' + IdProjectFloorPlanCounter;
        let newFloorImagePreviewId = 'floor-image-preview-' + IdProjectFloorPlanCounter;
        let newFloorImageRequired = 'floor-image-required-' + IdProjectFloorPlanCounter;
        let newRemoveFloor = 'remove-floor-' + IdProjectFloorPlanCounter;
        $(this).find('.floor-number').text(floorNumber);
        $(this).find('.floor-image').attr('id', newFloorImageId)
        $(this).find('.floor-image-preview').attr('id', newFloorImagePreviewId)
        $(this).find('.floor-image-required').attr('id', newFloorImageRequired)
        $(this).find('.remove-floor').attr('id', newRemoveFloor);
        $(this).slideDown();
        IdProjectFloorPlanCounter++;
    },
    hide: function (deleteElement) {
        $this = $(this);
        if ($(this).find('.remove-floor').data('id')) {
            let url = $(this).find('.remove-floor').data('url');
            showDeletePopupModal(url, {
                successCallBack: function () {
                    $this.slideUp(deleteElement, function () {
                        $this.remove();
                    });
                }, errorCallBack: function (response) {
                }
            })
        } else {
            $this.slideUp(deleteElement, function () {
                $this.remove();
            });
        }
    }
});

$(document).on('click', '.block-user', function () {
    let $this = $(this);
    let url = $(this).data('url');
    showSweetAlertConfirmPopup(url, 'POST', {}, {
        successCallBack: function (response) {
            if (response.error == false) {
                window.location.reload(true);
            } else {
                $("#chat_form").show();
                $(".blocked-user-message-div").hide();
                $(".for-blocked-by-admin").hide();
                $(".for-blocked-by-user").hide();
                $this.hide();
                showErrorToast(response.message);
            }
        }, errorCallBack: function (response) {

        }
    })
});

$(document).on('click', '.unblock-user', function () {
    let blockUserElement = $(document).find('.block-user');
    let url = $(this).data('url');

    let options = {
        text: window.trans["You wants to unblock ?"],
    }
    showSweetAlertConfirmPopup(url, 'POST', options, {
        successCallBack: function (response) {
            if (response.error == false) {
                let data = response.data;
                if (data) {
                    window.location.reload(true);
                    // if(data.is_blocked_by_user == 1){
                    //     $("#chat_form").hide();
                    //     $(".blocked-user-message-div").show();
                    //     $(".for-blocked-by-admin").hide();
                    //     $(".for-blocked-by-user").show();
                    //     blockUserElement.show();
                    // }else{
                    //     $("#chat_form").show();
                    //     $(".blocked-user-message-div").hide();
                    //     $(".for-blocked-by-admin").hide();
                    //     $(".for-blocked-by-user").hide();
                    //     blockUserElement.show();
                    // }
                } else {

                }
            } else {
                $("#chat_form").hide();
                $(".blocked-user-message-div").show();
                blockUserElement.hide();
                showErrorToast(response.message);
            }
        }, errorCallBack: function (response) {

        }
    })

});


$(document).on("click", '.request-status', function () {
    if ($(this).val() == 'rejected') {
        $(".reject-reason-text-div").show();
    } else {
        $(".reject-reason-text-div").hide();
    }
});


// Repeater On Features section's of packages
let featureCounter = 1;
var packageFeatureRepeater = $('.feature-sections').repeater({
    initEmpty: true,
    show: function () {
        // Add ID in Unlimited Radio
        let newFeatureTypeUnlimitedId = 'feature-type-unlimited-' + featureCounter;
        $(this).find('.feature-type-unlimited').attr('id', newFeatureTypeUnlimitedId)
        $(this).find('.feature-type-unlimited-label').attr('for', newFeatureTypeUnlimitedId)

        // Add ID in Limited Radio
        let newFeatureTypeLimitedId = 'feature-type-limited-' + featureCounter;
        $(this).find('.feature-type-limited').attr('id', newFeatureTypeLimitedId)
        $(this).find('.feature-type-limited-label').attr('for', newFeatureTypeLimitedId)

        // Add ID in Limit Div
        let newLimitDiv = 'limit-div-' + featureCounter;
        $(this).find('.limit-div').attr('id', newLimitDiv)


        // Add ID in Limit text
        let newLimitText = 'limit' + featureCounter;
        $(this).find('.limit').attr('id', newLimitText)

        $(this).slideDown();
        featureCounter++;
        checkDuplicateFeatures('.features', '.add-new-feature'); // Check duplicates after adding a new row
    },
    hide: function (deleteElement) {
        $(this).slideUp(deleteElement, function () {
            $(this).remove();
            checkDuplicateFeatures('.features', '.add-new-feature'); // Check duplicates after adding a new row

        });
    }
});
// Initialize jQuery Repeater for Bank Details
const bankDetailsRepeater = $('.bank-details-repeater').repeater({
    // Set initEmpty to false so it doesn't clear existing items
    initEmpty: true,
    show: function () {
        $(this).slideDown();
    },
    hide: function (deleteElement) {
        Swal.fire({
            title: window.trans["Are you sure"],
            text: window.trans["You wants to change it ?"],
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: window.trans["Yes"],
            cancelButtonText: window.trans["No"],
        }).then((result) => {
            if (result.isConfirmed) {
                $(this).slideUp(deleteElement);
            }
        });
    }
});

$(".toggle-password").click(function () {
    $(this).toggleClass("bi bi-eye bi-eye-slash");
    var input = $(this).parent().parent().find('.user-password');
    console.log(input);
    if (input.attr("type") == "password") {
        input.attr("type", "text");
    } else {
        input.attr("type", "password");
    }
});

$(document).ready(function () {
    // Helper function to get original unescaped data from row (for action events)
    // Usage: getOriginalValue(row, 'category') or getOriginalValue(row, 'raw_category')
    window.getOriginalValue = function (row, key) {
        if (row && row._original && row._original.hasOwnProperty(key)) {
            return row._original[key];
        }
        // Fallback to row value if _original doesn't exist
        return row ? row[key] : null;
    };

    // Configure table with global sanitization to prevent XSS attacks
    // Uses globalTableResponseHandler defined earlier for HTML sanitization
    $('#table_list, #table_list1').bootstrapTable({
        escape: false, // Disable global escape to allow sanitized HTML
        responseHandler: globalTableResponseHandler, // Use global sanitization handler
        onLoadSuccess: function (data) {
            // After Bootstrap Table loads data, store original data for action events
            var $table = $(this);

            // Use setTimeout to ensure Bootstrap Table has fully processed the data
            setTimeout(function () {
                try {
                    var tableData = $table.bootstrapTable('getData');

                    // Ensure tableData is an array
                    if (!Array.isArray(tableData)) {
                        // Try alternative methods to get data
                        if (data && Array.isArray(data)) {
                            tableData = data;
                        } else if (data && data.rows && Array.isArray(data.rows)) {
                            tableData = data.rows;
                        } else {
                            // Try getting data from Bootstrap Table's options
                            var options = $table.bootstrapTable('getOptions');
                            if (options && options.data && Array.isArray(options.data)) {
                                tableData = options.data;
                            } else {
                                return; // Can't get data, exit
                            }
                        }
                    }

                    if (Array.isArray(tableData) && tableData.length > 0) {
                        tableData.forEach(function (row) {
                            if (row && row._original) {
                                // Restore original data for action events if needed
                                for (var key in row._original) {
                                    if (key !== 'operate' && key !== 'status' && key !== '_original') {
                                        // Store reference to original data for action events
                                        if (!row.hasOwnProperty('_original_' + key)) {
                                            row['_original_' + key] = row._original[key];
                                        }
                                    }
                                }
                            }
                        });
                    }
                } catch (e) {
                    console.warn('Error processing table data:', e);
                }
            }, 100);
        }
    });
});

// Sidebar submenu arrow state management
// Ensures parent sidebar item gets 'submenu-open' class when submenu is active
(function () {
    'use strict';

    function updateSubmenuStates() {
        const sidebarItems = document.querySelectorAll('.sidebar-item.has-sub');

        sidebarItems.forEach(function (item) {
            const submenu = item.querySelector('.submenu');
            if (submenu) {
                // Check both active class and display style (app.js manipulates display directly)
                const hasActiveClass = submenu.classList.contains('active');
                const displayStyle = submenu.style.display;
                const computedDisplay = window.getComputedStyle(submenu).display;
                const isVisible = hasActiveClass || displayStyle === 'block' || computedDisplay === 'block';

                if (isVisible) {
                    item.classList.add('submenu-open');
                } else {
                    item.classList.remove('submenu-open');
                }
            }
        });
    }

    // Watch for submenu changes using MutationObserver
    function initSubmenuObserver() {
        const sidebarMenu = document.getElementById('sidebarMenu');
        if (!sidebarMenu) return;

        // Observer for class and style changes
        const observer = new MutationObserver(function (mutations) {
            let shouldUpdate = false;
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes') {
                    if (mutation.attributeName === 'class' || mutation.attributeName === 'style') {
                        shouldUpdate = true;
                    }
                }
            });
            if (shouldUpdate) {
                // Use requestAnimationFrame for smoother updates
                requestAnimationFrame(updateSubmenuStates);
            }
        });

        // Observe all submenu elements and parent items
        const submenus = sidebarMenu.querySelectorAll('.submenu');
        const sidebarItems = sidebarMenu.querySelectorAll('.sidebar-item.has-sub');

        submenus.forEach(function (submenu) {
            observer.observe(submenu, {
                attributes: true,
                attributeFilter: ['class', 'style'],
                subtree: false
            });
        });

        // Also watch parent items for active class changes
        sidebarItems.forEach(function (item) {
            observer.observe(item, {
                attributes: true,
                attributeFilter: ['class'],
                subtree: false
            });
        });
    }

    // Hook into sidebar link clicks to update state immediately
    function setupClickHandlers() {
        const sidebarMenu = document.getElementById('sidebarMenu');
        if (!sidebarMenu) return;

        // Use event delegation on the menu container
        sidebarMenu.addEventListener('click', function (e) {
            const sidebarLink = e.target.closest('.sidebar-item.has-sub .sidebar-link');
            const sidebarItem = e.target.closest('.sidebar-item.has-sub');

            if (sidebarLink && sidebarItem) {
                const submenu = sidebarItem.querySelector('.submenu');
                if (submenu) {
                    // Update state immediately before other handlers
                    updateSubmenuStates();

                    // Also update after a short delay to catch the toggle
                    setTimeout(function () {
                        updateSubmenuStates();
                    }, 10);

                    // Update after animation completes
                    setTimeout(function () {
                        updateSubmenuStates();
                    }, 350);
                }
            }
        }, true); // Use capture phase to run before app.js handlers
    }

    // Initialize on DOM ready
    function initialize() {
        updateSubmenuStates();
        initSubmenuObserver();
        setupClickHandlers();

        // Fallback: update periodically to catch any missed changes
        setInterval(updateSubmenuStates, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(initialize, 100);
        });
    } else {
        setTimeout(initialize, 100);
    }
})();
