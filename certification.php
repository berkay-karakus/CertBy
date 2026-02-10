<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// --- YETKİ KONTROLÜ ---
$userRole = strtolower($_SESSION['role_code']);

// 1. Denetçi (Auditor) bu sayfayı GÖREMEZ
if ($userRole === 'auditor') {
    header("Location: dashboard.php");
    exit();
}

// 2. Yönetim Yetkisi Kimde? (Operatör VE Kullanıcı)
$canManage = ($userRole === 'operator' || $userRole === 'user');

// ==========================================================================
//  OTOMATİK DURUM GÜNCELLEME MOTORU (AUTO-STATUS ENGINE)
//  Dökümandaki "Ara tetkik tarihi geçmiş ve planlanmamışsa PASİF yap" kuralı
// ==========================================================================
function updateCertificationStatuses($db) {
    // 1. Sadece 'Aktif' (ID: 1) olan sertifikaları çek
    $sql = "SELECT c.id, c.certno, c.publish_date, c.end_date, ct.surveillance_frequency, ct.period 
            FROM certification c
            JOIN cert ct ON c.f_cert_id = ct.id
            WHERE c.status = 1"; // Sadece Aktifler
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $certs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $today = new DateTime();

    foreach ($certs as $cert) {
        $pubDate = new DateTime($cert['publish_date']);
        $freq = intval($cert['surveillance_frequency']); // Örn: 12 Ay
        
        // Ara Tetkik Tarihlerini Hesapla (1. ve 2. Gözetim)
        $surv1 = (clone $pubDate)->modify("+$freq months");
        $surv2 = (clone $pubDate)->modify("+" . ($freq * 2) . " months");
        
        $checkDate = null;

        // Şu anki tarih hangi aralıkta? Hangi tetkik kaçmış olabilir?
        if ($today > $surv1 && $today < $surv2) {
            // 1. Ara tetkik tarihi geçmiş, 2. henüz gelmemiş
            $checkDate = $surv1;
        } elseif ($today > $surv2) {
            // 2. Ara tetkik tarihi de geçmiş
            $checkDate = $surv2;
        }

        // Eğer kritik bir tarih geçilmişse PLAN KONTROLÜ yap
        if ($checkDate) {
            // Planning tablosuna bak: Bu sertifika no ile İPTAL OLMAYAN bir plan var mı?
            // Not: Tarih toleransı koymuyoruz, sadece plan var mı diye bakıyoruz.
            $stmtPlan = $db->prepare("SELECT count(*) FROM planning WHERE audit_certtification_no = ? AND audit_status != 'İptal'");
            $stmtPlan->execute([$cert['certno']]);
            $hasPlan = $stmtPlan->fetchColumn();

            if ($hasPlan == 0) {
                // TARİH GEÇMİŞ VE PLAN YOK -> PASİF (ID: 2) YAP
                $updateStmt = $db->prepare("UPDATE certification SET status = 2 WHERE id = ?");
                $updateStmt->execute([$cert['id']]);
                
                // Logla
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, ?, 'Sistem Otomasyonu', ?)");
                $logStmt->execute([$_SESSION['user_id'], $cert['id'], "Sertifika otomatik olarak PASİF'e çekildi (Ara tetkik zamanı geçti ve plan yok)."]);
            }
        }
    }
}

// Sayfa her yüklendiğinde veya API çağrıldığında statüleri tazele
updateCertificationStatuses($db);

