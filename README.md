# Sistem Absensi SMP

Aplikasi pengelolaan kehadiran siswa berbasis Laravel, PostgreSQL, dan pembaca kartu RFID melalui ESP32.

## Fitur

- Pengelolaan siswa, kelas, tahun ajaran, dan UID kartu RFID.
- Absensi otomatis melalui RFID serta input dan koreksi manual.
- Rekap kehadiran harian, bulanan, dan tahunan.
- Kalender sekolah dan penandaan ALPHA otomatis.
- Hak akses administrator dan wali kelas.

## Teknologi

- Laravel 13 dan PHP 8.3 atau lebih baru.
- PostgreSQL.
- Firmware ESP32 di `esp32/firmware`.

## Menyiapkan firmware

Salin `esp32/firmware/secrets.example.h` menjadi `secrets.h`, lalu isi Wi-Fi sekolah, URL HTTPS aplikasi, dan API key perangkat. Buat API key perangkat melalui dashboard. File `secrets.h` diabaikan oleh Git agar konfigurasi perangkat tetap lokal.

## Keamanan

Jangan unggah `.env`, `secrets.h`, kredensial database, atau data pribadi siswa ke repository. Gunakan data contoh yang disamarkan untuk pengembangan.
