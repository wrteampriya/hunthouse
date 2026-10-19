@extends('layouts.main')

@section('title')
    {{ __('Watermark Settings') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>{{ __('Watermark Settings') }}</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        {!! Form::open(['url' => route('watermark-settings-store'), 'data-parsley-validate', 'class' => 'create-form', 'data-success-function'=> "formSuccessFunction", 'enctype' => 'multipart/form-data']) !!}
            {{ csrf_field() }}
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Watermark Configuration') }}</h6>
                            </div>

                            {{-- Enable/Disable Watermark --}}
                            <div class="form-group mt-3">
                                <label class="form-label" for="watermark_enabled">{{ __('Enable Watermark') }}</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="watermark_enabled" id="watermark_enabled" value="1"
                                           {{ ($watermarkSettings['enabled'] ?? 0) == 1 ? 'checked' : '' }}
                                           onchange="toggleWatermarkSettings(); updateEnabledLabel();">
                                    <input type="hidden" name="watermark_enabled" value="0" id="watermark_enabled_hidden">
                                    <label class="form-check-label" for="watermark_enabled" id="enabled-label">
                                        {{ ($watermarkSettings['enabled'] ?? 0) == 1 ? __('Enabled') : __('Disabled') }}
                                    </label>
                                </div>
                                <small class="text-muted">{{ __('When enabled, watermarks will be applied to uploaded images') }}</small>
                            </div>

                            {{-- Watermark Image Upload --}}
                            <div class="form-group mt-3" id="watermark-image-group">
                                <label class="form-label" for="watermark_image">{{ __('Watermark Image') }}</label>
                                <input type="file" name="watermark_image" id="watermark_image" class="filepond"
                                       accept="image/png,image/jpeg,image/jpg">
                                <small class="text-muted">{{ __('Upload a custom watermark image (PNG, JPG, JPEG). If not uploaded, default logo will be used.') }}</small>
                                @if(!empty($watermarkSettings['watermark_image']))
                                    <div class="mt-2">
                                        <p class="text-success mb-1"><small>{{ __('Current Watermark:') }}</small></p>
                                        <img src="{{ asset('assets/images/logo/' . $watermarkSettings['watermark_image']) }}"
                                             alt="Current Watermark"
                                             style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 5px; border-radius: 4px;"
                                             id="current-watermark-preview">
                                    </div>
                                @else
                                    <div class="mt-2">
                                        <p class="text-info mb-1"><small>{{ __('Using default logo: Company Logo') }}</small></p>
                                    </div>
                                @endif
                                <div id="new-watermark-preview" class="mt-2" style="display: none;">
                                    <p class="text-success mb-1"><small>{{ __('New Watermark Preview:') }}</small></p>
                                    <img id="new-watermark-preview-img" src="" alt="New Watermark"
                                         style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">
                                </div>
                            </div>

                            {{-- Opacity --}}
                            <div class="form-group mt-3" id="watermark-settings-group">
                                <label class="form-label" for="opacity">{{ __('Opacity') }} (<span id="opacity-value">{{ $watermarkSettings['opacity'] ?? 25 }}</span>%)</label>
                                <input type="range" name="opacity" id="opacity" class="form-range" min="0" max="100"
                                       value="{{ $watermarkSettings['opacity'] ?? 25 }}"
                                       data-parsley-required="true"
                                       oninput="updatePreview(); document.getElementById('opacity-value').textContent = this.value;">
                                <div class="d-flex justify-content-between">
                                    <small>0%</small>
                                    <small>100%</small>
                                </div>
                            </div>

                            {{-- Size --}}
                            <div class="form-group mt-3">
                                <label class="form-label" for="size">{{ __('Size') }} (<span id="size-value">{{ $watermarkSettings['size'] ?? 10 }}</span>%)</label>
                                <input type="range" name="size" id="size" class="form-range" min="1" max="100"
                                       value="{{ $watermarkSettings['size'] ?? 10 }}"
                                       data-parsley-required="true"
                                       oninput="updatePreview(); document.getElementById('size-value').textContent = this.value;">
                                <div class="d-flex justify-content-between">
                                    <small>1%</small>
                                    <small>100%</small>
                                </div>
                            </div>

                            {{-- Style --}}
                            <div class="form-group mt-3">
                                <label class="form-label" for="style">{{ __('Style') }}</label>
                                <select name="style" id="style" class="form-select" data-parsley-required="true" onchange="updatePreview(); togglePositionField();">
                                    <option value="tile" {{ ($watermarkSettings['style'] ?? 'tile') == 'tile' ? 'selected' : '' }}>{{ __('Tile (Repeat Pattern)') }}</option>
                                    <option value="single" {{ ($watermarkSettings['style'] ?? 'tile') == 'single' ? 'selected' : '' }}>{{ __('Single Watermark') }}</option>
                                    <option value="center" {{ ($watermarkSettings['style'] ?? 'tile') == 'center' ? 'selected' : '' }}>{{ __('Center Only') }}</option>
                                </select>
                            </div>

                            {{-- Position --}}
                            <div class="form-group mt-3" id="position-group">
                                <label class="form-label" for="position">{{ __('Position') }}</label>
                                <select name="position" id="position" class="form-select" data-parsley-required="true" onchange="updatePreview();">
                                    <option value="top-left" {{ ($watermarkSettings['position'] ?? 'center') == 'top-left' ? 'selected' : '' }}>{{ __('Top Left') }}</option>
                                    <option value="top-right" {{ ($watermarkSettings['position'] ?? 'center') == 'top-right' ? 'selected' : '' }}>{{ __('Top Right') }}</option>
                                    <option value="bottom-left" {{ ($watermarkSettings['position'] ?? 'center') == 'bottom-left' ? 'selected' : '' }}>{{ __('Bottom Left') }}</option>
                                    <option value="bottom-right" {{ ($watermarkSettings['position'] ?? 'center') == 'bottom-right' ? 'selected' : '' }}>{{ __('Bottom Right') }}</option>
                                    <option value="center" {{ ($watermarkSettings['position'] ?? 'center') == 'center' ? 'selected' : '' }}>{{ __('Center') }}</option>
                                </select>
                                <small class="text-muted" id="position-note" style="display: none;">{{ __('Position is automatically set to center for this style') }}</small>
                            </div>

                            {{-- Rotation --}}
                            <div class="form-group mt-3">
                                <label class="form-label" for="rotation">{{ __('Rotation') }} (<span id="rotation-value">{{ $watermarkSettings['rotation'] ?? 30 }}</span>°)</label>
                                <input type="range" name="rotation" id="rotation" class="form-range" min="0" max="360"
                                       value="{{ $watermarkSettings['rotation'] ?? 30 }}"
                                       oninput="updatePreview(); document.getElementById('rotation-value').textContent = this.value;">
                                <div class="d-flex justify-content-between">
                                    <small>0°</small>
                                    <small>360°</small>
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('Save Settings') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Preview Section --}}
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('Preview') }}</h6>
                            </div>
                            <div class="form-group mt-3">
                                <div id="preview-container" style="position: relative; width: 100%; height: 400px; border: 2px solid #ffffff; border-radius: 8px; overflow: hidden; background: linear-gradient(45deg, #f0f0f0 25%, transparent 25%), linear-gradient(-45deg, #f0f0f0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f0f0f0 75%), linear-gradient(-45deg, transparent 75%, #f0f0f0 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0px;">
                                    <div id="preview-image" style="width: 100%; height: 100%; background: linear-gradient(135deg, #eaebef 0%, #e1dfe4 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold;">
                                        {{ __('Sample Image') }}
                                    </div>
                                    <div id="watermark-preview" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10;"></div>
                                </div>
                                <p class="text-muted mt-2 text-center"><small>{{ __('This is a visual preview of how the watermark will appear') }}</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {!! Form::close() !!}
    </section>
