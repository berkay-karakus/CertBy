<?php
session_start();

// --- 1. GÜVENLİK ve OTURUM KONTROLÜ ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// Mevcut kullanıcının bilgileri
$currentUserId = $_SESSION['user_id'];
$currentUserRole = strtolower($_SESSION['role_code']); 

// Denetçi (Auditor) bu sayfaya hiç giremez
if ($currentUserRole === 'auditor') {
    header("Location: dashboard.php?error=yetkisiz_erisim");
    exit();
}

// Operatör mü?
$isOperator = ($currentUserRole === 'operator');

// --- 2. BACKEND İŞLEMLERİ (JSON API) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';

        // --- A. FİLTRELEME İŞLEMİ (AJAX) ---
        if ($action === 'filter_users') {
            $sql = "SELECT * FROM user WHERE 1=1";
            $params = [];

            // Eğer Operatör değilse sadece kendini görsün (Güvenlik)
            if (!$isOperator) {
                $sql .= " AND id = ?";
                $params[] = $currentUserId;
            } else {
                // Filtreler
                $f_role = trim($_POST['role'] ?? '');
                $f_status = trim($_POST['status'] ?? '');
                
                if (!empty($f_role)) { $sql .= " AND role_code = ?"; $params[] = $f_role; }
                if (!empty($f_status)) { $sql .= " AND status = ?"; $params[] = $f_status; }
            }

            $sql .= " ORDER BY id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // JSON için veriyi işle (Butonlar ve Renkler)
            $data = [];
            foreach ($users as $user) {
                $rowUserId = $user['id'];
                $rowUserRole = strtolower($user['role_code']);
                $isSelf = ($rowUserId == $currentUserId);
                
                // Yetki Mantığı (PHP tarafındaki logic aynen taşındı)
                $canUpdate = $isSelf || ($isOperator && $rowUserRole !== 'operator');
                $canDelete = $isOperator && $rowUserRole !== 'operator' && !$isSelf;

                // Rol Gösterimi
                $roleDisplay = htmlspecialchars($user['role_code']);
                if($rowUserRole == 'operator') $roleDisplay = 'Operatör';
                elseif($rowUserRole == 'user') $roleDisplay = 'Kullanıcı';
                elseif($rowUserRole == 'auditor') $roleDisplay = 'Denetçi';

                // Durum HTML
                $statusHtml = ($user['status'] == 'A') 
                    ? '<span style="color: var(--success-color); font-weight:bold;">Aktif</span>' 
                    : '<span style="color: var(--danger-color); font-weight:bold;">Pasif</span>';

                // Butonlar HTML
                $actionHtml = '';
                if ($canUpdate) {
                    $actionHtml .= '<button class="action-button update" onclick="openUserModal(\'update\', '.$user['id'].')">Güncelle</button> ';
                } else {
                    $actionHtml .= '<button class="action-button disabled" disabled>Güncelle</button> ';
                }
                if ($canDelete) {
                    $actionHtml .= '<button class="action-button delete" onclick="deleteUser('.$user['id'].')">Sil</button>';
                }

                $data[] = [
                    'username' => htmlspecialchars($user['username']),
                    'name' => htmlspecialchars($user['name']),
                    'email' => htmlspecialchars($user['email']),
                    'role' => $roleDisplay,
                    'status' => $statusHtml,
                    'actions' => $actionHtml
                ];
            }

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }

        // --- B. SİLME İŞLEMİ ---
        if ($action === 'delete') {
            if (!$isOperator) { echo json_encode(['status' => 'error', 'message' => 'Silme yetkiniz yok.']); exit(); }

            $targetId = intval($_POST['id'] ?? 0);
            if ($targetId == $currentUserId) { echo json_encode(['status' => 'error', 'message' => 'Kendi hesabınızı silemezsiniz.']); exit(); }

            $stmtRole = $db->prepare("SELECT role_code FROM user WHERE id = ?");
            $stmtRole->execute([$targetId]);
            $targetRole = strtolower($stmtRole->fetchColumn());

            if ($targetRole === 'operator') { echo json_encode(['status' => 'error', 'message' => 'Yönetici (Operatör) hesapları silinemez.']); exit(); }

            // İlişkili kayıt kontrolü
            $stmtLog = $db->prepare("SELECT (SELECT count(*) FROM general_log WHERE user_id = ?) + (SELECT count(*) FROM email_log WHERE user_id = ?) as total_logs");
            $stmtLog->execute([$targetId, $targetId]);
            if ($stmtLog->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => "Bu kullanıcı sistemde işlem yaptığı için silinemez! Lütfen durumunu 'Pasif' yapınız."]); exit();
            }

            $stmt = $db->prepare("DELETE FROM user WHERE id = ?");
            if ($stmt->execute([$targetId])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Kullanıcı Yönetim Logu', ?)");
                $logStmt->execute([$currentUserId, "Kullanıcı silindi. ID: $targetId"]);
                echo json_encode(['status' => 'success', 'message' => 'Kullanıcı silindi.']);
            } else { echo json_encode(['status' => 'error', 'message' => 'Hata oluştu.']); }
            exit();
        }

        // --- C. EKLEME (ADD) ---
        if ($action === 'add') {
            if (!$isOperator) { echo json_encode(['status' => 'error', 'message' => 'Yetkiniz yok.']); exit(); }
            
            $username = trim($_POST['username'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role_code = trim($_POST['role_code'] ?? '');
            $status = trim($_POST['status'] ?? 'A');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($name) || empty($email) || empty($role_code) || empty($password)) {
                echo json_encode(['status'=>'error','message'=>'Lütfen zorunlu alanları doldurun.']); exit;
            }
            // Şifre kontrolü
            if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                echo json_encode(['status'=>'error','message'=>'Şifre politikasına uymuyor.']); exit;
            }

            $stmt = $db->prepare("SELECT count(*) FROM user WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) { echo json_encode(['status'=>'error','message'=>'Bu kullanıcı adı veya e-posta zaten kayıtlı.']); exit; }

            $hashedPwd = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO user (username, name, email, role_code, status, password) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$username, $name, $email, $role_code, $status, $hashedPwd])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Kullanıcı Yönetim Logu', ?)");
                $logStmt->execute([$currentUserId, "Yeni kullanıcı eklendi: $username ($role_code)"]);
                echo json_encode(['status'=>'success','message'=>'Kullanıcı oluşturuldu.']);
            }
            exit();
        }

        // --- D. GÜNCELLEME (UPDATE) ---
        if ($action === 'update') {
            $targetId = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role_code = trim($_POST['role_code'] ?? '');
            $status = trim($_POST['status'] ?? 'A');
            $password = $_POST['password'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';

            $stmtTarget = $db->prepare("SELECT role_code FROM user WHERE id = ?");
            $stmtTarget->execute([$targetId]);
            $targetData = $stmtTarget->fetch(PDO::FETCH_ASSOC);
            $targetRole = strtolower($targetData['role_code']);

            $isSelf = ($targetId === $currentUserId);

            if (!$isSelf && !$isOperator) { echo json_encode(['status' => 'error', 'message' => 'Yetkisiz işlem.']); exit(); }
            if ($isOperator && !$isSelf && $targetRole === 'operator') { echo json_encode(['status' => 'error', 'message' => 'Diğer yönetici hesaplarını düzenleyemezsiniz.']); exit(); }
            if (!empty($password) && !$isSelf) { echo json_encode(['status' => 'error', 'message' => 'Başka kullanıcının şifresini değiştiremezsiniz.']); exit(); }

            $query = "UPDATE user SET name=?, email=?";
            $params = [$name, $email];

            if ($isOperator) {
                $query .= ", role_code=?, status=?";
                $params[] = $role_code;
                $params[] = $status;
            }

            if ($isSelf && !empty($password)) {
                if (empty($currentPassword)) { echo json_encode(['status'=>'error', 'message'=>'Mevcut şifrenizi giriniz.']); exit(); }
                
                $stmtPwd = $db->prepare("SELECT password FROM user WHERE id = ?");
                $stmtPwd->execute([$targetId]);
                if (!password_verify($currentPassword, $stmtPwd->fetchColumn())) { echo json_encode(['status'=>'error', 'message'=>'Mevcut şifreniz hatalı.']); exit(); }
                
                if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                    echo json_encode(['status'=>'error','message'=>'Yeni şifre politikaya uymuyor.']); exit;
                }
                $query .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $query .= " WHERE id=?";
            $params[] = $targetId;

            $stmt = $db->prepare($query);
            if ($stmt->execute($params)) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Kullanıcı Yönetim Logu', ?)");
                $logStmt->execute([$currentUserId, "Kullanıcı güncellendi (ID: $targetId)."]);
                echo json_encode(['status'=>'success','message'=>'Bilgiler güncellendi.']);
            }
            exit();
        }

    } catch (PDOException $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit; }
}

