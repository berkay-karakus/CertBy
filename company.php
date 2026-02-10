<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// --- YETKİ TANIMLAMALARI ---
$userRole = strtolower($_SESSION['role_code']);
if ($userRole === 'auditor') {
    header("Location: dashboard.php");
    exit();
}
$isOperator = ($userRole === 'operator');

require_once 'db.php';

// ---------------------------------------------------------
// BACKEND İŞLEMLERİ
// ---------------------------------------------------------

// 1. FİLTRELEME İŞLEMİ (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'filter_companies') {
    header('Content-Type: application/json');
    try {
        $f_status = trim($_POST['status'] ?? '');
        
        $sql = "SELECT c.id, c.c_name, c.tax_number, c.c_phone, c.contact_name, c.contact_email, c.status 
                FROM company c WHERE 1=1";
        
        $params = [];
        if (!empty($f_status)) {
            $sql .= " AND c.status = ?";
            $params[] = $f_status;
        }
        $sql .= " ORDER BY c.c_name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit();

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// 2. Tekil Firma Bilgisi Getir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_company') {
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "SELECT * FROM company WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($company) echo json_encode(['status' => 'success', 'data' => $company]);
            else echo json_encode(['status' => 'error', 'message' => 'Şirket bulunamadı.']);
        } else echo json_encode(['status' => 'error', 'message' => 'Geçersiz ID.']);
    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit();
}

// 3. Firmanın Sertifikalarını Getir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_company_certification') {
    header('Content-Type: application/json');
    try {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $sql = "SELECT cn.id, cn.certno, c.name, cn.scope, cn.publish_date, cn.end_date, cns.status
                    FROM certification cn 
                    LEFT JOIN cert c ON cn.f_cert_id = c.id
                    LEFT JOIN certification_status cns ON cn.status = cns.id
                    WHERE cn.f_company_id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($certifications) echo json_encode(['status' => 'success', 'data' => $certifications]);
            else echo json_encode(['status' => 'success', 'data' => []]); 
        } else echo json_encode(['status' => 'error', 'message' => 'Geçersiz ID.']);
    } catch (PDOException $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit();
}

