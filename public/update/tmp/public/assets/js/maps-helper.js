/* Backend-powered Google Maps/Places helper
   - Loads map with a domain-restricted MAP API key (already on the page)
   - Uses backend endpoints with IP-restricted PLACE API key for:
     - Autocomplete:  GET /api/get-map-places-list?input=...
     - Details/Geocode: GET /api/get-map-place-details?place_id=... OR ?latitude=..&longitude=..
   - Backend response shape is Places API (New): addressComponents/formattedAddress/location.
     Backend also appends resolved_country_id/snapped/snapped_country_name (country restricted
     to admin-added countries; snapped to the nearest one when the pin falls outside all of them).
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
        // Ensure container is positioned
        var computedStyle = window.getComputedStyle(mapElement);
        if (computedStyle.position === 'static') {
            mapElement.style.position = 'relative';
        }
        mapElement.appendChild(overlay);
        return overlay;
    }

    function showLoader(map) {
        var el = (map && map.getDiv) ? map.getDiv() : map;
        if (!el) return;
        var overlay = getOrCreateLoader(el);
        overlay.style.display = 'flex';
    }

    function hideLoader(map) {
        var el = (map && map.getDiv) ? map.getDiv() : map;
        if (!el) return;
        var overlay = el.querySelector('.map-loading-overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    function createSuggestionsContainer($input) {
        var $existing = $input.next('#places-suggestions');
        if ($existing.length) { return $existing; }
        var $suggestions = $('<div id="places-suggestions" class="list-group" style="position:absolute; z-index: 2000; width: 100%;"></div>');
        $input.after($suggestions); // positioned in normal flow; page should handle layout
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

    function setFromGeocodeResult(result, latLngObj, selectors, map, marker) {
        var address_components = (result && result.addressComponents) ? result.addressComponents : [];
        var city, state, country, full_address;

        for (var i = 0; i < address_components.length; i++) {
            var types = address_components[i].types || [];

            if (types.indexOf('locality') !== -1) {
                city = address_components[i].longText;
            }
            else if (types.indexOf('administrative_area_level_2') !== -1 && !city) {
                // fallback when locality is missing
                city = address_components[i].longText;
            }
            else if (types.indexOf('administrative_area_level_3') !== -1 && !city) {
                // secondary fallback
                city = address_components[i].longText;
            }
            else if (types.indexOf('administrative_area_level_1') !== -1) {
                state = address_components[i].longText;
            }
            else if (types.indexOf('country') !== -1) {
                country = address_components[i].longText;
            }
        }

        full_address = result && result.formattedAddress ? result.formattedAddress : '';

        if (selectors.inputSelector) { $(selectors.inputSelector).val(city || ''); }
        if (selectors.citySelector) { $(selectors.citySelector).val(city || ''); }
        if (selectors.countrySelector) { $(selectors.countrySelector).val(country || ''); }
        if (selectors.stateSelector) { $(selectors.stateSelector).val(state || ''); }
        if (selectors.addressSelector) { $(selectors.addressSelector).val(full_address || ''); }
        if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(latLngObj.lat); }
        if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(latLngObj.lng); }
        if (selectors.countryIdSelector) {
            $(selectors.countryIdSelector).val(result && result.resolved_country_id ? result.resolved_country_id : '');
        }

        var $input = selectors.inputSelector ? $(selectors.inputSelector) : null;
        if (result && result.snapped && result.snapped_country_name) {
            showSnapNotice($input, result.snapped_country_name);
        } else {
            hideSnapNotice($input);
        }

        // Let the form know the resolved country changed, so it can re-apply
        // any country -> currency mapping enforcement.
        $(document).trigger('map:countryResolved', [result && result.resolved_country_id ? result.resolved_country_id : null]);

        var gLatLng = new google.maps.LatLng(latLngObj.lat, latLngObj.lng);
        map.setCenter(gLatLng);
        map.setZoom(17);
        marker.position = gLatLng;
        marker.map = map; // Show marker
    }

    function getLocaleHeader() {
        return { 'Content-Language': (window.currentLocale || 'en') };
    }

    function fetchPlaceDetails(params) {
        return $.ajax({ url: '/api/get-map-place-details', data: params, headers: getLocaleHeader() });
    }

    // prevLat/prevLng: the pin's position before this placement attempt. When
    // the attempt lands outside the selected country, we keep the pin there
    // instead of teleporting it to the country's reference point — jumping
    // to an unrelated point loses the admin's spatial context, whereas
    // "nothing happened, try again closer" is easier to reason about.
    function applyPlaceDetailsResponse(resp, selectors, map, marker, prevLat, prevLng) {
        var d = resp && resp.data ? resp.data : {};
        var loc = d.location || {};

        if (d.snapped && !isNaN(prevLat) && !isNaN(prevLng)) {
            var revertLatLng = new google.maps.LatLng(prevLat, prevLng);
            map.setCenter(revertLatLng);
            marker.position = revertLatLng;
            marker.map = map;
            var $input = selectors.inputSelector ? $(selectors.inputSelector) : null;
            showSnapNotice($input, d.snapped_country_name, true);
            return;
        }

        if (typeof loc.latitude === 'number' && typeof loc.longitude === 'number') {
            setFromGeocodeResult(d, { lat: loc.latitude, lng: loc.longitude }, selectors, map, marker);
        }
    }

    function attachAutocomplete($input, selectors, map, marker) {
        var $suggestions = createSuggestionsContainer($input);
        var debounceTimer;
        $input.on('input', function () {
            clearTimeout(debounceTimer);
            var q = $(this).val();
            if (!q || q.length < 3) { $suggestions.empty().hide(); return; }
            debounceTimer = setTimeout(function () {
                showLoader(map);
                var listParams = { input: q };
                var countryId = getSelectedCountryId(selectors);
                if (countryId) { listParams.country_id = countryId; }
                $.ajax({ url: '/api/get-map-places-list', data: listParams, headers: getLocaleHeader() })
                    .done(function (resp) {
                        var data = resp && resp.data ? resp.data : {};
                        var suggestions = data.suggestions || [];
                        $suggestions.empty();
                        suggestions.slice(0, 7).forEach(function (s) {
                            var pred = s.placePrediction || {};
                            var text = (pred.text && pred.text.text) || (pred.structuredFormat && pred.structuredFormat.mainText && pred.structuredFormat.mainText.text) || '';
                            var $item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
                            $item.text(text);
                            $item.on('click', function (e) {
                                e.preventDefault();
                                $suggestions.empty().hide();
                                if (pred.placeId) {
                                    showLoader(map);
                                    var detailsParams = { place_id: pred.placeId };
                                    var selCountryId = getSelectedCountryId(selectors);
                                    if (selCountryId) { detailsParams.country_id = selCountryId; }
                                    var prevLat = parseFloat($(selectors.latitudeSelector).val());
                                    var prevLng = parseFloat($(selectors.longitudeSelector).val());
                                    fetchPlaceDetails(detailsParams)
                                        .done(function (r) { applyPlaceDetailsResponse(r, selectors, map, marker, prevLat, prevLng); })
                                        .always(function () { hideLoader(map); });
                                }
                            });
                            $suggestions.append($item);
                        });
                        if (suggestions.length) { $suggestions.show(); } else { $suggestions.hide(); }
                    }).always(function () { hideLoader(map); });
            }, 300);
        });
    }

    function initBackendPlacesMap(options) {
        var selectors = options || {};
        var defaultLat = parseFloat($(selectors.defaultLatitudeSelector || '#default-latitude').val() || -33.8688);
        var defaultLng = parseFloat($(selectors.defaultLongitudeSelector || '#default-longitude').val() || 151.2195);
        if (isNaN(defaultLat)) defaultLat = -33.8688;
        if (isNaN(defaultLng)) defaultLng = 151.2195;

        var mapEl = document.getElementById(selectors.mapElementId || 'map');
        var map = new google.maps.Map(mapEl, {
            mapId: GOOGLE_MAP_ID, // Required for AdvancedMarkerElement
            center: { lat: defaultLat, lng: defaultLng },
            zoom: 15
        });
        var marker = new google.maps.marker.AdvancedMarkerElement({
            gmpDraggable: true,
            position: { lat: defaultLat, lng: defaultLng },
            map: map
        });

        // Marker drag → reverse geocode via backend
        google.maps.event.addListener(marker, 'dragend', function (event) {
            var lat = event.latLng.lat();
            var lng = event.latLng.lng();
            var prevLat = parseFloat($(selectors.latitudeSelector).val());
            var prevLng = parseFloat($(selectors.longitudeSelector).val());
            showLoader(map);
            var dragParams = { latitude: lat, longitude: lng };
            var countryId = getSelectedCountryId(selectors);
            if (countryId) { dragParams.country_id = countryId; }
            fetchPlaceDetails(dragParams)
                .done(function (resp) {
                    var d = resp && resp.data ? resp.data : {};
                    var loc = d.location || {};
                    if (typeof loc.latitude === 'number' && typeof loc.longitude === 'number') {
                        applyPlaceDetailsResponse(resp, selectors, map, marker, prevLat, prevLng);
                    } else {
                        // Fallback: at least update lat/lng and marker position
                        if (selectors.latitudeSelector) { $(selectors.latitudeSelector).val(lat); }
                        if (selectors.longitudeSelector) { $(selectors.longitudeSelector).val(lng); }
                        var gLatLng = new google.maps.LatLng(lat, lng);
                        map.setCenter(gLatLng);
                        marker.position = gLatLng;
                        marker.map = map; // Show marker
                    }
                }).always(function () { hideLoader(map); });
        });

        // Text input → backend autocomplete + details
        if (selectors.inputSelector) {
            attachAutocomplete($(selectors.inputSelector), selectors, map, marker);
        }

        mapRegistry[selectors.mapElementId || 'map'] = { map: map, marker: marker, selectors: selectors };

        return { map: map, marker: marker };
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

        var params = { latitude: lat, longitude: lng };
        var countryId = getSelectedCountryId(selectors);
        if (countryId) { params.country_id = countryId; }

        showLoader(entry.map);
        fetchPlaceDetails(params)
            .done(function (resp) {
                var d = resp && resp.data ? resp.data : {};
                var loc = d.location || {};
                if (typeof loc.latitude === 'number' && typeof loc.longitude === 'number') {
                    setFromGeocodeResult(d, { lat: loc.latitude, lng: loc.longitude }, selectors, entry.map, entry.marker);
                }
            }).always(function () { hideLoader(entry.map); });
    }

    global.initBackendPlacesMap = initBackendPlacesMap;
    global.recheckPinForCountry = recheckPinForCountry;
})(window);