@endsection

@section('script')
<script>
    const defaultWatermarkPath = "{{ asset('assets/images/logo/logo.png') }}";
    const customWatermarkPath = @if(!empty($watermarkSettings['watermark_image'])) "{{ asset('assets/images/logo/' . $watermarkSettings['watermark_image']) }}" @else null @endif;
    let currentWatermarkPath = customWatermarkPath || defaultWatermarkPath;

    function previewWatermarkImage(file) {
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.getElementById('new-watermark-preview');
                const previewImg = document.getElementById('new-watermark-preview-img');
                previewImg.src = e.target.result;
                previewDiv.style.display = 'block';
                currentWatermarkPath = e.target.result;
                updatePreview();
            };
            reader.readAsDataURL(file);
        }
    }

    function updateEnabledLabel() {
        const enabled = document.getElementById('watermark_enabled').checked;
        const label = document.getElementById('enabled-label');
        label.textContent = enabled ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}';
    }

    function updateEnabledLabel() {
        const enabled = document.getElementById('watermark_enabled').checked;
        const label = document.getElementById('enabled-label');
        if (label) {
            label.textContent = enabled ? '{{ __('Enabled') }}' : '{{ __('Disabled') }}';
        }
    }

    function toggleWatermarkSettings() {
        const enabled = document.getElementById('watermark_enabled').checked;
        const settingsGroup = document.getElementById('watermark-settings-group');
        const imageGroup = document.getElementById('watermark-image-group');
        const positionGroup = document.getElementById('position-group');
        const styleSelect = document.getElementById('style');
        const opacityInput = document.getElementById('opacity');
        const sizeInput = document.getElementById('size');
        const rotationInput = document.getElementById('rotation');
        const positionSelect = document.getElementById('position');

        if (enabled) {
            document.getElementById('watermark_enabled_hidden').value = '1';
            settingsGroup.style.opacity = '1';
            imageGroup.style.opacity = '1';
            positionGroup.style.opacity = '1';
            styleSelect.disabled = false;
            opacityInput.disabled = false;
            sizeInput.disabled = false;
            rotationInput.disabled = false;
            positionSelect.disabled = (styleSelect.value === 'center');
        } else {
            document.getElementById('watermark_enabled_hidden').value = '0';
            settingsGroup.style.opacity = '0.5';
            imageGroup.style.opacity = '0.5';
            positionGroup.style.opacity = '0.5';
            styleSelect.disabled = true;
            opacityInput.disabled = true;
            sizeInput.disabled = true;
            rotationInput.disabled = true;
            positionSelect.disabled = true;
        }
        updatePreview();
    }

    function createWatermarkElement(size, opacity, rotation, applyRotation = true) {
        const watermark = document.createElement('img');
        watermark.src = currentWatermarkPath;
        watermark.style.opacity = opacity / 100;
        if (applyRotation) {
            watermark.style.transform = `rotate(${rotation}deg)`;
        }
        watermark.style.width = size + 'px';
        watermark.style.height = 'auto';
        watermark.style.imageRendering = 'auto';
        watermark.style.maxWidth = size + 'px';
        watermark.style.maxHeight = size + 'px';
        watermark.onerror = function() {
            // If logo doesn't exist, create a placeholder
            const placeholder = document.createElement('div');
            placeholder.style.width = size + 'px';
            placeholder.style.height = size + 'px';
            placeholder.style.border = '2px dashed #999';
            placeholder.style.display = 'flex';
            placeholder.style.alignItems = 'center';
            placeholder.style.justifyContent = 'center';
            placeholder.style.opacity = opacity / 100;
            if (applyRotation) {
                placeholder.style.transform = `rotate(${rotation}deg)`;
            }
            placeholder.textContent = 'LOGO';
            placeholder.style.fontSize = Math.max(12, size / 4) + 'px';
            placeholder.style.color = '#999';
            placeholder.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
            placeholder.style.borderRadius = '4px';
            this.parentNode.replaceChild(placeholder, this);
        };
        watermark.onload = function() {
            // Ensure image maintains aspect ratio
            if (this.naturalWidth > 0 && this.naturalHeight > 0) {
                const aspectRatio = this.naturalHeight / this.naturalWidth;
                this.style.height = (size * aspectRatio) + 'px';
            }
        };
        return watermark;
    }

    function updatePreview() {
        const enabled = document.getElementById('watermark_enabled').checked;
        const previewContainer = document.getElementById('preview-container');
        const watermarkPreview = document.getElementById('watermark-preview');

        // Clear previous watermark
        watermarkPreview.innerHTML = '';

        // If watermark is disabled, don't show preview
        if (!enabled) {
            return;
        }

        const opacity = parseInt(document.getElementById('opacity').value);
        const size = parseInt(document.getElementById('size').value);
        const style = document.getElementById('style').value;
        const position = document.getElementById('position').value;
        const rotation = parseInt(document.getElementById('rotation').value);

        const containerWidth = previewContainer.offsetWidth;
        const containerHeight = previewContainer.offsetHeight;

        // Calculate watermark size
        const watermarkSize = (containerWidth * size) / 100;

        if (style === 'tile') {
            // Create tiled pattern
            const spacing = watermarkSize * 1.5;
            const yPositions = [];
            for (let y = 0; y < containerHeight; y += spacing) {
                yPositions.push(y);
            }
            // Ensure last row is at the bottom - calculate bottom margin to maintain spacing
            const lastY = yPositions[yPositions.length - 1];
            const isLastRow = (y) => y === lastY;
            // Calculate bottom margin to maintain similar spacing as top (10px from top)
            const bottomMargin = 10;

            for (let i = 0; i < yPositions.length; i++) {
                const y = yPositions[i];
                const isBottomRow = isLastRow(y);
                for (let x = 0; x < containerWidth; x += spacing) {
                    const tile = createWatermarkElement(watermarkSize, opacity, rotation);
                    tile.style.position = 'absolute';
                    tile.style.left = x + 'px';
                    if (isBottomRow) {
                        // Use bottom positioning to stick to bottom
                        tile.style.bottom = bottomMargin + 'px';
                        tile.style.top = 'auto';
                    } else {
                        tile.style.top = y + 'px';
                        tile.style.bottom = 'auto';
                    }
                    watermarkPreview.appendChild(tile);
                }
            }
        } else if (style === 'center') {
            // Center only - always use center position
            const watermark = createWatermarkElement(watermarkSize, opacity, rotation, false);
            watermark.style.position = 'absolute';
            watermark.style.left = '50%';
            watermark.style.top = '50%';
            watermark.style.transform = `translate(-50%, -50%) rotate(${rotation}deg)`;
            watermarkPreview.appendChild(watermark);
        } else if (style === 'single') {
            // Single watermark - use position dropdown
            const watermark = createWatermarkElement(watermarkSize, opacity, rotation);
            watermark.style.position = 'absolute';

            // Calculate position based on dropdown
            let left = 0, top = 0, bottom = null;
            switch(position) {
                case 'top-left':
                    left = 10;
                    top = 10;
                    break;
                case 'top-right':
                    left = containerWidth - watermarkSize - 10;
                    top = 10;
                    break;
                case 'bottom-left':
                    left = 10;
                    bottom = 10;
                    break;
                case 'bottom-right':
                    left = containerWidth - watermarkSize - 10;
                    bottom = 10;
                    break;
                case 'center':
                default:
                    left = (containerWidth - watermarkSize) / 2;
                    top = (containerHeight - watermarkSize) / 2;
                    break;
            }

            watermark.style.left = left + 'px';
            if (bottom !== null) {
                watermark.style.bottom = bottom + 'px';
                watermark.style.top = 'auto';
            } else {
                watermark.style.top = top + 'px';
                watermark.style.bottom = 'auto';
            }
            watermarkPreview.appendChild(watermark);
        }
    }

    function togglePositionField() {
        const style = document.getElementById('style').value;
        const positionSelect = document.getElementById('position');
        const positionGroup = document.getElementById('position-group');
        const positionNote = document.getElementById('position-note');
        const savedPosition = "{{ $watermarkSettings['position'] ?? 'center' }}";

        if (style === 'tile' || style === 'center') {
            // Hide position field for tile style
            positionGroup.style.display = 'none';
            // Set position to saved database value or default to center
            positionSelect.value = savedPosition || 'center';
        } else {
            // Show and enable position field for single style
            positionGroup.style.display = 'block';
            positionSelect.disabled = false;
            positionSelect.style.opacity = '1';
            positionNote.style.display = 'none';
        }
    }

    // Initialize preview on page load
    $(document).ready(function() {
        // Wait for container to be fully rendered and FilePond to initialize
        setTimeout(function() {
            toggleWatermarkSettings();
            togglePositionField();
            updatePreview();

            // Hook into FilePond events for watermark image upload
            const watermarkInput = document.querySelector('#watermark_image');
            if (watermarkInput && typeof FilePond !== 'undefined') {
                // Try to get the FilePond instance (it may take a moment to initialize)
                let pond = FilePond.find(watermarkInput);

                // If not found immediately, try again after a short delay
                if (!pond) {
                    setTimeout(function() {
                        pond = FilePond.find(watermarkInput);
                        if (pond) {
                            attachFilePondEvents(pond);
                        }
                    }, 200);
                } else {
                    attachFilePondEvents(pond);
                }
            }
        }, 100);

        // Update preview when window is resized
        window.addEventListener('resize', function() {
            setTimeout(updatePreview, 100);
        });
    });

    function attachFilePondEvents(pond) {
        // Handle file add event
        pond.on('addfile', (error, file) => {
            if (!error && file && file.file) {
                previewWatermarkImage(file.file);
            }
        });

        // Handle file remove event
        pond.on('removefile', () => {
            const previewDiv = document.getElementById('new-watermark-preview');
            if (previewDiv) {
                previewDiv.style.display = 'none';
            }
            // Reset to default watermark
            currentWatermarkPath = customWatermarkPath || defaultWatermarkPath;
            updatePreview();
        });
    }

    function formSuccessFunction(response) {
        if(!response.error && !response.warning){
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    }
</script>
@endsection

