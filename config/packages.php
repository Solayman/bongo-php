<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Official Bongo Packages
    |--------------------------------------------------------------------------
    |
    | Enable optional packages by uncommenting their name or specifying a
    | version constraint. Run `php bongo package:install` or `php bongo add <name>`
    | to install them.
    |
    | Examples:
    |   'sslcommerz',
    |   'bkash' => '^1.0',
    |
    */

    // 'sslcommerz',     // SSLCommerz payment gateway
    // 'bkash',          // bKash payment gateway
    // 'nagad',          // Direct Nagad MFS payment gateway
    // 'sms',            // Bangladeshi SMS gateways + OTP
    // 's3',             // S3 / Spaces / R2 storage disk
    // 'social-login',   // Google / Facebook / GitHub login
    // 'stripe',         // Stripe international payments (cards, Apple Pay, webhooks)
    // 'two-factor',     // RFC 6238 TOTP two-factor auth + pure-PHP SVG QR codes
    // 'image',          // Pure-PHP image manipulation, cropping, filters, and WebP
    // 'backup',         // Zero-dependency database and asset backup engine
    // 'permission',     // Role-Based Access Control (RBAC) and permissions management
    // 'zip',            // Pure-PHP ZIP compression, extraction, and streaming download
    // 'ftp',            // Zero-dependency FTP and FTPS driver for Storage facade
    // 'paypal',         // PayPal REST API v2 Checkout, orders, refunds, and webhooks
    // 'api-tools',      // API Resources (JSON transformers), OpenAPI 3.0 docs, rate limiting
    // 'settings',       // Dynamic application & store settings in database with type casting & caching
    // 'seo',             // Search Engine Optimization (SEO), OpenGraph, Twitter Cards, Schema JSON-LD, XML Sitemap
    // 'activity-log',    // Enterprise audit trail & model activity logger with old/new attribute diffs
    // 'health',          // Application health checks & uptime monitoring for database, storage, cache, and redis
    // 'captcha',         // Anti-bot protection (Turnstile, reCAPTCHA v2/v3, hCaptcha, pure-PHP math & image)
    // 'aamarpay',        // AamarPay payment gateway for Bangladesh (bKash, Nagad, cards, TrxCheck)
    // 'telegram',        // Telegram Bot notifications, alerts, and channel messaging
    // 'bangla-tools',    // Bengali localization toolkit (numerals, words, Taka currency, Bongabdo calendar, slugs)
    // 'bd-locations',    // Bangladesh administrative divisions, 64 districts, upazilas, postcodes, shipping zones
    // 'webhooks',        // Outbound webhook dispatcher with HMAC-SHA256 signing and verification
    // 'impersonate',     // Admin user impersonation with secure session swapping
    // 'queue-monitor',   // Web dashboard for queue jobs, pending counts, and failed job retries
    // 'discord',         // Discord webhook alerts, notifications, and rich embed builder
    // 'toast',           // Zero-dependency flash and dynamic toast notification system with auto-dismiss and progress bars
    // 'sweet-alert',     // Zero-dependency modal alert and confirmation dialog engine with promise callbacks
    // 'zoom',            // Zoom Video Communications API client for meetings, registrants, OAuth and webhooks
    // 'google-meet',     // Google Meet and Calendar API client with Service Account RS256 JWT auth
    // 'courier',         // Bangladesh courier & logistics engine (Steadfast and Pathao Courier with tracking)
    // 'invoice',         // Professional invoice and POS thermal receipt generator with A4 print CSS
    // 'whatsapp',        // Meta WhatsApp Business Cloud API client for transactional messages, OTP & media
    'file-manager',    // Web file manager with tree navigation, image previews, and embedded code editor
];
