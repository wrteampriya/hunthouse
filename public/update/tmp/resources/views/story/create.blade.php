@extends('layouts.main')

@section('title')
    {{ __('Upload Story') }}
@endsection

@section('css')
<style>
    /* ── Native eBroker Theme Matching for Studio ── */
    .studio-dropzone {
        border: 2px dashed rgba(0, 0, 0, 0.15);
        border-radius: 8px;
        padding: 20px 14px;
        text-align: center;
        background: rgba(0, 0, 0, 0.012);
        cursor: pointer;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .studio-dropzone:hover {
        border-color: var(--bs-primary, #087C7C);
        background: rgba(8, 124, 124, 0.025);
    }
    .dropzone-icon-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(8, 124, 124, 0.1);
        color: var(--bs-primary, #087C7C);
        margin-bottom: 8px;
    }

    /* ── Story Preview Frame ── */
    .phone-simulator-frame {
        width: min(280px, 100%);
        margin: 0 auto;
        position: relative;
    }
    .phone-simulator-screen {
        width: 100%;
        aspect-ratio: 9 / 16;
        border-radius: 16px;
        overflow: hidden;
        background: #111827;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ── Filmstrip Trimmer Polish ── */
    #filmstrip_wrap {
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.25);
    }
    #sel_window {
        border: 3px solid var(--bs-primary, #087C7C) !important;
        box-shadow: 0 0 12px rgba(8, 124, 124, 0.35);
    }
    #handle_l, #handle_r {
        background: var(--bs-primary, #087C7C) !important;
    }
    #thumb_grid > div {
        transition: transform 0.2s ease;
    }
    #thumb_grid > div:hover {
        transform: translateY(-2px);
    }
    #property_select_wrap .select2-container,
    #project_select_wrap .select2-container {
        width: 100% !important;
    }
