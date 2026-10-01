# ISP-PLATFORM-AS — Code Audit Report
_तयार मिति: 2026-09-30_

## 1. यो repo के हो?

Splynx-जस्तो **ISP / WISP billing + network management system**, पूरै **vanilla PHP + MySQL (mysqli)** मा लेखिएको।
कुनै framework छैन, कुनै router छैन — हरेक page एउटा `.php` file हो (classic LAMP style)।

| कुरा | विवरण |
|---|---|
| भाषा | PHP (procedural + केही class), MySQL/MariaDB (mysqli) |
| Frontend | Inline HTML/CSS/JS प्रत्येक PHP file भित्रै |
| Dependency | `composer.json` → phpspreadsheet, twilio/sdk मात्र |
| Tracked files | ~254 |
| Test/CI | **छैन** (0 test, 0 CI workflow) |

### मुख्य module हरू
- **Admin panel** (root मा ~100 php): `dashboard.php`, `users.php`, `user_add/edit/view.php`, `plans.php`, `invoices.php`, `recharge.php`, `tickets.php`, `inventory.php`, `leads.php`
- **Network**: `mikrotik_*.php` (RouterOS API), `olt_dashboard.php` + `includes/bdcom_*.php` (BDCOM/Huawei OLT, SNMP/Telnet), `switch_dashboard.php`, `network_topology.php` (60KB!), `map.php`
- **Monitoring**: `monitoring/` (ping/SNMP + Twilio/WhatsApp/Viber alert), `network_alerts.php`, `noc_dashboard.php`
- **RADIUS**: `radcheck` / `radreply` / `radacct` / `radpostauth` table हरू सिधै manipulate — FreeRADIUS सँग जोडिएको
- **Hotspot**: `hotspot/` — captive portal, voucher, SMS OTP, PIN, admin panel
- **Customer portal**: `customer/` — login, invoice, ticket, WiFi settings
- **Payment**: eSewa + Khalti (`payment/`, `api/payment/`), wallet recharge
- **TR-069**: `genieacs_devices.php`, `includes/genieacs_api.php`
- **Field app**: `mobile_tech.php` (44KB) — technician mobile view
- **Cron**: `scripts/` — auto invoice, FUP, expiry block, backup, uptime

---

## 2. 🔴 Critical Bugs / Security Issues

### 2.1 SQL Injection (सबैभन्दा ठूलो समस्या)
धेरै जसो query string interpolation ले बनेको छ, prepared statement प्रायः छैन।

| File | Line | समस्या |
|---|---|---|
| `user_view.php` | 11–12, 36–68, 303–560 | `$username = $_GET['user']` **escape नगरी** ~19 वटा query मा सिधै घुसाइएको |
| `recharge.php` | 51–100 | उही — `$_GET['user']` raw, अनि `UPDATE`/`DELETE`/`INSERT` मा प्रयोग |
| `api/resolve_alert.php` | 12 | auth छैन + `UPDATE ... WHERE id=$id` (id intval छ, तर auth नै छैन) |
| `hotspot/includes/auth.php` | 62, 119, 340, 414, 484, 569 | login/OTP/session सबै raw string query — **unauthenticated SQLi** |
| `hotspot/includes/sms.php` | 122–149 | OTP table मा raw `$phone`, `$otp` |
| `customer/profile.php`, `customer/wifi_settings.php`, `customer/register.php` | — | raw interpolation |
| `admin_edit.php` | 52 | `UPDATE admins SET password='$new_password'` — string query मा hash |
| `includes/payment_gateway.php` | 160–183 | `transaction_id` raw |

> `hotspot/includes/auth.php` सबैभन्दा खतरनाक — यो login गर्नु अघि नै पुग्ने कोड हो।

### 2.2 Authentication पूरै छुटेका endpoint हरू
`includes/auth.php` include नगरिएका, तर data दिने/बदल्ने file हरू:

```
api/global_search.php        ← सबै customer data search, auth छैन
api/network_topology.php     ← nas table मा INSERT गर्छ, auth छैन
api/resolve_alert.php        ← alert resolve गर्छ, auth छैन
api/snmp_monitor.php         ← device poll, auth छैन
api/payment/esewa.php        ← payment verify, auth छैन
api/payment/get_details.php  ← auth छैन
api_mikrotik.php / api_mikrotik_snmp.php / api_network_status.php / api_status.php
mikrotik_connect.php / mikrotik_manager.php / mikrotik_test.php
olt_power_sync.php / cron_block_expired.php  ← web बाट पनि चल्छ
user_graph_data.php / user_live_graph_data.php / user_status.php
```

### 2.3 Customer login मा plaintext password fallback
`customer/index.php:17`
```php
if (password_verify($password, $user['password']) || $password === $user['password']) {
```
यदि DB मा hash बिग्रियो/plaintext छ भने बाइपास हुन्छ। यसले hash migration लाई पनि रोक्छ।
`customer/login.php` मा चाहिँ सही (`password_verify` मात्र) — दुई वटा login page, दुई फरक logic।

### 2.4 Repo भित्रै hardcoded credentials
```
user-config.php:6            new mysqli("localhost","radius","radiuspass","radius")
scripts/billing_cron.php:12  same
scripts/fup_cron.php:8       same
scripts/metrics_collector.php:11  same
monitoring/db.php:2          new mysqli("localhost","monitordb","password","monitoring")
test_login.php:5             $password = 'admin123'
test_pass.php:3              $password = 'radiuspass'
test_pass2.php:2             real bcrypt hash + password guessing list
cookies.txt                  live PHPSESSID committed
```
`.gitignore` ले `config.php` लाई ignore गरेको छ (राम्रो) — तर बाँकी सबैले त्यो सुरक्षा भत्काइदिएको छ।

### 2.5 CSRF protection **शून्य**
पुरै codebase मा `csrf` शब्द एक ठाउँ पनि छैन। `admin_edit.php` (password change), `branch_delete.php`, `disconnect_user.php`, `recharge.php` (renew) — सबै GET/POST मा एकै click बाट trigger हुन सक्छन्।

### 2.6 Command injection risk + quoting bug — `disconnect_user.php`
```php
$username = escapeshellarg($_POST['username']);
$cmd = "echo 'User-Name = $username' | $radclient -x {$nas['ip_address']}:3799 disconnect {$nas['secret']}";
```
- `escapeshellarg()` ले आफैँ quote थप्छ, तर यो पहिले नै single-quote भित्र छ → **quoting भाँचिन्छ, command fail हुन्छ** (functional bug)
- `$nas['ip_address']` र `$nas['secret']` **escape गरिएको छैन** → NAS record edit गर्न सक्ने जोसुकैले shell command चलाउन सक्छ
- RADIUS secret error message मा leak हुन सक्छ

### 2.7 Production मा error display on
`dashboard.php`, `nas_edit.php`, `quick_renew.php`, `customer/login.php`, `monitoring/*` मा `ini_set('display_errors', 1)` — stack trace, query, path सबै browser मा देखिन्छ।

### 2.8 Session security
`session_start()` सादा — `session_regenerate_id()` login पछि छैन (session fixation), cookie मा `httponly`/`secure`/`samesite` set छैन।

---

## 3. 🟡 Repo Hygiene समस्या

Git मा commit भइसकेका जंक file हरू (`.gitignore` ले `*.swp` मात्र समात्छ, `.sw[a-i]` होइन):
```
.admin.php.swd/.swg, .recharge.php.swc/.swf/.swi
.user_view.php.swg/.swh/.swi, .users.php.sv*/.sw*  (13 वटा!)
includes/.sidebar.php.swe/.swh, assets/css/.theme.css.sw*
assets/css/theme.bak2, assets/css/theme.csswq
user_view.bak1, monitoring/dashboard.bak1, user_add_befor_plug
isp-system-v1.0.0.zip   ← 518 KB build artifact repo भित्र
cookies.txt             ← session cookie
test_login.php test_pass.php test_pass2.php test_post.php test_session.php test.php
```

