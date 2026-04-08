@props(['product' => null, 'categories' => collect()])

@php
    // Existing images on the product, mapped to objects with the public URL
    // and the storage path. The storage path is what we POST back so the
    // controller knows which files to keep on update.
    $existingImages = collect($product?->images ?? [])
        ->filter(fn ($p) => is_string($p) && $p !== '')
        ->map(fn ($p) => [
            'path' => $p,
            'url'  => \Illuminate\Support\Facades\Storage::disk('public')->url($p),
        ])
        ->values()
        ->all();
@endphp

{{-- ====================================================
     Product images uploader.
     - Up to 6 files (matches the controller validation cap)
     - Drag/drop OR click to browse
     - Live preview thumbnails for both existing + newly-picked files
     - First image is the "Main" — used as the catalog thumbnail
     - On edit, existing images are tracked via existing_images[] hidden
       inputs; removing one drops it from the array, controller deletes
       the file from disk on update.
     ==================================================== --}}
<div x-data="productImages({{ \Illuminate\Support\Js::from($existingImages) }})"
     @dragover.prevent="dragging = true"
     @dragleave.prevent="dragging = false"
     @drop.prevent="handleDrop($event)"
     class="bg-surface border border-th-border rounded-2xl p-6 mb-5">
    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
        <div class="min-w-0">
            <h3 class="text-[15px] font-bold text-primary">{{ __('catalog.product_images') }}</h3>
            <p class="text-[12px] text-muted mt-0.5">{{ __('catalog.product_images_hint') }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-accent/10 border border-accent/20 text-accent text-[11px] font-semibold flex-shrink-0">
            <span x-text="totalCount"></span>/<span>{{ 6 }}</span>
        </span>
    </div>

    {{-- Drop zone — hidden once we hit the 6-image cap to avoid the
         confusing case where the user can pick more files than the form
         will accept. --}}
    <label x-show="totalCount < 6"
           :class="dragging ? 'border-accent bg-accent/5' : 'border-th-border bg-page'"
           class="block border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all hover:border-accent/40">
        <svg class="w-9 h-9 text-muted mx-auto mb-2.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
        </svg>
        <p class="text-[13px] font-semibold text-primary mb-0.5">{{ __('catalog.drag_drop_images') }}</p>
        <p class="text-[11px] text-muted">JPG · PNG · WebP · 4MB max</p>
        <input type="file" name="images[]" multiple x-ref="fileInput"
               accept="image/jpeg,image/png,image/webp"
               @change="handleChange($event)"
               class="hidden">
    </label>

    {{-- Capacity-reached banner --}}
    <div x-show="totalCount >= 6" x-cloak class="rounded-xl border border-[#ffb020]/30 bg-[#ffb020]/10 px-4 py-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-[#ffb020] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 3h.01"/></svg>
        <p class="text-[12px] text-[#ffb020] font-semibold">{{ __('catalog.images_limit_reached') }}</p>
    </div>

    {{-- Hidden inputs that re-post the existing images the user kept.
         Driven by Alpine state so the order stays in sync after deletes. --}}
    <template x-for="(img, idx) in existing" :key="'ex-'+img.path">
        <input type="hidden" name="existing_images[]" :value="img.path">
    </template>

    {{-- Preview grid: existing first, then newly-picked. The first tile
         carries the "Main" badge regardless of source. --}}
    <div x-show="totalCount > 0" x-cloak class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
        {{-- Existing (already on disk) --}}
        <template x-for="(img, idx) in existing" :key="'ex-tile-'+img.path">
            <div class="relative aspect-square rounded-xl overflow-hidden bg-page border border-th-border group">
                <img :src="img.url" alt="" class="w-full h-full object-cover">
                <span x-show="idx === 0" class="absolute top-2 start-2 inline-flex items-center px-2 py-0.5 rounded-full bg-accent text-white text-[10px] font-bold uppercase tracking-wider shadow">
                    {{ __('catalog.primary_image') }}
                </span>
                <button type="button" @click="removeExisting(idx)"
                        :aria-label="'{{ __('catalog.remove_image') }}'"
                        class="absolute top-2 end-2 w-7 h-7 rounded-full bg-black/60 backdrop-blur-sm text-white flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-[#ff4d7f] transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
        {{-- Newly-picked (in memory, will be uploaded on submit) --}}
        <template x-for="(p, idx) in pending" :key="'pn-tile-'+idx">
            <div class="relative aspect-square rounded-xl overflow-hidden bg-page border border-th-border group">
                <img :src="p.preview" alt="" class="w-full h-full object-cover">
                <span x-show="existing.length === 0 && idx === 0" class="absolute top-2 start-2 inline-flex items-center px-2 py-0.5 rounded-full bg-accent text-white text-[10px] font-bold uppercase tracking-wider shadow">
                    {{ __('catalog.primary_image') }}
                </span>
                <span class="absolute bottom-1 start-2 text-[10px] text-white drop-shadow-md font-medium" x-text="formatBytes(p.file.size)"></span>
                <button type="button" @click="removePending(idx)"
                        :aria-label="'{{ __('catalog.remove_image') }}'"
                        class="absolute top-2 end-2 w-7 h-7 rounded-full bg-black/60 backdrop-blur-sm text-white flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-[#ff4d7f] transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>
</div>

<div class="bg-surface border border-th-border rounded-2xl p-6 space-y-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.name') }}</label>
            <input type="text" name="name" required value="{{ old('name', $product?->name) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.name_ar') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $product?->name_ar) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.sku') }}</label>
            <input type="text" name="sku" maxlength="64" value="{{ old('sku', $product?->sku) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary font-mono focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.hs_code') }}</label>
            <div class="flex gap-2">
                <input type="text" id="hs_code_input" name="hs_code" maxlength="16" value="{{ old('hs_code', $product?->hs_code) }}"
                       class="flex-1 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary font-mono focus:outline-none focus:border-accent" />
                <button type="button" id="hs_code_suggest_btn"
                        class="px-3 rounded-lg bg-accent/10 border border-accent/30 text-[11px] font-semibold text-accent hover:bg-accent/20 transition-colors whitespace-nowrap">
                    {{ __('catalog.suggest_hs') }}
                </button>
            </div>
            <div id="hs_code_suggestions" class="mt-2 space-y-1 hidden"></div>
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.category') }}</label>
            <select name="category_id"
                    class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                <option value="">— {{ __('common.select') }} —</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('category_id', $product?->category_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.price') }}</label>
                <input type="number" step="0.01" min="0" name="base_price" required value="{{ old('base_price', $product?->base_price) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.currency') }}</label>
                <select name="currency" required
                        class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                    @foreach(['AED', 'USD', 'EUR', 'SAR'] as $cur)
                        <option value="{{ $cur }}" @selected(old('currency', $product?->currency ?? 'AED') === $cur)>{{ $cur }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.unit') }}</label>
                <input type="text" name="unit" required value="{{ old('unit', $product?->unit ?? 'pcs') }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.min_order_qty') }}</label>
                <input type="number" min="1" name="min_order_qty" required value="{{ old('min_order_qty', $product?->min_order_qty ?? 1) }}"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.stock_qty') }}</label>
            <input type="number" min="0" name="stock_qty" value="{{ old('stock_qty', $product?->stock_qty) }}"
                   placeholder="{{ __('catalog.stock_unlimited') }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.lead_time') }}</label>
            <input type="number" min="0" max="365" name="lead_time_days" required value="{{ old('lead_time_days', $product?->lead_time_days ?? 7) }}"
                   class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
        </div>
    </div>

    <div>
        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('catalog.description') }}</label>
        <textarea name="description" rows="4"
                  class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">{{ old('description', $product?->description) }}</textarea>
    </div>

    <label class="flex items-center gap-2 text-[13px] text-primary">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))>
        {{ __('catalog.active') }}
    </label>
