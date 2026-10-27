# Hisabio — Smart Digital Khata & Business Ledger System

> **"Har Hisab Saaf, Har Khata Aasaan"**  
> *English: Every account clear, every transaction simple.*

A fast, secure, mobile-first digital khata and udhaar management web application designed specifically for Pakistani shopkeepers, retailers, wholesalers, and small businesses.

---

## 🌟 Key Features

1. **Strict No-Modal UI Policy**
   - No bootstrap modals, popup dialogs, or blocking overlays anywhere in the application.
   - Dedicated, fast pages for all critical actions: Add Customer, Edit Customer, Give Credit, Receive Payment, Add Supplier, Record Purchase, Pay Supplier, and Transaction Details.
   - Inline expandable confirmation panels for archiving and transaction reversals.

2. **Customer Khata (Udhaar Management)**
   - Chronological customer ledger with exact running balances.
   - **Give Credit (Udhaar Diya):** Fast entry with live projected balance preview.
   - **Receive Payment (Raqam Wasool):** Counter collections with multiple payment methods (Cash, Easypaisa, JazzCash, Bank Transfer).
   - **Partial Payments:** Ledger-calculated running balance preserving original credit transactions.

3. **Supplier Khata (Payables Management)**
   - Maintain supplier accounts for distributors, wholesalers, and vendors.
   - Record credit purchases (+Payable) and payments to suppliers (-Payable).
   - Dedicated supplier ledger statements with printable vouchers.

4. **1-Tap WhatsApp Payment Reminders**
   - Click-to-chat WhatsApp link generation with Pakistani mobile normalization (`03xx` to `923xx`).
   - Pre-formatted polite templates in both English and Urdu.
   - Complete reminder audit log with snapshot balances.

5. **Financial Integrity & Double-Entry Compatibility**
   - Immutable posted transactions—never silently overwritten or deleted.
   - Audited reversals requiring a mandatory correction reason.
   - `DECIMAL(18,2)` MySQL columns for strict decimal arithmetic.
   - Complete audit trail in `transaction_audit_logs`.

6. **Bilingual (English & اردو Urdu)**
   - Native Right-to-Left (RTL) layout when Urdu is selected.
   - Urdu typography stack (`Jameel Noori Nastaleeq`, `Noto Nastaliq Urdu`).
   - Reusable dictionary translation system (`includes/lang.php`).

7. **Printable Statements & Digital Receipts**
   - Formal store headers with logo, report title, date range, and generation timestamps.
   - Print stylesheet (`print.css`) hiding navigation chrome for clean paper printing.
   - One-click CSV exports for accounting and recordkeeping.

8. **PWA & Offline Readiness**
   - Web App Manifest (`manifest.json`) and Service Worker (`service-worker.js`).
   - Network status banner alerting users when offline.
   - Strict financial safety: static UI assets cached; financial API writes require network connectivity.

---

## 🎨 Branding & Deliverables

### Color Palette
- **Primary:** Deep Emerald Green `#087F5B`
- **Primary Dark:** `#065F46`
- **Accent:** Fresh Mint `#D1FAE5`
- **Background:** `#F7F9FC`
- **Surface:** `#FFFFFF`
- **Main Text:** `#172B24`
- **Credit / Receivable:** `#D97706`
- **Payment / Received:** `#15803D`
- **Error / Reversal:** `#DC2626`

### Brand Assets Included
- `assets/images/logo.svg`
- `assets/images/logo.png`
- `assets/images/logo-dark.svg`
- `assets/images/logo-light.svg`
- `assets/images/app-icon.svg`
- `favicon.ico`
- `favicon.svg`
- `assets/images/favicon-16x16.png`
- `assets/images/favicon-32x32.png`
- `assets/images/apple-touch-icon.png`
- `assets/images/pwa-icon-192.png`
- `assets/images/pwa-icon-512.png`

### Logo & Favicon Prompts (PRD Section 2.4)
> **Logo Prompt:**  
> "Design a premium, minimalist fintech-inspired logo for Hisabio, a digital khata and business ledger management application for Pakistani shopkeepers and small businesses. Create an original geometric symbol combining a ledger book, a subtle checkmark, and a sense of financial clarity. Use deep emerald green, white, and a restrained mint accent. The logo must be clean, trustworthy, recognizable at small sizes, and suitable for a mobile app icon, website header, invoice, receipt, and favicon. Provide a complete icon-only version, horizontal wordmark, light-background version, dark-background version, and monochrome version. Avoid unnecessary gradients, tiny details, currency symbols, mockup backgrounds, and decorative text. Deliver clean vector SVG files and transparent PNG exports."

> **Favicon Prompt:**  
> "Create a simplified favicon for Hisabio using only its distinctive ledger-checkmark symbol. Use a centered, bold geometric silhouette with deep emerald green and white. Ensure excellent visibility at 16×16, 32×32, and 48×48 pixels. Avoid text, thin lines, tiny details, and complex gradients. The icon must remain recognizable on browser tabs and mobile home screens."

