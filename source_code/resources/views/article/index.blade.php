@extends('layouts.main')

@section('title')
    {{ __('Article') }}
@endsection

@section('page-title')
<div class="page-title">
	<div class="row">
		<div class="col-12 col-md-6 order-md-1 order-last">
			<h4>@yield('title')</h4>
		</div>
		<div class="col-12 order-first article_header">
            @if (has_permissions('create', 'article'))
                <a href="{{url('add_article') }}" class="btn btn-primary btn_add">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                        class="bi bi-plus-circle-fill" viewBox="0 0 16 16">
                        <path
                            d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.5 4.5a.5.5 0 0 0-1 0v3h-3a.5.5 0 0 0 0 1h3v3a.5.5 0 0 0 1 0v-3h3a.5.5 0 0 0 0-1h-3v-3z">
                        </path>
                    </svg>
                    {{ __('Add Article') }}
                </a>
            @endif
		</div>
	</div>
</div>
@endsection


@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped"
                            id="table_list" data-toggle="table" data-url="{{ route('article_list') }}"
                            data-click-to-select="true" data-responsive="true" data-side-pagination="server"
                            data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-trim-on-search="false" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams"
                            data-response-handler="globalTableResponseHandler">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="title" data-sortable="true">{{ __('Title') }}</th>
                                    <th scope="col" data-field="description" data-formatter="descriptionFormatter" data-sortable="true" style="max-width: 300px;">{{ __('Description') }}</th>
                                    <th scope="col" data-field="category.category" data-sortable="false">{{ __('Category Title') }}</th>
                                    <th scope="col" data-field="view_count" data-sortable="true" data-align="center">{{ __('View Count') }}</th>
                                    <th scope="col" data-field="image" data-formatter="imageFormatter" data-sortable="false" data-align="center">{{ __('Image') }}</th>
                                    <th scope="col" data-field="meta_title" data-sortable="false" data-visible="false">{{ __('Meta Title') }}</th>
                                    <th scope="col" data-field="meta_description" data-sortable="false" data-visible="false">{{ __('Meta Description') }}</th>
                                    @if (has_permissions('update', 'article') || has_permissions('delete', 'article'))
                                        <th scope="col" data-field="operate" data-sortable="false" data-align="center"> {{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Description Modal -->
    <div class="modal fade" id="descriptionModal" tabindex="-1" aria-labelledby="descriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="descriptionModalLabel">{{ __('Article Description') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea id="modalDescriptionContent" style="display: none;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
<script>
    window.articleDescriptions = {};

    function queryParams(p) {
        return {
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            limit: p.limit,
            search: p.search,
            status: $('#status').val(),
            category: $('#category').val(),
            customer_id: $('#customerid').val(),
        };
    }

    // Custom formatter for description column
    function descriptionFormatter(value, row, index) {
        if (!value) return '-';

        window.articleDescriptions[row.id] = row.raw_description || row.description || value;

        // Sanitize the value to prevent XSS while preserving HTML formatting
        const sanitizedValue = sanitizeHtml(value);

        // Strip HTML tags for length calculation
        const textOnly = value.replace(/<[^>]*>/g, '');
        const maxLength = 100;

        if (textOnly.length <= maxLength) {
            return `<div class="description-cell">${sanitizedValue}</div>`;
        }

        // Truncate and sanitize
        const truncatedText = textOnly.substring(0, maxLength);
        const truncated = sanitizeHtml(truncatedText);

        return `
            <div class="description-cell">
                <div class="description-preview">${truncated}...</div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1 read-more-btn" data-article-id="${row.id}"
                        title="{{ __('Click to read full description') }}">
                    <i class="bi bi-eye"></i> {{ __('Read More') }}
                </button>
            </div>
        `;
    }


    $(document).on('click', '.read-more-btn', function() {
        const articleId = $(this).data('article-id');
        showFullDescription(window.articleDescriptions[articleId] || '');
    });

    // Function to show full description in modal
    function showFullDescription(description) {
        const contentElement = document.getElementById('modalDescriptionContent');

        // Remove any existing TinyMCE instance
        const existingEditor = tinymce.get('modalDescriptionContent');
        if (existingEditor) {
            existingEditor.remove();
        }

        // Set the content
        contentElement.value = description;

        // Detect RTL from current language or HTML dir attribute
        const globalLangData = window.globalLanguageData || {};
        const currentLang = globalLangData.current || {};
        const isRTL = currentLang.rtl || document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';

        // Wait a moment to ensure previous instance is fully removed
        setTimeout(() => {
            // Double-check no instance exists before initializing
            if (tinymce.get('modalDescriptionContent')) {
                return;
            }

            // Initialize TinyMCE in readonly mode
            tinymce.init({
                selector: '#modalDescriptionContent',
                readonly: true,
                height: 400,
                menubar: false,
                toolbar: false,
                statusbar: false,
                plugins: [
                    'advlist autolink lists link charmap',
                    'searchreplace visualblocks code',
                    'table directionality'
                ],
                directionality: isRTL ? 'rtl' : 'ltr',
                content_style: isRTL ? `
                    body { direction: rtl; text-align: right; font-family: Arial, sans-serif; }
                    p, div, span { direction: rtl; text-align: right; }
                ` : 'body { font-family: Arial, sans-serif; }',
                setup: function (editor) {
                    if (isRTL) {
                        editor.on('init', function () {
                            const body = editor.getBody();
                            body.style.direction = 'rtl';
                            body.style.textAlign = 'right';
                            body.classList.add('rtl-content');
                        });
                    }
                }
            });
        }, 100);

        // Show modal (Bootstrap 5 syntax)
        const modal = new bootstrap.Modal(document.getElementById('descriptionModal'));
        modal.show();
        $('#modalDescriptionContent').show();

        // Clean up TinyMCE instance when modal is closed - ensure single listener
        const modalElement = document.getElementById('descriptionModal');
        // Remove existing listener by cloning
        modalElement.removeEventListener('hidden.bs.modal', arguments.callee);

        modalElement.addEventListener('hidden.bs.modal', function cleanupTinyMCE() {
            const editor = tinymce.get('modalDescriptionContent');
            if (editor) {
                editor.remove();
            }
            modalElement.removeEventListener('hidden.bs.modal', cleanupTinyMCE);
        }, { once: true });
    }
</script>

<style>
    .description-cell {
        max-width: 300px;
        word-wrap: break-word;
    }

    .description-preview {
        line-height: 1.4;
    }

    .read-more-btn {
        font-size: 0.85rem;
        color: #0d6efd !important;
        text-decoration: none;
    }

    .read-more-btn:hover {
        text-decoration: underline !important;
    }

    #descriptionModal .modal-body {
        max-height: 60vh;
        overflow-y: auto;
    }
</style>
@endsection
