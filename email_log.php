<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// --- YETKİ KONTROLÜ ---
$userRole = strtolower($_SESSION['role_code']);
if ($userRole === 'auditor') {
    header("Location: dashboard.php");
    exit();
}

// ---------------------------------------------------------
// BACKEND İŞLEMLERİ (AJAX API)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        if (isset($_POST['action']) && $_POST['action'] === 'filter_email_logs') {
            $f_start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $f_end_date = $_POST['end_date'] ?? date('Y-m-d');
            $f_company = intval($_POST['company'] ?? 0);
            $f_template = intval($_POST['template'] ?? 0);

            $sql = "
                SELECT 
                    el.id,
                    el.log_date,
                    el.content,
                    c.c_name as company_name,
                    u.username,
                    et.subject as template_subject
                FROM email_log el
                LEFT JOIN company c ON el.company_id = c.id
                LEFT JOIN user u ON el.user_id = u.id
                LEFT JOIN email_template et ON el.email_template_id = et.id
                WHERE DATE(el.log_date) BETWEEN :start_date AND :end_date
            ";

            $params = [':start_date' => $f_start_date, ':end_date' => $f_end_date];

            if ($f_company > 0) {
                $sql .= " AND el.company_id = :company_id";
                $params[':company_id'] = $f_company;
            }

            if ($f_template > 0) {
                $sql .= " AND el.email_template_id = :template_id";
                $params[':template_id'] = $f_template;
            }

            $sql .= " ORDER BY el.log_date DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Veriyi İşle (HTML oluşturma)
            foreach ($data as &$row) {
                $row['formatted_date'] = date('d.m.Y H:i', strtotime($row['log_date']));
                $row['company_html'] = !empty($row['company_name']) 
                    ? '<i class="fa fa-building" style="color:#999; margin-right:5px;"></i> ' . htmlspecialchars($row['company_name']) 
                    : '<span style="color:#ccc;">-</span>';

                // Konu / İçerik Mantığı
                if (!empty($row['template_subject'])) {
                    $row['subject_display'] = htmlspecialchars($row['template_subject']);
                } else {
                    if (preg_match('/Konu:\s*(.*)/i', $row['content'], $matches)) {
                        $row['subject_display'] = htmlspecialchars(trim($matches[1]));
                    } else {
                        $row['subject_display'] = htmlspecialchars(mb_strimwidth($row['content'], 0, 100, "..."));
                    }
                }
                
                $row['username'] = htmlspecialchars($row['username'] ?? 'Sistem');
            }

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// --- FİLTRE DEĞİŞKENLERİ (İLK YÜKLEME - GET) ---
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$f_company = intval($_GET['company'] ?? 0);
$f_template = intval($_GET['template'] ?? 0);