अन्य:
- **`config.php` gitignored छ तर `config.php.example` छैन** → नयाँ मान्छेले clone गरे app चल्दैन, कुन variable चाहिन्छ थाहा हुँदैन
- `map_api.php` र `map_api_temp.php`, `report/` र `reports/`, `invoices.php` र `billing/invoices.php` — duplicate
- `network_topology.php` 60KB, `mobile_tech.php` 44KB, `user_view.php` 38KB — single file मा PHP+HTML+CSS+JS सबै
- Commit history मा जम्मा **1 commit** — history छैन

---

## 4. ✅ के सुधार गर्ने — Priority अनुसार

### P0 — अहिल्यै (security)
1. **`hotspot/includes/auth.php` सबै query prepared statement मा बदल्ने** — यो unauthenticated हो
2. **`customer/index.php:17` को `|| $password === $user['password']` हटाउने**, अनि `customer/index.php` लाई पूरै हटाएर `customer/login.php` मात्र राख्ने
2. **सबै `api/*.php` मा auth guard थप्ने** — session वा API key + HMAC
3. **Hardcoded DB password हटाउने**: सबैलाई `require __DIR__.'/config.php'` गराउने, credentials `.env` वा gitignored `config.php` मा
4. **Git history बाट secret हटाउने** + ती password हरू rotate गर्ने (`radiuspass`, `admin123`)
5. **`test_*.php` सबै delete** — production मा deploy भए password oracle बन्छ
6. `ini_set('display_errors', 1)` सबै हटाएर एकै ठाउँ (`config.php`) मा env-based राख्ने

### P1 — छिट्टै
7. **CSRF token helper** बनाएर सबै POST form मा लगाउने:
   ```php
   // includes/csrf.php
   function csrf_token(){ return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
   function csrf_check(){ if(!hash_equals($_SESSION['csrf']??'', $_POST['_csrf']??'')) { http_response_code(419); exit('CSRF'); } }
   ```
8. **`user_view.php` + `recharge.php` को `$_GET['user']` parameterize गर्ने** — यी दुई सबैभन्दा धेरै touch हुने page
10. **`disconnect_user.php` fix**: quoting मिलाउने, `$nas['secret']`/`ip` पनि `escapeshellarg()` गर्ने, error message बाट secret हटाउने
11. Login पछि **`session_regenerate_id(true)`**, र `session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>true])`
12. **XSS**: सबै echo मा `htmlspecialchars()` — छोटो helper `e($v)` बनाउने

### P2 — Structural
13. **`config.php.example` + `README` मा setup step** थप्ने (schema SQL सहित — अहिले कतै schema छैन!)
14. **`.gitignore` सच्याउने**: `.*.sw[a-p]`, `*.bak*`, `*.zip`, `cookies.txt`, `.env`
15. Junk/duplicate file हरू `git rm` गर्ने
16. **साझा layout**: `includes/header/sidebar/topbar` छ तर inline CSS हरेक page मा दोहोरिएको — `assets/css/theme.css` मा सार्ने
17. **DB layer helper** बनाउने: `db_all($sql, $params)`, `db_one()`, `db_exec()` → prepared statement default
18. **Composer autoload प्रयोग गर्ने** (`vendor/autoload.php`), `includes/*` लाई PSR-4 class मा सार्ने
19. **GitHub Actions CI**: `php -l` सबै file मा + PHPStan level 1 + PHP_CodeSniffer
20. `network_topology.php`, `mobile_tech.php` लाई logic / view / JS मा फुटाउने

### P3 — Feature/Ops
21. RBAC सही गर्ने — अहिले `isSuperAdmin()`/`isBranchAdmin()` helper छ तर धेरै page मा check हुँदैन; branch isolation पनि query मा enforce छैन
22. Rate limiting — `includes/security.php` मा admin login को lockout छ, तर customer/hotspot login मा छैन
23. Payment callback मा **signature verification** (eSewa/Khalti) — अहिले transaction_id मात्र match गरिएको देखिन्छ
24. Audit log सबै mutation मा (`logActivity()` छ, तर प्रायः call हुँदैन)
25. Structured logging + cron को lock file (`scripts/*` मा concurrent run protection छैन)

---

## 5. सुझाव गरिएको क्रम (practical)

```
Week 1  → P0 items 1–7      (security emergency)   ✅ सकियो
Week 2  → P1 items 8–12     (CSRF + SQLi + session) ✅ प्रायः सकियो
Week 3  → P2 items 13–16    (repo सफा + setup docs) ✅ सकियो
Week 4+ → P2 17–20, P3      (refactor + CI)         ◻ बाँकी
```

---

## 6. ✅ यस session मा के-के fix भयो

### नयाँ infrastructure
| File | काम |
|---|---|
| `.env.example` | सबै secret को एकल source; real `.env` gitignored |
| `config.php.example` | bootstrap — env load, error policy, hardened session cookie, `$conn`, `e()` |
| `includes/env.php` | dependency-free `.env` loader + `env()` helper |
| `includes/db.php` | `db_one/db_all/db_value/db_exec/db_insert/db_like` — prepared statement मात्र |
| `includes/csrf.php` | `csrf_token() / csrf_field() / csrf_check()` |
| `includes/api_auth.php` | `api_require_auth()` — session वा `X-API-Key` (constant-time) |
| `.github/workflows/ci.yml` | हरेक push मा `php -l`, committed-secret detection, SQLi grep |

### Security fixes
- **SQL injection हटाइयो**: `hotspot/includes/auth.php` (पूरै rewrite — login/voucher/MAC/IP/PPPoE/OTP/session/blacklist/log), `user_view.php` (~19 query), `recharge.php`, `admin_edit.php`, `branch_delete.php`, `user_status.php`, `customer/{index,login,profile,register,wifi_settings}.php`, `api/{global_search,network_topology,resolve_alert}.php`
- **Header-driven injection बन्द**: `HotspotAuth::createSession()` मा `X-Client-MAC` header सिधै INSERT हुन्थ्यो → अब bound + `getClientMac()` ले MAC format validate गर्छ
- **Command injection / RCE**: `disconnect_user.php` र `user_view.php` को `radclient` call — अब `proc_open` + stdin, NAS IP `FILTER_VALIDATE_IP`, secret `escapeshellarg`, output log मा मात्र (पहिले response मा RADIUS secret leak हुन्थ्यो)
- **Quoting bug fix**: `escapeshellarg()` single-quote भित्र थियो → disconnect कहिल्यै काम गर्दैनथ्यो
- **Auth bypass हटाइयो**: `customer/index.php` को `|| $password === $user['password']`
- **15 endpoint मा auth guard**: `api/*`, `api_mikrotik*.php`, `api_status.php`, `olt_power_sync.php`, `user_*_data.php`; `cron_block_expired.php` अब CLI/API-key मात्र
- **CSRF protection**: helper + सबै login form, `user_view.php` (5 form), `recharge.php`, `admin_edit.php`, `branches.php`, customer portal; `includes/header.php` ले meta tag + jQuery/fetch मा auto-attach गर्छ
- **GET → POST**: `branch_delete.php`, `recharge.php?del_invoice`, `user_status.php` (एक click/`<img>` बाट trigger हुन्थे)
- **Session**: सबै login मा `session_regenerate_id(true)`; cookie `httponly` + `samesite=Lax` + `secure`
- **`display_errors` हटाइयो** 15 file बाट → `APP_DEBUG` env ले नियन्त्रण

### Secrets
`radiuspass`, `monitordb/password`, Twilio SID+token, GenieACS `StrongPass123`, Khalti keys — सबै code बाट हटेर `.env` मा गए।
`test_pass2.php` (bcrypt hash + guess list), `cookies.txt` (live PHPSESSID), `test_*.php` delete भए।

> ⚠️ **तपाईंले अझै गर्नुपर्ने**: ती password/token हरू git history मा अझै छन् — **rotate गर्नुहोस्** (DB password, Twilio token, GenieACS, Khalti), र चाहनुहुन्छ भने `git filter-repo` ले history सफा गर्नुहोस्।

