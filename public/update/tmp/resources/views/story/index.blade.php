@extends('layouts.main')

@section('title')
    {{ __('Stories') }}
@endsection

@section('css')
<style>
/* Grid card */
.story-grid-card { cursor:pointer; }
.story-grid-stack { position:relative; aspect-ratio:9/16; }
.story-grid-front { position:absolute; inset:0; border-radius:16px; overflow:hidden; background:#0f172a; box-shadow:0 8px 24px rgba(0,0,0,0.35); transition:transform .2s,box-shadow .2s; }
.story-grid-card:hover .story-grid-front { transform:scale(1.03); box-shadow:0 14px 32px rgba(0,0,0,0.45); }
.story-grid-front img { width:100%; height:100%; object-fit:contain; }
.story-grid-placeholder { width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#1e293b,#0f172a); color:rgba(255,255,255,0.3); font-size:2rem; }
.story-grid-top-fade { position:absolute; top:0; left:0; right:0; height:70px; background:linear-gradient(to bottom,rgba(0,0,0,0.6) 0%,transparent 100%); }
.story-grid-bottom-fade { position:absolute; bottom:0; left:0; right:0; height:100px; background:linear-gradient(to top,rgba(0,0,0,0.88) 0%,transparent 100%); }
.story-grid-media-icon { position:absolute; top:14px; left:14px; color:rgba(255,255,255,0.85); font-size:0.85rem; z-index:5; }
.story-grid-count { position:absolute; top:10px; right:12px; background:rgba(0,0,0,0.6); color:#fff; font-size:0.7rem; font-weight:600; padding:3px 9px; border-radius:20px; z-index:5; backdrop-filter:blur(4px); }
.story-grid-agent { position:absolute; bottom:14px; left:0; right:0; padding:0 14px; z-index:5; display:flex; align-items:center; gap:8px; }
.story-grid-avatar { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.85rem; color:#fff; flex-shrink:0; overflow:hidden; }
.story-grid-avatar--admin { background:linear-gradient(135deg,#4f46e5,#7c3aed); box-shadow:0 0 0 2px #fff,0 0 0 4px #7c3aed; }
.story-grid-avatar--agent { background:linear-gradient(135deg,#0ea5e9,#2563eb); box-shadow:0 0 0 2px #fff,0 0 0 4px #0ea5e9; }
.story-grid-avatar img { width:100%; height:100%; object-fit:cover; }
.story-grid-name { color:#fff; font-size:0.75rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-shadow:0 1px 3px rgba(0,0,0,0.6); }

/* Overlay segment bars */
.ov-seg { flex:1; height:3px; background:rgba(255,255,255,0.35); border-radius:2px; overflow:hidden; position:relative; }
.ov-seg-fill { position:absolute; top:0; left:0; bottom:0; width:0%; background:#fff; }
html[dir="rtl"] .ov-seg-fill { left:auto; right:0; }

/* RTL: swap nav button positions and flip chevron icons */
html[dir="rtl"] #overlayPrevBtn { left:auto !important; right:18px !important; }
html[dir="rtl"] #overlayNextBtn { right:auto !important; left:18px !important; }
html[dir="rtl"] #overlayPrevBtn i,
html[dir="rtl"] #overlayNextBtn i { display:inline-block; transform:scaleX(-1); }

/* RTL: move close button to left */
html[dir="rtl"] #overlayCloseBtn { right:auto !important; left:18px !important; }

/* Overlay control button */
.ov-ctrl { width:42px; height:42px; border-radius:50%; background:rgba(0,0,0,0.45); border:1px solid rgba(255,255,255,0.35); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.ov-nav { width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,0.18); border:2px solid rgba(255,255,255,0.4); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.1rem; }
.ov-nav:hover { background:rgba(255,255,255,0.25); }
.ov-ctrl:hover { background:rgba(0,0,0,0.65); }
@media (max-width: 768px) {
    .ov-nav { display:none !important; }
    #overlayCloseBtn { display:none !important; }
    #storyFrameWrapper { padding:0 !important; }
    #storyFrame { width:100% !important; max-width:100% !important; height:100% !important; max-height:100% !important; aspect-ratio:unset !important; border-radius:0 !important; }
    .ov-ctrl { width:34px !important; height:34px !important; }
    #overlayAvatar { width:46px !important; height:46px !important; }
    #overlayUploaderName { font-size:0.82rem !important; }
    #overlayViews { font-size:0.68rem !important; }
    #overlayEntityThumb { width:44px !important; height:44px !important; }
    #overlayEntityTitle { font-size:0.8rem !important; }
    #overlayEntityPrice { font-size:0.7rem !important; }
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
                        <li class="breadcrumb-item"><a href="{{ route('story.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Stories') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="card">

            @if (has_permissions('create', 'story'))
                <div class="card-header">
                    <div class="row">
                        <div class="col-12 col-xs-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('story.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>{{ __('Upload Story') }}
                            </a>
                        </div>
                    </div>
                </div>
                <hr>
            @endif

            <div class="card-body">
                {{-- Toolbar: filters left, view toggle right --}}
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center" id="story_filters">
                        <select id="filter_media_type" class="form-select form-control-sm w-auto" onchange="refreshStoryTable()">
                            <option value="">{{ __('All Media Types') }}</option>
                            <option value="image">{{ __('Image') }}</option>
                            <option value="video">{{ __('Video') }}</option>
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <div class="btn-group" role="group">
                            <button type="button" id="btn_table_view" class="btn btn-outline-primary active" onclick="switchView('table')">
                                <i class="fas fa-list"></i>
                            </button>
                            <button type="button" id="btn_card_view" class="btn btn-outline-primary" onclick="switchView('card')">
                                <i class="fas fa-th-large"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-outline-secondary" id="btn_refresh_table" onclick="$('#story_table').bootstrapTable('refresh')" title="{{ __('Refresh') }}">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </div>

                {{-- ── Table View ── --}}
                <div id="story_table_view">
                    <table class="table table-striped" id="story_table"
                        data-toggle="table"
                        data-url="{{ url('getStoryList') }}"
                        data-side-pagination="server"
                        data-pagination="true"
                        data-page-list="[10, 20, 50, 100]"
                        data-show-refresh="false"
                        data-trim-on-search="false"
                        data-responsive="true"
                        data-sort-name="created_at"
                        data-sort-order="desc"
                        data-pagination-successively-size="3"
                        data-query-params="storyQueryParams">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col" data-field="preview" data-sortable="false">{{ __('Preview') }}</th>
                                <th scope="col" data-field="uploader" data-sortable="false">{{ __('Uploader') }}</th>
                                <th scope="col" data-field="media_type" data-sortable="false" data-align="center">{{ __('Media Type') }}</th>
                                <th scope="col" data-field="linked_listing" data-sortable="false">{{ __('Linked Listing') }}</th>
                                <th scope="col" data-field="view_count" data-sortable="false" data-align="center">{{ __('Views') }}</th>
                                <th scope="col" data-field="expires_at" data-sortable="false" data-align="center">{{ __('Expires At') }}</th>
                                <th scope="col" data-field="status" data-sortable="false" data-align="center">{{ __('Status') }}</th>
                                @if (has_permissions('delete', 'story'))
                                    <th scope="col" data-field="operate" data-sortable="false" data-align="center">{{ __('Action') }}</th>
                                @endif
                            </tr>
                        </thead>
                    </table>
                </div>

                {{-- ── Story / Card View (grouped by agent) ── --}}
                <div id="story_card_view" style="display:none">
                    <div id="story_grid_container" class="d-flex gap-4" style="overflow-x:auto;overflow-y:visible;padding:16px 4px;flex-wrap:nowrap;"></div>
                    <div id="story_grid_empty" class="text-center text-muted py-5 d-none">
                        <i class="fas fa-photo-video fs-1 mb-3 d-block opacity-25"></i>
                        <p class="mb-3">{{ __('No stories found') }}</p>
                        @if (has_permissions('create', 'story'))
                            <a href="{{ route('story.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>{{ __('Upload Story') }}
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- Full-screen Story Overlay --}}
    <div id="storyOverlay" style="display:none;position:fixed;inset:0;z-index:1060;background:rgba(0,0,0,0.93);">
        {{-- Close X --}}
        <button id="overlayCloseBtn" onclick="closeStoryOverlay()" style="position:absolute;top:18px;right:18px;z-index:20;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.14);border:2px solid rgba(255,255,255,0.45);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-times"></i>
        </button>

        {{-- Left nav --}}
        <button id="overlayPrevBtn" onclick="prevStoryGlobal()" class="ov-nav" style="position:absolute;left:18px;top:50%;transform:translateY(-50%);z-index:25;">
            <i class="fas fa-chevron-left"></i>
        </button>

        {{-- Right nav --}}
        <button id="overlayNextBtn" onclick="nextStoryGlobal()" class="ov-nav" style="position:absolute;right:18px;top:50%;transform:translateY(-50%);z-index:25;">
            <i class="fas fa-chevron-right"></i>
        </button>

        {{-- Centered frame wrapper --}}
        <div id="storyFrameWrapper" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">

            {{-- Story frame --}}
            <div id="storyFrame" style="width:450px;max-width:calc(100vw - 140px);aspect-ratio:9/16;height:auto;max-height:calc(100vh - 40px);border-radius:16px;overflow:hidden;background:#000;position:relative;">

                {{-- Segment bars --}}
                <div id="overlaySegmentContainer" style="position:absolute;top:10px;left:10px;right:10px;z-index:25;display:flex;gap:3px;"></div>

                {{-- Top gradient --}}
                <div style="position:absolute;top:0;left:0;right:0;height:140px;background:linear-gradient(to bottom,rgba(0,0,0,0.7) 0%,transparent 100%);z-index:20;pointer-events:none;"></div>

                {{-- Header --}}
                <div style="position:absolute;top:36px;left:16px;right:16px;z-index:21;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div id="overlayAvatar" style="width:60px;height:60px;border-radius:12px;overflow:hidden;background:#e2e8f0;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,0.5);">
                            <i class="fas fa-user" style="color:#64748b;font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div id="overlayUploaderName" style="color:#fff;font-weight:700;font-size:1rem;text-shadow:0 1px 3px rgba(0,0,0,0.6);line-height:1.2;"></div>
                            <div id="overlayViews" style="color:rgba(255,255,255,0.75);font-size:0.78rem;margin-top:3px;"></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button id="overlayPlayPauseBtn" onclick="toggleOverlayPause()" class="ov-ctrl" title="{{ __('Pause') }}">
                            <i class="fas fa-pause" style="font-size:0.8rem;"></i>
                        </button>
                        <button id="overlayMuteBtn" onclick="toggleOverlayMute()" class="ov-ctrl" title="{{ __('Mute') }}">
                            <i class="fas fa-volume-up" style="font-size:0.8rem;"></i>
                        </button>
                        @if(has_permissions('delete','story'))
                        <button onclick="confirmOverlayDelete()" class="ov-ctrl" title="{{ __('Delete') }}" style="background:rgba(220,53,69,0.75);border-color:rgba(220,53,69,0.9);">
                            <i class="fas fa-trash" style="font-size:0.8rem;"></i>
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Media --}}
                <div id="overlayMediaContainer" style="position:absolute;inset:0;z-index:1;"></div>

                {{-- Bottom gradient --}}
                <div style="position:absolute;bottom:0;left:0;right:0;height:200px;background:linear-gradient(to top,rgba(0,0,0,0.55) 0%,transparent 100%);z-index:19;pointer-events:none;"></div>

                {{-- Bottom info card --}}
                <div style="position:absolute;bottom:12px;left:12px;right:12px;z-index:20;background:rgba(20,20,20,0.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-radius:16px;padding:12px 14px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img id="overlayEntityThumb" src="" alt="" style="width:52px;height:52px;border-radius:10px;object-fit:cover;flex-shrink:0;background:#334155;">
                        <div style="flex:1;min-width:0;">
                            <div id="overlayEntityTitle" style="font-weight:700;font-size:0.9rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;"></div>
                            <div id="overlayEntityPrice" style="font-size:0.8rem;color:rgba(255,255,255,0.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @if(has_permissions('delete','story'))
    <form method="GET" id="overlayDeleteForm" action="" style="display:none;">
    </form>
    @endif
@endsection

@section('script')
<script>
// ── Story groups for continuous playback ──────────────────────────────────────
let storyGroups     = [];   // [{agentData, stories:[...]}]
let currentGroupIdx = 0;
let currentStoryIdx = 0;
let isOverlayPaused = false;
let isOverlayMuted  = false;
let segRafId        = null;
let segDuration     = 5000;
let segElapsedMs    = 0;

// ── Table helpers ─────────────────────────────────────────────────────────────
function storyQueryParams(params) {
    params.media_type = document.getElementById('filter_media_type').value;
    return params;
}


function refreshStoryTable() {
    $('#story_table').bootstrapTable('refresh');
    fetchAllStoriesForPlayer();
    if (document.getElementById('story_card_view').style.display !== 'none') loadStoryGrid();
}

function switchView(mode) {
    const tableView = document.getElementById('story_table_view');
    const cardView  = document.getElementById('story_card_view');
    const btnTable  = document.getElementById('btn_table_view');
    const btnCard   = document.getElementById('btn_card_view');
    if (mode === 'table') {
        tableView.style.display = '';
        cardView.style.display  = 'none';
        btnTable.classList.add('active');
        btnCard.classList.remove('active');
    } else {
        tableView.style.display = 'none';
        cardView.style.display  = '';
        btnTable.classList.remove('active');
        btnCard.classList.add('active');
        loadStoryGrid();
    }
}

function loadStoryGrid() {
    const mediaType = document.getElementById('filter_media_type').value;
    const url = '{{ url("getStoryList") }}?limit=200&offset=0&media_type=' + mediaType;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('story_grid_container');
            const empty     = document.getElementById('story_grid_empty');
            container.innerHTML = '';
            if (!data.rows || data.rows.length === 0) {
                container.classList.add('d-none');
                empty.classList.remove('d-none');
                return;
            }
            empty.classList.add('d-none');
            container.classList.remove('d-none');

            const grouped = {};
            data.rows.forEach(row => {
                const key = row.uploader;
                if (!grouped[key]) grouped[key] = [];
                grouped[key].push(row);
            });

            Object.entries(grouped).forEach(([uploaderHtml, rows]) => {
                const first      = rows[0];
                const storyCount = rows.length;
                const isAdmin    = uploaderHtml.includes('bg-primary');
                const avatarClass = isAdmin ? 'story-grid-avatar--admin' : 'story-grid-avatar--agent';
                const tmpDiv = document.createElement('div');
                tmpDiv.innerHTML = first.preview;
                const img = tmpDiv.querySelector('img');
                const thumbSrc = img ? img.src : null;
                const previewEl = tmpDiv.firstElementChild;
                const onclick   = previewEl ? previewEl.getAttribute('onclick') : '';
                const col = document.createElement('div');
                col.style.cssText = 'flex:0 0 160px;width:160px;';
                col.innerHTML = `
                    <div class="story-grid-card" onclick="${onclick ? onclick.replace(/"/g, '&quot;') : ''}">
                        <div class="story-grid-stack">
                            <div class="story-grid-front">
                                ${thumbSrc ? `<img src="${thumbSrc}" alt="">` : `<div class="story-grid-placeholder"><i class="fas fa-play"></i></div>`}
                                ${storyCount > 1 ? `<div class="story-grid-count"><i class="fas fa-layer-group me-1"></i>${storyCount}</div>` : ''}
                                <div class="story-grid-top-fade"></div>
                                <div class="story-grid-media-icon">${first.media_type && first.media_type.includes('Video') ? '<i class="fas fa-play-circle"></i>' : '<i class="fas fa-image"></i>'}</div>
                                <div class="story-grid-bottom-fade"></div>
                                <div class="story-grid-agent">
                                    <div class="story-grid-avatar ${avatarClass}">
                                        ${isAdmin ? '<i class="fas fa-shield-alt"></i>' : '<i class="fas fa-user"></i>'}
                                    </div>
                                    <span class="story-grid-name">${isAdmin ? '{{ __("Admin") }}' : uploaderHtml}</span>
                                </div>
                            </div>
                        </div>
                    </div>`;
                container.appendChild(col);
            });
        });
}

// ── Load all stories for continuous player ────────────────────────────────────
function fetchAllStoriesForPlayer() {
    const mediaType = document.getElementById('filter_media_type').value;
    return fetch('{{ url("getStoryList") }}?limit=9999&offset=0&media_type=' + encodeURIComponent(mediaType))
        .then(r => r.json())
        .then(data => buildStoryGroups(data.rows || []))
        .catch(() => {});
}

function buildStoryGroups(rows) {
    const groupMap   = {};
    const groupOrder = [];
    rows.forEach(row => {
        if (!row.story_raw || !row.agent_raw) return;
        const key = String(row.agent_raw.agent_id);
        if (!groupMap[key]) {
            groupMap[key] = { agentData: row.agent_raw, stories: [] };
            groupOrder.push(key);
        }
        groupMap[key].stories.push(row.story_raw);
    });
    storyGroups = groupOrder.map(k => groupMap[k]);
}

// ── Open story ────────────────────────────────────────────────────────────────
function openStoryById(storyId) {
    const doOpen = () => {
        for (let gi = 0; gi < storyGroups.length; gi++) {
            for (let si = 0; si < storyGroups[gi].stories.length; si++) {
                if (storyGroups[gi].stories[si].story_id === storyId) {
                    currentGroupIdx = gi;
                    currentStoryIdx = si;
                    showStoryOverlay();
                    return true;
                }
            }
        }
        return false;
    };
    if (storyGroups.length && doOpen()) return;
    fetchAllStoriesForPlayer().then(() => doOpen());
}

// Legacy (called from card view)
function openStoryPreview(stories) {
    if (stories && stories.length) openStoryById(stories[0].story_id);
}

// ── Overlay show / hide ───────────────────────────────────────────────────────
function showStoryOverlay() {
    document.getElementById('storyOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
    renderOverlayPlayer();
}

function closeStoryOverlay() {
    stopOverlayProgress();
    clearOverlayMedia();
    document.getElementById('storyOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

function clearOverlayMedia() {
    const mc = document.getElementById('overlayMediaContainer');
    if (!mc) return;
    const vid = mc.querySelector('video');
    if (vid) { vid.pause(); vid.src = ''; }
    mc.innerHTML = '';
}

// ── Render current story ──────────────────────────────────────────────────────
function renderOverlayPlayer() {
    if (!storyGroups.length) return;
    const group = storyGroups[currentGroupIdx];
    if (!group) return;
    const story = group.stories[currentStoryIdx];
    if (!story) return;

    stopOverlayProgress();
    clearOverlayMedia();
    isOverlayPaused = false;

    // Segment bars
    const segCont = document.getElementById('overlaySegmentContainer');
    if (segCont) {
        segCont.innerHTML = '';
        group.stories.forEach((_, idx) => {
            const seg  = document.createElement('div');
            seg.className = 'ov-seg';
            const fill = document.createElement('div');
            fill.className = 'ov-seg-fill';
            fill.style.width = idx < currentStoryIdx ? '100%' : '0%';
            seg.appendChild(fill);
            segCont.appendChild(seg);
        });
    }

    // Avatar
    const avatarBox = document.getElementById('overlayAvatar');
    if (avatarBox) {
        if (group.agentData.profile) {
            avatarBox.innerHTML = '<img src="' + group.agentData.profile + '" style="width:100%;height:100%;object-fit:cover;" alt="">';
        } else if (group.agentData.agent_id == 0) {
            avatarBox.innerHTML = '<i class="fas fa-shield-alt small" style="color:#4f46e5;"></i>';
        } else {
            avatarBox.innerHTML = '<i class="fas fa-user small" style="color:#64748b;"></i>';
        }
    }

    const nameEl = document.getElementById('overlayUploaderName');
    if (nameEl) nameEl.textContent = group.agentData.uploader || '—';
    const viewsEl = document.getElementById('overlayViews');
    if (viewsEl) viewsEl.textContent = (story.view_count || 0) + ' {{ __("views") }}';

    // Info card
    const thumbEl = document.getElementById('overlayEntityThumb');
    if (thumbEl) thumbEl.src = story.entity_thumb || story.thumbnail_url || story.media_url || '';

    const titleEl = document.getElementById('overlayEntityTitle');
    if (titleEl) titleEl.textContent = story.linked_entity_title || '—';

    const priceEl = document.getElementById('overlayEntityPrice');
    if (priceEl) priceEl.textContent = story.linked_entity_price || story.linked_entity_city || '';

    // Delete form
    const deleteForm = document.getElementById('overlayDeleteForm');
    if (deleteForm && story.id) deleteForm.action = '{{ url("story") }}/' + story.id;

    // Play/pause icon reset
    const ppBtn = document.getElementById('overlayPlayPauseBtn');
    if (ppBtn) ppBtn.querySelector('i').className = 'fas fa-pause';

    // Mute icon — only relevant for video stories, images have no audio.
    const muteBtn = document.getElementById('overlayMuteBtn');
    if (muteBtn) {
        muteBtn.style.display = story.media_type === 'video' ? '' : 'none';
        muteBtn.querySelector('i').className = 'fas fa-volume-' + (isOverlayMuted ? 'mute' : 'up');
    }

    // Media
    const mc = document.getElementById('overlayMediaContainer');
    if (mc) {
        if (story.media_type === 'video' && story.media_url) {
            const vid = document.createElement('video');
            vid.src         = story.media_url;
            vid.style.cssText = 'width:100%;height:100%;object-fit:contain;';
            vid.autoplay    = true;
            vid.playsInline = true;
            vid.muted       = isOverlayMuted;
            vid.addEventListener('loadedmetadata', () => startOverlayProgress(vid.duration * 1000, false));
            vid.addEventListener('ended', nextStoryGlobal);
            mc.appendChild(vid);
            vid.play().catch(() => {});
        } else {
            const imgEl = document.createElement('img');
            imgEl.src           = story.media_url || story.thumbnail_url || '';
            imgEl.style.cssText = 'width:100%;height:100%;object-fit:contain;';
            mc.appendChild(imgEl);
            startOverlayProgress((story.duration_seconds || 5) * 1000, true);
        }
    }
}

// ── Progress bar animation ────────────────────────────────────────────────────
function startOverlayProgress(durationMs, autoAdvance) {
    stopOverlayProgress();
    segDuration  = durationMs;
    segElapsedMs = 0;
    const segs = document.querySelectorAll('#overlaySegmentContainer .ov-seg');
    const fill = segs[currentStoryIdx] ? segs[currentStoryIdx].querySelector('.ov-seg-fill') : null;
    if (!fill) return;
    fill.style.width = '0%';
    let lastTs = performance.now();
    function tick(now) {
        const delta = now - lastTs;
        lastTs = now;
        if (!isOverlayPaused) segElapsedMs += delta;
        const pct = Math.min((segElapsedMs / segDuration) * 100, 100);
        fill.style.width = pct + '%';
        if (pct < 100) {
            segRafId = requestAnimationFrame(tick);
        } else if (autoAdvance) {
            nextStoryGlobal();
        }
    }
    segRafId = requestAnimationFrame(tick);
}

function stopOverlayProgress() {
    if (segRafId) { cancelAnimationFrame(segRafId); segRafId = null; }
}

// ── Navigation ────────────────────────────────────────────────────────────────
function nextStoryGlobal() {
    if (!storyGroups.length) return;
    const group = storyGroups[currentGroupIdx];
    if (currentStoryIdx < group.stories.length - 1) {
        currentStoryIdx++;
    } else if (currentGroupIdx < storyGroups.length - 1) {
        currentGroupIdx++;
        currentStoryIdx = 0;
    } else {
        closeStoryOverlay();
        return;
    }
    renderOverlayPlayer();
}

function prevStoryGlobal() {
    if (!storyGroups.length) return;
    if (currentStoryIdx > 0) {
        currentStoryIdx--;
    } else if (currentGroupIdx > 0) {
        currentGroupIdx--;
        currentStoryIdx = storyGroups[currentGroupIdx].stories.length - 1;
    } else {
        return;
    }
    renderOverlayPlayer();
}

// ── Controls ──────────────────────────────────────────────────────────────────
function toggleOverlayPause() {
    isOverlayPaused = !isOverlayPaused;
    const ppBtn = document.getElementById('overlayPlayPauseBtn');
    if (ppBtn) ppBtn.querySelector('i').className = 'fas fa-' + (isOverlayPaused ? 'play' : 'pause');
    const vid = document.querySelector('#overlayMediaContainer video');
    if (vid) { isOverlayPaused ? vid.pause() : vid.play().catch(() => {}); }
}

function toggleOverlayMute() {
    isOverlayMuted = !isOverlayMuted;
    const muteBtn = document.getElementById('overlayMuteBtn');
    if (muteBtn) muteBtn.querySelector('i').className = 'fas fa-volume-' + (isOverlayMuted ? 'mute' : 'up');
    const vid = document.querySelector('#overlayMediaContainer video');
    if (vid) vid.muted = isOverlayMuted;
}

function confirmOverlayDelete() {
    const wasPlaying = !isOverlayPaused;
    isOverlayPaused = true;
    Swal.fire({
        title: '{{ __("Delete Story?") }}',
        text:  '{{ __("This action cannot be undone.") }}',
        icon:  'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor:  '#6c757d',
        confirmButtonText:  '{{ __("Yes, Delete") }}',
        cancelButtonText:   '{{ __("Cancel") }}',
    }).then(result => {
        if (result.isConfirmed) {
            document.getElementById('overlayDeleteForm').submit();
        } else if (wasPlaying) {
            isOverlayPaused = false;
            const ppBtn = document.getElementById('overlayPlayPauseBtn');
            if (ppBtn) ppBtn.querySelector('i').className = 'fas fa-pause';
            const vid = document.querySelector('#overlayMediaContainer video');
            if (vid) vid.play().catch(() => {});
        }
    });
}

// Close on backdrop click & keyboard nav
document.getElementById('storyOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeStoryOverlay();
});
document.addEventListener('keydown', function(e) {
    if (document.getElementById('storyOverlay').style.display === 'none') return;
    if (e.key === 'Escape')      closeStoryOverlay();
    if (e.key === 'ArrowRight')  nextStoryGlobal();
    if (e.key === 'ArrowLeft')   prevStoryGlobal();
});

// Tap left/right on story frame to navigate (mobile)
document.getElementById('storyFrame').addEventListener('click', function(e) {
    if (e.target.closest('button') || e.target.closest('a')) return;
    const x = e.clientX - this.getBoundingClientRect().left;
    const isRtl = document.documentElement.dir === 'rtl';
    const tappedLeft = x < this.offsetWidth / 2;
    if (tappedLeft !== isRtl) prevStoryGlobal();
    else nextStoryGlobal();
});

// Initial load
fetchAllStoriesForPlayer();
</script>
@endsection
