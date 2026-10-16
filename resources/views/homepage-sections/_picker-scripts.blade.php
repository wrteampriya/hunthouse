<script>
    const CURATED_PROPERTIES_TYPE = 'curated_properties_section';
    const CURATED_PROJECTS_TYPE = 'curated_projects_section';

    function formatListing(listing, listContainerId) {
        if (listing.loading) return listing.text;

        var fallbackImg = "{{ url('assets/images/logo/logo.png') }}";
        var imgUrl = listing.image ? listing.image : fallbackImg;
        var isAdded = listContainerId && $(listContainerId).find('input[value="' + listing.id + '"]').length > 0;

        var $container = $(
            "<div class='d-flex align-items-center w-100 p-1 rounded" + (isAdded ? " bg-light text-muted" : "") + "' style='" + (isAdded ? "opacity:0.6;" : "") + "'>" +
                "<img src='" + imgUrl + "' class='rounded me-2' style='width: 40px; height: 40px; object-fit: cover;' onerror='this.src=\"" + fallbackImg + "\"'/>" +
                "<div class='flex-grow-1'>" +
                    "<div class='fw-bold'>" + listing.text + "</div>" +
                "</div>" +
                (isAdded ? "<span class='badge bg-secondary ms-2'>{{ __('Added') }}</span>" : "") +
            "</div>"
        );
        return $container;
    }

    function toggleEmptyState(containerId) {
        var $container = $(containerId);
        if ($container.find('.drag-item').length === 0) {
            $container.hide();
        } else {
            $container.show();
        }
    }

    function addSortableItem(containerId, id, text, image, inputName, statusBadge) {
        if ($(containerId).find('input[value="' + id + '"]').length > 0) {
            showErrorToast("{{ __('Already added') }}");
            return;
        }

        var fallbackImg = "{{ url('assets/images/logo/logo.png') }}";
        var imgUrl = image ? image : fallbackImg;
        var badgeHtml = statusBadge ? statusBadge : "<span class='badge bg-success'>{{ __('Active') }}</span>";

        var html = `
            <div class="col-12 col-sm-6 col-lg-3 drag-item">
                <div class="d-flex align-items-center p-2 border rounded bg-white shadow-sm">
                    <div class="me-2 drag-handle" style="cursor: grab;"><i class="bi bi-grip-vertical text-muted fs-5 pointer-events-none"></i></div>
                    <img src="${imgUrl}" class="rounded me-2 border" style="width: 28px; height: 28px; object-fit: cover; flex-shrink: 0;" onerror="this.src='${fallbackImg}'" draggable="false">
                    <div class="flex-grow-1 text-truncate" style="font-size: 0.9rem;" title="${text}">
                        <div class="fw-bold text-truncate">${text}</div>
                        ${badgeHtml}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-sortable-item" style="padding: 2px 6px;"><i class="bi bi-x-lg"></i></button>
                    <input type="hidden" name="${inputName}" value="${id}">
                </div>
            </div>
        `;
        $(containerId).append(html);
        toggleEmptyState(containerId);
    }

    $(document).on('click', '.remove-sortable-item', function() {
        var $container = $(this).closest('.bg-light');
        $(this).closest('.drag-item').remove();
        if ($container.length > 0) {
            toggleEmptyState('#' + $container.attr('id'));
        }
    });

    function initListingSelect2(selector, url, listContainerId, inputName, modalId = null) {
        var options = {
            placeholder: "{{ __('Search and select...') }}",
            allowClear: true,
            width: '100%',
            theme: 'bootstrap-5',
            ajax: {
                url: url,
                dataType: 'json',
                delay: 250,
                cache: true,
                data: function(params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1,
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results || [],
                        pagination: {
                            more: data.pagination && data.pagination.more ? true : false
                        }
                    };
                }
            },
            minimumInputLength: 0,
            templateResult: function(listing) {
                return formatListing(listing, listContainerId);
            }
        };

        if (modalId) {
            options.dropdownParent = $(modalId);
        }

        $(selector).select2(options).on('select2:selecting', function(e) {
            var data = e.params.args.data;
            if ($(listContainerId).find('input[value="' + data.id + '"]').length > 0) {
                e.preventDefault();
                showErrorToast("{{ __('Already added') }}");
            }
        }).on('select2:select', function(e) {
            var data = e.params.data;
            addSortableItem(listContainerId, data.id, data.text, data.image, inputName);
            $(this).val(null).trigger('change');
        });
    }

    function togglePickerFor(sectionTypeValue, propertiesPickerSelector, projectsPickerSelector) {
        $(propertiesPickerSelector).toggle(sectionTypeValue === CURATED_PROPERTIES_TYPE);
        $(projectsPickerSelector).toggle(sectionTypeValue === CURATED_PROJECTS_TYPE);
    }

    function homepageSectionTypeFormatter(value){
        let AgentsList = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TITLE')) }}";
        let Articles = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TITLE')) }}";
        let Categories = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TITLE')) }}";
        let Faqs = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TITLE')) }}";
        let FeaturedProperties = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TITLE')) }}";
        let FeaturedProjects = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TITLE')) }}";
        let MostLikedProperties = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TITLE')) }}";
        let MostViewedProperties = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TITLE')) }}";
        let NearbyProperties = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TITLE')) }}";
        let Projects = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TITLE')) }}";
        let PremiumProjects = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROJECTS_SECTION.TITLE')) }}";
        let PremiumProperties = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TITLE')) }}";
        let UserRecommendations = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TITLE')) }}";
        let PropertiesByCities = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TITLE')) }}";
        let PropertiesOnMap = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TITLE')) }}";
        let CuratedProperties = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.CURATED_PROPERTIES_SECTION.TITLE')) }}";
        let CuratedProjects = "{{ __(config('constants.HOMEPAGE_SECTION_TYPES.CURATED_PROJECTS_SECTION.TITLE')) }}";
        if(value == 'agents_list_section'){
            return AgentsList;
        }else if(value == 'articles_section'){
            return Articles;
        }else if(value == 'categories_section'){
            return Categories;
        }else if(value == 'faqs_section'){
            return Faqs;
        }else if(value == 'featured_properties_section'){
            return FeaturedProperties;
        }else if(value == 'featured_projects_section'){
            return FeaturedProjects;
        }else if(value == 'most_liked_properties_section'){
            return MostLikedProperties;
        }else if(value == 'most_viewed_properties_section'){
            return MostViewedProperties;
        }else if(value == 'nearby_properties_section'){
            return NearbyProperties;
        }else if(value == 'projects_section'){
            return Projects;
        }else if(value == 'premium_projects_section'){
            return PremiumProjects;
        }else if(value == 'premium_properties_section'){
            return PremiumProperties;
        }else if(value == 'user_recommendations_section'){
            return UserRecommendations;
        }else if(value == 'properties_by_cities_section'){
            return PropertiesByCities;
        }else if(value == 'properties_on_map_section'){
            return PropertiesOnMap;
        }else if(value == 'curated_properties_section'){
            return CuratedProperties;
        }else if(value == 'curated_projects_section'){
            return CuratedProjects;
        }
        return value;
    }

    function statusFormatter(value) {
        if(value == 1) {
            return '<span class="badge bg-success">{{ __("Active") }}</span>';
        } else {
            return '<span class="badge bg-danger">{{ __("Deactive") }}</span>';
        }
    }
</script>