</div>

{{-- Phase 4 / Sprint 15 — Product variants editor.

     The supplier can attach SKU-level variations (size, color, tier).
     Each row carries an explicit price modifier on top of the base price,
     an optional stock count, and a free-form attributes_json field for
     buyer-facing labels (rendered as "Color: red, Size: large" later).
     Submitting with no rows leaves the product variant-less, which is
     exactly how Phase 1-3 products behave. --}}
<div x-data="variantsEditor({{ $product?->variants?->toJson() ?? '[]' }})"
     class="mt-5 bg-surface border border-th-border rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-[15px] font-bold text-primary">{{ __('catalog.variants') }}</h3>
            <p class="text-[12px] text-muted">{{ __('catalog.variants_hint') }}</p>
        </div>
        <button type="button" @click="addRow()"
                class="px-3 py-2 rounded-lg bg-accent/10 border border-accent/30 text-[12px] font-semibold text-accent hover:bg-accent/20 transition-colors">
            + {{ __('catalog.add_variant') }}
        </button>
    </div>

    <template x-if="rows.length === 0">
        <p class="text-[12px] text-muted italic">{{ __('catalog.no_variants_yet') }}</p>
    </template>

    <div class="space-y-3">
        <template x-for="(row, idx) in rows" :key="row._key">
            <div class="bg-page border border-th-border rounded-xl p-4" x-show="!row._delete">
                <input type="hidden" :name="`variants[${idx}][id]`" :value="row.id ?? ''">
                <input type="hidden" :name="`variants[${idx}][_delete]`" value="0">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-4">
                        <label class="block text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.variant_name') }}</label>
                        <input type="text" :name="`variants[${idx}][name]`" x-model="row.name" required
                               placeholder="Red / Large"
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.sku') }}</label>
                        <input type="text" :name="`variants[${idx}][sku]`" x-model="row.sku" maxlength="64"
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary font-mono"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.price_modifier') }}</label>
                        <input type="number" step="0.01" :name="`variants[${idx}][price_modifier]`" x-model="row.price_modifier"
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.stock_qty') }}</label>
                        <input type="number" min="0" :name="`variants[${idx}][stock_qty]`" x-model="row.stock_qty"
                               placeholder="∞"
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="button" @click="removeRow(idx)"
                                class="w-full h-9 rounded-lg bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] hover:bg-[#ff4d7f]/20 text-[14px]">×</button>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.attributes_json') }}</label>
                    <input type="text" :name="`variants[${idx}][attributes_json]`" x-model="row.attributes_json"
                           placeholder='{"color":"red","size":"large"}'
                           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[11px] text-primary font-mono"/>
                </div>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