// ==========================================================================
//  BACKEND İŞLEMLERİ (CRUD)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Yetkisiz işlem kontrolü
    if (!$canManage && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update', 'delete'])) {
        echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
        exit();
    }
    
    try {
        $action = $_POST['action'] ?? '';

        // --- 1. FİLTRELEME (AJAX) ---
        if ($action === 'filter_certifications') {
            $f_company_id = intval($_POST['company_id'] ?? 0);
            $f_cert_id = intval($_POST['cert_id'] ?? 0);
            $f_status = intval($_POST['status'] ?? 0);

            $sql = "SELECT 
                        c.id, 
                        comp.c_name as company_name, 
                        ct.name as cert_name, 
                        c.certno, 
                        c.end_date, 
                        st.status as status_name
                    FROM certification c
                    LEFT JOIN company comp ON c.f_company_id = comp.id
                    LEFT JOIN cert ct ON c.f_cert_id = ct.id
                    LEFT JOIN certification_status st ON c.status = st.id
                    WHERE 1=1";
            
            $params = [];
            if ($f_company_id > 0) { $sql .= " AND c.f_company_id = ?"; $params[] = $f_company_id; }
            if ($f_cert_id > 0) { $sql .= " AND c.f_cert_id = ?"; $params[] = $f_cert_id; }
            if ($f_status > 0) { $sql .= " AND c.status = ?"; $params[] = $f_status; }
            
            $sql .= " ORDER BY c.id DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }

        // --- TEKİL VERİ ÇEKME (GET) ---
        if ($action === 'get_certification') {
            $id = intval($_POST['id'] ?? 0);
            
            // Cert tablosundan period bilgisini de çekiyoruz (Join ile)
            $sql = "SELECT c.*, ct.period as cert_period 
                    FROM certification c 
                    LEFT JOIN cert ct ON c.f_cert_id = ct.id 
                    WHERE c.id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            $cert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cert) {
                echo json_encode(['status' => 'success', 'data' => $cert]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt bulunamadı.']);
            }
            exit();
        }

        // --- SİLME (DELETE) ---
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM certification WHERE id = ?");
            if ($stmt->execute([$id])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, ?, 'Belgelendirme Logu', ?)");
                $logStmt->execute([$_SESSION['user_id'], $id, "Sertifika silindi. ID: $id"]);
                echo json_encode(['status' => 'success', 'message' => 'Sertifika silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Silme başarısız.']);
            }
            exit();
        }

        // --- EKLEME / GÜNCELLEME ---
        if (in_array($action, ['add', 'update'])) {
            $f_company_id = intval($_POST['f_company_id'] ?? 0);
            $f_cert_id = intval($_POST['f_cert_id'] ?? 0);
            $certno = trim($_POST['certno'] ?? '');
            $scope = trim($_POST['scope'] ?? '');
            $publish_date = $_POST['publish_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $level = !empty($_POST['level']) ? intval($_POST['level']) : null;
            $consult_company_id = !empty($_POST['consult_company_id']) ? intval($_POST['consult_company_id']) : null;
            $status = intval($_POST['status'] ?? 1); 
            $accreditor = !empty($_POST['accreditor']) ? intval($_POST['accreditor']) : null;

            if ($f_company_id <= 0 || $f_cert_id <= 0 || empty($certno) || empty($scope) || empty($publish_date) || empty($end_date)) {
                echo json_encode(['status' => 'error', 'message' => 'Lütfen zorunlu alanları doldurun.']);
                exit();
            }

            // EKLEME
            if ($action === 'add') {
                $stmtCheck = $db->prepare("SELECT count(*) FROM certification WHERE certno = ?");
                $stmtCheck->execute([$certno]);
                if ($stmtCheck->fetchColumn() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu belge numarası zaten kullanılıyor.']); exit();
                }

                $sql = "INSERT INTO certification (f_company_id, f_cert_id, certno, scope, publish_date, end_date, level, consult_company_id, status, accreditor) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$f_company_id, $f_cert_id, $certno, $scope, $publish_date, $end_date, $level, $consult_company_id, $status, $accreditor])) {
                    $newId = $db->lastInsertId();
                    $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, ?, ?, 'Belgelendirme Logu', ?)");
                    $logStmt->execute([$_SESSION['user_id'], $f_company_id, $newId, "Yeni sertifika oluşturuldu: $certno"]);

                    echo json_encode(['status' => 'success', 'message' => 'Sertifika başarıyla oluşturuldu.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Kayıt hatası.']);
                }
                exit();
            }

            // GÜNCELLEME
            if ($action === 'update') {
                $id = intval($_POST['id'] ?? 0);
                $stmtCheck = $db->prepare("SELECT count(*) FROM certification WHERE certno = ? AND id != ?");
                $stmtCheck->execute([$certno, $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu belge numarası başka bir kayıtta kullanılıyor.']); exit();
                }

                $sql = "UPDATE certification SET f_company_id=?, f_cert_id=?, certno=?, scope=?, publish_date=?, end_date=?, level=?, consult_company_id=?, status=?, accreditor=? WHERE id=?";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$f_company_id, $f_cert_id, $certno, $scope, $publish_date, $end_date, $level, $consult_company_id, $status, $accreditor, $id])) {
                    $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, ?, ?, 'Belgelendirme Logu', ?)");
                    $logStmt->execute([$_SESSION['user_id'], $f_company_id, $id, "Sertifika güncellendi: $certno"]);
                    echo json_encode(['status' => 'success', 'message' => 'Sertifika güncellendi.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Güncelleme hatası.']);
                }
                exit();
            }
        }

    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit(); }
}

