<?php
/**
 * HTTP Security Headers
 *
 * Sets security-related HTTP headers to protect against common web vulnerabilities.
 * Include this file early in your page/API to apply security headers.
 *
 * Usage: require __DIR__ . '/../private/inc/security_headers.php';
 */

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Prevent clickjacking attacks
header('X-Frame-Options: DENY');

// Enable XSS protection (legacy, but still useful for older browsers)
header('X-XSS-Protection: 1; mode=block');

// Control referrer information
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (CSP) - adjust as needed for your application
// This is a basic policy that allows inline styles/scripts and same-origin resources
// For production, consider a stricter policy
$csp = implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com",  // unsafe-inline needed for inline JS
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",   // unsafe-inline needed for inline CSS
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data: https:",
    "connect-src 'self'",
    "frame-ancestors 'none'",  // Equivalent to X-Frame-Options: DENY
    "base-uri 'self'",
    "form-action 'self'"
]);
header("Content-Security-Policy: $csp");

// Permissions Policy (formerly Feature Policy)
// Disable unnecessary browser features
$permissions = implode(', ', [
    'geolocation=()',
    'microphone=()',
    'camera=()',
    'payment=()',
    'usb=()',
    'magnetometer=()',
    'gyroscope=()',
    'accelerometer=()'
]);
header("Permissions-Policy: $permissions");

// Strict Transport Security (HSTS) - only enable if using HTTPS
// Uncomment if your site is fully HTTPS
// if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
//     header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
// }
