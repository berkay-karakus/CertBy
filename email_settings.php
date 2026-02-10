<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

require_once 'db.php';

// Email settings güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $from_name = trim($_POST['from_name'] ?? '');
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password = trim($_POST['smtp_password'] ?? '');
        $smtp_secure = trim($_POST['smtp_secure'] ?? 'TLS');
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $from_email = trim($_POST['from_email'] ?? '');
        
        // Zorunlu alanları kontrol et
        if (empty($from_name) || empty($smtp_host) || empty($smtp_username) || 
            empty($smtp_password) || empty($smtp_port) || empty($from_email)) {
            $error_message = 'Lütfen tüm zorunlu alanları doldurun.';
        } else {
            // Mevcut ayarları kontrol et
            $checkSql = "SELECT id FROM email_settings LIMIT 1";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute();
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                $sql = "UPDATE email_settings SET from_name = ?, smtp_host = ?, smtp_username = ?, 
                        smtp_password = ?, smtp_secure = ?, smtp_port = ?, from_email = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$from_name, $smtp_host, $smtp_username, $smtp_password, 
                               $smtp_secure, $smtp_port, $from_email, $existing['id']]);
            } else {
                // Yeni ekle
                $sql = "INSERT INTO email_settings (from_name, smtp_host, smtp_username, 
                        smtp_password, smtp_secure, smtp_port, from_email) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$from_name, $smtp_host, $smtp_username, $smtp_password, 
                               $smtp_secure, $smtp_port, $from_email]);
            }
            
            $success_message = 'E-posta ayarları başarıyla kaydedildi.';
        }
    } catch (PDOException $e) {
        $error_message = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

try {
    $sql = "SELECT * FROM email_settings LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $emailSettings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $emailSettings = null;
    $error_message = 'Veritabanı bağlantı hatası: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - E-posta Ayarları</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            color: #343a40;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 300;
            color: #007bff;
        }

        .back-button {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            background-color: #6c757d;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .back-button:hover {
            background-color: #5a6268;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-field {
            margin-bottom: 20px;
        }

        .form-field.full-width {
            grid-column: 1 / -1;
        }

        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #495057;
        }

        .form-field label.required::after {
            content: " *";
            color: #dc3545;
        }

        .form-field input,
        .form-field select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s;
        }

        .form-field input:focus,
        .form-field select:focus {
            border-color: #007bff;
            outline: none;
        }

        .form-actions {
            text-align: right;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-primary {
            background-color: #28a745;
        }

        .btn-primary:hover {
            background-color: #218838;
        }

        .btn-secondary {
            background-color: #6c757d;
            margin-right: 10px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .help-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="header-title">E-posta Ayarları</h1>
            <a href="dashboard.php" class="back-button">Ana Sayfa</a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-field">
                    <label for="from_name" class="required">Gönderen Adı</label>
                    <input type="text" id="from_name" name="from_name" 
                           value="<?php echo htmlspecialchars($emailSettings['from_name'] ?? ''); ?>" required>
                    <div class="help-text">E-postalarda görünecek gönderen adı</div>
                </div>

                <div class="form-field">
                    <label for="from_email" class="required">Gönderen E-posta</label>
                    <input type="email" id="from_email" name="from_email" 
                           value="<?php echo htmlspecialchars($emailSettings['from_email'] ?? ''); ?>" required>
                    <div class="help-text">E-postaların gönderileceği adres</div>
                </div>

                <div class="form-field">
                    <label for="smtp_host" class="required">SMTP Sunucusu</label>
                    <input type="text" id="smtp_host" name="smtp_host" 
                           value="<?php echo htmlspecialchars($emailSettings['smtp_host'] ?? 'smtp.gmail.com'); ?>" required>
                    <div class="help-text">Örnek: smtp.gmail.com, smtp.outlook.com</div>
                </div>

                <div class="form-field">
                    <label for="smtp_port" class="required">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" 
                           value="<?php echo htmlspecialchars($emailSettings['smtp_port'] ?? '587'); ?>" required>
                    <div class="help-text">Genellikle 587 (TLS) veya 465 (SSL)</div>
                </div>

                <div class="form-field">
                    <label for="smtp_username" class="required">SMTP Kullanıcı Adı</label>
                    <input type="text" id="smtp_username" name="smtp_username" 
                           value="<?php echo htmlspecialchars($emailSettings['smtp_username'] ?? ''); ?>" required>
                    <div class="help-text">E-posta adresiniz</div>
                </div>

                <div class="form-field">
                    <label for="smtp_password" class="required">SMTP Şifre</label>
                    <input type="password" id="smtp_password" name="smtp_password" 
                           value="<?php echo htmlspecialchars($emailSettings['smtp_password'] ?? ''); ?>" required>
                    <div class="help-text">E-posta şifreniz veya uygulama şifresi</div>
                </div>

                <div class="form-field">
                    <label for="smtp_secure">Güvenlik Türü</label>
                    <select id="smtp_secure" name="smtp_secure">
                        <option value="TLS" <?php echo ($emailSettings['smtp_secure'] ?? 'TLS') === 'TLS' ? 'selected' : ''; ?>>TLS</option>
                        <option value="SSL" <?php echo ($emailSettings['smtp_secure'] ?? '') === 'SSL' ? 'selected' : ''; ?>>SSL</option>
                    </select>
                    <div class="help-text">TLS (Port 587) veya SSL (Port 465)</div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</body>
</html>