### Bug fixes (security बाहेक)
- `customer/profile.php` — `$address` कहिल्यै POST बाट पढिँदैनथ्यो, address हरेक save मा मेटिन्थ्यो
- `recharge.php` — renewal अब transaction भित्र; बीचमै fail भए customers/radcheck/radreply/invoices out-of-sync हुँदैन
- `customer/register.php` — transaction + username/email validation + min 8 char
- `hotspot` PPPoE password compare अब `hash_equals()` (timing attack)

### Repo hygiene
30+ vim swap file, `.bak`, `theme.csswq`, `isp-system-v1.0.0.zip` (518KB), `cookies.txt`, 7 वटा test file हटे। `.gitignore` पूरै rewrite। README मा real setup guide + cron + security convention थपियो।

**Net: 108 files changed, ~4400 lines deleted.**

---

## 7. ✅ Phase 2 — raw SQL सफाइ (यसै session मा)

Phase 1 पछि बाँकी रहेका सबै interpolated query हरू batch मा हटाइयो। हरेक batch पछि
`git grep -nE '(query|exec)\("[^"]*\$' -- '<glob>'` ले verify गरिएको छ।

### Batch 1 — `hotspot/` (clean ✅)
- `hotspot/admin/{roles,users,blacklist,hotel,settings,index,plans}.php`, `hotspot/captive_portal.php`, `hotspot/includes/sms.php` — सबै parameterised + POST handler भएका ठाउँमा `csrf_check()`, 8 admin file मा `csrf_field()` inject
- `hotspot/includes/plan_manager.php` — पूरै convert (17 method)
- `hotspot/includes/voucher.php` — `generatePins`/`validatePin`/`usePin`/`getProfile`

### Batch 2 — `includes/` (clean ✅)
- `includes/notification.php`, `includes/payment_gateway.php`, `includes/olt_api.php`

### Batch 3 — API + root endpoints (clean ✅)
- `api/olt_ont.php`, `api/snmp_monitor.php`, `api/mikrotik_olt_integration.php`, `api/payment/{esewa,khalti,get_gateway,get_details}.php`
- `billing/{gateways,invoices,payments,subscriptions}.php`
- `mobile_tech_api.php`, `provisioning_api.php`, `onu_power_api.php`, `report_api.php`, `system_config.php`
- `olt_dashboard.php`, `switch_dashboard.php`, `olt_power_sync.php`, `admin.php`, `nas.php`, `plans.php`, `faults.php`, `branch_edit.php`, `ticket_detail.php`, `work_diary_api.php`

### Batch 3 मा भेटिएका गम्भीर logic bug हरू

| फाइल | समस्या | असर | अवस्था |
|---|---|---|---|
| `api/payment/khalti.php` `handleWebhook()` | webhook body मा कुनै signature check थिएन — `{"event":"payment.success","transaction_id":"..."}` POST गरे मात्रै invoice **paid** हुन्थ्यो | **जो-कोहीले नतिरी bill clear** गर्न सक्थ्यो | ✅ अब Khalti सँग token re-verify + amount match |
| `api/payment/{esewa,khalti}.php` `initiatePayment()` | `amount` request body बाट लिइन्थ्यो | Rs 5000 को invoice `amount=1` पठाएर तिर्न सकिन्थ्यो | ✅ अब invoice बाट amount पढिन्छ |
| `api/payment/khalti.php` `verifyPayment()` | `$result['success']` मात्र हेरिन्थ्यो, captured amount होइन | कम रकममा invoice settle | ✅ paisa comparison थपियो |
| दुबै gateway callback | completed transaction दोहोर्‍याएर process हुन्थ्यो | replay गरेर दोहोरो credit | ✅ idempotency guard + `WHERE status <> 'completed'` |
| `mobile_tech_api.php` `send_otp` | response मै `debug_otp` फर्काउँथ्यो | OTP verification निरर्थक — कसैले पनि ticket close | ✅ हटाइयो; `mt_rand` → `random_int` |
| `mobile_tech_api.php` `confirm_collection` | `amount` POST बाट; `$user`/`$plan` null guard छैन | गलत billing + fatal error | ✅ plan price बाट, guard थपियो |
| `mobile_tech_api.php` `update_status` | जुनसुकै string ticket status मा लेखिन्थ्यो | data corruption | ✅ whitelist |
| `provisioning_api.php` `search_customer` | LIKE wildcard escape छैन | `q=%` ले सबै customer dump | ✅ `db_like()` |
| `provisioning_api.php` reboot/delete/get_power | OLT not-found guard छैन | fatal | ✅ guard थपियो |
| `report_api.php` | `$type` सिधै `Content-Disposition` header मा | header injection; साथै CSV formula injection | ✅ whitelist + `csv_cell()` |
| `system_config.php` | extension मात्र हेरेर upload; CSRF छैन | web-served dir मा फाइल राख्न सकिने | ✅ `getimagesize()` + `csrf_check()` |
| `api/payment/get_details.php` | `$transaction['full_name']` column नै छैन; सबै echo unescaped | खाली field + stored XSS | ✅ `CONCAT_WS` + `e()` |
| `includes/olt_api.php`, 6 अन्य file | `include 'config.php'` (CWD-निर्भर) | cron/subdir बाट चलाउँदा fail | ✅ `require_once __DIR__` |
| `hotspot/includes/plan_manager.php::updatePlan` | caller को array key सिधै column name बन्थ्यो | arbitrary column write | ✅ whitelist |
| `hotspot/includes/voucher.php` | PIN loop भित्र हरेक iteration मा profile query | N वटा voucher = N query | ✅ loop बाहिर hoist |
| `includes/notification.php` | customer नभेटिए fatal | notification cron crash | ✅ guard |

---

### Batch 4 — पेज, cron र helper (clean ✅)
`leads.php`, `invoices.php`, `customer/invoices.php`, `customer/ticket_view.php`, `ticket_view.php`, `ticket_detail.php`, `ticket_new.php`, `user_expiry.php`, `user_edit.php`, `user_add.php`, `users.php`, `user_log.php`, `user_graph_data.php`, `user_live_graph{,_data}.php`, `user_usage_data.php`, `plans.php`, `inventory.php`, `knowledge_base.php`, `kb_view.php`, `system_logs.php`, `network_alerts.php`, `map_api.php`, `wire_lease_api.php`, `work_diary_api.php`, `mikrotik_{dashboard,traffic_api}.php`, `billing/gateways.php`, `payment/{esewa_verify,recharge_wallet}.php`, `scripts/{billing_cron,fup_cron,fup_speed}.php`

### Batch 5 — multi-line queries (clean ✅)
`api/{olt_ont,snmp_monitor}.php`, `billing/payments.php`, `includes/{bdcom_olt,olt_api,security}.php`, `customer/{dashboard,usage_history}.php`, `payment/{khalti_pay,khalti_verify}.php`, `quick_renew.php`, `import_customers.php`, `notification_settings.php`, `hotspot/admin/{add_profile,hotel}.php`, `scripts/{auto_disable_expired,auto_invoice}.php`, `report/export_*_users.php`

**अन्तिम verification:** हरेक non-vendor PHP file मा `->query("...")` / `->prepare("...")` भित्रको string literal मा `$var` खोज्दा — **० मात्र**।

### Batch 4–5 मा भेटिएका bug हरू

