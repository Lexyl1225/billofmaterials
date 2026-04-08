# BOM Integration with Existing Nginx Configuration

## Current Setup
- Main site: https://serverx.ratfish-regulus.ts.net/ (WordPress)
- Site2: https://serverx.ratfish-regulus.ts.net/site2/
- **NEW BOM:** https://serverx.ratfish-regulus.ts.net/bom/

## Quick Integration Steps

### Step 1: Upload BOM Files to Server

```bash
# On your local machine
scp -r d:\2026\ Project\billofmaterials-main/* user@serverx:/tmp/bom-upload/

# On your server via SSH
sudo mkdir -p /var/www/electrical/wordpress/bom
sudo cp -r /tmp/bom-upload/* /var/www/electrical/wordpress/bom/
sudo chown -R www-data:www-data /var/www/electrical/wordpress/bom
sudo chmod -R 755 /var/www/electrical/wordpress/bom
```

### Step 2: Find Your Existing Nginx Config

```bash
# Find your main site config
ls -la /etc/nginx/sites-available/
# Look for files like: default, serverx, wordpress, main-site, etc.

# View your current config
sudo nano /etc/nginx/sites-available/YOUR_CONFIG_FILE
```

### Step 3: Add BOM Location Block

Open your existing nginx config and **ADD** this location block alongside your existing site2 location:

```nginx
# Add this INSIDE your existing server { } block for port 443

# BOM application location
location /bom/ {
    alias /var/www/electrical/wordpress/bom/;
    index login.php index.php index.html;
    try_files $uri $uri/ /bom/login.php?$args;
    
    location ~ ^/bom/.*\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    # Security: Block sensitive files
    location ~ ^/bom/(users\.json|user_activity_logs\.json|db-config\.php|activity_logger.*\.php|migrate.*\.php|.*\.sql|test_db\.php)$ {
        deny all;
        return 404;
    }
}
```

### Step 4: Test and Reload Nginx

```bash
# Test configuration
sudo nginx -t

# If test passes, reload nginx
sudo systemctl reload nginx
```

### Step 5: Setup Database

```bash
# Create database
mysql -u root -p

# In MySQL prompt:
CREATE DATABASE IF NOT EXISTS bom_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;

# Import schema
mysql -u root -p bom_db < /var/www/electrical/wordpress/bom/db-schema.sql
```

### Step 6: Configure Database Connection

```bash
# Edit db-config.php
sudo nano /var/www/electrical/wordpress/bom/db-config.php
```

Update these values:
```php
define('DB_PASSWORD', 'your_mysql_password');
define('SALT', 'GENERATE_NEW_RANDOM_64_CHAR_STRING');
```

Generate new SALT:
```bash
openssl rand -base64 64
```

### Step 7: Run Migration

Visit: `https://serverx.ratfish-regulus.ts.net/bom/migrate_to_db.php`

After migration completes:
```bash
# Delete migration files for security
sudo rm /var/www/electrical/wordpress/bom/migrate_to_db.php
sudo rm /var/www/electrical/wordpress/bom/test_db.php
```

### Step 8: Test BOM Application

Visit: `https://serverx.ratfish-regulus.ts.net/bom/`

Default login:
- Username: `Admin`
- Password: `T0ms1234`

## Example Nginx Config Structure

Your complete server block should look like this:

```nginx
server {
    listen 443 ssl http2;
    server_name serverx.ratfish-regulus.ts.net;
    
    # SSL certificates (existing)
    ssl_certificate /etc/letsencrypt/live/serverx.ratfish-regulus.ts.net/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/serverx.ratfish-regulus.ts.net/privkey.pem;
    
    # WordPress root (existing)
    root /var/www/electrical/wordpress;
    index index.php index.html;
    
    # WordPress main site (existing)
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    # PHP for WordPress (existing)
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        include fastcgi_params;
    }
    
    # Site2 (existing)
    location /site2/ {
        # ... your existing site2 config ...
    }
    
    # BOM Application (NEW - add this)
    location /bom/ {
        alias /var/www/electrical/wordpress/bom/;
        index login.php index.php index.html;
        try_files $uri $uri/ /bom/login.php?$args;
        
        location ~ ^/bom/.*\.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $request_filename;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }
        
        location ~ ^/bom/(users\.json|user_activity_logs\.json|db-config\.php|activity_logger.*\.php|migrate.*\.php|.*\.sql|test_db\.php)$ {
            deny all;
            return 404;
        }
    }
}
```

## Verification Checklist

- [ ] Files uploaded to /var/www/electrical/wordpress/bom/
- [ ] Permissions set (www-data:www-data, 755)
- [ ] Location block added to nginx config
- [ ] Nginx config tested: `sudo nginx -t`
- [ ] Nginx reloaded: `sudo systemctl reload nginx`
- [ ] Database created and schema imported
- [ ] db-config.php updated with credentials
- [ ] Migration run successfully
- [ ] Migration files deleted
- [ ] Can access https://serverx.ratfish-regulus.ts.net/bom/
- [ ] Can login successfully
- [ ] WordPress and site2 still working

## Troubleshooting

**404 Not Found:**
```bash
# Check file exists
ls -la /var/www/electrical/wordpress/bom/login.php

# Check nginx error log
sudo tail -f /var/log/nginx/error.log
```

**PHP not executing:**
```bash
# Check PHP-FPM is running
sudo systemctl status php8.1-fpm

# Check socket exists
ls -la /var/run/php/php8.1-fpm.sock
```

**Database connection error:**
```bash
# Test database connection
mysql -u root -p bom_db

# Check credentials in db-config.php
sudo cat /var/www/electrical/wordpress/bom/db-config.php
```

## Rollback (if needed)

```bash
# Remove location block from nginx config
sudo nano /etc/nginx/sites-available/YOUR_CONFIG_FILE
# Delete the /bom/ location block

# Reload nginx
sudo nginx -t && sudo systemctl reload nginx

# Remove BOM files (optional)
sudo rm -rf /var/www/electrical/wordpress/bom/
```

---

**Your existing WordPress and site2 will NOT be affected** - we're only adding a new location block, not modifying any existing configurations.
