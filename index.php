<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT id, username, password, role_code, status FROM user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_code'] = $user['role_code'];
        header('Location: dashboard.php');
        exit();
    } else {
        $error_message = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Belge Takip Sistemi</title>
    <style>
        body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
        min-height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-container {
        background: #fff;
        padding: 36px 32px 32px 32px;
        border-radius: 18px;
        box-shadow: 0 8px 32px rgba(37,99,235,0.12);
        width: 100%;
        max-width: 340px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .login-container::before {
        content: "";
        position: absolute;
        top: -60px;
        right: -60px;
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, #2563eb 60%, transparent 100%);
        opacity: 0.15;
        z-index: 0;
    }
    .login-container h2 {
        margin-bottom: 18px;
        color: #2563eb;
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: 1px;
        z-index: 1;
        position: relative;
    }
    .form-group {
        margin-bottom: 18px;
        text-align: left;
        z-index: 1;
        position: relative;
    }
    .form-group label {
        display: block;
        margin-bottom: 6px;
        color: #333;
        font-weight: 500;
    }
    .form-group input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 1rem;
        background: #f9fafb;
        transition: border-color 0.2s;
    }
    .form-group input:focus {
        border-color: #2563eb;
        outline: none;
        background: #fff;
    }
    .btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(90deg, #2563eb 60%, #60a5fa 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 10px;
        box-shadow: 0 2px 8px rgba(37,99,235,0.08);
        transition: background 0.2s;
    }
    .btn:hover {
        background: linear-gradient(90deg, #1e40af 60%, #2563eb 100%);
    }
    .error {
        color: #b91c1c;
        background: #fee2e2;
        border: 1px solid #b91c1c;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 18px;
        font-weight: 500;
        font-size: 1rem;
        z-index: 1;
        position: relative;
    }
    .login-logo {
        width: 54px;
        height: 54px;
        margin-bottom: 12px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2563eb 60%, #60a5fa 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(37,99,235,0.12);
        margin-left: auto;
        margin-right: auto;
    }
    .login-logo svg {
        width: 32px;
        height: 32px;
        fill: #fff;
    }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <!-- Basit bir anahtar simgesi SVG -->
            <svg viewBox="0 0 24 24"><path d="M12 17a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm7-5a7 7 0 1 0-7 7v-2a5 5 0 1 1 5-5h2zm-7-3a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
        </div>
        <h2>CERTBY Giriş</h2>
        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <form action="" method="post">
            <div class="form-group">
                <label for="username">Kullanıcı adı</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Parola</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Giriş Yap</button>
        </form>
    </div>
</body>
</html>
