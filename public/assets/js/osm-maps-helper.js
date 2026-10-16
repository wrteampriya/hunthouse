/* Free map helper — OpenStreetMap (Leaflet) + GeoNames backend.
   Independent of the Google maps-helper.js. Takes the SAME options object as
   initBackendPlacesMap so the blade markup stays identical, but renders with Leaflet
   and talks to the backend GeoNames endpoints:
     - Autocomplete:       GET /api/get-osm-places-list?input=...
     - Details/Reverse:    GET /api/get-osm-place-details?place_id=... OR ?latitude=..&longitude=..
*/
(function (global) {
    function getOrCreateLoader(mapElement) {
        var existing = mapElement.querySelector('.map-loading-overlay');
        if (existing) { return existing; }
        var overlay = document.createElement('div');
        overlay.className = 'map-loading-overlay';
        overlay.style.position = 'absolute';
        overlay.style.inset = '0px';
        overlay.style.background = 'rgba(255,255,255,0.6)';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '2001';
        var text = document.createElement('div');
        text.style.padding = '8px 12px';
        text.style.background = '#fff';
        text.style.border = '1px solid #ddd';
        text.style.borderRadius = '4px';
        text.style.boxShadow = '0 2px 6px rgba(0,0,0,0.15)';
        text.style.color = '#333';
        text.style.fontSize = '13px';
        text.textContent = 'Loading...';
        overlay.appendChild(text);
        var computedStyle = window.getComputedStyle(mapElement);
        if (computedStyle.position === 'static') {
            mapElement.style.position = 'relative';
        }
        mapElement.appendChild(overlay);
        return overlay;
    }

    function showLoader(mapEl) {
        if (!mapEl) return;
        getOrCreateLoader(mapEl).style.display = 'flex';
    }

    function hideLoader(mapEl) {
        if (!mapEl) return;
        var overlay = mapEl.querySelector('.map-loading-overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    function createSuggestionsContainer($input) {
        var $existing = $input.next('#places-suggestions');
        if ($existing.length) { return $existing; }
        var $suggestions = $('<div id="places-suggestions" class="list-group" style="position:absolute; z-index: 2000; width: 100%;"></div>');
        $input.after($suggestions);
        return $suggestions;
    }

    function showSnapNotice($input, countryName, reverted) {
        if (!$input || !$input.length) return;
        var $container = $input.closest('.form-group, .col-md-12, .col-12');
        var $notice = $container.find('.map-snap-notice');
        if (!$notice.length) {
            $notice = $('<div class="map-snap-notice text-muted small mt-1"></div>');
            $container.append($notice);
        }
        var message = reverted
            ? 'Location is outside ' + countryName + '. Pin kept at its previous position.'
            : 'Location is outside ' + countryName + '. Pin moved to ' + countryName + '.';
        $notice.text(message).show();
    }

    // The selected country (Details card) restricts where the pin/search can
    // land — read it fresh on every request so it always reflects the
    // current #country_select value.
    function getSelectedCountryId(selectors) {
        var val = selectors.countryIdSelector ? $(selectors.countryIdSelector).val() : '';
        return val ? val : null;
    }

    // Registry of initialized maps/markers, keyed by mapElementId, so
    // recheckPinForCountry() can re-validate an already-placed pin after the
    // country dropdown changes without the blade page having to track the
    // map/marker objects itself.
    var mapRegistry = {};

    function hideSnapNotice($input) {
        if (!$input || !$input.length) return;
        $input.closest('.form-group, .col-md-12, .col-12').find('.map-snap-notice').hide();
    }

    // Write a resolved place into the form fields and recentre the map/marker.
    function applyResult(fields, latLng, selectors, ctx) {
        if (selectors.citySelector) { $(selectors.citySelector).val(fields.city || ''); }
        if (selectors.inputSelector) { $(selectors.inputSelector).val(fields.city || ''); }
        if (selectors.stateSelector) { $(selectors.stateSelector).val(fields.state || ''); }
        if (selectors.countrySelector) { $(selectors.countrySelector).val(fields.country || ''); }
        if (selectors.addressSelector) { $(selectors.addressSelector).val(fields.address || ''); }
        if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(latLng.lat); }
        if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(latLng.lng); }
        if (selectors.countryIdSelector) {
            $(selectors.countryIdSelector).val(fields.resolved_country_id || '');
        }

        var $input = selectors.inputSelector ? $(selectors.inputSelector) : null;
        if (fields.snapped && fields.snapped_country_name) {
            showSnapNotice($input, fields.snapped_country_name);
        } else {
            hideSnapNotice($input);
        }

        $(document).trigger('map:countryResolved', [fields.resolved_country_id || null]);

        ctx.map.setView([latLng.lat, latLng.lng], 17);
        ctx.marker.setLatLng([latLng.lat, latLng.lng]);
    }

    function getLocaleHeader() {
        return { 'Content-Language': (window.currentLocale || 'en') };
    }

    // prevLatLng: the pin's position before this placement attempt (omitted
    // by recheckPinForCountry, where snapping to the new country's reference
    // point is still correct). When set and the attempt lands outside the
    // selected country, the pin stays put instead of teleporting to the
    // country's reference point — jumping to an unrelated point loses the
    // admin's spatial context.
    function reverseGeocode(latLng, selectors, ctx, prevLatLng) {
        showLoader(ctx.mapEl);
        var params = { latitude: latLng.lat, longitude: latLng.lng };
        var countryId = getSelectedCountryId(selectors);
        if (countryId) { params.country_id = countryId; }
        $.ajax({ url: '/api/get-osm-place-details', data: params, headers: getLocaleHeader() })
            .done(function (resp) {
                var d = (resp && resp.data) ? resp.data : {};
                var result = d.result || null;

                if (d.snapped && prevLatLng && !isNaN(prevLatLng.lat) && !isNaN(prevLatLng.lng)) {
                    ctx.map.setView([prevLatLng.lat, prevLatLng.lng], ctx.map.getZoom());
                    ctx.marker.setLatLng([prevLatLng.lat, prevLatLng.lng]);
                    var $input = selectors.inputSelector ? $(selectors.inputSelector) : null;
                    showSnapNotice($input, d.snapped_country_name, true);
                    return;
                }

                if (result) {
                    var loc = (result.geometry && result.geometry.location) || latLng;
                    result.resolved_country_id = d.resolved_country_id || null;
                    result.snapped = d.snapped || false;
                    result.snapped_country_name = d.snapped_country_name || null;
                    applyResult(result, { lat: loc.lat || latLng.lat, lng: loc.lng || latLng.lng }, selectors, ctx);
                } else {
                    // Fallback: keep the dragged coordinates even if no place was found
                    if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(latLng.lat); }
                    if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(latLng.lng); }
                }
            })
            .always(function () { hideLoader(ctx.mapEl); });
    }

    function attachAutocomplete($input, selectors, ctx) {
        var $suggestions = createSuggestionsContainer($input);
        var debounceTimer;
        $input.on('input', function () {
            clearTimeout(debounceTimer);
            var q = $(this).val();
            if (!q || q.length < 3) { $suggestions.empty().hide(); return; }
            debounceTimer = setTimeout(function () {
                showLoader(ctx.mapEl);
                var listParams = { input: q };
                var countryId = getSelectedCountryId(selectors);
                if (countryId) { listParams.country_id = countryId; }
                $.ajax({ url: '/api/get-osm-places-list', data: listParams, headers: getLocaleHeader() })
                    .done(function (resp) {
                        var data = (resp && resp.data) ? resp.data : {};
                        var preds = data.predictions || [];
                        $suggestions.empty();
                        preds.slice(0, 7).forEach(function (p) {
                            var $item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
                            $item.text(p.description || '');
                            $item.on('click', function (e) {
                                e.preventDefault();
                                $suggestions.empty().hide();
                                // Route through the reverse-geocode endpoint (not just the raw
                                // prediction) so the picked place is resolved/snapped against
                                // admin-added countries the same way a marker drag is.
                                if (typeof p.lat === 'number' && typeof p.lng === 'number') {
                                    var prevLat = parseFloat($(selectors.latitudeSelector).val());
                                    var prevLng = parseFloat($(selectors.longitudeSelector).val());
                                    reverseGeocode({ lat: p.lat, lng: p.lng }, selectors, ctx, { lat: prevLat, lng: prevLng });
                                }
                            });
                            $suggestions.append($item);
                        });
                        if (preds.length) { $suggestions.show(); } else { $suggestions.hide(); }
                    })
                    .always(function () { hideLoader(ctx.mapEl); });
            }, 300);
        });
    }

    function initOsmPlacesMap(options) {
        var selectors = options || {};
        var defaultLat = parseFloat($(selectors.defaultLatitudeSelector || '#default-latitude').val() || -33.8688);
        var defaultLng = parseFloat($(selectors.defaultLongitudeSelector || '#default-longitude').val() || 151.2195);
        if (isNaN(defaultLat)) defaultLat = -33.8688;
        if (isNaN(defaultLng)) defaultLng = 151.2195;

        var mapEl = document.getElementById(selectors.mapElementId || 'map');
        // Guard against double initialisation (some forms call initMap() more than once).
        if (!mapEl || mapEl._leaflet_id) { return; }
        var map = L.map(mapEl).setView([defaultLat, defaultLng], 15);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

        var ctx = { map: map, marker: marker, mapEl: mapEl };

        // Marker drag → reverse geocode via backend
        marker.on('dragend', function () {
            var latLng = marker.getLatLng();
            var prevLat = parseFloat($(selectors.latitudeSelector).val());
            var prevLng = parseFloat($(selectors.longitudeSelector).val());
            reverseGeocode({ lat: latLng.lat, lng: latLng.lng }, selectors, ctx, { lat: prevLat, lng: prevLng });
        });

        // Text input → backend autocomplete
        if (selectors.inputSelector) {
            attachAutocomplete($(selectors.inputSelector), selectors, ctx);
        }

        // Leaflet needs a size recalculation when the container becomes visible
        setTimeout(function () { map.invalidateSize(); }, 200);

        mapRegistry[selectors.mapElementId || 'map'] = { ctx: ctx, selectors: selectors };

        return ctx;
    }

    // Re-validate the pin already on the map against the currently selected
    // country (called after the #country_select dropdown changes). If the
    // existing lat/long falls outside the newly selected country, the
    // backend snaps it and we redraw the marker + show the info notice.
    function getSelectedCountryLatLng(selectors) {
        if (!selectors.countrySelectSelector) return null;
        var $option = $(selectors.countrySelectSelector).find('option:selected');
        var lat = parseFloat($option.attr('data-lat'));
        var lng = parseFloat($option.attr('data-lng'));
        if (isNaN(lat) || isNaN(lng)) return null;
        return { lat: lat, lng: lng };
    }

    function recheckPinForCountry(mapElementId, selectorsOverride) {
        var entry = mapRegistry[mapElementId || 'map'];
        if (!entry) return;
        var selectors = selectorsOverride || entry.selectors;
        var lat = parseFloat($(selectors.latitudeSelector).val());
        var lng = parseFloat($(selectors.longitudeSelector).val());
        if (isNaN(lat) || isNaN(lng)) {
            // No pin placed yet — drop one at the newly selected country's
            // reference point so the admin has somewhere to start dragging from.
            var countryLatLng = getSelectedCountryLatLng(selectors);
            if (!countryLatLng) return;
            lat = countryLatLng.lat;
            lng = countryLatLng.lng;
        }

        reverseGeocode({ lat: lat, lng: lng }, selectors, entry.ctx);
    }

    global.initOsmPlacesMap = initOsmPlacesMap;
    global.recheckPinForCountry = recheckPinForCountry;
})(window);