</style>
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('story.index') }}">{{ __('View Stories') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Upload') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    {!! Form::open(['route' => 'story.store', 'id' => 'storyForm', 'files' => true]) !!}
        <input type="file" id="media_submit" name="media" style="display:none">
        <input type="file" id="thumb_submit" name="thumbnail" style="display:none">
        <input type="hidden" id="listing_media_path_input" name="listing_media_path">

        <div class="row">
            {{-- ── Left Column: Listing Details & Media File Selection ── --}}
            <div class="col-12 col-lg-6">
                <div class="card">
                    <h3 class="card-header">{{ __('Listing & Media Details') }}</h3>
                    <hr>
                    <div class="card-body">
                        @if (session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Entity Type --}}
                        <div class="col-md-12 col-12 form-group mandatory">
                            {{ Form::label('entity_type', __('Listing Type'), ['class' => 'form-label col-12']) }}
                            <select name="entity_type" id="entity_type" class="form-select form-control-sm">
                                <option value="property" @selected(old('entity_type','property')==='property')>{{ __('Property') }}</option>
                                <option value="project"  @selected(old('entity_type')==='project')>{{ __('Project') }}</option>
                            </select>
                        </div>

                        {{-- Select Listing --}}
                        <div class="col-md-12 col-12 form-group mandatory mt-3">
                            {{ Form::label('entity_id_select', __('Select Listing'), ['class' => 'form-label col-12']) }}
                            <div id="property_select_wrap" style="{{ old('entity_type','property')==='property' ? '' : 'display:none' }}">
                                <select id="property_select" class="select2 form-control">
                                    <option value="">{{ __('Select Property') }}</option>
                                    @foreach ($properties as $p)
                                        <option value="{{ $p->id }}" @selected(old('entity_id')==$p->id)>{{ $p->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="project_select_wrap" style="{{ old('entity_type')==='project' ? '' : 'display:none' }}">
                                <select id="project_select" class="select2 form-control">
                                    <option value="">{{ __('Select Project') }}</option>
                                    @foreach ($projects as $p)
                                        <option value="{{ $p->id }}" @selected(old('entity_id')==$p->id)>{{ $p->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="hidden" name="entity_id" id="entity_id_hidden">
                        </div>

                        {{-- Listing Media Picker (loaded via AJAX on listing select) --}}
                        <div id="listing_media_section" class="col-md-12 col-12 mt-3 d-none">
                            <label class="form-label fw-semibold">{{ __('Pick from Listing Images') }}</label>
                            <div id="listing_media_loading" class="text-muted small d-none">
                                <div class="spinner-border spinner-border-sm me-1"></div> {{ __('Loading images...') }}
                            </div>
                            <div id="listing_images_wrap" class="d-none">
                                <div id="listing_images_grid" class="d-flex flex-wrap gap-2 mb-3"></div>
                            </div>
                            <hr class="my-3">
                        </div>

                        {{-- Media Type --}}
                        <div class="col-md-12 col-12 form-group mandatory mt-3">
                            {{ Form::label('media_type', __('Media Type'), ['class' => 'form-label col-12']) }}
                            <select name="media_type" id="media_type" class="form-select form-control-sm">
                                <option value="image" @selected(old('media_type','image')==='image')>{{ __('Image') }}</option>
                                <option value="video" @selected(old('media_type')==='video')>{{ __('Video') }}</option>
                            </select>
                        </div>

                        {{-- Image Section --}}
                        <div id="image_section" class="col-md-12 col-12 form-group mandatory mt-3">
                            {{ Form::label('image_file', __('Image File'), ['class' => 'form-label col-12']) }}
                            <div class="studio-dropzone" onclick="document.getElementById('image_file').click()">
                                <div class="dropzone-icon-box">
                                    <i class="fas fa-cloud-upload-alt fs-4"></i>
                                </div>
                                <h6 class="fw-bold mb-1">{{ __('Select Image File') }}</h6>
                                <p class="text-muted small mb-0">{{ __('JPG, PNG, WEBP (Max 100 MB)') }}</p>
                                <input type="file" id="image_file" class="d-none" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <div id="image_selected_badge" class="mt-2 p-2 rounded bg-success bg-opacity-10 text-success d-none align-items-center gap-2 small fw-semibold">
                                <i class="fas fa-check-circle"></i>
                                <span id="image_selected_name" class="flex-grow-1">{{ __('Image selected') }}</span>
                                <button type="button" id="image_remove_btn" class="btn-close" style="font-size:0.7rem;" aria-label="{{ __('Remove image') }}"></button>
                            </div>
                        </div>

                        {{-- Video Section --}}
                        <div id="video_section" class="col-md-12 col-12 form-group mandatory mt-3" style="display:none">
                            {{ Form::label('video_file', __('Video File'), ['class' => 'form-label col-12']) }}
                            <div class="studio-dropzone" onclick="document.getElementById('video_file').click()">
                                <div class="dropzone-icon-box">
                                    <i class="fas fa-film fs-4"></i>
                                </div>
                                <h6 class="fw-bold mb-1">{{ __('Select Video File') }}</h6>
                                <p class="text-muted small mb-0">{{ __('MP4, MOV, WEBM (Max') }} {{ $maxDuration }} {{ __('Sec · Max 100MB)') }}</p>
                                <input type="file" id="video_file" class="d-none" accept="video/mp4,video/quicktime,video/webm">
                            </div>
                            <div id="video_selected_badge" class="mt-2 p-2 rounded bg-primary bg-opacity-10 text-primary d-none align-items-center gap-2 small fw-semibold">
                                <i class="fas fa-check-circle"></i>
                                <span id="video_selected_name" class="flex-grow-1">{{ __('Video selected') }}</span>
                                <button type="button" id="video_remove_btn" class="btn-close" style="font-size:0.7rem;" aria-label="{{ __('Remove video') }}"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Right Column: Preview & Trimmer Studio ── --}}
            <div class="col-12 col-lg-6 mt-4 mt-lg-0">
                <div class="card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h3 class="card-header">{{ __('Preview & Trimmer') }}</h3>
                        <hr>
                        <div class="card-body">
                            {{-- Empty State before file upload --}}
                            <div id="studio_empty_state" class="text-center py-5 text-muted">
                                <i class="fas fa-mobile-alt fs-1 d-block mb-2"></i>
                                <h6 class="fw-bold">{{ __('No Media Selected') }}</h6>
                                <p class="small mb-0">{{ __('Upload an image or video to preview and trim.') }}</p>
                            </div>

                            {{-- Image Live Phone Preview --}}
                            <div id="image_phone_ui" class="text-center py-2" style="display:none">
                                <label class="form-label fw-semibold small text-muted mb-3 d-block">{{ __('Preview') }}</label>
                                <div class="phone-simulator-frame">
                                    <div class="phone-simulator-screen">
                                        <img id="image_phone_preview_el" src="" alt="Live Preview" class="w-100 h-100" style="object-fit:contain;">
                                    </div>
                                </div>
                            </div>

                            {{-- Video Trimmer & Phone Simulator --}}
                            <div id="video_ui" style="display:none">
                                {{-- Trimmer Header --}}
                                <div id="video_trimmer_header" class="p-3 bg-dark text-white rounded mb-3">
                                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span id="dur_badge" class="badge bg-primary px-3 py-1 fw-bold" style="font-size:0.85rem;">
                                                {{ $maxDuration }}s Max
                                            </span>
                                            <span class="text-light opacity-75 small">{{ __('Drag handles to trim clip') }}</span>
                                        </div>
                                        <span id="clip_range_label" class="text-info fw-bold small ms-auto"></span>
                                    </div>

                                    {{-- Filmstrip Trimmer Track --}}
                                    <div id="filmstrip_wrap" style="position:relative; border-radius:6px; overflow:hidden; background:#000; height:70px; user-select:none;">
                                        <div id="filmstrip_frames" style="display:flex; height:100%;"></div>
                                        <div id="filmstrip_loading" style="position:absolute;inset:0;background:rgba(0,0,0,0.75);display:flex;align-items:center;justify-content:center;gap:8px;">
                                            <div class="spinner-border spinner-border-sm text-info"></div>
                                            <span class="text-white small">{{ __('Generating frames...') }}</span>
                                        </div>
                                        <div id="mask_left" style="position:absolute;top:0;left:0;bottom:0;background:rgba(0,0,0,0.65);pointer-events:none;"></div>
                                        <div id="mask_right" style="position:absolute;top:0;right:0;bottom:0;background:rgba(0,0,0,0.65);pointer-events:none;"></div>
                                        <div id="sel_window" style="position:absolute;top:0;bottom:0;border:3px solid #087C7C;box-sizing:border-box;pointer-events:none;">
                                            <div id="handle_l" style="position:absolute;left:-3px;top:0;bottom:0;width:16px;background:#087C7C;cursor:ew-resize;pointer-events:all;display:flex;align-items:center;justify-content:center;border-radius:4px 0 0 4px;">
                                                <div style="width:3px;height:24px;background:rgba(255,255,255,0.9);border-radius:2px;"></div>
                                            </div>
                                            <div id="handle_r" style="position:absolute;right:-3px;top:0;bottom:0;width:16px;background:#087C7C;cursor:ew-resize;pointer-events:all;display:flex;align-items:center;justify-content:center;border-radius:0 4px 4px 0;">
                                                <div style="width:3px;height:24px;background:rgba(255,255,255,0.9);border-radius:2px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Preview & Thumbnail Picker --}}
                                <div class="row g-3 align-items-center">
                                    <div class="col-12 col-xl-5 text-center">
                                        <label class="form-label fw-semibold small text-muted mb-2 d-block">{{ __('Preview') }}</label>
                                        <div class="phone-simulator-frame">
                                            <div class="phone-simulator-screen">
                                                <video id="video_preview" class="w-100 h-100" style="object-fit:contain;" muted playsinline loop></video>
                                                <img id="thumb_preview_overlay" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:5;">
                                                <div id="thumb_play_btn" onclick="hideThumbOverlay()" style="display:none;position:absolute;inset:0;z-index:6;align-items:center;justify-content:center;cursor:pointer;">
                                                    <div style="width:52px;height:52px;border-radius:50%;background:rgba(0,0,0,0.55);border:2px solid rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;">
                                                        <i class="fas fa-play text-white" style="margin-left:3px;font-size:1.1rem;"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-xl-7">
                                        <div class="p-3 bg-light rounded border">
                                            <label class="form-label fw-bold small text-dark">{{ __('Select Cover Thumbnail') }}</label>
                                            <p class="text-muted mb-2" style="font-size:0.8rem;">
                                                {{ __('Click a frame below to set as cover.') }}
                                            </p>

                                            <div id="thumb_grid" class="d-flex gap-2 flex-wrap mb-3" style="min-height:95px;"></div>

                                            <div class="pt-2 border-top">
                                                <label class="form-label small fw-semibold text-dark">{{ __('Custom Cover Image') }}</label>
                                                <input type="file" id="custom_thumb" class="form-control form-control-sm bg-white" accept="image/jpeg,image/png,image/webp">
                                                <small class="text-muted" style="font-size:0.75rem;">JPG, PNG, WEBP (Max 5MB)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>{{-- /video_ui --}}
                        </div>
                    </div>

                    {{-- ── Right Card Footer: Instant Submit right where user finishes trimming! ── --}}
                    <div id="studio_action_footer" class="card-footer bg-transparent border-top p-3" style="display:none">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">{{ __('Ready to publish?') }}</span>
                            <button type="submit" id="upload_btn" class="btn btn-primary px-4 submit-action-btn">
                                <span id="upload_btn_text">{{ __('Upload Story') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    {!! Form::close() !!}
@endsection

@section('script')
<script>
const MAX_DURATION = {{ $maxDuration }};

// ── DOM refs ──────────────────────────────────────────────────────────────────
const entityTypeEl     = document.getElementById('entity_type');
const propertySelect   = document.getElementById('property_select');
const projectSelect    = document.getElementById('project_select');
const entityIdHidden   = document.getElementById('entity_id_hidden');
const mediaTypeEl      = document.getElementById('media_type');
const imageSection     = document.getElementById('image_section');
const videoSection     = document.getElementById('video_section');
const imageFileEl      = document.getElementById('image_file');
const videoFileEl      = document.getElementById('video_file');
const mediaSubmit      = document.getElementById('media_submit');
const thumbSubmit      = document.getElementById('thumb_submit');
const videoUi          = document.getElementById('video_ui');
const videoPreview     = document.getElementById('video_preview');
const filmstripWrap    = document.getElementById('filmstrip_wrap');
const filmstripFrames  = document.getElementById('filmstrip_frames');
const filmstripLoading = document.getElementById('filmstrip_loading');
const maskLeft         = document.getElementById('mask_left');
const maskRight        = document.getElementById('mask_right');
const selWindow        = document.getElementById('sel_window');
const handleL          = document.getElementById('handle_l');
const handleR          = document.getElementById('handle_r');
const clipRangeLabel   = document.getElementById('clip_range_label');
const thumbGrid        = document.getElementById('thumb_grid');
const customThumb      = document.getElementById('custom_thumb');
const uploadBtn        = document.getElementById('upload_btn');
const uploadBtnText    = document.getElementById('upload_btn_text');
const studioActionFooter = document.getElementById('studio_action_footer');

// Studio UI extra refs
const studioEmptyState    = document.getElementById('studio_empty_state');
const imagePhoneUi        = document.getElementById('image_phone_ui');
const imagePhonePreviewEl = document.getElementById('image_phone_preview_el');
const imageLiveType    = document.getElementById('image_live_type');
const imageLiveTitle   = document.getElementById('image_live_title');
const videoLiveType    = document.getElementById('video_live_type');
const videoLiveTitle   = document.getElementById('video_live_title');
const imageSelectedBadge = document.getElementById('image_selected_badge');
const imageRemoveBtn     = document.getElementById('image_remove_btn');
const videoRemoveBtn     = document.getElementById('video_remove_btn');
const imageSelectedName  = document.getElementById('image_selected_name');
const videoSelectedBadge = document.getElementById('video_selected_badge');
const videoSelectedName  = document.getElementById('video_selected_name');

// ── Ensure Upload Spinner is NEVER stuck on page load / browser back ──────────
function showUploadProgress(label) {
    if (uploadBtnText) {
        uploadBtnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" style="vertical-align:-2px;"></span>' + (label || '{{ __("Uploading...") }}');
    }
    document.querySelectorAll('.submit-action-btn').forEach(btn => btn.disabled = true);
}
function resetUploadUI() {
    if (uploadBtnText) uploadBtnText.innerHTML = '{{ __("Upload Story") }}';
    document.querySelectorAll('.submit-action-btn').forEach(btn => btn.disabled = false);
}
window.addEventListener('pageshow', resetUploadUI);
document.addEventListener('DOMContentLoaded', resetUploadUI);

const ENTITY_MEDIA_URL       = "{{ route('story.entity-media') }}";
const listingMediaPathInput  = document.getElementById('listing_media_path_input');

// ── Entity select & Live Listing Title Sync ───────────────────────────────────
function syncEntityId() {
    const vis = entityTypeEl.value === 'property' ? propertySelect : projectSelect;
    entityIdHidden.value = vis.value;

    const selOption = vis.options[vis.selectedIndex];
    const titleText = (selOption && selOption.value !== "") ? selOption.text : "Listing Title";
    const typeLabel = entityTypeEl.value === 'property' ? 'Property' : 'Project';

    if (imageLiveType) imageLiveType.textContent = typeLabel;
    if (imageLiveTitle) imageLiveTitle.textContent = titleText;
    if (videoLiveType) videoLiveType.textContent = typeLabel;
    if (videoLiveTitle) videoLiveTitle.textContent = titleText;

    if (vis.value) {
        fetchListingMedia(entityTypeEl.value, vis.value);
    } else {
        hideListingMedia();
    }
}

function applyEntityType(type) {
    if (type === 'property') {
        $('#property_select_wrap').show();
        $('#project_select_wrap').hide();
    } else {
        $('#property_select_wrap').hide();
        $('#project_select_wrap').show();
    }
    syncEntityId();
}
// ── Listing Media Picker DOM refs (must be before applyEntityType call) ───────
const listingMediaSection  = document.getElementById('listing_media_section');
const listingMediaLoading  = document.getElementById('listing_media_loading');
const listingImagesWrap    = document.getElementById('listing_images_wrap');
const listingImagesGrid    = document.getElementById('listing_images_grid');


$('#property_select').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '{{ __("Select Property") }}' });
$('#project_select').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '{{ __("Select Project") }}' });