// --- FİLTRE DEĞİŞKENLERİ (İlk Yükleme) ---
$f_company_id = intval($_GET['company_id'] ?? 0);
$f_cert_id = intval($_GET['cert_id'] ?? 0);
$f_status = intval($_GET['status'] ?? 0);

// --- SAYFA VERİLERİ ---
try {
    $companies = $db->query("SELECT id, c_name FROM company ORDER BY c_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $certTypes = $db->query("SELECT id, name, period FROM cert ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $consultants = $db->query("SELECT id, c_name FROM consult_company ORDER BY c_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $accreditors = $db->query("SELECT id, code FROM acreditor ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $statuses = $db->query("SELECT id, status FROM certification_status ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // LİSTELEME SORGUSU (FİLTRELİ)
    $sqlList = "SELECT 
                    c.id, 
                    comp.c_name as company_name, 
                    ct.name as cert_name, 
                    c.certno, 
                    c.end_date, 
                    st.status as status_name
                FROM certification c
                LEFT JOIN company comp ON c.f_company_id = comp.id
                LEFT JOIN cert ct ON c.f_cert_id = ct.id
                LEFT JOIN certification_status st ON c.status = st.id
                WHERE 1=1";
                
    $params = [];

    if ($f_company_id > 0) {
        $sqlList .= " AND c.f_company_id = ?";
        $params[] = $f_company_id;
    }

    if ($f_cert_id > 0) {
        $sqlList .= " AND c.f_cert_id = ?";
        $params[] = $f_cert_id;
    }

    if ($f_status > 0) {
        $sqlList .= " AND c.status = ?";
        $params[] = $f_status;
    }

    $sqlList .= " ORDER BY c.id DESC";
    
    $stmt = $db->prepare($sqlList);
    $stmt->execute($params);
    $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $error = $e->getMessage(); }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Belgelendirme Yönetimi</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* ORTAK STİLLER */
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545; --dropdown-hover: #f1f3f5; --dropdown-shadow: rgba(0,0,0,0.1); }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .add-button { padding: 12px 25px; font-size: 1rem; font-weight: 500; color: #fff; background-color: var(--primary-color); border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .action-button { padding: 6px 12px; font-size: 0.85rem; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; color: #fff; margin-right: 5px; }
        .update { background-color: var(--primary-color); }
        .delete { background-color: var(--danger-color); }
        .search-input { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_filter input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-left: 10px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }

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

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 5% auto; padding: 30px; border-radius: 8px; width: 500px; max-width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        @keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        
        .form-grid-modal { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-field { margin-bottom: 15px; }
        .form-field label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        .required { color: var(--danger-color); }
        .form-field input, .form-field select, .form-field textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; }
        
        /* DROPDOWN */
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px var(--dropdown-shadow); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content input { box-sizing: border-box; width: 95%; margin: 5px 2.5%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.95rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        
        .modal-footer { text-align: right; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; }
        .modal-button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; color: #fff; }
        .modal-button.cancel { background-color: var(--secondary-color); margin-right: 10px; }
        .modal-button.save { background-color: var(--success-color); }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">Belgelendirme Yönetimi</h1>
        <?php if ($canManage): ?>
            <button class="add-button" onclick="openModal('add')"><i class="fa fa-plus"></i> Yeni Sertifika Oluştur</button>
        <?php endif; ?>
    </div>

    <form id="filterForm" class="filter-section">
        
        <div class="filter-group">
            <label>Firma</label>
            <div class="dropdown">
                <input type="hidden" name="company_id" id="search_company_id" value="<?php echo $f_company_id > 0 ? $f_company_id : ''; ?>">
                <button type="button" id="search_companyBtn" onclick="toggleDropdown('search_companyFilterDropdown')" class="dropbtn" style="width:100%">
                    <?php 
                        $selectedComp = 'Tümü';
                        foreach($companies as $c){ if($c['id'] == $f_company_id) $selectedComp = htmlspecialchars($c['c_name']); }
                        echo $selectedComp;
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="search_companyFilterDropdown" class="dropdown-content">
                    <input type="text" placeholder="Ara..." onkeyup="filterDropdown('search_companyFilterDropdown')">
                    <a href="javascript:void(0)" onclick="selectFilterOption('search_company_id', '', 'Tümü', 'search_companyFilterDropdown')">Tümü</a>
                    <?php foreach($companies as $c): ?>
                        <a href="javascript:void(0)" onclick="selectFilterOption('search_company_id', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['c_name']); ?>', 'search_companyFilterDropdown')">
                            <?php echo htmlspecialchars($c['c_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="filter-group">
            <label>Belge Türü</label>
            <div class="dropdown">
                <input type="hidden" name="cert_id" id="search_cert_id" value="<?php echo $f_cert_id > 0 ? $f_cert_id : ''; ?>">
                <button type="button" id="search_certBtn" onclick="toggleDropdown('search_certFilterDropdown')" class="dropbtn" style="width:100%">
                     <?php 
                        $selectedCert = 'Tümü';
                        foreach($certTypes as $ct){ if($ct['id'] == $f_cert_id) $selectedCert = htmlspecialchars($ct['name']); }
                        echo $selectedCert;
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="search_certFilterDropdown" class="dropdown-content">
                    <input type="text" placeholder="Ara..." onkeyup="filterDropdown('search_certFilterDropdown')">
                    <a href="javascript:void(0)" onclick="selectFilterOption('search_cert_id', '', 'Tümü', 'search_certFilterDropdown')">Tümü</a>
                    <?php foreach($certTypes as $ct): ?>
                         <a href="javascript:void(0)" onclick="selectFilterOption('search_cert_id', '<?php echo $ct['id']; ?>', '<?php echo htmlspecialchars($ct['name']); ?>', 'search_certFilterDropdown')">
                            <?php echo htmlspecialchars($ct['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="filter-group">
            <label>Durum</label>
            <div class="dropdown">
                <input type="hidden" name="status" id="search_status" value="<?php echo $f_status > 0 ? $f_status : ''; ?>">
                <button type="button" id="search_statusBtn" onclick="toggleDropdown('search_statusFilterDropdown')" class="dropbtn" style="width:100%">
                    <?php 
                        $stLabel = 'Tümü';
                        foreach($statuses as $st) { if($st['id'] == $f_status) $stLabel = htmlspecialchars($st['status']); }
                        echo $stLabel;
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="search_statusFilterDropdown" class="dropdown-content">
                    <a href="javascript:void(0)" onclick="selectFilterOption('search_status', '', 'Tümü', 'search_statusFilterDropdown')">Tümü</a>
                    <?php foreach ($statuses as $st): ?>
                        <a href="javascript:void(0)" onclick="selectFilterOption('search_status', '<?php echo $st['id']; ?>', '<?php echo htmlspecialchars($st['status']); ?>', 'search_statusFilterDropdown')">
                            <?php echo htmlspecialchars($st['status']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="button" class="btn-filter" onclick="applyFilters(true)"><i class="fa fa-filter"></i> Uygula</button>
            <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fa fa-times"></i> Temizle</button>
        </div>
    </form>

    <input type="text" id="customSearch" class="search-input" placeholder="Firma, belge no ara...">

    <div class="table-responsive">
        <table id="certTable">
            <thead>
                <tr>
                    <th>Firma</th>
                    <th>Belge Türü</th>
                    <th>Belge No</th>
                    <th>Bitiş Tarihi</th>
                    <th>Durum</th>
                    <?php if ($canManage): ?> <th style="width: 150px;">İşlemler</th> <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($certifications)): ?>
                    <?php foreach ($certifications as $cert): ?>
                    <tr id="row-<?php echo $cert['id']; ?>">
                        <td><?php echo htmlspecialchars($cert['company_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($cert['cert_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($cert['certno'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($cert['end_date'] ?? ''); ?></td>
                        <td>
                            <?php 
                                $statusColor = 'gray';
                                $statusName = $cert['status_name'] ?? '';
                                if($statusName == 'Aktif') $statusColor = 'green';
                                if($statusName == 'Pasif' || $statusName == 'İptal') $statusColor = 'red';
                                if($statusName == 'Askıda') $statusColor = 'orange';
                                echo "<span style='color:$statusColor; font-weight:bold;'>".htmlspecialchars($statusName)."</span>";
                            ?>
                        </td>
                        <?php if ($canManage): ?>
                        <td>
                            <button class="action-button update" onclick="openModal('update', <?php echo $cert['id']; ?>)">Güncelle</button>
                            <button class="action-button delete" onclick="deleteCert(<?php echo $cert['id']; ?>)">Sil</button>
                        </td>
                        <?php endif; ?>
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
            <h2 class="modal-title" id="modalTitle"></h2>
            <span class="close-button" onclick="closeModal()">&times;</span>
        </div>
        <form id="certForm" onsubmit="submitForm(event)">
            <input type="hidden" id="action" name="action">
            <input type="hidden" id="certId" name="id">

            <div class="form-grid-modal">
                <div class="form-field">
                    <label class="required">Firma</label>
                    <div class="dropdown">
                        <input type="hidden" id="f_company_id" name="f_company_id">
                        <button type="button" id="companyBtn" onclick="toggleDropdown('companyDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="companyDropdown" class="dropdown-content">
                            <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('companyDropdown')">
                            <?php foreach ($companies as $c): ?>
                                <a href="javascript:void(0)" data-id="<?php echo $c['id']; ?>" onclick="selectOption('f_company_id', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['c_name']); ?>', 'companyDropdown')"><?php echo htmlspecialchars($c['c_name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label class="required">Belge Türü</label>
                    <div class="dropdown">
                        <input type="hidden" id="f_cert_id" name="f_cert_id" onchange="calculateEndDate()">
                        <button type="button" id="certBtn" onclick="toggleDropdown('certDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="certDropdown" class="dropdown-content">
                            <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('certDropdown')">
                            <?php foreach ($certTypes as $ct): ?>
                                <a href="javascript:void(0)" data-id="<?php echo $ct['id']; ?>" data-period="<?php echo $ct['period']; ?>" onclick="selectOption('f_cert_id', '<?php echo $ct['id']; ?>', '<?php echo htmlspecialchars($ct['name']); ?>', 'certDropdown')"><?php echo htmlspecialchars($ct['name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label class="required">Belge Numarası (Tekil)</label>
                    <input type="text" id="certno" name="certno" required placeholder="Örn: ISO-001">
                </div>
                <div class="form-field">
                    <label class="required">Kapsam</label>
                    <textarea id="scope" name="scope" rows="1" required></textarea>
                </div>

                <div class="form-field">
                    <label class="required">İlk Yayın Tarihi</label>
                    <input type="date" id="publish_date" name="publish_date" required onchange="calculateEndDate()">
                </div>
                <div class="form-field">
                    <label class="required">Bitiş Tarihi</label>
                    <input type="date" id="end_date" name="end_date" required style="background-color: #f8f9fa;">
                    <small style="color:#666; font-size:0.8rem;">(Otomatik Hesaplanır)</small>
                </div>

                <div class="form-field">
                    <label>Seviye</label>
                    <input type="number" id="level" name="level" min="0">
                </div>
                <div class="form-field">
                    <label>Akreditasyon</label>
                    <div class="dropdown">
                        <input type="hidden" id="accreditor" name="accreditor">
                        <button type="button" id="accreditorBtn" onclick="toggleDropdown('accreditorDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="accreditorDropdown" class="dropdown-content">
                             <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('accreditorDropdown')">
                             <a href="javascript:void(0)" onclick="selectOption('accreditor', '', 'Seçiniz...', 'accreditorDropdown')" style="color: #dc3545; font-style: italic; border-bottom: 1px solid #eee;">
                                <i class="fa fa-times-circle"></i> Seçimi Temizle
                            </a>
                             <?php foreach ($accreditors as $acc): ?>
                                <a href="javascript:void(0)" data-id="<?php echo $acc['id']; ?>" onclick="selectOption('accreditor', '<?php echo $acc['id']; ?>', '<?php echo htmlspecialchars($acc['code']); ?>', 'accreditorDropdown')"><?php echo htmlspecialchars($acc['code']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Danışman Firma</label>
                    <div class="dropdown">
                        <input type="hidden" id="consult_company_id" name="consult_company_id">
                        <button type="button" id="consultBtn" onclick="toggleDropdown('consultDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="consultDropdown" class="dropdown-content">
                            <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('consultDropdown')">
                            
                            <a href="javascript:void(0)" onclick="selectOption('consult_company_id', '', 'Seçiniz...', 'consultDropdown')" style="color: #dc3545; font-style: italic; border-bottom: 1px solid #eee;">
                                <i class="fa fa-times-circle"></i> Seçimi Temizle
                            </a>
    
                             <?php foreach ($consultants as $cons): ?>
                                <a href="javascript:void(0)" data-id="<?php echo $cons['id']; ?>" onclick="selectOption('consult_company_id', '<?php echo $cons['id']; ?>', '<?php echo htmlspecialchars($cons['c_name']); ?>', 'consultDropdown')"><?php echo htmlspecialchars($cons['c_name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label class="required">Durum</label>
                    <div class="dropdown">
                        <input type="hidden" id="status" name="status" value="1">
                        <button type="button" id="statusBtn" onclick="toggleDropdown('statusDropdown')" class="dropbtn">Aktif <span class="arrow-down">&#9662;</span></button>
                        <div id="statusDropdown" class="dropdown-content">
                            <?php foreach ($statuses as $st): ?>
                                <a href="javascript:void(0)" data-id="<?php echo $st['id']; ?>" onclick="selectOption('status', '<?php echo $st['id']; ?>', '<?php echo htmlspecialchars($st['status']); ?>', 'statusDropdown')"><?php echo htmlspecialchars($st['status']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-button cancel" onclick="closeModal()">İptal</button>
                <button type="submit" class="modal-button save">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    
    let currentCertPeriod = 0;
    let dataTable;

    $(document).ready(function() {
        // 1. Initial State Handling (Sayfa ilk yüklendiğinde)
        const urlParams = new URLSearchParams(window.location.search);
        const companyId = urlParams.get('company_id');
        const certId = urlParams.get('cert_id');
        const status = urlParams.get('status');

        if(companyId) {
            document.getElementById('search_company_id').value = companyId;
            syncFilterDropdownUI('search_company_id', companyId, 'search_companyFilterDropdown');
        }
        if(certId) {
            document.getElementById('search_cert_id').value = certId;
            syncFilterDropdownUI('search_cert_id', certId, 'search_certFilterDropdown');
        }
        if(status) {
            document.getElementById('search_status').value = status;
            syncFilterDropdownUI('search_status', status, 'search_statusFilterDropdown');
        }

        dataTable = $('#certTable').DataTable({
            "destroy": true, 
            "pageLength": 10,
            "dom": 'rtip',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 5 } ], // Sütun sayısı 6, index 5
            "autoWidth": false
        });
        $('#customSearch').on('keyup', function() { dataTable.search(this.value).draw(); });
    });

    // --- POPSTATE EVENT LISTENER (BACK BUTTON SUPPORT) ---
    window.addEventListener('popstate', function(event) {
        // 1. URL'den parametreleri oku
        const urlParams = new URLSearchParams(window.location.search);
        const companyId = urlParams.get('company_id') || '';
        const certId = urlParams.get('cert_id') || '';
        const status = urlParams.get('status') || '';

        // 2. Hidden Inputları Güncelle
        document.getElementById('search_company_id').value = companyId;
        document.getElementById('search_cert_id').value = certId;
        document.getElementById('search_status').value = status;

        // 3. Dropdown Buton Metinlerini Görsel Olarak Güncelle
        syncFilterDropdownUI('search_company_id', companyId, 'search_companyFilterDropdown');
        syncFilterDropdownUI('search_cert_id', certId, 'search_certFilterDropdown');
        syncFilterDropdownUI('search_status', status, 'search_statusFilterDropdown');

        // 4. Tabloyu Güncelle (False = Geçmişe yeni kayıt atma)
        applyFilters(false);
    });

    // Dropdown UI Senkronizasyon Yardımcısı
    function syncFilterDropdownUI(inputId, value, dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        let text = 'Tümü'; // Varsayılan metin

        if (value) {
            // Dropdown içindeki linkleri tara, değeri bul ve metnini al
            const links = dropdown.querySelectorAll('a');
            for (let link of links) {
                if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(`'${value}'`)) {
                    text = link.textContent.trim();
                    break;
                }
            }
        }
        
        const baseId = inputId.replace('_id', ''); 
        let btnId = baseId + 'Btn';
        if(inputId === 'search_status') btnId = 'search_statusBtn';

        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
    }

    // --- FİLTRELEME FONKSİYONU ---
    function applyFilters(pushToHistory = true) {
        const companyId = document.getElementById('search_company_id').value;
        const certId = document.getElementById('search_cert_id').value;
        const status = document.getElementById('search_status').value;

        // URL Yapılandırması
        const newUrl = new URL(window.location.href);
        if(companyId) newUrl.searchParams.set('company_id', companyId); else newUrl.searchParams.delete('company_id');
        if(certId) newUrl.searchParams.set('cert_id', certId); else newUrl.searchParams.delete('cert_id');
        if(status) newUrl.searchParams.set('status', status); else newUrl.searchParams.delete('status');
        
        if (pushToHistory) {
            window.history.pushState({path: newUrl.href}, '', newUrl);
        } else {
            window.history.replaceState(null, '', newUrl);
        }

        // Veriyi Çek
        const fd = new FormData();
        fd.append('action', 'filter_certifications');
        fd.append('company_id', companyId);
        fd.append('cert_id', certId);
        fd.append('status', status);

        fetch('certification.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                dataTable.clear();
                
                res.data.forEach(row => {
                    let statusColor = 'gray';
                    if(row.status_name == 'Aktif') statusColor = 'green';
                    if(row.status_name == 'Pasif' || row.status_name == 'İptal') statusColor = 'red';
                    if(row.status_name == 'Askıda') statusColor = 'orange';

                    const statusHtml = `<span style='color:${statusColor}; font-weight:bold;'>${row.status_name || ''}</span>`;
                    
                    let actionHtml = '';
                    <?php if($canManage): ?>
                    actionHtml = `
                        <button class="action-button update" onclick="openModal('update', ${row.id})">Güncelle</button>
                        <button class="action-button delete" onclick="deleteCert(${row.id})">Sil</button>
                    `;
                    <?php endif; ?>

                    dataTable.row.add([
                        row.company_name || '',
                        row.cert_name || '',
                        row.certno || '',
                        row.end_date || '',
                        statusHtml,
                        actionHtml
                    ]);
                });
                
                dataTable.draw();
            }
        });
    }

    function resetFilters() {
        document.getElementById('search_company_id').value = '';
        document.getElementById('search_cert_id').value = '';
        document.getElementById('search_status').value = '';
        
        document.getElementById('search_companyBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        document.getElementById('search_certBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        document.getElementById('search_statusBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        
        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl);

        applyFilters(false);
    }

    // --- TARİH HESAPLAMA ---
    function calculateEndDate() {
        const publishDateVal = document.getElementById('publish_date').value;
        
        if (publishDateVal && currentCertPeriod > 0) {
            const pubDate = new Date(publishDateVal);
            pubDate.setFullYear(pubDate.getFullYear() + parseInt(currentCertPeriod));
            pubDate.setDate(pubDate.getDate() - 1);
            
            const yyyy = pubDate.getFullYear();
            const mm = String(pubDate.getMonth() + 1).padStart(2, '0');
            const dd = String(pubDate.getDate()).padStart(2, '0');
            
            document.getElementById('end_date').value = `${yyyy}-${mm}-${dd}`;
        }
    }

    // --- UI YARDIMCILARI ---
    function toggleDropdown(id) { document.getElementById(id).classList.toggle("show"); }

    window.onclick = function(e) {
        if (!e.target.matches('.dropbtn') && !e.target.matches('.dropdown-content input')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
        if(e.target == document.getElementById('certModal')) closeModal();
    }
    
    document.addEventListener('keydown', function(event) { if (event.key === "Escape") closeModal(); });

    function filterDropdown(id) {
        var input = document.querySelector("#" + id + " input");
        var filter = input.value.toUpperCase();
        var div = document.getElementById(id);
        var a = div.getElementsByTagName("a");
        for (var i = 0; i < a.length; i++) {
            if ((a[i].textContent || a[i].innerText).toUpperCase().indexOf(filter) > -1) {
                a[i].style.display = "";
            } else { a[i].style.display = "none"; }
        }
    }

    // FİLTRE SEÇİM FONKSİYONU
    function selectFilterOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const baseId = inputId.replace('_id', ''); 
        const btnId = baseId + 'Btn';
        
        const btn = document.getElementById(btnId);
        if(btn) {
             btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        }

        document.getElementById(dropdownId).classList.remove('show');
    }

    // MODAL SEÇİM FONKSİYONU
    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btn = document.getElementById(dropdownId).closest('.dropdown').querySelector('.dropbtn');
        btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        
        if (inputId === 'f_cert_id') {
            const dropdown = document.getElementById(dropdownId);
            const link = dropdown.querySelector(`a[data-id='${value}']`);
            if (link) {
                currentCertPeriod = parseInt(link.getAttribute('data-period')) || 0;
                calculateEndDate(); 
            }
        }
    }

    // --- MODAL İŞLEMLERİ ---
    function openModal(type, id = 0) {
        const modal = document.getElementById('certModal');
        const form = document.getElementById('certForm');
        const title = document.getElementById('modalTitle');
        
        form.reset();
        
        document.querySelectorAll('.dropbtn').forEach(btn => {
            if(btn.id.startsWith('search_')) return; 

            if(btn.id === 'statusBtn') btn.innerHTML = 'Aktif <span class="arrow-down">&#9662;</span>';
            else btn.innerHTML = 'Seçiniz... <span class="arrow-down">&#9662;</span>';
        });
        
        currentCertPeriod = 0; 

        document.getElementById('action').value = type;
        document.getElementById('certId').value = id;

        if (type === 'add') {
            title.textContent = 'Yeni Sertifika Oluştur';
            document.getElementById('status').value = 1;
        } else {
            title.textContent = 'Sertifika Güncelle';
            
            const fd = new FormData();
            fd.append('action', 'get_certification');
            fd.append('id', id);

            fetch('certification.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    const d = res.data;
                    document.getElementById('certno').value = d.certno;
                    document.getElementById('scope').value = d.scope;
                    document.getElementById('publish_date').value = d.publish_date;
                    document.getElementById('end_date').value = d.end_date;
                    document.getElementById('level').value = d.level || '';
                    
                    if(d.cert_period) currentCertPeriod = parseInt(d.cert_period);

                    setDropdownByValue('f_company_id', d.f_company_id, 'companyDropdown');
                    setDropdownByValue('f_cert_id', d.f_cert_id, 'certDropdown');
                    setDropdownByValue('consult_company_id', d.consult_company_id, 'consultDropdown');
                    setDropdownByValue('accreditor', d.accreditor, 'accreditorDropdown');
                    setDropdownByValue('status', d.status, 'statusDropdown');

                } else {
                    alert(res.message);
                }
            })
            .catch(err => console.error(err));
        }
        modal.style.display = 'block';
    }

    function setDropdownByValue(inputId, value, dropdownId) {
        if(!value) return;
        document.getElementById(inputId).value = value;
        const dropdown = document.getElementById(dropdownId);
        // data-id ile modal dropdown linkini bul
        const link = dropdown.querySelector(`a[data-id='${value}']`);
        
        if(link) {
             const btn = dropdown.closest('.dropdown').querySelector('.dropbtn');
             btn.innerHTML = link.textContent + ' <span class="arrow-down">&#9662;</span>';
        }
    }

    function closeModal() { document.getElementById('certModal').style.display = 'none'; }

    function submitForm(e) {
        e.preventDefault();

        // Tarih Kontrolü
        const publishDateInput = document.getElementById('publish_date').value;
        if (publishDateInput) {
            const selectedDate = new Date(publishDateInput);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            selectedDate.setHours(0, 0, 0, 0);
            if (selectedDate > today) {
                alert('Hata: İlk Yayın Tarihi bugünden ileri (gelecek) bir tarih olamaz!');
                return;
            }
        }

        const formData = new FormData(e.target);
        fetch('certification.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                // Sayfayı yenilemek yerine tabloyu güncelle (Filtreyi tekrar çalıştır)
                closeModal();
                applyFilters(); 
            } else {
                alert('Hata: ' + data.message);
            }
        })
        .catch(err => { console.error(err); alert('Bir hata oluştu.'); });
    }

    function deleteCert(id) {
        if (confirm("Bu sertifikayı silmek istediğinize emin misiniz?")) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('certification.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    applyFilters(); // Tabloyu yenile
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