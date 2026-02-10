<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// --- YETKİ VE ROL KONTROLÜ ---
$userRole = strtolower($_SESSION['role_code']);
$username = $_SESSION['username'];
$fullName = $_SESSION['name'] ?? ''; 

$canEdit = ($userRole === 'operator');
$isAuditor = ($userRole === 'auditor');

// ---------------------------------------------------------
// BACKEND İŞLEMLERİ (AJAX)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 1. FİLTRELEME İŞLEMİ
    if (isset($_POST['action']) && $_POST['action'] === 'filter_audits') {
        try {
            $f_start_date = $_POST['start_date'] ?? '';
            $f_end_date = $_POST['end_date'] ?? '';
            $f_company_id = intval($_POST['company_id'] ?? 0);
            $f_audit_type = $_POST['audit_type'] ?? '';
            $f_auditor_id = intval($_POST['auditor_id'] ?? 0);
            $f_audit_status = $_POST['audit_status'] ?? '';

            $sql = "SELECT 
                        p.id, 
                        c.c_name as company_name, 
                        ct.name as cert_type_name, 
                        p.audit_publish_date, 
                        p.audit_end_date, 
                        p.audit_status, 
                        p.audit_certtification_no, 
                        p.audit_type,
                        GROUP_CONCAT(u.name SEPARATOR ', ') as auditor_name,
                        DATEDIFF(p.audit_publish_date, CURDATE()) as days_remaining 
                    FROM planning p
                    LEFT JOIN company c ON p.f_company_id = c.id
                    LEFT JOIN cert ct ON p.f_cert_id = ct.id
                    LEFT JOIN planning_auditor pa ON p.id = pa.f_planning_id
                    LEFT JOIN user u ON pa.f_auditor_id = u.id
                    WHERE 1=1";

            $params = [];

            if ($isAuditor) {
                $sql .= " AND p.id IN (SELECT f_planning_id FROM planning_auditor pa2 JOIN user u2 ON pa2.f_auditor_id = u2.id WHERE u2.username = ?)";
                $params[] = $username;
            }

            if (!empty($f_start_date)) { $sql .= " AND p.audit_publish_date >= ?"; $params[] = $f_start_date; }
            if (!empty($f_end_date)) { $sql .= " AND p.audit_publish_date <= ?"; $params[] = $f_end_date; }
            if ($f_company_id > 0) { $sql .= " AND p.f_company_id = ?"; $params[] = $f_company_id; }
            if (!empty($f_audit_type)) { $sql .= " AND p.audit_type = ?"; $params[] = $f_audit_type; }
            if (!$isAuditor && $f_auditor_id > 0) { $sql .= " AND pa.f_auditor_id = ?"; $params[] = $f_auditor_id; }
            if (!empty($f_audit_status)) { $sql .= " AND p.audit_status = ?"; $params[] = $f_audit_status; }

            $sql .= " GROUP BY p.id ORDER BY p.audit_publish_date ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }

    // 2. TAKVİM VERİLERİ
    if (isset($_POST['action']) && $_POST['action'] === 'get_calendar_events') {
        try {
            $sql = "SELECT p.id, c.c_name as company_name, p.audit_publish_date, p.audit_end_date, p.audit_status, 
                           GROUP_CONCAT(u.name SEPARATOR ', ') as auditor_names 
                    FROM planning p
                    LEFT JOIN company c ON p.f_company_id = c.id
                    LEFT JOIN planning_auditor pa ON p.id = pa.f_planning_id
                    LEFT JOIN user u ON pa.f_auditor_id = u.id
                    WHERE 1=1";
            
            $params = [];

            if ($isAuditor) {
                $sql .= " AND p.id IN (SELECT f_planning_id FROM planning_auditor pa2 JOIN user u2 ON pa2.f_auditor_id = u2.id WHERE u2.username = ?)";
                $params[] = $username;
            }

            if (!empty($_POST['company_id'])) { $sql .= " AND p.f_company_id = ?"; $params[] = $_POST['company_id']; }
            if (!empty($_POST['audit_status'])) { $sql .= " AND p.audit_status = ?"; $params[] = $_POST['audit_status']; }
            if (!empty($_POST['audit_type'])) { $sql .= " AND p.audit_type = ?"; $params[] = $_POST['audit_type']; }
            if (!empty($_POST['auditor_id'])) { $sql .= " AND pa.f_auditor_id = ?"; $params[] = $_POST['auditor_id']; }
            if (!empty($_POST['start_date'])) { $sql .= " AND p.audit_publish_date >= ?"; $params[] = $_POST['start_date']; }
            if (!empty($_POST['end_date'])) { $sql .= " AND p.audit_publish_date <= ?"; $params[] = $_POST['end_date']; }
            
            $sql .= " GROUP BY p.id"; 

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $events = [];
            foreach ($plans as $p) {
                $color = '#3788d8'; 
                if ($p['audit_status'] == 'Planlanacak') $color = '#6c757d';
                elseif ($p['audit_status'] == 'Gerçekleşti') $color = '#28a745';
                elseif ($p['audit_status'] == 'Ertelendi') $color = '#ffc107';
                elseif ($p['audit_status'] == 'İptal') $color = '#dc3545';
                
                $auditorDisplay = $p['auditor_names'] ? $p['auditor_names'] : 'Atanmadı';

                $events[] = [
                    'id' => $p['id'],
                    'title' => $p['company_name'] . ' (' . $auditorDisplay . ')', 
                    'start' => $p['audit_publish_date'],
                    'end' => date('Y-m-d', strtotime($p['audit_end_date'] . ' +1 day')),
                    'color' => $color
                ];
            }
            echo json_encode($events);
            exit();
        } catch (Exception $e) {
            echo json_encode([]); exit();
        }
    }
    
    // 3. E-POSTA ŞABLON VERİSİ
    if (isset($_POST['action']) && $_POST['action'] === 'get_template_data') {
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

    // YETKİ KONTROLÜ (CRUD)
    if (!$canEdit && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update', 'delete'])) {
        echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
        exit();
    }

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'get_company_certs') {
            $company_id = intval($_POST['company_id'] ?? 0);
            $sql = "SELECT c.id, c.certno, c.f_cert_id, ct.name as cert_name 
                    FROM certification c 
                    LEFT JOIN cert ct ON c.f_cert_id = ct.id 
                    WHERE c.f_company_id = ? AND c.status = 1"; 
            $stmt = $db->prepare($sql);
            $stmt->execute([$company_id]);
            $certs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $certs]);
            exit();
        }

        if ($action === 'get_plan') {
            $id = intval($_POST['id'] ?? 0);
            
            $stmt = $db->prepare("SELECT * FROM planning WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($plan) {
                $stmtPart = $db->prepare("SELECT name, email FROM participant WHERE f_planning_id = ?");
                $stmtPart->execute([$id]);
                $plan['participants'] = $stmtPart->fetchAll(PDO::FETCH_ASSOC);
                
                $stmtAuds = $db->prepare("SELECT f_auditor_id FROM planning_auditor WHERE f_planning_id = ?");
                $stmtAuds->execute([$id]);
                $plan['auditor_ids'] = $stmtAuds->fetchAll(PDO::FETCH_COLUMN);
                
                $stmtAudDetails = $db->prepare("SELECT u.name, u.email FROM planning_auditor pa LEFT JOIN user u ON pa.f_auditor_id = u.id WHERE pa.f_planning_id = ?");
                $stmtAudDetails->execute([$id]);
                $plan['auditor_details'] = $stmtAudDetails->fetchAll(PDO::FETCH_ASSOC);

                $plan['company_id'] = $plan['f_company_id'];
                $plan['consult_id'] = $plan['f_consult_company_id'];
                $plan['cert_type_id'] = $plan['f_cert_id']; 

                if($plan['audit_certtification_no']) {
                     $stmtCert = $db->prepare("SELECT id FROM certification WHERE certno = ?");
                     $stmtCert->execute([$plan['audit_certtification_no']]);
                     $cert = $stmtCert->fetch(PDO::FETCH_ASSOC);
                     $plan['linked_cert_id'] = $cert ? $cert['id'] : 0;
                }

                echo json_encode(['status' => 'success', 'data' => $plan]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Plan bulunamadı.']);
            }
            exit();
        }

        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM planning WHERE id = ?");
            if ($stmt->execute([$id])) {
                $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, 0, 0, 'Denetim Planlama', ?)");
                $logStmt->execute([$_SESSION['user_id'], "Denetim planı silindi. ID: $id"]);
                echo json_encode(['status' => 'success', 'message' => 'Plan silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Silme başarısız.']);
            }
            exit();
        }

        // --- EKLEME / GÜNCELLEME ---
        if ($action === 'add' || $action === 'update') {
            $f_company_id = intval($_POST['company_id'] ?? 0);
            $audit_type = $_POST['audit_type'] ?? 'ilk'; 
            $start_date = $_POST['audit_publish_date'] ?? '';
            $end_date = $_POST['audit_end_date'] ?? '';
            $status = $_POST['audit_status'] ?? 'Planlandı';
            $f_consult_company_id = !empty($_POST['consult_id']) ? intval($_POST['consult_id']) : null;
            $auditorsStr = $_POST['auditors'] ?? '';
            $auditorIds = !empty($auditorsStr) ? explode(',', $auditorsStr) : [];
            $link = trim($_POST['audit_link'] ?? '');
            $participantsJson = $_POST['participants'] ?? '[]';
            $participants = json_decode($participantsJson, true);

            $f_cert_id = null;
            $audit_certtification_no = ''; 
            
            // --- YENİ MANTIK: Sadece "Ara Tetkik"te Bağlı Sertifika Zorunlu ---
            if ($audit_type === 'ara') {
                $cert_db_id = intval($_POST['linked_cert_id'] ?? 0);
                if($cert_db_id <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Lütfen mevcut bir sertifika seçiniz.']); exit();
                }
                
                $stmtCert = $db->prepare("SELECT c.certno, c.f_cert_id FROM certification c WHERE c.id = ?");
                $stmtCert->execute([$cert_db_id]);
                $certData = $stmtCert->fetch(PDO::FETCH_ASSOC);
                
                if(!$certData) {
                    echo json_encode(['status' => 'error', 'message' => 'Seçilen sertifika bulunamadı.']); exit();
                }
                
                $audit_certtification_no = $certData['certno'] ?? '';
                $f_cert_id = $certData['f_cert_id'] ?? null;
            
            } else {
                // İlk Belgelendirme (ilk) VEYA Yeniden Belgelendirme (yenileme) -> Soyut Belge Türü Seçilmeli
                $f_cert_id = intval($_POST['cert_type_id'] ?? 0);
                if($f_cert_id <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Lütfen planlanan belge türünü seçiniz.']); exit();
                }
            }

            if ($f_company_id <= 0 || empty($start_date) || empty($end_date) || empty($auditorIds)) {
                echo json_encode(['status' => 'error', 'message' => 'Lütfen zorunlu alanları (Firma, Tarihler, En az bir Denetçi) doldurun.']);
                exit();
            }

            // Çakışma Kontrolü
            $currentPlanId = ($action === 'update') ? intval($_POST['id'] ?? 0) : 0;
            foreach($auditorIds as $audId) {
                $checkSql = "SELECT count(*) FROM planning p 
                             JOIN planning_auditor pa ON p.id = pa.f_planning_id 
                             WHERE pa.f_auditor_id = ? 
                             AND p.id != ? 
                             AND (p.audit_publish_date <= ? AND p.audit_end_date >= ?)
                             AND p.audit_status != 'İptal'";

                $stmtCheck = $db->prepare($checkSql);
                $stmtCheck->execute([$audId, $currentPlanId, $end_date, $start_date]);
                
                if ($stmtCheck->fetchColumn() > 0) {
                    $stmtAudName = $db->prepare("SELECT name FROM user WHERE id = ?");
                    $stmtAudName->execute([$audId]);
                    $audName = $stmtAudName->fetchColumn();
                    echo json_encode(['status' => 'error', 'message' => "ÇAKIŞMA HATASI: $audName isimli denetçi, seçilen tarih aralığında başka bir denetimde görevli!"]);
                    exit();
                }
            }

            if ($action === 'add') {
                $sql = "INSERT INTO planning (f_company_id, f_cert_id, audit_publish_date, audit_end_date, audit_status, audit_certtification_no, f_consult_company_id, audit_link, is_auto_generated, audit_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$f_company_id, $f_cert_id, $start_date, $end_date, $status, $audit_certtification_no, $f_consult_company_id, $link, $audit_type])) {
                    $newId = $db->lastInsertId();
                    
                    $stmtPA = $db->prepare("INSERT INTO planning_auditor (f_planning_id, f_auditor_id) VALUES (?, ?)");
                    foreach($auditorIds as $aId) { $stmtPA->execute([$newId, intval($aId)]); }
                    
                    if (!empty($participants) && is_array($participants)) {
                        $stmtPart = $db->prepare("INSERT INTO participant (f_planning_id, name, email) VALUES (?, ?, ?)");
                        foreach ($participants as $p) {
                            if (!empty($p['name']) && !empty($p['email'])) { $stmtPart->execute([$newId, $p['name'], $p['email']]); }
                        }
                    }
                    
                    $stmtCN = $db->prepare("SELECT c_name FROM company WHERE id = ?");
                    $stmtCN->execute([$f_company_id]);
                    $logCompName = $stmtCN->fetchColumn();
                    $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, ?, 0, 'Denetim Planlama', ?)");
                    $logStmt->execute([$_SESSION['user_id'], $f_company_id, "Yeni denetim planlandı: $logCompName ($start_date)"]);
                    
                    echo json_encode(['status' => 'success', 'message' => 'Denetim planı başarıyla oluşturuldu.']);
                } else { echo json_encode(['status' => 'error', 'message' => 'Kayıt hatası.']); }
                exit();
            }

            if ($action === 'update') {
                $id = intval($_POST['id'] ?? 0);
                $sql = "UPDATE planning SET f_company_id=?, f_cert_id=?, audit_publish_date=?, audit_end_date=?, audit_status=?, audit_certtification_no=?, f_consult_company_id=?, audit_link=?, audit_type=? WHERE id=?";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$f_company_id, $f_cert_id, $start_date, $end_date, $status, $audit_certtification_no, $f_consult_company_id, $link, $audit_type, $id])) {
                    
                    $db->prepare("DELETE FROM planning_auditor WHERE f_planning_id = ?")->execute([$id]);
                    $stmtPA = $db->prepare("INSERT INTO planning_auditor (f_planning_id, f_auditor_id) VALUES (?, ?)");
                    foreach($auditorIds as $aId) { $stmtPA->execute([$id, intval($aId)]); }

                    $db->prepare("DELETE FROM participant WHERE f_planning_id = ?")->execute([$id]);
                    if (!empty($participants) && is_array($participants)) {
                        $stmtPart = $db->prepare("INSERT INTO participant (f_planning_id, name, email) VALUES (?, ?, ?)");
                        foreach ($participants as $p) {
                            if (!empty($p['name']) && !empty($p['email'])) { $stmtPart->execute([$id, $p['name'], $p['email']]); }
                        }
                    }

                    $logStmt = $db->prepare("INSERT INTO general_log (user_id, company_id, cert_id, log_type, content) VALUES (?, ?, 0, 'Denetim Planlama', ?)");
                    $logStmt->execute([$_SESSION['user_id'], $f_company_id, "Denetim güncellendi: $id"]);
                    echo json_encode(['status' => 'success', 'message' => 'Denetim planı başarıyla güncellendi.']);
                } else { echo json_encode(['status' => 'error', 'message' => 'Güncelleme hatası.']); }
                exit();
            }
        }

    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit(); }
}

