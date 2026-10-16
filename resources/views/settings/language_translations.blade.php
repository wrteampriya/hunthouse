@extends('layouts.main')

@section('title')
    {{ __('Edit Translations') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first text-end">
                <a href="{{ route('language.index') }}" class="btn btn-secondary btn-sm">{{ __('Back') }}</a>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ $language->name }} ({{ $language->code }}) — {{ strtoupper($type) }}</h4>
                    </div>
                </div>
            </div>

            <div class="card-content">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('language.translations.save', ['id' => $language->id]) }}"
                          id="translationsForm"
                          data-chunk-url="{{ route('language.translations.save-chunk', ['id' => $language->id]) }}">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="small text-muted">{{ __('Tip: Empty keys appear first') }}</div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showOnlyEmpty">
                                <label class="form-check-label" for="showOnlyEmpty">{{ __('Show only empty') }}</label>
                            </div>
                        </div>

                        <div class="row g-3" id="translationsGrid">
                            @php
                                $missing = [];
                                $filled = [];
                                foreach ($translations as $k => $v) {
                                    $val = is_string($v) ? $v : json_encode($v);
                                    if (trim((string)$val) === '') {
                                        $missing[$k] = $val;
                                    } else {
                                        $filled[$k] = $val;
                                    }
                                }
                                ksort($filled);
                                ksort($missing);
                                $sorted = $missing + $filled;
                            @endphp
                            @forelse($sorted as $key => $value)
                                @php $isEmpty = trim((string)$value) === ''; @endphp
                                <div class="col-md-6 translation-row {{ $isEmpty ? 'is-empty' : '' }}">
                                    <div class="form-group">
                                        <label class="form-label">{{ $key }}</label>
                                        <input type="text" class="form-control" name="translations[{{ $key }}]" value="{{ $value }}">
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="alert alert-info">{{ __('No keys found.') }}</div>
                                </div>
                            @endforelse
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" id="btnSaveTranslations">
                                {{ __('Save') }}
                            </button>
                            <span class="text-muted small ms-2 d-none" id="translationsProgress"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        (function(){
            // Toggle "show only empty"
            const toggle = document.getElementById('showOnlyEmpty');
            const grid = document.getElementById('translationsGrid');
            if (toggle && grid) {
                const applyFilter = () => {
                    const onlyEmpty = toggle.checked;
                    const rows = grid.querySelectorAll('.translation-row');
                    rows.forEach(row => {
                        if (!onlyEmpty) {
                            row.classList.remove('d-none');
                            return;
                        }
                        if (row.classList.contains('is-empty')) {
                            row.classList.remove('d-none');
                        } else {
                            row.classList.add('d-none');
                        }
                    });
                };
                toggle.addEventListener('change', applyFilter);
            }

            // Chunked save of translations to avoid huge single request
            const form = document.getElementById('translationsForm');
            if (!form) {
                return;
            }

            const chunkUrl = form.getAttribute('data-chunk-url');
            const csrfToken = form.querySelector('input[name=_token]')?.value || '';
            const typeInput = form.querySelector('input[name=type]');
            const submitBtn = document.getElementById('btnSaveTranslations');
            const progressEl = document.getElementById('translationsProgress');

            function getKeyFromInputName(name) {
                // name format: translations[some.key]
                const match = name.match(/^translations\[(.+)\]$/);
                return match ? match[1] : null;
            }

            async function postChunk(translationsChunk) {
                const payload = {
                    type: typeInput ? typeInput.value : 'admin',
                    translations: translationsChunk
                };

                const response = await fetch(chunkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    let message = 'Request failed';
                    try {
                        const data = await response.json();
                        if (data && data.message) {
                            message = data.message;
                        }
                    } catch (e) {
                        // ignore parse error
                    }
                    throw new Error(message);
                }

                return response.json();
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const inputs = Array.from(form.querySelectorAll('input[name^="translations["]'));
                if (!inputs.length) {
                    form.submit(); // nothing to chunk
                    return;
                }

                const chunkSize = 150; // keys per request
                const total = inputs.length;
                let processed = 0;

                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (progressEl) {
                    progressEl.classList.remove('d-none');
                    progressEl.textContent = `0 / ${total}`;
                }

                try {
                    for (let i = 0; i < total; i += chunkSize) {
                        const slice = inputs.slice(i, i + chunkSize);
                        const chunk = {};

                        slice.forEach(inp => {
                            const key = getKeyFromInputName(inp.name);
                            if (!key) return;
                            chunk[key] = inp.value || '';
                        });

                        await postChunk(chunk);

                        processed += slice.length;
                        if (progressEl) {
                            progressEl.textContent = `${processed} / ${total}`;
                        }
                    }

                    // All chunks saved successfully – reload page or show a toast
                    if (progressEl) {
                        progressEl.textContent = window.trans
                            ? window.trans['Data Updated Successfully'] || 'Data Updated Successfully'
                            : 'Data Updated Successfully';
                    }

                    // Small delay so user can see the message
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                } catch (error) {
                    if (progressEl) {
                        progressEl.textContent = error.message || 'Error saving translations';
                        progressEl.classList.remove('d-none');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }
            });
        })();
    </script>
@endsection
