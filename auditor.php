<?php
session_start();

// --- GÜVENLİK KONTROLÜ ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

$currentUserRole = strtolower($_SESSION['role_code']);

// Yetki Matrisi: Sadece OPERATÖR erişebilir.
if ($currentUserRole !== 'operator') {
    header("Location: dashboard.php?error=yetkisiz_erisim");
    exit();
}

// ---------------------------------------------------------
// BACKEND İŞLEMLERİ (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $action = $_POST['action'] ?? '';
        $id = intval($_POST['id'] ?? 0);

        // --- SİLME İŞLEMİ ---
        if ($action === 'delete') {
            // Önce bu kullanıcıya atanmış denetim var mı kontrol et (auditor_transaction tablosundan)
            // Not: Artık f_auditorid, user tablosundaki ID'ye denk geliyor.
            $stmtCheck = $db->prepare("SELECT count(*) FROM auditor_transaction WHERE f_auditorid = ?");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['status'=>'error', 'message'=>'Bu denetçi geçmişte veya gelecekte bir denetime atanmış durumda. Silinemez, sadece Pasif yapabilirsiniz.']);
                exit();
            }

            // user tablosundan sil
            $stmt = $db->prepare("DELETE FROM user WHERE id = ? AND role_code = 'auditor'");
            if ($stmt->execute([$id])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Denetçi Yönetimi', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Denetçi silindi. ID: $id"]);
                echo json_encode(['status'=>'success', 'message'=>'Denetçi başarıyla silindi.']);
            } else {
                echo json_encode(['status'=>'error', 'message'=>'Silme işlemi başarısız.']);
            }
            exit();
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = trim($_POST['status'] ?? 'A');

        if (empty($name) || empty($email)) {
            echo json_encode(['status'=>'error', 'message'=>'Lütfen zorunlu alanları (Ad Soyad, E-posta) doldurun.']);
            exit();
        }

        // --- EKLEME İŞLEMİ ---
        if ($action === 'add') {
            // Şifre alanı olmadığı için varsayılan bir şifre veya null atanabilir,
            // ancak user tablosunda password zorunluysa buraya dummy bir veri eklememiz gerekebilir.
            // Şimdilik sadece gerekli alanları ekliyoruz.
            // NOT: Eğer user tablosunda 'password' not null ise hata alabilirsin. 
            // Öyleyse SQL'i şöyle güncelle: INSERT INTO user (..., password) VALUES (..., '$2y$10$dummyhash...')
            
            $sql = "INSERT INTO user (name, email, status, role_code) VALUES (?, ?, ?, 'auditor')";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$name, $email, $status])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Denetçi Yönetimi', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Yeni denetçi eklendi: $name"]);
                echo json_encode(['status'=>'success', 'message'=>'Denetçi başarıyla eklendi.']);
            } else {
                echo json_encode(['status'=>'error', 'message'=>'Ekleme sırasında hata oluştu.']);
            }
            exit();
        }

        // --- GÜNCELLEME İŞLEMİ ---
        if ($action === 'update') {
            // DÜZELTME: auditor tablosu yerine user tablosu güncelleniyor
            $sql = "UPDATE user SET name=?, email=?, status=? WHERE id=? AND role_code='auditor'";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$name, $email, $status, $id])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Denetçi Yönetimi', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Denetçi güncellendi: $name"]);
                echo json_encode(['status'=>'success', 'message'=>'Bilgiler güncellendi.']);
            } else {
                echo json_encode(['status'=>'error', 'message'=>'Güncelleme sırasında hata oluştu.']);
            }
            exit();
        }

    } catch (PDOException $e) { echo json_encode(['status'=>'error', 'message'=>'Veritabanı Hatası: '.$e->getMessage()]); exit(); }
}

