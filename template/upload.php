<?php
session_start();

// Koneksi ke database
$koneksi = mysqli_connect("localhost", "root", "", "db_itsave");

// Periksa koneksi
if (mysqli_connect_errno()) {
    http_response_code(500);
    echo "Koneksi database gagal: " . mysqli_connect_error();
    exit();
}

// Periksa apakah user telah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Anda harus login terlebih dahulu.";
    exit();
}

// Ambil user_id dari sesi
$user_id = $_SESSION['user_id'];

// Periksa apakah form telah disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tweet_text = mysqli_real_escape_string($koneksi, $_POST['tweet-text']);
    $image_names = [];

    // Proses upload gambar jika ada
    if (isset($_FILES['upload-images']) && count($_FILES['upload-images']['name']) > 0 && $_FILES['upload-images']['name'][0] != "") {
        $total_files = count($_FILES['upload-images']['name']);

        if ($total_files > 4) {
            http_response_code(400);
            echo "Anda hanya dapat mengunggah maksimal 4 gambar.";
            exit();
        }

        $target_dir = "../assets/konten/"; // Direktori untuk menyimpan gambar yang diunggah

        for ($i = 0; $i < $total_files; $i++) {
            $image_name = basename($_FILES["upload-images"]["name"][$i]);
            $target_file = $target_dir . $image_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Periksa apakah file adalah gambar
            $check = getimagesize($_FILES["upload-images"]["tmp_name"][$i]);
            if ($check === false) {
                http_response_code(400);
                echo "File bukan gambar.";
                exit();
            }

            // Periksa apakah file sudah ada
            if (file_exists($target_file)) {
                http_response_code(400);
                echo "File sudah ada.";
                exit();
            }

            // Batasi ukuran file
            if ($_FILES["upload-images"]["size"][$i] > 5000000) {
                http_response_code(400);
                echo "Ukuran file terlalu besar.";
                exit();
            }

            // Hanya izinkan format file tertentu
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                http_response_code(400);
                echo "Hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan.";
                exit();
            }

            // Jika semua pemeriksaan berhasil, pindahkan file ke direktori tujuan
            if (!move_uploaded_file($_FILES["upload-images"]["tmp_name"][$i], $target_file)) {
                http_response_code(500);
                echo "Ada kesalahan saat mengunggah file.";
                exit();
            }

            $image_names[] = $image_name;
        }
    }

    // Simpan data ke database
    $images = implode(',', $image_names); // Gabungkan semua nama file gambar menjadi satu string
    $created_at = date('Y-m-d H:i:s');
    $query = "INSERT INTO posts (user_id, content, image, created_at) VALUES ('$user_id', '$tweet_text', '$images', '$created_at')";

    if (mysqli_query($koneksi, $query)) {
        $post_id = mysqli_insert_id($koneksi); // Dapatkan ID dari postingan yang baru saja dimasukkan

        // Deteksi dan simpan tag username
        if (preg_match_all('/@(\w+)/', $tweet_text, $matches)) {
            $tagged_usernames = $matches[1];
            foreach ($tagged_usernames as $username) {
                // Dapatkan ID pengguna yang ditandai
                $user_query = "SELECT id FROM users WHERE username = '$username'";
                $user_result = mysqli_query($koneksi, $user_query);
                if (mysqli_num_rows($user_result) > 0) {
                    $user_data = mysqli_fetch_assoc($user_result);
                    $tagged_user_id = $user_data['id'];

                    // Simpan notifikasi tag
                    $notif_query = "INSERT INTO notifications (user_id, type, post_id, created_at) VALUES ('$tagged_user_id', 'tag', '$post_id', '$created_at')";
                    mysqli_query($koneksi, $notif_query);
                }
            }
        }

        http_response_code(200);
        echo "Konten berhasil diunggah!";
    } else {
        http_response_code(500);
        echo "Error: " . mysqli_error($koneksi);
    }

    // Tutup koneksi database
    mysqli_close($koneksi);
}
?>
