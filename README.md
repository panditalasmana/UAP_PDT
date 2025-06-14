# NaRiPa Wheels
Proyek ini merupakan sistem yang berfungsi mempermudah proses penyewaan sepeda motor. Sistem ini menggunakan PHP dan MySQL. Tujuannya adalah untuk mengelola proses penyewaan motor secara efisien, aman, dan konsisten, dengan memanfaatkan stored procedure, trigger, transaction, dan stored function. Sistem ini juga dilengkapi dengan mekanisme backup otomatis serta task scheduler untuk menjaga keamanan dan keberlangsungan data apabila terjadi hal yang tidak diinginkan.
![image](https://github.com/user-attachments/assets/116a211b-9de5-493f-a5bc-bfc834d7af77)

# Detail Konsep

### Disclaimer
Dalam proyek Naripa Wheels, penerapan stored procedure, trigger, transaction, dan stored function dirancang khusus untuk menangani proses bisnis penyewaan motor secara efisien, aman, dan terkontrol.

### Stored Procedure 
Stored procedure pada NaRiPa Wheels digunakan untuk memproses penyewaan motor secara otomatis. Menangani validasi tanggal, pengecekan ketersediaan, perhitungan harga sewa, serta pencatatan data ke tabel rentals. Dengan prosedur ini, proses penyewaan lebih terstruktur dan mudah dipanggil dari aplikasi.

![image](https://github.com/user-attachments/assets/30077580-71b4-4726-b572-bcbb8466ee58)

**Procedure yang digunakan :**  
`naripa-wheels\user\rent.php`

- <code> buatPenyewaan(p_user_id,p_motorcycle_id INT,p_rental_date DATE,p_return_date DATE,p_result)</code>: berfungsi untuk membuat transaksi penyewaan motor secara otomatis. 
  ```php
  $query = "CALL buatPenyewaan(?, ?, ?, ?, @result)";
  $stmt = $db->prepare($query);
  $stmt->execute([$_SESSION['user_id'], $motorcycle_id, $rental_date, $return_date]); ```


### Trigger
![image](https://github.com/user-attachments/assets/bdbbb4ca-ef85-4638-8a48-ab3091de18a5)

Trigger <code> log_rental_cancellation </code> berfungsi mencatat pembatalan penyewaan ke dalam tabel, setiap kali status penyewaan diubah menjadi cancelled. Trigger hanya aktif jika status penyewaan berubah menjadi cancelled dari status selain cancelled.
 ```php
 IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN ```

Trigger <code> log_rental_deletion </code> berfungsi mencatat histori penghapusan penyewaan ke tabel rental_history saat data dihapus dari tabel rentals. Trigger hanya aktif saat ada perintah <code>DELETE </code> terhadap tabel rentals.
  