entityTypeEl.addEventListener('change', e => applyEntityType(e.target.value));
$('#property_select').on('change', syncEntityId);
$('#project_select').on('change', syncEntityId);
applyEntityType(entityTypeEl.value);

function resetStudioIfListingSelected() {
    if (!listingMediaPathInput.value) return;
    listingMediaPathInput.value = '';
    studioEmptyState.style.display    = '';
    imagePhoneUi.style.display        = 'none';
    studioActionFooter.style.display  = 'none';
    imageSelectedBadge.classList.add('d-none');
    imageSelectedBadge.classList.remove('d-flex');
}

function hideListingMedia() {
    resetStudioIfListingSelected();
    listingMediaSection.classList.add('d-none');
    listingImagesGrid.innerHTML = '';
}

function fetchListingMedia(type, id) {
    resetStudioIfListingSelected();
    listingMediaSection.classList.remove('d-none');
    listingMediaLoading.classList.remove('d-none');
    listingImagesWrap.classList.add('d-none');
    listingImagesGrid.innerHTML = '';

    fetch(ENTITY_MEDIA_URL + '?type=' + type + '&id=' + id)
        .then(r => r.json())
        .then(data => {
            listingMediaLoading.classList.add('d-none');

            if (data.images && data.images.length) {
                listingImagesWrap.classList.remove('d-none');
                data.images.forEach(img => {
                    const wrap = document.createElement('div');
                    wrap.className = 'listing-media-item position-relative';
                    wrap.style.cssText = 'cursor:pointer; border:3px solid transparent; border-radius:8px; overflow:hidden; transition:border-color .15s;';
                    wrap.innerHTML = `
                        <img src="${img.url}" style="width:80px;height:80px;object-fit:cover;display:block;"
                             onerror="this.parentElement.remove()">
                        ${img.label ? `<div class="position-absolute bottom-0 start-0 end-0 text-center py-1"
                             style="background:rgba(0,0,0,0.55);font-size:10px;color:#fff;">${img.label}</div>` : ''}
                    `;
                    wrap.addEventListener('click', () => selectListingImage(wrap, img.url, img.path, img.label));
                    listingImagesGrid.appendChild(wrap);
                });
            }
        })
        .catch(() => {
            listingMediaLoading.classList.add('d-none');
        });
}

