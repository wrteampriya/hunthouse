<script src="{{ url('assets/js/bootstrap.js') }}"></script>

{{-- Call Language Route for JS --}}
<script src="{{url('/js/lang')}}"></script>
<script type="text/javascript" src="{{ url('/assets/js/axios.min.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/firebase/firebase-app-8-10-0.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/firebase/firebase-messaging-8-10-0.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/jquery-3-1-1.min.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/jquery.validate.min.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/filepond/filepond.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/custom/filepond-localization.js') }}"></script>
<script type="text/javascript" src="{{ url('/assets/js/js-color.min.js') }}"></script>

<script src="{{ url('/assets/js/jquery.repeater.js') }}"></script>

<script type="text/javascript" src="{{ url('/assets/js/filepond/filepond.jquery.js') }}"></script>

<script src="{{ url('assets/js/custom/firebase_config.js') }}"></script>

<script src="{{ url('assets/js/bootstrap-3-3-7.min.js') }}"></script>

<script src="{{ url('assets/extensions/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="{{ url('assets/js/dragula.js') }}"></script>

<script src="{{ url('assets/js/app.js') }}"></script>
<script src="{{ url('assets/js/sidebar-responsive.js') }}"></script>
<script src="{{ url('assets/extensions/tinymce/tinymce.min.js') }}"></script>

{{-- DOMPurify for global HTML sanitization to prevent XSS attacks --}}
<script src="{{ url('assets/js/purify.min.js') }}"></script>

<script src="{{ url('assets/js/custom/function.js') }}"></script>
<script src="{{ url('assets/js/custom/common.js') }}"></script>
<script src="{{ url('assets/js/custom/custom.js') }}"></script>
<script src="{{ url('assets/js/custom/formatter.js') }}?v={{ filemtime(public_path('assets/js/custom/formatter.js')) }}"></script>
<script src="{{ url('assets/js/custom/validate.js') }}"></script>

<script src="{{ url('assets/js/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ url('assets/js/jquery-jvectormap-asia-merc.js') }}"></script>
<script src="{{ url('assets/js/query-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ url('assets/js/jquery-jvectormap-world-mill.js') }}"></script>

<script src="{{ url('assets/extensions/toastify-js/src/toastify.js') }}"></script>
<script src="{{ url('assets/extensions/parsleyjs/parsley.min.js') }}"></script>
<script src="{{ url('assets/js/pages/parsley.js') }}"></script>
<script src="{{ url('assets/js/custom/parsley-localization.js') }}"></script>

{{-- Set current locale and translations before loading Bootstrap Table --}}
<!-- <script>
    window.currentLocale = '{{ Session::get("locale", "en") }}';
    window.trans = {!! json_encode([
        'Loading, please wait' => __('Loading, please wait'),
        'rows per page' => __('rows per page'),
        'Showing' => __('Showing'),
        'to' => __('to'),
        'of' => __('of'),
        'rows' => __('rows'),
        'filtered from' => __('filtered from'),
        'total rows' => __('total rows'),
        'previous page' => __('previous page'),
        'to page' => __('to page'),
        'next page' => __('next page'),
        'Search' => __('Search'),
        'Clear Search' => __('Clear Search'),
        'No matching records found' => __('No matching records found'),
        'Hide/Show pagination' => __('Hide/Show pagination'),
        'Show pagination' => __('Show pagination'),
        'Hide pagination' => __('Hide pagination'),
        'Refresh' => __('Refresh'),
        'Toggle' => __('Toggle'),
        'Show card view' => __('Show card view'),
        'Hide card view' => __('Hide card view'),
        'Columns' => __('Columns'),
        'Toggle all' => __('Toggle all'),
        'Fullscreen' => __('Fullscreen'),
        'All' => __('All'),
    ]) !!};
    
    // Prevent Bootstrap Table auto-initialization and add global XSS protection
    $(document).ready(function() {
        $('[data-toggle="table"]').each(function() {
            // Store original toggle attribute
            $(this).attr('data-toggle-original', 'table').removeAttr('data-toggle');
            
            // Add global response handler for XSS protection if not already set
            if (!$(this).attr('data-response-handler')) {
                $(this).attr('data-response-handler', 'globalTableResponseHandler');
            }
        });
    });
