<?php
session_start();

// --- 1. GÜVENLİK ve YETKİ KONTROLÜ ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// Rol Kontrolü
$currentUserRole = strtolower($_SESSION['role_code']);

if ($currentUserRole !== 'operator') {
    header("Location: dashboard.php?error=yetkisiz_erisim");
    exit();
}

// --- 2. BACKEND İŞLEMLERİ (API) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $certId = intval($_POST['id'] ?? 0);

        // --- FİLTRELEME İŞLEMİ (AJAX - POST) ---
        if ($action === 'filter_certs') {
            $f_period = intval($_POST['period'] ?? 0);
            
            // Veritabanı yapısına tam uygun sorgu (JOIN YOK)
            $sql = "SELECT * FROM cert WHERE 1=1";
            
            $params = [];
            if ($f_period > 0) {
                $sql .= " AND period = ?";
                $params[] = $f_period;
            }
            
            $sql .= " ORDER BY name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }

        // --- SİLME İŞLEMİ ---
        if ($action === 'delete') {
            $stmtCheck = $db->prepare("SELECT count(*) FROM certification WHERE f_cert_id = ?");
            $stmtCheck->execute([$certId]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Bu belge türüne ait verilmiş sertifikalar var. Silinemez.']);
                exit();
            }

            $stmt = $db->prepare("DELETE FROM cert WHERE id = ?");
            if ($stmt->execute([$certId])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, ?, 'Belge Yönetim Logu', ?)");
                $logStmt->execute([$_SESSION['user_id'], $certId, "Belge türü silindi. ID: $certId"]);
                echo json_encode(['status' => 'success', 'message' => 'Belge türü silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Silme başarısız.']);
            }
            exit();
        }

        // --- EKLEME / GÜNCELLEME VERİLERİ ---
        if (in_array($action, ['add', 'update'])) {
            $name = trim($_POST['name'] ?? '');
            $standard = trim($_POST['standard'] ?? ''); // TEXT INPUT
            $period = intval($_POST['period'] ?? 0);
            $surveillance_count = intval($_POST['surveillance_count'] ?? 0);
            $surveillance_frequency = intval($_POST['surveillance_frequency'] ?? 12);

            if (empty($name) || empty($standard) || $period <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Lütfen zorunlu alanları doldurun.']);
                exit();
            }

            // EKLEME
            if ($action === 'add') {
                $stmtCheck = $db->prepare("SELECT count(*) FROM cert WHERE name = ?");
                $stmtCheck->execute([$name]);
                if ($stmtCheck->fetchColumn() > 0) {
                     echo json_encode(['status' => 'error', 'message' => 'Bu belge adı zaten mevcut.']); exit;
                }

                $sql = "INSERT INTO cert (name, standard, period, surveillance_count, surveillance_frequency) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$name, $standard, $period, $surveillance_count, $surveillance_frequency])) {
                    $newId = $db->lastInsertId();
                    $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, ?, 'Belge Yönetim Logu', ?)");
                    $logStmt->execute([$_SESSION['user_id'], $newId, "Yeni belge türü eklendi: $name"]);
                    echo json_encode(['status' => 'success', 'message' => 'Belge türü oluşturuldu.']);
                }
                exit();
            }

            // GÜNCELLEME
            if ($action === 'update') {
                $sql = "UPDATE cert SET name=?, standard=?, period=?, surveillance_count=?, surveillance_frequency=? WHERE id=?";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$name, $standard, $period, $surveillance_count, $surveillance_frequency, $certId])) {
                    $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, ?, 'Belge Yönetim Logu', ?)");
                    $logStmt->execute([$_SESSION['user_id'], $certId, "Belge türü güncellendi. ID: $certId"]);
                    echo json_encode(['status' => 'success', 'message' => 'Güncelleme başarılı.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Güncelleme başarısız.']);
                }
                exit();
            }
        }
        
        // --- TEKİL VERİ ÇEKME ---
        if ($action === 'get_cert') {
            $stmt = $db->prepare("SELECT * FROM cert WHERE id = ?");
            $stmt->execute([$certId]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
        exit();
    }
}

