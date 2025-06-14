# NaRiPa Wheels
Proyek ini merupakan sistem yang berfungsi mempermudah proses penyewaan sepeda motor. Sistem ini menggunakan PHP dan MySQL. Tujuannya adalah untuk mengelola proses penyewaan motor secara efisien, aman, dan konsisten, dengan memanfaatkan stored procedure, trigger, transaction, dan stored function. Sistem ini juga dilengkapi dengan mekanisme backup otomatis serta task scheduler untuk menjaga keamanan dan keberlangsungan data apabila terjadi hal yang tidak diinginkan.
![image](https://github.com/user-attachments/assets/116a211b-9de5-493f-a5bc-bfc834d7af77)

# Detail Konsep

### Disclaimer
Dalam proyek Naripa Wheels, penerapan stored procedure, trigger, transaction, dan stored function dirancang khusus untuk menangani proses bisnis penyewaan motor secara efisien, aman, dan terkontrol.

### Stored Procedure 
Stored procedure pada NaRiPa Wheels digunakan untuk memproses penyewaan motor secara otomatis. Menangani validasi tanggal, pengecekan ketersediaan, perhitungan harga sewa, serta pencatatan data ke tabel rentals. Dengan prosedur ini, proses penyewaan lebih terstruktur dan mudah dipanggil dari aplikasi.

![image](https://github.com/user-attachments/assets/30077580-71b4-4726-b572-bcbb8466ee58)

Procedure yang digunakan : 
'''<code> buatPenyewaan(p_user_id,p_motorcycle_id INT,p_rental_date DATE,p_return_date DATE,p_result)</code>: berfungsi untuk membuat transaksi penyewaan motor secara otomatis. 
<code> $query = "CALL buatPenyewaan(?, ?, ?, ?, @result)";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $motorcycle_id, $rental_date, $return_date]); </code>