| फाइल | समस्या | असर | अवस्था |
|---|---|---|---|
| `payment/khalti_verify.php` | auth guard छैन; `username` POST body बाट | **जो-कोहीले जुनसुकै ग्राहकको wallet भर्न सक्थ्यो** | ✅ session बाट username, amount match, replay guard |
| `report/export_{active,expired,expiring,new}_users.php` | PHP code बीचमै खुला SQL fragment | चारै export **parse error** — कहिल्यै चलेनन् | ✅ पुनर्लेखन + filter query भित्र + CSV escape |
| `customer/ticket_view.php` | reply insert मा ownership check छैन | एक ग्राहकले अर्काको ticket मा पोस्ट | ✅ ticket पहिले load + `customer_id` match |
| `customer/invoices.php` | `WHERE username=$id` (numeric id) | पेज सधैँ खाली | ✅ `customers` सँग join |
| `ticket_detail.php` | बनेको statement फालेर id-only lookup | `?username=` सधैँ "not found" | ✅ |
| `wire_lease_api.php` terminate | पटक-पटक चलाउँदा `used_cores` घट्दै जान्थ्यो | route capacity शून्य | ✅ status guard + transaction |
| `wire_lease_api.php` add | check-then-insert race | एउटै core दुई जनालाई lease | ✅ `FOR UPDATE` lock |
| `payment/esewa_verify.php` | refresh/back गर्दा फेरि credit | wallet दोहोरो भरिने | ✅ `txn_id` replay guard |
| `invoices.php` | GET link ले invoice delete + expiry rollback | `<img>` ले पनि trigger | ✅ POST + CSRF |
| `leads.php` convert | username uniqueness छैन; दोहोरो convert | duplicate customer | ✅ suffix + status guard |
| `ticket_new.php` | escape + prepare दुवै | ticket मा literal backslash | ✅ |
| `map_api.php` | client capacity अनुसार असीमित port row; delete मा orphan | table भरिने | ✅ cap 1024 + cascade |
| `user_edit.php` | password लेखेपछि rename | दुई वटा `Cleartext-Password` row | ✅ क्रम उल्टाइयो |
| `quick_renew.php` | expiry र invoice अलग query | बीचमा fail भए mismatch | ✅ transaction |
| `scripts/fup_speed.php`, `customer/dashboard.php` | row नभेटिए fatal | cron/पोर्टल crash | ✅ guard |
| `includes/olt_api.php::getAllOnus` | method भित्र `include 'config.php'` | CWD + scope निर्भर | ✅ `require_once __DIR__` + `global` |

---

## 8. ◻ अझै बाँकी (अर्को phase)

1. बाँकी सबै form मा `csrf_field()` (जोखिमपूर्ण write path हरू सकिए)
2. `hotspot/admin/users.php` — HTML output truncated, `<form>` छैन, JS ले नभएका DOM id खोज्छ
3. `api/payment/*` मा `Access-Control-Allow-Origin: *` — payment endpoint मा origin सीमित गर्ने
4. Duplicate page merge: `index.php`/`login.php`, `customer/index.php`/`customer/login.php`, `report/`/`reports/`, `invoices.php`/`billing/invoices.php`
5. `network_topology.php` (60KB), `mobile_tech.php` (44KB) लाई logic/view/JS मा split
6. DB schema SQL repo मा राख्ने (अहिले कतै छैन — clone गरेर table बनाउन सकिँदैन)
7. Automated test सुरु गर्ने — अहिले शून्य; CI मा `php -l` मात्र छ

> RBAC/branch isolation (पुरानो item 4) §10 मा सकियो।

---

## 9. ✅ Phase 3 — XSS / output escaping (सकियो)

### 9.1 केन्द्रीय helper: `includes/html.php`

`config.php` ले अब `includes/html.php` require गर्छ, त्यसैले हरेक page मा यी function उपलब्ध छन्:

| Function | कहाँ प्रयोग गर्ने |
|---|---|
| `e($v)` | HTML text र quoted attribute value. `null`→`''`, bool/array पनि सुरक्षित handle गर्छ |
| `e_attr($v)` | `e()` कै alias, attribute context स्पष्ट पार्न |
| `e_js($v)` | `<script>` भित्र। पूरै JS literal (quote सहित) फर्काउँछ — **`e()` यहाँ गलत हो** |
| `e_url($v)` | query-string / path segment (`rawurlencode`) |
| `e_href($v)` | पूरा URL। relative वा `http/https/mailto/tel` बाहेक `#` फर्काउँछ, त्यसैले `javascript:` block हुन्छ |

पहिले `e()` सिधै `config.php.example` भित्र लेखिएको थियो; अब एउटै ठाउँमा आयो।

### 9.2 PHP output escaping

Repo भरि **१,३८५ वटा `<?= … ?>` site** classify गरियो:

| वर्ग | संख्या | कारबाही |
|---|---|---|
| पहिले नै सुरक्षित | 688 | `htmlspecialchars()`, `number_format()`, `csrf_token()`, constant-only ternary (`'selected'`/`''`, color code) आदि |
| Auto-wrapped | **677** | `<?= $x ?>` → `<?= e($x) ?>` — 82 फाइलमा |
| `<script>` भित्र | 12 | numeric लाई `(int)` cast; string लाई `e_js()` |
| हातले | 8 | nested ternary, `href` build, `(int)` cast |

Constant मात्र निकाल्ने expression (जस्तै `$row['status']=='active' ? 'badge-success' : 'badge-danger'`) मा जानाजान `e()` लगाइएको छैन — त्यहाँ user data कहिल्यै output मा पुग्दैन।

### 9.3 JavaScript DOM-XSS (PHP escaping ले नछुने बग)

`innerHTML` मा template literal हालेर render गर्ने ठाउँमा **stored XSS** भेटियो — यो `e()` ले समाधान हुँदैन, किनकि data JSON API बाट client-side आउँछ:

| फाइल | बग |
|---|---|
| `work_diary.php` | `${entry.title}`, `${entry.content}`, `${c.comment}` — कुनै पनि staff ले diary entry मा `<img src=x onerror=…>` लेखे सबै admin को browser मा script चल्थ्यो |
| `mobile_tech.php` | `${j.full_name}`, `${j.address}`, `${j.subject}` — customer ले आफ्नै नाम/ठेगानामा payload राखे technician app मा execute हुन्थ्यो |
| `map.php` | `${c.full_name}`, `${n.name}`, `${r.name}` (Leaflet `bindPopup`) |
| `olt_dashboard.php` | `${ont.onu_serial}`, `${ont.status}` |

तीनवटै फाइलमा client-side `esc()` / `escJs()` / `num()` helper थपेर हरेक interpolation wrap गरियो। साथै:

- `work_diary.php` — `'<?php echo $_SESSION['role'] ?>'` भन्ने nested-quote hack हटाएर `IS_SUPERADMIN` boolean बनाइयो (delete को असली check server-side मै छ, यो button देखाउन मात्र हो); fetch URL मा `encodeURIComponent()`
- `mobile_tech.php` — `Debug OTP: ${res.debug_otp}` हटाइयो (API ले अब OTP फर्काउँदैन, dead reference थियो); `tel:` link मा `encodeURIComponent()`
- `map.php` / `olt_dashboard.php` — inline `onclick="fn('${serial}')"` मा `escJs()`, id हरूमा `num()`

### 9.4 प्रमाणीकरण

- Delimiter-balance checker: 85 फाइल, **0 problem**
- SQL regression: interpolated SQL **0** (बाँकी दुई hit `exec()` shell call हुन्, `escapeshellarg()` लागेको छ)

---

## 10. ✅ Phase 4 — RBAC, branch isolation र बाँकी XSS (सकियो)

### 10.1 Role vocabulary को बेमेल (असली बग)

`includes/auth.php` मा `isBranchAdmin()` ले `'branchadmin'` र `isStaff()` ले `'staff'` खोज्थ्यो — तर database मा कहिल्यै त्यो value बस्दैन। असली role हुन् `superadmin` / `manager` / `support` (`admin.php` को `$allowed_roles` हेर्नुहोस्)। अर्थात् **ती दुई function सधैं `false` फर्काउँथे** — dead code थियो।

### 10.2 नयाँ `includes/rbac.php`

`auth.php` ले require गर्छ, त्यसैले authenticate हुने हरेक page मा उपलब्ध:

| Function | काम |
|---|---|
| `require_role($role)` | Minimum role (`'manager'`) वा explicit list। नमिले 403 + activity log |
| `branch_scope($alias)` | `[" AND c.branch_id = ?", [$id]]` फर्काउँछ; superadmin लाई `['', []]` — त्यसैले query मा बिना `if` splice गर्न मिल्छ |
| `require_branch_access($row)` | पहिले नै load भएको row अर्को branch को हो भने 403 |
| `require_customer_access($conn, $username)` | `?user=` लिने page का लागि — customer resolve गरेर branch जाँच्छ |
| `rbac_deny($code, $msg)` | JSON endpoint लाई JSON, page लाई HTML error |