// --- 3. VERİ ÇEKME (İLK YÜKLEME - GET) ---
try {
    if (isset($_GET['action']) && $_GET['action'] === 'get_user' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        if (!$isOperator && $id !== $currentUserId) { echo json_encode(['error' => 'Yetkisiz erişim']); exit(); }
        $stmt = $db->prepare("SELECT id, username, name, email, role_code, status FROM user WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        exit();
    }

    $sql = "SELECT * FROM user WHERE 1=1";
    $params = [];

    // URL Filtrelerini Al
    $f_role = trim($_GET['role'] ?? '');
    $f_status = trim($_GET['status'] ?? '');

    if (!$isOperator) {
        $sql .= " AND id = ?";
        $params[] = $currentUserId;
    } else {
        if (!empty($f_role)) { $sql .= " AND role_code = ?"; $params[] = $f_role; }
        if (!empty($f_status)) { $sql .= " AND status = ?"; $params[] = $f_status; }
    }

    $sql .= " ORDER BY id ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        /* STİLLER */
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545; --dropdown-hover: #f1f3f5; --dropdown-shadow: rgba(0,0,0,0.1); }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .add-button { padding: 12px 25px; font-size: 1rem; font-weight: 500; color: #fff; background-color: var(--primary-color); border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .action-button { padding: 8px 15px; font-size: 0.9rem; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; margin: 0 5px; color: #fff; }
        .action-button.update { background-color: var(--primary-color); }
        .action-button.delete { background-color: var(--danger-color); }
        .action-button.cancel { background-color: var(--secondary-color); margin-right: 10px; }
        .action-button.disabled { background-color: #ccc; cursor: not-allowed; opacity: 0.6; }
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; }
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th, .user-table td { text-align: left; padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }
        .user-table th { background-color: var(--background-color); font-weight: 600; color: var(--secondary-color); }
        .search-input { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_filter input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-left: 10px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 700px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        @keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-field { margin-bottom: 15px; }
        .form-field label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500; color: #495057; }
        label.required::after { content: " *"; color: var(--danger-color); }
        .form-field input, .form-field select { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; background-color: #fcfcfc; }
        
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 12px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px var(--dropdown-shadow); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.95rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        .arrow-down { font-size: 0.8rem; color: #666; }
        
        .filter-section { background: #f1f3f5; padding: 20px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #e9ecef; display: flex; gap: 15px; align-items: flex-end; justify-content: flex-start; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; width: 200px; } 
        .btn-filter { background-color: var(--primary-color); color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: 500; transition: 0.2s; height: 42px; display: flex; align-items: center; gap: 8px; }
        .btn-reset { background-color: var(--secondary-color); color: white; border: none; padding: 10px 30px; border-radius: 4px; cursor: pointer; font-weight: 500; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        
        .modal-footer { text-align: right; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .password-rules-box { font-size: 0.85rem; color: #666; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 10px; margin-top: 5px; display: none; }
        .rule-item { display: flex; align-items: center; margin-bottom: 3px; }
        .rule-item i { margin-right: 8px; font-size: 0.8rem; }
        .rule-item.valid { color: var(--success-color); }
        .rule-item.invalid { color: var(--danger-color); }
        .password-match-message { margin-top: 5px; font-size: 0.85rem; font-weight: 500; }
        .password-match-message.success { color: var(--success-color); }
        .password-match-message.error { color: var(--danger-color); }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">Kullanıcı Yönetimi</h1>
        <?php if ($isOperator): ?>
            <button class="add-button" onclick="openUserModal('add')"><i class="fa fa-plus"></i> Yeni Kullanıcı Ekle</button>
        <?php endif; ?>
    </div>

    <?php if ($isOperator): ?>
    <form id="filterForm" class="filter-section">
        <div class="filter-group">
            <label>Rol</label>
            <div class="dropdown">
                <input type="hidden" name="role" id="f_role" value="<?php echo htmlspecialchars($f_role ?? ''); ?>">
                <button type="button" id="f_roleBtn" onclick="toggleDropdown('roleFilterDropdown')" class="dropbtn" style="width:100%">
                    <?php 
                        $roleLabels = ['' => 'Tümü', 'operator' => 'Operatör', 'auditor' => 'Denetçi', 'user' => 'Kullanıcı'];
                        echo $roleLabels[$f_role] ?? 'Tümü';
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="roleFilterDropdown" class="dropdown-content">
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_role', '', 'Tümü', 'roleFilterDropdown')">Tümü</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_role', 'operator', 'Operatör', 'roleFilterDropdown')">Operatör</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_role', 'auditor', 'Denetçi', 'roleFilterDropdown')">Denetçi</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_role', 'user', 'Kullanıcı', 'roleFilterDropdown')">Kullanıcı</a>
                </div>
            </div>
        </div>

        <div class="filter-group">
            <label>Durum</label>
            <div class="dropdown">
                <input type="hidden" name="status" id="f_status" value="<?php echo htmlspecialchars($f_status ?? ''); ?>">
                <button type="button" id="f_statusBtn" onclick="toggleDropdown('statusFilterDropdown')" class="dropbtn" style="width:100%">
                    <?php 
                        $statusLabels = ['' => 'Tümü', 'A' => 'Aktif', 'P' => 'Pasif'];
                        echo $statusLabels[$f_status] ?? 'Tümü';
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="statusFilterDropdown" class="dropdown-content">
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_status', '', 'Tümü', 'statusFilterDropdown')">Tümü</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_status', 'A', 'Aktif', 'statusFilterDropdown')">Aktif</a>
                    <a href="javascript:void(0)" onclick="selectFilterOption('f_status', 'P', 'Pasif', 'statusFilterDropdown')">Pasif</a>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: flex-end; gap: 10px;">
            <button type="button" class="btn-filter" onclick="applyFilters(true)"><i class="fa fa-filter"></i> Uygula</button>
            <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fa fa-times"></i> Temizle</button>
        </div>
    </form>
    <?php endif; ?>

    <input type="text" id="customSearch" class="search-input" placeholder="Kullanıcı adı, e-posta veya role göre ara...">

    <div class="table-responsive">
        <table class="user-table" id="userTable">
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
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <?php 
                            $rowUserId = $user['id'];
                            $rowUserRole = strtolower($user['role_code']);
                            $isSelf = ($rowUserId == $currentUserId);
                            
                            $canUpdate = $isSelf || ($isOperator && $rowUserRole !== 'operator');
                            $canDelete = $isOperator && $rowUserRole !== 'operator' && !$isSelf;
                        ?>
                        <tr id="row-<?php echo $user['id']; ?>">
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php 
                                switch(strtolower($user['role_code'])) {
                                    case 'operator': echo 'Operatör'; break;
                                    case 'user': echo 'Kullanıcı'; break;
                                    case 'auditor': echo 'Denetçi'; break;
                                    default: echo htmlspecialchars($user['role_code']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if($user['status'] == 'A'): ?>
                                    <span style="color: var(--success-color); font-weight:bold;">Aktif</span>
                                <?php else: ?>
                                    <span style="color: var(--danger-color); font-weight:bold;">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canUpdate): ?>
                                    <button class="action-button update" onclick="openUserModal('update', <?php echo $user['id']; ?>)">Güncelle</button>
                                <?php else: ?>
                                    <button class="action-button disabled" disabled>Güncelle</button>
                                <?php endif; ?>

                                <?php if ($canDelete): ?>
                                    <button class="action-button delete" onclick="deleteUser(<?php echo $user['id']; ?>)">Sil</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Yeni Kullanıcı</h2>
            <span class="close-button" onclick="closeUserModal()">&times;</span>
        </div>
        <form id="userForm" onsubmit="submitUserForm(event)">
            <input type="hidden" id="action" name="action">
            <input type="hidden" id="userId" name="id">

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
                    <label class="required">Rol</label>
                    <?php if ($isOperator): ?>
                        <div class="dropdown">
                            <input type="hidden" id="role_code" name="role_code" required>
                            <button type="button" id="roleBtn" onclick="toggleDropdown('roleDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                            <div id="roleDropdown" class="dropdown-content">
                                <a href="javascript:void(0)" onclick="selectOption('role_code', 'operator', 'Operatör', 'roleDropdown')">Operatör</a>
                                <a href="javascript:void(0)" onclick="selectOption('role_code', 'auditor', 'Denetçi', 'roleDropdown')">Denetçi</a>
                                <a href="javascript:void(0)" onclick="selectOption('role_code', 'user', 'Kullanıcı', 'roleDropdown')">Kullanıcı</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="text" id="role_display" class="form-control" disabled style="background:#f8f9fa; color:#6c757d;">
                        <input type="hidden" id="role_code" name="role_code">
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label>Durum</label>
                    <?php if ($isOperator): ?>
                        <div class="dropdown">
                            <input type="hidden" id="status" name="status" value="A">
                            <button type="button" id="statusBtn" onclick="toggleDropdown('statusDropdown')" class="dropbtn">Aktif <span class="arrow-down">&#9662;</span></button>
                            <div id="statusDropdown" class="dropdown-content">
                                <a href="javascript:void(0)" onclick="selectOption('status', 'A', 'Aktif', 'statusDropdown')">Aktif</a>
                                <a href="javascript:void(0)" onclick="selectOption('status', 'P', 'Pasif', 'statusDropdown')">Pasif</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="text" id="status_display" class="form-control" disabled style="background:#f8f9fa; color:#6c757d;">
                        <input type="hidden" id="status" name="status">
                    <?php endif; ?>
                </div>
            </div>

            <div id="passwordSection" style="border-top: 1px solid #e9ecef; padding-top: 20px; margin-top: 10px;">
                <div class="form-field" id="currentPassDiv">
                    <label for="current_password" class="required">Mevcut Şifre (Güvenlik İçin)</label>
                    <input type="password" id="current_password" name="current_password" placeholder="İşlemi onaylamak için mevcut şifrenizi girin">
                </div>
                
                <div class="form-grid">
                    <div class="form-field">
                        <label for="password" id="passwordLabel">Yeni Şifre</label>
                        <input type="password" id="password" name="password" 
                               placeholder="En az 8 karakter" 
                               onkeyup="checkPasswordRules(this.value); checkPasswordMatch();">
                        
                        <div id="passwordRulesBox" class="password-rules-box">
                            <div id="rule-len" class="rule-item invalid"><i class="fa fa-circle"></i> En az 8 Karakter</div>
                            <div id="rule-up" class="rule-item invalid"><i class="fa fa-circle"></i> 1 Büyük Harf (A-Z)</div>
                            <div id="rule-low" class="rule-item invalid"><i class="fa fa-circle"></i> 1 Küçük Harf (a-z)</div>
                            <div id="rule-num" class="rule-item invalid"><i class="fa fa-circle"></i> 1 Rakam (0-9)</div>
                            <div id="rule-spec" class="rule-item invalid"><i class="fa fa-circle"></i> 1 Özel Karakter</div>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                        <input type="password" id="confirm_password" name="confirm_password" onkeyup="checkPasswordMatch()">
                        <div id="passwordMatchMessage" class="password-match-message"></div>
                    </div>
                </div>
            </div>
            <div id="passwordLockedMsg" style="display:none; color:#dc3545; font-size:0.9rem; margin-top:10px;">
                <i class="fa fa-lock"></i> Güvenlik gereği başka bir kullanıcının şifresini değiştiremezsiniz.
            </div>

            <div class="modal-footer">
                <button type="button" class="action-button cancel" onclick="closeUserModal()">İptal</button>
                <button type="submit" class="action-button update">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    let dataTable;

    $(document).ready(function() {
        // Initial State from URL
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.get('role')) {
            const role = urlParams.get('role');
            if(document.getElementById('f_role')) {
                document.getElementById('f_role').value = role;
                syncFilterDropdownUI('f_role', role, 'roleFilterDropdown');
            }
        }
        if (urlParams.get('status')) {
            const status = urlParams.get('status');
            if(document.getElementById('f_status')) {
                document.getElementById('f_status').value = status;
                syncFilterDropdownUI('f_status', status, 'statusFilterDropdown');
            }
        }

        dataTable = $('#userTable').DataTable({
            "destroy": true,
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50],
            "dom": 'rtip',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 5 } ],
            "autoWidth": false
        });
        $('#customSearch').on('keyup', function() { dataTable.search(this.value).draw(); });
    });

    // --- POPSTATE EVENT LISTENER (BACK BUTTON SUPPORT) ---
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        
        const role = urlParams.get('role') || '';
        const status = urlParams.get('status') || '';

        if(document.getElementById('f_role')) {
            document.getElementById('f_role').value = role;
            syncFilterDropdownUI('f_role', role, 'roleFilterDropdown');
        }
        if(document.getElementById('f_status')) {
            document.getElementById('f_status').value = status;
            syncFilterDropdownUI('f_status', status, 'statusFilterDropdown');
        }

        applyFilters(false);
    });

    // --- FİLTRELEME (AJAX) ---
    function applyFilters(pushToHistory = true) {
        if(!document.getElementById('f_role')) return; // Operatör değilse filtre yok

        const role = document.getElementById('f_role').value;
        const status = document.getElementById('f_status').value;

        // URL Güncelleme
        const newUrl = new URL(window.location.href);
        if(role) newUrl.searchParams.set('role', role); else newUrl.searchParams.delete('role');
        if(status) newUrl.searchParams.set('status', status); else newUrl.searchParams.delete('status');

        if (pushToHistory) {
            window.history.pushState({path: newUrl.href}, '', newUrl);
        } else {
            window.history.replaceState(null, '', newUrl);
        }

        const fd = new FormData();
        fd.append('action', 'filter_users');
        fd.append('role', role);
        fd.append('status', status);

        fetch('user.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                dataTable.clear();
                
                res.data.forEach(row => {
                    dataTable.row.add([
                        row.username,
                        row.name,
                        row.email,
                        row.role,
                        row.status,
                        row.actions
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
        if(!document.getElementById('f_role')) return;

        document.getElementById('f_role').value = '';
        document.getElementById('f_roleBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        
        document.getElementById('f_status').value = '';
        document.getElementById('f_statusBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';

        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl); // PUSH STATE for reset
        
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
        if (e.target == document.getElementById('userModal')) closeUserModal();
    }

    document.addEventListener('keydown', function(event) { if (event.key === "Escape") closeUserModal(); });

    function selectFilterOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btnId = inputId + 'Btn'; 
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        document.getElementById(dropdownId).classList.remove('show');
    }

    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btnId = dropdownId.replace('Dropdown', 'Btn'); 
        document.getElementById(btnId).innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        document.getElementById(dropdownId).classList.remove('show');
    }

    function syncFilterDropdownUI(inputId, value, dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        let text = 'Tümü';
        if (value) {
            const links = dropdown.querySelectorAll('a');
            for (let link of links) {
                // Linkin onclick özelliğini kontrol et
                if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(`'${value}'`)) {
                    text = link.textContent.trim();
                    break;
                }
            }
        }
        const btnId = inputId + 'Btn'; 
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
    }

    // Mevcut Şifre Kontrol JS (Aynen korundu)
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('passwordMatchMessage');
    const isOperator = <?php echo $isOperator ? 'true' : 'false'; ?>;
    const currentUserId = <?php echo $currentUserId; ?>;

    function checkPasswordMatch() {
        const pass = passwordField.value;
        const confirm = confirmPasswordField.value;
        if (confirm === '') { matchMessage.textContent = ''; return; }
        if (pass === confirm) {
            matchMessage.textContent = 'Şifreler Eşleşiyor';
            matchMessage.className = 'password-match-message success';
        } else {
            matchMessage.textContent = 'Şifreler Eşleşmiyor';
            matchMessage.className = 'password-match-message error';
        }
    }

    function checkPasswordRules(val) {
        const box = document.getElementById('passwordRulesBox');
        if (val.length > 0) { box.style.display = 'block'; } else { box.style.display = 'none'; return; }
        updateRule('rule-len', val.length >= 8);
        updateRule('rule-up', /[A-Z]/.test(val));
        updateRule('rule-low', /[a-z]/.test(val));
        updateRule('rule-num', /[0-9]/.test(val));
        updateRule('rule-spec', /[\W_]/.test(val));
    }

    function updateRule(id, isValid) {
        const el = document.getElementById(id);
        const icon = el.querySelector('i');
        if (isValid) { el.className = 'rule-item valid'; icon.className = 'fa fa-check-circle'; } 
        else { el.className = 'rule-item invalid'; icon.className = 'fa fa-times-circle'; }
    }

    function openUserModal(type, id = 0) {
        const modal = document.getElementById('userModal');
        const form = document.getElementById('userForm');
        const title = document.getElementById('modalTitle');
        const usernameInput = document.getElementById('username');
        const passwordSection = document.getElementById('passwordSection');
        const passwordLockedMsg = document.getElementById('passwordLockedMsg');
        const currentPassDiv = document.getElementById('currentPassDiv');
        const currentPassInput = document.getElementById('current_password');
        
        form.reset();
        document.getElementById('passwordRulesBox').style.display = 'none';
        document.getElementById('passwordMatchMessage').textContent = '';
        
        if(isOperator) {
            selectOption('role_code', '', 'Seçiniz...', 'roleDropdown');
            selectOption('status', 'A', 'Aktif', 'statusDropdown');
        }

        document.getElementById('action').value = type;
        document.getElementById('userId').value = id;

        if (type === 'add') {
            title.textContent = 'Yeni Kullanıcı Ekle';
            usernameInput.disabled = false;
            passwordSection.style.display = 'block';
            passwordLockedMsg.style.display = 'none';
            currentPassDiv.style.display = 'none'; 
            currentPassInput.required = false;
            document.getElementById('password').required = true;
        } 
        else {
            title.textContent = 'Kullanıcı Güncelle';
            usernameInput.disabled = true;
            
            if(id == currentUserId) {
                passwordSection.style.display = 'block';
                passwordLockedMsg.style.display = 'none';
                currentPassDiv.style.display = 'block';
                currentPassInput.required = false; 
                document.getElementById('password').required = false;
            } else {
                passwordSection.style.display = 'none';
                passwordLockedMsg.style.display = 'block';
                document.getElementById('password').value = '';
                document.getElementById('confirm_password').value = '';
            }

            fetch(`user.php?action=get_user&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if(data.error) { alert(data.error); closeUserModal(); return; }

                    document.getElementById('username').value = data.username;
                    document.getElementById('name').value = data.name;
                    document.getElementById('email').value = data.email;
                    
                    const roleMap = {'operator': 'Operatör', 'auditor': 'Denetçi', 'user': 'Kullanıcı'};
                    const roleText = roleMap[data.role_code.toLowerCase()] || data.role_code;
                    
                    const statusMap = {'A': 'Aktif', 'P': 'Pasif'};
                    const statusText = statusMap[data.status] || 'Bilinmiyor';

                    if(isOperator) {
                        selectOption('role_code', data.role_code.toLowerCase(), roleText, 'roleDropdown');
                        selectOption('status', data.status, statusText, 'statusDropdown');
                    } else {
                        document.getElementById('role_display').value = roleText;
                        document.getElementById('role_code').value = data.role_code;
                        document.getElementById('status_display').value = statusText;
                        document.getElementById('status').value = data.status;
                    }
                });
        }
        modal.style.display = 'block';
    }

    function closeUserModal() {
        document.getElementById('userModal').style.display = 'none';
    }

    function submitUserForm(e) {
        e.preventDefault();
        
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const type = document.getElementById('action').value;
        const currentPass = document.getElementById('current_password').value;
        const isSelfUpdate = (document.getElementById('userId').value == currentUserId);

        if (pass.length > 0) {
            if (pass !== confirm) { alert('Yeni şifreler eşleşmiyor!'); return; }
            if (pass.length < 8) { alert('Şifre en az 8 karakter olmalıdır.'); return; }
            if (!/[A-Z]/.test(pass) || !/[a-z]/.test(pass) || !/[0-9]/.test(pass)) { alert('Şifre kriterleri sağlanmıyor.'); return; }
            
            if (isSelfUpdate && type === 'update' && currentPass === '') {
                alert('Şifrenizi değiştirmek için lütfen mevcut şifrenizi giriniz.');
                document.getElementById('current_password').focus();
                return;
            }
        } else if (type === 'add') { 
            alert('Yeni kullanıcı için şifre zorunludur.'); return; 
        }
        
        const formData = new FormData(e.target);
        if(document.getElementById('username').disabled) {
             formData.append('username', document.getElementById('username').value);
        }

        fetch('user.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                // Tabloyu güncelle (Sayfa yenilemeden)
                if(isOperator) applyFilters(false);
                else location.reload(); // Operatör değilse filtre olmadığı için reload mecbur
                
                closeUserModal();
            } else { alert('Hata: ' + data.message); }
        })
        .catch(err => { console.error(err); alert('Bir hata oluştu.'); });
    }

    function deleteUser(id) {
        if (confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch('user.php', { method: 'POST', body: formData })
            .then(r => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    if(isOperator) applyFilters(false);
                    else {
                        var table = $('#userTable').DataTable();
                        table.row($('#row-' + id)).remove().draw();
                    }
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