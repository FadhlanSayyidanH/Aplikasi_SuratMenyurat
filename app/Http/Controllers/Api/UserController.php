<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Migrasi dari backend/public/users_list.php, users_create.php,
 * users_update.php, users_delete.php, user_akses_update.php (proyek PHP
 * lama). Semua endpoint di sini admin-only (lihat routes/api_users.php,
 * middleware auth.token + auth.admin).
 */
class UserController extends Controller
{
    private const ROLES = ['admin', 'user', 'pimpinan', 'turmin'];

    public function list(Request $request)
    {
        $users = User::query()
            ->orderBy('id', 'asc')
            ->get(['id', 'username', 'nama', 'role', 'boleh_input_masuk', 'boleh_input_keluar']);

        $data = $users->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama,
            'role' => $user->role,
            'boleh_input_masuk' => (bool) $user->boleh_input_masuk,
            'boleh_input_keluar' => (bool) $user->boleh_input_keluar,
        ]);

        return response()->json($data);
    }

    public function create(Request $request)
    {
        $authUser = $request->user();

        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $nama = trim((string) $request->input('nama', ''));
        $role = trim((string) $request->input('role', 'user'));

        if ($username === '' || $nama === '') {
            throw new ApiException('Username dan nama wajib diisi', 400);
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new ApiException('Role harus "admin", "user", "pimpinan", atau "turmin"', 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
            throw new ApiException('Username hanya boleh huruf, angka, titik, dan underscore', 400);
        }
        if (strlen($password) < 6) {
            throw new ApiException('Password minimal 6 karakter', 400);
        }

        if (User::query()->where('username', $username)->exists()) {
            throw new ApiException('Username sudah dipakai', 409);
        }

        // Dua akun pejabat dengan `nama` yang sama akan membuat sistem
        // pencocokan identitas (DisposisiRoleLabel.fromLabel) ambigu -- role
        // 'admin' dikecualikan, sama seperti PHP lama.
        if ($role !== 'admin') {
            $clash = User::query()->where('nama', $nama)->where('role', '!=', 'admin')->exists();
            if ($clash) {
                throw new ApiException("Sudah ada akun pejabat lain dengan nama \"$nama\"", 409);
            }
        }

        $user = new User();
        $user->username = $username;
        $user->password = Hash::make($password);
        $user->nama = $nama;
        $user->role = $role;
        $user->save();

        ActivityLogger::log($request, $authUser->username, $authUser->nama, 'create', "Tambah akun user $nama ($username), role $role");

        return response()->json([
            'id' => $user->id,
            'username' => $username,
            'nama' => $nama,
            'role' => $role,
        ]);
    }

    public function update(Request $request)
    {
        $authUser = $request->user();

        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        $username = trim((string) $request->input('username', ''));
        $nama = trim((string) $request->input('nama', ''));
        $role = trim((string) $request->input('role', 'user'));
        $password = (string) $request->input('password', ''); // kosong = password tidak diubah

        if (!$id) {
            throw new ApiException('ID user tidak valid', 400);
        }
        if ($username === '' || $nama === '') {
            throw new ApiException('Username dan nama wajib diisi', 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
            throw new ApiException('Username hanya boleh huruf, angka, titik, dan underscore', 400);
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new ApiException('Role harus "admin", "user", "pimpinan", atau "turmin"', 400);
        }
        if ($password !== '' && strlen($password) < 6) {
            throw new ApiException('Password baru minimal 6 karakter', 400);
        }

        $old = User::query()->find($id);
        if (!$old) {
            throw new ApiException('User tidak ditemukan', 404);
        }
        if ($old->username === 'admin' && $role !== 'admin') {
            throw new ApiException('Role akun Administrator tidak bisa diturunkan', 400);
        }

        $usernameClash = User::query()->where('username', $username)->where('id', '!=', $id)->exists();
        if ($usernameClash) {
            throw new ApiException('Username sudah dipakai', 409);
        }

        // Sama seperti create(): dua akun pejabat dengan `nama` sama bikin
        // pencocokan identitas ambigu. Role 'admin' dikecualikan.
        if ($role !== 'admin') {
            $namaClash = User::query()
                ->where('nama', $nama)
                ->where('role', '!=', 'admin')
                ->where('id', '!=', $id)
                ->exists();
            if ($namaClash) {
                throw new ApiException("Sudah ada akun pejabat lain dengan nama \"$nama\"", 409);
            }
        }

        $oldUsername = $old->username;
        $oldNama = $old->nama;
        $oldRole = $old->role;

        $old->username = $username;
        $old->nama = $nama;

        // Ganti password ATAU role (kalau berubah) memaksa logout sesi lama,
        // jadi token dihapus sekalian -- SENGAJA hanya dua kondisi ini
        // (bukan termasuk perubahan username saja), persis perilaku PHP
        // lama (users_update.php baris 100-118).
        if ($password !== '' || $role !== $oldRole) {
            $old->role = $role;
            if ($password !== '') {
                $old->password = Hash::make($password);
            }
            $old->token = null;
            $old->token_expires_at = null;
        }

        $old->save();

        $keterangan = "Ubah akun user {$oldNama} ({$oldUsername})";
        if ($username !== $oldUsername || $nama !== $oldNama) {
            $keterangan .= " menjadi $nama ($username)";
        }
        if ($role !== $oldRole) {
            $keterangan .= ", role diubah dari {$oldRole} menjadi $role";
        }
        if ($password !== '') {
            $keterangan .= ', password direset';
        }

        ActivityLogger::log($request, $authUser->username, $authUser->nama, 'update', $keterangan);

        return response()->json([
            'id' => $id,
            'username' => $username,
            'nama' => $nama,
            'role' => $role,
        ]);
    }

    public function delete(Request $request)
    {
        $authUser = $request->user();

        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID user tidak valid', 400);
        }

        $target = User::query()->find($id);
        if (!$target) {
            throw new ApiException('User tidak ditemukan', 404);
        }
        if ($target->username === 'admin') {
            throw new ApiException('Akun Administrator tidak bisa dihapus', 400);
        }
        if ((int) $id === (int) $authUser->id) {
            throw new ApiException('Tidak bisa menghapus akun sendiri', 400);
        }

        $targetUsername = $target->username;
        $targetNama = $target->nama;

        $target->delete();

        ActivityLogger::log($request, $authUser->username, $authUser->nama, 'delete', "Hapus akun user {$targetNama} ({$targetUsername})");

        return response()->json(['ok' => true]);
    }

    public function aksesUpdate(Request $request)
    {
        $authUser = $request->user();

        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        $bolehInputMasuk = (bool) $request->input('boleh_input_masuk', false);
        $bolehInputKeluar = (bool) $request->input('boleh_input_keluar', false);

        if (!$id) {
            throw new ApiException('ID user tidak valid', 400);
        }

        $old = User::query()->find($id);
        if (!$old) {
            throw new ApiException('User tidak ditemukan', 404);
        }

        // Akun admin tidak pernah menginput surat -- switch-nya tidak
        // relevan buat role ini, jangan sampai admin diam-diam diberi hak
        // input lewat panel ini.
        if ($old->role === 'admin') {
            throw new ApiException('Akun role admin tidak bisa diberi hak input surat', 400);
        }

        $old->boleh_input_masuk = $bolehInputMasuk;
        $old->boleh_input_keluar = $bolehInputKeluar;

        // Perubahan hak input berdampak besar ke apa yang boleh dilakukan
        // akun ini -- token dihapus sekalian, sama seperti pola di update()
        // saat role berubah, supaya sesi yang sedang aktif langsung
        // mengambil hak akses terbaru begitu login ulang.
        $old->token = null;
        $old->token_expires_at = null;
        $old->save();

        ActivityLogger::log(
            $request,
            $authUser->username,
            $authUser->nama,
            'update',
            "Ubah hak input {$old->nama} ({$old->username}): "
                .'Surat Masuk '.($bolehInputMasuk ? 'diizinkan' : 'dicabut').', '
                .'Surat Keluar '.($bolehInputKeluar ? 'diizinkan' : 'dicabut')
        );

        return response()->json([
            'id' => $id,
            'boleh_input_masuk' => $bolehInputMasuk,
            'boleh_input_keluar' => $bolehInputKeluar,
        ]);
    }
}