// 4. Ekleme ve Güncelleme İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'update')) {
    header('Content-Type: application/json');
    if (!$isOperator) { echo json_encode(['status' => 'error', 'message' => 'Yetkiniz yok.']); exit(); }

    try {
        $action = $_POST['action'];
        $id = intval($_POST['id'] ?? 0);
        
        // Verileri Al
        $c_name = trim($_POST['c_name'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact_address = trim($_POST['contact_address'] ?? '');
        $web = trim($_POST['web'] ?? '');
        $c_phone = trim($_POST['c_phone'] ?? '');
        $c_email = trim($_POST['c_email'] ?? '');
        $c_invoice_address = trim($_POST['c_invoice_address'] ?? '');
        $authorized_contact_name = trim($_POST['authorized_contact_name'] ?? '');
        $authorized_contact_title = trim($_POST['authorized_contact_title'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $tax_office = trim($_POST['tax_office'] ?? '');
        $tax_number = trim($_POST['tax_number'] ?? '');
        $consulting_id = intval($_POST['consulting_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if(empty($c_name) || empty($name) || empty($address) || empty($c_invoice_address) || empty($contact_name) || empty($contact_email) || empty($tax_office) || empty($tax_number) || empty($status)) {
             echo json_encode(['status' => 'error', 'message' => 'Lütfen zorunlu alanları doldurun.']); exit();
        }

        if (!empty($tax_office) && !empty($tax_number)) {
            $sql_check = "SELECT id FROM company WHERE tax_office = :tax_office AND tax_number = :tax_number";
            if ($action === 'update') $sql_check .= " AND id != :id";
            $stmt_check = $db->prepare($sql_check);
            $stmt_check->bindParam(':tax_office', $tax_office);
            $stmt_check->bindParam(':tax_number', $tax_number);
            if ($action === 'update') $stmt_check->bindParam(':id', $id);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) { echo json_encode(['status' => 'error', 'message' => 'Aynı vergi numarasına sahip firma zaten var.']); exit(); }
        }

        if ($action === 'add') {
            $sql = "INSERT INTO company (c_name, `name`, `address`, contact_address, web, c_phone, c_email, c_invoice_address, authorized_contact_name, authorized_contact_title, contact_name, contact_phone, contact_email, tax_office, tax_number, consulting_id, `status`) VALUES (:c_name, :name, :address, :contact_address, :web, :c_phone, :c_email, :c_invoice_address, :authorized_contact_name, :authorized_contact_title, :contact_name, :contact_phone, :contact_email, :tax_office, :tax_number, :consulting_id, :status)";
            $stmt = $db->prepare($sql);
             $params = [':c_name'=>$c_name, ':name'=>$name, ':address'=>$address, ':contact_address'=>$contact_address, ':web'=>$web, ':c_phone'=>$c_phone, ':c_email'=>$c_email, ':c_invoice_address'=>$c_invoice_address, ':authorized_contact_name'=>$authorized_contact_name, ':authorized_contact_title'=>$authorized_contact_title, ':contact_name'=>$contact_name, ':contact_phone'=>$contact_phone, ':contact_email'=>$contact_email, ':tax_office'=>$tax_office, ':tax_number'=>$tax_number, ':consulting_id'=>$consulting_id, ':status'=>$status];
             if ($stmt->execute($params)) {
                 $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Firma Yönetim Logu', ?)");
                 $logStmt->execute([$_SESSION['user_id'], "Yeni firma eklendi: $c_name"]);
                 echo json_encode(['status' => 'success', 'message' => 'Yeni şirket tanımlandı.']);
             } else echo json_encode(['status' => 'error', 'message' => 'Hata oluştu.']);
        } 
        elseif ($action === 'update') {
            $sql = "UPDATE company SET c_name = :c_name, `name` = :name, `address` = :address, contact_address = :contact_address, web = :web, c_phone = :c_phone, c_email = :c_email, c_invoice_address = :c_invoice_address, authorized_contact_name = :authorized_contact_name, authorized_contact_title = :authorized_contact_title, contact_name = :contact_name, contact_phone = :contact_phone, contact_email = :contact_email, tax_office = :tax_office, tax_number = :tax_number, consulting_id = :consulting_id, `status` = :status WHERE id = :id";
            $stmt = $db->prepare($sql);
            $params = [':c_name'=>$c_name, ':name'=>$name, ':address'=>$address, ':contact_address'=>$contact_address, ':web'=>$web, ':c_phone'=>$c_phone, ':c_email'=>$c_email, ':c_invoice_address'=>$c_invoice_address, ':authorized_contact_name'=>$authorized_contact_name, ':authorized_contact_title'=>$authorized_contact_title, ':contact_name'=>$contact_name, ':contact_phone'=>$contact_phone, ':contact_email'=>$contact_email, ':tax_office'=>$tax_office, ':tax_number'=>$tax_number, ':consulting_id'=>$consulting_id, ':status'=>$status, ':id'=>$id];
            if ($stmt->execute($params)) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, ?, 0, 'Firma Yönetim Logu', ?)");
                $logStmt->execute([$_SESSION['user_id'], $id, "Firma güncellendi: $c_name"]);
                echo json_encode(['status' => 'success', 'message' => 'Güncelleme başarılı.']);
            } else echo json_encode(['status' => 'error', 'message' => 'Hata oluştu.']);
        }
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit();
}

// 5. E-posta Şablonu Çekme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_template_data') {
    header('Content-Type: application/json');
    if (!$isOperator) { echo json_encode(['success' => false, 'message' => 'Yetkiniz yok.']); exit; }
    
    $templateId = intval($_POST['id'] ?? 0);
    $sql = "SELECT et.subject, et.body, GROUP_CONCAT(ea.file_name) AS attached_files, GROUP_CONCAT(ea.id) AS attached_ids FROM email_template et LEFT OUTER JOIN email_attachment ea ON et.id = ea.FK_email_template_id WHERE et.id = ? GROUP BY et.id, et.subject, et.body";
    $stmt = $db->prepare($sql);
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        $files = $template['attached_files'] ? explode(',', $template['attached_files']) : [];
        $ids = $template['attached_ids'] ? explode(',', $template['attached_ids']) : [];
        $attachments = [];
        for($i=0; $i<count($files); $i++){
            $attachments[] = ['id' => $ids[$i], 'file_name' => $files[$i]];
        }
        echo json_encode(['success' => true, 'subject' => $template['subject'], 'body' => $template['body'], 'attachments' => $attachments]);
    } else echo json_encode(['success' => false, 'message' => 'Şablon bulunamadı.']);
    exit();
}

// --- FİLTRE DEĞİŞKENLERİ (İlk Yükleme İçin) ---
$f_status = trim($_GET['status'] ?? '');

