<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

$userRole = strtolower($_SESSION['role_code']);
$userId = $_SESSION['user_id'];
$isAuditor = ($userRole === 'auditor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $action = $_POST['action'] ?? '';

        // --- 1. DİNAMİK DENETİM ÖNGÖRÜ RAPORU ---
        if ($action === 'forecast_report') {
            $daysFilter = intval($_POST['days_filter'] ?? 0); 
            $typeFilter = $_POST['type_filter'] ?? ''; 
            $planningFilter = $_POST['planning_filter'] ?? ''; // YENİ EKLENDİ
            
            $sql = "
            SELECT 
                MainData.*,
                p.id as plan_id,
                p.audit_publish_date as planned_date,
                p.audit_status,
                DATEDIFF(MainData.target_date, CURDATE()) as days_remaining_calculated,
                DATEDIFF(p.audit_publish_date, CURDATE()) as days_remaining_planned
            FROM (
                SELECT 
                    c.id as cert_db_id,
                    comp.c_name,
                    ct.name as cert_name,
                    c.certno,
                    ct.period,
                    
                    CASE 
                        WHEN ct.surveillance_count >= 1 AND TIMESTAMPDIFF(MONTH, c.publish_date, CURDATE()) < ct.surveillance_frequency THEN 'Ara Tetkik 1'
                        WHEN ct.surveillance_count >= 2 AND TIMESTAMPDIFF(MONTH, c.publish_date, CURDATE()) < (ct.surveillance_frequency * 2) THEN 'Ara Tetkik 2'
                        ELSE 'Yeniden Belgelendirme'
                    END as next_audit_type,

                    CASE 
                        WHEN ct.surveillance_count >= 1 AND TIMESTAMPDIFF(MONTH, c.publish_date, CURDATE()) < ct.surveillance_frequency 
                            THEN DATE_ADD(c.publish_date, INTERVAL ct.surveillance_frequency MONTH)
                        WHEN ct.surveillance_count >= 2 AND TIMESTAMPDIFF(MONTH, c.publish_date, CURDATE()) < (ct.surveillance_frequency * 2)
                            THEN DATE_ADD(c.publish_date, INTERVAL (ct.surveillance_frequency * 2) MONTH)
                        ELSE c.end_date
                    END as target_date,
                    
                    CASE 
                        WHEN TIMESTAMPDIFF(MONTH, c.publish_date, CURDATE()) < (ct.surveillance_frequency * ct.surveillance_count) THEN 'surveillance'
                        ELSE 'recertification'
                    END as type_code,

                    (SELECT audit_publish_date FROM planning WHERE audit_certtification_no = c.certno AND audit_status NOT IN ('İptal', 'Gerçekleşti') ORDER BY id DESC LIMIT 1) as planned_date_real

                FROM certification c
                JOIN company comp ON c.f_company_id = comp.id
                JOIN cert ct ON c.f_cert_id = ct.id
                WHERE c.status = 1

                UNION ALL

                SELECT 
                    0 as cert_db_id,
                    comp.c_name,
                    ct.name as cert_name,
                    'Henüz Yok (Plan Aşamasında)' as certno,
                    ct.period,
                    'İlk Belgelendirme' as next_audit_type,
                    p.audit_publish_date as target_date,
                    'initial' as type_code,
                    p.audit_publish_date as planned_date_real

                FROM planning p
                JOIN company comp ON p.f_company_id = comp.id
                LEFT JOIN cert ct ON p.f_cert_id = ct.id
                WHERE p.audit_type = 'ilk' 
                AND p.audit_status NOT IN ('İptal', 'Gerçekleşti')

            ) as MainData
            
            LEFT JOIN planning p ON p.audit_certtification_no = MainData.certno 
                                 AND p.audit_status != 'İptal'
                                 AND p.audit_status != 'Gerçekleşti'
            
            WHERE 1=1
            ";

            // YENİ FİLTRE MANTIĞI: PLANLAMA DURUMU
            if ($planningFilter === 'planned') {
                // Sadece planlanmış olanlar (veya İlk Belgelendirme olanlar ki onlar zaten planlıdır)
                $sql .= " AND (p.id IS NOT NULL OR MainData.type_code = 'initial') ";
            } elseif ($planningFilter === 'unplanned') {
                // Sadece planlanmamış olanlar (ve İlk Belgelendirme OLMAYANLAR)
                $sql .= " AND p.id IS NULL AND MainData.type_code != 'initial' ";
            }

            // Geriye dönük 2 aydan eskileri getirme (Çok eski verileri elemek için)
            $sql .= " AND target_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) "; 

            $params = [];
            
            if ($daysFilter > 0) {
                $sql .= " AND DATEDIFF(target_date, CURDATE()) BETWEEN 0 AND ?";
                $params[] = $daysFilter;
            }

            if (!empty($typeFilter)) {
                $sql .= " AND type_code = ?";
                $params[] = $typeFilter;
            }

            $sql .= " ORDER BY target_date ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($data as &$row) {
                $row['formatted_target'] = date('d.m.Y', strtotime($row['target_date']));
                $days = (new DateTime($row['target_date']))->diff(new DateTime())->days;
                $invert = (new DateTime($row['target_date'])) < (new DateTime()) ? -1 : 1;
                $days = $days * $invert;
                $row['days_val'] = $days;

                if ($row['type_code'] === 'initial') {
                    $row['status_html'] = "
                        <div class='status-box planned'>
                            <div class='status-title' style='color:#28a745'><i class='fa fa-flag'></i> Başlangıç Planlandı</div>
                            <div class='status-date'>Tarih: {$row['formatted_target']}</div>
                            <span class='badge badge-success'>$days Gün Kaldı</span>
                        </div>";
                } 
                elseif (!empty($row['planned_date_real'])) {
                    $planDate = date('d.m.Y', strtotime($row['planned_date_real']));
                    $row['status_html'] = "
                        <div class='status-box planned'>
                            <div class='status-title'><i class='fa fa-check-circle'></i> Planlandı</div>
                            <div class='status-date'>$planDate</div>
                            <span class='badge badge-success'>$days Gün Kaldı</span>
                        </div>";
                } 
                else {
                    $badgeClass = 'badge-info';
                    $alertText = 'Normal';
                    if($days < 0) { $badgeClass = 'badge-secondary'; $alertText = 'Gecikmiş'; }
                    elseif($days <= 30) { $badgeClass = 'badge-danger'; $alertText = 'Çok Acil'; }
                    elseif($days <= 60) { $badgeClass = 'badge-warning'; $alertText = 'Planlanmalı'; }
                    
                    $row['status_html'] = "
                        <div class='status-box pending'>
                            <div class='status-title'><i class='fa fa-hourglass-half'></i> Bekleniyor</div>
                            <div class='status-date'>Hedef: {$row['formatted_target']}</div>
                            <span class='badge $badgeClass'>$days Gün ($alertText)</span>
                        </div>";
                }
            }

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }

        // --- 2. RAPOR ARŞİVİ ---
        if ($action === 'filter_audit_reports') {
            $f_start = $_POST['start_date'] ?? '';
            $f_end = $_POST['end_date'] ?? '';
            
            try {
                $sql = "SELECT ar.*, c.c_name, p.audit_type 
                        FROM audit_report ar
                        JOIN planning p ON ar.f_planning_id = p.id
                        JOIN company c ON p.f_company_id = c.id
                        WHERE 1=1";
                
                $params = [];
                if($f_start) { $sql .= " AND ar.audit_date_real >= ?"; $params[] = $f_start; }
                if($f_end) { $sql .= " AND ar.audit_date_real <= ?"; $params[] = $f_end; }
                
                if ($isAuditor) {
                    $sql .= " AND p.id IN (SELECT f_planning_id FROM planning_auditor WHERE f_auditor_id = ?)";
                    $params[] = $userId;
                }

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['status' => 'success', 'data' => $data]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'success', 'data' => []]);
            }
            exit();
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - Raporlar</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --danger-color: #dc3545; --warning-color: #ffc107; --info-color: #17a2b8; --success-color: #28a745; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .tab-menu { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 20px; }
        .tab-link { padding: 12px 20px; cursor: pointer; font-weight: 600; color: #666; border: none; background: none; outline: none; font-size: 1rem; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab-link:hover { color: var(--primary-color); }
        .tab-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .report-controls { background: #eef2f7; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; border: 1px solid #dce4ec; }
        .control-group { display: flex; flex-direction: column; width: 250px; }
        .control-group label { font-weight: 600; font-size: 0.9rem; margin-bottom: 5px; color: #555; }
        .form-select, .form-control { padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #fff; font-size: 0.95rem; width: 100%; cursor: pointer; box-sizing: border-box; }
        .btn-report { background: var(--primary-color); color: #fff; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: 600; height: 42px; width: 100%; transition: 0.2s; }
        .btn-report:hover { background: #0056b3; }
        .table-responsive { overflow-x: auto; }
        table.dataTable thead th { background-color: #f8f9fa; color: #495057; font-weight: 600; border-bottom: 2px solid #e9ecef; }
        .badge { padding: 5px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; color: #fff; display: inline-block; min-width: 80px; text-align: center; }
        .badge-danger { background-color: var(--danger-color); }
        .badge-warning { background-color: var(--warning-color); color: #212529; }
        .badge-info { background-color: var(--info-color); }
        .badge-success { background-color: var(--success-color); }
        .badge-secondary { background-color: var(--secondary-color); }
        .status-box { padding: 8px; border-radius: 6px; border-left: 4px solid #ccc; font-size: 0.9rem; }
        .status-box.planned { border-left-color: var(--success-color); background: #e8f5e9; }
        .status-box.pending { border-left-color: var(--warning-color); background: #fff3cd; }
        .status-title { font-weight: 700; margin-bottom: 3px; display: flex; align-items: center; gap: 5px; }
        .status-date { font-size: 0.85rem; color: #555; margin-bottom: 3px; }
        .cert-no-main { color: var(--primary-color); font-weight: 700; font-size: 1rem; }
        .cert-name-sub { color: #555; font-size: 0.85rem; margin-top: 2px; }
        .cert-info-sub { color: #888; font-size: 0.75rem; font-style: italic; }
        .dt-buttons .dt-button { background: #fff !important; color: #333 !important; border: 1px solid #ccc !important; border-radius: 4px !important; padding: 5px 12px !important; margin-right: 5px !important; font-size: 0.9rem !important; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    <div class="header">
        <h1 class="header-title"><i class="fa fa-chart-pie"></i> Raporlama Modülü</h1>
    </div>

    <div class="tab-menu">
        <button class="tab-link active" onclick="openTab(event, 'forecastTab')">Denetim Öngörü & Takip</button>
        <button class="tab-link" onclick="openTab(event, 'historyTab')">Tamamlanan Rapor Arşivi</button>
    </div>

    <div id="forecastTab" class="tab-content active">
        <div class="alert" style="background:#e3f2fd; color:#0c5460; padding:15px; border-radius:6px; margin-bottom:20px; border:1px solid #bee5eb;">
            <i class="fa fa-info-circle"></i> Bu rapor; mevcut sertifikaların <strong>Ara Tetkik/Yenileme</strong> tarihlerini ve planlanmış <strong>İlk Belgelendirme</strong> süreçlerini birleştirir.
        </div>

        <div class="report-controls">
            <div class="control-group">
                <label>Planlama Durumu</label>
                <select id="planning_filter" class="form-select">
                    <option value="">Tümü</option>
                    <option value="planned">Planlananlar</option>
                    <option value="unplanned">Planlanmayanlar</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>Vade Durumu</label>
                <select id="days_filter" class="form-select">
                    <option value="0">Tümü</option>
                    <option value="30">Acil (30 Gün)</option>
                    <option value="60">Yakın (60 Gün)</option>
                    <option value="90">Orta Vade (90 Gün)</option>
                    <option value="180">Uzun Vade (6 Ay)</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>İşlem Türü</label>
                <select id="type_filter" class="form-select">
                    <option value="">Tümü</option>
                    <option value="initial">İlk Belgelendirme</option>
                    <option value="surveillance">Ara Tetkik</option>
                    <option value="recertification">Yeniden Belgelendirme</option>
                </select>
            </div>
            
            <div class="control-group">
                <button class="btn-report" onclick="loadForecastReport()">
                    <i class="fa fa-filter"></i> Listele
                </button>
            </div>
        </div>

        <table id="forecastTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Firma Adı</th>
                    <th>Belge No / Standart</th>
                    <th>Gereken Denetim</th>
                    <th>Durum / Planlama</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div id="historyTab" class="tab-content">
        <div class="report-controls">
            <div class="control-group"><label>Başlangıç:</label><input type="date" id="ar_start" class="form-control"></div>
            <div class="control-group"><label>Bitiş:</label><input type="date" id="ar_end" class="form-control"></div>
            <div class="control-group">
                <button class="btn-report" onclick="loadAuditReports()"><i class="fa fa-search"></i> Ara</button>
            </div>
        </div>
        <table id="auditReportTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Rapor No</th>
                    <th>Firma</th>
                    <th>Denetim Türü</th>
                    <th>Tarih</th>
                    <th>Karar</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
    let tableForecast;
    let tableHistory;

    $(document).ready(function() {
        tableForecast = $('#forecastTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['excel', 'pdf', 'print'],
            language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            order: [[ 3, "asc" ]], 
            pageLength: 25,
            columns: [ { width: "20%" }, { width: "30%" }, { width: "20%" }, { width: "30%" } ]
        });

        tableHistory = $('#auditReportTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['excel', 'pdf', 'print'],
            language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" },
            order: [[ 3, "desc" ]]
        });

        loadForecastReport();
    });

    window.openTab = function(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.className += " active";
        
        if(tabName === 'historyTab') {
            loadAuditReports();
            tableHistory.columns.adjust().draw();
        } else {
            tableForecast.columns.adjust().draw();
        }
    }

    function loadForecastReport() {
        const days = document.getElementById('days_filter').value;
        const type = document.getElementById('type_filter').value;
        const planning = document.getElementById('planning_filter').value;

        const fd = new FormData();
        fd.append('action', 'forecast_report');
        fd.append('days_filter', days);
        fd.append('type_filter', type);
        fd.append('planning_filter', planning);

        fetch('report.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'error') { alert(res.message); return; }
            
            tableForecast.clear();
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach(row => {
                    const certInfo = `
                        <div class="cert-no-main">${row.certno}</div>
                        <div class="cert-name-sub">${row.cert_name}</div>
                        <div class="cert-info-sub">Periyot: ${row.period} Yıl</div>
                    `;
                    
                    tableForecast.row.add([
                        row.c_name,
                        certInfo,
                        `<span style="font-weight:600; color:#333;">${row.next_audit_type}</span>`,
                        row.status_html
                    ]);
                });
            }
            tableForecast.draw();
        })
        .catch(err => console.error(err));
    }

    function loadAuditReports() {
        const start = document.getElementById('ar_start').value;
        const end = document.getElementById('ar_end').value;

        const fd = new FormData();
        fd.append('action', 'filter_audit_reports');
        fd.append('start_date', start);
        fd.append('end_date', end);

        fetch('report.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            tableHistory.clear();
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach(row => {
                    tableHistory.row.add([
                        row.report_no || '-',
                        row.c_name || '-',
                        row.audit_type || '-',
                        row.audit_date_real || '-',
                        row.decision || '-',
                        '<button class="action-button" style="background:#17a2b8; border:none; padding:5px 10px; border-radius:4px; color:#fff;" onclick="alert(\'Rapor: '+row.report_no+'\')"><i class="fa fa-eye"></i></button>'
                    ]);
                });
            }
            tableHistory.draw();
        });
    }
</script>

</body>
</html>