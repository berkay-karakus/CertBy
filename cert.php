<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Belge Yönetimi</title>
    <style>
        /* Ana Renk Paleti ve Stil Temeli */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #e9ecef;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --dropdown-hover: #f1f3f5;
            --dropdown-shadow: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
            color: #343a40;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Sayfa Başlığı ve Buton */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .header-title {
            font-size: 2rem;
            font-weight: 300;
            color: var(--primary-color);
        }

        .add-button {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            background-color: var(--primary-color);
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        .add-button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        /* Arama Alanı */
        .search-container {
            margin-bottom: 25px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        /* Firma Tablosu */
        .table-responsive {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .cert-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cert-table th, .cert-table td {
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
        }

        .cert-table th {
            background-color: var(--background-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: var(--secondary-color);
        }
        
        .cert-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .cert-table tbody tr {
            transition: background-color 0.2s;
        }

        .cert-table tbody tr:hover {
            background-color: #f1f3f5;
        }
        
        .cert-table td:last-child {
            text-align: center;
            white-space: nowrap;
        }

        .action-button {
            padding: 8px 15px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
        }
        
        .action-button.update {
            background-color: var(--primary-color);
            color: #fff;
        }

        .action-button.delete {
            background-color: var(--danger-color);
            color: #fff;
        }
        
        .action-button:hover {
            opacity: 0.8;
        }

        /* Modal (Pop-up) Pencere Stilleri */
        .modal {
            display: none; /* Varsayılan olarak gizli */
            position: fixed; /* Sabit pozisyon */
            z-index: 1000; /* Diğer her şeyin üstünde */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto; /* İçerik taşarsa kaydırma çubuğu */
            background-color: rgba(0, 0, 0, 0.5); /* Yarı saydam siyah arka plan */
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: var(--card-background);
            margin: 10% auto; /* Dikeyde ortalama */
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            font-weight: 300;
        }
        
        .close-button {
            color: var(--secondary-color);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-button:hover {
            color: var(--danger-color);
        }

        .form-field {
            margin-bottom: 15px;
        }

        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #495057;
        }

        .form-field input,
        .form-field select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #fcfcfc;
            transition: border-color 0.2s;
        }

        .form-field input:focus,
        .form-field select:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .modal-button-group {
            text-align: right;
            padding-top: 15px;
        }

        .modal-button {
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .modal-button.cancel {
            background-color: var(--secondary-color);
            margin-right: 10px;
        }

        .modal-button.save {
            background-color: var(--success-color);
        }

          /* Dropdown Stilleri */
          .dropdown {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .dropbtn {
            background-color: #fcfcfc;
            color: #495057;
            padding: 12px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.2s;
        }
        
        .dropbtn:hover, .dropbtn:focus {
            border-color: var(--primary-color);
        }
        
        .dropbtn .arrow-down {
            transition: transform 0.3s ease-in-out;
            margin-left: 10px;
        }

        .dropdown.show .dropbtn .arrow-down {
            transform: rotate(180deg);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--card-background);
            min-width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 8px var(--dropdown-shadow);
            z-index: 100;
            border-radius: 4px;
            margin-top: 5px;
        }
        
        .dropdown-content a {
            color: #343a40;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        
        .dropdown-content a:hover {
            background-color: var(--dropdown-hover);
        }

        .dropdown-content .search-input-dropdown {
            width: 90%;
            padding: 8px;
            margin: 10px 5%;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 0.9rem;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="%236c757d" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zm-160 0c0-44.2-35.8-80-80-80s-80 35.8-80 80s35.8 80 80 80s80-35.8 80-80z"/></svg>');
            background-repeat: no-repeat;
            background-position: 8px center;
            background-size: 16px;
            padding-left: 35px;
        }
        
        .dropdown-content .search-input-dropdown:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .dropdown.show .dropdown-content {
            display: block;
        }

    </style>
</head>
<body>

    <div class="container">
        
        <header class="header">
            <h1 class="header-title">Belge Yönetimi</h1>
            <a href="register_cert.php" class="add-button">Yeni Belge Tanımla</a>
        </header>

        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Belge adı, standardı veya periyoda göre ara...">
        </div>

        <div class="table-responsive">
            <table class="cert-table">
                <thead>
                    <tr>
                        <th>Belge Adı</th>
                        <th>Belge Standardı</th>
                        <th>Belgelendirme Periyodu</th>
                        <th>Ara Tetkik Sayısı</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-id="1">
                        <td>Çevre Yönetim Sistemi</td>
                        <td>ISO 14001:2015</td>
                        <td>3 Yıl</td>
                        <td>2</td>
                        <td>
                            <button class="action-button update" onclick="openModal(1, 'Çevre Yönetim Sistemi', 'ISO 14001:2015', '3 Yıl', 2)">Güncelle</button>
                            <button class="action-button delete" onclick="deleteCert(1)">Sil</button>
                        </td>
                    </tr>
                    <tr data-id="2">
                        <td>Bilgi Güvenliği Yönetim Sistemi</td>
                        <td>ISO 27001:2022</td>
                        <td>3 Yıl</td>
                        <td>2</td>
                        <td>
                            <button class="action-button update" onclick="openModal(2, 'Bilgi Güvenliği Yönetim Sistemi', 'ISO 27001:2022', '3 Yıl', 2)">Güncelle</button>
                            <button class="action-button delete" onclick="deleteCert(2)">Sil</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Belgeyi Güncelle</h2>
                <span class="close-button" onclick="closeModal()">&times;</span>
            </div>
            <form id="updateForm" action="update_cert_process.php" method="POST">
                <input type="hidden" id="certId" name="id">

                <div class="form-field">
                    <label for="certName">Belge Adı</label>
                    <input type="text" id="certName" name="name" required>
                </div>
                <div class="form-field">
                <label for="certStandard">Belge Standardı</label>
                        <input type="hidden" id="certStandard" name="certStandard" required>
                        <div class="dropdown">
                            <button type="button" onclick="toggleDropdown('certStandardDropdown')" class="dropbtn" id="certStandardDropdownbtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                            <div id="certStandardDropdown" class="dropdown-content">
                                <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('certStandardDropdown')">
                            </div>
                        </div>
                </div>
                <div class="form-field">
                <label for="certPeriod">Belge Periyodu</label>
                        <input type="hidden" id="certPeriod" name="certPeriod" required>
                        <div class="dropdown">
                            <button type="button" onclick="toggleDropdown('certPeriodDropdown')" class="dropbtn" id="certPeriodDropdownbtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                            <div id="certPeriodDropdown" class="dropdown-content">
                                <input type="number" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('certPeriodDropdown')">
                            </div>
                        </div>
                </div>
                
                <div class="form-field">
                <label for="certSurveillanceDropdown">Ara Tetkik Sayısı</label>
                        <input type="hidden" id="certSurveillanceCount" name="SurveillanceCount" required>
                        <div class="dropdown">
                            <button type="button" onclick="toggleDropdown('certSurveillanceDropdown')" class="dropbtn" id="certSurveillanceDropdownbtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                            <div id="certSurveillanceDropdown" class="dropdown-content">
                                <input type="number" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('certSurveillanceDropdown')">
                            </div>
                        </div>
                </div>

            </form>
        </div>
    </div>

    <script>
        function deleteCert(certId) {
            if (confirm("Belgeyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.")) {
                // Silme işlemini tetikleyecek backend sayfasını çağır
                // Örnek: window.location.href = 'delete_cert.php?id=' + certId;
                document.querySelector(`[data-id="${certId}"]`).style.display = 'none';
                alert("Belge silindi (sadece test amaçlı gizlendi).");
            }
        }

        // Modal'ı ve formu yöneten fonksiyonlar
        const modal = document.getElementById('updateModal');
        const updateForm = document.getElementById('updateForm');
        
        function openModal(id, name, standard, period, surveillance_count) {
            // Form alanlarını gelen verilerle doldur
            document.getElementById('certId').value = id;
            document.getElementById('certName').value = name;
            document.getElementById('certStandard').value = standard;
            document.getElementById('certPeriod').value = period;
            document.getElementById('certSurveillanceCount').value = surveillance_count;
            
            // Modalı görünür yap
            modal.style.display = 'block';

            // Buton metinlerini mevcut değerlere göre senkronize et
            populateDropdown('certStandardDropdown', certStandards, standard);
            populateDropdown('certPeriodDropdown', certPeriods, String(period));
            populateDropdown('certSurveillanceDropdown', certSurveillances, String(surveillance_count));
        }

        function closeModal() {
            document.getElementById('updateModal').style.display = 'none';
        }

        // Modal dışına tıklayınca kapanmasını sağla
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Dropdown veri kümeleri
        const certStandards = {
            'ISO 9001:2015': 'ISO 9001:2015',
            'ISO 14001:2015': 'ISO 14001:2015',
            'ISO 27001:2022': 'ISO 27001:2022'
        };

        const certPeriods = {
            '1': '1 Yıl',
            '2': '2 Yıl',
            '3': '3 Yıl'
        };

        const certSurveillances = {
            '0': '0',
            '1': '1',
            '2': '2',
            '3': '3'
        };

        function populateDropdown(dropdownId, data, selectedValue = null) {
    const dropdownContent = document.getElementById(dropdownId);
    
    // Arama input'unu koru
    const searchInput = dropdownContent.querySelector('.search-input-dropdown');
    dropdownContent.innerHTML = '';
    dropdownContent.appendChild(searchInput);

    // Dropdown seçeneklerini oluştur ve ekle
    for (const key in data) {
        const a = document.createElement('a');
        a.href = '#';
        a.setAttribute('data-value', key);
        a.textContent = data[key];
        a.onclick = function(e) {
            e.preventDefault();
            setDropdownValue(this, dropdownId);
        };
        dropdownContent.appendChild(a);
    }

    // Eğer bir başlangıç değeri varsa, butonda bunu ve gizli input değerini ayarla
    if (selectedValue && data[selectedValue]) {
        const dropbtn = dropdownContent.closest('.dropdown').querySelector('.dropbtn');
        dropbtn.innerHTML = data[selectedValue] + ' <span class="arrow-down">&#x25BC;</span>';
        const formField = dropdownContent.closest('.form-field');
        const hiddenInput = formField ? formField.querySelector('input[type="hidden"]') : null;
        if (hiddenInput) {
            hiddenInput.value = selectedValue;
        }
    }
}

function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId).closest('.dropdown');
            dropdown.classList.toggle('show');
            
            if (dropdown.classList.contains('show')) {
                const search = dropdown.querySelector('.search-input-dropdown');
                if (search) {
                    search.value = '';
                    resetDropdownFilter(dropdownId);
                    search.focus();
                }
                enableDropdownKeyboard(dropdownId);
            }
        }

        function filterDropdown(dropdownId) {
            const input = document.getElementById(dropdownId).querySelector('.search-input-dropdown');
            const filter = input.value.toUpperCase();
            const dropdownContent = document.getElementById(dropdownId);
            const a = dropdownContent.getElementsByTagName("a");

            for (let i = 0; i < a.length; i++) {
                const txtValue = a[i].textContent || a[i].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    a[i].style.display = "";
                } else {
                    a[i].style.display = "none";
                }
            }
        }
        
        function resetDropdownFilter(dropdownId) {
            const dropdownContent = document.getElementById(dropdownId);
            const a = dropdownContent.getElementsByTagName('a');
            for (let i = 0; i < a.length; i++) {
                a[i].style.display = '';
            }
        }
        
        function setDropdownValue(element, dropdownId) {
            const parentDropdown = document.getElementById(dropdownId).closest('.dropdown');
            const dropbtn = parentDropdown.querySelector('.dropbtn');
            const value = element.getAttribute('data-value');
            const text = element.textContent;

            // Aynı .form-field içindeki gizli inputu bul
            const formField = parentDropdown.closest('.form-field');
            const hiddenInput = formField ? formField.querySelector('input[type="hidden"]') : null;

            if (hiddenInput) {
                hiddenInput.value = value;
            }
            dropbtn.innerHTML = text + ' <span class="arrow-down">&#x25BC;</span>';
            parentDropdown.classList.remove('show');
        }

        function enableDropdownKeyboard(dropdownId) {
            const dropdownContent = document.getElementById(dropdownId);
            const searchInput = dropdownContent.querySelector('.search-input-dropdown');
            const options = () => Array.from(dropdownContent.querySelectorAll('a')).filter(el => el.style.display !== 'none');
            let activeIndex = -1;

            function highlight(index) {
                options().forEach((el, i) => {
                    el.style.backgroundColor = i === index ? 'var(--dropdown-hover)' : '';
                });
            }

            searchInput.onkeydown = function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, options().length - 1);
                    highlight(activeIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    highlight(activeIndex);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const list = options();
                    if (list[activeIndex]) {
                        setDropdownValue(list[activeIndex], dropdownId);
                    }
                } else if (e.key === 'Escape') {
                    dropdownContent.closest('.dropdown').classList.remove('show');
                }
            };
        }

        window.addEventListener('click', function(event) {
            document.querySelectorAll('.dropdown.show').forEach(dropdown => {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });
        });

        // Sayfa yüklendiğinde dropdown içeriklerini hazırla
        document.addEventListener('DOMContentLoaded', () => {
            populateDropdown('certStandardDropdown', certStandards);
            populateDropdown('certPeriodDropdown', certPeriods);
            populateDropdown('certSurveillanceDropdown', certSurveillances);
        });

    </script>
</body>
</html>