function selectListingImage(wrap, url, path, label) {
    listingImagesGrid.querySelectorAll('.listing-media-item').forEach(el => el.style.borderColor = 'transparent');
    wrap.style.borderColor = '#087C7C';

    // Clear any file selections so only listing image is active
    listingMediaPathInput.value = path;
    imageFileEl.value = '';
    videoFileEl.value = '';
    imageSelectedBadge.classList.add('d-none');
    imageSelectedBadge.classList.remove('d-flex');
    videoSelectedBadge.classList.add('d-none');
    videoSelectedBadge.classList.remove('d-flex');

    mediaTypeEl.value = 'image';
    applyMediaType('image');

    // Show preview directly from existing URL
    studioEmptyState.style.display = 'none';
    imagePhoneUi.style.display = '';
    studioActionFooter.style.display = '';
    imagePhonePreviewEl.src = url;
    imageSelectedBadge.classList.remove('d-none');
    imageSelectedBadge.classList.add('d-flex');
    imageSelectedName.textContent = label || '{{ __("Listing Image") }}';
}


// ── Media type toggle ─────────────────────────────────────────────────────────
function applyMediaType(type) {
    imageSection.style.display = type === 'image' ? '' : 'none';
    videoSection.style.display = type === 'video' ? '' : 'none';
    
    if (type === 'image') {
        videoUi.style.display = 'none';
        if (imageFileEl.files[0]) {
            studioEmptyState.style.display = 'none';
            imagePhoneUi.style.display = '';
            studioActionFooter.style.display = '';
        } else {
            studioEmptyState.style.display = '';
            imagePhoneUi.style.display = 'none';
            studioActionFooter.style.display = 'none';
        }
    } else {
        imagePhoneUi.style.display = 'none';
        if (videoFileEl.files[0]) {
            studioEmptyState.style.display = 'none';
            videoUi.style.display = '';
            studioActionFooter.style.display = '';
        } else {
            studioEmptyState.style.display = '';
            videoUi.style.display = 'none';
            studioActionFooter.style.display = 'none';
        }
    }
}
mediaTypeEl.addEventListener('change', e => applyMediaType(e.target.value));
applyMediaType(mediaTypeEl.value);

