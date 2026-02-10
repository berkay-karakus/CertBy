<?php
session_start();

// --- GÜVENLİK KONTROLÜ ---
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// Rol Kontrolü (Case-Insensitive)
$currentUserRole = strtolower($_SESSION['role_code']);

// Yetki Matrisi: Sadece OPERATÖR erişebilir.
if ($currentUserRole !== 'operator') {
    header("Location: dashboard.php?error=yetkisiz_erisim");
    exit();
}

// --- BACKEND İŞLEMLERİ (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $action = $_POST['action'] ?? '';
        $id = intval($_POST['id'] ?? 0);

        // SİLME İŞLEMİ
        if ($action === 'delete') {
            // İlişki Kontrolü
            $stmtCheck = $db->prepare("SELECT count(*) FROM company WHERE consulting_id = ?");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(['status'=>'error', 'message'=>'Bu danışman firma, sistemdeki bazı şirketlerle ilişkilendirilmiş durumda. Silinemez.']);
                exit();
            }

            $stmt = $db->prepare("DELETE FROM consult_company WHERE id = ?");
            if ($stmt->execute([$id])) {
                // Logla
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Danışman Firma Yönetimi', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Danışman firma silindi. ID: $id"]);
                echo json_encode(['status'=>'success', 'message'=>'Kayıt başarıyla silindi.']);
            } else {
                echo json_encode(['status'=>'error', 'message'=>'Silme işlemi başarısız.']);
            }
            exit();
        }

        // EKLEME / GÜNCELLEME VERİLERİ
        $c_name = trim($_POST['c_name'] ?? '');      
        $name = trim($_POST['name'] ?? '');          
        $email = trim($_POST['email'] ?? '');        
        $address = trim($_POST['address'] ?? '');    
        $contact_name = trim($_POST['contact_name'] ?? ''); 
        $contact_email = trim($_POST['contact_email'] ?? ''); 

        // Zorunlu Alan Kontrolü
        if (empty($c_name) || empty($name) || empty($email)) {
            echo json_encode(['status'=>'error', 'message'=>'Lütfen zorunlu alanları (Kısa Ad, Uzun Ad, E-posta) doldurun.']);
            exit();
        }

        // EKLEME
        if ($action === 'add') {
            $sql = "INSERT INTO consult_company (c_name, name, email, address, contact_name, contact_email) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$c_name, $name, $email, $address, $contact_name, $contact_email])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Danışman Firma Yönetimi', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Yeni danışman firma eklendi: $c_name"]);
                echo json_encode(['status'=>'success', 'message'=>'Danışman firma başarıyla eklendi.']);
            } else {
                echo json_encode(['status'=>'error', 'message'=>'Ekleme sırasında hata oluştu.']);
            }
            exit();
        }

        // GÜNCELLEME
        if ($action === 'update') {
            $sql = "UPDATE consult_company SET c_name=?, name=?, email=?, address=?, contact_name=?, contact_email=? WHERE id=?";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$c_name, $name, $email, $address, $contact_name, $contact_email, $id])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Danışman Firma Yönetimi', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Danışman firma güncellendi: $c_name"]);
                echo json_encode(['status'=>'success', 'message'=>'Bilgiler güncellendi.']);
            } else {
                echo json_encode(['status'=>'error', 'message'=>'Güncelleme sırasında hata oluştu.']);
            }
            exit();
        }

    } catch (PDOException $e) { echo json_encode(['status'=>'error', 'message'=>'Veritabanı Hatası: '.$e->getMessage()]); exit(); }
}