// ---------------------------------------------------------
// SAYFA VERİLERİ (GET)
// ---------------------------------------------------------
try {
    // Tekil veri çekme (Düzenleme Modu için)
    if (isset($_GET['action']) && $_GET['action'] === 'get_auditor' && isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM user WHERE role_code = 'auditor' AND id = ?");
        $stmt->execute([$_GET['id']]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        exit();
    }

    // Liste Verisi
    $stmt = $db->prepare("SELECT * FROM user WHERE role_code = 'auditor' ORDER BY name ASC");
    $stmt->execute();
    $auditors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Denetçi Yönetimi</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        /* Ortak Stil */
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
        .search-input { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        
        .action-button { padding: 6px 12px; font-size: 0.85rem; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; color: #fff; margin-right: 5px; }
        .update { background-color: var(--primary-color); }
        .delete { background-color: var(--danger-color); }

        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_filter input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-left: 10px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 5% auto; padding: 30px; border-radius: 8px; width: 500px; max-width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        @keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .modal-title { font-size: 1.5rem; color: var(--primary-color); font-weight: 300; margin: 0; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        .required { color: var(--danger-color); }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; }
        
        /* DROPDOWN STİLLERİ */
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; }
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
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Dashboard'a Dön</a>
    
    <div class="header">
        <h1 class="header-title">Denetçi Yönetimi</h1>
        <button class="add-button" onclick="openModal('add')"><i class="fa fa-plus"></i> Yeni Denetçi Ekle</button>
    </div>

    <input type="text" id="customSearch" class="search-input" placeholder="Denetçi adı veya e-posta ile ara...">

    <div class="table-responsive">
        <table id="auditorTable">
            <thead>
                <tr>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Durum</th>
                    <th style="width: 150px; text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($auditors)): ?>
                    <?php foreach ($auditors as $a): ?>
                    <tr id="row-<?php echo $a['id']; ?>">
                        <td><?php echo htmlspecialchars($a['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($a['email'] ?? ''); ?></td>
                        <td>
                            <?php if (($a['status'] ?? '') == 'A'): ?>
                                <span style="color:var(--success-color); font-weight:bold;">Aktif</span>
                            <?php else: ?>
                                <span style="color:var(--danger-color); font-weight:bold;">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <button class="action-button update" onclick="openModal('update', <?php echo $a['id']; ?>)">Güncelle</button>
                            <button class="action-button delete" onclick="deleteAuditor(<?php echo $a['id']; ?>)">Sil</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="auditorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle"></h2>
            <span class="close-button" onclick="closeModal()">&times;</span>
        </div>
        <form id="auditorForm" onsubmit="submitForm(event)">
            <input type="hidden" id="action" name="action">
            <input type="hidden" id="auditorId" name="id">

            <div class="form-group">
                <label>Ad Soyad <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>E-posta <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Durum</label>
                <div class="dropdown">
                    <input type="hidden" id="status" name="status" required>
                    <button type="button" id="statusBtn" onclick="toggleDropdown('statusDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                    <div id="statusDropdown" class="dropdown-content">
                        <a href="#" onclick="selectOption('status', 'A', 'Aktif', 'statusDropdown')">Aktif</a>
                        <a href="#" onclick="selectOption('status', 'P', 'Pasif', 'statusDropdown')">Pasif</a>
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
    $(document).ready(function() {
        var table = $('#auditorTable').DataTable({
            "pageLength": 10,
            "dom": 'rtip',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 3 } ],
            "autoWidth": false
        });
        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
    });

    // --- DROPDOWN FONKSİYONLARI ---
    function toggleDropdown(id) { document.getElementById(id).classList.toggle("show"); }

    window.onclick = function(e) {
        if (!e.target.matches('.dropbtn') && !e.target.matches('.dropdown-content input')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
        if(e.target == document.getElementById('auditorModal')) closeModal();
    }

    // ESC Tuşu ile Modal Kapatma
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });

    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        document.getElementById(dropdownId).closest('.dropdown').querySelector('.dropbtn').innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
    }

    function openModal(type, id = 0) {
        const modal = document.getElementById('auditorModal');
        const form = document.getElementById('auditorForm');
        form.reset();
        document.getElementById('action').value = type;
        document.getElementById('auditorId').value = id;

        if (type === 'add') {
            document.getElementById('modalTitle').textContent = "Yeni Denetçi Ekle";
            // Varsayılan Aktif
            selectOption('status', 'A', 'Aktif', 'statusDropdown');
        } else {
            document.getElementById('modalTitle').textContent = "Denetçi Bilgilerini Güncelle";
            
            // AJAX ile veri çek
            fetch(`auditor.php?action=get_auditor&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('name').value = data.name;
                    document.getElementById('email').value = data.email;
                    
                    if (data.status === 'A') {
                        selectOption('status', 'A', 'Aktif', 'statusDropdown');
                    } else {
                        selectOption('status', 'P', 'Pasif', 'statusDropdown');
                    }
                });
        }
        modal.style.display = 'block';
    }

    function closeModal() { document.getElementById('auditorModal').style.display = 'none'; }

    function submitForm(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        // DÜZELTME: user.php değil, aynı dosyaya (auditor.php) istek atılmalı
        fetch('auditor.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') { alert(data.message); location.reload(); }
            else { alert('Hata: ' + data.message); }
        });
    }

    function deleteAuditor(id) {
        if(confirm("Bu denetçiyi silmek istediğinize emin misiniz?")) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            // DÜZELTME: user.php değil, aynı dosyaya (auditor.php) istek atılmalı
            fetch('auditor.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') { alert(data.message); location.reload(); }
                else { alert(data.message); }
            });
        }
    }
</script>
</body>
</html>