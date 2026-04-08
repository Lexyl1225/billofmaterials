# BOM/BOQ Web Application - Deployment Guide

## 🎯 Overview
Complete guide for deploying the BOM/BOQ Document Management System with full MySQL database integration, role-based access control, and department-based file sharing.

## ✨ Features

### Core Functionality
- **BOM (Bill of Materials)** - Create and manage material lists
- **BOQ (Bill of Quantities)** - Create and manage quantity documents
- **Price Editing** - Purchasing department can edit unit costs
- **Document Posting** - Post documents to specific departments

### Access Control & Sharing
- **Role-Based Access:**
  - `Admin` - Full access to all documents and settings
  - `User` - Standard access based on department
  - `Guest` - Read-only access (cannot post files)
  
- **Department-Based Sharing:**
  - Users see documents from their own department
  - Purchasing department can see all posted documents for price editing
  - Documents can be posted to specific target departments

### Departments Supported
- Purchasing
- Design and Construction Department
- Operations
- Electrical
- Technical
- Admin

## 📋 Prerequisites

1. **Server Requirements:**
   - PHP 7.4 or higher (8.1 recommended)
   - MySQL 5.7 or higher (8.0 recommended)
   - Nginx or Apache web server
   - PHP-FPM (for Nginx)

2. **PHP Extensions Required:**
   ```bash
   sudo apt install php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-json php8.1-cli
   ```

## 🗄️ Database Schema

The application uses 3 main tables:

| Table | Purpose |
|-------|---------|
| `users` | User accounts with roles and departments |
| `documents` | BOM/BOQ documents with status tracking |
| `activity_logs` | User activity audit trail |

### Document Status Flow
```
saved → pending_price_edit → posted → (can be unposted)
                ↓
           completed
```

## 🚀 Local Development Setup

### Step 1: Setup Database
```bash
# Start MySQL
mysql -u root -p

# Create database and tables
mysql -u root -p < db-schema.sql
```

### Step 2: Configure Database Connection
Edit `db-config.php`:
```php
define('DB_NAME', 'bom_db');
define('DB_USER', 'root');
define('DB_PASSWORD', 'your_password_here');
define('DB_HOST', 'localhost');
define('SALT', 'CHANGE_THIS_TO_RANDOM_STRING'); // Generate unique salt!
```

### Step 3: Run Migration (If upgrading from JSON)
1. Start PHP server: `php -S localhost:8000`
2. Open browser: `http://localhost:8000/migrate_to_db.php`
3. Follow migration instructions
4. **Delete migrate_to_db.php after completion**

### Step 4: Test Application
- URL: `http://localhost:8000/login.php`
- Create a new user or use existing accounts
- Test document creation, posting, and department sharing

## 🌐 Production Deployment

### Step 1: Upload Files to Server
```bash
# Upload to server
scp -r /path/to/bom2/* user@server:/var/www/electrical/wordpress/bom/

# Or use rsync for updates
rsync -avz --exclude='*.json' --exclude='db-config.php' /path/to/bom2/ user@server:/var/www/electrical/wordpress/bom/
```

### Step 2: Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/electrical/wordpress/bom
sudo find /var/www/electrical/wordpress/bom -type d -exec chmod 755 {} \;
sudo find /var/www/electrical/wordpress/bom -type f -exec chmod 644 {} \;
```

### Step 3: Configure Nginx

Create `/etc/nginx/sites-available/bom`:
```nginx
server {
    listen 80;
    server_name serverx.ratfish-regulus.ts.net;
    
    root /var/www/electrical/wordpress/bom;
    index index.php login.php;
    
    access_log /var/log/nginx/bom_access.log;
    error_log /var/log/nginx/bom_error.log;
    
    # BOM application
    location /bom/ {
        alias /var/www/electrical/wordpress/bom/;
        try_files $uri $uri/ /bom/login.php?$args;
        
        location = /bom/ {
            rewrite ^/bom/$ /bom/login.php last;
        }
        
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $request_filename;
            include fastcgi_params;
        }
    }
    
    # Security: Deny access to sensitive files
    location ~ /bom/(users\.json|user_activity_logs\.json|.*\.sql|migrate.*\.php|db-config\.php)$ {
        deny all;
        return 404;
    }
    
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;
    client_max_body_size 10M;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/bom /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 4: Setup Production Database
```bash
# Create database
mysql -u root -p

# Run schema
mysql -u root -p bom_db < /var/www/electrical/wordpress/bom/db-schema.sql
```

### Step 5: Update Production Config
Edit `/var/www/electrical/wordpress/bom/db-config.php`:
```php
define('DB_PASSWORD', 'strong_production_password');
define('SALT', 'generated_random_salt_here');
```

