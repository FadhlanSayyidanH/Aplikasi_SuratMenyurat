{{-- Manajemen akun user (admin-only). Cermin user_management_screen.dart (proyek Flutter lama). --}}
<div>
    @if ($error)
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-4 py-2.5 text-sm text-app-error">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ $error }}
        </div>
    @endif

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
                type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau username..."
                class="w-full rounded-lg border border-gray-300 bg-app-surface py-2.5 pl-9 pr-3 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
            >
        </div>
        <button
            type="button" wire:click="openAddForm"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary-green"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg>
            Tambah User
        </button>
    </div>

    <div class="overflow-hidden rounded-xl bg-app-surface shadow-sm">
        @if ($this->users->isEmpty())
            <p class="p-8 text-center text-sm text-text-muted">Tidak ada user yang cocok</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($this->users as $user)
                    @php
                        $isAdminUsername = $user->username === 'admin';
                        $isSelf = $user->id === auth()->id();
                    @endphp
                    <li wire:key="user-{{ $user->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-green/10 text-primary-green">
                            @if ($user->role === 'admin')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5z"/></svg>
                            @elseif ($user->role === 'pimpinan')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 12 22l-1.5-4-1.5 4-3.5-8.5"/></svg>
                            @elseif ($user->role === 'turmin')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="font-semibold text-text-dark">{{ $user->nama }}</p>
                                @if ($user->role === 'admin')
                                    <span class="rounded-full bg-primary-green/10 px-2 py-0.5 text-[11px] font-semibold text-primary-green">Admin</span>
                                @elseif ($user->role === 'pimpinan')
                                    <span class="rounded-full bg-orange-100 px-2 py-0.5 text-[11px] font-semibold text-orange-700">Pimpinan</span>
                                @elseif ($user->role === 'turmin')
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-700">Turmin</span>
                                @endif
                            </div>
                            <p class="text-xs text-text-muted">{{ $user->username }}</p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-1.5 text-xs">
                            <button
                                type="button" wire:click="resetPassword({{ $user->id }})"
                                wire:confirm="Password baru akan dibuat untuk akun &quot;{{ $user->nama }}&quot; ({{ $user->username }}). Sesi login akun ini akan berakhir dan harus login ulang dengan password baru. Lanjutkan?"
                                class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-text-dark hover:bg-app-background"
                            >Reset Pass.</button>
                            <button type="button" wire:click="openEditForm({{ $user->id }})" class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-text-dark hover:bg-app-background">Ubah</button>
                            <button
                                type="button"
                                @if ($isAdminUsername || $isSelf)
                                    disabled title="{{ $isAdminUsername ? 'Akun Administrator tidak bisa dihapus' : 'Tidak bisa menghapus akun sendiri' }}"
                                    class="cursor-not-allowed rounded-lg border border-gray-200 px-2.5 py-1.5 font-medium text-gray-400"
                                @else
                                    wire:click="deleteUser({{ $user->id }})"
                                    wire:confirm="Akun &quot;{{ $user->nama }}&quot; ({{ $user->username }}) akan dihapus permanen. Lanjutkan?"
                                    title="Hapus user ini"
                                    class="rounded-lg border border-app-error/40 px-2.5 py-1.5 font-medium text-app-error hover:bg-app-error/5"
                                @endif
                            >Hapus</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Dialog Tambah/Ubah User --}}
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-8" wire:key="user-form-backdrop">
            <div class="w-full max-w-md rounded-2xl bg-app-surface p-6 shadow-xl" @click.outside="$wire.closeForm()">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-text-dark">{{ $formTitle }}</h2>
                    <button type="button" wire:click="closeForm" class="text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <form wire:submit="save" class="max-h-[75vh] space-y-3 overflow-y-auto pr-1">
                    @if ($formError)
                        <div class="rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">
                            {{ $formError }}
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Nama</label>
                        <select wire:model.live="namaPilihan" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            @foreach ($this->namaTetap as $n)
                                <option value="{{ $n }}">{{ $n }}</option>
                            @endforeach
                            <option value="{{ $this->namaLainnyaSentinel }}">Lainnya (isi manual)</option>
                        </select>
                    </div>
                    @if ($this->namaLainnyaAktif)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-text-dark">Nama (isi manual)</label>
                            <input type="text" wire:model="namaLainnya" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Username</label>
                        <input type="text" wire:model="username" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Role</label>
                        <select wire:model="role" @disabled($kunciRoleAdmin) class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green disabled:opacity-60">
                            <option value="user">User (Pejabat)</option>
                            <option value="pimpinan">Pimpinan</option>
                            <option value="turmin">Turmin</option>
                            <option value="admin">Admin</option>
                        </select>
                        @if ($kunciRoleAdmin)
                            <p class="mt-1 text-xs text-text-muted">Role akun Administrator tidak bisa diubah.</p>
                        @endif
                    </div>

                    <div x-data="{ show: true }">
                        <label class="mb-1 block text-xs font-medium text-text-dark">
                            {{ $passwordRequired ? 'Password' : 'Password Baru (kosongkan jika tidak diubah)' }}
                        </label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="password" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 pr-10 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            <button type="button" @click="show = !show" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-dark">
                                <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" wire:click="closeForm" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background">Batal</button>
                        <button type="submit" class="flex-1 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary-green">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Dialog Kredensial (setelah create/reset password) --}}
    @if ($showCredentialsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:key="credentials-backdrop">
            <div class="w-full max-w-sm rounded-2xl bg-app-surface p-6 text-center shadow-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-secondary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h2 class="mt-3 text-base font-bold text-text-dark">{{ $credTitle }}</h2>
                <p class="mt-2 text-xs text-text-muted">
                    Simpan atau catat kredensial berikut sekarang -- password tidak akan bisa dilihat lagi setelah ini.
                </p>

                <div class="mt-4 space-y-2 rounded-lg bg-app-background p-3 text-left text-sm" x-data>
                    <div class="flex items-center gap-2">
                        <span class="w-20 shrink-0 text-xs text-text-muted">Nama</span>
                        <span class="min-w-0 flex-1 truncate font-semibold text-text-dark">{{ $credNama }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-20 shrink-0 text-xs text-text-muted">Username</span>
                        <span class="min-w-0 flex-1 truncate font-semibold text-text-dark">{{ $credUsername }}</span>
                        <button type="button" title="Salin" @click="navigator.clipboard.writeText(@js($credUsername))" class="shrink-0 text-text-muted hover:text-text-dark">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-20 shrink-0 text-xs text-text-muted">Password</span>
                        <span class="min-w-0 flex-1 truncate font-semibold text-text-dark">{{ $credPassword }}</span>
                        <button type="button" title="Salin" @click="navigator.clipboard.writeText(@js($credPassword))" class="shrink-0 text-text-muted hover:text-text-dark">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </div>
                </div>

                <button type="button" wire:click="closeCredentials" class="mt-5 w-full rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary-green">Selesai</button>
            </div>
        </div>
    @endif
</div>