// --- 3. İLK YÜKLEME VERİLERİ (GET) ---
try {
    // URL'den gelen filtre varsa al (Sayfa yenilendiğinde filtreyi korumak için)
    $f_period = intval($_GET['period'] ?? 0);

    // Ana SQL
    $sql = "SELECT * FROM cert WHERE 1=1";
    $params = [];
    
    // Eğer sayfaya link ile gelindiyse filtreyi uygula
    if ($f_period > 0) {
        $sql .= " AND period = ?";
        $params[] = $f_period;
    }
    
    $sql .= " ORDER BY name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $certs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $error = "Hata: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Belge Yönetimi</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        /* ORTAK STİLLER */
        :root {
            --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa;
            --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545;
            --dropdown-hover: #f1f3f5; --dropdown-shadow: rgba(0,0,0,0.1);
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .add-button { padding: 12px 25px; font-size: 1rem; font-weight: 500; color: #fff; background-color: var(--primary-color); border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .add-button:hover { background-color: #0056b3; }
        
        /* Buton Stilleri */
        .action-buttons-wrapper { display: flex; gap: 5px; justify-content: flex-end; }
        .action-button { 
            padding: 8px 15px; 
            font-size: 0.85rem; 
            font-weight: 500; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            color: #fff; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
        }
        .action-button.update { background-color: var(--primary-color); }
        .action-button.update:hover { background-color: #0056b3; }
        .action-button.delete { background-color: var(--danger-color); }
        .action-button.delete:hover { background-color: #c82333; }
        
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        
        .search-input-dt { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }

        /* FİLTRE ALANI */
        .filter-section { 
            background: #f1f3f5; padding: 20px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #e9ecef; 
            display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; justify-content: flex-start;
        }
        .filter-group { display: flex; flex-direction: column; width: 250px; }
        .filter-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; color: #555; }
        
        .btn-filter { background-color: var(--primary-color); color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: 500; transition: 0.2s; height: 42px; display: flex; align-items: center; gap: 8px; }
        .btn-filter:hover { background-color: #0056b3; }
        .btn-reset { background-color: var(--secondary-color); color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: 500; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-reset:hover { background-color: #5a6268; }

        /* DataTables */
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_filter input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-left: 10px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }
        
        /* MODAL & DROPDOWN */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 5% auto; padding: 30px; border-radius: 8px; width: 500px; max-width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        @keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        label.required::after { content: " *"; color: var(--danger-color); }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; }
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px var(--dropdown-shadow); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content input { box-sizing: border-box; width: 95%; margin: 5px 2.5%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.95rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        .arrow-down { font-size: 0.8rem; color: #666; }
        
        .modal-footer { text-align: right; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">Belge Yönetimi</h1>
        <button class="add-button" onclick="openModal('add')"><i class="fa fa-plus"></i> Yeni Belge Tanımla</button>
    </div>

    <form id="filterForm" class="filter-section">
        <div class="filter-group">
            <label>Periyot</label>
            <div class="dropdown">
                <input type="hidden" name="period" id="f_period" value="<?php echo $f_period > 0 ? $f_period : ''; ?>">
                <button type="button" id="f_periodBtn" onclick="toggleDropdown('periodFilterDropdown')" class="dropbtn" style="width:100%">
                    <?php echo $f_period > 0 ? $f_period . ' Yıl' : 'Tümü'; ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="periodFilterDropdown" class="dropdown-content">
                    <input type="text" placeholder="Yıl Ara..." onkeyup="filterFunction('periodFilterDropdown')">
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_period', '', 'Tümü', 'periodFilterDropdown')">Tümü</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_period', '1', '1 Yıl', 'periodFilterDropdown')">1 Yıl</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_period', '2', '2 Yıl', 'periodFilterDropdown')">2 Yıl</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_period', '3', '3 Yıl', 'periodFilterDropdown')">3 Yıl</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_period', '4', '4 Yıl', 'periodFilterDropdown')">4 Yıl</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_period', '5', '5 Yıl', 'periodFilterDropdown')">5 Yıl</a>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="button" class="btn-filter" onclick="applyFilters(true)"><i class="fa fa-filter"></i> Uygula</button>
            <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fa fa-times"></i> Temizle</button>
        </div>
    </form>

    <input type="text" id="customSearch" class="search-input-dt" placeholder="Tabloda ara...">

    <div class="table-responsive">
        <table id="certTable">
            <thead>
                <tr>
                    <th>Belge Adı</th>
                    <th>Standart</th> 
                    <th>Periyot (Yıl)</th>
                    <th>Ara Tetkik Sayısı</th>
                    <th>Ara Tetkik Sıklığı</th>
                    <th style="width: 150px; text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($certs)): ?>
                    <?php foreach ($certs as $cert): ?>
                        <tr id="row-<?php echo $cert['id']; ?>">
                            <td><?php echo htmlspecialchars($cert['name']); ?></td>
                            <td><?php echo htmlspecialchars($cert['standard'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($cert['period']); ?> Yıl</td>
                            <td><?php echo htmlspecialchars($cert['surveillance_count']); ?></td>
                            <td><?php echo htmlspecialchars($cert['surveillance_frequency']); ?> Ay</td>
                            <td style="text-align: right;">
                                <div class="action-buttons-wrapper">
                                    <button class="action-button update" onclick="openModal('update', <?php echo $cert['id']; ?>)">Güncelle</button>
                                    <button class="action-button delete" onclick="deleteCert(<?php echo $cert['id']; ?>)">Sil</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="certModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle" style="margin:0; color:var(--primary-color); font-weight:300;">Yeni Belge Tanımı</h2>
            <span class="close-button" onclick="closeModal()">&times;</span>
        </div>
        <form id="certForm" onsubmit="submitForm(event)">
            <input type="hidden" id="action" name="action">
            <input type="hidden" id="certId" name="id">

            <div class="form-group">
                <label for="name" class="required">Belge Adı (Örn: BGYS)</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="standard" class="required">Belge Standardı (Örn: ISO 27001)</label>
                <input type="text" id="standard" name="standard" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="required">Belgelendirme Periyodu (Yıl)</label>
                <div class="dropdown">
                    <input type="hidden" id="period" name="period" required onchange="updateSurveillanceOptions()">
                    <button type="button" id="periodBtn" onclick="toggleDropdown('periodDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                    <div id="periodDropdown" class="dropdown-content">
                        <input type="text" placeholder="Yıl Ara..." onkeyup="filterFunction('periodDropdown')">
                        <a href="javascript:void(0)" onclick="selectOption('period', '1', '1 Yıl')">1 Yıl</a>
                        <a href="javascript:void(0)" onclick="selectOption('period', '2', '2 Yıl')">2 Yıl</a>
                        <a href="javascript:void(0)" onclick="selectOption('period', '3', '3 Yıl')">3 Yıl</a>
                        <a href="javascript:void(0)" onclick="selectOption('period', '4', '4 Yıl')">4 Yıl</a>
                        <a href="javascript:void(0)" onclick="selectOption('period', '5', '5 Yıl')">5 Yıl</a>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="required">Ara Tetkik Sayısı</label>
                <div class="dropdown">
                    <input type="hidden" id="surveillance_count" name="surveillance_count" required onchange="calculateFrequency()">
                    <button type="button" id="surveillance_countBtn" onclick="toggleDropdown('surveillanceCountDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                    <div id="surveillanceCountDropdown" class="dropdown-content">
                        <a href="javascript:void(0)" style="color:#999; cursor:default;">Önce Periyot Seçiniz</a>
                    </div>
                </div>
                <small style="color:#666;">Toplam denetim sayısı (Senede en fazla 1 kez).</small>
            </div>

            <div class="form-group">
                <label class="required">Ara Tetkik Sıklığı (Ay)</label>
                <input type="text" id="surveillance_frequency" name="surveillance_frequency" class="form-control" value="12" readonly style="background-color: #e9ecef; cursor: not-allowed;">
                <small style="color:#666;">Periyot ve tetkik sayısına göre otomatik hesaplanır.</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="action-button" style="background-color:#6c757d;" onclick="closeModal()">İptal</button>
                <button type="submit" class="action-button update">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    let dataTable;

    $(document).ready(function() {
        // 1. Initial State Handling (Sayfa ilk yüklendiğinde)
        // URL'de bir filtre varsa (örneğin bookmark'tan gelindiyse)
        // PHP zaten tabloyu filtreli basıyor, ama Dropdown UI'ını güncellemeliyiz.
        const urlParams = new URLSearchParams(window.location.search);
        const period = urlParams.get('period');
        
        if (period) {
            document.getElementById('f_period').value = period;
            document.getElementById('f_periodBtn').innerHTML = period + ' Yıl <span class=\"arrow-down\">&#9662;</span>';
        }

        // DataTables Ayarları
        dataTable = $('#certTable').DataTable({
            "destroy": true, 
            "pageLength": 10, 
            "lengthMenu": [5, 10, 25, 50],
            "dom": 'rtip', 
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 5 } ]
        });

        $('#customSearch').on('keyup', function() {
            dataTable.search(this.value).draw();
        });
    });

    // --- POPSTATE EVENT LISTENER (BACK BUTTON SUPPORT) ---
    window.addEventListener('popstate', function(event) {
        // 1. URL'den parametreleri oku
        const urlParams = new URLSearchParams(window.location.search);
        const period = urlParams.get('period') || '';

        // 2. UI'ı güncelle
        document.getElementById('f_period').value = period;
        const btn = document.getElementById('f_periodBtn');
        if(period) btn.innerHTML = period + ' Yıl <span class="arrow-down">&#9662;</span>';
        else btn.innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';

        // 3. Tabloyu güncelle (False parametresi ile history'ye tekrar push yapma)
        applyFilters(false);
    });

    // --- FİLTRELEME FONKSİYONU (AJAX) ---
    function applyFilters(pushToHistory = true) {
        const period = document.getElementById('f_period').value;

        // URL Güncelleme
        const newUrl = new URL(window.location.href);
        if(period) newUrl.searchParams.set('period', period); else newUrl.searchParams.delete('period');

        if (pushToHistory) {
            // Kullanıcı butona bastı: Geçmişe ekle
            window.history.pushState({path: newUrl.href}, '', newUrl);
        } else {
            // Geri tuşu ile gelindi: Sadece URL'i güncelle (replaceState)
            window.history.replaceState(null, '', newUrl);
        }

        const fd = new FormData();
        fd.append('action', 'filter_certs');
        fd.append('period', period);

        fetch('cert.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                dataTable.clear();
                
                res.data.forEach(row => {
                    const buttons = `
                        <div class="action-buttons-wrapper">
                            <button class="action-button update" onclick="openModal('update', ${row.id})">Güncelle</button>
                            <button class="action-button delete" onclick="deleteCert(${row.id})">Sil</button>
                        </div>
                    `;
                    
                    dataTable.row.add([
                        row.name,
                        row.standard || '', 
                        row.period + ' Yıl',
                        row.surveillance_count,
                        (row.surveillance_frequency || 12) + ' Ay',
                        buttons
                    ]);
                });
                
                dataTable.draw();
            }
        })
        .catch(err => console.error('Filtre hatası:', err));
    }

    function resetFilters() {
        // Formu temizle
        document.getElementById('f_period').value = '';
        document.getElementById('f_periodBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        
        // URL'yi temizle ve geçmişe ekle
        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl);
        
        // Tabloyu sıfırla
        applyFilters(false);
    }

    // --- MEVCUT FONKSİYONLAR ---
    function toggleDropdown(id) { document.getElementById(id).classList.toggle("show"); }

    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn') && !event.target.matches('.dropdown-content input')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
        if (event.target == document.getElementById('certModal')) closeModal();
    }

    document.addEventListener('keydown', function(event) { if (event.key === "Escape") { closeModal(); } });

    function filterFunction(dropdownId) {
        var input = document.querySelector("#" + dropdownId + " input");
        if(!input) return; 
        var filter = input.value.toUpperCase();
        var div = document.getElementById(dropdownId);
        var a = div.getElementsByTagName("a");
        for (var i = 0; i < a.length; i++) {
            if ((a[i].textContent || a[i].innerText).toUpperCase().indexOf(filter) > -1) {
                a[i].style.display = "";
            } else {
                a[i].style.display = "none";
            }
        }
    }

    function selectFilterOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btnId = inputId + 'Btn'; 
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        document.getElementById(dropdownId).classList.remove('show');
    }

    function selectOption(inputId, value, text) {
        document.getElementById(inputId).value = value;
        document.getElementById(inputId + 'Btn').innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        
        const btn = document.getElementById(inputId + 'Btn');
        if(btn) {
            btn.closest('.dropdown').classList.remove('show');
        }
        
        if(inputId === 'period') {
            updateSurveillanceOptions();
        }
        if(inputId === 'surveillance_count') {
            calculateFrequency();
        }
    }

    function updateSurveillanceOptions(preSelectedCount = null) {
        const period = parseInt(document.getElementById('period').value) || 0;
        const dropdown = document.getElementById('surveillanceCountDropdown');
        dropdown.innerHTML = ''; 

        if (period > 0) {
            const maxCount = period - 1;
            for (let i = 0; i <= maxCount; i++) {
                const a = document.createElement('a');
                a.href = 'javascript:void(0)';
                a.innerHTML = i + ' Adet';
                a.onclick = function() { selectOption('surveillance_count', i, i + ' Adet'); };
                dropdown.appendChild(a);
            }
            let targetCount = (preSelectedCount !== null) ? preSelectedCount : maxCount;
            if(targetCount > maxCount) targetCount = maxCount;
            selectOption('surveillance_count', targetCount, targetCount + ' Adet'); 
        } else {
            const a = document.createElement('a');
            a.innerHTML = 'Önce Periyot Seçiniz';
            a.style.color = '#999';
            dropdown.appendChild(a);
            selectOption('surveillance_count', 0, 'Seçiniz...');
        }
        calculateFrequency(); 
    }

    function calculateFrequency() {
        const periodYears = parseInt(document.getElementById('period').value) || 0;
        const count = parseInt(document.getElementById('surveillance_count').value) || 0;
        const freqInput = document.getElementById('surveillance_frequency');

        if (periodYears > 0 && count > 0) {
            const totalMonths = periodYears * 12;
            const intervals = count + 1;
            const frequency = Math.round(totalMonths / intervals);
            freqInput.value = frequency;
        } else if (periodYears > 0 && count === 0) {
            freqInput.value = periodYears * 12;
        } else {
            freqInput.value = 0;
        }
    }

    function openModal(type, id = 0) {
        const modal = document.getElementById('certModal');
        const form = document.getElementById('certForm');
        const title = document.getElementById('modalTitle');
        
        form.reset();
        document.getElementById('action').value = type;
        document.getElementById('certId').value = id;
        
        document.getElementById('periodBtn').innerHTML = 'Seçiniz... <span class="arrow-down">&#9662;</span>';
        document.getElementById('surveillance_countBtn').innerHTML = 'Seçiniz... <span class="arrow-down">&#9662;</span>';
        
        document.getElementById('standard').value = '';
        document.getElementById('period').value = '';
        document.getElementById('surveillance_count').value = '';
        document.getElementById('surveillance_frequency').value = '';

        if (type === 'add') {
            title.textContent = 'Yeni Belge Tanımı';
            updateSurveillanceOptions(); 
        } else {
            title.textContent = 'Belge Tanımı Güncelle';
            const fd = new FormData();
            fd.append('action', 'get_cert');
            fd.append('id', id);

            fetch('cert.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('name').value = data.name;
                    document.getElementById('standard').value = data.standard;
                    
                    const periodVal = parseInt(data.period);
                    selectOption('period', periodVal, periodVal + ' Yıl');
                    updateSurveillanceOptions(parseInt(data.surveillance_count));
                });
        }
        modal.style.display = 'block';
    }

    function closeModal() { document.getElementById('certModal').style.display = 'none'; }

    function submitForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        fetch('cert.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                closeModal();
                applyFilters(false);
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(err => { console.error(err); alert('Bir hata oluştu.'); });
    }

    function deleteCert(id) {
        if (confirm("Bu belge türünü silmek istediğinize emin misiniz?")) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('cert.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    applyFilters(false);
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(err => console.error(err));
        }
    }
</script>

</body>
</html>