### Step 6: Run Production Migration (If needed)
1. Visit: `https://serverx.ratfish-regulus.ts.net/bom/migrate_to_db.php`
2. Complete migration
3. **Important:** Delete migration files
   ```bash
   sudo rm /var/www/electrical/wordpress/bom/migrate_to_db.php
   sudo rm /var/www/electrical/wordpress/bom/migrate_documents_phase2.php
   sudo rm /var/www/electrical/wordpress/bom/migrate_documents_phase2_web.php
   ```

## 🔒 Security Checklist

- [ ] Generate strong unique SALT value
- [ ] Set strong database password
- [ ] Delete all migration files after use
- [ ] Verify sensitive files are blocked in nginx
- [ ] Set proper file permissions (644 for files, 755 for directories)
- [ ] Enable HTTPS (strongly recommended)
- [ ] Setup regular database backups
- [ ] Review user accounts and remove unused ones

## 📊 What's Stored in Database

| Data | Storage | Notes |
|------|---------|-------|
| User accounts | `users` table | Passwords hashed with bcrypt |
| BOM/BOQ documents | `documents` table | Full document data with HTML content |
| Activity logs | `activity_logs` table | Complete audit trail |
| Document status | `documents.status` | saved, pending_price_edit, posted, unposted |
| Department assignments | `documents.department` | Target department for posted docs |

## 📁 File Structure

```
bom2/
├── login.php              # User authentication
├── index.php              # Main landing page
├── bom.php                # BOM document creation
├── bom_labor.php          # BOM with labor costs
├── save_bomboq.php        # Document management dashboard
├── price_edit_bomboq.php  # Price editing (Purchasing dept)
├── document_api.php       # REST API for document operations
├── document_manager.php   # Database operations for documents
├── db-config.php          # Database configuration
├── db-schema.sql          # Database schema
├── user_logs.php          # Activity log viewer
├── activity_logger_db.php # Activity logging functions
└── DEPLOYMENT_GUIDE.md    # This file
```

## 🔄 Application Workflow

### Creating a Document
1. User creates BOM/BOQ in `bom.php` or `bom_labor.php`
2. Document saved to database via `document_api.php`
3. Appears in `save_bomboq.php` under "Saved Documents"

### Posting to Purchasing
1. User clicks "Post" on saved document
2. Status changes to `pending_price_edit`
3. Document appears in Purchasing department's `price_edit_bomboq.php`

### Price Editing & Posting to Department
1. Purchasing edits unit costs
2. Selects target department and clicks "Post to Department"
3. Status changes to `posted`
4. Target department users can now see the document

### Unposting (Admin/Purchasing only)
1. Can unpost documents back to pending status
2. Document returns to Purchasing for re-editing

## 🆘 Troubleshooting

### Database Connection Error
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u root -p bom_db -e "SELECT 1"

# Check db-config.php credentials
```

### PHP Errors
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm

# View error logs
sudo tail -f /var/log/nginx/bom_error.log
sudo tail -f /var/log/php8.1-fpm.log
```

### Permission Denied
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/electrical/wordpress/bom

# Fix permissions
sudo chmod -R 755 /var/www/electrical/wordpress/bom
```

### Document Not Found Errors
1. Check if document exists in database:
   ```sql
   SELECT name, status, department FROM documents WHERE name = 'document_name';
   ```
2. Verify user has permission (same department or admin)
3. Check browser console for API errors

### Session Issues
```bash
# Check PHP session directory permissions
sudo ls -la /var/lib/php/sessions/

# Fix if needed
sudo chown -R www-data:www-data /var/lib/php/sessions/
```

## 📞 Support & Logs

Check these logs for debugging:
- **Nginx access:** `/var/log/nginx/bom_access.log`
- **Nginx errors:** `/var/log/nginx/bom_error.log`
- **PHP errors:** `/var/log/php8.1-fpm.log`
- **MySQL errors:** `/var/log/mysql/error.log`
- **Application logs:** `user_logs.php` (in browser)

## 🎉 Success Verification

After deployment, verify:
- [ ] Login page loads: `/bom/login.php`
- [ ] User registration works
- [ ] BOM/BOQ document creation works
- [ ] Documents save to database
- [ ] Posting workflow functions correctly
- [ ] Department-based access works
- [ ] Activity logs record all actions
- [ ] Price editing works for Purchasing users
- [ ] Documents post to correct departments

---

**Version:** 2.0  
**Last Updated:** February 2026  
**Status:** Full database integration complete
