<?php
session_start();
// Hata gizleme
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // ... (Giriş değişkenleri aynı) ...
        $planningId = intval($_POST['planningId'] ?? 0);
        $companyId = intval($_POST['emailCompanyId'] ?? 0);
        $templateId = intval($_POST['templateId'] ?? 0);
        $originalSubject = trim($_POST['template_subject_id'] ?? '');
        
        $rawBody = trim($_POST['template_body_subject'] ?? '');
        $originalBody = nl2br($rawBody); 

        // ... (Dosya ekleme işlemleri aynı) ...
        // ...
        $removedIdsInput = $_POST['removed_attachment_ids'] ?? '';
        $removedAttachmentIds = $removedIdsInput ? explode(',', $removedIdsInput) : [];

        $sql = "SELECT * FROM email_settings LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $email_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$email_settings) { 
            echo json_encode(['success' => false, 'message' => 'E-posta ayarları bulunamadı.']); 
            exit(); 
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $email_settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_settings['smtp_username'];
        $mail->Password = $email_settings['smtp_password'];
        $mail->SMTPSecure = $email_settings['smtp_secure'];
        $mail->Port = $email_settings['smtp_port']; 
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($email_settings['from_email'], $email_settings['from_name']);
        $mail->isHTML(true); // HTML formatı zorunlu

        // --- DOSYA EKLERİ (Aynı) ---
        if ($templateId > 0) {
            $sqlAtt = "SELECT id, file_path, file_name FROM email_attachment WHERE FK_email_template_id = ?";
            $stmtAtt = $db->prepare($sqlAtt);
            $stmtAtt->execute([$templateId]);
            $attachments = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($attachments as $attachment) {
                if (!in_array($attachment['id'], $removedAttachmentIds)) {
                    if (file_exists($attachment['file_path'])) {
                        $mail->addAttachment($attachment['file_path'], $attachment['file_name']);
                    }
                }
            }
        }
        if (isset($_FILES['new_attachments']) && count($_FILES['new_attachments']['name']) > 0) {
            for ($i = 0; $i < count($_FILES['new_attachments']['name']); $i++) {
                if ($_FILES['new_attachments']['error'][$i] == UPLOAD_ERR_OK) {
                    $tmpFilePath = $_FILES['new_attachments']['tmp_name'][$i];
                    $fileName = $_FILES['new_attachments']['name'][$i];
                    $mail->addAttachment($tmpFilePath, $fileName);
                }
            }
        }

        if ($planningId > 0) {
            $stmt = $db->prepare("SELECT * FROM planning WHERE id = ?");
            $stmt->execute([$planningId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$plan) { echo json_encode(['success' => false, 'message' => 'Plan bulunamadı.']); exit(); }

            $recipients = [];
            
            $stmtAuds = $db->prepare("SELECT u.name, u.email FROM planning_auditor pa LEFT JOIN user u ON pa.f_auditor_id = u.id WHERE pa.f_planning_id = ?");
            $stmtAuds->execute([$planningId]);
            $auditors = $stmtAuds->fetchAll(PDO::FETCH_ASSOC);
            foreach($auditors as $aud) {
                if(!empty($aud['email'])) $recipients[] = ['email' => $aud['email'], 'name' => $aud['name']];
            }
            
            $stmtPart = $db->prepare("SELECT name, email FROM participant WHERE f_planning_id = ?");
            $stmtPart->execute([$planningId]);
            $participants = $stmtPart->fetchAll(PDO::FETCH_ASSOC);
            foreach($participants as $part) {
                if(!empty($part['email'])) $recipients[] = ['email' => $part['email'], 'name' => $part['name']];
            }
            
            if(empty($recipients)) { echo json_encode(['success' => false, 'message' => 'Alıcı bulunamadı.']); exit(); }

            foreach($recipients as $rcpt) {
                $mail->clearAddresses(); 
                $mail->addAddress($rcpt['email'], $rcpt['name']);

                $personalBody = $originalBody;
                $personalSubject = $originalSubject;

                $personalBody = str_ireplace('Sayın Yetkili', 'Sayın ' . $rcpt['name'], $personalBody);
                $personalBody = str_replace('{ad_soyad}', $rcpt['name'], $personalBody);

                $placeholders = [
                    '{firma_adi}' => $plan['company_name'] ?? '',
                    '{baslangic_tarihi}' => $plan['audit_publish_date'],
                    '{bitis_tarihi}' => $plan['audit_end_date'],
                    '{denetim_linki}' => $plan['audit_link'],
                    '{belge_numarasi}' => $plan['audit_certtification_no']
                ];
                
                if(empty($placeholders['{firma_adi}']) && !empty($plan['f_company_id'])) {
                     $stmtCN = $db->prepare("SELECT c_name FROM company WHERE id = ?");
                     $stmtCN->execute([$plan['f_company_id']]);
                     $placeholders['{firma_adi}'] = $stmtCN->fetchColumn();
                } elseif(!empty($plan['company_name'])) {
                     $placeholders['{firma_adi}'] = $plan['company_name'];
                }

                foreach ($placeholders as $key => $value) {
                    $personalSubject = str_replace($key, $value ?? '', $personalSubject);
                    $personalBody = str_replace($key, $value ?? '', $personalBody);
                }

                // İmza (nl2br yapıldığı için <br> ile alt satıra geçiyoruz)
                $personalBody .= "<br><br>" . $email_settings['from_name'];

                $mail->Subject = $personalSubject;
                $mail->Body = $personalBody;
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $personalBody));

                $mail->send();
            }

            if (isset($_SESSION['user_id'])) {
                $count = count($recipients);
                $log_content = "Denetim Planı (ID: $planningId) için $count kişiye e-posta gönderildi.";
                $log_stmt = $db->prepare("INSERT INTO email_log (log_date, email_template_id, user_id, company_id, log_type, content) VALUES (NOW(), ?, ?, ?, 'Denetim E-postası', ?)");
                $log_stmt->execute([$templateId, $_SESSION['user_id'], $plan['f_company_id'] ?? 0, $log_content]);
            }

            echo json_encode(['success' => true, 'message' => 'E-postalar başarıyla gönderildi.']);

        } 
        // --- SENARYO 2: FİRMA MAİLİ ---
        elseif ($companyId > 0) {
             $sql = "SELECT * FROM company WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$company || empty($company['contact_email'])) { echo json_encode(['success' => false, 'message' => 'Firma e-postası bulunamadı.']); exit(); }
            
            $mail->addAddress($company['contact_email'], $company['c_name']);
            
            $contactName = $company['contact_name'];
            if(empty($contactName)) $contactName = $company['authorized_contact_name'];
            if(empty($contactName)) $contactName = $company['c_name'];

            $personalBody = $originalBody;
            $personalBody = str_ireplace('Sayın Yetkili', 'Sayın ' . $contactName, $personalBody);

            $placeholders = [
                '{firma_adi}' => $company['c_name'],
                '{firma_unvan}' => $company['name'],
                '{yetkili}' => $company['authorized_contact_name'],
                '{iletisim_kisi}' => $company['contact_name'],
                '{telefon}' => $company['c_phone'],
                '{eposta}' => $company['contact_email']
            ];

            foreach ($placeholders as $key => $value) {
                $originalSubject = str_replace($key, $value ?? '', $originalSubject);
                $personalBody = str_replace($key, $value ?? '', $personalBody);
            }
            
            // İmza
            $personalBody .= "<br><br>" . $email_settings['from_name'];

            $mail->Subject = $originalSubject;
            $mail->Body = $personalBody;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $personalBody));

            $mail->send();
            
            if (isset($_SESSION['user_id'])) {
                 $log_stmt = $db->prepare("INSERT INTO email_log (log_date, email_template_id, user_id, company_id, log_type, content) VALUES (NOW(), ?, ?, ?, 'E-posta Logu', ?)");
                 $log_stmt->execute([$templateId, $_SESSION['user_id'], $companyId, "Firma ID $companyId adresine mail atıldı."]);
            }
            
            echo json_encode(['success' => true, 'message' => 'E-posta gönderildi.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
        }

    } catch (\Exception $e) { echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]); }
    exit();
}
?>