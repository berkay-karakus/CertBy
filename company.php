<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CERTBY - Firma Yönetimi</title>
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

        .company-table {
            width: 100%;
            border-collapse: collapse;
        }

        .company-table th, .company-table td {
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
        }

        .company-table th {
            background-color: var(--background-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: var(--secondary-color);
        }
        
        .company-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .company-table tbody tr {
            transition: background-color 0.2s;
        }

        .company-table tbody tr:hover {
            background-color: #f1f3f5;
        }
        
        .company-table td:last-child {
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
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
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
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
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

        .form-grid-modal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 600px) {
            .form-grid-modal {
                grid-template-columns: 1fr;
            }
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
        
        .form-field input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .modal-button-group {
            text-align: right;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
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
            <h1 class="header-title">Firma Yönetimi</h1>
            <button class="add-button" onclick="openNewCompanyModal()">Yeni Firma Ekle</button>
        </header>

        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Firma adına, vergi numarasına veya iletişim bilgisine göre ara...">
        </div>

        <div class="table-responsive">
            <table class="company-table">
                <thead>
                    <tr>
                        <th>Firma Adı</th>
                        <th>Vergi No</th>
                        <th>Telefon</th>
                        <th>İletişim Kurulacak Kişi</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <tr id="company-1">
                        <td>CERTBY Yazılım A.Ş.</td>
                        <td>1234567890</td>
                        <td>(212) 555-1234</td>
                        <td>Mesut Yılmaz</td>
                        <td>Aktif</td>
                        <td>
                            <button class="action-button update" 
                                onclick="openCompanyModal(
                                    1, 
                                    'CERTBY Yazılım A.Ş.', 
                                    'CERTBY Yazılım ve Danışmanlık A.Ş.', 
                                    'Örnek Mah. Örnek Cad. No: 123', 
                                    'Örnek İletişim Adresi',
                                    'www.certby.com',
                                    '2125551234', // Numarayı formata çevirerek gönderdik
                                    'info@certby.com',
                                    'Örnek Fatura Adresi',
                                    'Yetkili Adı',
                                    'Yetkili Ünvanı',
                                    'Mesut Yılmaz',
                                    '2125551235', // Numarayı formata çevirerek gönderdik
                                    'mesut@certby.com',
                                    'Vergi Dairesi',
                                    '1234567890',
                                    '1', /* consulting_id */
                                    'A' /* Durum değeri, veritabanına uyumlu hale getirildi */
                                )">Güncelle</button>
                            <button class="action-button delete" onclick="deleteCompany(1)">Sil</button>
                        </td>
                    </tr>
                    <tr id="company-2">
                        <td>Gelişim Çözüm Ltd.</td>
                        <td>0987654321</td>
                        <td>(312) 555-5678</td>
                        <td>Ayşe Güneş</td>
                        <td>Pasif</td>
                        <td>
                            <button class="action-button update" 
                                onclick="openCompanyModal(
                                    2, 
                                    'Gelişim Çözüm Ltd.', 
                                    'Gelişim Çözüm Danışmanlık', 
                                    'Diğer Mah. Diğer Cad. No: 456', 
                                    'Diğer İletişim Adresi',
                                    'www.gelisimcozum.com',
                                    '3125555678', // Numarayı formata çevirerek gönderdik
                                    'info@gelisimcozum.com',
                                    'Diğer Fatura Adresi',
                                    'Diğer Yetkili',
                                    'Diğer Ünvan',
                                    'Ayşe Güneş',
                                    '3125555679', // Numarayı formata çevirerek gönderdik
                                    'ayse@gelisimcozum.com',
                                    'Diğer Vergi Dairesi',
                                    '0987654321',
                                    '2', /* consulting_id */
                                    'P' /* Durum değeri, veritabanına uyumlu hale getirildi */
                                )">Güncelle</button>
                            <button class="action-button delete" onclick="deleteCompany(2)">Sil</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="companyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Firma Bilgilerini Güncelle</h2>
                <span class="close-button" onclick="closeCompanyModal()">&times;</span>
            </div>
            <form id="companyForm" action="" method="POST">
                <input type="hidden" id="companyId" name="id">

                <div class="form-grid-modal">
                    <div class="form-field">
                        <label for="c_name">Firma Adı</label>
                        <input type="text" id="c_name" name="c_name" required>
                    </div>
                    <div class="form-field">
                        <label for="name">Ticari Unvan</label>
                        <input type="text" id="name" name="name">
                    </div>
                    <div class="form-field">
                        <label for="address">Adres</label>
                        <input type="text" id="address" name="address">
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
                        <input type="tel" id="c_phone" name="c_phone" required>
                    </div>
                    <div class="form-field">
                        <label for="c_email">Firma E-postası</label>
                        <input type="email" id="c_email" name="c_email" required>
                    </div>
                    <div class="form-field">
                        <label for="c_invoice_address">Fatura Adresi</label>
                        <input type="text" id="c_invoice_address" name="c_invoice_address">
                    </div>
                    <div class="form-field">
                        <label for="authorized_contact_name">Yetkili İletişim Adı</label>
                        <input type="text" id="authorized_contact_name" name="authorized_contact_name" required>
                    </div>
                    <div class="form-field">
                        <label for="authorized_contact_title">Yetkili İletişim Unvanı</label>
                        <input type="text" id="authorized_contact_title" name="authorized_contact_title">
                    </div>
                    <div class="form-field">
                        <label for="contact_name">İletişim Kurulacak Kişi</label>
                        <input type="text" id="contact_name" name="contact_name">
                    </div>
                    <div class="form-field">
                        <label for="contact_phone">İletişim Telefonu</label>
                        <input type="tel" id="contact_phone" name="contact_phone">
                    </div>
                    <div class="form-field">
                        <label for="contact_email">İletişim E-postası</label>
                        <input type="email" id="contact_email" name="contact_email">
                    </div>
                    <div class="form-field">
                        <label for="tax_office">Vergi Dairesi</label>
                        <input type="text" id="tax_office" name="tax_office" required>
                    </div>
                    <div class="form-field">
                        <label for="tax_number">Vergi Numarası</label>
                        <input type="text" id="tax_number" name="tax_number" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="consultingDropdown">Danışman Firma</label>
                        <input type="hidden" id="consulting_id" name="consulting_id">
                        <div class="dropdown">
                            <button type="button" onclick="toggleDropdown('consultingDropdown')" class="dropbtn" id="consultingDropbtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                            <div id="consultingDropdown" class="dropdown-content">
                                <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('consultingDropdown')">
                            </div>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="statusDropdown">Durum</label>
                        <input type="hidden" id="status_code" name="status">
                        <div class="dropdown">
                            <button type="button" onclick="toggleDropdown('statusDropdown')" class="dropbtn" id="statusDropbtn">Seçiniz... <span class="arrow-down">&#x25BC;</span></button>
                            <div id="statusDropdown" class="dropdown-content">
                                <input type="text" placeholder="Ara..." class="search-input-dropdown" onkeyup="filterDropdown('statusDropdown')">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-button-group">
                    <button type="button" class="modal-button cancel" onclick="closeCompanyModal()">İptal</button>
                    <button type="submit" class="modal-button save" id="modalActionButton">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Veritabanı ID'leri ve görünen metinleri eşleştirmek için yardımcı veri yapıları
        // Bu veriler dinamik olarak bir API'den veya backend'den gelmelidir.
        const consultingFirms = {
            '1': 'CERTBY Danışmanlık Hizmetleri',
            '2': 'Proje Yazılım Danışmanlık',
            '3': 'Gelişim Danışmanlık Ltd.'
        };

        const companyStatuses = {
            'A': 'Aktif',
            'P': 'Pasif'
        };

        function deleteCompany(companyId) {
            if (confirm("Firmayı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.")) {
                document.getElementById(`company-${companyId}`).style.display = 'none';
                alert("Firma silindi (sadece test amaçlı gizlendi).");
            }
        }

        function closeCompanyModal() {
            document.getElementById('companyModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('companyModal')) {
                closeCompanyModal();
            }
        }

        /**
         * Dropdown menüsünü verilen verilerle doldurur ve başlangıç değerini ayarlar.
         * @param {string} dropdownId - Dropdown menüsünün ID'si.
         * @param {object} data - {key: value} formatında veriler.
         * @param {string|null} [selectedValue=null] - Seçili olacak değerin key'i.
         */
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

    // Eğer bir başlangıç değeri varsa, butonda bunu ve gizli inputu ayarla
    if (selectedValue && data[selectedValue]) {
        const dropbtn = dropdownContent.closest('.dropdown').querySelector('.dropbtn');
        dropbtn.innerHTML = data[selectedValue] + ' <span class="arrow-down">&#x25BC;</span>';
        const hiddenInput = dropdownContent.closest('.form-field').querySelector('input[type="hidden"]');
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
                // Açıldığında arama metnini temizle ve tüm seçenekleri göster
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

            // Gizli input, .dropdown'ın içinde değil; aynı .form-field içinde kardeş eleman
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

        // Yeni firma ekleme modalını açan fonksiyon
        function openNewCompanyModal() {
            // Formu temizle ve yeni kayıt moduna geçir
            document.getElementById('companyForm').reset();
            document.getElementById('companyForm').action = 'add_company_process.php'; // Backend'deki ekleme işlemine yönlendir
            document.getElementById('modalTitle').textContent = 'Yeni Firma Ekle';
            document.getElementById('modalActionButton').textContent = 'Ekle';
            document.getElementById('companyId').value = ''; // ID alanını boşalt

            // Telefon numarası alanlarını varsayılan formata ayarla
            setupPhoneInput('c_phone', '');
            setupPhoneInput('contact_phone', '');

            // Dropdown buton metinlerini varsayılana döndür ve içini doldur
            document.getElementById('consulting_id').value = '';
            document.getElementById('consultingDropbtn').innerHTML = 'Seçiniz... <span class="arrow-down">&#x25BC;</span>';
            populateDropdown('consultingDropdown', consultingFirms);
            
            document.getElementById('status_code').value = '';
            document.getElementById('statusDropbtn').innerHTML = 'Seçiniz... <span class="arrow-down">&#x25BC;</span>';
            populateDropdown('statusDropdown', companyStatuses);

            document.getElementById('companyModal').style.display = 'block';
        }

        // Firma bilgilerini güncelleme modalını açan fonksiyon
        function openCompanyModal(id, c_name, name, address, contact_address, web, c_phone, c_email, c_invoice_address, authorized_contact_name, authorized_contact_title, contact_name, contact_phone, contact_email, tax_office, tax_number, consulting_id, status_code) {
            // Formu güncelleme moduna geçir
            document.getElementById('companyForm').action = 'update_company_process.php'; // Backend'deki güncelleme işlemine yönlendir
            document.getElementById('modalTitle').textContent = 'Firma Bilgilerini Güncelle';
            document.getElementById('modalActionButton').textContent = 'Güncelle';
            
            // Formu gelen verilerle doldur
            document.getElementById('companyId').value = id;
            document.getElementById('c_name').value = c_name;
            document.getElementById('name').value = name;
            document.getElementById('address').value = address;
            document.getElementById('contact_address').value = contact_address;
            document.getElementById('web').value = web;
            document.getElementById('c_email').value = c_email;
            document.getElementById('c_invoice_address').value = c_invoice_address;
            document.getElementById('authorized_contact_name').value = authorized_contact_name;
            document.getElementById('authorized_contact_title').value = authorized_contact_title;
            document.getElementById('contact_name').value = contact_name;
            document.getElementById('contact_email').value = contact_email;
            document.getElementById('tax_office').value = tax_office;
            document.getElementById('tax_number').value = tax_number;
            
            // Telefon numarası alanlarını maskeleme ve doldurma
            setupPhoneInput('c_phone', c_phone);
            setupPhoneInput('contact_phone', contact_phone);
            
            // Dropdown değerlerini ayarla ve içlerini doldur
            document.getElementById('consulting_id').value = consulting_id;
            populateDropdown('consultingDropdown', consultingFirms, consulting_id);
            
            document.getElementById('status_code').value = status_code;
            populateDropdown('statusDropdown', companyStatuses, status_code);

            document.getElementById('companyModal').style.display = 'block';
        }
        
        /**
         * Telefon numarası inputlarına maskeleme uygular.
         * @param {string} id - Input alanı için ID.
         * @param {string} initialValue - Varsayılan değer (sadece rakamlar).
         */
        function setupPhoneInput(id, initialValue) {
            const input = document.getElementById(id);

            // Gelen değeri temizle, sadece rakamları al
            const initialNumbers = initialValue.replace(/\D/g, '');

            function formatNumbers(numbers) {
                let formatted = '';
                if (numbers.length > 0) {
                    formatted += '(' + numbers.substring(0, 3);
                }
                if (numbers.length >= 4) {
                    formatted += ') ' + numbers.substring(3, 6);
                }
                if (numbers.length >= 7) {
                    formatted += ' ' + numbers.substring(6, 10);
                }
                return formatted;
            }

            function updateValue(e) {
                const value = e.target.value.replace(/\D/g, '');
                const formatted = formatNumbers(value);
                e.target.value = formatted;
            }


            // Başlangıç değeri varsa formatla, yoksa boş kalıp göster
            input.value = initialNumbers ? formatNumbers(initialNumbers) : '';
            input.addEventListener('input', updateValue);
        }

        // Sayfa yüklendiğinde global click listener'ı ekle.
        document.addEventListener('DOMContentLoaded', () => {
            // Dropdown'ları modal açılmadan önce ilk defa doldurmak için boş bir çağrı yapabiliriz.
            // Bu, modalı hiç açmadan önce sayfanın yüklenmesiyle birlikte dropdown verilerinin hazır olmasını sağlar.
            populateDropdown('consultingDropdown', consultingFirms);
            populateDropdown('statusDropdown', companyStatuses);
        });
    </script>
</body>
</html>