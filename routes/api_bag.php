<?php

use App\Http\Controllers\Api\BagController;
use App\Http\Controllers\Api\BagMasukController;
use App\Http\Controllers\Api\BagMemberController;
use Illuminate\Support\Facades\Route;

// Bag (unit organisasi) -- bag_list/create/update/delete.php,
// bag_member_*.php, bag_kasi_grup_*.php, bag_disposisi_anggota_*.php,
// bag_set_turmin.php, bag_set_kasubdit.php.
//
// bag_list.php & jalur_keluar_saya.php: SEMUA akun login (bukan cuma
// admin) -- dipakai form Surat Keluar & checkbox disposisi Surat Masuk.
Route::middleware('auth.token')->group(function () {
    Route::get('/bag_list.php', [BagController::class, 'list']);
    Route::get('/jalur_keluar_saya.php', [BagController::class, 'jalurKeluarSaya']);
});

// Sisanya (CRUD Bag, member/kasi grup, disposisi anggota, set turmin/kasubdit)
// admin-only.
Route::middleware(['auth.token', 'auth.admin'])->group(function () {
    Route::post('/bag_create.php', [BagController::class, 'create']);
    Route::post('/bag_update.php', [BagController::class, 'update']);
    Route::post('/bag_delete.php', [BagController::class, 'delete']);

    Route::post('/bag_member_add.php', [BagMemberController::class, 'add']);
    Route::post('/bag_member_remove.php', [BagMemberController::class, 'remove']);
    Route::post('/bag_member_update.php', [BagMemberController::class, 'update']);
    Route::post('/bag_kasi_grup_create.php', [BagMemberController::class, 'kasiGrupCreate']);
    Route::post('/bag_kasi_grup_delete.php', [BagMemberController::class, 'kasiGrupDelete']);

    Route::post('/bag_disposisi_anggota_add.php', [BagMasukController::class, 'disposisiAnggotaAdd']);
    Route::post('/bag_disposisi_anggota_remove.php', [BagMasukController::class, 'disposisiAnggotaRemove']);
    Route::post('/bag_disposisi_anggota_update.php', [BagMasukController::class, 'disposisiAnggotaUpdate']);
    Route::post('/bag_set_turmin.php', [BagMasukController::class, 'setTurmin']);
    Route::post('/bag_set_kasubdit.php', [BagMasukController::class, 'setKasubdit']);
});
