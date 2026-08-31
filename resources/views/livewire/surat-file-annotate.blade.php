<div>
    <script>
        // Editor "Coret Foto" -- gambar bebas (freehand) + catatan tempel di
        // atas kanvas HTML5 murni, dipasang lewat Alpine x-data di bawah.
        // Meniru model visual & data lib/widgets/coretan_markup.dart dan
        // lib/models/pdf_annotation.dart (Flutter) 1:1 -- lihat komentar di
        // masing-masing method.
        function annotateEditor({ initialCoretan, initialCatatan, currentUser, imageUrl }) {
            return {
                // --- Data markup (bentuk PdfAnnotationStroke/Catatan Dart, snake_case field JSON) ---
                coretan: initialCoretan,
                catatan: initialCatatan,
                undoStack: [], // array of 'coretan' | 'catatan' -- tipe aksi TERAKHIR, untuk undo satu langkah.

                // --- Alat & palet (SAMA PERSIS dgn image_annotate_screen.dart::_palet) ---
                palette: [
                    { hex: '#F44336', nama: 'Merah' },
                    { hex: '#000000', nama: 'Hitam' },
                    { hex: '#2196F3', nama: 'Biru' },
                    { hex: '#4CAF50', nama: 'Hijau' },
                    { hex: '#FF9800', nama: 'Oranye' },
                ],
                tool: 'pena', // 'pena' | 'catatan'
                warnaAktif: '#F44336',
                tebalPena: 4, // piksel LAYAR (belum dinormalisasi) -- lihat _tebalMin/_tebalMax Flutter.
                tebalMin: 1,
                tebalMax: 16,

                // --- State kanvas ---
                imageUrl,
                currentUser,
                drawing: false,
                currentPoints: [], // {x,y} normalisasi 0..1, coretan yang SEDANG digambar tangan.
                imgError: false,
                imgLoaded: false,

                // --- Catatan (dialog tambah teks) ---
                noteModalOpen: false,
                noteModalPos: null,
                noteText: '',

                // --- Simpan ---
                saving: false,
                savedMessage: '',
                dirty: false,

                init() {
                    const img = this.$refs.img;
                    const onReady = () => {
                        this.imgLoaded = true;
                        this.$nextTick(() => this.resizeCanvas());
                    };
                    img.addEventListener('load', onReady);
                    img.addEventListener('error', () => { this.imgError = true; });
                    if (img.complete && img.naturalWidth) onReady();

                    const ro = new ResizeObserver(() => this.resizeCanvas());
                    ro.observe(this.$refs.imgWrap);
                    window.addEventListener('resize', () => this.resizeCanvas());
                },

                resizeCanvas() {
                    const canvas = this.$refs.canvas;
                    const rect = this.$refs.imgWrap.getBoundingClientRect();
                    if (rect.width < 1 || rect.height < 1) return;
                    canvas.width = rect.width;
                    canvas.height = rect.height;
                    this.redraw();
                },

                // Titik pointer (mouse/pena/sentuh -- lewat Pointer Events, sudah
                // menyatukan ketiganya) DINORMALISASI 0..1 relatif ke kanvas, sama
                // seperti _lanjutCoret()/_tambahCatatan() di Flutter membagi
                // localPosition dengan _ukuranKanvas.
                pointerPos(e) {
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    const x = (e.clientX - rect.left) / rect.width;
                    const y = (e.clientY - rect.top) / rect.height;
                    return { x: Math.min(Math.max(x, 0), 1), y: Math.min(Math.max(y, 0), 1) };
                },

                onPointerDown(e) {
                    if (this.tool !== 'pena') return;
                    this.$refs.canvas.setPointerCapture(e.pointerId);
                    this.drawing = true;
                    this.currentPoints = [this.pointerPos(e)];
                },

                onPointerMove(e) {
                    if (!this.drawing) return;
                    this.currentPoints.push(this.pointerPos(e));
                    this.redraw();
                },

                onPointerUp() {
                    if (!this.drawing) return;
                    this.drawing = false;
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    if (this.currentPoints.length >= 2 && rect.width > 0) {
                        this.coretan.push({
                            warna: this.warnaAktif,
                            // Dinormalisasi terhadap lebar kanvas SAAT digambar -- sama
                            // seperti PdfAnnotationStroke.tebal (lihat _selesaiCoret Flutter).
                            tebal: this.tebalPena / rect.width,
                            dibuat_oleh: this.currentUser,
                            titik: this.currentPoints.map((p) => [p.x, p.y]),
                        });
                        this.undoStack.push('coretan');
                        this.dirty = true;
                    }
                    this.currentPoints = [];
                    this.redraw();
                },

                onCanvasClick(e) {
                    if (this.tool !== 'catatan') return;
                    this.noteModalPos = this.pointerPos(e);
                    this.noteText = '';
                    this.noteModalOpen = true;
                    this.$nextTick(() => this.$refs.noteInput && this.$refs.noteInput.focus());
                },

                confirmNote() {
                    const teks = this.noteText.trim();
                    this.noteModalOpen = false;
                    if (!teks || !this.noteModalPos) return;
                    this.catatan.push({
                        x: this.noteModalPos.x,
                        y: this.noteModalPos.y,
                        teks,
                        warna: this.warnaAktif,
                        dibuat_oleh: this.currentUser,
                    });
                    this.undoStack.push('catatan');
                    this.dirty = true;
                },

                undo() {
                    const last = this.undoStack.pop();
                    if (last === 'coretan') this.coretan.pop();
                    else if (last === 'catatan') this.catatan.pop();
                    if (last) this.dirty = true;
                    this.redraw();
                },

                hapusSemua() {
                    if (!this.coretan.length && !this.catatan.length) return;
                    if (!confirm('Hapus SEMUA coretan & catatan di foto ini?')) return;
                    this.coretan = [];
                    this.catatan = [];
                    this.undoStack = [];
                    this.dirty = true;
                    this.redraw();
                },

                // Painter kanvas -- cermin CoretanTersimpanPainter + CoretanBerjalanPainter
                // (widgets/coretan_markup.dart): garis stroke/round-cap/round-join,
                // ketebalan dikali lebar kanvas SEKARANG supaya proporsional di ukuran
                // layar berapa pun.
                redraw() {
                    const canvas = this.$refs.canvas;
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    const drawStroke = (titik, warna, tebalPx) => {
                        if (titik.length < 2) return;
                        ctx.beginPath();
                        ctx.strokeStyle = warna;
                        ctx.lineWidth = Math.max(1, tebalPx);
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.moveTo(titik[0][0] * canvas.width, titik[0][1] * canvas.height);
                        for (let i = 1; i < titik.length; i++) {
                            ctx.lineTo(titik[i][0] * canvas.width, titik[i][1] * canvas.height);
                        }
                        ctx.stroke();
                    };

                    for (const s of this.coretan) {
                        drawStroke(s.titik, s.warna, s.tebal * canvas.width);
                    }
                    if (this.drawing && this.currentPoints.length >= 2) {
                        drawStroke(this.currentPoints.map((p) => [p.x, p.y]), this.warnaAktif, this.tebalPena);
                    }
                },

                save() {
                    this.saving = true;
                    this.savedMessage = '';
                    // Bentuk PdfAnnotationDokumen::toJson() (Dart) -- foto = SATU
                    // "halaman" nomor 1 (lihat utils/image_markup.dart::halamanFotoTunggal).
                    // Halaman kosong SENGAJA tidak disertakan sama sekali (bukan {}),
                    // supaya SuratFileAnnotate::save() menghapus barisnya -- sama
                    // seperti PdfAnnotationHalaman.isEmpty di Dart.
                    const halamanKosong = this.coretan.length === 0 && this.catatan.length === 0;
                    const document = { halaman: halamanKosong ? {} : { 1: { coretan: this.coretan, catatan: this.catatan } } };

                    this.$wire.save(document).then(() => {
                        this.saving = false;
                        this.dirty = false;
                        this.savedMessage = 'Markup foto tersimpan.';
                        setTimeout(() => { this.savedMessage = ''; }, 3000);
                    }).catch(() => {
                        this.saving = false;
                        this.savedMessage = 'Gagal menyimpan markup.';
                    });
                },

                confirmLeaveIfDirty(e) {
                    if (this.dirty) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                },
            };
        }
    </script>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-app-surface p-4 shadow-sm">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-text-dark">Coret Foto: {{ $suratFile->file_original_name }}</p>
            <p class="text-xs text-text-muted">Markup disimpan terpisah sebagai data vektor, TIDAK mengubah file foto aslinya.</p>
        </div>
        <a
            href="{{ route('surat.review', $suratFile->surat_id) }}"
            class="shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-text-dark transition hover:bg-app-background"
        >
            &larr; Kembali ke Surat
        </a>
    </div>

    <div
        x-data="annotateEditor({
            initialCoretan: @js($initialCoretan),
            initialCatatan: @js($initialCatatan),
            currentUser: @js($currentUserName),
            imageUrl: @js($imageUrl),
        })"
        x-init="init()"
        @beforeunload.window="confirmLeaveIfDirty($event)"
        class="flex flex-col gap-4"
    >
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-center gap-4 rounded-xl bg-app-surface p-3 shadow-sm">
            {{-- Palet warna --}}
            <div class="flex flex-wrap items-center gap-2">
                <template x-for="c in palette" :key="c.hex">
                    <button
                        type="button"
                        @click="warnaAktif = c.hex; tool = 'pena'"
                        :title="c.nama"
                        class="h-7 w-7 shrink-0 rounded-full border-2 transition"
                        :style="`background-color:${c.hex}`"
                        :class="(tool === 'pena' && warnaAktif === c.hex) ? 'border-primary-green ring-2 ring-primary-green/40' : 'border-gray-300'"
                    ></button>
                </template>
            </div>

            <div class="h-6 w-px bg-gray-200"></div>

            {{-- Ketebalan pena --}}
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-text-muted">Tebal</span>
                <input type="range" x-model.number="tebalPena" min="1" max="16" step="1" class="w-24 accent-primary-green">
                <span class="w-5 text-xs text-text-muted" x-text="tebalPena"></span>
            </div>

            <div class="h-6 w-px bg-gray-200"></div>

            {{-- Alat --}}
            <div class="flex items-center gap-2">
                <button
                    type="button" @click="tool = 'pena'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                    :class="tool === 'pena' ? 'bg-primary-green text-white' : 'bg-app-background text-text-dark hover:bg-gray-200'"
                >Pena</button>
                <button
                    type="button" @click="tool = 'catatan'"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                    :class="tool === 'catatan' ? 'bg-primary-green text-white' : 'bg-app-background text-text-dark hover:bg-gray-200'"
                >Catatan</button>
            </div>

            <div class="h-6 w-px bg-gray-200"></div>

            {{-- Undo / Hapus --}}
            <div class="flex items-center gap-2">
                <button
                    type="button" @click="undo()" :disabled="!undoStack.length"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-text-dark transition hover:bg-app-background disabled:opacity-40"
                >Undo</button>
                <button
                    type="button" @click="hapusSemua()" :disabled="!coretan.length && !catatan.length"
                    class="rounded-lg border border-app-error px-3 py-1.5 text-xs font-medium text-app-error transition hover:bg-app-error/5 disabled:opacity-40"
                >Hapus Semua</button>
            </div>

            <div class="h-6 w-px bg-gray-200"></div>

            {{-- Simpan --}}
            <div class="flex items-center gap-2">
                <span x-show="savedMessage" x-text="savedMessage" class="text-xs font-medium text-secondary-green"></span>
                <button
                    type="button" @click="save()" :disabled="saving"
                    class="rounded-lg bg-gold px-4 py-1.5 text-xs font-semibold text-primary-green transition hover:bg-gold-light disabled:opacity-50"
                >
                    <span x-show="!saving">Simpan</span>
                    <span x-show="saving">Menyimpan&hellip;</span>
                </button>
            </div>
        </div>

        {{-- Kanvas --}}
        <div class="flex justify-center rounded-xl bg-gray-800 p-4">
            <div x-show="imgError" class="py-16 text-center text-sm text-white/80">
                Gagal memuat foto. Coba muat ulang halaman ini.
            </div>
            <div x-show="!imgError" x-ref="imgWrap" class="relative inline-block max-w-full bg-white shadow-lg" style="line-height:0;">
                <img
                    x-ref="img" :src="imageUrl" alt="Foto lampiran"
                    class="block max-h-[75vh] w-auto max-w-full select-none"
                    draggable="false"
                >
                <canvas
                    x-ref="canvas"
                    class="absolute inset-0 h-full w-full touch-none"
                    :class="tool === 'pena' ? 'cursor-crosshair' : 'cursor-copy'"
                    @pointerdown="onPointerDown($event)"
                    @pointermove="onPointerMove($event)"
                    @pointerup="onPointerUp($event)"
                    @pointercancel="onPointerUp($event)"
                    @click="onCanvasClick($event)"
                ></canvas>
                <template x-for="(c, idx) in catatan" :key="idx">
                    <div
                        class="pointer-events-none absolute max-w-[160px] rounded-md px-2 py-1.5 text-[11px] leading-tight text-black/85 shadow"
                        :style="`left:${c.x * 100}%; top:${c.y * 100}%; background-color:${c.warna}`"
                        x-text="c.teks"
                    ></div>
                </template>
            </div>
        </div>

        {{-- Dialog tambah catatan --}}
        <div x-show="noteModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="noteModalOpen = false">
            <div @click.outside="noteModalOpen = false" class="w-full max-w-sm rounded-xl bg-app-surface p-5 shadow-xl">
                <h2 class="mb-3 text-sm font-semibold text-text-dark">Tambah Catatan</h2>
                <textarea
                    x-ref="noteInput" x-model="noteText" rows="3" maxlength="280"
                    placeholder="Tulis catatan..."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                    @keydown.enter.meta="confirmNote()"
                ></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="noteModalOpen = false" class="rounded-lg px-3 py-2 text-xs font-medium text-text-muted hover:bg-app-background">Batal</button>
                    <button type="button" @click="confirmNote()" class="rounded-lg bg-primary-green px-4 py-2 text-xs font-semibold text-white hover:bg-secondary-green">Tambah</button>
                </div>
            </div>
        </div>
    </div>
</div>