Role hierarchy: `superadmin 30 > manager 20 > support 10`। पुराना `isSuperAdmin()` आदि alias भएर चल्छन्, तर अब सही role मा map हुन्छन्।

### 10.3 Privilege escalation (सबैभन्दा गम्भीर)

| फाइल | पहिले | अब |
|---|---|---|
| **`admin_edit.php`** | **कुनै role check थिएन** — जो-कोही logged-in ले `?id=1` खोलेर आफैँलाई superadmin बनाउन सक्थ्यो | `require_role('superadmin')` |
| `system_config.php` | global config जो-कोहीले बदल्न सक्थ्यो | `require_role('superadmin')` |
| `notification_settings.php` | SMTP/SMS credential देखिन्थ्यो | `require_role('superadmin')` |
| `billing/gateways.php` | payment gateway API key/secret | `require_role('superadmin')` |
| `hotspot/admin/settings.php` | SMS gateway credential | `require_role('superadmin')` |
| `import_customers.php` | bulk customer creation | `require_role('manager')` |

### 10.4 Secret हरू DOM मा जानु

`billing/gateways.php` ले हरेक gateway को `api_key` र `api_secret` लाई `onclick="editGateway(…)"` भित्र हाल्थ्यो — जबकि `editGateway()` त **केवल `alert(id + name)` गर्ने stub** थियो। Credential हरू argument बाट पूरै हटाइयो।

`hotspot/admin/settings.php` मा SMS API key/password `value="…"` मा render हुन्थ्यो। अब render हुँदैन; field खाली छोडे पुरानै value रहन्छ ("unchanged - type to replace")।

### 10.5 IDOR / branch isolation

`branch_id` भएका table: `admins`, `customers`, `tickets`।

- `users.php` — list र stats दुवै `branch_scope()` ले scoped
- `tickets.php` — 4 वटा अलग count query एउटै scoped query मा मिलाइयो, list पनि scoped
- `user_view.php`, `ticket_view.php` — load भएको row मा `require_branch_access()`
- `user_edit.php`, `user_graph.php`, `user_log.php`, `user_status.php`, `user_expiry.php`, `user_usage_data.php`, `user_live_graph.php`, `quick_renew.php`, `disconnect_user.php` — `require_customer_access()`

### 10.6 GET मा destructive action + CSRF

`tickets.php?delete=`, `admin.php?del=`, `nas.php?del=`, `plans.php?del=`, `knowledge_base.php?del=` — पाँचै वटा **token बिना** थिए। `csrf_check()` ले GET लाई छोड्ने भएकाले नयाँ **`csrf_check_request()`** थपियो (query string बाट पनि token पढ्छ) र लिंकमा `&_csrf=` जोडियो।

`tickets.php` को delete ले अब branch ownership पनि जाँच्छ — पहिले जुनसुकै branch को ticket मेटाउन मिल्थ्यो।

### 10.7 Phase 3 ले छुटाएको XSS pattern

**`onclick="fn('<?= e($x) ?>')"` सुरक्षित छैन।** Browser ले attribute पहिले HTML-decode गर्छ, त्यसैले `e()` को `&#39;` फेरि `'` बन्छ र JS string बाट breakout हुन्छ। नयाँ **`e_attr_js()`** ले पहिले JS-escape (`'` → `\'`) अनि HTML-escape गर्छ, जसले backslash जोगिन्छ। **१८ site** मा लागू (`network_topology.php` 8, `billing/gateways.php` 6, `genieacs_devices.php` 2, `inventory.php` 1, `mikrotik_dashboard.php` 1)।

साथै `echo "<div>$var</div>"` ढाँचाका 5 site (`admin_edit.php` 2, `customer/login.php`, `import_customers.php`, `payment/khalti_verify.php`) escape गरिए।

### 10.8 अन्य

- `admin.php` ले `$stmt->error` सिधै page मा देखाउँथ्यो (DB structure leak) → generic message + `error_log()`
- `includes/auth.php` र `hotspot/admin/settings.php` का raw `$conn->query()` + `while(fetch_assoc())` → `db_all()` + `foreach`

### 10.9 प्रमाणीकरण

- Delimiter-balance checker: 33 फाइल, **0 problem**
- सबै helper call site definition सँग resolve हुन्छन् (`require_role` 6, `require_customer_access` 9, `e_attr_js` 5, `csrf_check_request` 5, `branch_scope` 2, `require_branch_access` 3)
- Loop pairing र बाँकी `fetch_assoc()` जाँचिए

---

## 11. ✅ Phase 5 — Database schema (सकियो)

### 11.1 समस्या

Repo मा **कुनै SQL थिएन**। Clone गरेर चलाउँदा पहिलो query मै हरेक page मर्थ्यो — अर्थात् नयाँ install असम्भव थियो, र automated test लेख्ने कुनै आधार थिएन।

### 11.2 `database/schema.sql` — 67 table

Code का हरेक `INSERT INTO t (…)` column list, `UPDATE … SET` clause र qualified column reference निकालेर, अनि प्रत्येक column कसरी प्रयोग हुन्छ (int bind, `CURDATE()` सँग तुलना, money format) हेरेर type अनुमान गरियो।

समूहहरू: tenancy/staff · plans+customers · FreeRADIUS · billing · support · network/FTTH · hotspot · settings।

उल्लेखनीय निर्णय:

- **FreeRADIUS table हरू (`radacct`, `radcheck`, `radreply`, `radusergroup`, `radpostauth`) upstream FreeRADIUS 3.x layout कै हुन्** — `radiusd` ले ठ्याक्कै ती column नाम खोज्छ, त्यसैले "सफा" पार्न मिल्दैन।
- `radacct` मा `idx_radacct_online (acctstoptime, username)` थपियो — dashboard हरू बारम्बार "अहिले को online छ" सोध्छन्, जुन `WHERE acctstoptime IS NULL` हो; index बिना पूरै table scan हुन्थ्यो।
- `payment_transactions` मा `UNIQUE KEY (gateway_id, reference_id)` — `khalti_verify.php` को replay सुरक्षा application code मा मात्र थियो, जुन concurrency मा भरपर्दो हुँदैन। अब database ले नै रोक्छ।
- `wallet_transactions` मा `UNIQUE KEY (gateway, txn_id)` — त्यही कारण।
- Branch isolation का तीन table (`admins`, `customers`, `tickets`) मा `branch_id` सँग index र FK।

### 11.3 Schema लेख्दा भेटिएका असली code बग

| बग | विवरण | कारबाही |
|---|---|---|
| **`ticket_replies` को दुई असंगत shape** | `ticket_view.php` र `customer/ticket_view.php` ले `(ticket_id, sender, message)` लेख्छन्; `ticket_detail.php` ले `(ticket_id, reply_text, created_at, admin)` — जुन schema भए पनि एउटा page भाँचिन्थ्यो | `ticket_detail.php` कतैबाट linked थिएन (orphan) → हटाइयो |
| **`payment_transactions.ref_id` vs `reference_id`** | `api/payment/esewa.php` ले `ref_id` लेख्थ्यो, `includes/payment_gateway.php` ले `reference_id` — एउटा "Unknown column" ले fail हुन्थ्यो | `esewa.php` लाई `reference_id` मा मिलाइयो |
| छुटेका table | `recharge`, `billing_history`, `billing_cycles` — code ले लेख्छ तर कहीँ document थिएन | schema मा थपिए |
| छुटेका column | `customers.blocked`, `payment_transactions.payment_method`, `customer_subscriptions.billing_cycle_id` | थपिए |

### 11.4 `database/seed.sql`

Branch, 3 plan, billing cycle, role, र **`includes/auth.php` ले हरेक request मा पढ्ने `session_timeout`/`session_idle_timeout`** — ती नभए चुपचाप default मा झर्थ्यो। `system_config` मा ठ्याक्कै एउटा row (page ले `LIMIT 1` गर्छ)।