// ── Image preview listener ────────────────────────────────────────────────────
imageFileEl.addEventListener('change', function () {
    if (this.files[0]) {
        listingMediaPathInput.value = '';
        listingImagesGrid.querySelectorAll('.listing-media-item').forEach(el => el.style.borderColor = 'transparent');
        studioEmptyState.style.display = 'none';
        imagePhoneUi.style.display = '';
        studioActionFooter.style.display = '';
        imagePhonePreviewEl.src = URL.createObjectURL(this.files[0]);
        imageSelectedBadge.classList.remove('d-none');
        imageSelectedBadge.classList.add('d-flex');
        imageSelectedName.textContent = this.files[0].name;
    }
});

imageRemoveBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    imageFileEl.value = '';
    imagePhonePreviewEl.src = '';
    imageSelectedBadge.classList.add('d-none');
    imageSelectedBadge.classList.remove('d-flex');
    studioEmptyState.style.display = '';
    imagePhoneUi.style.display = 'none';
    studioActionFooter.style.display = 'none';

    listingMediaPathInput.value = '';
    listingImagesGrid.querySelectorAll('.listing-media-item').forEach(el => el.style.borderColor = 'transparent');
});

// ── Trimmer state ─────────────────────────────────────────────────────────────
let videoDuration       = 0;
let trimStart           = 0;
let trimEnd             = 0;
let selectedThumbCanvas = null;
let thumbCanvases       = [];

function fmt(s) { return (Math.round(s * 10) / 10) + 's'; }

