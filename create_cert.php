<?php

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Yeni Belge Tanımlama</title>
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
            max-width: 600px;
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

        .form-field {
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
        input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        input:focus {
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
            <h1 class="header-title">Yeni Belge Tanımlama</h1>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="add_document.php" method="POST">
            
            <div class="form-section">
                <h2 class="form-section-title">Belge Bilgileri</h2>
                
                <div class="form-field">
                    <label for="name" class="required">Belge Adı (örneğin: BGYS)</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-field">
                    <label for="standard" class="required">Belge Standardı (ISO/IEC 27001 vb)</label>
                    <input type="text" id="standard" name="standard" required>
                </div>
                
                <div class="form-field">
                    <label for="period" class="required">Belgelendirme Periyodu (Yıl)</label>
                    <input type="number" id="period" name="period" required>
                </div>

                <div class="form-field">
                    <label for="surveillance_count" class="required">Ara Tetkik Sayısı</label>
                    <input type="number" id="surveillance_count" name="surveillance_count" required>
                </div>
                
            </div>
            
            <div class="button-group">
                <button type="submit" class="action-button">Belge Ekle</button>
            </div>
        </form>
    </div>

</body>
</html>
