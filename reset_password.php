<?php
require_once 'db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$isValid = false;
$userId = null;

// 1. Token Kontrolü (Sayfa Yüklendiğinde)
if (!empty($token)) {
    $stmt = $db->prepare("SELECT id, password FROM user WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $isValid = true;
        $userId = $user['id'];
        $currentHash = $user['password'];
    } else {
        $error = 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş.';
    }
} else {
    $error = 'Geçersiz istek.';
}

// 2. Şifre Değiştirme İşlemi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValid) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    // Backend Şifre Politikası Kontrolü
    if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        $error = 'Şifre en az 8 karakter olmalı; büyük harf, küçük harf ve rakam içermelidir.';
    } 
    elseif ($pass !== $confirm) {
        $error = 'Şifreler eşleşmiyor.';
    } 
    // YENİ: Eski Şifre Kontrolü
    elseif (password_verify($pass, $currentHash)) {
        $error = 'Yeni şifreniz, mevcut şifrenizle aynı olamaz. Lütfen farklı bir şifre belirleyin.';
    } 
    else {
        // Şifreyi Güncelle ve Token'ı Temizle
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE user SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($update->execute([$hashed, $userId])) {
            $success = 'Şifreniz başarıyla değiştirildi. Giriş sayfasına yönlendiriliyorsunuz...';
            
            // Log
            $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Kullanıcı Yönetim Logu', ?)")
               ->execute([$userId, "Kullanıcı şifresini 'Şifremi Unuttum' ile sıfırladı."]);
            
            header("refresh:3;url=index.php");
        } else {
            $error = 'Veritabanı hatası oluştu.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - CERTBY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .card { 
            background: #fff; 
            padding: 40px; 
            border-radius: 18px; 
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.12); 
            width: 100%; 
            max-width: 400px; 
        }
        h2 { margin-top: 0; color: #2563eb; text-align: center; margin-bottom: 20px;}
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #333; font-weight: 500; font-size: 0.9rem; }
        input { 
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; 
            transition: border-color 0.2s; font-size: 1rem;
        }
        input:focus { border-color: #2563eb; outline: none; }
        
        button { 
            width: 100%; padding: 12px; background: linear-gradient(90deg, #2563eb 60%, #60a5fa 100%); 
            color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight:600;
            margin-top: 10px;
        }
        button:hover { background: linear-gradient(90deg, #1e40af 60%, #2563eb 100%); }
        
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: left; font-size: 0.9rem; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .link { text-align: center; display: block; margin-top: 15px; color: #666; text-decoration: none; font-size: 0.9rem; }
        .link:hover { text-decoration: underline; color: #2563eb; }

        /* Şifre Kuralları Kutusu */
        .password-rules-box { 
            font-size: 0.85rem; color: #666; background: #f8f9fa; border: 1px solid #e9ecef; 
            border-radius: 6px; padding: 10px; margin-top: 8px; display: none; 
        }
        .rule-item { display: flex; align-items: center; margin-bottom: 4px; }
        .rule-item i { margin-right: 8px; font-size: 0.8rem; width: 14px; text-align: center; }
        .rule-item.valid { color: #28a745; }
        .rule-item.invalid { color: #dc3545; }

        .password-match-message { margin-top: 5px; font-size: 0.85rem; font-weight: 500; min-height: 20px;}
        .password-match-message.success { color: #28a745; }
        .password-match-message.error { color: #dc3545; }
    </style>
</head>
<body>

<div class="card">
    <h2>Yeni Şifre Belirle</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php if(!$isValid): ?><a href="index.php" class="link">Giriş Sayfasına Dön</a><?php endif; ?>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($isValid && empty($success)): ?>
    <form method="POST" onsubmit="return validateForm()">
        <div class="form-group">
            <label>Yeni Şifre</label>
            <input type="password" id="password" name="password" required 
                   placeholder="En az 8 karakter" 
                   onkeyup="checkPasswordRules(this.value); checkPasswordMatch();"
                   onfocus="document.getElementById('passwordRulesBox').style.display='block'">
            
            <div id="passwordRulesBox" class="password-rules-box">
                <div id="rule-len" class="rule-item invalid"><i class="fas fa-times-circle"></i> En az 8 Karakter</div>
                <div id="rule-up" class="rule-item invalid"><i class="fas fa-times-circle"></i> 1 Büyük Harf (A-Z)</div>
                <div id="rule-low" class="rule-item invalid"><i class="fas fa-times-circle"></i> 1 Küçük Harf (a-z)</div>
                <div id="rule-num" class="rule-item invalid"><i class="fas fa-times-circle"></i> 1 Rakam (0-9)</div>
            </div>
        </div>
        <div class="form-group">
            <label>Yeni Şifre (Tekrar)</label>
            <input type="password" id="confirm_password" name="confirm_password" required 
                   onkeyup="checkPasswordMatch()">
            <div id="passwordMatchMessage" class="password-match-message"></div>
        </div>
        <button type="submit" id="btnSubmit">Şifreyi Değiştir</button>
    </form>
    <?php endif; ?>
</div>

<script>
    function checkPasswordRules(val) {
        const box = document.getElementById('passwordRulesBox');
        box.style.display = 'block';

        updateRule('rule-len', val.length >= 8);
        updateRule('rule-up', /[A-Z]/.test(val));
        updateRule('rule-low', /[a-z]/.test(val));
        updateRule('rule-num', /[0-9]/.test(val));
    }

    function updateRule(id, isValid) {
        const el = document.getElementById(id);
        const icon = el.querySelector('i');
        if (isValid) {
            el.className = 'rule-item valid';
            icon.className = 'fas fa-check-circle';
        } else {
            el.className = 'rule-item invalid';
            icon.className = 'fas fa-times-circle';
        }
    }

    function checkPasswordMatch() {
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const msg = document.getElementById('passwordMatchMessage');

        if (confirm === '') {
            msg.textContent = '';
            msg.className = 'password-match-message';
            return;
        }
        if (pass === confirm) {
            msg.textContent = 'Şifreler Eşleşiyor';
            msg.className = 'password-match-message success';
        } else {
            msg.textContent = 'Şifreler Eşleşmiyor';
            msg.className = 'password-match-message error';
        }
    }

    function validateForm() {
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;

        if (pass.length < 8 || !/[A-Z]/.test(pass) || !/[a-z]/.test(pass) || !/[0-9]/.test(pass)) {
            alert('Şifre kurallara uymuyor.');
            return false;
        }
        if (pass !== confirm) {
            alert('Şifreler eşleşmiyor.');
            return false;
        }
        return true;
    }
</script>

</body>
</html>