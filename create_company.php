<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_code = $_SESSION['role_code'];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_once 'db.php';

    $c_name = $_POST['c_name'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $contact_address = $_POST['contact_address'];
    $web = $_POST['web'];
    $c_phone = $_POST['c_phone'];
    $c_email = $_POST['c_email'];
    $c_invoice_address = $_POST['c_invoice_address'];
    $authorized_contact_name = $_POST['authorized_contact_name'];
    $authorized_contact_title = $_POST['authorized_contact_title'];
    $contact_name = $_POST['contact_name'];
    $contact_phone = $_POST['contact_phone'];
    $contact_email = $_POST['contact_email'];
    $tax_office = $_POST['tax_office'];
    $tax_number = $_POST['tax_number'];
    $consulting_id = $_POST['consulting_id'];
    $status = $_POST['status'];

    $query_insert = $db->prepare("INSERT INTO company(c_name, name, address, contact_address, web, c_phone, c_email,
    c_invoice_address, authorized_contact_name, authorized_contact_title, contact_name, contact_phone, 
    contact_email, tax_office, tax_number, consulting_id, status)
			      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'A')");

    $result_insert = $query->execute([$c_name, $name, $address, $contact_address, $web, $c_phone, $c_email,
    $c_invoice_address, $authorized_contact_name, $authorized_contact_title, $contact_name, $contact_phone,
    $contact_email, $tax_office, $tax_number, $consulting_id, $status]);

    if ($result_insert) {
        $message = "Yeni şirket başarıyla tanımlandı.";
		$message_type = "success";
        header("Location: dashboard.php");
        exit();
    } else {
        $query_select = $db->prepare("SELECT COUNT(*) FROM company WHERE $tax_office === tax_office
        AND $tax_number === tax_number");
        $result_select = $query_select->execute();
        if ($result_select > 0) {
            $message = "Bu vergi dairesi ve vergi numarası ile kayıtlı şirket zaten mevcut.";
            $message_type = 'error';
            header("Location: create_company.php");
            exit();
        } else {
            $message = 'Şirket tanımlanamadı, lütfen tekrar deneyin.';
            $message_type = 'error';
            header("Location: create_company.php");
            exit();
        }
    }


}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Yeni Firma Tanımlama</title>
    <style>
        /* Modern Renk Paleti ve Stil Temeli */
        :root {
            --primary-color: #007bff; /* Mavi - Vurgu ve buton rengi */
            --secondary-color: #6c757d; /* Gri - İkincil metinler */
            --background-color: #f8f9fa; /* Açık gri - Sayfa arkaplanı */
            --card-background: #ffffff; /* Beyaz - Kart ve kutu arkaplanı */
            --border-color: #ced4da; /* İnce gri - Kenarlıklar */
            --success-color: #28a745; /* Yeşil - Başarı mesajları */
            --error-color: #dc3545; /* Kırmızı - Hata mesajları */
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background-color: var(--background-color);
            color: #343a40;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: var(--card-background);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header-title {
            font-size: 2.2rem;
            font-weight: 300;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            display: inline-block;
        }

        .form-section {
            padding: 25px;
            margin-bottom: 30px;
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

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .form-field {
            flex: 1 1 45%;
            padding: 0 15px;
            margin-bottom: 20px;
        }

        .form-field-full {
            flex: 1 1 100%;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        label.required::after {
            content: " *";
            color: var(--error-color);
        }
        
        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        textarea {
            resize: vertical;
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
        }

        .action-button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .alert.success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert.error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>

    <div class="container">
        <header class="header">
            <h1 class="header-title">Yeni Firma Tanımlama</h1>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="create_company.php" method="POST">
            
            <div class="form-section">
                <h2 class="form-section-title">Ticari ve İletişim Bilgileri</h2>
                <div class="form-row">
                    <div class="form-field">
                        <label for="c_name" class="required">Firma Kısa Adı</label>
                        <input type="text" id="c_name" name="c_name" required>
                    </div>
                    <div class="form-field">
                        <label for="name" class="required">Firma Ticari Unvanı</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field-full">
                        <label for="c_invoice_address" class="required">Fatura Adresi</label>
                        <textarea id="c_invoice_address" name="c_invoice_address" rows="3" required></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label for="contact_address">İletişim Adresi</label>
                        <input type="text" id="contact_address" name="contact_address">
                    </div>
                    <div class="form-field">
                        <label for="web">Web Sitesi</label>
                        <input type="text" id="web" name="web">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label for="c_phone">Telefonu</label>
                        <input type="text" id="c_phone" name="c_phone">
                    </div>
                    <div class="form-field">
                        <label for="c_email">Kurumsal İletişim E-postası</label>
                        <input type="email" id="c_email" name="c_email">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="form-section-title">Yetkili ve İrtibat Kişisi Bilgileri</h2>
                <div class="form-row">
                    <div class="form-field">
                        <label for="authorized_contact_name">Yetkili Kişi Adı ve Soyadı</label>
                        <input type="text" id="authorized_contact_name" name="authorized_contact_name">
                    </div>
                    <div class="form-field">
                        <label for="authorized_contact_title">Yetkili Kişi Unvanı</label>
                        <input type="text" id="authorized_contact_title" name="authorized_contact_title">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label for="contact_name" class="required">İrtibat Kişisi Adı ve Soyadı</label>
                        <input type="text" id="contact_name" name="contact_name" required>
                    </div>
                    <div class="form-field">
                        <label for="contact_phone" class="required">İrtibat Kişi Telefonu</label>
                        <input type="text" id="contact_phone" name="contact_phone" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label for="contact_email" class="required">İrtibat Kişi E-postası</label>
                        <input type="email" id="contact_email" name="contact_email" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2 class="form-section-title">Vergi ve Danışman Firma Bilgileri</h2>
                <div class="form-row">
                    <div class="form-field">
                        <label for="tax_office" class="required">Vergi Dairesi</label>
                        <input type="text" id="tax_office" name="tax_office" required>
                    </div>
                    <div class="form-field">
                        <label for="tax_number" class="required">Vergi Numarası</label>
                        <input type="text" id="tax_number" name="tax_number" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field-full">
                        <label for="consulting_id">Danışman Firma</label>
                        <select id="consulting_id" name="consulting_id">
                            <option value="">Danışman Firma Seçiniz</option>
                            </select>
                    </div>
                </div>
            </div>
            
            <div class="button-group">
                <a href="company.php" class="action-button">Firma Ekle</a>
            </div>
        </form>
    </div>
</body>
</html>