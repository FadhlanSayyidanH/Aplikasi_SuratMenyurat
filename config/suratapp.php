<?php

// Konfigurasi khusus aplikasi Surat Menyurat Ditajenad -- migrasi dari
// backend/config/*.php pada proyek PHP murni sebelumnya.
return [
    'auth_token_ttl_seconds' => (int) env('AUTH_TOKEN_TTL_SECONDS', 12 * 60 * 60),
    'file_token_ttl_seconds' => (int) env('FILE_TOKEN_TTL_SECONDS', 30 * 60),
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),

    'onlyoffice_port' => (int) env('ONLYOFFICE_PORT', 8801),
    // Port yang dipakai BROWSER klien untuk memuat JS SDK Document Server
    // saat produksi (bukan 'onlyoffice_port' di atas -- itu port Docker
    // yang cuma bisa diakses dari VM itu sendiri, 127.0.0.1). Di produksi,
    // Nginx me-reverse-proxy Document Server lewat port HTTPS terpisah ini
    // (sertifikat sama dengan situs utama) supaya browser publik bisa
    // memuatnya -- lihat SuratFileEditor::mount() & deploy/nginx-*.conf.
    'onlyoffice_public_port' => (int) env('ONLYOFFICE_PUBLIC_PORT', 8443),
    'onlyoffice_jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),
    'onlyoffice_host_gateway' => env('ONLYOFFICE_HOST_GATEWAY', 'host.docker.internal:8800'),

    // Origin CORS diizinkan tambahan (domain produksi) di luar
    // localhost/IP LAN yang selalu diizinkan (lihat CorsMiddleware).
    'cors_extra_origin' => env('CORS_EXTRA_ORIGIN'),

    // Tombol mengambang "Kontak Admin" (x-kontak-admin) -- nomor WA format
    // internasional TANPA "+" (mis. 62812xxxxxxx), sesuai format wa.me.
    // Sengaja lewat .env (bukan hardcode di blade) supaya repo publik tidak
    // usah menyertakan kontak pribadi asli -- isi nilai sesungguhnya di
    // .env produksi saja.
    'kontak_admin_nama' => env('KONTAK_ADMIN_NAMA', 'Admin'),
    'kontak_admin_wa' => env('KONTAK_ADMIN_WA', ''),

    'nama_posisi_tetap' => ['Kabagtu Subditbinum', 'Turmin Subditbinum', 'Kasubditbinum'],

    // Instruksi disposisi (multi-pilih) -- kode => label tampil.
    'instruksi_disposisi_valid' => [
        'acc' => 'ACC',
        'udl' => 'UDL',
        'udk' => 'UDK',
        'tindak_lanjuti' => 'TINDAK LANJUTI',
        'monitor' => 'MONITOR',
        'buat_sprin_kep_st_se' => 'BUAT SPRIN/KEP/ST/SE',
        'laporkan_hasilnya' => 'LAPORKAN HASILNYA',
        'pedomani' => 'PEDOMANI',
        'saran' => 'SARAN',
        'bantu' => 'BANTU',
        'pelajari' => 'PELAJARI',
        'siapkan' => 'SIAPKAN',
        'catat' => 'CATAT',
        'ingatkan' => 'INGATKAN',
        'koordinasikan' => 'KOORDINASIKAN',
        'infokan_ke_anggota' => 'INFOKAN KE ANGGOTA',
    ],

    // Ekstensi & MIME type lampiran surat yang diterima.
    'mime_foto' => [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ],
    'mime_keluar' => [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ],
    'max_upload_size_bytes' => 200 * 1024 * 1024,

    // Target ukuran maksimal PER FILE lampiran setelah kompresi otomatis
    // (App\Services\SuratFileService::kompresJikaPerlu()) -- dijamin
    // tercapai untuk foto & PDF (Ghostscript), usaha terbaik saja untuk
    // docx/pptx/xlsx (cuma gambar tertanam yang bisa dikompres, dokumen
    // teks murni tanpa gambar besar tidak akan banyak mengecil).
    'compress_target_bytes' => (int) env('COMPRESS_TARGET_BYTES', 1 * 1024 * 1024),

    // Lokasi fisik penyimpanan lampiran surat, sama seperti
    // backend/storage/uploads pada proyek lama (di luar public/ supaya
    // tidak bisa diakses langsung tanpa lewat file_token).
    'uploads_path' => storage_path('app/uploads'),
    'preview_cache_path' => storage_path('app/uploads/preview_cache'),
];
