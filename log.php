<?php
session_start();

// Oturum Kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'db.php';

// --- YETKİ KONTROLÜ ---
// Matris 1.5: Operatör ve Kullanıcı (R) görebilir. Denetçi göremez.
$userRole = strtolower($_SESSION['role_code']);
if ($userRole === 'auditor') {
    header("Location: dashboard.php");
    exit();
}

// --- YARDIMCI FONKSİYON: Badge Oluşturucu ---
// Hem ilk yüklemede hem AJAX'ta kullanacağız
function getActionBadge($content) {
    $text = mb_strtolower($content, 'UTF-8');

    if (strpos($text, 'silindi') !== false) {
        return '<span class="badge badge-danger">Silme</span>';
    }
    if (strpos($text, 'güncellendi') !== false) {
        return '<span class="badge badge-warning">Güncelleme</span>';
    }
    if (strpos($text, 'eklendi') !== false || strpos($text, 'oluşturuldu') !== false || strpos($text, 'planlandı') !== false) {
        return '<span class="badge badge-success">Ekleme</span>';
    }
    if (strpos($text, 'başarılı giriş') !== false) {
        return '<span class="badge badge-success">Giriş Başarılı</span>';
    }
    if (strpos($text, 'başarısız') !== false || strpos($text, 'hatalı') !== false) {
        return '<span class="badge badge-danger">Giriş Başarısız</span>';
    }
    
    return '<span class="badge badge-default" title="'.htmlspecialchars($content).'">İşlem</span>';
}

