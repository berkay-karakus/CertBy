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

        if (empty($subject) || empty($body)) {
            throw new Exception("Konu ve İçerik alanları zorunludur.");
        }

        // GÜVENLİK: Veri bütünlüğü için Transaction başlatıldı
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO email_template (subject, description, body) VALUES (?, ?, ?)");
        $stmt->execute([$subject, $description, $body]);

        $email_template_id = $db->lastInsertId();
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $uploaded_file_paths = [];

        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $file_names = $_FILES['attachments']['name'];
            $tmp_names = $_FILES['attachments']['tmp_name'];
            $errors = $_FILES['attachments']['error'];
            $sizes = $_FILES['attachments']['size']; // Dosya boyutları alındı

            $attachment_stmt = $db->prepare("INSERT INTO email_attachment (FK_email_template_id, file_name, file_path) VALUES (?, ?, ?)");
            
            // GÜVENLİK: İzin verilen dosya uzantıları (RCE Koruması)
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'txt', 'zip', 'rar'];
            $max_file_size = 10 * 1024 * 1024; // Maksimum 10 MB

            for ($i = 0; $i < count($file_names); $i++) {
                if ($errors[$i] === UPLOAD_ERR_OK) {
                    $file_name = basename($file_names[$i]);
                    $file_size = $sizes[$i];

                    // GÜVENLİK: Uzantı Kontrolü
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($file_ext, $allowed_extensions)) {
                        throw new Exception("Geçersiz dosya türü: " . htmlspecialchars($file_name) . ". Sadece güvenli ofis ve görsel dosyalarına izin verilir.");
                    }

                    // GÜVENLİK: Boyut Kontrolü
                    if ($file_size > $max_file_size) {
                        throw new Exception("Dosya boyutu çok büyük (Maks 10MB): " . htmlspecialchars($file_name));
                    }

                    // GÜVENLİK: Sunucuya kaydedilecek dosya adını özel karakterlerden arındırma
                    $safe_file_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file_name);
                    $unique_file_name = uniqid() . '_' . $safe_file_name;
                    $target_path = $upload_dir . $unique_file_name;

                    if (move_uploaded_file($tmp_names[$i], $target_path)) {
                        $uploaded_file_paths[] = $target_path;
                        $attachment_stmt->execute([$email_template_id, $file_name, $target_path]);
                    } else {
                        throw new Exception("Dosya yüklenirken bir sorun oluştu: " . htmlspecialchars($file_name));
                    }
                } elseif ($errors[$i] !== UPLOAD_ERR_NO_FILE) {
                    throw new Exception("Dosya yükleme hatası kodu: " . $errors[$i]);
                }
            }
        }

        $db->commit();
        $success_message = "E-posta şablonu başarıyla kaydedildi!";

    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        // GÜVENLİK: Veritabanı hatası gizlendi (Information Exposure koruması)
        error_log("Email Template DB Error: " . $e->getMessage());
        $error_message = "Kayıt işlemi sırasında sistemsel bir hata oluştu.";
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Yeni E-Posta Şablonu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-color: #f4f6f9;
            --card-bg: #ffffff;
            --text-color: #333333;
            --border-color: #dee2e6;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-link {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 1.1rem;
            margin-right: 15px;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0,123,255,.25);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 150px;
        }

        .file-upload-wrapper {
            border: 2px dashed var(--border-color);
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background-color: #fafafa;
            cursor: pointer;
            transition: border-color 0.3s;
            position: relative;
        }

        .file-upload-wrapper:hover {
            border-color: var(--primary-color);
        }

        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .file-upload-text {
            color: var(--secondary-color);
            font-weight: 500;
        }

        .file-list {
            list-style: none;
            margin-top: 15px;
        }

        .file-list li {
            background-color: #f8f9fa;
            border: 1px solid var(--border-color);
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        .remove-file {
            color: var(--error-color);
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2rem;
            line-height: 1;
            padding: 0 5px;
        }

        .remove-file:hover {
            color: #a71d2a;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: #0056b3;
        }

        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success-message {
            background-color: #d4edda;
            color: var(--success-color);
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: var(--error-color);
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="page-header">
            <a href="dashboard.php" class="back-link" title="Geri Dön"><i class="fas fa-arrow-left"></i></a>
            <h1 class="page-title">Yeni E-Posta Şablonu Oluştur</h1>
        </div>

        <?php if ($error_message): ?>
            <div class="message error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="message success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card">
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label for="subject" class="form-label">Konu Başlığı *</label>
                    <input type="text" id="subject" name="subject" class="form-control" required placeholder="E-posta konusunu girin">
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Açıklama (İsteğe Bağlı)</label>
                    <input type="text" id="description" name="description" class="form-control" placeholder="Bu şablon ne için kullanılacak?">
                </div>

                <div class="form-group">
                    <label for="body" class="form-label">E-posta İçeriği *</label>
                    <textarea id="body" name="body" class="form-control" required placeholder="E-posta metnini buraya yazın..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Dosya Ekleri (İsteğe Bağlı)</label>
                    <div class="file-upload-wrapper">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <div class="file-upload-text">Dosyaları seçmek için tıklayın veya sürükleyip bırakın</div>
                        <input type="file" id="attachments" name="attachments[]" class="file-upload-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.txt,.zip,.rar">
                    </div>
                    <ul id="file-list" class="file-list"></ul>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Şablonu Kaydet</button>

            </form>
        </div>
    </div>

    <script>
        // GÜVENLİK: JS DOM XSS Koruması için fonksiyon eklendi
        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
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
                    // GÜVENLİK: Dosya adı HTML içine basılmadan önce sanitize edildi
                    listItem.innerHTML = `
                    <span>${escapeHTML(file.name)}</span>
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