**Admin account जानाजान seed मा राखिएको छैन** — version control मा password hash पठाउनु भनेको सबैलाई password दिनु हो, जुन यही audit ले `admin123` मा औंल्याएको समस्या हो।

### 11.5 `scripts/create_admin.php`

CLI-only (`PHP_SAPI` जाँच)। Password सधैं prompt हुन्छ, **argument बाट कहिल्यै लिँदैन** — argument shell history र `ps` मा देखिन्छ। `stty -echo` ले echo बन्द गर्छ, नभए warning दिन्छ। Non-superadmin लाई branch अनिवार्य, किनभने `auth.php` ले branch बिनाको non-superadmin लाई login गर्नै दिँदैन।

### 11.6 CI मा नयाँ `schema` job

MySQL 8 service मा:
1. `schema.sql` + `seed.sql` load हुन्छ (MySQL आफैँले syntax जाँच्छ)
2. `schema.sql` दोहोर्‍याएर चलाइन्छ — idempotent छ कि
3. **Code ले लेख्ने हरेक column `information_schema` मा छ कि** — schema र code बेग्लै हुन नदिने असली guard

### 11.7 प्रमाणीकरण

- `sqlglot` (MySQL dialect): 71 statement parse, 67 `CREATE TABLE`, कुनै duplicate छैन, सबै FK ले अस्तित्वमा भएकै table देखाउँछन्
- Code का सबै `INSERT`/`UPDATE` column schema सँग resolve हुन्छन् — **0 missing**

---

## 12. ✅ Phase 6 — Automated test (सकियो)

### 12.1 अन्ततः वास्तविक PHP

यो सत्रभरि sandbox मा PHP binary थिएन, त्यसैले Python ले लेखिएको delimiter-balance checker ले काम चलाइएको थियो — त्यो brace/paren मात्र गन्छ, PHP grammar बुझ्दैन। यसपटक npm बाट **`@php-wasm/node` (PHP 8.2.33, WebAssembly)** ल्याएर वास्तविक PHP parser चलाइयो।

### 12.2 त्यसले तुरुन्तै समातेको critical regression

```
FAIL includes/rbac.php
    syntax error, unexpected token "const" on line 24
```

PHP मा `const` केवल file को top level वा class भित्र मात्र लेख्न मिल्छ — **`if` block भित्र होइन**। Phase 4 मा थपिएको `includes/rbac.php` यसै कारण parse नै हुँदैनथ्यो, र `auth.php` ले त्यही require गर्छ — अर्थात् **हरेक login-गरिएको page 500 हुन्थ्यो**। `define()` मा बदलेर मिलाइयो।

Python checker ले यो कहिल्यै भेट्दैनथ्यो, किनभने bracket सन्तुलन त ठीकै थियो।

### 12.3 दोस्रो समातिएको बग

`e()` मा comment लेख्दा भित्र `?>` अक्षर परेको थियो। PHP ले **`//` comment भित्रको `?>` लाई पनि PHP block को अन्त्य मान्छ**, त्यसैले फाइल भाँचियो। Linter ले तुरुन्तै देखायो।

### 12.4 `tests/` — dependency-free harness

Repo मा composer dev dependency छैन र CI ले runtime मात्र install गर्छ, त्यसैले PHPUnit थप्नुभन्दा ~८० line को `tests/bootstrap.php` लेखियो। `php tests/run.php` जताततै चल्छ, fail भए exit code 1।

| फाइल | के जाँच्छ |
|---|---|
| `tests/escaping_test.php` | `e()`, `e_js()`, `e_attr_js()`, `e_url()`, `e_href()` — XSS payload सहित |
| `tests/rbac_test.php` | role hierarchy, `branch_scope()` fragment, row ownership |
| `tests/csrf_test.php` | token issue, POST/header/GET validation, cross-session rejection |
| `tests/db_helpers_test.php` | `db_types()`, `db_like()` |

उल्लेखनीय assertion हरू:

- `e_href()` ले `javascript:`, `JaVaScRiPt:`, `data:`, `vbscript:` र अगाडि space राखेको `javascript:` सबै `#` बनाउँछ
- `e_js('</script>')` मा `</` रहँदैन — नत्र JS string भित्र भए पनि `<script>` element बन्द हुन्छ
- `e_attr_js("a'b")` लाई HTML-decode गर्दा `a\'b` आउँछ (bare quote होइन) — यही Phase 3 ले छुटाएको बग हो
- `branch_scope()` को fragment मा branch id कहिल्यै inline हुँदैन, सधैं `?` placeholder
- अज्ञात role (`branchadmin`) को rank 0 — पुरानो `isBranchAdmin()` bug दोहोरिन नदिन

### 12.5 Test ले भेटेको तेस्रो बग

`e(false)` ले `'0'` फर्काउँथ्यो तर `e_attr_js(false)` ले `''` — दुई helper असहमत थिए। साथै native `<?php echo false; ?>` ले केही छाप्दैन। अब `e()` ले पनि `''` फर्काउँछ, अर्थात् **raw echo लाई `e()` ले बदल्दा output कहिल्यै फेरिँदैन**।

### 12.6 CI

`lint` job मा `php tests/run.php` step थपियो। अब CI मा तीन तह छन्: `php -l` (सबै फाइल) → unit test → schema job (MySQL मा schema load + column verification)।

### 12.7 अन्तिम अवस्था

```
LINT: 204 files, 0 parse errors
105 assertions, 105 passed, 0 failed
```

---

## §13 — Phase 7: payment endpoint exposure (CORS + missing auth)

§8 item 3 was recorded as "wildcard CORS". Looking at it closely, the
CORS header was the smaller half of the problem.

### What was wrong

`api/payment/esewa.php` and `api/payment/khalti.php` each opened with:

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

and had **no authentication of any kind** — no session check, no API
key, nothing. Any page on the internet could make a visitor's browser
call `action=initiate` or `action=verify`. Grepping the repo found that
**no page in the application calls these endpoints at all**, so the
wildcard was not serving a real caller; it was pure attack surface.

### Fixes

1. **New `includes/cors.php`.** Default is to send *no* CORS headers.
   Gateway callbacks are server-to-server (CORS does not apply) and our
   own pages are same-origin (CORS not needed), so nothing legitimate
   is lost. A genuinely separate front-end can be allowed through
   `CORS_ALLOWED_ORIGINS` in `.env` (comma-separated, exact origins).
   Only an exact match is echoed back, `Vary: Origin` is always sent so
   caches cannot cross-serve, and `*` is rejected even if configured.

2. **Authentication on the money-moving actions.** New
   `api_require_payment_caller()` in `includes/api_auth.php` accepts an
   admin session, a valid API key, or a logged-in customer, and
   otherwise returns 401. Applied to eSewa `initiate` and Khalti
   `initiate` / `verify`.

3. **Ownership scoping.** Customer sessions are separate from admin
   sessions (`$_SESSION['customer_id']`, not `user_id`), so the new
   `api_customer_id()` exposes them. After the invoice is loaded, a
   customer caller who does not own the invoice gets the same
   "Invoice not found" answer as for a nonexistent one — no enumeration.

4. `webhook` / `callback` stay reachable without a session on purpose:
   the gateway calls them machine-to-machine, and they are already
   validated by re-checking the payment against the gateway's own API
   (Phase 1).

5. `khalti.php` used `include_once '../../includes/payment_gateway.php'`,
   a working-directory-relative path that breaks under cron/CLI. Now
   `__DIR__`-relative, like everything else in the file.

### Verification

`tests/cors_test.php` adds 25 assertions: allowlist parsing, wildcard
rejection, and that matching is exact (a subdomain, a suffix, a
different scheme and a different port must all fail — the classic CORS
bypasses); plus the API-key rules (an empty or short `API_KEY`
authenticates nobody) and that an admin session is never mistaken for a
customer.

Full run: **206 files, 0 parse errors; 130 assertions, 130 passed.**

### Still open from §8