// ====================================================================
// Product images uploader.
//
// Tracks two parallel lists:
//  - existing: paths already on disk (rendered from $product->images)
//  - pending:  newly-picked File objects with object-URL previews
//
// On submit, the form posts:
//  - existing_images[]  → which existing paths to KEEP
//  - images[]           → newly uploaded files
//
// The controller diffs existing_images[] against the product's current
// images and deletes anything not in the keep-list, then appends the
// new uploads. Order is preserved: existing first, then pending.
// ====================================================================
function productImages(initial) {
    return {
        existing: (initial || []).map(i => ({ path: i.path, url: i.url })),
        pending: [],
        dragging: false,
        max: 6,

        get totalCount() { return this.existing.length + this.pending.length; },

        // File input change handler. Caps at 6 total to keep the UI honest.
        handleChange(e) {
            this.addFiles(Array.from(e.target.files || []));
            // The hidden <input> still holds ALL picked files even after we
            // splice this.pending. Re-sync it via DataTransfer so the form
            // submits exactly the files the user sees in the preview grid.
            this.syncInput();
        },

        // Drop handler. Same flow as the change handler — we hand the
        // files off to addFiles() so the cap + duplicate logic stays in
        // one place, then write back to the hidden input.
        handleDrop(e) {
            this.dragging = false;
            const dropped = Array.from(e.dataTransfer?.files || []).filter(f => f.type.startsWith('image/'));
            if (dropped.length === 0) return;
            this.addFiles(dropped);
            this.syncInput();
        },

        addFiles(files) {
            const room = this.max - this.totalCount;
            if (room <= 0) return;
            files.slice(0, room).forEach(file => {
                this.pending.push({ file, preview: URL.createObjectURL(file) });
            });
        },

        removeExisting(i) {
            this.existing.splice(i, 1);
        },

        removePending(i) {
            // Free the object URL so we don't leak memory if the user
            // adds + removes lots of images before submitting.
            URL.revokeObjectURL(this.pending[i].preview);
            this.pending.splice(i, 1);
            this.syncInput();
        },

        // Rebuild the hidden file input from the current pending list.
        // DataTransfer is the only browser API that lets us programmatically
        // set <input type=file>'s files property.
        syncInput() {
            const dt = new DataTransfer();
            this.pending.forEach(p => dt.items.add(p.file));
            this.$refs.fileInput.files = dt.files;
        },

        formatBytes(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0; let n = bytes;
            while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
            return n.toFixed(n >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
        },
    };
}

function variantsEditor(initial) {
    // Hydrate rows from existing variants. Stringify attributes for the
    // single-line text input so the supplier can edit JSON inline.
    const seed = (initial || []).map((v, i) => ({
        _key: 'v' + i,
        id: v.id,
        name: v.name || '',
        sku: v.sku || '',
        price_modifier: v.price_modifier ?? 0,
        stock_qty: v.stock_qty ?? '',
        attributes_json: v.attributes ? JSON.stringify(v.attributes) : '',
        _delete: false,
    }));
    let counter = seed.length;
    return {
        rows: seed,
        addRow() {
            this.rows.push({
                _key: 'v' + (++counter),
                id: null, name: '', sku: '', price_modifier: 0,
                stock_qty: '', attributes_json: '', _delete: false,
            });
        },
        removeRow(idx) {
            // Existing variants get a soft-delete flag the controller
            // honours. New rows are dropped on the floor.
            if (this.rows[idx].id) {
                this.rows[idx]._delete = true;
                // Flip the hidden input to "1" so the controller deletes it.
                this.$nextTick(() => {
                    const inp = document.querySelector('input[name="variants[' + idx + '][_delete]"]');
                    if (inp) inp.value = '1';
                });
            } else {
                this.rows.splice(idx, 1);
            }
        },
    };
}
</script>
@endpush

@push('scripts')
<script>
(function() {
    const btn      = document.getElementById('hs_code_suggest_btn');
    const input    = document.getElementById('hs_code_input');
    const wrap     = document.getElementById('hs_code_suggestions');
    const descEl   = document.querySelector('textarea[name="description"]');
    const nameEl   = document.querySelector('input[name="name"]');
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    if (!btn) return;

    btn.addEventListener('click', async function() {
        const description = (descEl?.value || nameEl?.value || '').trim();
        if (description.length < 3) {
            wrap.classList.remove('hidden');
            wrap.innerHTML = '<div class="text-[11px] text-[#ff4d7f]">{{ __('catalog.hs_need_description') }}</div>';
            return;
        }
        btn.disabled = true;
        btn.textContent = '...';
        try {
            const res = await fetch('{{ route('dashboard.products.suggest-hs') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ description }),
            });
            const data = await res.json();
            if (!data.suggestions || data.suggestions.length === 0) {
                wrap.classList.remove('hidden');
                wrap.innerHTML = '<div class="text-[11px] text-muted">{{ __('catalog.hs_no_suggestions') }}</div>';
                return;
            }
            wrap.classList.remove('hidden');
            wrap.innerHTML = data.suggestions.map(s => `
                <button type="button" data-code="${s.code}" class="hs-suggestion w-full text-start bg-surface-2 hover:bg-accent/10 border border-th-border rounded-lg px-3 py-2 text-[12px]">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-bold text-accent">${s.code}</span>
                        <span class="text-[10px] text-muted">${Math.round((s.confidence || 0) * 100)}%</span>
                    </div>
                    <div class="text-muted mt-0.5">${s.description || ''}</div>
                </button>
            `).join('');
            wrap.querySelectorAll('.hs-suggestion').forEach(el => {
                el.addEventListener('click', function() {
                    input.value = this.dataset.code;
                    wrap.classList.add('hidden');
                });
            });
        } catch (e) {
            wrap.classList.remove('hidden');
            wrap.innerHTML = '<div class="text-[11px] text-[#ff4d7f]">{{ __('catalog.hs_error') }}</div>';
        } finally {
            btn.disabled = false;
            btn.textContent = '{{ __('catalog.suggest_hs') }}';
        }
    });
})();
</script>
@endpush