function updateTrimmerUI() {
    if (!videoDuration) return;
    const totalW   = filmstripWrap.offsetWidth || 400;
    const startPx  = (trimStart / videoDuration) * totalW;
    const endPx    = (trimEnd   / videoDuration) * totalW;

    maskLeft.style.width   = startPx + 'px';
    maskRight.style.width  = (totalW - endPx) + 'px';
    selWindow.style.left   = startPx + 'px';
    selWindow.style.width  = Math.max(16, endPx - startPx) + 'px';

    clipRangeLabel.textContent = fmt(trimStart) + ' → ' + fmt(trimEnd) + ' (' + fmt(trimEnd - trimStart) + ')';
    videoPreview.currentTime   = trimStart;
    videoPreview.play().catch(() => {});
}

// Loop preview within trim window
videoPreview.addEventListener('timeupdate', function() {
    if (trimEnd > 0 && this.currentTime >= trimEnd) {
        this.currentTime = trimStart;
    }
});

// ── Bulletproof Seek helper with timeout fallback ─────────────────────────────
function waitForSeek(vid, targetTime, timeoutMs = 350) {
    return new Promise(resolve => {
        if (Math.abs(vid.currentTime - targetTime) < 0.02) {
            resolve();
            return;
        }
        let settled = false;
        const done = () => {
            if (!settled) {
                settled = true;
                vid.removeEventListener('seeked', done);
                vid.removeEventListener('error', done);
                resolve();
            }
        };
        vid.addEventListener('seeked', done, { once: true });
        vid.addEventListener('error', done, { once: true });
        try {
            vid.currentTime = Math.min(targetTime, Math.max(0, vid.duration - 0.05));
        } catch (e) {
            done();
        }
        setTimeout(done, timeoutMs);
    });
}

async function captureFrame(vid, time, w, h) {
    await waitForSeek(vid, time, 350);
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    try {
        c.getContext('2d').drawImage(vid, 0, 0, w, h);
    } catch (e) {}
    return c;
}

async function loadVideoFrames(file) {
    studioEmptyState.style.display = 'none';
    imagePhoneUi.style.display     = 'none';
    videoUi.style.display          = '';
    studioActionFooter.style.display = '';
    filmstripLoading.style.display = 'flex';
    filmstripFrames.innerHTML      = '';
    thumbGrid.innerHTML            = '';
    thumbCanvases                  = [];
    selectedThumbCanvas            = null;
    showThumbOverlay(null);

    videoSelectedBadge.classList.remove('d-none');
    videoSelectedBadge.classList.add('d-flex');
    videoSelectedName.textContent  = file.name;

    const url = URL.createObjectURL(file);
    const extVid = document.createElement('video');
    extVid.src     = url;
    extVid.muted   = true;
    extVid.preload = 'auto';

    try {
        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => resolve(), 2000);
            extVid.addEventListener('loadedmetadata', () => { clearTimeout(timer); resolve(); }, { once: true });
            extVid.addEventListener('error', () => { clearTimeout(timer); reject(); }, { once: true });
        });

        videoDuration = extVid.duration || 10;
        trimStart     = 0;
        trimEnd       = Math.min(videoDuration, MAX_DURATION);

        videoPreview.src = URL.createObjectURL(file);
        videoPreview.load();
        videoPreview.play().catch(() => {});

        const STRIP_N = 10, STRIP_W = 120, STRIP_H = 68;
        for (let i = 0; i < STRIP_N; i++) {
            const time = (i / STRIP_N) * videoDuration;
            const c    = await captureFrame(extVid, time, STRIP_W, STRIP_H);
            const img  = document.createElement('img');
            img.src    = c.toDataURL('image/jpeg', 0.65);
            img.style.cssText = 'flex:1; object-fit:cover; min-width:0; height:100%; display:block;';
            filmstripFrames.appendChild(img);
        }

        const THUMB_N = 6, THUMB_W = 58, THUMB_H = 103;
        for (let i = 0; i < THUMB_N; i++) {
            const time = i === 0 ? 0 : (i / (THUMB_N - 1)) * videoDuration * 0.96;
            const c    = await captureFrame(extVid, time, THUMB_W, THUMB_H);
            thumbCanvases.push(c);

            const wrap  = document.createElement('div');
            wrap.style.cssText = 'position:relative; cursor:pointer; border-radius:6px; overflow:hidden; border:3px solid transparent; transition:border-color .15s, box-shadow .15s; flex-shrink:0;';
            wrap.dataset.idx   = i;

            const img   = document.createElement('img');
            img.src     = c.toDataURL('image/jpeg', 0.82);
            img.style.cssText = 'width:' + THUMB_W + 'px; height:' + THUMB_H + 'px; object-fit:cover; display:block;';
            wrap.appendChild(img);

            const badge = document.createElement('div');
            badge.style.cssText = 'position:absolute;top:4px;right:4px;background:#087C7C;border-radius:50%;width:20px;height:20px;display:none;align-items:center;justify-content:center;';
            badge.innerHTML     = '<i class="fas fa-check" style="color:#fff;font-size:12px;"></i>';
            wrap.appendChild(badge);

            wrap.addEventListener('click', () => selectThumb(i));
            thumbGrid.appendChild(wrap);

            if (i === 0) selectThumb(0);
        }
    } catch (e) {
        console.error('Frame generation error:', e);
    } finally {
        filmstripLoading.style.display = 'none';
        updateTrimmerUI();
        if (extVid.src) URL.revokeObjectURL(extVid.src);
    }
}