// --- VERİ ÇEKME (GET) ---
try {
    // Tekil Veri (Modal İçin)
    if (isset($_GET['action']) && $_GET['action'] === 'get_consultant' && isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM consult_company WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        exit();
    }

    // Liste Verisi
    $stmt = $db->prepare("SELECT * FROM consult_company ORDER BY c_name ASC");
    $stmt->execute();
    $consultants = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Danışman Firmalar</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        /* Company.php ve Cert.php ile Uyumlu Tasarım */
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .add-button { padding: 12px 25px; font-size: 1rem; font-weight: 500; color: #fff; background-color: var(--primary-color); border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .add-button:hover { background-color: #0056b3; }
        
        .search-input { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        
        /* Tablo */
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        
        /* Butonlar */
        .action-button { padding: 6px 12px; font-size: 0.85rem; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; color: #fff; margin-right: 5px; }
        .update { background-color: var(--primary-color); }
        .delete { background-color: var(--danger-color); }

        /* DataTables Custom */
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_filter input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-left: 10px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 5% auto; padding: 30px; border-radius: 8px; width: 600px; max-width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        @keyframes slideIn { from {transform: translateY(-20px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .modal-title { font-size: 1.5rem; color: var(--primary-color); font-weight: 300; margin: 0; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .close-button:hover { color: #000; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-full { grid-column: 1 / -1; }
        .form-field { margin-bottom: 15px; }
        .form-field label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        label.required::after { content: " *"; color: var(--danger-color); }
        .form-field input, .form-field textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; }
        
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
        <h1 class="header-title">Danışman Firmalar</h1>
        <button class="add-button" onclick="openModal('add')"><i class="fa fa-plus"></i> Yeni Danışman Firma Ekle</button>
    </div>

    <input type="text" id="customSearch" class="search-input" placeholder="Firma adı veya e-posta ile ara...">

    <div class="table-responsive">
        <table id="consultantTable">
            <thead>
                <tr>
                    <th>Firma Kısa Adı</th>
                    <th>Ticari Unvan</th>
                    <th>E-posta</th>
                    <th>Danışman Adı</th>
                    <th width="150" style="text-align: right;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($consultants)): ?>
                    <?php foreach ($consultants as $c): ?>
                    <tr id="row-<?php echo $c['id']; ?>">
                        <td><?php echo htmlspecialchars($c['c_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($c['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($c['contact_name'] ?? ''); ?></td>
                        <td style="text-align: right;">
                            <button class="action-button update" onclick="openModal('update', <?php echo $c['id']; ?>)">Güncelle</button>
                            <button class="action-button delete" onclick="deleteConsultant(<?php echo $c['id']; ?>)">Sil</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="consultantModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle"></h2>
            <span class="close-button" onclick="closeModal()">&times;</span>
        </div>
        <form id="consultantForm" onsubmit="submitForm(event)">
            <input type="hidden" id="action" name="action">
            <input type="hidden" id="consultantId" name="id">

            <div class="form-grid">
                <div class="form-field">
                    <label>Firma Kısa Adı <span style="color:red">*</span></label>
                    <input type="text" id="c_name" name="c_name" required>
                </div>
                <div class="form-field">
                    <label>Ticari Unvan <span style="color:red">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-field">
                    <label>Firma E-posta <span style="color:red">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-field">
                    <label>Danışman Adı Soyadı</label>
                    <input type="text" id="contact_name" name="contact_name">
                </div>
                <div class="form-field">
                    <label>Danışman E-posta</label>
                    <input type="email" id="contact_email" name="contact_email">
                </div>
                <div class="form-field form-full">
                    <label>Adres</label>
                    <textarea id="address" name="address" rows="2"></textarea>
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
        var table = $('#consultantTable').DataTable({
            "pageLength": 10,
            "dom": 'rtip',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 4 } ]
        });
        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
    });

    function openModal(type, id = 0) {
        const modal = document.getElementById('consultantModal');
        const form = document.getElementById('consultantForm');
        form.reset();
        document.getElementById('action').value = type;
        document.getElementById('consultantId').value = id;

        if (type === 'add') {
            document.getElementById('modalTitle').textContent = "Yeni Danışman Firma Ekle";
        } else {
            document.getElementById('modalTitle').textContent = "Danışman Firma Güncelle";
            fetch(`consultant.php?action=get_consultant&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('c_name').value = data.c_name;
                    document.getElementById('name').value = data.name;
                    document.getElementById('email').value = data.email;
                    document.getElementById('address').value = data.address;
                    document.getElementById('contact_name').value = data.contact_name;
                    document.getElementById('contact_email').value = data.contact_email;
                });
        }
        modal.style.display = 'block';
    }

    function closeModal() { document.getElementById('consultantModal').style.display = 'none'; }
    window.onclick = function(e) { if(e.target == document.getElementById('consultantModal')) closeModal(); }

    function submitForm(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('consultant.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') { alert(data.message); location.reload(); }
            else { alert('Hata: ' + data.message); }
        });
    }

    function deleteConsultant(id) {
        if(confirm("Bu danışman firmayı silmek istediğinize emin misiniz?")) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('consultant.php', { method: 'POST', body: fd })
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