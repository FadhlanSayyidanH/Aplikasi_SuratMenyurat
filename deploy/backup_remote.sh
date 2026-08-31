#!/usr/bin/env bash
# Backup harian aplikasi Laravel (surat_ditajenad_laravel) -- dump database +
# arsip file surat yang diunggah. Jalan DI VM lewat cron.
#
# Simpan 14 hari terakhir secara lokal di VM. Backup LOKAL DI VM SAJA --
# kalau VM-nya hilang/rusak, backup ini ikut hilang. Untuk perlindungan
# penuh, salin folder BACKUP_DIR ke luar VM secara berkala.
#
# Folder & file backup dibuat 700/600 (bukan default umask 755/644) --
# sebelum 2026-08-27 backup ini world-readable di server (siapa pun akun
# lokal lain bisa baca dump password hash & seluruh lampiran surat) --
# lihat catatan health check 2026-08-27.

set -euo pipefail

APP_DIR="/opt/surat_ditajenad_laravel"
BACKUP_DIR="/var/backups/surat_ditajenad_laravel"
STAMP="$(date +%Y-%m-%d_%H%M)"
DEST="$BACKUP_DIR/$STAMP"
RETAIN_DAYS=14

umask 077
mkdir -p "$DEST"
chmod 700 "$BACKUP_DIR" "$DEST"

echo "[$STAMP] Dump database ..."
mysqldump --single-transaction --routines=0 --triggers=1 --events=0 \
  surat_ditajenad_laravel > "$DEST/db_dump_surat_ditajenad_laravel.sql"

echo "[$STAMP] Arsipkan file surat yang diunggah ..."
tar -czf "$DEST/uploads.tar.gz" -C "$APP_DIR/storage/app" uploads

chmod 600 "$DEST"/*

echo "[$STAMP] Hapus backup lebih lama dari $RETAIN_DAYS hari ..."
find "$BACKUP_DIR" -maxdepth 1 -mindepth 1 -type d -mtime "+$RETAIN_DAYS" -exec rm -rf {} \;

echo "[$STAMP] Selesai: $DEST"