</script> -->

<script src="{{ url('assets/extensions/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ url('assets/extensions/bootstrap-table/reorder-rows.min.js') }}"></script>
<script src="{{ url('assets/extensions/bootstrap-table/jquery.tablednd.min.js') }}"></script>
<script src="{{ url('assets/extensions/bootstrap-table/fixed-columns/bootstrap-table-fixed-columns.min.js') }}"></script>
<script src="{{ url('assets/extensions/bootstrap-table/mobile/bootstrap-table-mobile.min.js') }}"></script>

{{-- Load localization after Bootstrap Table --}}
<script src="{{ url('assets/js/custom/bootstrap-table-localization.js') }}"></script>

<script src="{{ url('assets/extensions/magnific-popup/jquery.magnific-popup.min.js') }}"></script>
<script src="{{ url('assets/extensions/select2/dist/js/select2.min.js') }}"></script>

<script src="{{ url('assets/extensions/jquery-ui/jquery-ui.js') }}"></script>

<script src="{{ url('assets/extensions/clipboardjs/dist/clipboard.min.js') }}"></script>

<script src="{{ asset('assets/js/chosen.jquery.min.js') }}"></script>

<script src="{{ asset('assets/js/filepond/filepond.min.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-image-preview.min.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-pdf-preview.min.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-file-validate-size.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-file-validate-type.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-image-validate-size.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond.jquery.js') }}"></script>

<script src="{{ asset('assets/js/tagify-4-15-2.min.js') }}"></script>

<script>
    if (document.getElementById("meta_tags") != null) {
        $(document).ready(function() {
            var input = document.querySelector('input[id=meta_tags]');
            new Tagify(input)
        });
    }

    if (document.getElementById("edit_meta_tags") != null) {
        $(document).ready(function() {
            var input = document.querySelector('input[id=edit_meta_tags]');
            new Tagify(input)
        });
    }
</script>
<script>
    // Retrieve the value from the .env file in Laravel
    const fillColor = "{{ env('PRIMARY_COLOR') }}";
</script>

<script>
    // Set the CSS custom property using JavaScript
    var primarycolor = "{{ env('PRIMARY_COLOR') }}";

    document.documentElement.style.setProperty('--bs-primary', primarycolor);

    var rgbaprimarycolor = "{{ env('PRIMARY_RGBA_COLOR') }}";

    document.documentElement.style.setProperty('--primary-rgba', rgbaprimarycolor);
</script>

@if (Session::has('success'))
    <script type="text/javascript">
        Toastify({
            text: '{{ Session::get('success') }}',
            duration: 6000,
            close: !0,
            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
        }).showToast()
    </script>
@endif

@if (Session::has('error'))
    <script type="text/javascript">
        Toastify({
            text: '{{ Session::get('error') }}',
            duration: 6000,
            close: !0,
            backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"
        }).showToast()
    </script>
@endif
@if ($errors->any())
    <script type="text/javascript">
        Toastify({
            text: "{{ implode(', ', $errors->all()) }}",
            duration: 6000,
            close: true,
            backgroundColor: '#dc3545'
        }).showToast();
    </script>
@endif