// --- SAYFA VERİLERİ (İLK YÜKLEME) ---
try {
    // Filtre Değişkenleri
    $filterStartDate = $_GET['start_date'] ?? '';
    $filterEndDate = $_GET['end_date'] ?? '';
    $filterStatus = $_GET['audit_status'] ?? '';
    $filterAuditType = $_GET['audit_type'] ?? '';
    $filterAuditorId = $_GET['auditor_id'] ?? '';
    $filterCompanyId = $_GET['company_id'] ?? '';

    $companies = $db->query("SELECT id, c_name FROM company ORDER BY c_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $certTypes = $db->query("SELECT id, name FROM cert ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $consultants = $db->query("SELECT id, c_name FROM consult_company ORDER BY c_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $auditors = $db->query("SELECT id, name FROM user WHERE role_code='auditor' AND status='A' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $emailTemplates = $db->query("SELECT id, `subject` FROM email_template")->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- LİSTELEME SORGUSU (İLK YÜKLEME) ---
    $sqlList = "SELECT 
                    p.id, 
                    c.c_name as company_name, 
                    ct.name as cert_type_name, 
                    p.audit_publish_date, 
                    p.audit_end_date, 
                    p.audit_status, 
                    p.audit_certtification_no,
                    p.audit_type,
                    GROUP_CONCAT(u.name SEPARATOR ', ') as auditor_name,
                    DATEDIFF(p.audit_publish_date, CURDATE()) as days_remaining 
                FROM planning p
                LEFT JOIN company c ON p.f_company_id = c.id
                LEFT JOIN cert ct ON p.f_cert_id = ct.id
                LEFT JOIN planning_auditor pa ON p.id = pa.f_planning_id
                LEFT JOIN user u ON pa.f_auditor_id = u.id
                WHERE 1=1";
                
    $params = [];

    if ($isAuditor) {
        $sqlList .= " AND p.id IN (SELECT f_planning_id FROM planning_auditor pa2 JOIN user u2 ON pa2.f_auditor_id = u2.id WHERE u2.username = ?)";
        $params[] = $username;
    }

    if (!empty($filterStartDate)) { $sqlList .= " AND p.audit_publish_date >= ?"; $params[] = $filterStartDate; }
    if (!empty($filterEndDate)) { $sqlList .= " AND p.audit_publish_date <= ?"; $params[] = $filterEndDate; }
    if (!empty($filterStatus)) { $sqlList .= " AND p.audit_status = ?"; $params[] = $filterStatus; }
    if (!empty($filterAuditType)) { $sqlList .= " AND p.audit_type = ?"; $params[] = $filterAuditType; }
    if (!$isAuditor && !empty($filterAuditorId)) { $sqlList .= " AND pa.f_auditor_id = ?"; $params[] = $filterAuditorId; }
    if (!empty($filterCompanyId)) { $sqlList .= " AND p.f_company_id = ?"; $params[] = $filterCompanyId; }

    $sqlList .= " GROUP BY p.id ORDER BY p.audit_publish_date ASC";
    
    $stmt = $db->prepare($sqlList);
    $stmt->execute($params);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $error = $e->getMessage(); }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Denetim Planlama</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>

    <style>
        /* STİLLER AYNI (Kısaltıldı) */
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545; --dropdown-hover: #f1f3f5; --dropdown-shadow: rgba(0,0,0,0.1); }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .add-button { padding: 12px 25px; font-size: 1rem; font-weight: 500; color: #fff; background-color: var(--primary-color); border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .action-buttons-wrapper { display: flex; gap: 5px; justify-content: flex-end; }
        .action-button { padding: 6px 12px; font-size: 0.85rem; font-weight: 500; border: none; border-radius: 4px; cursor: pointer; color: #fff; margin-right: 5px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .update { background-color: var(--primary-color); }
        .delete { background-color: var(--danger-color); }
        .email-button { background-color: #f39c12; }
        .search-input { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .view-toggle { display: flex; gap: 10px; margin-bottom: 20px; }
        .view-btn { padding: 10px 20px; border: 1px solid var(--border-color); background: #fff; cursor: pointer; border-radius: 4px; font-weight: 500; transition: 0.2s; }
        .view-btn.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        #filterPanel { background: #f8f9fa; padding: 20px; border: 1px solid #e9ecef; border-radius: 6px; margin-bottom: 20px; display: block; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; color: #555; }
        .filter-control { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .btn-apply { background-color: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; width: 100%; }
        .btn-clear { background-color: var(--secondary-color); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 500; width: 100%; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box;}
        #calendar { max-width: 100%; margin: 0 auto; display: none; min-height: 600px; }
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_filter input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; margin-left: 10px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--card-background); margin: 2% auto; padding: 30px; border-radius: 8px; width: 800px; max-width: 95%; box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .close-button { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .form-grid-modal { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: 1 / -1; } 
        .form-field { margin-bottom: 15px; }
        .form-field label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        .required { color: var(--danger-color); }
        .form-control, .form-field input, .form-field textarea, .form-field select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; }
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 1rem; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px var(--dropdown-shadow); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content input { box-sizing: border-box; width: 95%; margin: 5px 2.5%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.95rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        .auditor-selection-box { border: 1px solid #e9ecef; padding: 10px; border-radius: 4px; background: #fcfcfc; min-height: 45px; display: flex; flex-wrap: wrap; gap: 5px; }
        .selected-auditor { display: inline-flex; align-items: center; background: #e8f0fe; color: #1967d2; padding: 5px 10px; border-radius: 15px; font-size: 0.9rem; }
        .selected-auditor .remove-auditor { margin-left: 8px; cursor: pointer; font-weight: bold; color: #d93025; display: flex; align-items: center; justify-content: center; width: 16px; height: 16px; }
        .participant-row { display: flex; gap: 10px; margin-bottom: 5px; align-items: center; }
        .remove-part { color: red; cursor: pointer; font-size: 1.2rem; font-weight: bold; }
        .modal-footer { text-align: right; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px; }
        .modal-button { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; color: #fff; }
        .modal-button.cancel { background-color: var(--secondary-color); margin-right: 10px; }
        .modal-button.save { background-color: var(--success-color); }
        .attachment-list { list-style: none; margin: 0; padding: 0; }
        .attachment-item { display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 8px 12px; margin-bottom: 5px; border-radius: 4px; border: 1px solid #eee; }
        .attachment-name { font-size: 0.9rem; color: #333; }
        .remove-attachment { color: #dc3545; cursor: pointer; font-weight: bold; font-size: 0.9rem; transition: color 0.2s; }
        .remove-attachment:hover { color: #a71d2a; }
        .new-file-badge { background: #28a745; color: #fff; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 8px; }
        .fa-envelope:before { content: "\f0e0"; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">Denetim Planlama</h1>
        <?php if ($canEdit): ?>
            <button class="add-button" onclick="openModal('add')"><i class="fa fa-plus"></i> Yeni Planlama</button>
        <?php endif; ?>
    </div>

    <div class="view-toggle">
        <button class="view-btn active" onclick="switchView('list')"><i class="fa fa-list"></i> Liste Görünümü</button>
        <button class="view-btn" onclick="switchView('calendar')"><i class="fa fa-calendar-alt"></i> Takvim Görünümü</button>
    </div>
    
    <form id="filterForm" class="filter-section">
        <div class="filter-grid">
            <div class="filter-group">
                <label>Başlangıç Tarihi</label>
                <input type="date" name="start_date" id="f_start_date" class="filter-control" value="<?php echo $filterStartDate; ?>">
            </div>
            <div class="filter-group">
                <label>Bitiş Tarihi</label>
                <input type="date" name="end_date" id="f_end_date" class="filter-control" value="<?php echo $filterEndDate; ?>">
            </div>
            
            <div class="filter-group">
                <label>Firma</label>
                <div class="dropdown">
                    <input type="hidden" name="company_id" id="f_company_id" value="<?php echo $filterCompanyId; ?>">
                    <button type="button" id="f_companyBtn" onclick="toggleDropdown('f_companyDropdown')" class="dropbtn" style="width:100%">Tümü <span class="arrow-down">&#9662;</span></button>
                    <div id="f_companyDropdown" class="dropdown-content">
                        <input type="text" placeholder="Ara..." onkeyup="filterDropdown('f_companyDropdown')">
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_company_id', '', 'Tümü', 'f_companyDropdown')">Tümü</a>
                        <?php foreach($companies as $c): ?>
                            <a href="javascript:void(0)" onclick="selectFilterOption('f_company_id', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['c_name']); ?>', 'f_companyDropdown')"><?php echo htmlspecialchars($c['c_name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <label>Denetim Türü</label>
                <div class="dropdown">
                    <input type="hidden" name="audit_type" id="f_audit_type" value="<?php echo $filterAuditType; ?>">
                    <button type="button" id="f_auditTypeBtn" onclick="toggleDropdown('f_auditTypeDropdown')" class="dropbtn" style="width:100%">Tümü <span class="arrow-down">&#9662;</span></button>
                    <div id="f_auditTypeDropdown" class="dropdown-content">
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_type', '', 'Tümü', 'f_auditTypeDropdown')">Tümü</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_type', 'ilk', 'İlk Belgelendirme', 'f_auditTypeDropdown')">İlk Belgelendirme</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_type', 'ara', 'Ara Tetkik (Gözetim)', 'f_auditTypeDropdown')">Ara Tetkik (Gözetim)</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_type', 'yenileme', 'Yeniden Belgelendirme', 'f_auditTypeDropdown')">Yeniden Belgelendirme</a>
                    </div>
                </div>
            </div>

            <?php if(!$isAuditor): ?>
            <div class="filter-group">
                <label>Denetçi</label>
                <div class="dropdown">
                    <input type="hidden" name="auditor_id" id="f_auditor_id" value="<?php echo $filterAuditorId; ?>">
                    <button type="button" id="f_auditorBtn" onclick="toggleDropdown('f_auditorDropdown')" class="dropbtn" style="width:100%">Tümü <span class="arrow-down">&#9662;</span></button>
                    <div id="f_auditorDropdown" class="dropdown-content">
                        <input type="text" placeholder="Ara..." onkeyup="filterDropdown('f_auditorDropdown')">
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_auditor_id', '', 'Tümü', 'f_auditorDropdown')">Tümü</a>
                        <?php foreach($auditors as $aud): ?>
                            <a href="javascript:void(0)" onclick="selectFilterOption('f_auditor_id', '<?php echo $aud['id']; ?>', '<?php echo htmlspecialchars($aud['name']); ?>', 'f_auditorDropdown')"><?php echo htmlspecialchars($aud['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <label>Durum</label>
                <div class="dropdown">
                    <input type="hidden" name="audit_status" id="f_audit_status" value="<?php echo $filterStatus; ?>">
                    <button type="button" id="f_auditStatusBtn" onclick="toggleDropdown('f_auditStatusDropdown')" class="dropbtn" style="width:100%">Tümü <span class="arrow-down">&#9662;</span></button>
                    <div id="f_auditStatusDropdown" class="dropdown-content">
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_status', '', 'Tümü', 'f_auditStatusDropdown')">Tümü</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_status', 'Planlanacak', 'Planlanacak (Taslak)', 'f_auditStatusDropdown')">Planlanacak (Taslak)</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_status', 'Planlandı', 'Planlandı', 'f_auditStatusDropdown')">Planlandı</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_status', 'Gerçekleşti', 'Gerçekleşti', 'f_auditStatusDropdown')">Gerçekleşti</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_status', 'Ertelendi', 'Ertelendi', 'f_auditStatusDropdown')">Ertelendi</a>
                        <a href="javascript:void(0)" onclick="selectFilterOption('f_audit_status', 'İptal', 'İptal', 'f_auditStatusDropdown')">İptal</a>
                    </div>
                </div>
            </div>
            
            <div class="filter-group">
                <button type="button" class="btn-apply" onclick="applyFilters(true)"><i class="fa fa-check"></i> Uygula</button>
            </div>
            <div class="filter-group">
                <button type="button" class="btn-clear" onclick="resetFilters()"><i class="fa fa-times"></i> Temizle</button>
            </div>
        </div>
    </form>

    <div id="listView">
        <input type="text" id="customSearch" class="search-input" placeholder="Firma, denetçi veya durum ara...">
        <div class="table-responsive">
            <table id="planTable">
                <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Denetim Türü</th>
                        <th>Belge Türü</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Denetçi</th>
                        <th>Durum</th>
                        <th>Kalan Süre</th>
                        <th>Belge No</th>
                        <th style="width: 180px; text-align:right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($plans)): ?>
                        <?php foreach ($plans as $plan): ?>
                        <tr id="row-<?php echo $plan['id']; ?>">
                            <td><?php echo htmlspecialchars($plan['company_name'] ?? ''); ?></td>
                            <td>
                                <?php 
                                    $t = $plan['audit_type'] ?? 'ilk';
                                    if($t == 'ilk') echo 'İlk Belgelendirme';
                                    elseif($t == 'ara') echo 'Ara Tetkik';
                                    elseif($t == 'yenileme') echo 'Yeniden Belgelendirme';
                                    else echo $t;
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($plan['cert_type_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($plan['audit_publish_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($plan['audit_end_date'] ?? ''); ?></td>
                            <td>
                                <?php echo !empty($plan['auditor_name']) ? htmlspecialchars($plan['auditor_name']) : '<span style="color:red; font-style:italic;">Atanmadı</span>'; ?>
                            </td>
                            <td>
                                <?php 
                                    $st = $plan['audit_status'] ?? '';
                                    $color = '#6c757d';
                                    if ($st == 'Gerçekleşti') $color = 'green';
                                    elseif ($st == 'Planlanacak') $color = 'purple';
                                    elseif ($st == 'İptal') $color = 'red';
                                    elseif ($st == 'Planlandı') $color = 'orange';
                                    
                                    echo "<span style='color:$color; font-weight:bold;'>".htmlspecialchars($st)."</span>";
                                ?>
                            </td>
                            <td>
                                <?php 
                                    if ($st == 'Gerçekleşti' || $st == 'İptal') {
                                        echo "-";
                                    } else {
                                        $today = new DateTime();
                                        $auditDate = new DateTime($plan['audit_publish_date']);
                                        $interval = $today->diff($auditDate);
                                        $days = $interval->days;
                                        if ($interval->invert == 1) {
                                            echo "<span style='color:red; font-weight:bold;'>Süresi Geçti!</span>";
                                        } else {
                                            if ($days < 30) echo "<span style='color:#d35400; font-weight:bold;'>$days Gün Kaldı</span>";
                                            else echo "<span style='color:green;'>$days Gün Kaldı</span>";
                                        }
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($plan['audit_certtification_no'] ?? '-'); ?></td>
                            
                            <td style="text-align: right;">
                                <div class="action-buttons-wrapper">
                                    <button class="action-button email-button" onclick="openEmailModal('<?php echo $plan['id']; ?>', '<?php echo $plan['audit_type']; ?>')">
                                        <i class="fa fa-envelope"></i>
                                    </button>
                                    <?php if ($canEdit): ?>
                                        <button class="action-button update" onclick="openModal('update', <?php echo $plan['id']; ?>)">Güncelle</button>
                                        <button class="action-button delete" onclick="deletePlan(<?php echo $plan['id']; ?>)">Sil</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="calendar"></div>

</div>

<div id="planModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle"></h2>
            <span class="close-button" onclick="closeModal()">&times;</span>
        </div>
        <form id="planForm" onsubmit="submitForm(event)">
            <input type="hidden" id="action" name="action">
            <input type="hidden" id="planId" name="id">

            <div class="form-grid-modal">
                <div class="form-field">
                    <label class="required">Firma</label>
                    <div class="dropdown">
                        <input type="hidden" id="company_id" name="company_id" required onchange="loadCompanyCerts(this.value)">
                        <button type="button" id="companyBtn" onclick="toggleDropdown('companyDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="companyDropdown" class="dropdown-content">
                            <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('companyDropdown')">
                            <?php foreach ($companies as $c): ?>
                                <a href="javascript:void(0)" onclick="selectOption('company_id', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['c_name']); ?>', 'companyDropdown'); loadCompanyCerts('<?php echo $c['id']; ?>')" data-id="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['c_name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label class="required">Denetim Türü</label>
                    <div class="dropdown">
                        <input type="hidden" id="audit_type" name="audit_type" value="ilk" onchange="toggleCertSelection()">
                        <button type="button" id="auditTypeBtn" onclick="toggleDropdown('auditTypeDropdown')" class="dropbtn">İlk Belgelendirme <span class="arrow-down">&#9662;</span></button>
                        <div id="auditTypeDropdown" class="dropdown-content">
                            <a href="javascript:void(0)" onclick="selectOption('audit_type', 'ilk', 'İlk Belgelendirme', 'auditTypeDropdown'); toggleCertSelection();" data-id="ilk">İlk Belgelendirme</a>
                            <a href="javascript:void(0)" onclick="selectOption('audit_type', 'ara', 'Ara Tetkik (Gözetim)', 'auditTypeDropdown'); toggleCertSelection();" data-id="ara">Ara Tetkik (Gözetim)</a>
                            <a href="javascript:void(0)" onclick="selectOption('audit_type', 'yenileme', 'Yeniden Belgelendirme', 'auditTypeDropdown'); toggleCertSelection();" data-id="yenileme">Yeniden Belgelendirme</a>
                        </div>
                    </div>
                </div>

                <div class="form-field" id="linkedCertDiv" style="display:none;">
                    <label class="required" style="color:#d93025;">Bağlı Sertifika (Zorunlu)</label>
                    <div class="dropdown">
                        <input type="hidden" id="linked_cert_id" name="linked_cert_id">
                        <button type="button" id="linkedCertBtn" onclick="toggleDropdown('linkedCertDropdown')" class="dropbtn">Önce Firma Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="linkedCertDropdown" class="dropdown-content">
                            <a href="javascript:void(0)" style="color:#999; cursor:default;">Lütfen önce firma seçin</a>
                        </div>
                    </div>
                </div>

                <div class="form-field" id="manualCertDiv">
                    <label class="required">Planlanan Belge Türü</label>
                    <div class="dropdown">
                        <input type="hidden" id="cert_type_id" name="cert_type_id">
                        <button type="button" id="certTypeBtn" onclick="toggleDropdown('certTypeDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="certTypeDropdown" class="dropdown-content">
                             <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('certTypeDropdown')">
                            <?php foreach ($certTypes as $ct): ?>
                                <a href="javascript:void(0)" onclick="selectOption('cert_type_id', '<?php echo $ct['id']; ?>', '<?php echo htmlspecialchars($ct['name']); ?>', 'certTypeDropdown')" data-id="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label class="required">Başlangıç Tarihi</label>
                    <input type="date" id="audit_publish_date" name="audit_publish_date" required>
                </div>
                <div class="form-field">
                    <label class="required">Bitiş Tarihi</label>
                    <input type="date" id="audit_end_date" name="audit_end_date" required>
                </div>

                <div class="form-field" style="grid-column: 1 / -1;">
                    <label class="required">Atanan Denetçiler (Birden fazla seçilebilir)</label>
                    <div class="dropdown">
                        <button type="button" onclick="toggleDropdown('auditorDropdown')" class="dropbtn" id="auditorBtn">Denetçi Ekle... <span class="arrow-down">&#9662;</span></button>
                        <div id="auditorDropdown" class="dropdown-content">
                             <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('auditorDropdown')">
                             <?php foreach ($auditors as $aud): ?>
                                <a href="javascript:void(0)" onclick="addAuditor('<?php echo $aud['id']; ?>', '<?php echo htmlspecialchars($aud['name']); ?>')" data-id="<?php echo $aud['id']; ?>"><?php echo htmlspecialchars($aud['name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="selectedAuditorsBox" class="auditor-selection-box"></div>
                    <input type="hidden" id="auditorsInput" name="auditors">
                </div>
                
                <div class="form-field">
                    <label>Durum</label>
                    <div class="dropdown">
                        <input type="hidden" id="audit_status" name="audit_status" value="Planlandı">
                        <button type="button" id="statusBtn" onclick="toggleDropdown('statusDropdown')" class="dropbtn">Planlandı <span class="arrow-down">&#9662;</span></button>
                        <div id="statusDropdown" class="dropdown-content">
                            <a href="javascript:void(0)" onclick="selectOption('audit_status', 'Planlanacak', 'Planlanacak (Taslak)', 'statusDropdown')" data-id="Planlanacak" style="color:purple">Planlanacak (Taslak)</a>
                            <a href="javascript:void(0)" onclick="selectOption('audit_status', 'Planlandı', 'Planlandı', 'statusDropdown')" data-id="Planlandı">Planlandı</a>
                            <a href="javascript:void(0)" onclick="selectOption('audit_status', 'Gerçekleşti', 'Gerçekleşti', 'statusDropdown')" data-id="Gerçekleşti">Gerçekleşti</a>
                            <a href="javascript:void(0)" onclick="selectOption('audit_status', 'Ertelendi', 'Ertelendi', 'statusDropdown')" data-id="Ertelendi">Ertelendi</a>
                            <a href="javascript:void(0)" onclick="selectOption('audit_status', 'İptal', 'İptal', 'statusDropdown')" data-id="İptal">İptal</a>
                        </div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Danışman Firma</label>
                    <div class="dropdown">
                        <input type="hidden" id="consult_id" name="consult_id">
                        <button type="button" id="consultBtn" onclick="toggleDropdown('consultDropdown')" class="dropbtn">Seçiniz... <span class="arrow-down">&#9662;</span></button>
                        <div id="consultDropdown" class="dropdown-content">
                            <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('consultDropdown')">
                            <a href="javascript:void(0)" onclick="selectOption('consult_id', '', 'Seçiniz...', 'consultDropdown')" style="color: #dc3545; font-style: italic; border-bottom: 1px solid #eee;">
                                <i class="fa fa-times-circle"></i> Seçimi Temizle
                            </a>
                             <?php foreach ($consultants as $cons): ?>
                                <a href="javascript:void(0)" onclick="selectOption('consult_id', '<?php echo $cons['id']; ?>', '<?php echo htmlspecialchars($cons['c_name']); ?>', 'consultDropdown')" data-id="<?php echo $cons['id']; ?>"><?php echo htmlspecialchars($cons['c_name']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                 <div class="form-field full-width">
                    <label class="required">Denetim Linki (Online/Konum)</label>
                    <input type="text" id="audit_link" name="audit_link" class="form-control" required>
                </div>

                <div class="form-field full-width" style="border-top:1px solid #eee; padding-top:10px;">
                    <label>Katılımcılar</label>
                    <div id="participantList"></div>
                    <button type="button" class="action-button update" style="margin-top:5px;" onclick="addParticipantRow()">+ Katılımcı Ekle</button>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-button cancel" onclick="closeModal()">İptal</button>
                <button type="submit" class="modal-button save">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<div id="emailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Denetim Bilgilendirme</h2>
            <span class="close-button" onclick="closeEmailModal()">&times;</span>
        </div>
        <div style="margin-bottom: 15px; background:#f1f3f5; padding:10px; border-radius:4px;">
            <strong>Denetçi Alıcılar: </strong><span id="emailRecipients"></span><br>
            <strong>Katılımcı Alıcılar: </strong><span id="participantRecipients"></span>
        </div>
        <form id="emailForm" enctype="multipart/form-data">
            <input type="hidden" id="planningId" name="planningId">
            <input type="hidden" id="templateId" name="templateId">
             <div class="form-field">
                <label>E-posta Şablonu</label>
                <div class="dropdown">
                    <button type="button" onclick="toggleDropdown('templateDropdown')" class="dropbtn" id="templateDropbtn">Şablon Seçiniz... <span class="arrow-down">&#9662;</span></button>
                    <div id="templateDropdown" class="dropdown-content">
                        <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('templateDropdown')">
                        <?php foreach ($emailTemplates as $id => $subj): ?>
                            <a href="javascript:void(0)" onclick="selectOption('templateId', '<?php echo $id; ?>', '<?php echo htmlspecialchars($subj); ?>', 'templateDropdown'); fetchTemplateData('<?php echo $id; ?>')" data-id="<?php echo $id; ?>"><?php echo htmlspecialchars($subj); ?></a>
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
                <label>Ekler:</label>
                <ul id="templateAttachments" class="attachment-list"></ul>
                <ul id="newAttachmentList" class="attachment-list"></ul>
                <div id="noAttachmentsMsg" style="display:none; color:#999; font-style:italic; font-size:0.85rem; text-align:center; padding:5px;">Dosya yok</div>
            </div>

            <div class="form-field">
                <label class="action-button" style="background-color:#17a2b8; cursor:pointer; display:inline-block; width:auto;">
                    <i class="fa fa-paperclip"></i> Yeni Dosya Ekle
                    <input type="file" id="newEmailAttachments" style="display:none;" multiple>
                </label>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-button cancel" onclick="closeEmailModal()">İptal</button>
                <button type="button" class="modal-button save" onclick="submitEmailForm()">Gönder</button>
            </div>
        </form>
    </div>
</div>

<script>
    const emailTemplates = <?php echo json_encode($emailTemplates); ?>;
    let removedAttachments = []; 
    let selectedNewFiles = [];
    let calendarInitialized = false;
    let selectedAuditors = [];
    let dataTable;

    $(document).ready(function() {
        // Initial State from URL
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('start_date')) document.getElementById('f_start_date').value = urlParams.get('start_date');
        if(urlParams.get('end_date')) document.getElementById('f_end_date').value = urlParams.get('end_date');
        
        if(urlParams.get('company_id')) {
            document.getElementById('f_company_id').value = urlParams.get('company_id');
            syncFilterDropdownUI('f_company_id', urlParams.get('company_id'), 'f_companyDropdown');
        }
        if(urlParams.get('audit_type')) {
            document.getElementById('f_audit_type').value = urlParams.get('audit_type');
            syncFilterDropdownUI('f_audit_type', urlParams.get('audit_type'), 'f_auditTypeDropdown');
        }
        if(urlParams.get('auditor_id')) {
            document.getElementById('f_auditor_id').value = urlParams.get('auditor_id');
            syncFilterDropdownUI('f_auditor_id', urlParams.get('auditor_id'), 'f_auditorDropdown');
        }
        if(urlParams.get('audit_status')) {
            document.getElementById('f_audit_status').value = urlParams.get('audit_status');
            syncFilterDropdownUI('f_audit_status', urlParams.get('audit_status'), 'f_auditStatusDropdown');
        }

        dataTable = $('#planTable').DataTable({
            "pageLength": 10,
            "dom": 'rtip',
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            "columnDefs": [ { "orderable": false, "targets": 9 } ], // İşlemler (index 9)
            "autoWidth": false
        });
        $('#customSearch').on('keyup', function() { dataTable.search(this.value).draw(); });
    });

    // --- POPSTATE EVENT LISTENER (BACK BUTTON) ---
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        
        document.getElementById('f_start_date').value = urlParams.get('start_date') || '';
        document.getElementById('f_end_date').value = urlParams.get('end_date') || '';
        
        const compId = urlParams.get('company_id') || '';
        document.getElementById('f_company_id').value = compId;
        syncFilterDropdownUI('f_company_id', compId, 'f_companyDropdown');

        const type = urlParams.get('audit_type') || '';
        document.getElementById('f_audit_type').value = type;
        syncFilterDropdownUI('f_audit_type', type, 'f_auditTypeDropdown');

        const audId = urlParams.get('auditor_id') || '';
        if(document.getElementById('f_auditor_id')) {
            document.getElementById('f_auditor_id').value = audId;
            syncFilterDropdownUI('f_auditor_id', audId, 'f_auditorDropdown');
        }

        const status = urlParams.get('audit_status') || '';
        document.getElementById('f_audit_status').value = status;
        syncFilterDropdownUI('f_audit_status', status, 'f_auditStatusDropdown');

        applyFilters(false);
    });

    function syncFilterDropdownUI(inputId, value, dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        let text = 'Tümü';
        if (value) {
            const links = dropdown.querySelectorAll('a');
            for (let link of links) {
                if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(`'${value}'`)) {
                    text = link.textContent.trim();
                    break;
                }
            }
        }
        const btnId = dropdownId.replace('Dropdown', 'Btn');
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
    }

    // --- FİLTRELEME (AJAX) ---
    function applyFilters(pushToHistory = true) {
        const start_date = document.getElementById('f_start_date').value;
        const end_date = document.getElementById('f_end_date').value;
        const companyId = document.getElementById('f_company_id').value;
        const auditType = document.getElementById('f_audit_type').value;
        const auditorId = document.getElementById('f_auditor_id') ? document.getElementById('f_auditor_id').value : '';
        const auditStatus = document.getElementById('f_audit_status').value;

        // URL Güncelleme
        const newUrl = new URL(window.location.href);
        if(start_date) newUrl.searchParams.set('start_date', start_date); else newUrl.searchParams.delete('start_date');
        if(end_date) newUrl.searchParams.set('end_date', end_date); else newUrl.searchParams.delete('end_date');
        if(companyId) newUrl.searchParams.set('company_id', companyId); else newUrl.searchParams.delete('company_id');
        if(auditType) newUrl.searchParams.set('audit_type', auditType); else newUrl.searchParams.delete('audit_type');
        if(auditorId) newUrl.searchParams.set('auditor_id', auditorId); else newUrl.searchParams.delete('auditor_id');
        if(auditStatus) newUrl.searchParams.set('audit_status', auditStatus); else newUrl.searchParams.delete('audit_status');

        if (pushToHistory) { 
            window.history.pushState({path: newUrl.href}, '', newUrl);
        } else {
            window.history.replaceState(null, '', newUrl);
        }

        // POST ile Veri Çek
        const fd = new FormData();
        fd.append('action', 'filter_audits');
        fd.append('start_date', start_date);
        fd.append('end_date', end_date);
        fd.append('company_id', companyId);
        fd.append('audit_type', auditType);
        fd.append('auditor_id', auditorId);
        fd.append('audit_status', auditStatus);

        fetch('audit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                dataTable.clear();

                res.data.forEach(row => {
                    let st = row.audit_status;
                    let color = '#6c757d';
                    if (st == 'Gerçekleşti') color = 'green';
                    else if (st == 'Planlanacak') color = 'purple';
                    else if (st == 'İptal') color = 'red';
                    else if (st == 'Planlandı') color = 'orange';
                    const statusHtml = `<span style='color:${color}; font-weight:bold;'>${st}</span>`;

                    let remainingHtml = '-';
                    if(st !== 'Gerçekleşti' && st !== 'İptal') {
                        const days = parseInt(row.days_remaining);
                        if(days < 0) remainingHtml = "<span style='color:red; font-weight:bold;'>Süresi Geçti!</span>";
                        else if(days < 30) remainingHtml = `<span style='color:#d35400; font-weight:bold;'>${days} Gün Kaldı</span>`;
                        else remainingHtml = `<span style='color:green;'>${days} Gün Kaldı</span>`;
                    }

                    let auditorDisplay = row.auditor_name ? row.auditor_name : '<span style="color:red; font-style:italic;">Atanmadı</span>';
                    
                    let typeDisplay = row.audit_type;
                    if(typeDisplay == 'ilk') typeDisplay = 'İlk Belgelendirme';
                    else if(typeDisplay == 'ara') typeDisplay = 'Ara Tetkik';
                    else if(typeDisplay == 'yenileme') typeDisplay = 'Yeniden Belgelendirme';

                    const actions = `
                        <div class="action-buttons-wrapper">
                            <button class="action-button email-button" onclick="openEmailModal('${row.id}', '${row.audit_type}')"><i class="fa fa-envelope"></i></button>
                            <?php if ($canEdit): ?>
                                <button class="action-button update" onclick="openModal('update', '${row.id}')">Güncelle</button>
                                <button class="action-button delete" onclick="deletePlan('${row.id}')">Sil</button>
                            <?php endif; ?>
                        </div>
                    `;

                    dataTable.row.add([
                        row.company_name || '',
                        typeDisplay,
                        row.cert_type_name || '',
                        row.audit_publish_date,
                        row.audit_end_date,
                        auditorDisplay,
                        statusHtml,
                        remainingHtml,
                        row.audit_certtification_no || '-',
                        actions
                    ]);
                });
                dataTable.draw();

                // Takvimi Güncelle (Eğer aktifse)
                if(calendarInitialized && document.getElementById('calendar').style.display !== 'none') {
                    initCalendar();
                }
            } else {
                alert(res.message || 'Bir hata oluştu.');
            }
        })
        .catch(err => console.error(err));
    }

    function resetFilters() {
        document.getElementById('f_start_date').value = '';
        document.getElementById('f_end_date').value = '';
        document.getElementById('f_company_id').value = '';
        document.getElementById('f_companyBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        document.getElementById('f_audit_type').value = '';
        document.getElementById('f_auditTypeBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        if(document.getElementById('f_auditor_id')) {
            document.getElementById('f_auditor_id').value = '';
            document.getElementById('f_auditorBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        }
        document.getElementById('f_audit_status').value = '';
        document.getElementById('f_auditStatusBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';

        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl); // PUSH STATE
        applyFilters(false);
    }

    // --- MEVCUT JS FONKSİYONLARI ---
    function selectFilterOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btnId = dropdownId.replace('Dropdown', 'Btn');
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        document.getElementById(dropdownId).classList.remove('show');
    }

    function switchView(view) {
        const listView = document.getElementById('listView');
        const calendarView = document.getElementById('calendar');
        const btns = document.querySelectorAll('.view-btn');
        btns.forEach(b => b.classList.remove('active'));
        if (view === 'list') {
            listView.style.display = 'block';
            calendarView.style.display = 'none';
            btns[0].classList.add('active');
        } else {
            listView.style.display = 'none';
            calendarView.style.display = 'block';
            btns[1].classList.add('active');
            if (!calendarInitialized) { initCalendar(); calendarInitialized = true; }
        }
    }

    function initCalendar() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
            locale: 'tr',
            events: function(info, successCallback, failureCallback) {
                var fd = new FormData();
                fd.append('action', 'get_calendar_events');
                const urlParams = new URLSearchParams(window.location.search);
                if(urlParams.has('start_date')) fd.append('start_date', urlParams.get('start_date'));
                if(urlParams.has('end_date')) fd.append('end_date', urlParams.get('end_date'));
                if(urlParams.has('audit_status')) fd.append('audit_status', urlParams.get('audit_status'));
                if(urlParams.has('audit_type')) fd.append('audit_type', urlParams.get('audit_type'));
                if(urlParams.has('auditor_id')) fd.append('auditor_id', urlParams.get('auditor_id'));
                if(urlParams.has('company_id')) fd.append('company_id', urlParams.get('company_id')); 
                
                fetch('audit.php', { method: 'POST', body: fd })
                .then(response => response.json()).then(data => successCallback(data)).catch(error => failureCallback(error));
            },
            eventClick: function(info) {
                <?php if ($canEdit): ?> openModal('update', info.event.id);
                <?php else: ?> alert('Bu planın detayı: ' + info.event.title); <?php endif; ?>
            }
        });
        calendar.render();
    }
    
    function toggleDropdown(id) { document.getElementById(id).classList.toggle("show"); }
    window.onclick = function(e) {
        if (!e.target.matches('.dropbtn') && !e.target.matches('.dropdown-content input')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) { if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show'); }
        }
        if(e.target == document.getElementById('planModal')) closeModal();
        if(e.target == document.getElementById('emailModal')) closeEmailModal();
    }
    document.addEventListener('keydown', function(event) { if (event.key === "Escape") { closeModal(); closeEmailModal(); } });

    function filterDropdown(id) {
        var input = document.querySelector("#" + id + " input");
        var filter = input.value.toUpperCase();
        var div = document.getElementById(id);
        var a = div.getElementsByTagName("a");
        for (var i = 0; i < a.length; i++) {
            if ((a[i].textContent || a[i].innerText).toUpperCase().indexOf(filter) > -1) { a[i].style.display = ""; } else { a[i].style.display = "none"; }
        }
    }

    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btn = document.getElementById(dropdownId).closest('.dropdown').querySelector('.dropbtn');
        btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        if(inputId === 'company_id') loadCompanyCerts(value);
    }

    function addAuditor(id, name) {
        if (selectedAuditors.some(a => a.id == id)) return;
        selectedAuditors.push({id: id, name: name});
        renderAuditors();
        document.getElementById('auditorDropdown').classList.remove('show'); 
    }

    window.removeAuditor = function(id) {
        selectedAuditors = selectedAuditors.filter(a => a.id != id);
        renderAuditors();
    }

    function renderAuditors() {
        const box = document.getElementById('selectedAuditorsBox');
        box.innerHTML = '';
        const ids = [];
        selectedAuditors.forEach(a => {
            ids.push(a.id);
            const span = document.createElement('span');
            span.className = 'selected-auditor';
            span.innerHTML = `${a.name} <span class="remove-auditor" onclick="removeAuditor('${a.id}')">&times;</span>`;
            box.appendChild(span);
        });
        document.getElementById('auditorsInput').value = ids.join(',');
    }

    function toggleCertSelection() {
        const type = document.getElementById('audit_type').value;
        // İŞ MANTIĞI GÜNCELLEMESİ (DÜZELTİLDİ): Sadece Ara Tetkik (ara) için Sertifika Zorunlu
        if (type === 'ara') {
            document.getElementById('linkedCertDiv').style.display = 'block';
            document.getElementById('manualCertDiv').style.display = 'none';
            document.getElementById('linked_cert_id').required = true;
            document.getElementById('cert_type_id').required = false;
        } else {
            // İlk Belgelendirme (ilk) VE Yeniden Belgelendirme (yenileme) -> Soyut Belge Türü
            document.getElementById('linkedCertDiv').style.display = 'none';
            document.getElementById('manualCertDiv').style.display = 'block';
            document.getElementById('linked_cert_id').required = false;
            document.getElementById('cert_type_id').required = true;
        }
    }

    function loadCompanyCerts(companyId, preSelectedId = null) {
        if(!companyId) return;
        const fd = new FormData();
        fd.append('action', 'get_company_certs');
        fd.append('company_id', companyId);

        fetch('audit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            const dropdown = document.getElementById('linkedCertDropdown');
            dropdown.innerHTML = ''; 
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach(c => {
                    const a = document.createElement('a');
                    a.href = 'javascript:void(0)';
                    a.setAttribute('data-id', c.id);
                    a.textContent = `${c.certno} - ${c.cert_name}`;
                    a.onclick = function(e) {
                         e.preventDefault();
                         selectOption('linked_cert_id', c.id, `${c.certno} - ${c.cert_name}`, 'linkedCertDropdown');
                    };
                    dropdown.appendChild(a);
                });
                if (preSelectedId) { setDropdownByValue('linked_cert_id', preSelectedId, 'linkedCertDropdown'); }
            } else {
                const a = document.createElement('a');
                a.href = 'javascript:void(0)';
                a.textContent = "Aktif Sertifika Bulunamadı";
                a.style.color = "#999";
                dropdown.appendChild(a);
            }
        });
    }

    function addParticipantRow(name='', email='') {
        const div = document.createElement('div');
        div.className = 'participant-row';
        div.innerHTML = `
            <input type="text" placeholder="Ad Soyad" class="form-control" value="${name}" style="flex:1">
            <input type="email" placeholder="E-posta" class="form-control" value="${email}" style="flex:1">
            <span class="remove-part" onclick="this.parentElement.remove()">&times;</span>
        `;
        document.getElementById('participantList').appendChild(div);
    }

    function openModal(type, id = 0) {
        const modal = document.getElementById('planModal');
        const form = document.getElementById('planForm');
        const title = document.getElementById('modalTitle');
        form.reset();
        document.getElementById('participantList').innerHTML = '';
        
        document.querySelectorAll('.dropbtn').forEach(btn => {
            if(btn.id.startsWith('filter_')) return;
            
            if(btn.id === 'statusBtn') btn.innerHTML = 'Planlandı <span class="arrow-down">&#9662;</span>';
            else if(btn.id === 'auditTypeBtn') btn.innerHTML = 'İlk Belgelendirme <span class="arrow-down">&#9662;</span>';
            else if(!btn.id.includes('auditor')) btn.innerHTML = 'Seçiniz... <span class="arrow-down">&#9662;</span>';
        });
        
        selectedAuditors = [];
        renderAuditors();
        const audBtn = document.getElementById('auditorBtn');
        if(audBtn) audBtn.innerHTML = 'Denetçi Ekle... <span class="arrow-down">&#9662;</span>';

        document.getElementById('action').value = type;
        document.getElementById('planId').value = id;
        document.getElementById('audit_type').value = 'ilk'; 
        toggleCertSelection();

        if (type === 'add') {
            document.getElementById('modalTitle').textContent = 'Yeni Denetim Planla';
            addParticipantRow();
        } else {
            document.getElementById('modalTitle').textContent = 'Planı Güncelle';
            const fd = new FormData();
            fd.append('action', 'get_plan');
            fd.append('id', id);
            
            fetch('audit.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if(res.status === 'success') {
                        const d = res.data;
                        document.getElementById('audit_publish_date').value = d.audit_publish_date;
                        document.getElementById('audit_end_date').value = d.audit_end_date;
                        document.getElementById('audit_link').value = d.audit_link;
                        
                        const typeMap = {'ilk': 'İlk Belgelendirme', 'ara': 'Ara Tetkik (Gözetim)', 'yenileme': 'Yeniden Belgelendirme'};
                        const typeName = typeMap[d.audit_type] || d.audit_type;
                        setDropdownByValue('audit_type', d.audit_type, 'auditTypeDropdown', typeName);
                        toggleCertSelection();

                        setDropdownByValue('audit_status', d.audit_status, 'statusDropdown');
                        
                        if(d.company_id) {
                            setDropdownByValue('company_id', d.company_id, 'companyDropdown');
                            if(d.audit_certtification_no) {
                                loadCompanyCerts(d.company_id, d.linked_cert_id);
                            } else {
                                loadCompanyCerts(d.company_id);
                                if(d.cert_type_id) setDropdownByValue('cert_type_id', d.cert_type_id, 'certTypeDropdown');
                            }
                        }
                        if(d.consult_id) setDropdownByValue('consult_id', d.consult_id, 'consultDropdown');
                        
                        if(d.auditor_ids && d.auditor_ids.length > 0) {
                            d.auditor_ids.forEach(audId => {
                                const auditorLink = document.querySelector(`#auditorDropdown a[data-id='${audId}']`);
                                if(auditorLink) {
                                    addAuditor(audId, auditorLink.textContent);
                                }
                            });
                        }

                        if(d.participants) { d.participants.forEach(p => addParticipantRow(p.name, p.email)); }
                    }
                });
        }
        modal.style.display = 'block';
    }
    
    function setDropdownByValue(inputId, value, dropdownId, explicitText = null) {
        if(!value) return;
        document.getElementById(inputId).value = value;
        
        if(explicitText) {
             const btn = document.getElementById(dropdownId).closest('.dropdown').querySelector('.dropbtn');
             btn.innerHTML = explicitText + ' <span class="arrow-down">&#9662;</span>';
             return;
        }

        const dropdown = document.getElementById(dropdownId);
        let link = dropdown.querySelector(`a[data-id='${value}']`);
        if(!link && dropdownId === 'statusDropdown') {
             const links = dropdown.querySelectorAll('a');
             for(let l of links) { if(l.textContent.trim() === value) { link = l; break; } }
        }
        if(link) {
             const btn = dropdown.closest('.dropdown').querySelector('.dropbtn');
             btn.innerHTML = link.textContent + ' <span class="arrow-down">&#9662;</span>';
        }
    }

    function closeModal() { document.getElementById('planModal').style.display = 'none'; }

    function submitForm(e) {
        e.preventDefault();
        const parts = [];
        document.querySelectorAll('.participant-row').forEach(row => {
            const inputs = row.querySelectorAll('input');
            if(inputs[0].value && inputs[1].value) { parts.push({name: inputs[0].value, email: inputs[1].value}); }
        });
        const fd = new FormData(e.target);
        fd.append('participants', JSON.stringify(parts));
        fetch('audit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') { alert(data.message); location.reload(); }
            else { alert('Hata: ' + data.message); }
        }).catch(err => { console.error(err); alert('Bir hata oluştu.'); });
    }

    function deletePlan(id) {
        if(confirm("Bu planı silmek istediğinize emin misiniz?")) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('audit.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') { alert(data.message); location.reload(); }
                else { alert(data.message); }
            });
        }
    }
    
    function openEmailModal(planId, auditType) {
        const modal = document.getElementById('emailModal');
        const form = document.getElementById('emailForm');
        form.reset();
        removedAttachments = []; selectedNewFiles = [];
        document.getElementById('templateAttachments').innerHTML = '';
        document.getElementById('newAttachmentList').innerHTML = '';
        document.getElementById('planningId').value = planId;
        document.getElementById('templateDropbtn').innerHTML = 'Şablon Seçiniz... <span class="arrow-down">&#9662;</span>';
        
        let templateIdToSelect = '';
        if(auditType === 'ilk') templateIdToSelect = 1; 
        else if(auditType === 'ara') templateIdToSelect = 2; 
        else if(auditType === 'yenileme') templateIdToSelect = 5; 
        
        if(templateIdToSelect) {
             const link = document.querySelector(`#templateDropdown a[data-id='${templateIdToSelect}']`);
             if(link) {
                 selectOption('templateId', templateIdToSelect, link.textContent, 'templateDropdown');
                 fetchTemplateData(templateIdToSelect);
             }
        }

        const fd = new FormData();
        fd.append('action', 'get_plan');
        fd.append('id', planId);
        fetch('audit.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            if(res.status === 'success') {
                const p = res.data;
                let recipients = [];
                let partRecipients = [];
                
                if(p.auditor_details) {
                    p.auditor_details.forEach(aud => recipients.push(aud.name + " (" + aud.email + ")"));
                }
                
                if(p.participants) {
                     p.participants.forEach(part => partRecipients.push(part.name + " (" + part.email + ")"));
                }
                
                document.getElementById('emailRecipients').innerText = recipients.join(', ');
                document.getElementById('participantRecipients').innerText = partRecipients.join(', ');
                modal.style.display = 'block';
            }
        });
    }
    
    function closeEmailModal() { document.getElementById('emailModal').style.display = 'none'; }
    
    function fetchTemplateData(id) {
        removedAttachments = []; 
        const fd = new FormData();
        fd.append('action', 'get_template_data');
        fd.append('id', id);
        fetch('audit.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => {
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
                        li.innerHTML = `<span class="attachment-name"><i class="fa fa-file-alt" style="color:#6c757d; margin-right: 8px;"></i> ${f.file_name}</span><span class="remove-attachment" onclick="removeTemplateAttachment(${f.id})"><i class="fa fa-times"></i></span>`;
                        ul.appendChild(li);
                    });
                }
            } else alert(res.message);
        });
    }

    function removeTemplateAttachment(id) { removedAttachments.push(id); document.getElementById('att-tmpl-' + id).style.display = 'none'; }
    
    const fileInput = document.getElementById('newEmailAttachments');
    if(fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = Array.from(this.files);
            files.forEach(file => { selectedNewFiles.push(file); });
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
            li.innerHTML = `<span class="attachment-name"><i class="fa fa-file-upload" style="color:#28a745; margin-right: 8px;"></i> ${file.name} <span class="new-file-badge">YENİ</span></span><span class="remove-attachment" onclick="removeNewFile(${index})"><i class="fa fa-times"></i></span>`;
            list.appendChild(li);
        });
    }

    function removeNewFile(index) { selectedNewFiles.splice(index, 1); renderNewFiles(); }
    
    function submitEmailForm() {
        const form = document.getElementById('emailForm');
        const fd = new FormData(form);
        if(removedAttachments.length > 0) fd.append('removed_attachment_ids', removedAttachments.join(','));
        selectedNewFiles.forEach(file => fd.append('new_attachments[]', file));

        const btn = document.querySelector('#emailModal .modal-footer .save');
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Gönderiliyor...';

        fetch('send_email.php', { method: 'POST', body: fd })
        .then(r => {
            if (!r.ok) throw new Error("Sunucu hatası");
            return r.text().then(text => {
                try { return JSON.parse(text); } 
                catch (e) { throw new Error("Sunucudan geçersiz yanıt: " + text); }
            });
        })
        .then(res => {
            btn.disabled = false;
            btn.innerText = originalText;
            if(res.success) {
                alert(res.message);
                closeEmailModal();
            } else {
                alert('Hata: ' + res.message);
            }
        })
        .catch(err => { 
            btn.disabled = false;
            btn.innerText = originalText;
            console.error(err); 
            alert('Bir hata oluştu: ' + err.message); 
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

    
</script>
</body>
</html>