// --- VERİ ÇEKME ---
try {
    $consultingFirms = $db->query("SELECT id, c_name FROM consult_company ORDER BY c_name ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
    $emailTemplates = $db->query("SELECT id, `subject` FROM email_template ORDER BY `subject` ASC")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Ana Liste Sorgusu (Filtreli - İlk Yükleme)
    $sql = "SELECT c.id, c.c_name, c.tax_number, c.c_phone, c.contact_name, c.contact_email, c.status 
            FROM company c WHERE 1=1";
    
    $params = [];
    if (!empty($f_status)) {
        $sql .= " AND c.status = ?";
        $params[] = $f_status;
    }
    $sql .= " ORDER BY c.c_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $error = "Veritabanı hatası: " . $e->getMessage(); }

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Firma Yönetimi</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

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
        
        .action-buttons-wrapper { display: flex; gap: 5px; justify-content: flex-end; }
        .action-button { padding: 6px 12px; font-size: 0.85rem; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; color: #fff; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
        .action-button i { margin-right: 5px; }
        .action-button.update { background-color: var(--primary-color); }
        .action-button.delete { background-color: var(--danger-color); }
        .action-button.email-button { background-color: #f39c12; } 
        .action-button.disabled { background-color: #ccc !important; cursor: not-allowed !important; opacity: 0.7; pointer-events: none; }
        .action-button.view-certification { background-color: #17a2b8; }
        
        .search-input-dt { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
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
            display: flex; gap: 15px; align-items: flex-end; justify-content: flex-start; 
        }
        .filter-group { display: flex; flex-direction: column; width: 250px; }
        .filter-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; color: #555; }
        .btn-filter { background-color: var(--primary-color); color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: 500; transition: 0.2s; height: 42px; display: flex; align-items: center; gap: 8px; }
        .btn-filter:hover { background-color: #0056b3; }
        .btn-reset { background-color: var(--secondary-color); color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: 500; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-reset:hover { background-color: #5a6268; }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 800px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        @keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        
        .form-grid-modal { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: 1 / -1; } 
        .form-field { margin-bottom: 15px; }
        .form-field label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        .required { color: var(--danger-color); }
        .form-field input, .form-field select, .form-field textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; }
        
        /* DROPDOWN */
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 1rem; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px var(--dropdown-shadow); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content input { box-sizing: border-box; width: 95%; margin: 5px 2.5%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.95rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        .arrow-down { font-size: 0.8rem; color: #666; }
        
        .modal-footer { text-align: right; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; }
        .modal-button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; color: #fff; }
        .modal-button.cancel { background-color: var(--secondary-color); margin-right: 10px; }
        .modal-button.save { background-color: var(--success-color); }
        
        /* EK DOSYALAR */
        .attachment-container { border: 1px solid #e9ecef; border-radius: 4px; padding: 10px; background-color: #f8f9fa; margin-bottom: 15px; max-height: 150px; overflow-y: auto; }
        .attachment-list { list-style: none; margin: 0; padding: 0; }
        .attachment-item { display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 8px 12px; margin-bottom: 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.9rem; color: #495057; transition: background-color 0.2s; }
        .attachment-item:hover { background-color: #f1f3f5; }
        .attachment-name { display: flex; align-items: center; gap: 8px; font-weight: 500; }
        .attachment-name i { font-size: 1rem; }
        .remove-attachment { color: #dc3545; cursor: pointer; font-weight: bold; font-size: 0.85rem; padding: 2px 6px; border-radius: 4px; display: flex; align-items: center; justify-content: center; }
        .remove-attachment:hover { background-color: #ffebee; }
        .new-file-badge { background: #28a745; color: #fff; font-size: 0.65rem; padding: 2px 5px; border-radius: 3px; margin-left: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .fa-envelope:before { content: "\f0e0"; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">Firma Yönetimi</h1>
        <?php if ($isOperator): ?>
            <button class="add-button" onclick="openNewCompanyModal()"><i class="fa fa-plus"></i> Yeni Firma Ekle</button>
        <?php endif; ?>
    </div>

    <form id="filterForm" class="filter-section">
        <div class="filter-group">
            <label>Durum</label>
            <div class="dropdown">
                <input type="hidden" name="status" id="f_status" value="<?php echo htmlspecialchars($f_status ?? ''); ?>">
                <button type="button" id="f_statusBtn" onclick="toggleDropdown('statusDropdownF')" class="dropbtn" style="width:100%">
                    <?php 
                        $stLabel = 'Tümü';
                        if(($f_status ?? '') == 'A') $stLabel = 'Aktif';
                        if(($f_status ?? '') == 'P') $stLabel = 'Pasif';
                        echo $stLabel;
                    ?>
                    <span class="arrow-down">&#x25BC;</span>
                </button>
                <div id="statusDropdownF" class="dropdown-content">
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_status', '', 'Tümü', 'statusDropdownF')">Tümü</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_status', 'A', 'Aktif', 'statusDropdownF')">Aktif</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_status', 'P', 'Pasif', 'statusDropdownF')">Pasif</a>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="button" class="btn-filter" onclick="applyFilters()"><i class="fa fa-filter"></i> Uygula</button>
            <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fa fa-times"></i> Temizle</button>
        </div>
    </form>

    <input type="text" id="customSearch" class="search-input-dt" placeholder="Tabloda ara...">

    <div class="table-responsive">
        <table id="companyTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Firma Adı</th>
                    <th>Vergi No</th>
                    <th>Telefon</th>
                    <th>İletişim Kişisi</th>
                    <th>Durum</th>
                    <th style="width: 1%; white-space: nowrap; text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($companies)): ?>
                    <?php foreach ($companies as $company): ?>
                        <tr data-id="<?php echo htmlspecialchars($company['id']); ?>">
                            <td><input type="checkbox" class="company-checkbox"></td>
                            <td><?php echo htmlspecialchars($company['c_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($company['tax_number'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($company['c_phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($company['contact_name'] ?? ''); ?></td>
                            <td>
                                <?php if (($company['status'] ?? '') == 'A'): ?>
                                    <span style="color:var(--success-color); font-weight:bold;">Aktif</span>
                                <?php else: ?>
                                    <span style="color:var(--danger-color); font-weight:bold;">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-buttons-wrapper">
                                    <?php if ($isOperator): ?>
                                        <?php if (($company['status'] ?? '') == 'A'): ?>
                                            <button class="action-button email-button" 
                                                data-company-id="<?php echo htmlspecialchars($company['id'] ?? ''); ?>"
                                                data-company-name="<?php echo htmlspecialchars($company['c_name'] ?? ''); ?>"
                                                data-company-email="<?php echo htmlspecialchars($company['contact_email'] ?? ''); ?>">
                                                <i class="fa fa-envelope"></i> E-posta
                                            </button>
                                        <?php else: ?>
                                            <button class="action-button email-button disabled" 
                                                disabled
                                                title="Pasif firmalara e-posta gönderilemez.">
                                                <i class="fa fa-envelope"></i> E-posta
                                            </button>
                                        <?php endif; ?>
                                        <button class="action-button update" onclick="openCompanyModal('<?php echo htmlspecialchars($company['id'] ?? ''); ?>')">Güncelle</button>
                                    <?php endif; ?>
                                    <button class="action-button view-certification" 
                                        onclick="openCompanyCertificationModal('<?php echo htmlspecialchars($company['id'] ?? ''); ?>', '<?php echo htmlspecialchars($company['c_name'] ?? ''); ?>')">
                                        Sertifikalar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="companyModal" class="modal">
     <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle"></h2>
            <span class="close-button" onclick="closeCompanyModal()">&times;</span>
        </div>
        <form id="companyForm" action="" method="POST">
            <input type="hidden" id="companyId" name="id">
            <input type="hidden" id="actionField" name="action" value="">
            
            <div class="form-grid-modal">
                <div class="form-field">
                    <label for="c_name" class="required">Firma Adı</label>
                    <input type="text" id="c_name" name="c_name" required>
                </div>
                <div class="form-field">
                    <label for="name" class="required">Ticari Unvan</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-field">
                    <label for="address" class="required">Adres</label>
                    <input type="text" id="address" name="address" required>
                </div>
                <div class="form-field">
                    <label for="contact_address">İletişim Adresi</label>
                    <input type="text" id="contact_address" name="contact_address">
                </div>
                <div class="form-field">
                    <label for="web">Web Sitesi</label>
                    <input type="url" id="web" name="web">
                </div>
                <div class="form-field">
                    <label for="c_phone">Firma Telefonu</label>
                    <input type="tel" id="c_phone" name="c_phone">
                </div>
                <div class="form-field">
                    <label for="c_email">Firma E-postası</label>
                    <input type="email" id="c_email" name="c_email">
                </div>
                <div class="form-field">
                    <label for="c_invoice_address" class="required">Fatura Adresi</label>
                    <input type="text" id="c_invoice_address" name="c_invoice_address" required>
                </div>
                <div class="form-field">
                    <label for="authorized_contact_name">Yetkili İletişim Adı</label>
                    <input type="text" id="authorized_contact_name" name="authorized_contact_name">
                </div>
                <div class="form-field">
                    <label for="authorized_contact_title">Yetkili İletişim Unvanı</label>
                    <input type="text" id="authorized_contact_title" name="authorized_contact_title">
                </div>
                <div class="form-field">
                    <label for="contact_name" class="required">İletişim Kurulacak Kişi</label>
                    <input type="text" id="contact_name" name="contact_name" required>
                </div>
                <div class="form-field">
                    <label for="contact_phone">İletişim Telefonu</label>
                    <input type="tel" id="contact_phone" name="contact_phone">
                </div>
                <div class="form-field">
                    <label for="contact_email" class="required">İletişim E-postası</label>
                    <input type="email" id="contact_email" name="contact_email" required>
                </div>
                <div class="form-field">
                    <label for="tax_office" class="required">Vergi Dairesi</label>
                    <input type="text" id="tax_office" name="tax_office" required>
                </div>
                <div class="form-field">
                    <label for="tax_number" class="required">Vergi Numarası</label>
                    <input type="text" id="tax_number" name="tax_number" required>
                </div>

                <div class="form-field">
                    <label for="consultingDropdown">Danışman Firma</label>
                    <input type="hidden" id="consulting_id" name="consulting_id">
                    <div class="dropdown">
                        <button type="button" onclick="toggleDropdown('consultingDropdown')" class="dropbtn" id="consultBtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                        <div id="consultingDropdown" class="dropdown-content">
                            <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('consultingDropdown')">
                            
                            <a href="javascript:void(0)" onclick="selectOption('consulting_id', '', 'Seçiniz...', 'consultingDropdown')" style="color: #dc3545; font-style: italic; border-bottom: 1px solid #eee;">
                                <i class="fa fa-times-circle"></i> Seçimi Temizle
                            </a>

                            <?php foreach ($consultingFirms as $id => $name): ?>
                                <a href="javascript:void(0)" data-id="<?php echo $id; ?>" onclick="selectOption('consulting_id', '<?php echo $id; ?>', '<?php echo htmlspecialchars($name); ?>', 'consultingDropdown')"><?php echo htmlspecialchars($name); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label for="statusDropdown" class="required">Durum</label>
                    <input type="hidden" id="status_code" name="status" required>
                    <div class="dropdown">
                        <button type="button" onclick="toggleDropdown('statusDropdown')" class="dropbtn" id="statusBtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                        <div id="statusDropdown" class="dropdown-content">
                            <a href="javascript:void(0)" data-id="A" onclick="selectOption('status_code', 'A', 'Aktif', 'statusDropdown')">Aktif</a>
                            <a href="javascript:void(0)" data-id="P" onclick="selectOption('status_code', 'P', 'Pasif', 'statusDropdown')">Pasif</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-button cancel" onclick="closeCompanyModal()">İptal</button>
                <button type="submit" class="modal-button save" id="modalActionButton">Kaydet</button>
            </div>
        </form>
     </div>
</div>

<div id="companyCertificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="certModalTitle"></h2>
            <span class="close-button" onclick="closeCompanyCertificationModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="table-responsive">
                <table class="company-certification-table" width="100%">
                    <thead>
                        <tr>
                            <th>Belge No</th>
                            <th>Tür</th>
                            <th>Kapsam</th>
                            <th>Tarih</th>
                            <th>Bitiş</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody id="certificationTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="emailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">E-posta Gönderimi</h2>
            <span class="close-button" onclick="closeEmailModal()">&times;</span>
        </div>
        <div style="margin-bottom: 15px; background:#f1f3f5; padding:10px; border-radius:4px;">
            <strong>Firma: </strong><span id="emailCompanyName"></span><br>
            <strong>Alıcı: </strong><span id="emailRecipient"></span>
        </div>
        <form id="emailForm" enctype="multipart/form-data">
            <div class="form-field">
                <label>E-posta Şablonu</label>
                <input type="hidden" id="emailCompanyId" name="emailCompanyId">
                <input type="hidden" id="templateId" name="templateId">
                <div class="dropdown">
                    <button type="button" onclick="toggleDropdown('templateDropdown')" class="dropbtn" id="templateBtn">Şablon Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                    <div id="templateDropdown" class="dropdown-content">
                        <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('templateDropdown')">
                        <?php foreach ($emailTemplates as $id => $subj): ?>
                             <a href="javascript:void(0)" data-id="<?php echo $id; ?>" onclick="selectOption('templateId', '<?php echo $id; ?>', '<?php echo htmlspecialchars($subj); ?>', 'templateDropdown'); fetchTemplateData('<?php echo $id; ?>')"><?php echo htmlspecialchars($subj); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-field">
                <label>Konu</label>
                <input type="text" id="template_subject_id" name="template_subject_id" required>
            </div>
            <div class="form-field">
                <label>İçerik</label>
                <textarea id="template_body_subject" name="template_body_subject" rows="6" required></textarea>
            </div>
            
           <div class="form-field">
    <label style="font-weight:600; color:#333; margin-bottom:5px; display:block;">Ekli Dosyalar:</label>
    
    <div class="attachment-container">
        <ul id="templateAttachments" class="attachment-list"></ul>
        <ul id="newAttachmentList" class="attachment-list"></ul>
    </div>
</div>

<div class="form-field" style="margin-bottom: 20px;">
    <label class="action-button" style="background-color:#17a2b8; cursor:pointer; display:inline-flex; align-items:center; gap:8px; width:auto; padding:10px 20px; font-size:0.9rem;">
        <i class="fa fa-paperclip"></i> Dosya Ekle
        <input type="file" id="newEmailAttachments" style="display:none;" multiple>
    </label>
</div>

            <div class="modal-footer">
                <button type="button" class="modal-button cancel" onclick="closeEmailModal()">İptal</button>
                <button type="button" class="modal-button save" id="btnSendEmail">Gönder</button>
            </div>
        </form>
    </div>
</div>

<script>
    let removedAttachments = []; 
    let selectedNewFiles = [];   
    let dataTable;

    $(document).ready(function() {
        dataTable = $('#companyTable').DataTable({
            "pageLength": 10,
            "dom": 'Bfrtip',
            "buttons": [], // Sütun gizle/göster kaldırıldı
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 6 } ], // İşlemler (Index 6)
            "autoWidth": false 
        });

        $('#customSearch').on('keyup', function() {
            dataTable.search(this.value).draw();
        });
        
        // Select All Checkbox Logic
        $('#selectAll').on('change', function(){
            var rows = dataTable.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
        });

        // 1. Initial State Handling (Sayfa ilk yüklendiğinde)
        // URL'de bir filtre varsa (örneğin bookmark'tan gelindiyse)
        // PHP zaten tabloyu filtreli basıyor, ama Dropdown UI'ını güncellemeliyiz.
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status) {
            document.getElementById('f_status').value = status;
            // Dropdown buton metnini güncelle
            const btn = document.getElementById('f_statusBtn');
            if (status === 'A') btn.innerHTML = 'Aktif <span class=\"arrow-down\">&#x25BC;</span>';
            else if (status === 'P') btn.innerHTML = 'Pasif <span class=\"arrow-down\">&#x25BC;</span>';
        }
    });

    // --- POPSTATE EVENT LISTENER (BACK BUTTON SUPPORT) ---
    window.addEventListener('popstate', function(event) {
        // URL'den parametreleri oku
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status') || '';

        // UI'ı güncelle
        document.getElementById('f_status').value = status;
        
        const btn = document.getElementById('f_statusBtn');
        if(status === 'A') btn.innerHTML = 'Aktif <span class="arrow-down">&#x25BC;</span>';
        else if(status === 'P') btn.innerHTML = 'Pasif <span class="arrow-down">&#x25BC;</span>';
        else btn.innerHTML = 'Tümü <span class="arrow-down">&#x25BC;</span>';

        // Tabloyu güncelle (False parametresi ile history'ye tekrar push yapma)
        applyFilters(false);
    });

    // --- FİLTRELEME FONKSİYONU (AJAX) ---
    function applyFilters(pushToHistory = true) {
        const status = document.getElementById('f_status').value;

        // URL Güncelleme (pushState ile yeni geçmiş kaydı oluştur)
        if(pushToHistory) {
            const newUrl = new URL(window.location.href);
            if(status) newUrl.searchParams.set('status', status); else newUrl.searchParams.delete('status');
            window.history.pushState({path: newUrl.href}, '', newUrl); // PUSH STATE
        }

        // AJAX Veri Çekme
        const fd = new FormData();
        fd.append('action', 'filter_companies');
        fd.append('status', status);

        fetch('company.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                dataTable.clear();

                res.data.forEach(row => {
                    let statusHtml = '';
                    if(row.status === 'A') statusHtml = '<span style="color:var(--success-color); font-weight:bold;">Aktif</span>';
                    else statusHtml = '<span style="color:var(--danger-color); font-weight:bold;">Pasif</span>';

                    let actions = `
                        <div class="action-buttons-wrapper">
                            <?php if ($isOperator): ?>
                                ${row.status === 'A' 
                                    ? `<button class="action-button email-button" data-company-id="${row.id}" data-company-name="${row.c_name}" data-company-email="${row.contact_email}"><i class="fa fa-envelope"></i> E-posta</button>` 
                                    : `<button class="action-button email-button disabled" disabled title="Pasif firmalara e-posta gönderilemez."><i class="fa fa-envelope"></i> E-posta</button>`
                                }
                                <button class="action-button update" onclick="openCompanyModal('${row.id}')">Güncelle</button>
                            <?php endif; ?>
                            <button class="action-button view-certification" onclick="openCompanyCertificationModal('${row.id}', '${row.c_name}')">Sertifikalar</button>
                        </div>
                    `;

                    dataTable.row.add([
                        `<input type="checkbox" class="company-checkbox">`, // Checkbox
                        row.c_name || '',
                        row.tax_number || '',
                        row.c_phone || '',
                        row.contact_name || '',
                        statusHtml,
                        actions
                    ]);
                });
                dataTable.draw();
            } else {
                alert('Hata: ' + res.message);
            }
        })
        .catch(err => console.error(err));
    }

    function resetFilters() {
        document.getElementById('f_status').value = '';
        document.getElementById('f_statusBtn').innerHTML = 'Tümü <span class="arrow-down">&#x25BC;</span>';
        
        // Temizle butonuna basınca da pushState yapmalı ki "geri" ile filtreli hale dönülebilsin
        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl); // PUSH STATE

        applyFilters(false);
    }

    // --- UI HELPERS ---
    function toggleDropdown(id) { document.getElementById(id).classList.toggle("show"); }

    window.onclick = function(e) {
        if (!e.target.matches('.dropbtn') && !e.target.matches('.dropdown-content input')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
        if (e.target.classList.contains('modal')) e.target.style.display = "none";
    }

    document.addEventListener('keydown', function(event) { if (event.key === "Escape") { closeCompanyModal(); closeEmailModal(); closeCompanyCertificationModal(); } });

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
        let btnId = baseId + 'Btn';
        if(inputId === 'f_status') btnId = 'f_statusBtn';
        
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#x25BC;</span>';
        document.getElementById(dropdownId).classList.remove('show');
    }

    // GENEL SEÇİM FONKSİYONU
    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const dropdownDiv = document.getElementById(dropdownId);
        const btn = dropdownDiv.closest('.dropdown').querySelector('.dropbtn');
        btn.innerHTML = text + ' <span class="arrow-down">&#x25BC;</span>';
        dropdownDiv.closest('.dropdown').classList.remove('show');
    }

    // GÜNCELLEME İÇİN DEĞER ATAMA
    function setDropdownByValue(inputId, value, dropdownId) {
        if(!value) return;
        document.getElementById(inputId).value = value;
        const dropdown = document.getElementById(dropdownId);
        const link = dropdown.querySelector(`a[data-id='${value}']`);
        
        if(link) {
             const btn = dropdown.closest('.dropdown').querySelector('.dropbtn');
             btn.innerHTML = link.textContent + ' <span class="arrow-down">&#x25BC;</span>';
        }
    }

    // --- MODAL FUNCTIONS ---
    function openNewCompanyModal() {
        document.getElementById('companyForm').reset();
        document.getElementById('actionField').value = 'add';
        document.getElementById('modalTitle').textContent = 'Yeni Firma Ekle';
        document.getElementById('modalActionButton').textContent = 'Ekle';
        document.getElementById('companyId').value = '';
        
        // Reset Dropdowns (ID'ler düzeltildi)
        document.getElementById('consultBtn').innerHTML = 'Seçiniz... <span class="arrow-down">&#x25BC;</span>';
        document.getElementById('statusBtn').innerHTML = 'Seçiniz... <span class="arrow-down">&#x25BC;</span>';
        document.getElementById('consulting_id').value = '';
        document.getElementById('status_code').value = '';
        
        document.getElementById('companyModal').style.display = 'block';
    }

    function openCompanyModal(id) {
        document.getElementById('companyForm').reset();
        document.getElementById('actionField').value = 'update';
        document.getElementById('modalTitle').textContent = 'Firma Bilgilerini Güncelle';
        document.getElementById('modalActionButton').textContent = 'Güncelle';
        document.getElementById('companyId').value = id;

        const fd = new FormData();
        fd.append('action', 'get_company');
        fd.append('id', id);

        fetch('company.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                const d = res.data;
                document.getElementById('c_name').value = d.c_name;
                document.getElementById('name').value = d.name;
                document.getElementById('address').value = d.address;
                document.getElementById('contact_address').value = d.contact_address;
                document.getElementById('web').value = d.web;
                document.getElementById('c_phone').value = d.c_phone;
                document.getElementById('c_email').value = d.c_email;
                document.getElementById('c_invoice_address').value = d.c_invoice_address;
                document.getElementById('authorized_contact_name').value = d.authorized_contact_name;
                document.getElementById('authorized_contact_title').value = d.authorized_contact_title;
                document.getElementById('contact_name').value = d.contact_name;
                document.getElementById('contact_phone').value = d.contact_phone;
                document.getElementById('contact_email').value = d.contact_email;
                document.getElementById('tax_office').value = d.tax_office;
                document.getElementById('tax_number').value = d.tax_number;
                
                // ID Düzeltmeleri ile setDropdownByValue
                setDropdownByValue('consulting_id', d.consulting_id, 'consultingDropdown');
                setDropdownByValue('status_code', d.status, 'statusDropdown');

                document.getElementById('companyModal').style.display = 'block';
            } else { alert(res.message); }
        });
    }

    function closeCompanyModal() { document.getElementById('companyModal').style.display = 'none'; }

    const form = document.getElementById('companyForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(form);
        fetch('company.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert(res.message);
                // Tabloyu yenile (mevcut filtre ile, history push yapmadan)
                applyFilters(false);
                closeCompanyModal();
            } else { alert('Hata: ' + res.message); }
        });
    });

    // --- SERTİFİKA ---
    function openCompanyCertificationModal(id, name) {
        document.getElementById('certModalTitle').textContent = name + ' - Sertifikalar';
        const tbody = document.getElementById('certificationTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Yükleniyor...</td></tr>';
        document.getElementById('companyCertificationModal').style.display = 'block';

        const fd = new FormData();
        fd.append('action', 'get_company_certification');
        fd.append('id', id);

        fetch('company.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            tbody.innerHTML = '';
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach(c => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${c.certno}</td><td>${c.name}</td><td>${c.scope}</td><td>${c.publish_date}</td><td>${c.end_date}</td><td>${c.status}</td>`;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Kayıt bulunamadı.</td></tr>';
            }
        });
    }
    function closeCompanyCertificationModal() { document.getElementById('companyCertificationModal').style.display = 'none'; }

    // --- EMAIL İŞLEMLERİ ---
    $(document).on('click', '.email-button', function(e) {
        e.preventDefault();
        const btn = $(this);
        if(btn.hasClass('disabled')) return; 

        const id = btn.data('company-id');
        const name = btn.data('company-name');
        const mail = btn.data('company-email');
        openEmailModal(id, name, mail);
    });

    function openEmailModal(id, name, mail) {
        const modal = document.getElementById('emailModal');
        const form = document.getElementById('emailForm');
        form.setAttribute('data-active-id', id);
        
        removedAttachments = [];
        selectedNewFiles = [];
        document.getElementById('emailForm').reset();
        document.getElementById('templateAttachments').innerHTML = '';
        document.getElementById('newAttachmentList').innerHTML = '';
        document.getElementById('newEmailAttachments').value = '';

        document.getElementById('emailCompanyName').textContent = name;
        document.getElementById('emailRecipient').textContent = mail;
        document.getElementById('emailCompanyId').value = id;
        
        // ID Düzeltmesi
        document.getElementById('templateBtn').innerHTML = 'Şablon Seçiniz... <span class="arrow-down">&#x25BC;</span>';
        
        const sendBtn = document.getElementById('btnSendEmail');
        if(sendBtn) {
             sendBtn.innerText = 'Gönder';
             sendBtn.disabled = false;
        }
        
        modal.style.display = 'block';
    }
    
    function closeEmailModal() { document.getElementById('emailModal').style.display = 'none'; }

    function fetchTemplateData(id) {
        removedAttachments = []; 
        const fd = new FormData();
        fd.append('action', 'get_template_data');
        fd.append('id', id);
        fetch('company.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                document.getElementById('template_subject_id').value = res.subject;
                document.getElementById('template_body_subject').value = res.body;
                const ul = document.getElementById('templateAttachments');
                ul.innerHTML = '';
                if(res.attachments) {
                    res.attachments.forEach(f => {
                        const li = document.createElement('li');
                        li.className = 'attachment-item';
                        li.id = 'att-tmpl-' + f.id;
                        li.innerHTML = `
                            <span class="attachment-name"><i class="fa fa-file-alt" style="color:#6c757d; margin-right: 8px;"></i> ${f.file_name}</span>
                            <span class="remove-attachment" onclick="removeTemplateAttachment(${f.id})"><i class="fa fa-times"></i></span>
                        `;
                        ul.appendChild(li);
                    });
                }
            } else alert(res.message);
        });
    }

    function removeTemplateAttachment(id) {
        removedAttachments.push(id);
        document.getElementById('att-tmpl-' + id).style.display = 'none';
    }

    const fileInput = document.getElementById('newEmailAttachments');
    if(fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = Array.from(this.files);
            files.forEach(file => {
                selectedNewFiles.push(file);
            });
            renderNewFiles();
            this.value = ''; 
        });
    }

    function renderNewFiles() {
        const list = document.getElementById('newAttachmentList');
        list.innerHTML = '';
        selectedNewFiles.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'attachment-item';
            li.innerHTML = `
                <span class="attachment-name"><i class="fa fa-file-upload" style="color:#28a745; margin-right: 8px;"></i> ${file.name} <span class="new-file-badge">YENİ</span></span><span class="remove-attachment" onclick="removeNewFile(${index})"><i class="fa fa-times"></i></span>
            `;
            list.appendChild(li);
        });
    }

    function removeNewFile(index) {
        selectedNewFiles.splice(index, 1);
        renderNewFiles();
    }

    const emailBtn = document.getElementById('btnSendEmail');
    if(emailBtn) {
        emailBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = document.getElementById('emailForm');
            const btn = this;
            const originalText = btn.innerText;

            btn.disabled = true;
            btn.innerText = 'Gönderiliyor...';

            const fd = new FormData(form);
            const activeId = form.getAttribute('data-active-id');
            if(activeId) fd.set('emailCompanyId', activeId);

            if(removedAttachments.length > 0) {
                fd.append('removed_attachment_ids', removedAttachments.join(','));
            }

            selectedNewFiles.forEach(file => {
                fd.append('new_attachments[]', file);
            });

            fetch('send_email.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerText = originalText;

                if(res.success) {
                    alert(res.message);
                    closeEmailModal();
                } else alert('Hata: ' + res.message);
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = originalText;
                console.error(err);
                alert('Bir hata oluştu.');
            });
        });
    }

    function setupPhoneInput(id) {
        const el = document.getElementById(id);
        if(!el) return;
        el.addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });
    }
    setupPhoneInput('c_phone');
    setupPhoneInput('contact_phone');
</script>
</body>
</html> 