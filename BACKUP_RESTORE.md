# ISP System Backup & Restore Guide (Nepali)

## 📦 Backup Download
File: `isp-system-v1.0.0.zip` - Yo file download garera naya system ma rakhnu parxa.

---

## 🖥️ Naya System Ma Install Garnu

### Step 1: XAMPP/WAMP Install Garau
- XAMPP download: https://www.apachefriends.org
- Install garera Apache & MySQL start garau

### Step 2: Folder Copy Garau
```bash
# htdocs folder ma yo extract garau
C:\xampp\htdocs\isp-system\
```

### Step 3: Database Create Garau
1. Browser ma jao: http://localhost/phpmyadmin
2. New database banaune: `isp_db` (utf8mb4_unicode_ci)
3. Import garau: `db.sql` file

### Step 4: Config Setup Garau
File: `config.php` edit garau:
```php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';  // XAMPP ma khali rakhnu
$db_name = 'isp_db';
```

### Step 5: Run Garau
Browser ma jao: http://localhost/isp-system/

---

## 🔄 Restore Garnu (Backup Bata)

### Method A: Zip Bata
1. Purano folder hataau
2. Naya zip extract garau
3. Config setup garau (Step 4)
4. Done!

### Method B: Git Bata
```bash
# Naya system ma git install garau
git clone <your-repo-url>
cd isp-system
git checkout v1.0.0
# Step 3-5 garnu (database & config)
```

---

## 📝 Config File Details

```php
// config.php
$db_host = 'localhost';      // Database server
$db_user = 'root';         // Username (XAMPP = root)
$db_pass = '';              // Password (XAMPP = khali)
$db_name = 'isp_db';        // Database name
$base_path = '';            // Folder path
$site_url = 'http://localhost/isp-system';
```

---

## ⚠️ Important Notes

1. **config.php** - Contains database settings
2. **db.sql** - Contains all tables
3. **Hotspot & Billing** - Al database huncha, tei import garnu parxa
4. **Images/Files** - uploads/ folder copy garnu

---

## 🔧 Common Issues

| Problem | Solution |
|---------|----------|
| Blank page | config.php check garau |
| Database error | phpmyadmin ma import try garau |
| Login nai hudaina | Database ma admin user check garau |

---

## 📞 Support
Kisai problem aayo bhane:
1. Check config.php settings
2. Check XAMPP running xa ki nai
3. Check database import vako xa
