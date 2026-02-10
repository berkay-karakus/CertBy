<?php
session_start();

// Oturum Kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_code = $_SESSION['role_code'];

// Kullanıcının tam adını çek (Denetçi eşleşmesi için gerekli)
$stmt = $db->prepare("SELECT name FROM user WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$fullName = $currentUser['name'] ?? $username;

// --- ROL TANIMLAMALARI ---
$currentRole = strtolower($role_code);
$isOperator = ($currentRole === 'operator');
$isUser     = ($currentRole === 'user');
$isAuditor  = ($currentRole === 'auditor');

// Görünen Rol Adı
$displayRole = 'Bilinmeyen Rol';
if ($isOperator) $displayRole = 'Operatör';
elseif ($isUser) $displayRole = 'Kullanıcı';
elseif ($isAuditor) $displayRole = 'Denetçi';

// --- VERİLERİ ÇEKME (Sadece Operatör ve Kullanıcı İçin) ---
$stats = [
    'company_count' => 0,
    'active_cert_count' => 0,
    'upcoming_audit_count' => 0,
    'expired_cert_count' => 0,
    'user_count' => 0
];
$recentCerts = [];
$myAudits = [];

try {
    if ($isOperator || $isUser) {
        // 1. Toplam Firma
        $stats['company_count'] = $db->query("SELECT count(*) FROM company")->fetchColumn();

        // 2. Aktif Belgeler (Status ID 1 = Aktif)
        $stats['active_cert_count'] = $db->query("SELECT count(*) FROM certification WHERE status = 1")->fetchColumn();

        // 3. Yaklaşan Denetimler (Gelecek 30 Gün)
        $stats['upcoming_audit_count'] = $db->query("SELECT count(*) FROM planning WHERE audit_publish_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND audit_status != 'İptal'")->fetchColumn();

        // 4. Süresi Dolanlar
        $stats['expired_cert_count'] = $db->query("SELECT count(*) FROM certification WHERE end_date < CURDATE()")->fetchColumn();

        // 5. Kullanıcı Sayısı
        if ($isOperator) {
            $stats['user_count'] = $db->query("SELECT count(*) FROM user")->fetchColumn();
        }

        // 6. Son Eklenen 5 Belge
        $sqlRecent = "
            SELECT 
                c.certno, 
                c.end_date, 
                comp.c_name as company_name, 
                ct.name as cert_type,
                st.status as status_name
            FROM certification c
            LEFT JOIN company comp ON c.f_company_id = comp.id
            LEFT JOIN cert ct ON c.f_cert_id = ct.id
            LEFT JOIN certification_status st ON c.status = st.id
            ORDER BY c.id DESC 
            LIMIT 5
        ";
        $recentCerts = $db->query($sqlRecent)->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- DENETÇİ İÇİN VERİLER ---
    if ($isAuditor) {
        // Denetçinin kendi planları (Gelecektekiler)
        // Veritabanında 'auditor' sütunu string isim tuttuğu için LIKE ile arıyoruz
        $sqlAuditor = "SELECT * FROM planning WHERE auditor LIKE ? AND audit_publish_date >= CURDATE() ORDER BY audit_publish_date ASC";
        $stmtAud = $db->prepare($sqlAuditor);
        $stmtAud->execute(["%" . $fullName . "%"]);
        $myAudits = $stmtAud->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    // Hata durumunda sessiz kal veya logla
    error_log("Dashboard Hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Kontrol Paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        /* Modern Renk Paleti ve Stil Temeli */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #e9ecef;
            --success-color: #28a745;
            --warning-color: #ffc107;
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

        .dashboard-container { display: flex; min-height: 100vh; }
        
        /* Sidebar - Yan Menü */
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 20px 0;
            box-shadow: 2px 0 6px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8rem;
            font-weight: 500;
            color: #fff;
            padding: 0 15px 15px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: #bdc3c7;
            padding: 12px 25px;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
            border-left-color: var(--primary-color);
        }
        .sidebar-menu a i { width: 25px; margin-right: 10px; text-align: center; }

        /* Main Content */
        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 20px;
        }
        .header-title { font-size: 2rem; font-weight: 300; color: var(--primary-color); margin: 0; }
        .user-info {
            font-size: 0.95rem;
            color: var(--secondary-color);
            text-align: right;
            background: var(--card-background);
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        /* Dashboard Kartları */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .dashboard-card {
            background-color: var(--card-background);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
            border-left: 5px solid var(--secondary-color);
        }
        .dashboard-card:hover { transform: translateY(-5px); }
        .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 10px; color: var(--secondary-color); text-transform: uppercase; letter-spacing: 0.5px; }
        .card-value { font-size: 2.2rem; font-weight: 700; color: #343a40; margin-bottom: 5px; }
        .card-text { font-size: 0.85rem; color: #868e96; }

        /* Tablolar */
        .table-section {
            background-color: var(--card-background);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { text-align: left; padding: 12px 15px; border-bottom: 1px solid var(--border-color); }
        .data-table th { background-color: var(--background-color); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--secondary-color); }
        .data-table tbody tr:hover { background-color: #f8f9fa; }

        .auditor-welcome {
            background-color: #e8f4fd;
            border: 1px solid #b6e0fe;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        /* Durum Badge */
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .badge-success { background-color: #e8f5e9; color: #2e7d32; }
        .badge-warning { background-color: #fff3e0; color: #ef6c00; }
        .badge-danger { background-color: #ffebee; color: #c62828; }
        .badge-info { background-color: #e3f2fd; color: #1565c0; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-logo">
                CERTBY
            </div>
            <nav class="sidebar-menu">
                <a href="dashboard.php" class="active">
                    <i class="fa fa-home"></i> <span>Ana Sayfa</span>
                </a>

                <?php if ($isOperator || $isUser): ?>
                    <a href="company.php">
                        <i class="fa fa-building"></i> <span>Firma Yönetimi</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator): ?>
                    <a href="cert.php">
                        <i class="fa fa-file-alt"></i> <span>Belge Yönetimi</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator || $isUser): ?>
                    <a href="certification.php">
                        <i class="fa fa-certificate"></i> <span>Belgelendirme Yönetimi</span>
                    </a>
                <?php endif; ?>

                <a href="audit.php">
                    <i class="fa fa-clipboard-check"></i> <span>Denetim Planlama</span>
                </a>

                <?php if ($isOperator || $isUser): ?>
                    <a href="report.php">
                        <i class="fa fa-chart-bar"></i> <span>Raporlar</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator || $isUser): ?>
                    <a href="email_template.php">
                        <i class="fa fa-envelope-open-text"></i> <span>E-posta Şablonları</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator || $isUser): ?>
                    <a href="log.php">
                        <i class="fa fa-history"></i> <span>Log</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator || $isUser): ?>
                    <a href="email_log.php">
                        <i class="fa fa-history"></i> <span>E-Posta Log</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator): ?>
                    <a href="user.php">
                        <i class="fa fa-users-cog"></i> <span>Kullanıcı Yönetimi</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator): ?>
                    <a href="consultant.php">
                        <i class="fa fa-handshake"></i> <span>Danışman Firmalar</span>
                    </a>
                <?php endif; ?>

                <?php if ($isOperator): ?>
                    <a href="settings.php">
                        <i class="fa fa-sliders-h"></i> <span>Ayarlar</span>
                    </a>
                <?php endif; ?>

                <a href="logout.php" style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1);">
                    <i class="fa fa-sign-out-alt"></i> <span>Güvenli Çıkış</span>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1 class="header-title">Kontrol Paneli</h1>
                <div class="user-info">
                    <i class="fa fa-user-circle"></i> &nbsp;
                    <?php echo htmlspecialchars($fullName); ?> <br>
                    <small style="color: var(--primary-color); font-weight: 600;">
                        <?php echo $displayRole; ?>
                    </small>
                </div>
            </header>

            <?php if ($isAuditor): ?>
                <div class="auditor-welcome">
                    <h3><i class="fa fa-info-circle"></i> Denetçi Paneline Hoşgeldiniz</h3>
                    <p>Sayın <strong><?php echo htmlspecialchars($fullName); ?></strong>, bu ekranda sadece size atanmış yaklaşan denetim planlarını görüntüleyebilirsiniz.</p>
                    <p>Tüm planlarınızı yönetmek için soldaki menüden <strong>"Denetim Planlama"</strong> sayfasına gidiniz.</p>
                </div>
                
                <div class="table-section">
                    <h2 class="table-title">Yaklaşan Denetimlerim</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Firma</th>
                                <th>Tarih</th>
                                <th>Belge</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($myAudits)): ?>
                                <?php foreach ($myAudits as $audit): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($audit['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($audit['audit_publish_date']); ?></td>
                                        <td><?php echo htmlspecialchars($audit['cert_type']); ?></td>
                                        <td>
                                            <?php 
                                                $st = $audit['audit_status'];
                                                $badge = 'badge-info';
                                                if($st == 'Gerçekleşti') $badge = 'badge-success';
                                                if($st == 'İptal') $badge = 'badge-danger';
                                                echo "<span class='badge $badge'>".htmlspecialchars($st)."</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; color:#999;">Henüz yaklaşan bir denetim planınız bulunmuyor.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="dashboard-grid">
                    <div class="dashboard-card" style="border-left-color: var(--primary-color);">
                        <div class="card-title">Toplam Firma</div>
                        <div class="card-value"><?php echo $stats['company_count']; ?></div>
                        <div class="card-text">Sistemdeki aktif müşteri sayısı</div>
                    </div>

                    <div class="dashboard-card" style="border-left-color: var(--success-color);">
                        <div class="card-title">Aktif Belgeler</div>
                        <div class="card-value"><?php echo $stats['active_cert_count']; ?></div>
                        <div class="card-text">Geçerliliği devam eden sertifikalar</div>
                    </div>

                    <div class="dashboard-card" style="border-left-color: var(--warning-color);">
                        <div class="card-title">Yaklaşan Denetimler</div>
                        <div class="card-value"><?php echo $stats['upcoming_audit_count']; ?></div>
                        <div class="card-text">Önümüzdeki 30 gün içindeki planlamalar</div>
                    </div>

                    <div class="dashboard-card" style="border-left-color: var(--danger-color);">
                        <div class="card-title">Süresi Dolanlar</div>
                        <div class="card-value"><?php echo $stats['expired_cert_count']; ?></div>
                        <div class="card-text">Yenileme süresi geçmiş belgeler</div>
                    </div>
                </div>

                <?php if ($isOperator): ?>
                <div class="dashboard-grid" style="margin-top: -20px;">
                    <div class="dashboard-card" style="border-left-color: #6f42c1;">
                        <div class="card-title">Sistem Kullanıcıları</div>
                        <div class="card-value"><?php echo $stats['user_count']; ?></div>
                        <div class="card-text">Kayıtlı personel sayısı</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-section">
                    <h2 class="table-title">Son Eklenen Belgeler</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Firma Adı</th>
                                <th>Belge Türü</th>
                                <th>Belge No</th>
                                <th>Bitiş Tarihi</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentCerts)): ?>
                                <?php foreach ($recentCerts as $cert): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cert['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cert['cert_type']); ?></td>
                                        <td><?php echo htmlspecialchars($cert['certno']); ?></td>
                                        <td><?php echo htmlspecialchars($cert['end_date']); ?></td>
                                        <td>
                                            <?php 
                                                $st = $cert['status_name'];
                                                $badge = 'badge-info';
                                                if($st == 'Aktif') $badge = 'badge-success';
                                                if($st == 'Pasif' || $st == 'İptal') $badge = 'badge-danger';
                                                if($st == 'Askıda') $badge = 'badge-warning';
                                                echo "<span class='badge $badge'>".htmlspecialchars($st)."</span>";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; color:#999;">Henüz belge kaydı yok.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </main>
    </div>

</body>
</html>