{{-- Global Search --}}
<script>
    // Global Search (menus & key actions)
    $(document).ready(function () {
        function buildSearchIndex() {
            const items = [];

            const toTitleCase = (str) => (str || '')
                .replace(/[-_]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase()
                .replace(/(^|\s)\S/g, (t) => t.toUpperCase());

            // Translation helper with graceful fallback
            const t = (key) => {
                try {
                    const dict = window.trans || {};
                    return dict[key] || key;
                } catch (e) {
                    return key;
                }
            };

            // Sidebar menus (include localized label + English alias from URL)
            // Index all rendered menu items regardless of current visibility (collapsed/expanded)
            $('#sidebarMenu a').each(function () {
                const $a = $(this);
                const text = ($a.text() || '').trim();
                const href = $a.attr('href');
                if (!href || href === '#') return;

                let pathname = '';
                try {
                    pathname = new URL(href, window.location.origin).pathname;
                } catch (e) {
                    pathname = href;
                }
                const seg = (pathname || '')
                    .split('/')
                    .filter(Boolean)
                    .pop() || '';
                const englishAlias = toTitleCase(seg);

                items.push({
                    label: text || englishAlias || href,
                    url: href,
                    group: 'Menu',
                    keywords: [text, englishAlias]
                });
            });

            // Key actions and deep links (with synonyms for English + generic terms)
            const push = (label, url, group, keywords = []) => {
                items.push({ label, url, group, keywords: [label, ...keywords] });
            };
            try {
                // Property - only if user has create permission
                @if (has_permissions('create', 'property'))
                    push(t('Add Property'), "{{route('property.create')}}", 'Action', [t('Create Property'), t('New Property'), t('Property')]);
                @endif
                
                // Project - only if user has create permission
                @if (has_permissions('create', 'project'))
                    push(t('Add Project'), "{{route('project.create')}}", 'Action', [t('Create Project'), t('New Project'), t('Project')]);
                @endif
                
                // Settings - only if user has read permission for system settings
                @if (has_permissions('read', 'system_settings'))
                    push(t('Razorpay Settings'), "{{route('system-settings.index')}}#search-anchor-razorpay", 'Settings', [t('Razorpay'), t('Payment')]);
                    push(t('PayPal Settings'), "{{route('system-settings.index')}}#search-anchor-paypal", 'Settings', [t('Paypal'), t('Payment')]);
                    push(t('Paystack Settings'), "{{route('system-settings.index')}}#search-anchor-paystack", 'Settings', [t('Paystack'), t('Payment')]);
                    push(t('Stripe Settings'), "{{route('system-settings.index')}}#search-anchor-stripe", 'Settings', [t('Stripe'), t('Payment')]);
                    push(t('Flutterwave Settings'), "{{route('system-settings.index')}}#search-anchor-flutterwave", 'Settings', [t('Flutterwave'), t('Payment')]);
                    push(t('Bank Details Settings'), "{{route('system-settings.index')}}#search-anchor-bank-details", 'Settings', [t('Bank'), t('Details')]);
                @endif

            } catch (e) {
                console.log(e);
            }
            return items;
        }

        let index = buildSearchIndex();
        function ensureIndex() {
            if (!index || index.length === 0) {
                index = buildSearchIndex();
            }
        }
        const $input = $('#globalSearchInput');
        const $results = $('#globalSearchResults');
        const $inputMobile = $('#globalSearchInputMobile');
        const $resultsMobile = $('#globalSearchResultsMobile');

            function filterItems(q) {
            ensureIndex();
            const normalize = (s) => (s || '')
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
            const query = normalize(q || '');
            if (!query) return [];
            return index.filter(it => {
                    // Include menu, settings and action shortcuts
                    const allowedGroups = ['Menu', 'Settings', 'Action'];
                    if (!allowedGroups.includes(it.group)) { return false; }
                    const labelMatch = normalize(it.label).includes(query);
                    const kw = (it.keywords || []).map(normalize);
                    const kwMatch = kw.some(k => k.includes(query));
                    return labelMatch || kwMatch;
                })
                .slice(0, 15);
        }

        function renderResults(items) {
            if (!items.length) {
                const html = `<div class="list-group-item text-center text-muted">
                                <i class="fas fa-search me-2"></i>
                                {{ __('No results found') }}
                            </div>`;
                $results.html(html).show();
                return;
            }
            const html = items
                .map(it => `<a href="${it.url}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>${it.label}</span>
                                <small class="text-muted">${it.group}</small>
                            </a>`)
                .join('');
            $results.html(html).show();
        }

        function renderResultsMobile(items) {
            if (!items.length) {
                const html = `<div class="list-group-item text-center text-muted">
                                <i class="fas fa-search me-2"></i>
                                {{ __('No results found') }}
                            </div>`;
                $resultsMobile.html(html).show();
                return;
            }
            const html = items
                .map(it => `<a href="${it.url}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span>${it.label}</span>
                                <small class="text-muted">${it.group}</small>
                            </a>`)
                .join('');
            $resultsMobile.html(html).show();
        }

        let debounceTimer = null;
        $input.on('focus', ensureIndex);
        $input.on('input', function () {
            const val = $(this).val();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (!val || val.trim() === '') {
                    $results.hide().empty();
                } else {
                    renderResults(filterItems(val));
                }
            }, 120);
        });

        // Enter to go to first result
        $input.on('keydown', function (e) {
            if (e.key === 'Enter') {
                const first = $results.find('a').get(0);
                if (first) {
                    e.preventDefault();
                    window.location.href = $(first).attr('href');
                }
            }
        });

        // Mobile bindings
        let debounceTimerMobile = null;
        $inputMobile.on('focus', ensureIndex);
        $inputMobile.on('input', function () {
            const val = $(this).val();
            clearTimeout(debounceTimerMobile);
            debounceTimerMobile = setTimeout(() => {
                if (!val || val.trim() === '') {
                    $resultsMobile.hide().empty();
                } else {
                    renderResultsMobile(filterItems(val));
                }
            }, 120);
        });
        $inputMobile.on('keydown', function (e) {
            if (e.key === 'Enter') {
                const first = $resultsMobile.find('a').get(0);
                if (first) {
                    e.preventDefault();
                    window.location.href = $(first).attr('href');
                }
            }
        });

        // Click navigates as normal, also close list
        $results.on('click', 'a', function () {
            $results.hide().empty();
        });
        $resultsMobile.on('click', 'a', function () {
            const modal = bootstrap.Modal.getInstance(document.getElementById('globalSearchModal'));
            if (modal) modal.hide();
            $resultsMobile.hide().empty();
        });

        // Keyboard shortcut Ctrl+/ to focus search
        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                $input.focus();
            }
            // Escape to close
            if (e.key === 'Escape') {
                $results.hide().empty();
            }
        });

        // Hide results when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.global-search').length) {
                $results.hide().empty();
            }
        });

        // Open modal button
        $('#openGlobalSearch').on('click', function () {
            const modalEl = document.getElementById('globalSearchModal');
            if (!modalEl) { return; }
            const modal = new bootstrap.Modal(modalEl);
            ensureIndex();
            $inputMobile.val('');
            $resultsMobile.hide().empty();
            modal.show();
            setTimeout(() => $inputMobile.trigger('focus'), 200);
        });
        // Cleanup on hide
        $('#globalSearchModal').on('hidden.bs.modal', function () {
            $inputMobile.val('');
            $resultsMobile.hide().empty();
        });
    });
</script>
{{-- End Global Search --}}

{{-- Fix dropdown inside bootstrap-table --}}
<script>
    $(document).on('show.bs.dropdown', '.fixed-table-body .dropdown', function () {
        var $menu = $(this).find('.dropdown-menu');
        var $btn = $(this).find('.dropdown-toggle');
        var btnRect = $btn[0].getBoundingClientRect();

        $menu.appendTo('body').css({
            position: 'fixed',
            top: btnRect.bottom + 'px',
            left: btnRect.left + 'px',
            zIndex: 1060,
            display: 'block'
        });

        $(this).data('bs-menu', $menu);
    });

    $(document).on('hide.bs.dropdown', '.fixed-table-body .dropdown', function () {
        var $menu = $(this).data('bs-menu');
        if ($menu) {
            $menu.removeAttr('style').appendTo(this);
            $(this).removeData('bs-menu');
        }
    });
</script>

{{-- Google Map ID --}}
<script>
    window.GOOGLE_MAP_ID = '{{ env('MAP_API_KEY') }}';
</script>