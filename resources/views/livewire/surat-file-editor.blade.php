{{--
    Halaman pembuka editor OnlyOffice (docx/pptx/xlsx) & viewer/editor PDF --
    setara backend/public/onlyoffice_editor.html pada proyek lama. Dibuka
    lewat navigasi dari Surat Review (lihat app/Livewire/SuratFileEditor.php
    untuk alasan arsitektur lengkap).

    -m-4/-lg:-m-6 di bawah menegasikan padding <main> dari layouts.app supaya
    editor/PDF viewer dapat setinggi mungkin (halaman ini SELALU dibuka
    sendirian, tidak pernah dalam layout gabungan dengan konten lain, jadi
    tidak masalah "mencuri" seluruh ruang konten) -- lihat komentar
    SuratFileEditor soal kenapa layouts.app (bukan layout kustom full-bleed)
    tetap dipakai: konsisten dengan surat-file-annotate.blade.php (halaman
    serupa).
--}}
<div class="-m-4 flex flex-col lg:-m-6" style="height: calc(100vh - 3.75rem);">
    @unless($supported)
        <div class="flex flex-1 items-center justify-center p-8 text-center">
            <div class="max-w-md rounded-2xl border border-app-error/30 bg-app-error/5 p-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-9 w-9 text-app-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16.5" r=".5" fill="currentColor"/>
                </svg>
                <p class="mt-3 text-sm font-medium text-app-error">{{ $unsupportedMessage }}</p>
            </div>
        </div>
    @else
        <div id="oo-status" class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
            <svg id="oo-status-spinner" class="h-8 w-8 animate-spin text-primary-green" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <p id="oo-status-text" class="max-w-md text-sm font-medium text-text-muted">Memuat "{{ $fileDisplayName }}"...</p>
        </div>

        <div id="oo-placeholder" class="hidden min-h-0 flex-1"></div>

        <script>
            (function () {
                var fileId = {{ $fileId }};
                var apiToken = @js($apiToken);
                var configEndpoint = @js($configEndpoint);
                var onlyofficePort = {{ $onlyofficePort }};

                var statusWrap = document.getElementById('oo-status');
                var statusText = document.getElementById('oo-status-text');
                var statusSpinner = document.getElementById('oo-status-spinner');
                var placeholder = document.getElementById('oo-placeholder');

                function showError(message) {
                    statusText.textContent = message;
                    if (statusSpinner) statusSpinner.remove();
                }

                function showViewer(node) {
                    node.className = 'h-full w-full border-0';
                    statusWrap.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                    placeholder.appendChild(node);
                }

                // Origin API PHP kita (Laravel) SELALU sama dengan origin
                // halaman ini sendiri -- lihat komentar apiBase di
                // onlyoffice_editor.html lama (alasan sama persis berlaku
                // di sini).
                var apiBase = window.location.origin;

                fetch(apiBase + '/' + configEndpoint + '?file_id=' + encodeURIComponent(fileId) +
                        '&token=' + encodeURIComponent(apiToken), {
                    headers: { Authorization: 'Bearer ' + apiToken },
                    credentials: 'same-origin',
                })
                    .then(function (res) {
                        if (!res.ok) {
                            return res.json().catch(function () { return {}; }).then(function (body) {
                                throw new Error(body.error || ('Gagal memuat konfigurasi editor (HTTP ' + res.status + ')'));
                            });
                        }
                        return res.json();
                    })
                    .then(function (data) {
                        document.title = 'Editor - ' + (data.perihal || 'Dokumen');
                        var config = data.config;
                        var permissions = (config.document && config.document.permissions) || {};

                        // PDF tanpa hak edit (permissions.edit=false, lihat
                        // OnlyOfficeController::editPdf -- ini kasus
                        // "pratinjau saja") -- pakai <iframe> PDF native
                        // browser, jauh lebih ringan & tidak bergantung sama
                        // sekali pada Document Server. config.document.url
                        // menunjuk ke docserver (host.docker.internal, tidak
                        // bisa diakses langsung dari browser) -- dibangun
                        // ulang jadi URL same-origin dari path+query-nya
                        // (path & token file sama, cuma host-nya diganti).
                        if (config.documentType === 'pdf' && permissions.edit === false) {
                            var srcUrl = new URL(config.document.url);
                            var nativeUrl = window.location.origin + srcUrl.pathname + srcUrl.search;
                            var iframe = document.createElement('iframe');
                            iframe.src = nativeUrl;
                            iframe.title = data.perihal || 'Dokumen PDF';
                            showViewer(iframe);
                            return;
                        }

                        var docServerBase = window.location.protocol + '//' + window.location.hostname + ':' + onlyofficePort;
                        var script = document.createElement('script');
                        script.src = docServerBase + '/web-apps/apps/api/documents/api.js';
                        script.onload = function () {
                            if (typeof DocsAPI === 'undefined') {
                                showError('OnlyOffice Document Server tidak tersedia di lingkungan ini.');
                                return;
                            }
                            var container = document.createElement('div');
                            container.id = 'oo-editor';
                            showViewer(container);
                            new DocsAPI.DocEditor('oo-editor', config);
                        };
                        script.onerror = function () {
                            showError('OnlyOffice Document Server tidak tersedia di lingkungan ini.');
                        };
                        document.head.appendChild(script);
                    })
                    .catch(function (err) {
                        showError(err.message || 'Gagal memuat dokumen.');
                    });
            })();
        </script>
    @endunless
</div>
