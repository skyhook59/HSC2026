# HSC Deployment Guide

This guide explains how to deploy the HSC application to different environments, including subdirectory deployments.

## Quick Start

### Production Deployment (Root Directory)

For production deployment at `hsc.mcph.ee/`:

1. Upload files to server:
   ```
   /home/public/    (contains files from /HSC/public/)
   /home/private/   (contains files from /HSC/private/)
   ```

2. Set environment variable:
   ```bash
   export APP_BASE_PATH=""
   ```

3. Configure web server to point document root to `/home/public/`

### Test Deployment (Subdirectory)

For test deployment at `hsc.mcph.ee/HSC-test/`:

1. Create subdirectory and upload files:
   ```
   /home/public/HSC-test/public/
   /home/public/HSC-test/private/
   ```

2. Set environment variable:
   ```bash
   export APP_BASE_PATH="/HSC-test"
   ```

3. Web server document root remains at `/home/public/`, subdirectory is accessible via URL path

## Environment Configuration

The application uses the `APP_BASE_PATH` environment variable to dynamically generate URLs:

| Environment | APP_BASE_PATH | Access URL |
|-------------|---------------|------------|
| Production  | `` (empty)    | `https://hsc.mcph.ee/` |
| Testing     | `/HSC-test`   | `https://hsc.mcph.ee/HSC-test/` |
| Development | `/dev`        | `https://hsc.mcph.ee/dev/` |

### Setting Environment Variables

**Option 1: Apache (.htaccess or VirtualHost)**
```apache
SetEnv APP_BASE_PATH "/HSC-test"
```

**Option 2: PHP (in .env file or directly)**
```php
putenv('APP_BASE_PATH=/HSC-test');
```

**Option 3: Server-level (recommended)**
Add to your shell profile or systemd service:
```bash
export APP_BASE_PATH="/HSC-test"
```

## Directory Structure

Your server structure should look like this:

### Production
```
/home/
  └── public/              (web root)
      ├── index.php
      ├── menu.php
      ├── api/
      ├── assets/
      └── ...
  └── private/
      ├── inc/
      ├── cli/
      ├── storage/
      └── vendor/
```

### Test Environment
```
/home/
  └── public/              (web root)
      ├── HSC-test/
      │   ├── public/
      │   │   ├── index.php
      │   │   ├── menu.php
      │   │   ├── api/
      │   │   ├── assets/
      │   │   └── ...
      │   └── private/
      │       ├── inc/
      │       ├── cli/
      │       ├── storage/
      │       └── vendor/
      └── (production files)
```

## Web Server Configuration

### Apache

For subdirectory deployment, ensure your Apache configuration allows:

```apache
<Directory /home/public/HSC-test/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### Nginx

For subdirectory deployment:

```nginx
location /HSC-test/ {
    alias /home/public/HSC-test/public/;
    try_files $uri $uri/ /HSC-test/index.php?$query_string;

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_param APP_BASE_PATH "/HSC-test";
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }
}
```

## How It Works

The application uses two helper functions defined in `/private/inc/db.php`:

### `url($path)`
Generates URLs with the base path:
```php
// In production (APP_BASE_PATH="")
url('menu.php')  // returns "/menu.php"

// In test (APP_BASE_PATH="/HSC-test")
url('menu.php')  // returns "/HSC-test/menu.php"
```

### `redirect($path)`
Redirects to a URL with the base path:
```php
// In production
redirect('index.php')  // redirects to "/index.php"

// In test
redirect('index.php')  // redirects to "/HSC-test/index.php"
```

## Migrating Between Environments

To move from test to production (or vice versa):

1. **Change environment variable only** - no code changes needed
2. Test by accessing the new URL
3. Update DNS/web server configuration if changing domains

Example migration from test to production:
```bash
# Change environment variable
export APP_BASE_PATH=""

# Optionally move files
mv /home/public/HSC-test/public/* /home/public/
mv /home/public/HSC-test/private/* /home/private/
```

## Troubleshooting

### URLs not working in subdirectory
- Verify `APP_BASE_PATH` is set correctly
- Check that your web server is passing the environment variable to PHP
- Confirm no hardcoded URLs remain in custom code

### Assets (CSS/JS) not loading
- Check that `url()` helper is used for all asset paths
- Verify file permissions on assets directory
- Check browser console for 404 errors

### Redirects going to wrong location
- Ensure `redirect()` function is used instead of raw `header()` calls
- Check auth_guard.php is using the redirect helper

## Security Considerations

When deploying to a subdirectory:
- The `private/` directory should still NOT be web-accessible
- Ensure proper file permissions (644 for files, 755 for directories)
- Never commit `.env` files to version control
- Use HTTPS for all deployments

## Additional Notes

- File system paths (using `__DIR__`) work automatically regardless of deployment location
- Only HTTP URLs need the base path - file system operations are unaffected
- Session cookies work across the domain by default
- Database connections are unaffected by base path configuration
