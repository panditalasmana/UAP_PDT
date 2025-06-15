# NaRiPa Wheels
Proyek ini merupakan sistem yang berfungsi mempermudah proses penyewaan sepeda motor. Sistem ini menggunakan PHP dan MySQL. Tujuannya adalah untuk mengelola proses penyewaan motor secara efisien, aman, dan konsisten, dengan memanfaatkan stored procedure, trigger, transaction, dan stored function. Sistem ini juga dilengkapi dengan mekanisme backup otomatis serta task scheduler untuk menjaga keamanan dan keberlangsungan data apabila terjadi hal yang tidak diinginkan.
![image](https://github.com/user-attachments/assets/116a211b-9de5-493f-a5bc-bfc834d7af77)

# Detail Konsep

### Disclaimer
Dalam proyek Naripa Wheels, penerapan stored procedure, trigger, transaction, dan stored function dirancang khusus untuk menangani proses bisnis penyewaan motor secara efisien, aman, dan terkontrol.

### Stored Procedure 
Stored procedure pada NaRiPa Wheels digunakan untuk memproses penyewaan motor secara otomatis. Menangani validasi tanggal, pengecekan ketersediaan, perhitungan harga sewa, serta pencatatan data ke tabel rentals. Dengan prosedur ini, proses penyewaan lebih terstruktur dan mudah dipanggil dari aplikasi.

![image](https://github.com/user-attachments/assets/dedb46de-dd78-4709-8329-47d6905232cd)



**Procedure yang digunakan :**  
`naripa-wheels\user\rent.php`

- <code> buatPenyewaan(p_user_id,p_motorcycle_id INT,p_rental_date DATE,p_return_date DATE,p_result)</code>: berfungsi untuk membuat transaksi penyewaan motor secara otomatis. 
  ```php
  $query = "CALL buatPenyewaan(?, ?, ?, ?, @result)";
  $stmt = $db->prepare($query);
  $stmt->execute([$_SESSION['user_id'], $motorcycle_id, $rental_date, $return_date]); ```


### Trigger
![image](https://github.com/user-attachments/assets/03f43616-63b9-4899-b39b-d49a1503ff6c)



Trigger <code> log_rental_cancellation </code> berfungsi mencatat pembatalan penyewaan ke dalam tabel, setiap kali status penyewaan diubah menjadi cancelled. Trigger hanya aktif jika status penyewaan berubah menjadi cancelled dari status selain cancelled.
 ```php
 IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
```
Trigger <code> log_rental_deletion </code> berfungsi encatat histori penghapusan penyewaan ke tabel rental_history saat data dihapus dari tabel rentals. Trigger  aktif secara  saat ada perintah <code> DELETE </code> terhadap tabel rentals.
  

### Transaction
Dalam sistem penyewaan motor ini, fitur transaction digunakan untuk menjaga konsistensi dan integritas data saat proses penyewaan dilakukan. Hal ini diimplementasikan melalui stored procedure bernama buatPenyewaan, yang menjalankan beberapa operasi penting secara berurutan, seperti validasi tanggal sewa dan kembali, pengecekan ketersediaan motor, perhitungan total biaya, pencatatan penyewaan pada tabel rentals, serta pengurangan jumlah motor yang tersedia di tabel motorcycles. Untuk memastikan seluruh proses berjalan atomik, prosedur ini menggunakan blok transaksi yang dimulai dengan START TRANSACTION dan diakhiri dengan COMMIT apabila semua proses berhasil. Jika terjadi kesalahan (misalnya saat penyisipan data atau pengurangan stok), maka perubahan dibatalkan secara keseluruhan menggunakan ROLLBACK melalui handler SQLEXCEPTION. Dengan demikian, sistem dapat mencegah terjadinya ketidaksesuaian data seperti stok motor berkurang tanpa data penyewaan yang tercatat. Pendekatan ini juga menyederhanakan pemanggilan dari sisi aplikasi karena seluruh proses transaksi terkonsentrasi di dalam prosedur yang dijalankan langsung oleh database.


### Stored Function
![image](https://github.com/user-attachments/assets/e5eba0b5-5c14-4c2b-8369-1eee4893de28)



Stored function berfungsi untuk mengecek ketersediaan motor sebelum proses penyewaan dilakukan. Fungsi ini bertugas memastikan bahwa motor yang dipilih oleh pengguna masih tersedia dan tidak sedang dalam proses penyewaan oleh orang lain pada rentang tanggal yang sama.
```php
$query = "SELECT cekKetersediaan(?, ?, ?) as available";
$stmt = $db->prepare($query);
$stmt->execute([$motorcycle_id, $rental_date, $return_date]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Backup Otomatis
Pada sistem ini, telah diimplementasikan fitur Task Scheduler menggunakan <code> MySQL Event Scheduler </code> untuk menjalankan proses backup otomatis data penyewaan. Task ini dibuat dalam bentuk event bernama daily_backup yang dijalankan setiap hari pada pukul 23:59 waktu server.

### Relevansi Proyek dengan Pemrosesan Data Terdistribusi
Sistem ini dirancang dengan memperhatikan prinsip-prinsip dasar dalam pemrosesan data terdistribusi.
- Konsistensi: Semua proses penyewaan dilakukan melalui stored procedure buatPenyewaan, yang mencakup validasi tanggal, pengecekan ketersediaan motor, hingga pengurangan stok secara atomik.
- Reliabilitas: Dengan penggunaan transaction dan error handler, sistem mampu menangani gangguan atau kegagalan saat proses penyewaan. Jika terjadi kesalahan pada salah satu tahap, maka seluruh transaksi akan dibatalkan dengan ROLLBACK, sehingga data tidak rusak atau setengah tersimpan.
- Integritas: Logika bisnis utama disimpan di tingkat database melalui stored procedure, function, dan trigger, sehingga meskipun sistem diakses dari berbagai sumber—seperti antarmuka web, layanan API, atau aplikasi mobile—semua aturan dan mekanisme tetap konsisten, tanpa perlu duplikasi logika di sisi klien.
Dengan desain seperti ini, sistem penyewaan motor Naripa Wheels mendukung prinsip-prinsip pemrosesan terdistribusi yang kuat.