1. CSRF fields on the remaining forms · 2. truncated
`hotspot/admin/users.php` · 4. duplicate-page merge · 5. split
oversized files. There is also a wider cleanup worth doing: ~40 sites
still use working-directory-relative `include '../config.php';`.

---

## §14 — Phase 8: working-directory-relative includes

### The bug

433 include/require statements across 118 files named their target with
a bare relative path:

```php
include 'config.php';
include '../includes/auth.php';
```

PHP resolves those against `include_path`, then the calling script's
directory, then the **current working directory**. Under a normal web
request the CWD is the entry script's directory, so most of these
happened to work — but a cron job (`php /var/www/scripts/auto_invoice.php`
run from `/`), a CLI invocation, or an FPM pool with a different
`chdir` gets a fatal "Failed opening required file". The cron scripts
are exactly the ones that must not break silently at 2am.

All of them now use `__DIR__ . '/...'`, which is resolved from the
file's own location and cannot depend on how the process was started.

### 42 of them were already broken

Checking that each rewritten path actually points at a real file
surfaced a second, pre-existing bug: pages in subdirectories were
naming their includes **as seen from the repository root**.

| File | Wrote | Would have needed CWD |
|---|---|---|
| `billing/index.php` | `include 'config.php'` | repo root |
| `hotspot/admin/*.php` (8 files) | `include 'includes/auth.php'` | repo root |
| `hotspot/index.php` | `include 'hotspot/includes/auth.php'` | repo root |
| `hotspot/includes/voucher.php` | `include 'config.php'` | repo root |

A request to `/billing/index.php` gives a CWD of `billing/`, so
`config.php` resolved to `billing/config.php` and the page died. These
were corrected to the right depth (`__DIR__ . '/../config.php'`) rather
than being faithfully preserved.

One was broken in both directions: `api/snmp_monitor.php` included
`mikrotik_api.php`, but that file has only ever existed in `includes/`,
so no working directory could have satisfied it.

### Verification

- Every `__DIR__`-relative include was resolved against the filesystem;
  the only unresolved paths left are non-includes (a log file, a backup
  directory, a glob pattern) plus `config.php` and `vendor/autoload.php`,
  which exist at runtime but are not in the repository.
- The rewrite was driven by PHP's own lexer (`token_get_all`), not a
  regex, after a regex pass was caught mis-parsing `accept="image/*"`
  in `work_diary.php` as the start of a block comment and skipping the
  rest of that file.
- New CI step **"Block working-directory-relative includes"** fails the
  build on any new bare relative include; confirmed it passes on the
  current tree and catches a deliberately reintroduced one.
- Full run: **206 files, 0 parse errors; 130 assertions, 130 passed.**

---

## §15 — Phase 9: CSRF coverage

### Where it stood

Of the files that handle a POST or render a POST form, **43 were
missing one half or both**. Two distinct failure modes:

- **Unprotected** — 28 pages read `$_POST` with no token check at all,
  including `user_add.php`, `user_edit.php`, `branch_add.php`,
  `change_password.php`, `nas_edit.php`, `inventory.php` and the whole
  of `billing/`. A malicious page could make a logged-in admin's
  browser create users or change passwords.
- **Already broken** — 10 pages *did* call `csrf_check()` but their
  forms never emitted `csrf_field()`, so those pages were rejecting
  their own submissions with a 419 (`plans.php`, `nas.php`,
  `quick_renew.php`, `knowledge_base.php`, the `report/*` exports,
  `ticket_new.php`). Adding the field fixes a live bug, not just a
  theoretical one.

### Approach

`csrf_check()` returns immediately on anything that is not a POST, so
one call near the top of a page protects every POST that page handles.
That is what was added — 19 new guards and 34 new hidden fields across
29 pages — rather than one guard per handler branch.

**AJAX.** `includes/header.php` already published the token in a
`<meta name="csrf-token">`. A small shim there now attaches
`X-CSRF-Token` to every same-origin non-GET `fetch()` and
`XMLHttpRequest`, so the dozens of existing AJAX call sites are covered
without editing each one — and so the next one is covered by default.
Forms built in JavaScript are a real navigation and bypass the shim, so
`payment/khalti_pay.php` sets the field explicitly.

**Machine callers.** New `api_csrf_check()` skips the token when the
caller authenticated with an API key, because a cron job has no session
to ride and a foreign site cannot obtain the key. Applied to
`api/resolve_alert.php`, `api/olt_ont.php`, `api/snmp_monitor.php`,
`api/network_topology.php`, `api/mikrotik_olt_integration.php` and
`work_diary_api.php`. Two of those (`olt_ont`,
`mikrotik_olt_integration`) had **no authentication at all** and gained
`api_require_auth()` in the same pass.

**One deliberate exception.** `payment/esewa_pay.php` renders a form
that posts to eSewa. It gets no token - handing ours to a third party
would be worse than having none - and the comment in the file says so.
The POST arriving at that page is checked.

### A credential leak found on the way

`api/payment/get_gateway.php` - the modal fragment behind the "edit
gateway" button - was **unauthenticated** and rendered
`<input value="<?= $gateway['api_key'] ?>">`. Anyone who could reach
`?id=1` could read the live payment credentials, against an explicit
`secret - never render this` comment on that column in the schema.

It was also simply broken: it read `$gateway['name']`, `['status']`
and `['webhook_url']`, none of which exist on `payment_gateways`, and
posted field names `billing/gateways.php` does not handle, so saving
had never worked.

Rewritten to require `superadmin`, select only non-secret columns
(secrets are reduced to a `has_api_key` boolean for the placeholder),
post the field names the handler expects, and carry a token. The
handler now treats an empty key or secret as "unchanged" instead of
writing the blank through and silently breaking payments.

### Verification

New `scripts/check_csrf.php`, wired into CI as **"CSRF coverage"**,
fails the build on any POST form without a field or any `$_POST`
handler without a check, with a named exemption list. It reports clean,
alongside **207 files, 0 parse errors** and **133 assertions, 133
passed**.

### Noted, not yet done

Phase 8 converted literal relative includes to `__DIR__`. A further
**70 sites use `include $base_path . 'config.php'`**, which is the same
CWD dependency wearing a variable, and the new CI guard does not catch
it. Worth a follow-up.

---

## §16 — Phase 10: the rest of the working-directory dependency

Phase 8 left a note: 70 includes still went through `$base_path`.
Pulling that thread found the whole mechanism, and two more bugs.

### `$base_path` was doing two incompatible jobs

It is set per page (`''`, `'./'`, `'../'`, `'.'`) and used in 93 places
as a **URL** prefix — `<a href="<?= e($base_path) ?>dashboard.php">`,
stylesheet links, redirects. That use is fine and stays.

It was *also* used to build **filesystem** paths, which is a different
question with a different answer. 71 includes went through it, plus:

- `includes/sidebar.php` — `file_exists($base_path.'uploads/'.$logo)`,
  a disk check driven by a URL prefix.
- `cron_block_expired.php` — `file_put_contents('logs/block_expired.log', …)`.
  A cron script logging to a path relative to wherever cron started it,
  which under the usual `cd / && php /var/www/…` is `/logs/`. It has
  never written that log.

All filesystem uses are now `__DIR__`-relative. `$base_path` is a URL
prefix and nothing else.

### `chdir()` was the workaround, and it had its own victim

20 files opened with `chdir(__DIR__ . '/..')` — someone's fix for the
CWD-relative includes, moving the mountain to the path. With the
includes made absolute in Phases 8 and 10, every one of them was dead
weight, and they have been removed.

One of them was actively harmful. `hotspot/index.php` calls
`chdir(__DIR__ . '/..')`, putting the CWD at the repo root, and then
asks `file_exists('../uploads/' . $portalLogo)` — which resolves
*outside* the application directory. The captive portal logo could
never have displayed. Same class of bug in `index.php`,
`system_config.php` and `monitoring/viber_webhook.php`.

### Verification

The CI step from Phase 8 now also fails on `include $base_path . …`,
on `$base_path` appearing in any filesystem call, and on `chdir()`
anywhere in a PHP file. Checked against the current tree: clean.