// --- VERİ ÇEKME (İLK YÜKLEME) ---
try {
    // Filtreler için veriler
    $companies = $db->query("SELECT id, c_name FROM company ORDER BY c_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $templates = $db->query("SELECT id, subject FROM email_template ORDER BY subject ASC")->fetchAll(PDO::FETCH_ASSOC);

    // E-posta Log Sorgusu
    $sql = "
        SELECT 
            el.id,
            el.log_date,
            el.content,
            c.c_name as company_name,
            u.username,
            et.subject as template_subject
        FROM email_log el
        LEFT JOIN company c ON el.company_id = c.id
        LEFT JOIN user u ON el.user_id = u.id
        LEFT JOIN email_template et ON el.email_template_id = et.id
        WHERE DATE(el.log_date) BETWEEN :start_date AND :end_date
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];

    if ($f_company > 0) {
        $sql .= " AND el.company_id = :company_id";
        $params[':company_id'] = $f_company;
    }

    if ($f_template > 0) {
        $sql .= " AND el.email_template_id = :template_id";
        $params[':template_id'] = $f_template;
    }

    $sql .= " ORDER BY el.log_date DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $logs = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CERTBY - E-posta Logları</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        /* ORTAK STİLLER */
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --dropdown-hover: #f1f3f5; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        
        /* Filtre Alanı */
        .filter-section { background: #f1f3f5; padding: 20px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #e9ecef; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; min-width: 200px; flex: 1; }
        .filter-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; color: #555; }
        .filter-control { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 0.95rem; }

        /* Custom Dropdown */
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 0.95rem; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px rgba(0,0,0,0.1); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content input { box-sizing: border-box; width: 95%; margin: 5px 2.5%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.9rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        .arrow-down { font-size: 0.8rem; color: #666; }
        
        /* BUTON STİLLERİ */
        .btn-filter, .btn-reset {
            padding: 0 25px; height: 42px; border-radius: 4px; font-weight: 500; font-size: 0.95rem; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; text-decoration: none; box-sizing: border-box;
        }
        .btn-filter { background-color: var(--primary-color); color: white; }
        .btn-filter:hover { background-color: #0056b3; }
        .btn-reset { background-color: var(--secondary-color); color: white; }
        .btn-reset:hover { background-color: #5a6268; }

        /* Tablo */
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; font-size: 0.9rem; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        
        .search-input-dt { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Ana Sayfaya Dön</a>
    
    <div class="header">
        <h1 class="header-title">E-posta Logları</h1>
    </div>

    <form id="filterForm" class="filter-section">
        <div class="filter-group">
            <label>Başlangıç Tarihi</label>
            <input type="date" id="f_start_date" class="filter-control" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        <div class="filter-group">
            <label>Bitiş Tarihi</label>
            <input type="date" id="f_end_date" class="filter-control" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        
        <div class="filter-group">
            <label>Firma</label>
            <div class="dropdown">
                <input type="hidden" name="company" id="f_company" value="<?php echo $f_company > 0 ? $f_company : ''; ?>">
                <button type="button" id="f_companyBtn" onclick="toggleDropdown('companyDropdown')" class="dropbtn" style="width:100%">
                    <?php 
                        $compName = 'Tümü';
                        foreach($companies as $c) { if($c['id'] == $f_company) $compName = htmlspecialchars($c['c_name']); }
                        echo $compName;
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="companyDropdown" class="dropdown-content">
                    <input type="text" placeholder="Ara..." onkeyup="filterDropdown('companyDropdown')">
                    <a href="javascript:void(0)" onclick="selectOption('f_company', '', 'Tümü', 'companyDropdown')">Tümü</a>
                    <?php foreach($companies as $c): ?>
                        <a href="javascript:void(0)" onclick="selectOption('f_company', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['c_name']); ?>', 'companyDropdown')">
                            <?php echo htmlspecialchars($c['c_name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="filter-group">
            <label>Konu (Şablon)</label>
            <div class="dropdown">
                <input type="hidden" name="template" id="f_template" value="<?php echo $f_template > 0 ? $f_template : ''; ?>">
                <button type="button" id="f_templateBtn" onclick="toggleDropdown('templateDropdown')" class="dropbtn" style="width:100%">
                    <?php 
                        $tmplName = 'Tümü';
                        foreach($templates as $t) { if($t['id'] == $f_template) $tmplName = htmlspecialchars($t['subject']); }
                        echo $tmplName;
                    ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="templateDropdown" class="dropdown-content">
                    <a href="javascript:void(0)" onclick="selectOption('f_template', '', 'Tümü', 'templateDropdown')">Tümü</a>
                    <?php foreach($templates as $t): ?>
                        <a href="javascript:void(0)" onclick="selectOption('f_template', '<?php echo $t['id']; ?>', '<?php echo htmlspecialchars($t['subject']); ?>', 'templateDropdown')">
                            <?php echo htmlspecialchars($t['subject']); ?>
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

    <input type="text" id="customSearch" class="search-input-dt" placeholder="Tabloda ara...">

    <div class="table-responsive">
        <table id="emailLogTable">
            <thead>
                <tr>
                    <th style="width: 150px;">Tarih / Saat</th>
                    <th style="width: 200px;">Firma</th>
                    <th>Konu / İçerik</th>
                    <th style="width: 150px;">Gönderen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d.m.Y H:i', strtotime($log['log_date'])); ?></td>
                            <td>
                                <?php if(!empty($log['company_name'])): ?>
                                    <i class="fa fa-building" style="color:#999; margin-right:5px;"></i>
                                    <?php echo htmlspecialchars($log['company_name']); ?>
                                <?php else: ?>
                                    <span style="color:#ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#444; font-size:0.9rem; font-weight:500;">
                                <?php 
                                    if (!empty($log['template_subject'])) {
                                        echo htmlspecialchars($log['template_subject']);
                                    } 
                                    else {
                                        $content = $log['content'];
                                        if (preg_match('/Konu:\s*(.*)/i', $content, $matches)) {
                                            echo htmlspecialchars(trim($matches[1]));
                                        } else {
                                            echo htmlspecialchars(mb_strimwidth($content, 0, 100, "..."));
                                        }
                                    }
                                ?>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <i class="fa fa-user-circle" style="color:#ccc; font-size:1.1rem;"></i>
                                    <div>
                                        <div style="font-weight:600; font-size:0.85rem;"><?php echo htmlspecialchars($log['username'] ?? 'Sistem'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    let dataTable;

    $(document).ready(function() {
        // 1. Initial State from URL
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.get('start_date')) document.getElementById('f_start_date').value = urlParams.get('start_date');
        if (urlParams.get('end_date')) document.getElementById('f_end_date').value = urlParams.get('end_date');
        
        if (urlParams.get('company')) {
            const val = urlParams.get('company');
            document.getElementById('f_company').value = val;
            syncFilterDropdownUI('f_company', val, 'companyDropdown');
        }
        if (urlParams.get('template')) {
            const val = urlParams.get('template');
            document.getElementById('f_template').value = val;
            syncFilterDropdownUI('f_template', val, 'templateDropdown');
        }

        dataTable = $('#emailLogTable').DataTable({
            "pageLength": 10, 
            "lengthMenu": [10, 25, 50, 100], 
            "dom": 'rtip', 
            "order": [[ 0, "desc" ]], 
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json" }
        });

        $('#customSearch').on('keyup', function() { dataTable.search(this.value).draw(); });
    });

    // --- POPSTATE EVENT LISTENER (BACK BUTTON SUPPORT) ---
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        
        const start = urlParams.get('start_date') || '<?php echo date('Y-m-d', strtotime('-30 days')); ?>';
        const end = urlParams.get('end_date') || '<?php echo date('Y-m-d'); ?>';
        const company = urlParams.get('company') || '';
        const template = urlParams.get('template') || '';

        document.getElementById('f_start_date').value = start;
        document.getElementById('f_end_date').value = end;
        
        document.getElementById('f_company').value = company;
        syncFilterDropdownUI('f_company', company, 'companyDropdown');
        
        document.getElementById('f_template').value = template;
        syncFilterDropdownUI('f_template', template, 'templateDropdown');

        applyFilters(false);
    });

    // --- FİLTRELEME (AJAX) ---
    function applyFilters(pushToHistory = true) {
        const start_date = document.getElementById('f_start_date').value;
        const end_date = document.getElementById('f_end_date').value;
        const company = document.getElementById('f_company').value;
        const template = document.getElementById('f_template').value;

        // URL Güncelleme
        const newUrl = new URL(window.location.href);
        if(start_date) newUrl.searchParams.set('start_date', start_date);
        if(end_date) newUrl.searchParams.set('end_date', end_date);
        if(company) newUrl.searchParams.set('company', company); else newUrl.searchParams.delete('company');
        if(template) newUrl.searchParams.set('template', template); else newUrl.searchParams.delete('template');

        if (pushToHistory) {
            window.history.pushState({path: newUrl.href}, '', newUrl);
        } else {
            window.history.replaceState(null, '', newUrl);
        }

        const fd = new FormData();
        fd.append('action', 'filter_email_logs');
        fd.append('start_date', start_date);
        fd.append('end_date', end_date);
        fd.append('company', company);
        fd.append('template', template);

        fetch('email_log.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                dataTable.clear();
                
                res.data.forEach(row => {
                    let senderHtml = `
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i class="fa fa-user-circle" style="color:#ccc; font-size:1.1rem;"></i>
                            <div>
                                <div style="font-weight:600; font-size:0.85rem;">${row.username}</div>
                            </div>
                        </div>`;
                    
                    let subjectHtml = `<td style="color:#444; font-size:0.9rem; font-weight:500;">${row.subject_display}</td>`;

                    dataTable.row.add([
                        row.formatted_date,
                        row.company_html,
                        subjectHtml,
                        senderHtml
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
        document.getElementById('f_start_date').value = '<?php echo date('Y-m-d', strtotime('-30 days')); ?>';
        document.getElementById('f_end_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('f_company').value = '';
        document.getElementById('f_companyBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        document.getElementById('f_template').value = '';
        document.getElementById('f_templateBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        
        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl); // PUSH STATE for reset
        
        applyFilters(false);
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
    }

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

    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        const btnId = inputId + 'Btn'; 
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        document.getElementById(dropdownId).classList.remove('show');
    }

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
        
        const btnId = inputId + 'Btn'; 
        const btn = document.getElementById(btnId);
        if(btn) btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
    }
</script>

</body>
</html>