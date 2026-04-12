# Redis Cheatsheet

## Konfigurasi `.env` Laravel

```env
REDIS_CLIENT=predis          # predis (composer) atau phpredis (ekstensi PHP)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0                   # DB untuk koneksi default
REDIS_CACHE_DB=0             # DB untuk cache (default: 1)
REDIS_PREFIX=                # prefix key, kosongkan agar tidak ada prefix otomatis
```

---

## Koneksi & Info

```bash
redis-cli                    # masuk interactive mode
redis-cli -n 1               # connect ke DB index 1
redis-cli ping               # cek koneksi → PONG = ok
redis-cli info server        # info lengkap server Redis
redis-cli info memory        # cek penggunaan memori
```

---

## Keys

```bash
keys *                       # lihat semua keys
keys "authors*"              # filter keys by pattern
type "namakey"               # tipe data: string, list, hash, set, zset
exists "namakey"             # 1 = ada, 0 = tidak ada
del "namakey"                # hapus 1 key
ttl "namakey"                # sisa waktu expire (detik)
                             # -1 = tidak expire, -2 = key tidak ada
expire "namakey" 300         # set expire 300 detik
persist "namakey"            # hapus expire → jadi permanent
```

---

## Baca Value

```bash
get "namakey"                # baca value (tipe string / serialized)
hgetall "namakey"            # baca semua field (tipe hash)
lrange "namakey" 0 -1        # baca semua item (tipe list)
smembers "namakey"           # baca semua member (tipe set)
```

---

## Monitoring

```bash
monitor                      # lihat semua command realtime (untuk debug)
redis-cli --stat             # statistik live: memory, keys, connections
dbsize                       # jumlah total keys di DB aktif
```

---

## Database

```bash
select 0                     # pindah ke DB0 (default)
select 1                     # pindah ke DB1
move "namakey" 1             # pindahkan key ke DB1
```

---

## Flush / Clear

```bash
flushdb                      # hapus semua keys di DB aktif saja
flushall                     # hapus semua keys di SEMUA DB (hati-hati!)
```

---

## Debug Laravel Cache via Redis CLI

```bash
# 1. Lihat semua cache key yang tersimpan
redis-cli keys "*"

# 2. Cek value cache tertentu
redis-cli get "book_manager_cacheauthors.all.d41d8cd98f00b204e9800998ecf8427e"

# 3. Cek berapa lama lagi cache expire
redis-cli ttl "book_manager_cacheauthors.all.d41d8cd98f00b204e9800998ecf8427e"

# 4. Monitor realtime saat hit API (buka terminal kedua)
redis-cli monitor

# 5. Clear cache tanpa php artisan
redis-cli flushdb
```

---

## Debug via Laravel Tinker

```bash
php artisan tinker
```

```php
// Cek driver aktif
Cache::getDefaultDriver();

// Cek apakah key ada
Cache::has('authors.all.' . md5(''));

// Ambil value
Cache::get('authors.all.' . md5(''));

// Simpan manual
Cache::put('test', 'hello', 60);

// Hapus key tertentu
Cache::forget('authors.all.' . md5(''));

// Hapus semua cache
Cache::flush();

// Ping Redis
Illuminate\Support\Facades\Redis::ping();
```

---

## Tips

| Masalah                                    | Solusi                                                       |
| ------------------------------------------ | ------------------------------------------------------------ |
| `incomplete object` saat unserialize       | Simpan sebagai array: `->get()->toArray()`                   |
| Keys tidak muncul di `redis-cli keys *`    | Cek DB index dengan `redis-cli -n 1 keys *`                  |
| Ada prefix aneh `laravel-database-`        | Set `REDIS_PREFIX=` di `.env`                                |
| Cache tidak ter-update setelah ubah `.env` | Jalankan `php artisan config:clear`                          |
| Mau pakai predis                           | `composer require predis/predis` → set `REDIS_CLIENT=predis` |