// ---------------------------------------------------------
// BACKEND İŞLEMLERİ (AJAX API)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        // FİLTRELEME İŞLEMİ
        if (isset($_POST['action']) && $_POST['action'] === 'filter_logs') {
            $f_start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $f_end_date = $_POST['end_date'] ?? date('Y-m-d');
            $f_log_type = $_POST['log_type'] ?? '';

            $sql = "SELECT 
                        gl.id,
                        gl.log_date,
                        gl.log_type,
                        gl.content,
                        u.username,
                        u.name as user_full_name,
                        c.c_name as company_name
                    FROM general_log gl
                    LEFT JOIN user u ON gl.user_id = u.id
                    LEFT JOIN company c ON gl.company_id = c.id
                    WHERE DATE(gl.log_date) BETWEEN :start_date AND :end_date";

            if (!empty($f_log_type)) {
                $sql .= " AND gl.log_type = :log_type";
            }

            $sql .= " ORDER BY gl.log_date DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':start_date', $f_start_date);
            $stmt->bindValue(':end_date', $f_end_date);
            
            if (!empty($f_log_type)) {
                $stmt->bindValue(':log_type', $f_log_type);
            }

            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Veriyi işle (Badge HTML'ini ekle)
            foreach ($data as &$row) {
                $row['badge_html'] = getActionBadge($row['content']);
                $row['formatted_date'] = date('d.m.Y H:i', strtotime($row['log_date']));
                $row['username'] = $row['username'] ?? 'Sistem';
                $row['user_full_name'] = $row['user_full_name'] ?? '';
                $row['company_name'] = $row['company_name'] ?? '-';
            }

            echo json_encode(['status' => 'success', 'data' => $data]);
            exit();
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// --- SAYFA VERİLERİ (İLK YÜKLEME - GET) ---
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$logType = $_GET['log_type'] ?? '';

try {
    // 1. Log Türleri (Dropdown için)
    $typesQuery = $db->query("SELECT DISTINCT log_type FROM general_log ORDER BY log_type ASC");
    $logTypes = $typesQuery->fetchAll(PDO::FETCH_COLUMN);

    // 2. İlk Yükleme Verisi
    $sql = "SELECT 
                gl.id,
                gl.log_date,
                gl.log_type,
                gl.content,
                u.username,
                u.name as user_full_name,
                c.c_name as company_name
            FROM general_log gl
            LEFT JOIN user u ON gl.user_id = u.id
            LEFT JOIN company c ON gl.company_id = c.id
            WHERE DATE(gl.log_date) BETWEEN :start_date AND :end_date";

    if (!empty($logType)) {
        $sql .= " AND gl.log_type = :log_type";
    }

    $sql .= " ORDER BY gl.log_date DESC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':start_date', $startDate);
    $stmt->bindValue(':end_date', $endDate);
    if (!empty($logType)) {
        $stmt->bindValue(':log_type', $logType);
    }
    $stmt->execute();
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
    <title>CERTBY - Sistem Logları</title>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        :root { --primary-color: #007bff; --secondary-color: #6c757d; --background-color: #f8f9fa; --card-background: #ffffff; --border-color: #e9ecef; --success-color: #28a745; --danger-color: #dc3545; --dropdown-hover: #f1f3f5; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--background-color); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 98%; margin: 0 auto; background-color: var(--card-background); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: var(--primary-color); font-weight: 300; font-size: 2rem; }
        .back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        
        /* FİLTRE ALANI */
        .filter-section { background: #f1f3f5; padding: 20px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #e9ecef; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; min-width: 200px; flex: 1; }
        .filter-group label { font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; color: #555; }
        .filter-control { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 0.95rem; }
        
        /* Custom Dropdown */
        .dropdown { position: relative; display: inline-block; width: 100%; }
        .dropbtn { background-color: #fff; color: #333; padding: 10px; font-size: 0.95rem; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; width: 100%; text-align: left; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 100%; max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 8px 16px rgba(0,0,0,0.1); z-index: 100; border-radius: 4px; margin-top: 2px; }
        .dropdown-content a { color: #333; padding: 10px 16px; text-decoration: none; display: block; font-size: 0.9rem; }
        .dropdown-content a:hover { background-color: var(--dropdown-hover); }
        .show { display: block; }
        .arrow-down { font-size: 0.8rem; color: #666; }

        /* BUTONLAR */
        .btn-filter { 
            background-color: var(--primary-color); color: white; border: none; padding: 10px 30px; 
            border-radius: 4px; cursor: pointer; font-weight: 500; transition: 0.2s; height: 42px; 
            display: flex; align-items: center; gap: 8px;
        }
        .btn-filter:hover { background-color: #0056b3; }
        
        .btn-reset { 
            background-color: var(--secondary-color); color: white; border: none; padding: 10px 30px; 
            border-radius: 4px; cursor: pointer; font-weight: 500; transition: 0.2s; height: 42px; 
            display: flex; align-items: center; gap: 8px; text-decoration: none; box-sizing: border-box;
        }
        .btn-reset:hover { background-color: #5a6268; }
        
        /* Tablo */
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; font-size: 0.9rem; }
        th { background-color: #f1f3f5; font-weight: 600; color: var(--secondary-color); white-space: nowrap; }
        
        .search-input-dt { width: 100%; padding: 12px 15px; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; margin-bottom: 20px; background-color: #fcfcfc; }
        .dataTables_wrapper .dataTables_length select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--primary-color) !important; color: white !important; border: 1px solid var(--primary-color) !important; }
        
        /* RENKLİ ETİKETLER VE BADGE'LER */
        .badge { padding: 6px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* İşlem Türü Badge'leri */
        .badge-access { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; } 
        .badge-audit { background-color: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; } 
        .badge-cert { background-color: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; } 
        .badge-default { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }

        /* Aksiyon Badge'leri */
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: #212529; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fa fa-arrow-left"></i> Dashboard'a Dön</a>
    
    <div class="header">
        <h1 class="header-title">Sistem Logları</h1>
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
            <label>İşlem Türü</label>
            <div class="dropdown">
                <input type="hidden" name="log_type" id="f_log_type" value="<?php echo htmlspecialchars($logType); ?>">
                <button type="button" id="f_logTypeBtn" onclick="toggleDropdown('logTypeDropdown')" class="dropbtn" style="width:100%">
                    <?php echo !empty($logType) ? htmlspecialchars($logType) : 'Tümü'; ?>
                    <span class="arrow-down">&#9662;</span>
                </button>
                <div id="logTypeDropdown" class="dropdown-content">
                    <a href="javascript:void(0)" onclick="selectOption('f_log_type', '', 'Tümü', 'logTypeDropdown')">Tümü</a>
                    <?php foreach ($logTypes as $type): ?>
                        <a href="javascript:void(0)" onclick="selectOption('f_log_type', '<?php echo htmlspecialchars($type); ?>', '<?php echo htmlspecialchars($type); ?>', 'logTypeDropdown')">
                            <?php echo htmlspecialchars($type); ?>
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

    <input type="text" id="customSearch" class="search-input-dt" placeholder="Log içeriği, kullanıcı, firma veya tarih ara...">

    <div class="table-responsive">
        <table id="logTable">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th style="width: 150px;">Tarih / Saat</th>
                    <th style="width: 180px;">Kullanıcı</th>
                    <th style="width: 160px;">İşlem Türü</th>
                    <th style="width: 200px;">İlgili Firma</th>
                    <th>Açıklama</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($log['log_date'])); ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <i class="fa fa-user-circle" style="color:#ccc; font-size:1.2rem;"></i>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($log['username'] ?? 'Sistem'); ?></div>
                                        <div style="font-size:0.8rem; color:#666;"><?php echo htmlspecialchars($log['user_full_name'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $lType = $log['log_type'];
                                    $badgeClass = 'badge-default';
                                    
                                    if(stripos($lType, 'Erişim') !== false) $badgeClass = 'badge-access';
                                    elseif(stripos($lType, 'Denetim') !== false) $badgeClass = 'badge-audit';
                                    elseif(stripos($lType, 'Belge') !== false || stripos($lType, 'Sertifika') !== false) $badgeClass = 'badge-cert';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($lType); ?></span>
                            </td>
                            <td>
                                <?php if(!empty($log['company_name'])): ?>
                                    <i class="fa fa-building" style="color:#999; margin-right:5px;"></i>
                                    <?php echo htmlspecialchars($log['company_name']); ?>
                                <?php else: ?>
                                    <span style="color:#ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo getActionBadge($log['content']); ?>
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
        // Initial State from URL
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.get('start_date')) document.getElementById('f_start_date').value = urlParams.get('start_date');
        if (urlParams.get('end_date')) document.getElementById('f_end_date').value = urlParams.get('end_date');
        if (urlParams.get('log_type')) {
            const type = urlParams.get('log_type');
            document.getElementById('f_log_type').value = type;
            const btn = document.getElementById('f_logTypeBtn');
            if (btn) btn.innerHTML = type + ' <span class="arrow-down">&#9662;</span>';
        }

        dataTable = $('#logTable').DataTable({
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
        const type = urlParams.get('log_type') || '';

        document.getElementById('f_start_date').value = start;
        document.getElementById('f_end_date').value = end;
        document.getElementById('f_log_type').value = type;
        
        const btn = document.getElementById('f_logTypeBtn');
        if (type) btn.innerHTML = type + ' <span class="arrow-down">&#9662;</span>';
        else btn.innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';

        applyFilters(false);
    });

    // --- FİLTRELEME (AJAX) ---
    function applyFilters(pushToHistory = true) {
        const start_date = document.getElementById('f_start_date').value;
        const end_date = document.getElementById('f_end_date').value;
        const log_type = document.getElementById('f_log_type').value;

        // URL Güncelleme
        const newUrl = new URL(window.location.href);
        if(start_date) newUrl.searchParams.set('start_date', start_date);
        if(end_date) newUrl.searchParams.set('end_date', end_date);
        if(log_type) newUrl.searchParams.set('log_type', log_type); else newUrl.searchParams.delete('log_type');

        if (pushToHistory) {
            window.history.pushState({path: newUrl.href}, '', newUrl);
        } else {
            window.history.replaceState(null, '', newUrl);
        }

        const fd = new FormData();
        fd.append('action', 'filter_logs');
        fd.append('start_date', start_date);
        fd.append('end_date', end_date);
        fd.append('log_type', log_type);

        fetch('log.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                dataTable.clear();

                res.data.forEach(row => {
                    let userHtml = `
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i class="fa fa-user-circle" style="color:#ccc; font-size:1.2rem;"></i>
                            <div>
                                <div style="font-weight:600;">${row.username}</div>
                                <div style="font-size:0.8rem; color:#666;">${row.user_full_name}</div>
                            </div>
                        </div>`;

                    let typeHtml = '';
                    let badgeClass = 'badge-default';
                    let lType = row.log_type || '';
                    if(lType.includes('Erişim')) badgeClass = 'badge-access';
                    else if(lType.includes('Denetim')) badgeClass = 'badge-audit';
                    else if(lType.includes('Belge') || lType.includes('Sertifika')) badgeClass = 'badge-cert';
                    typeHtml = `<span class="badge ${badgeClass}">${lType}</span>`;

                    let companyHtml = '-';
                    if(row.company_name && row.company_name !== '-') {
                        companyHtml = `<i class="fa fa-building" style="color:#999; margin-right:5px;"></i> ${row.company_name}`;
                    }

                    dataTable.row.add([
                        row.id,
                        row.formatted_date,
                        userHtml,
                        typeHtml,
                        companyHtml,
                        row.badge_html
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
        document.getElementById('f_log_type').value = '';
        document.getElementById('f_logTypeBtn').innerHTML = 'Tümü <span class="arrow-down">&#9662;</span>';
        
        const newUrl = window.location.href.split('?')[0];
        window.history.pushState({path: newUrl}, '', newUrl); // PUSH STATE
        
        applyFilters(false);
    }

    // --- MEVCUT UI FONKSİYONLARI ---
    function toggleDropdown(id) { document.getElementById(id).classList.toggle("show"); }
    
    window.onclick = function(e) {
        if (!e.target.matches('.dropbtn') && !e.target.matches('.dropdown-content input')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
    }
    
    function selectOption(inputId, value, text, dropdownId) {
        document.getElementById(inputId).value = value;
        // ID düzeltmesi: f_log_type -> f_logTypeBtn
        const btnId = inputId.replace('_type', 'Type') + 'Btn'; 
        const btn = document.getElementById(btnId);
        if(btn) {
            btn.innerHTML = text + ' <span class="arrow-down">&#9662;</span>';
        }
        document.getElementById(dropdownId).classList.remove('show');
    }
</script>

</body>
</html>