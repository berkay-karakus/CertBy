<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_code = $_SESSION['role_code'];

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Kontrol Paneli</title>
    <style>
        /* Modern Renk Paleti ve Stil Temeli */
        :root {
            --primary-color: #007bff; /* Mavi - Vurgu ve buton rengi */
            --secondary-color: #6c757d; /* Gri - İkincil metinler */
            --background-color: #f8f9fa; /* Açık gri - Sayfa arkaplanı */
            --card-background: #ffffff; /* Beyaz - Kart ve kutu arkaplanı */
            --border-color: #e9ecef; /* İnce gri - Kenarlıklar */
            --success-color: #28a745; /* Yeşil - Başarı mesajları */
            --warning-color: #ffc107; /* Sarı - Uyarılar */
            --danger-color: #dc3545; /* Kırmızı - Tehlikeler */
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
            color: #343a40;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Yan Menü */
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            box-shadow: 2px 0 6px rgba(0, 0, 0, 0.1);
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 500;
            color: #fff;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu a {
            display: block;
            color: #bdc3c7;
            padding: 12px 15px;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s, color 0.2s;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: var(--primary-color);
            color: #fff;
        }

        /* Main Content - Ana İçerik Alanı */
        .main-content {
            flex-grow: 1;
            padding: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 300;
            color: var(--primary-color);
        }

        .user-info {
            font-size: 0.9rem;
            color: var(--secondary-color);
        }
        
        /* Dashboard Kartları ve Tabloları */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background-color: var(--card-background);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }

        .card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .card-text {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .table-section {
            background-color: var(--card-background);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th, .data-table td {
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: var(--background-color);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .table-action-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .table-action-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-logo">
                CERTBY
            </div>
            <nav class="sidebar-menu">
                <a href="#" class="active">Ana Sayfa</a>
                <a href="company.php">Firmalar</a>
                <a href="cert.php">Belge Yönetimi</a>
                <a href="certification.php">Belgelendirme Yönetimi</a>
                <a href="denetimler.php">Denetim Planlama</a>
                <a href="raporlar.php">Raporlar</a>
                <a href="user.php">Kullanıcı Yönetimi</a>
                <a href="ayarlar.php">Ayarlar</a>
                <a href="cikis.php">Çıkış</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1 class="header-title">Kontrol Paneli</h1>
                <div class="user-info">
                    Merhaba, **Mesut**<br>
                    Rol: **Operatör**
                </div>
            </header>

            <div class="dashboard-grid">
                
                <div class="dashboard-card" style="border-left: 4px solid var(--primary-color);">
                    <div class="card-title">Toplam Firma Sayısı</div>
                    <div class="card-value">124</div>
                    <div class="card-text">Sistemde kayıtlı tüm firmalar</div>
                </div>

                <div class="dashboard-card" style="border-left: 4px solid var(--success-color);">
                    <div class="card-title">Aktif Belgelendirme</div>
                    <div class="card-value">85</div>
                    <div class="card-text">Süresi devam eden belgeler</div>
                </div>

                <div class="dashboard-card" style="border-left: 4px solid var(--warning-color);">
                    <div class="card-title">Yaklaşan Yenilemeler</div>
                    <div class="card-value">12</div>
                    <div class="card-text">90 gün içinde süresi dolacak belgeler</div>
                </div>

                <div class="dashboard-card" style="border-left: 4px solid var(--danger-color);">
                    <div class="card-title">Süresi Dolmuş Belgeler</div>
                    <div class="card-value">5</div>
                    <div class="card-text">Yenileme süresi geçmiş belgeler</div>
                </div>
            </div>

            <div class="table-section">
                <h2 class="table-title">Yaklaşan Denetimler</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Firma Adı</th>
                            <th>Belge Türü</th>
                            <th>Başlangıç Tarihi</th>
                            <th>Bitiş Tarihi</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>ABC Ltd. Şti.</td>
                            <td>ISO 27001</td>
                            <td>27.08.2025</td>
                            <td>28.08.2025</td>
                            <td><a href="#" class="table-action-link">Detay</a></td>
                        </tr>
                        <tr>
                            <td>DEF A.Ş.</td>
                            <td>ISO 9001</td>
                            <td>05.09.2025</td>
                            <td>05.09.2025</td>
                            <td><a href="#" class="table-action-link">Detay</a></td>
                        </tr>
                        <tr>
                            <td>GHI Teknoloji</td>
                            <td>ISO 20000</td>
                            <td>15.09.2025</td>
                            <td>16.09.2025</td>
                            <td><a href="#" class="table-action-link">Detay</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

</body>
</html>