const thumbPreviewOverlay = document.getElementById('thumb_preview_overlay');
const thumbPlayBtn        = document.getElementById('thumb_play_btn');

function showThumbOverlay(src) {
    if (!src) {
        thumbPreviewOverlay.style.display = 'none';
        thumbPlayBtn.style.display = 'none';
        return;
    }
    thumbPreviewOverlay.src = src;
    thumbPreviewOverlay.style.display = '';
    thumbPlayBtn.style.display = 'flex';
    videoPreview.pause();
}

function hideThumbOverlay() {
    thumbPreviewOverlay.style.display = 'none';
    thumbPlayBtn.style.display = 'none';
    videoPreview.play().catch(() => {});
}

function selectThumb(idx) {
    thumbGrid.querySelectorAll('[data-idx]').forEach(w => {
        w.style.borderColor = 'transparent';
        w.style.boxShadow   = 'none';
        w.querySelector('div').style.display = 'none';
    });
    const wrap = thumbGrid.querySelector('[data-idx="' + idx + '"]');
    if (!wrap) return;
    wrap.style.borderColor = '#087C7C';
    wrap.style.boxShadow   = '0 0 0 2px rgba(8,124,124,0.35)';
    wrap.querySelector('div').style.display = 'flex';
    selectedThumbCanvas = thumbCanvases[idx] || null;
    showThumbOverlay(selectedThumbCanvas ? selectedThumbCanvas.toDataURL('image/jpeg', 0.85) : null);
}

customThumb.addEventListener('change', function () {
    if (this.files[0]) {
        selectedThumbCanvas = null;
        thumbGrid.querySelectorAll('[data-idx]').forEach(w => {
            w.style.borderColor = 'transparent';
            w.style.boxShadow   = 'none';
            w.querySelector('div').style.display = 'none';
        });
        showThumbOverlay(URL.createObjectURL(this.files[0]));
    }
});

videoFileEl.addEventListener('change', function () {
    if (this.files[0]) {
        listingMediaPathInput.value = '';
        listingImagesGrid.querySelectorAll('.listing-media-item').forEach(el => el.style.borderColor = 'transparent');
        loadVideoFrames(this.files[0]);
    }
});

videoRemoveBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    videoFileEl.value = '';
    customThumb.value = '';
    videoPreview.src  = '';
    filmstripFrames.innerHTML = '';
    thumbGrid.innerHTML       = '';
    thumbCanvases             = [];
    selectedThumbCanvas       = null;
    videoDuration = 0;
    trimStart     = 0;
    trimEnd       = 0;
    showThumbOverlay(null);
    videoSelectedBadge.classList.add('d-none');
    videoSelectedBadge.classList.remove('d-flex');
    studioEmptyState.style.display = '';
    videoUi.style.display = 'none';
    studioActionFooter.style.display = 'none';
});

// ── Filmstrip drag ────────────────────────────────────────────────────────────
function clientX(e) { return e.touches ? e.touches[0].clientX : e.clientX; }

function makeDraggable(handle, isStart) {
    let drag = false;
    handle.addEventListener('mousedown',  e => { drag = true; e.preventDefault(); });
    handle.addEventListener('touchstart', e => { drag = true; e.preventDefault(); }, { passive: false });
    document.addEventListener('mouseup',  () => { drag = false; });
    document.addEventListener('touchend', () => { drag = false; });

    function onMove(e) {
        if (!drag || !videoDuration) return;
        const rect = filmstripWrap.getBoundingClientRect();
        const pct  = Math.max(0, Math.min(1, (clientX(e) - rect.left) / rect.width));
        let sec    = pct * videoDuration;

        if (isStart) {
            sec = Math.max(0, Math.min(sec, trimEnd - 0.5));
            if (trimEnd - sec > MAX_DURATION) trimEnd = sec + MAX_DURATION;
            trimStart = sec;
        } else {
            sec = Math.min(videoDuration, Math.max(sec, trimStart + 0.5));
            if (sec - trimStart > MAX_DURATION) trimStart = sec - MAX_DURATION;
            trimEnd = sec;
        }
        updateTrimmerUI();
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false });
}

makeDraggable(handleL, true);
makeDraggable(handleR, false);

// ── Form submit ───────────────────────────────────────────────────────────────
function setFile(input, file) {
    const dt = new DataTransfer();
    dt.items.add(file instanceof File ? file : new File([file], file.name || 'file'));
    input.files = dt.files;
}
function setBlobAsFile(input, blob, name, mime) {
    const dt = new DataTransfer();
    dt.items.add(new File([blob], name, { type: mime }));
    input.files = dt.files;
}

