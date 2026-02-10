<?php
session_start();
require_once 'db.php';
require_once 'vendor/autoload.php'; // PHPMailer için

// Oturum açıksa yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// --- LOGIN İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Lütfen kullanıcı adı ve şifre giriniz.';
    } else {
        $stmt = $db->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'P') {
                $error = 'Hesabınız pasif durumdadır. Yöneticinizle görüşün.';
            } else {
                // Giriş Başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role_code'] = $user['role_code'];
                
                // Log
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Erişim Logu', ?)");
                $logStmt->execute([$user['id'], "Başarılı giriş yapıldı."]);

                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı!';
            // Hatalı Giriş Logu
            $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (0, 0, 0, 'Erişim Logu', ?)");
            $logStmt->execute(["Başarısız giriş denemesi - Denenen: $username"]);
        }
    }
}

// --- ŞİFRE SIFIRLAMA İŞLEMİ (AJAX İÇİN) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    header('Content-Type: application/json');
    $email = trim($_POST['email']);

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Lütfen e-posta adresinizi giriniz.']);
        exit();
    }

    $stmt = $db->prepare("SELECT id, name FROM user WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        try {
            // 1. Token Oluştur
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 saat geçerli

            // 2. Token'ı Kaydet
            $update = $db->prepare("UPDATE user SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $update->execute([$token, $expires, $user['id']]);

            // 3. Mail Ayarlarını Çek
            $mailSettings = $db->query("SELECT * FROM email_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            
            if ($mailSettings) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $mailSettings['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $mailSettings['smtp_username'];
                $mail->Password = $mailSettings['smtp_password'];
                $mail->SMTPSecure = $mailSettings['smtp_secure'];
                $mail->Port = $mailSettings['smtp_port'];
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($mailSettings['from_email'], $mailSettings['from_name']);
                
                $mail->addAddress($email, $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'Şifre Sıfırlama Talebi - CERTBY';
                
                // Linki oluştur (Kendi domaininize göre düzenleyin)
                // Dinamik olarak şu anki klasörü alır.
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $resetLink = "$protocol://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                
                $mail->Body = "
                    <h3>Merhaba {$user['name']},</h3>
                    <p>Hesabınız için şifre sıfırlama talebinde bulundunuz.</p>
                    <p>Şifrenizi yenilemek için lütfen aşağıdaki butona tıklayın:</p>
                    <p><a href='$resetLink' style='background:#007bff; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block;'>Şifremi Sıfırla</a></p>
                    <p>Bu link 1 saat süreyle geçerlidir.</p>
                    <br>
                    <p>Eğer bu talebi siz yapmadıysanız, bu e-postayı dikkate almayınız.</p>
                ";

                $mail->send();
                echo json_encode(['status' => 'success', 'message' => 'Sıfırlama bağlantısı e-posta adresinize gönderildi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sistem mail ayarları yapılandırılmamış.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Mail gönderim hatası: ' . $e->getMessage()]);
        }
    } else {
        // Güvenlik gereği "Böyle bir mail yok" demek yerine başarılı gibi davranabiliriz veya
        // kullanıcı deneyimi için uyarabiliriz. İç sistem olduğu için uyarmayı tercih ettim.
        echo json_encode(['status' => 'error', 'message' => 'Bu e-posta adresiyle kayıtlı kullanıcı bulunamadı.']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Giriş Yap</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root { --primary-color: #007bff; --background-color: #f4f6f9; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%); margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; }
        
        .login-card { background: #fff; padding: 40px; border-radius: 18px; box-shadow: 0 8px 32px rgba(37, 99, 235, 0.12); width: 100%; max-width: 340px; text-align: center; position: relative; overflow: hidden; }
        .login-card::before { content: ""; position: absolute; top: -60px; right: -60px; width: 120px; height: 120px; background: radial-gradient(circle, #2563eb 60%, transparent 100%); opacity: 0.15; z-index: 0; }
        
        .login-logo { width: 54px; height: 54px; margin-bottom: 12px; border-radius: 50%; background: linear-gradient(135deg, #2563eb 60%, #60a5fa 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12); margin-left: auto; margin-right: auto; position: relative; z-index: 1; }
        .login-logo svg { width: 32px; height: 32px; fill: #fff; }

        .login-header h2 { margin: 0 0 20px; color: #2563eb; font-weight: 700; font-size: 1.8rem; letter-spacing: 1px; position: relative; z-index: 1; }
        
        .form-group { margin-bottom: 18px; text-align: left; position: relative; z-index: 1; }
        .form-group label { display: block; margin-bottom: 6px; color: #333; font-weight: 500; font-size: 0.95rem; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; background: #f9fafb; transition: border-color 0.2s; box-sizing: border-box; }
        .form-control:focus { border-color: var(--primary-color); outline: none; background: #fff; }
        
        .btn-login { width: 100%; padding: 12px; background: linear-gradient(90deg, #2563eb 60%, #60a5fa 100%); color: #fff; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; margin-top: 10px; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.08); transition: background 0.2s; z-index: 1; position: relative; }
        .btn-login:hover { background: linear-gradient(90deg, #1e40af 60%, #2563eb 100%); }
        
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 18px; font-size: 0.9rem; font-weight: 500; text-align: left; z-index: 1; position: relative; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #b91c1c; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .forgot-link { display: block; margin-top: 15px; color: #666; font-size: 0.9rem; text-decoration: none; cursor: pointer; position: relative; z-index: 1; transition: color 0.2s; }
        .forgot-link:hover { text-decoration: underline; color: var(--primary-color); }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); position: relative; animation: slideDown 0.3s; }
        @keyframes slideDown { from {transform: translateY(-30px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; color: #aaa; cursor: pointer; }
        .close-btn:hover { color: #000; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <svg viewBox="0 0 24 24">
            <path d="M12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm7-5a7 7 0 1 0-7 7v-2a5 5 0 1 1 5-5h2zm-7-3a3 3 0 1 0 0 6 3 3 0 0 0 0-6z" />
        </svg>
    </div>
    <div class="login-header">
        <h2>CERTBY Giriş</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>Kullanıcı Adı / E-Posta</label>
            <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="form-group">
            <label>Şifre</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn-login">Giriş Yap</button>
    </form>

    <a onclick="openForgotModal()" class="forgot-link">Şifremi Unuttum</a>
</div>

<div id="forgotModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeForgotModal()">&times;</span>
        <h2 style="margin-top:0; color:#2563eb; font-size:1.5rem;">Şifre Sıfırlama</h2>
        <p style="color:#666; font-size:0.9rem; margin-bottom:20px; line-height:1.5;">Kayıtlı e-posta adresinizi giriniz. Size şifre sıfırlama bağlantısı göndereceğiz.</p>
        
        <div id="forgotAlert" class="alert" style="display:none;"></div>

        <form id="forgotForm">
            <input type="hidden" name="action" value="forgot_password">
            <div class="form-group">
                <label>E-posta Adresi</label>
                <input type="email" name="email" id="forgotEmail" class="form-control" required placeholder="ornek@sirket.com">
            </div>
            <button type="submit" id="btnForgot" class="btn-login">Gönder</button>
        </form>
    </div>
</div>

<script>
    function openForgotModal() {
        document.getElementById('forgotModal').style.display = 'block';
        document.getElementById('forgotEmail').focus();
    }
    function closeForgotModal() {
        document.getElementById('forgotModal').style.display = 'none';
        document.getElementById('forgotAlert').style.display = 'none';
        document.getElementById('forgotForm').reset();
    }

    // AJAX Form Gönderimi
    document.getElementById('forgotForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnForgot');
        const alertBox = document.getElementById('forgotAlert');
        const fd = new FormData(this);

        btn.disabled = true;
        btn.innerText = 'Gönderiliyor...';
        alertBox.style.display = 'none';

        fetch('index.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            alertBox.style.display = 'block';
            if(res.status === 'success') {
                alertBox.className = 'alert alert-success';
                alertBox.innerText = res.message;
                // 3 saniye sonra modalı kapat
                setTimeout(closeForgotModal, 3000);
            } else {
                alertBox.className = 'alert alert-danger';
                alertBox.innerText = res.message;
            }
        })
        .catch(err => {
            alertBox.style.display = 'block';
            alertBox.className = 'alert alert-danger';
            alertBox.innerText = 'Bir hata oluştu. Lütfen tekrar deneyin.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = 'Gönder';
        });
    });
    
    window.onclick = function(e) {
        if(e.target == document.getElementById('forgotModal')) closeForgotModal();
    }
</script>

</body>
</html>