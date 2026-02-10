<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $body = trim($_POST['body'] ?? '');

        $stmt = $db->prepare("INSERT INTO email_template (subject, description, body) VALUES (?, ?, ?)");
        $stmt->execute([$subject, $description, $body]);

        $email_template_id = $db->lastInsertId();
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $uploaded_file_paths = [];

        if (isset($_FILES['attachments'])) {
            $file_names = $_FILES['attachments']['name'];
            $tmp_names = $_FILES['attachments']['tmp_name'];
            $errors = $_FILES['attachments']['error'];

            $attachment_stmt = $db->prepare("INSERT INTO email_attachment (FK_email_template_id, file_name, file_path) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($file_names); $i++) {
                if ($errors[$i] === UPLOAD_ERR_OK) {
                    $file_name = basename($file_names[$i]);

                    $unique_file_name = uniqid() . '_' . $file_name;
                    $upload_path = $upload_dir . $unique_file_name;

                    if (move_uploaded_file($tmp_names[$i], $upload_path)) {
                        $attachment_stmt->execute([$email_template_id, $file_name, $upload_path]);
                    } else {

                        $error_message .= 'Dosya yükleme sırasında bir hata oluştu: ' . $file_name . "<br>";
                    }
                }
            }
        }

        if (empty($error_message)) {
            $success_message = 'E-posta şablonu ve ekleri başarıyla kaydedildi.';
            header("Location: dashboard.php?success=1");
            exit();
        }
    } catch (PDOException $e) {
        $error_message = "Veritabanı bağlantı hatası: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-posta Şablonu Oluştur</title>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background-color: #fff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 2.5em;
            color: #1a237e;
            margin-bottom: 5px;
        }

        .header p {
            color: #555;
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .required {
            color: #d32f2f;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
            resize: vertical;
            min-height: 120px;
            height: auto;
        }

        textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .file-upload-section {
            border: 2px dashed #b0c4de;
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: center;
        }

        .custom-file-upload {
            display: inline-block;
            background-color: #1a73e8;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
        }

        .custom-file-upload:hover {
            background-color: #0d47a1;
        }

        input[type="file"] {
            display: none;
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin-top: 15px;
            text-align: left;
        }

        .file-list li {
            background-color: #e8f0fe;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-list li .remove-file {
            color: #d32f2f;
            cursor: pointer;
            font-size: 1.2em;
            transition: color 0.3s;
        }

        .file-list li .remove-file:hover {
            color: #b71c1c;
        }

        .form-actions {
            text-align: right;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            font-weight: 600;
        }

        .btn-primary {
            background-color: #2ecc71;
            color: white;
        }

        .btn-primary:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #bdc3c7;
            color: #333;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background-color: #95a5a6;
            transform: translateY(-2px);
        }

        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>

    <div class="container">
        <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
        <div class="header">
            <h1>E-posta Şablonu Oluştur</h1>
        </div>

        <form id="email-template-form" action="" method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label for="subject">Konu<span class="required">*</span></label>
                <input type="text" id="subject" name="subject" placeholder="E-postanın konusunu girin" required>
            </div>

            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" rows="3" placeholder="Şablonun amacı hakkında kısa bir not yazın"></textarea>
            </div>

            <div class="form-group">
                <label for="body">İçerik<span class="required">*</span></label>
                <textarea id="body" name="body" rows="10" required></textarea>
            </div>

            <div class="form-group file-upload-section">
                <label>Dosya Ekle</label>
                <div class="file-input-wrapper">
                    <input type="file" id="attachments" name="attachments[]" multiple>
                    <label for="attachments" class="custom-file-upload">
                        <i class="fas fa-plus-circle"></i> Dosya Seç
                    </label>
                </div>
                <ul id="file-list" class="file-list">
                </ul>
            </div>

            <div class="form-actions">
                <button type="reset" class="btn btn-secondary" onclick="goToDashboard()">İptal</button>
                <button type="submit" class="btn btn-primary">Şablonu Kaydet</button>
            </div>
        </form>
    </div>


    <script>
        function goToDashboard() {
            window.location.href = "dashboard.php";
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('attachments');
            const fileList = document.getElementById('file-list');

            fileInput.addEventListener('change', function() {
                fileList.innerHTML = '';
                const files = this.files;
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const listItem = document.createElement('li');
                    listItem.innerHTML = `
                    <span>${file.name}</span>
                    <span class="remove-file" data-file-index="${i}">&times;</span>
                    `;
                    fileList.appendChild(listItem);
                }
            });

            fileList.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-file')) {
                    const indexToRemove = parseInt(e.target.dataset.fileIndex);
                    const tempFileList = new DataTransfer();
                    for (let i = 0; i < fileInput.files.length; i++) {
                        if (i !== indexToRemove) {
                            tempFileList.items.add(fileInput.files[i]);
                        }
                    }
                    fileInput.files = tempFileList.files;
                    e.target.closest('li').remove();
                }

            });
        });
    </script>
</body>

</html>