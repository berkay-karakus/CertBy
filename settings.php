<?php
session_start();

// --- GÜVENLİK KONTROLÜ ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// Sadece OPERATÖR erişebilir
$userRole = strtolower($_SESSION['role_code']);
if ($userRole !== 'operator') {
    header("Location: dashboard.php?error=yetkisiz_erisim");
    exit();
}

// ---------------------------------------------------------
// BACKEND İŞLEMLERİ
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        // SMTP AYARLARI KAYDET
        if ($action === 'save_smtp') {
            $host = trim($_POST['smtp_host']);
            $port = intval($_POST['smtp_port']);
            $user = trim($_POST['smtp_username']);
            $pass = trim($_POST['smtp_password']);
            $secure = trim($_POST['smtp_secure']);
            $fromName = trim($_POST['from_name']);
            $fromEmail = trim($_POST['from_email']);

            // Mevcut ayar var mı kontrol et
            $check = $db->query("SELECT count(*) FROM email_settings")->fetchColumn();
            
            if ($check > 0) {
                // İlk satırı güncelle
                $firstId = $db->query("SELECT id FROM email_settings LIMIT 1")->fetchColumn();
                $sql = "UPDATE email_settings SET smtp_host=?, smtp_port=?, smtp_username=?, smtp_password=?, smtp_secure=?, from_name=?, from_email=? WHERE id=?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$host, $port, $user, $pass, $secure, $fromName, $fromEmail, $firstId]);
            } else {
                // Yoksa oluştur
                $sql = "INSERT INTO email_settings (smtp_host, smtp_port, smtp_username, smtp_password, smtp_secure, from_name, from_email) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$host, $port, $user, $pass, $secure, $fromName, $fromEmail]);
            }

            // Logla
            $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Sistem Ayarları', ?)");
            $logStmt->execute([$_SESSION['user_id'], "SMTP e-posta ayarları güncellendi."]);

            echo json_encode(['status' => 'success', 'message' => 'E-posta ayarları başarıyla kaydedildi.']);
            exit();
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Hata: ' . $e->getMessage()]);
        exit();
    }
}

// --- VERİLERİ ÇEK (GET) ---
$smtp = $db->query("SELECT * FROM email_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
// Varsayılan değerler (Eğer DB boşsa)
if(!$smtp) {
    $smtp = [
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_secure' => 'tls',
        'from_name' => 'CERTBY Bildirim',
        'from_email' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Genel Ayarlar</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        /* MEVCUT TASARIM SİSTEMİ */
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        
        .container { max-width: 800px; margin: 0 auto; background-color: var(--card-background); padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }

        /* FORM ELEMANLARI */
        .form-section-title { font-size: 1.1rem; font-weight: 600; color: #495057; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px dashed #ccc; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.95rem; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1rem; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--primary-color); outline: none; }
        
        .form-row { display: flex; gap: 20px; }
        .form-row .form-group { flex: 1; }

        /* BUTONLAR */
        .btn { padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: 500; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background-color 0.2s; }
        .btn-primary { background-color: var(--primary-color); }
        .btn-primary:hover { background-color: #0056b3; }
        
        /* Info Box */
        .info-box { background-color: #e3f2fd; border-left: 4px solid var(--primary-color); padding: 15px; margin-bottom: 25px; border-radius: 4px; color: #0c5460; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">Genel Ayarlar</h1>
    </div>

    <div class="info-box">
        <i class="fa fa-info-circle"></i> 
        Buradaki ayarlar sistemin e-posta gönderim altyapısını (SMTP) yapılandırır. Değişiklik yapmadan önce bilgilerin doğruluğundan emin olunuz.
    </div>

    <form id="smtpForm">
        <input type="hidden" name="action" value="save_smtp">
        
        <div class="form-section-title">E-posta Gönderim Ayarları</div>

        <div class="form-row">
            <div class="form-group">
                <label>Gönderen Adı (Görünen İsim)</label>
                <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars($smtp['from_name']); ?>" placeholder="Örn: CERTBY Bildirim" required>
            </div>
            <div class="form-group">
                <label>Gönderen E-posta Adresi</label>
                <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" placeholder="Örn: noreply@certby.com" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>SMTP Sunucusu (Host)</label>
                <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_host']); ?>" placeholder="Örn: smtp.gmail.com" required>
            </div>
            <div class="form-group">
                <label>SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_port']); ?>" placeholder="587" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>SMTP Kullanıcı Adı</label>
                <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_username']); ?>" required>
            </div>
            <div class="form-group">
                <label>SMTP Şifre</label>
                <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($smtp['smtp_password']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Güvenlik Türü</label>
            <select name="smtp_secure" class="form-control">
                <option value="tls" <?php echo ($smtp['smtp_secure'] == 'tls') ? 'selected' : ''; ?>>TLS (Önerilen)</option>
                <option value="ssl" <?php echo ($smtp['smtp_secure'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                <option value="" <?php echo ($smtp['smtp_secure'] == '') ? 'selected' : ''; ?>>Yok (Güvenli Değil)</option>
            </select>
        </div>

        <div style="text-align: right; margin-top: 30px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Ayarları Kaydet</button>
        </div>
    </form>

</div>

<script>
    document.getElementById('smtpForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Kaydediliyor...';

        const fd = new FormData(this);
        
        fetch('settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            alert(res.message);
            if(res.status === 'success') {
                // İsteğe bağlı: Sayfayı yenile
                // location.reload();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Bir hata oluştu.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
</script>

</body>
</html>