<x-filament-panels::page>
    {{-- Inline styles — no @push needed --}}
    <style>
        /* Zero out all Filament content wrappers */
        main.fi-main { max-width:100% !important; padding:0 !important; margin:0 !important; }
        .fi-main-ctn,
        .fi-main-ctn > div,
        .fi-body-ctn,
        .fi-page,
        .fi-page > div,
        .fi-page-content-ctn,
        .fi-page-content { max-width:100% !important; width:100% !important; padding:0 !important; margin:0 !important; }
        /* Kill any top gap left by topbar spacer / sticky offset */
        .fi-body-ctn { padding-top:0 !important; margin-top:0 !important; }
        .fi-topbar + * { margin-top:0 !important; }
        /* Kill the py-8 / gap-y-8 on Filament's generic <section> page wrapper */
        .fi-page-content > section,
        .fi-page > section { padding:0 !important; gap:0 !important; margin:0 !important; }
        /* Hide page heading — the editor itself shows scene context */
        .fi-header { display:none !important; }

        /* ── Layout ─────────────────────────────────── */
        .tour-editor { display:flex; height:calc(100vh - 64px); gap:0; border:none; border-radius:0; overflow:hidden; background:#111; width:100%; position:relative; top:0; }
        .tour-editor .te-sidebar { width:220px; min-width:220px; background:#1a1a2e; color:#e5e7eb; overflow-y:auto; border-right:1px solid #333; display:flex; flex-direction:column; }
        .tour-editor .te-viewer { flex:1; position:relative; min-width:0; }
        .tour-editor .te-properties { width:300px; min-width:300px; background:#fff; border-left:1px solid #e5e7eb; overflow-y:auto; }

        /* ── Sidebar ────────────────────────────────── */
        .te-sidebar-header { padding:12px 14px; border-bottom:1px solid #333; font-weight:700; font-size:13px; color:#94a3b8; text-transform:uppercase; letter-spacing:0.05em; }
        .te-scene-item { display:flex; align-items:center; gap:10px; padding:8px 14px; cursor:pointer; transition:background .15s; border-bottom:1px solid rgba(255,255,255,.05); }
        .te-scene-item:hover { background:rgba(255,255,255,.08); }
        .te-scene-item.active { background:rgba(59,130,246,.25); border-left:3px solid #3b82f6; }
        .te-scene-thumb { width:48px; height:36px; border-radius:4px; object-fit:cover; background:#333; flex-shrink:0; }
        .te-scene-info { min-width:0; }
        .te-scene-name { font-size:12px; font-weight:600; color:#f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .te-scene-meta { font-size:10px; color:#94a3b8; }
        .te-scene-badge { display:inline-block; background:rgba(59,130,246,.3); color:#93c5fd; font-size:9px; padding:1px 5px; border-radius:8px; margin-left:4px; }

        /* ── Viewer toolbar ──────────────────────────── */
        .te-toolbar { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); display:flex; gap:6px; background:rgba(0,0,0,.8); padding:6px 12px; border-radius:999px; z-index:20; align-items:center; }
        .te-toolbar button { width:36px; height:36px; border-radius:50%; border:2px solid transparent; background:rgba(255,255,255,.1); color:#fff; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
        .te-toolbar button:hover { background:rgba(255,255,255,.25); }
        .te-toolbar button.active { border-color:#3b82f6; background:rgba(59,130,246,.3); }
        .te-toolbar .divider { width:1px; height:24px; background:rgba(255,255,255,.2); }
        .te-status { position:absolute; top:12px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,.75); color:#fff; padding:4px 14px; border-radius:999px; font-size:12px; z-index:20; pointer-events:none; }
        .te-live-stats { position:absolute; top:12px; right:12px; display:flex; gap:8px; z-index:20; pointer-events:none; }
        .te-live-stat { min-width:88px; background:rgba(0,0,0,.78); color:#fff; padding:8px 10px; border-radius:10px; backdrop-filter:blur(8px); box-shadow:0 8px 24px rgba(0,0,0,.18); }
        .te-live-stat-label { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.65); margin-bottom:2px; }
        .te-live-stat-value { font-size:13px; font-weight:700; color:#f8fafc; }

        /* ── Properties panel ────────────────────────── */
        .te-props-header { padding:14px 16px; border-bottom:1px solid #e5e7eb; font-weight:700; font-size:14px; color:#1e293b; display:flex; justify-content:space-between; align-items:center; }
        .te-props-section { padding:14px 16px; border-bottom:1px solid #f1f5f9; }
        .te-props-section h4 { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px; }
        .te-field { margin-bottom:10px; }
        .te-field label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:3px; }
        .te-field input, .te-field select, .te-field textarea { width:100%; padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; background:#f9fafb; }
        .te-field input:focus, .te-field select:focus, .te-field textarea:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 2px rgba(59,130,246,.15); background:#fff; }
        .te-field textarea { resize:vertical; min-height:48px; }
        .te-coords { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .te-btn { display:inline-flex; align-items:center; justify-content:center; gap:4px; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:all .15s; }
        .te-btn-primary { background:#3b82f6; color:#fff; }
        .te-btn-primary:hover { background:#2563eb; }
        .te-btn-success { background:#22c55e; color:#fff; }
        .te-btn-success:hover { background:#16a34a; }
        .te-btn-danger { background:#ef4444; color:#fff; }
        .te-btn-danger:hover { background:#dc2626; }
        .te-btn-ghost { background:transparent; color:#64748b; border:1px solid #e2e8f0; }
        .te-btn-ghost:hover { background:#f1f5f9; }
        .te-btn-block { width:100%; }

        /* ── Hotspot list in properties ──────────────── */
        .te-hotspot-item { display:flex; align-items:center; gap:7px; padding:6px 8px; border-radius:6px; cursor:pointer; transition:background .1s; border:1px solid transparent; }
        .te-hotspot-item:hover { background:#f1f5f9; }
        .te-hotspot-item.selected { background:#eff6ff; border-color:#93c5fd; }
        .te-hotspot-icon { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
        .te-hotspot-label { min-width:0; flex:1; }
        .te-hotspot-label .name { font-size:11px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .te-hotspot-label .type { font-size:10px; color:#94a3b8; }

        /* ── Empty state ─────────────────────────────── */
        .te-empty { text-align:center; padding:40px 20px; color:#94a3b8; }
        .te-empty svg { width:48px; height:48px; margin:0 auto 12px; opacity:.5; }
        .te-empty p { font-size:13px; }

        /* ── Toggle ──────────────────────────────────── */
        .te-toggle { position:relative; width:36px; height:20px; }
        .te-toggle input { opacity:0; width:0; height:0; }
        .te-toggle .slider { position:absolute; inset:0; background:#d1d5db; border-radius:999px; cursor:pointer; transition:.2s; }
        .te-toggle .slider:before { content:''; position:absolute; height:16px; width:16px; left:2px; bottom:2px; background:#fff; border-radius:50%; transition:.2s; }
        .te-toggle input:checked + .slider { background:#3b82f6; }
        .te-toggle input:checked + .slider:before { transform:translateX(16px); }

        /* ── Panorama container needs explicit dimensions ── */
        #psv-editor-container { width:100%; height:100%; min-height:400px; }
        /* Override PSV's pointer cursor on markers — editor uses crosshair/default instead */
        #psv-editor-container .psv-marker--has-tooltip,
        #psv-editor-container .psv-marker--has-content { cursor: default !important; }

        /* ── Drag-and-drop reordering ────────────────────── */
        .te-drag-handle { cursor:grab; color:#c4c4c4; padding:0 3px; font-size:14px; flex-shrink:0; user-select:none; }
        .te-drag-handle:active { cursor:grabbing; }
        .te-hotspot-item.drag-over { border-color:#3b82f6 !important; background:#eff6ff !important; }
        .te-inline-toggle { border:none; border-radius:99px; font-size:10px; font-weight:700; color:white; cursor:pointer; flex-shrink:0; padding:2px 7px; line-height:1.4; }
    </style>

    {{-- Alpine root component --}}
    <div x-data="tourEditorApp()" x-init="init()" class="tour-editor">

        {{-- ─── Left Sidebar: Scenes ──────────────────────── --}}
        <div class="te-sidebar">
            <div class="te-sidebar-header">
                Scenes
                <span x-text="waypoints.length" style="float:right;opacity:.6"></span>
            </div>
            <div style="flex:1;overflow-y:auto">
                <template x-for="wp in waypoints" :key="wp.id">
                    <div class="te-scene-item"
                         :class="{ active: activeWaypointId === wp.id }"
                         @click="selectScene(wp.id)">
                        <img class="te-scene-thumb"
                             :src="wp.thumbnail_url || wp.panorama_url"
                             :alt="wp.name"
                             onerror="this.style.display='none'">
                        <div class="te-scene-info">
                            <div class="te-scene-name" x-text="wp.name"></div>
                            <div class="te-scene-meta">
                                <span x-text="wp.type_label"></span>
                                <span class="te-scene-badge" x-text="wp.hotspots_count + ' hotspots'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ─── Center: Panorama Viewer ─────────────────── --}}
        <div class="te-viewer" wire:ignore>
            <div id="psv-editor-container" style="width:100%;height:100%"
                 @wheel.stop.prevent="if(editor?.viewer) { $event.deltaY < 0 ? editor.viewer.zoomIn(5) : editor.viewer.zoomOut(5); }"></div>

            {{-- Status bar --}}
            <div class="te-status" x-show="statusText" x-text="statusText" x-transition></div>

            <div class="te-live-stats">
                <div class="te-live-stat">
                    <div class="te-live-stat-label">Yaw</div>
                    <div class="te-live-stat-value" x-text="formatDegrees(liveYaw)"></div>
                </div>
                <div class="te-live-stat">
                    <div class="te-live-stat-label">Pitch</div>
                    <div class="te-live-stat-value" x-text="formatDegrees(livePitch)"></div>
                </div>
                <div class="te-live-stat">
                    <div class="te-live-stat-label">Zoom</div>
                    <div class="te-live-stat-value" x-text="formatZoom(liveZoom)"></div>
                </div>
            </div>

            {{-- Toolbar --}}
            <div class="te-toolbar">
                <template x-for="ic in icons" :key="ic">
                    <button :class="{ active: placing && placingIcon === ic }"
                            @click="togglePlacing(ic)"
                            :title="'Place ' + ic + ' hotspot'"
                            x-html="iconSvg(ic, 18, '#fff')"></button>
                </template>
                <div class="divider"></div>
                <button @click="cancelPlacing()" title="Cancel placement" style="font-size:12px">✕</button>
            </div>
        </div>

        {{-- ─── Right Sidebar: Properties ─────────────────── --}}
        <div class="te-properties">
            {{-- ── Placing hotspot: preview dropped, awaiting confirmation ── --}}
            <template x-if="placing === 'move'">
                <div>
                    <div class="te-props-header">
                        <span>📍 Placing Hotspot</span>
                    </div>
                    <div class="te-props-section">
                        <p style="font-size:12px;color:#64748b;margin-bottom:12px">
                            Click anywhere on the panorama to reposition. Hit <strong>Confirm</strong> when satisfied.
                        </p>
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px;margin-bottom:12px;font-size:12px;color:#64748b">
                            Yaw:&nbsp;<strong x-text="pendingHotspot?.yaw?.toFixed(2)"></strong>&deg; &nbsp;
                            Pitch:&nbsp;<strong x-text="pendingHotspot?.pitch?.toFixed(2)"></strong>&deg;
                        </div>
                        <button class="te-btn te-btn-success te-btn-block" @click="confirmPlacement()" style="margin-bottom:8px">
                            ✓ Confirm Position
                        </button>
                        <button class="te-btn te-btn-ghost te-btn-block" @click="cancelPlacing()">
                            ✕ Discard
                        </button>
                    </div>
                </div>
            </template>

            {{-- ── Editing a hotspot ── --}}
            <template x-if="editingHotspot && placing !== 'move'">
                <div>
                    <div class="te-props-header">
                        <span x-text="editingHotspot.id ? 'Edit Hotspot' : 'New Hotspot'"></span>
                        <button class="te-btn te-btn-ghost" @click="deselectHotspot()" title="Close">✕</button>
                    </div>

                    <div class="te-props-section">
                        <h4>Details</h4>
                        <div class="te-field">
                            <label>Title *</label>
                            <input type="text" x-model="editingHotspot.title" placeholder="e.g. Enter Lobby">
                        </div>
                        <div class="te-field">
                            <label>Description</label>
                            <textarea x-model="editingHotspot.description" placeholder="Optional description..."></textarea>
                        </div>
                        <div class="te-field">
                            <label>Icon</label>
                            <div style="display:flex;gap:4px;flex-wrap:wrap">
                                <template x-for="ic in icons" :key="'sel-'+ic">
                                    <button style="width:30px;height:30px;border-radius:6px;border:2px solid transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;background:#f1f5f9"
                                            :style="editingHotspot.icon === ic ? 'border-color:#3b82f6;background:#eff6ff' : ''"
                                            @click="editingHotspot.icon = ic"
                                            x-html="iconSvg(ic, 16, '#334155')"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="te-props-section">
                        <h4>Position</h4>
                        <div class="te-coords">
                            <div class="te-field">
                                <label>Pitch</label>
                                <input type="number" step="0.01" x-model.number="editingHotspot.pitch" min="-90" max="90">
                            </div>
                            <div class="te-field">
                                <label>Yaw</label>
                                <input type="number" step="0.01" x-model.number="editingHotspot.yaw" min="-180" max="180">
                            </div>
                        </div>
                    </div>

                    <div class="te-props-section">
                        <h4>Action</h4>
                        <div class="te-field">
                            <label>Type</label>
                            <select x-model="editingHotspot.action_type">
                                <option value="navigate">🔗 Navigate to Scene</option>
                                <option value="info">ℹ️ Show Info</option>
                                <option value="bookmark">🔖 Bookmark</option>
                                <option value="external-link">🌐 External Link</option>                                <option value="video">▶️ Play YouTube Video</option>                                <option value="audio">🔊 Play Audio</option>
                            </select>
                        </div>
                        <div class="te-field" x-show="editingHotspot.action_type === 'navigate'">
                            <label>Target Scene</label>
                            <select x-model="editingHotspot.action_target">
                                <option value="">— Select scene —</option>
                                <template x-for="wp in waypoints" :key="'target-'+wp.id">
                                    <option :value="wp.slug" x-text="wp.name" :disabled="wp.id === activeWaypointId"></option>
                                </template>
                            </select>
                        </div>
                        <div class="te-field" x-show="['external-link','video','audio'].includes(editingHotspot.action_type)">
                            <label x-text="editingHotspot.action_type === 'video' ? 'YouTube URL' : editingHotspot.action_type === 'audio' ? 'Audio URL' : 'URL'"></label>
                            <input type="url" x-model="editingHotspot.action_target"
                                   :placeholder="editingHotspot.action_type === 'video' ? 'https://youtube.com/watch?v=…' : editingHotspot.action_type === 'audio' ? 'https://…/audio.mp3' : 'https://…'">
                            <div x-show="editingHotspot.action_type === 'audio'" style="font-size:10px;color:#94a3b8;margin-top:3px">Supports MP3, OGG, WAV, M4A.</div>
                        </div>
                    </div>

                    <div class="te-props-section" x-show="editingHotspot.action_type === 'info'">
                        <h4>Media <span style="font-size:10px;font-weight:400;color:#94a3b8">(optional)</span></h4>
                        <div class="te-field">
                            <label>Type</label>
                            <select x-model="editingHotspot.media_type"
                                    @change="editingHotspot.media_url = ''">
                                <option value="">None</option>
                                <option value="image">🖼️ Image</option>
                                <option value="video">▶️ YouTube Video</option>
                                <option value="gallery">🖼️ Image Gallery</option>
                            </select>
                        </div>
                        <div class="te-field" x-show="editingHotspot.media_type === 'image'"
                             x-data="{ uploading: false, uploadProgress: 0, uploadError: '' }">
                            <label>Image <span style="font-size:10px;font-weight:400;color:#94a3b8">(jpg, png, webp, gif · max 2 MB)</span></label>

                            {{-- Upload picker --}}
                            <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#f1f5f9;border:1px dashed #cbd5e1;border-radius:6px;cursor:pointer;font-size:11px;color:#475569;font-weight:600"
                                   :style="uploading ? 'opacity:.6;pointer-events:none' : ''">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span x-text="uploading ? 'Uploading...' : 'Upload image'"></span>
                                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none"
                                       @change="
                                           const file = $event.target.files[0];
                                           if (!file) return;
                                           uploading = true; uploadError = ''; uploadProgress = 0;
                                           $wire.upload(
                                               'hotspotImageFile',
                                               file,
                                               async () => {
                                                   try {
                                                       const url = await $wire.uploadHotspotImage();
                                                       editingHotspot.media_url = url;
                                                   } catch (e) {
                                                       uploadError = e?.message || 'Upload failed.';
                                                   } finally {
                                                       uploading = false; uploadProgress = 0;
                                                       $event.target.value = '';
                                                   }
                                               },
                                               (err) => { uploading = false; uploadError = 'Upload failed. Check file type and size.'; $event.target.value = ''; },
                                               (evt) => { uploadProgress = evt.detail.progress; }
                                           );
                                       ">
                            </label>

                            {{-- Progress bar --}}
                            <div x-show="uploading" style="margin-top:5px;height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden">
                                <div style="height:100%;background:#3b82f6;transition:width .2s" :style="'width:' + uploadProgress + '%'"></div>
                            </div>

                            {{-- Error --}}
                            <div x-show="uploadError" x-text="uploadError" style="margin-top:4px;font-size:10px;color:#ef4444"></div>

                            {{-- Preview --}}
                            <div x-show="editingHotspot.media_url" style="margin-top:6px;border-radius:6px;overflow:hidden;max-height:100px;background:#000">
                                <img :src="editingHotspot.media_url" style="width:100%;max-height:100px;object-fit:cover;display:block" onerror="this.parentElement.style.display='none'">
                            </div>
                        </div>
                        <div class="te-field" x-show="editingHotspot.media_type === 'video'">
                            <label>YouTube URL</label>
                            <input type="url" x-model="editingHotspot.media_url" placeholder="https://youtube.com/watch?v=...">
                            <div style="font-size:10px;color:#94a3b8;margin-top:3px">Supports youtube.com, youtu.be, and /shorts/ links.</div>
                        </div>
                        <div class="te-field" x-show="editingHotspot.media_type === 'gallery'">
                            <label>Image URLs <span style="font-size:10px;font-weight:400;color:#94a3b8">(one per line)</span></label>
                            <textarea x-model="editingHotspot.media_url" rows="4"
                                      placeholder="https://example.com/photo1.jpg&#10;https://example.com/photo2.jpg"></textarea>
                            <div style="font-size:10px;color:#94a3b8;margin-top:3px">Enter one image URL per line.</div>
                        </div>
                    </div>

                    <div class="te-props-section">
                        <h4>Options</h4>
                        <div class="te-field" style="display:flex;align-items:center;gap:8px">
                            <label class="te-toggle" style="margin:0">
                                <input type="checkbox" x-model="editingHotspot.is_active">
                                <span class="slider"></span>
                            </label>
                            <span style="font-size:12px;color:#475569">Active</span>
                        </div>

                    </div>

                    <div class="te-props-section" style="display:flex;gap:8px">
                        <button class="te-btn te-btn-success te-btn-block" @click="saveCurrentHotspot()" :disabled="!editingHotspot.title">
                            <span x-text="editingHotspot.id ? '✓ Update' : '✓ Create'"></span>
                        </button>
                        <template x-if="editingHotspot.id">                            <button class="te-btn te-btn-ghost" @click="duplicateCurrentHotspot()" title="Duplicate this hotspot">⊕</button>
                        </template>
                        <template x-if="editingHotspot.id">                            <button class="te-btn te-btn-danger" @click="deleteCurrentHotspot()" title="Delete">🗑</button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- ── No hotspot selected: show scene info + hotspot list ── --}}
            <template x-if="!editingHotspot && placing !== 'move'">
                <div>
                    <div class="te-props-header">
                        <span>Hotspots</span>
                        <span style="font-size:12px;color:#94a3b8" x-text="hotspots.length + ' total'"></span>
                    </div>

                    {{-- Hotspot list --}}
                    <div style="padding:8px">
                        <template x-for="h in hotspots" :key="'hl-'+h.id">
                            <div class="te-hotspot-item"
                                 :class="{ selected: editingHotspot?.id === h.id, 'drag-over': dragOverIdx === h.id }"
                                 draggable="true"
                                 @dragstart="draggedHotspotId = h.id; $event.dataTransfer.effectAllowed = 'move'"
                                 @dragover.prevent="dragOverIdx = h.id"
                                 @dragleave="if (dragOverIdx === h.id) dragOverIdx = null"
                                 @drop.prevent="dropHotspot(h.id)"
                                 @dragend="draggedHotspotId = null; dragOverIdx = null"
                                 @click="selectHotspot(h)">
                                <div class="te-drag-handle" @click.stop>⠇</div>
                                <div class="te-hotspot-icon" :style="'background:' + actionColor(h.action_type)">
                                    <span x-html="iconSvg(h.icon, 14, '#1e293b')"></span>
                                </div>
                                <div class="te-hotspot-label">
                                    <div class="name" x-text="h.title"></div>
                                    <div class="type" x-text="actionLabel(h.action_type) + (h.action_type === 'navigate' && h.action_target ? ' \u2192 ' + waypointName(h.action_target) : '')"></div>
                                </div>
                                <button class="te-inline-toggle"
                                        @click.stop="quickToggleActive(h)"
                                        :style="`background:${h.is_active ? '#22c55e' : '#9ca3af'}`"
                                        :title="h.is_active ? 'Active \u2014 click to disable' : 'Inactive \u2014 click to enable'"
                                        x-text="h.is_active ? 'on' : 'off'"></button>
                            </div>
                        </template>

                        <div x-show="hotspots.length === 0" class="te-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            <p>No hotspots yet.<br>Use the toolbar below the viewer to place one.</p>
                        </div>
                    </div>

                    {{-- Scene info --}}
                    <div class="te-props-section" x-show="activeScene">
                        <h4>Current Scene</h4>
                        <div style="font-size:13px;color:#1e293b;font-weight:600" x-text="activeScene?.name"></div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px" x-text="activeScene?.type_label"></div>

                        {{-- Default view --}}
                        <div style="margin-top:10px;padding:8px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0">
                            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Default View</div>
                            <div style="font-size:11px;color:#64748b;margin-bottom:4px">
                                Yaw: <span x-text="activeScene?.default_yaw?.toFixed(1) ?? '0'"></span>° &nbsp;
                                Pitch: <span x-text="activeScene?.default_pitch?.toFixed(1) ?? '0'"></span>° &nbsp;
                                Zoom: <span x-text="activeScene?.default_zoom ?? 50"></span>%
                            </div>

                            <button @click="captureDefaultView()" class="te-btn te-btn-success te-btn-block" style="font-size:11px;padding:5px 10px">
                                📷 Set Current View as Default
                            </button>
                        </div>

                        <div style="margin-top:8px">
                            <a :href="'/admin/virtual-tour/' + activeScene?.id + '/edit'" class="te-btn te-btn-ghost te-btn-block" style="text-decoration:none;text-align:center">
                                ✏️ Edit Scene Details
                            </a>
                        </div>

                        {{-- Room Info reposition (only when scene is room-linked) --}}
                        <template x-if="activeScene?.linked_room_type_id">
                            <div style="margin-top:8px">
                                <button
                                    @click="placingRoomInfo ? cancelRoomInfoPlacing() : startRoomInfoPlacing()"
                                    class="te-btn te-btn-block"
                                    :style="placingRoomInfo
                                        ? 'background:#fef3c7;border-color:#f59e0b;color:#92400e;font-size:11px'
                                        : 'background:#f0fdf4;border-color:#86efac;color:#166534;font-size:11px'">
                                    <span x-text="placingRoomInfo ? '✕ Cancel — click panorama to place' : '📍 Reposition Room Info Marker'"></span>
                                </button>

                            </div>
                        </template>
                    </div>

                    {{-- Preview link --}}
                    <div class="te-props-section">
                        <a :href="'/tour/' + (activeScene?.slug || '') + '?preview=1'" target="_blank" class="te-btn te-btn-primary te-btn-block" style="text-decoration:none;text-align:center">
                            👁️ Preview as Guest
                        </a>
                    </div>
                </div>
            </template>
        </div>

    </div>

    @vite(['resources/js/tour-editor.js'])
    <script>
        function tourEditorApp() {
            return {
                // Data from server
                waypoints: {!! $this->waypointsJson !!},
                hotspots: {!! $this->hotspotsJson !!},
                activeWaypointId: {{ $this->activeWaypointId }},

                // Editor state
                editor: null,
                placing: false,
                placingIcon: 'chevron-up',
                editingHotspot: null,
                pendingHotspot: null,
                statusText: '',
                liveYaw: 0,
                livePitch: 0,
                liveZoom: 0,
                placingRoomInfo: false,
                draggedHotspotId: null,
                dragOverIdx: null,
                icons: [
                    'chevron-up', 'chevron-down', 'chevron-left', 'chevron-right',
                    'chevron-up-right', 'chevron-down-right', 'chevron-down-left', 'chevron-up-left',
                    'info', 'link', 'pin', 'warning',
                ],

                get activeScene() {
                    return this.waypoints.find(w => w.id === this.activeWaypointId) || null;
                },

                init() {
                    // Guard: only create the viewer once
                    if (this._initialized) return;
                    this._initialized = true;

                    // Delay to ensure panorama container has rendered dimensions
                    setTimeout(() => {
                        const container = document.getElementById('psv-editor-container');
                        const scene = this.activeScene;
                        if (!scene || !container) return;

                        this.editor = new window.TourEditor('psv-editor-container', {
                            waypoint: scene,
                            hotspots: this.hotspots,
                            allWaypoints: this.waypoints,
                            onReady: () => {
                                this.syncLiveViewStats();
                                this.startLiveViewTracking();
                                // Track zoom changes in real-time
                                this.editor.viewer.addEventListener('zoom-updated', (e) => {
                                    this.liveZoom = Math.round(e.zoomLevel);
                                });
                                this.editor.viewer.addEventListener('position-updated', (e) => {
                                    this.updateLivePosition(e.position);
                                });
                                this.statusText = 'Ready — click toolbar icons to place hotspots';
                                setTimeout(() => this.statusText = '', 3000);
                            },
                            onHotspotPlaced: (data) => {
                                // Called by editor.confirmPlacement() after user confirms position
                                this.placing = false;
                                this.pendingHotspot = null;
                                this.statusText = '';
                                this.editingHotspot = {
                                    id: null,
                                    title: '',
                                    description: '',
                                    media_type: '',
                                    media_url: '',
                                    icon: data.icon,
                                    pitch: data.pitch,
                                    yaw: data.yaw,
                                    action_type: 'info',
                                    action_target: '',
                                    sort_order: this.hotspots.length,
                                    is_active: true,
                                };
                                // Rotate panorama to the confirmed hotspot position
                                this.editor?.viewer?.rotate({ yaw: `${data.yaw}deg`, pitch: `${data.pitch}deg` });
                            },
                            onHotspotDropped: (data) => {
                                // First click — preview dropped, enter move mode
                                this.placing = 'move';
                                this.pendingHotspot = { yaw: data.yaw, pitch: data.pitch, icon: data.icon };
                                this.statusText = 'Click to reposition — or ✓ Confirm when ready';
                            },
                            onHotspotMoved: (data) => {
                                if (this.pendingHotspot) {
                                    this.pendingHotspot.yaw   = data.yaw;
                                    this.pendingHotspot.pitch = data.pitch;
                                }
                            },
                            onHotspotSelected: (h) => {
                                this.editingHotspot = { ...h };
                            },
                            onHotspotDeselected: () => {
                                this.editingHotspot = null;
                            },
                            onRoomInfoPlaced: async (data) => {
                                // Stay in placing mode until user explicitly cancels
                                await this.$wire.setRoomInfoPosition(this.activeWaypointId, data.yaw, data.pitch);
                                // Move marker immediately in the editor
                                this.editor?.updateRoomInfoMarkerPosition(data.yaw, data.pitch);
                                // Update local waypoint data
                                const wp = this.waypoints.find(w => w.id === this.activeWaypointId);
                                if (wp) { wp.room_info_yaw = data.yaw; wp.room_info_pitch = data.pitch; }
                            },
                        });
                        this.editor.init();
                        window.__tourEditorAlpine = this;
                    }, 200);

                    // Listen for Livewire events
                    this.$wire.$on('hotspots-updated', () => {
                        this.reloadHotspots();
                    });
                },

                async selectScene(waypointId) {
                    if (waypointId === this.activeWaypointId) return;
                    this.activeWaypointId = waypointId;
                    this.editingHotspot = null;
                    this.placingRoomInfo = false;
                    this.editor?.cancelRoomInfoPlacement();

                    // Get hotspots from server and switch panorama
                    const hotspotsJson = await this.$wire.switchScene(waypointId);
                    this.hotspots = JSON.parse(hotspotsJson);
                    const scene = this.activeScene;
                    if (scene && this.editor) {
                        this.editor.switchScene(scene, this.hotspots);
                        setTimeout(() => this.syncLiveViewStats(), 50);
                    }
                    // Update count in sidebar
                    const wp = this.waypoints.find(w => w.id === waypointId);
                    if (wp) wp.hotspots_count = this.hotspots.length;
                },

                async captureDefaultView() {
                    if (!this.editor?.viewer || !this.activeWaypointId) return;
                    const pos = this.editor.viewer.getPosition();
                    const zoom = this.editor.viewer.getZoomLevel();
                    console.log('[captureDefaultView] raw pos:', pos, 'raw zoom:', zoom);
                    const yaw = parseFloat((pos.yaw * 180 / Math.PI).toFixed(4));
                    const pitch = parseFloat((pos.pitch * 180 / Math.PI).toFixed(4));
                    const zoomInt = Math.round(zoom);
                    console.log('[captureDefaultView] saving:', { yaw, pitch, zoomInt, waypointId: this.activeWaypointId });
                    await this.$wire.setDefaultView(this.activeWaypointId, yaw, pitch, zoomInt);
                    // Update local data
                    const wp = this.waypoints.find(w => w.id === this.activeWaypointId);
                    if (wp) {
                        wp.default_yaw = yaw;
                        wp.default_pitch = pitch;
                        wp.default_zoom = zoomInt;
                    }
                },

                togglePlacing(icon) {
                    if (this.placing === 'new' && this.placingIcon === icon) {
                        this.cancelPlacing();
                        return;
                    }
                    // If already in 'move' mode, discard the pending preview first
                    if (this.placing) {
                        this.editor?.cancelPlacement();
                        this.pendingHotspot = null;
                    }
                    this.placing = 'new';
                    this.placingIcon = icon;
                    this.editingHotspot = null;
                    this.editor?.startPlacement(icon);
                    this.statusText = '🎯 Click on the panorama to place a hotspot';
                },

                cancelPlacing() {
                    this.placing = false;
                    this.pendingHotspot = null;
                    this.editor?.cancelPlacement();
                    this.statusText = '';
                },

                confirmPlacement() {
                    if (!this.pendingHotspot || !this.editor) return;
                    this.editor.confirmPlacement(); // triggers onHotspotPlaced callback
                },

                startRoomInfoPlacing() {
                    this.placingRoomInfo = true;
                    this.placing = false;
                    this.editor?.cancelPlacement();
                    this.editor?.startRoomInfoPlacement();
                    this.statusText = '📍 Click on the panorama to set the Room Info marker position';
                },

                cancelRoomInfoPlacing() {
                    this.placingRoomInfo = false;
                    this.editor?.cancelRoomInfoPlacement();
                    this.statusText = '';
                },

                selectHotspot(h) {
                    this.editingHotspot = { ...h };
                    if (this.editor) {
                        this.editor.selectedHotspotId = h.id;
                        this.editor._highlightMarker(h.id);
                        // Rotate panorama to this hotspot so user can see it
                        this.editor.viewer?.rotate({ yaw: `${h.yaw}deg`, pitch: `${h.pitch}deg` });
                    }
                },

                deselectHotspot() {
                    this.editingHotspot = null;
                    this.editor?.deselectHotspot();
                },

                async saveCurrentHotspot() {
                    const h = this.editingHotspot;
                    if (!h || !h.title) return;

                    if (h.id) {
                        await this.$wire.updateHotspot(
                            h.id, h.title, h.description || '', h.icon,
                            h.pitch, h.yaw, h.action_type, h.action_target || null,
                            h.sort_order, h.is_active,
                            h.media_type || null, h.media_url || null
                        );
                    } else {
                        await this.$wire.saveHotspot(
                            this.activeWaypointId,
                            h.title, h.description || '', h.icon,
                            h.pitch, h.yaw, h.action_type, h.action_target || null,
                            h.sort_order, h.is_active,
                            h.media_type || null, h.media_url || null
                        );
                    }
                    this.editingHotspot = null;
                },

                async deleteCurrentHotspot() {
                    const h = this.editingHotspot;
                    if (!h?.id) return;
                    if (!confirm('Delete this hotspot?')) return;
                    await this.$wire.deleteHotspot(h.id);
                    this.editingHotspot = null;
                },

                async reloadHotspots() {
                    const hotspotsJson = await this.$wire.getHotspotsForScene(this.activeWaypointId);
                    this.hotspots = JSON.parse(hotspotsJson);
                    this.editor?.refreshMarkers(this.hotspots);
                    // Update count in sidebar
                    const wp = this.waypoints.find(w => w.id === this.activeWaypointId);
                    if (wp) wp.hotspots_count = this.hotspots.length;
                },

                actionColor(type) {
                    return { navigate:'#dbeafe', info:'#fef3c7', bookmark:'#ede9fe', 'external-link':'#d1fae5', audio:'#fce7f3', video:'#fce7f3' }[type] || '#f1f5f9';
                },

                actionLabel(type) {
                    return { navigate:'Navigate', info:'Info', bookmark:'Bookmark', 'external-link':'Link', audio:'Audio', video:'Video' }[type] || type;
                },

                waypointName(slug) {
                    return this.waypoints.find(w => w.slug === slug)?.name || slug;
                },

                quickToggleActive(h) {
                    h.is_active = !h.is_active;
                    if (this.editingHotspot?.id === h.id) this.editingHotspot.is_active = h.is_active;
                    this.$wire.updateHotspot(
                        h.id, h.title, h.description || '', h.icon,
                        h.pitch, h.yaw, h.action_type, h.action_target || null,
                        h.sort_order, h.is_active, h.media_type || null, h.media_url || null
                    );
                },

                duplicateCurrentHotspot() {
                    const h = this.editingHotspot;
                    if (!h?.id) return;
                    this.editingHotspot = {
                        id: null,
                        title: h.title + ' (copy)',
                        description: h.description,
                        icon: h.icon,
                        pitch: Math.max(-85, Math.min(85, parseFloat(h.pitch) + 3)),
                        yaw: parseFloat(h.yaw) + 3,
                        action_type: h.action_type,
                        action_target: h.action_target,
                        sort_order: this.hotspots.length,
                        is_active: h.is_active,
                        media_type: h.media_type,
                        media_url: h.media_url,
                    };
                },

                async dropHotspot(targetId) {
                    const fromId = this.draggedHotspotId;
                    this.draggedHotspotId = null;
                    this.dragOverIdx = null;
                    if (!fromId || fromId === targetId) return;
                    const fromIdx = this.hotspots.findIndex(h => h.id === fromId);
                    const toIdx   = this.hotspots.findIndex(h => h.id === targetId);
                    if (fromIdx < 0 || toIdx < 0) return;
                    const [item] = this.hotspots.splice(fromIdx, 1);
                    this.hotspots.splice(toIdx, 0, item);
                    this.hotspots.forEach((h, i) => { h.sort_order = i; });
                    this.editor?.refreshMarkers(this.hotspots);
                    await this.$wire.reorderHotspots(this.hotspots.map(h => h.id));
                },

                iconSvg(id, size = 16, color = 'currentColor') {
                    const s = size, c = color;
                    const icons = {
                        'chevron-up':        `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 15 12 9 18 15"/></svg>`,
                        'chevron-down':      `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>`,
                        'chevron-left':      `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>`,
                        'chevron-right':     `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`,
                        'chevron-up-right':  `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 17 17 7"/><polyline points="7 7 17 7 17 17"/></svg>`,
                        'chevron-down-right':`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 7 17 17"/><polyline points="17 7 17 17 7 17"/></svg>`,
                        'chevron-down-left': `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 7 7 17"/><polyline points="7 7 7 17 17 17"/></svg>`,
                        'chevron-up-left':   `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 17 7 7"/><polyline points="17 7 7 7 7 17"/></svg>`,
                        'info':              `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`,
                        'link':              `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>`,
                        'pin':               `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`,
                        'warning':           `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
                    };
                    return icons[id] || `<span style="font-size:${s}px">${id}</span>`;
                },

                updateLivePosition(position) {
                    if (!position) return;
                    this.liveYaw = parseFloat((position.yaw * 180 / Math.PI).toFixed(1));
                    this.livePitch = parseFloat((position.pitch * 180 / Math.PI).toFixed(1));
                },

                startLiveViewTracking() {
                    if (this._liveViewRaf) return;
                    const tick = () => {
                        this.syncLiveViewStats();
                        this._liveViewRaf = window.requestAnimationFrame(tick);
                    };
                    this._liveViewRaf = window.requestAnimationFrame(tick);
                },

                syncLiveViewStats() {
                    if (!this.editor?.viewer) return;
                    const pos = this.editor.viewer.getPosition();
                    this.updateLivePosition(pos);
                    this.liveZoom = Math.round(this.editor.viewer.getZoomLevel());
                },

                formatDegrees(value) {
                    return `${Number(value ?? 0).toFixed(1)}\u00B0`;
                },

                formatZoom(value) {
                    return `${Math.round(Number(value ?? 0))}%`;
                },
            };
        }
    </script>
</x-filament-panels::page>