---

## 🛠️ Technology Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript, AJAX (Fetch API)
- **Backend:** Procedural Core PHP 8.x
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB, UTF-8 mb4, PDO with prepared statements)
- **Security:** CSRF tokens on all state-changing forms, BCRYPT password hashing, session regeneration, SQL injection prevention, rate limiting

---

## 🚀 Quick Setup & Credentials

1. **Database Setup:**
   Import `database/schema.sql` into MySQL.
   ```sql
   mysql -u root -p < database/schema.sql
   ```
   *(The schema creates the `hisabio` database, all 14 tables, foreign keys, indexes, and initial seed data.)*

2. **Accessing the Application:**
   Open your browser and navigate to:
   ```
   http://localhost/Hisabio/
   ```

3. **Default Demo Credentials:**
   - **Mobile:** `03001234567`
   - **Password:** `admin123`
   - **Business:** Al-Madina General Store

---

## 📁 Project Structure

```
hisabio/
├── index.php                 # Landing page & gateway
├── login.php                 # Rate-limited user login
├── register.php              # Business & user signup
├── forgot-password.php       # Expiring token recovery
├── logout.php                # Secure logout
├── dashboard.php             # KPIs, quick actions, balances & recent activity
├── customers.php             # Customer directory, search, sort, filter
├── customer-add.php          # Dedicated customer creation
├── customer-edit.php         # Customer update form
├── customer-profile.php      # Customer overview & inline archive
├── customer-ledger.php       # Chronological ledger with mobile stacked cards
├── customer-statement.php    # Printable statement & CSV export
├── give-credit.php           # Fast Udhaar entry with live calculator
├── receive-payment.php       # Fast payment entry with digital receipt
├── suppliers.php             # Supplier directory & payables
├── supplier-add.php          # Dedicated supplier creation
├── supplier-edit.php         # Supplier update form
├── supplier-profile.php      # Supplier overview & actions
├── supplier-ledger.php       # Supplier payable ledger
├── supplier-purchase.php     # Credit purchase entry
├── supplier-payment.php      # Supplier payment voucher entry
├── supplier-statement.php    # Printable supplier statement
├── transactions.php          # Unified transaction multi-filter history
├── transaction-details.php   # Detailed voucher & digital receipt
├── transaction-reverse.php   # Audited transaction reversal
├── receivables.php           # Outstanding receivables list
├── payables.php              # Outstanding payables list
├── overdue.php               # Accounts past promised due date
├── reports.php               # Performance, summaries & CSV export
├── reminders.php             # WhatsApp reminder dispatcher & history
├── settings.php              # Business profile, footer & logo upload
├── profile.php               # Personal user profile & password
├── appearance.php            # English / Urdu LTR-RTL toggle
├── security.php              # Security audit log & rate-limiting monitor
├── users.php                 # Staff & munshi accounts
├── roles.php                 # Roles & permissions management
├── backup.php                # One-click portable JSON/SQL backup
├── help.php                  # Shopkeeper guide & FAQs
├── 404.php                   # Clean Not Found view
├── 403.php                   # Access Denied view
├── 500.php                   # Server Error view
├── manifest.json             # PWA Web App Manifest
├── service-worker.js         # PWA Cache Handler
├── .htaccess                 # Apache server security headers
├── database/
│   └── schema.sql            # Executable MySQL schema & seed data
├── includes/
│   ├── config.php            # App constants & session configuration
│   ├── db.php                # PDO singleton connection
│   ├── auth.php              # Auth & session security helpers
│   ├── csrf.php              # CSRF protection
│   ├── lang.php              # Bilingual English & Urdu dictionaries
│   ├── functions.php         # Authoritative financial calculations
│   ├── header.php            # Shared header & mobile navigation
│   ├── sidebar.php           # Desktop sidebar
│   ├── bottom-nav.php        # Mobile bottom navigation & quick add button
│   └── footer.php            # Shared footer & toast notification handler
├── api/
│   ├── dashboard/metrics.php # Live KPI endpoint
│   ├── customers/search.php  # Customer search autocomplete
│   ├── suppliers/search.php  # Supplier search autocomplete
│   └── reminders/send.php    # Reminder dispatch logger
└── assets/
    ├── css/
    │   ├── style.css         # Strict no-modal design system
    │   ├── responsive.css    # Mobile-first layouts (320px to 1440px+)
    │   ├── animations.css    # Smooth page transitions & skeletons
    │   └── print.css         # Formal paper statement print formatting
    ├── js/
    │   ├── app.js            # Toasts, offline monitor, loading states, PWA
    │   ├── ajax.js           # AJAX fetch client with CSRF tokens
    │   └── ledger.js         # Live projected balance calculator
    └── images/               # SVGs, PNGs, touch icons, and favicons
```