**207 files, 0 parse errors · CSRF coverage clean · 133 assertions,
133 passed.**

Nothing in the application now depends on the working directory it is
started from, so the cron scripts behave the same from `/` as from the
document root.

---

## §17 — Phase 11: the truncated hotspot user page

`hotspot/admin/users.php` was listed in §8 as "truncated". It is worse
than it sounds.

### What was missing

The file stops mid-way through the first stat card:

```html
            <div style="opacity: 0.5;"><i class="fas fa-users fa-2x"></i></div>
    </div>
</div>
```

and then jumps straight to the script tags, `</body></html>`, and a
*second* copy of some of those script tags after the closing html.

Gone with it: three stat cards, the search and filter bar, the entire
user table, the bulk-action controls, and **all four modals**.

The JavaScript at the bottom survived intact, and it addresses
`#userModal`, `#topupModal`, `#rechargeModal`, `#deleteModal` and
`#bulkForm` — none of which existed in the document. Every one of those
`addEventListener` calls threw on page load.

Meanwhile the PHP at the top handles six POST actions — `save_user`,
`delete_user`, `toggle_status`, `topup`, `recharge`, `bulk_action` —
and **not one of them had a form that could submit it**. The page
rendered a nav bar, one broken card, and a console full of errors.
Hotspot user management did not exist.

### Rebuilt

The markup was reconstructed against two fixed points: the element ids
the surviving JavaScript expects, and the real `hotspot_users` columns
from the schema (verified: every column the new table reads exists).
Every form carries `csrf_field()`.

One structural note: the status toggle cannot be a form nested inside
the bulk-action form, so those are declared separately and the buttons
reference them with `form="toggleForm<id>"`.

### A second bug found in passing

`$message` was being set with `json_encode(['type' => …, 'msg' => …])`.
But `header_hotspot.php` — included by this page — renders `$message`
with `e($message)`, so the raw JSON string was printed into the alert
box. The page then decoded and rendered it a *second* time further
down. Both the encoding and the duplicate block are gone; `$message` is
a plain string, rendered once by the header, which is what every other
page in that directory does.

### Verification

**207 files, 0 parse errors · CSRF coverage clean · 133 assertions,
133 passed.** HTML tag balance checked: the page closes exactly the one
`<div>` that `header_hotspot.php` leaves open.

---

## §18 — Phase 12: duplicate pages

§8 item 4 listed four "duplicate page" pairs. They turned out to be
three different situations, and only one of them was a duplicate in the
copy-paste sense. That one was a security hole.

### `login.php` defeated the brute-force lockout

`index.php` is the admin login. It is careful: it checks
`isLockedOut()`, calls `recordLoginAttempt()` on both success and
failure, writes an activity log entry, regenerates the session id, and
tells the user how many attempts remain before a 15-minute lock.

`login.php` authenticated against **the same `admins` table** and did
none of that. No lockout check, no attempt recorded, no log.

So the lockout on `index.php` protected nothing. An attacker who
exhausted their five attempts just moved to `/login.php` and carried
on guessing, indefinitely and unlogged. The two files set identical
session keys and both ended at `dashboard.php`, so there was no
functional reason to prefer either — and **nothing in the codebase
linked to `login.php` at all**.

`customer/login.php` was the same shape of problem one level down.
`customer/index.php` always runs `password_verify()` against a dummy
hash when the username does not exist, so the response time is the
same either way; the duplicate returned early instead, making customer
accounts enumerable by timing.

Both duplicates are now 301 redirects to the surviving page, with a
comment explaining why, so old bookmarks still work. Every in-repo
link was repointed at the canonical page directly. After the change,
`index.php` is the only file in the application that verifies an admin
password.

### Two redirects pointed at a file that never existed

`hotspot/admin/users.php` and `hotspot/admin/roles.php` sent
unauthenticated visitors to `login.php` *relative to their own
directory* — `hotspot/admin/login.php`, which has never existed in this
repository. Both now point at the real admin login.

### Not duplicates: `report/` and `reports/`

Different pages that happen to have near-identical directory names:
`report/` holds the user reports and their CSV exports, `reports/`
holds `accounting.php` and `financial.php`. Both are linked from the
sidebar and both work. This is a naming annoyance, not a bug, and
renaming it buys nothing but risk. Left alone deliberately.

### Not a duplicate either - and this one needs a decision

`invoices.php` and `billing/invoices.php` are not two views of one
thing. They are **two parallel invoicing systems on two different
tables**, and they do not talk to each other:

| | `invoices` | `billing_invoices` |
|---|---|---|
| keyed by | `username` | `customer_id` |
| written by | `scripts/auto_invoice.php` (the monthly cron), `recharge.php`, `quick_renew.php` | `billing/invoices.php` |
| read by | `customer/invoices.php`, `dashboard.php`, `reports/accounting.php`, `report_api.php` | `billing/index.php` |
| marked paid by | — | `api/payment/esewa.php`, `api/payment/khalti.php` |

Read the last two rows together. **The eSewa and Khalti endpoints
settle `billing_invoices`. The invoice the customer is actually sent —
generated by the billing cron into `invoices`, and the one
`customer/invoices.php` displays — is never marked paid by an online
payment.** Nor does either gateway touch the customer's expiry date.

This is not something to fix by guessing. Either the gateways should
be pointed at `invoices`, or `invoices` should be migrated into
`billing_invoices` and the cron rewritten — and which one is right
depends on which system the business actually runs on, and on live
data. Flagged here for the owner to decide; deliberately not changed.

### Verification

**207 files, 0 parse errors · CSRF coverage clean · 133 assertions,
133 passed.**

---

## §19 — Phase 13: splitting the oversized pages

§8 item 5 asked for `network_topology.php` and `mobile_tech.php` to be
split. Measuring them first showed what they actually were:

| file | before | PHP | CSS | JS | HTML |
|---|---|---|---|---|---|
| `network_topology.php` | 1754 | 178 | 791 | 467 | 319 |
| `mobile_tech.php` | 990 | 15 | 85 | 740 | 151 |

Almost none of either file was PHP. **No `<style>` or `<script>` block
in either file contained a single PHP tag**, so all of it could move
out verbatim — no data-passing, no templating. The JavaScript already
gets everything it needs from `fetch()` calls to the API endpoints.

| file | after |
|---|---|
| `network_topology.php` | 1754 → **498** |
| `mobile_tech.php` | 990 → **167** |

New: `assets/css/network-topology.css`, `assets/js/network-topology.js`,
`assets/css/mobile-tech.css`, `assets/js/mobile-tech.js`. The browser
can now cache them, and getting the inline blocks out is a
prerequisite for a Content-Security-Policy that does not need
`unsafe-inline`.

### Extraction immediately exposed two dead scripts

Once the JavaScript was in a `.js` file, `node --check` could read it —
and it failed on both counts.

**1. `network_topology.php`'s entire script block never ran.** Line
241 was:

```js
document.querySelector('.device-node[data-id="' + id + '"]')?.style.boxShadow = '0 0 20px #10b981';
```

Optional chaining is not allowed on the left-hand side of an
assignment. That is a `SyntaxError`, and a syntax error anywhere in a
script block prevents **the whole block** from executing. All 465
lines of the network topology page's behaviour — the device clicks,
the cable drawing, the live refresh — have never worked. Rewritten as
an explicit null check.

**2. `assets/js/disconnect.js` was not JavaScript.** It contained raw
`<script>` tags, including one loading jQuery. A `.js` file is parsed
as JavaScript, so the browser threw `Unexpected token '<'` on the
first character. Nothing currently references the file; it has been
rewritten as valid JavaScript with its jQuery dependency documented,
rather than deleted, so that wiring it up would now work.

### Verification

New CI step **"JavaScript syntax"** runs `node --check` over every file
in `assets/js/`. It is the check that found both bugs above, and all
five files now pass it.

**207 files, 0 parse errors · CSRF coverage clean · 133 assertions,
133 passed · 5/5 JavaScript files parse.**

This closes the last open item from §8.
