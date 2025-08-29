<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Yeni Kullanıcı Ekle</title>
    <style>

        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #e9ecef;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
            color: #343a40;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Sayfa Başlığı ve Form Stili */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
        }

        .header-title {
            font-size: 2.2rem;
            font-weight: 300;
            color: var(--primary-color);
            display: inline-block;
        }

        .form-section {
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--secondary-color);
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 8px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-field {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #495057;
        }
        
        label.required::after {
            content: " *";
            color: var(--danger-color);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-size: 1rem;
        }
        
        input:focus,
        select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .button-group {
            border-top: 1px solid var(--border-color);
            padding-top: 25px;
            text-align: right;
        }

        .action-button {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            background-color: var(--primary-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            text-decoration: none;
        }

        .action-button.cancel {
            background-color: var(--secondary-color);
            margin-right: 10px;
        }

        .action-button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .password-match-message {
        margin-top: 5px;
        font-size: 0.9em;
        font-weight: 500;
        }

        .password-match-message.success {
        color: #28a745; /* Yeşil */
        }

        .password-match-message.error {
        color: #dc3545; /* Kırmızı */
        }

    </style>
</head>
<body>

    <div class="container">
        <header class="header">
            <h1 class="header-title">Yeni Kullanıcı Ekle</h1>
        </header>
        
        <form action="register_user_process.php" method="POST" onsubmit="return validatePassword()">
            
            <div class="form-section">
                <h2 class="form-section-title">Kullanıcı Bilgileri</h2>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="username" class="required">Kullanıcı Adı</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-field">
                        <label for="name" class="required">Ad Soyad</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-field">
                        <label for="email" class="required">E-posta</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-field">
                        <label for="role_code" class="required">Rol</label>
                        <select id="role_code" name="role_code" required>
                            <option value="">Seçiniz</option>
                            <option value="Operatör">Operatör</option>
                            <option value="Danışman">Danışman</option>
                            <option value="Denetçi">Denetçi</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="status" class="required">Durum</label>
                        <select id="status" name="status" required>
                            <option value="Aktif" selected>Aktif</option>
                            <option value="Pasif">Pasif</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2 class="form-section-title">Şifre Belirleme</h2>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="password" class="required">Şifre</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-field">
                        <label for="confirm_password" class="required">Şifre (Tekrar)</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
            </div>
            
            <div class="button-group">
                <a href="user.php" class="action-button cancel">İptal</a>
                <button type="submit" class="action-button">Kullanıcı Oluştur</button>
            </div>
        </form>
    </div>

    <script>
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');

        const message = document.createElement('div');
        message.className = 'password-match-message';
        confirmPasswordField.parentNode.appendChild(message);

        passwordField.addEventListener('input', checkPasswordMatch);
        confirmPasswordField.addEventListener('input', checkPasswordMatch);
        function checkPasswordMatch() {
            if (confirmPasswordField.value === '') {
                message.textContent = '';
                message.className = 'password-match-message';   
            }

            if (passwordField.value === confirmPasswordField.value) {
                message.textContent = 'Sifreler eşleşiyor';
                message.className = 'password-match-message success';
               
            } else{
                message.textContent = 'Şifreler eşleşmiyor.';
                message.className = 'password-match-message error';
            }
        }
        function validatePassword() {
        let isValid = true;
        let errorMessage = '';
      
        if (password !== confirmPassword) {
            errorMessage += "Şifreler eşleşmiyor.<br>";
            isValid = false;
        }

        if (password.length < 8) {
            errorMessage += "Şifre en az 8 karakter uzunluğunda olmalıdır.<br>";
            isValid = false;
        }

        const hasUpperCase = /[A-Z]/.test(password);
        if (!(/[A-Z]/.test(password))) {
            errorMessage += 'Şifre en az bir büyük harf içermelidir.<br>';
            isValid = false;
        }

        const hasLowerCase = /[a-z]/.test(password);
        if (!(/[a-z]/.test(password))) {
            errorMessage += 'Şifre en az bir küçük harf içermelidir.<br>';
            isValid = false;
        }

        const hasNumber = /[0-9]/.test(password);
        if (!(/[0-9]/.test(password))) {
            errorMessage += 'Şifre en az bir rakam içermelidir.<br>';
            isValid = false;
        }

        if (!isValid) {
            alert(errorMessage);
            passwordField.focus();
            return false;
        }

        return true;
    }
    </script>

</body>
</html>