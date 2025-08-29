<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Kullanıcı Yönetimi</title>
    <style>
        /* Ana Renk Paleti ve Stil Temeli */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #e9ecef;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Sayfa Başlığı ve Buton */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .header-title {
            font-size: 2rem;
            font-weight: 300;
            color: var(--primary-color);
        }

        .add-button {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            background-color: var(--primary-color);
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        .add-button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        /* Mesaj Kutusu */
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            display: none; /* Varsayılan olarak gizli */
        }

        .alert-success {
            background-color: #d4edda;
            color: var(--success-color);
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: var(--danger-color);
            border: 1px solid #f5c6cb;
        }

        /* Arama Alanı */
        .search-container {
            margin-bottom: 25px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        /* Kullanıcı Tablosu */
        .table-responsive {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th, .user-table td {
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
        }

        .user-table th {
            background-color: var(--background-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: var(--secondary-color);
        }
        
        .user-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .user-table tbody tr {
            transition: background-color 0.2s;
        }

        .user-table tbody tr:hover {
            background-color: #f1f3f5;
        }
        
        .user-table td:last-child {
            text-align: center;
            white-space: nowrap;
        }

        .action-button {
            padding: 8px 15px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
        }
        
        .action-button.update {
            background-color: var(--primary-color);
            color: #fff;
        }

        .action-button.delete {
            background-color: var(--danger-color);
            color: #fff;
        }
        
        .action-button:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <header class="header">
            <h1 class="header-title">Kullanıcı Yönetimi</h1>
            <a href="create_user.php" class="add-button">Yeni Kullanıcı Ekle</a>
        </header>

        <div id="statusMessage" class="alert"></div>

        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Kullanıcı adı, e-posta veya role göre ara...">
        </div>

        <div class="table-responsive">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>Kullanıcı Adı</th>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Rol</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="user-1">
                        <td>admin</td>
                        <td>Admin Kullanıcı</td>
                        <td>admin@certby.com</td>
                        <td>Operatör</td>
                        <td>Aktif</td>
                        <td>
                            <a href="update_user.php?id=1" class="action-button update">Güncelle</a>
                            <button class="action-button delete" onclick="deleteUser(1)">Sil</button>
                        </td>
                    </tr>
                    <tr id="user-2">
                        <td>denetci1</td>
                        <td>Denetçi A.</td>
                        <td>denetci@certby.com</td>
                        <td>Denetçi</td>
                        <td>Aktif</td>
                        <td>
                            <a href="update_user.php?id=2" class="action-button update">Güncelle</a>
                            <button class="action-button delete" onclick="deleteUser(2)">Sil</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function deleteUser(userId) {
            // Kullanıcı silme işlemini backend'e göndermeden önce onay al
            if (confirm("Kullanıcıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.")) {
                // Silme işlemini tetikleyecek backend sayfasını çağır
                // Örnek: window.location.href = 'delete_user.php?id=' + userId;
                
                // Şimdilik sadece satırı gizle (Backend mantığı eklenince bu satır silinecek)
                document.getElementById('user-' + userId).style.display = 'none';
                alert("Kullanıcı silindi (sadece test amaçlı gizlendi).");
            }
        }

        // Sayfa yüklendiğinde URL'deki status parametresini kontrol et
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const messageBox = document.getElementById('statusMessage');

            if (status) {
                if (status === 'success') {
                    messageBox.innerText = 'İşlem başarılı! Kullanıcı bilgileri güncellendi.';
                    messageBox.classList.add('alert-success');
                } else if (status === 'error') {
                    messageBox.innerText = 'Hata! Güncelleme işlemi başarısız oldu.';
                    messageBox.classList.add('alert-danger');
                }
                // Mesaj kutusunu görünür yap
                messageBox.style.display = 'block';

                // URL'yi temizle (isteğe bağlı, sayfanın temiz görünmesini sağlar)
                history.replaceState({}, '', window.location.pathname);
            }
        }
    </script>
</body>
</html>