document.getElementById('storyForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!entityIdHidden.value) {
        Toastify({ text: '{{ __("Please select a listing.") }}', duration: 3000, backgroundColor: '#dc3545' }).showToast();
        return;
    }

    const type = mediaTypeEl.value;

    // ── Image ──
    if (type === 'image') {
        if (!imageFileEl.files[0] && !listingMediaPathInput.value) {
            Toastify({ text: '{{ __("Please select an image file or pick one from the listing.") }}', duration: 3000, backgroundColor: '#dc3545' }).showToast();
            return;
        }
        document.querySelectorAll('.submit-action-btn').forEach(btn => btn.disabled = true);
        showUploadProgress('{{ __("Uploading...") }}');
        if (imageFileEl.files[0]) setFile(mediaSubmit, imageFileEl.files[0]);
        this.submit();
        return;
    }

    // ── Video ──
    if (!videoFileEl.files[0] && !listingMediaPathInput.value) {
        Toastify({ text: '{{ __("Please select a video file.") }}', duration: 3000, backgroundColor: '#dc3545' }).showToast();
        return;
    }

    document.querySelectorAll('.submit-action-btn').forEach(btn => btn.disabled = true);
    showUploadProgress('{{ __("Processing clip...") }}');

    // If using a listing video (no local file), skip trim and submit directly
    if (!videoFileEl.files[0]) {
        this.submit();
        return;
    }

    const needsTrim = trimStart > 0.05 || (videoDuration && trimEnd < videoDuration - 0.05);

    if (needsTrim) {
        try {
            // Fast trim with 4-second race fallback so upload never gets stuck locally
            const blob = await Promise.race([
                trimVideoBlob(videoFileEl.files[0], trimStart, trimEnd - trimStart),
                new Promise(resolve => setTimeout(() => resolve(videoFileEl.files[0]), 4000))
            ]);
            if (blob instanceof Blob && !(blob instanceof File)) {
                setBlobAsFile(mediaSubmit, blob, 'story.webm', blob.type || 'video/webm');
            } else {
                setFile(mediaSubmit, videoFileEl.files[0]);
            }
        } catch (err) {
            setFile(mediaSubmit, videoFileEl.files[0]);
        }
    } else {
        setFile(mediaSubmit, videoFileEl.files[0]);
    }

    // ── Thumbnail ──
    showUploadProgress('{{ __("Uploading...") }}');
    if (customThumb.files[0]) {
        setFile(thumbSubmit, customThumb.files[0]);
    } else if (selectedThumbCanvas) {
        await new Promise(resolve => {
            selectedThumbCanvas.toBlob(blob => {
                setBlobAsFile(thumbSubmit, blob, 'thumbnail.jpg', 'image/jpeg');
                resolve();
            }, 'image/jpeg', 0.85);
        });
    }

    // Safety timeout: if page has not navigated after 12 seconds, reset UI so user is never stuck
    setTimeout(() => {
        resetUploadUI();
    }, 12000);

    this.submit();
});

// ── MediaRecorder trim with rapid slice & safety timeout ──────────────────────
function trimVideoBlob(file, startSec, durSec) {
    return new Promise((resolve, reject) => {
        const vid   = document.createElement('video');
        vid.src     = URL.createObjectURL(file);
        vid.muted   = true;
        vid.preload = 'auto';

        let safetyTimeout = null;
        const cleanup = () => {
            if (safetyTimeout) clearTimeout(safetyTimeout);
            if (vid.src) URL.revokeObjectURL(vid.src);
        };

        vid.addEventListener('error', () => {
            cleanup();
            resolve(file);
        });

        vid.addEventListener('loadedmetadata', async () => {
            try {
                await waitForSeek(vid, startSec, 350);

                if (!vid.captureStream || typeof MediaRecorder === 'undefined') {
                    cleanup();
                    resolve(file);
                    return;
                }

                const stream = vid.captureStream();
                const mimeType = ['video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'].find(m => MediaRecorder.isTypeSupported(m)) || '';
                const options = mimeType ? { mimeType } : {};

                const recorder = new MediaRecorder(stream, options);
                const chunks = [];
                recorder.ondataavailable = ev => { if (ev.data.size) chunks.push(ev.data); };
                recorder.onstop = () => {
                    cleanup();
                    if (chunks.length === 0) {
                        resolve(file);
                    } else {
                        resolve(new Blob(chunks, { type: mimeType || 'video/webm' }));
                    }
                };
                recorder.onerror = () => {
                    cleanup();
                    resolve(file);
                };

                recorder.start(100);
                await vid.play();

                safetyTimeout = setTimeout(() => {
                    vid.pause();
                    if (recorder.state === 'recording') recorder.stop();
                }, Math.max(1, durSec) * 1000);

            } catch (err) {
                cleanup();
                resolve(file);
            }
        });
    });
}
</script>
@endsection
