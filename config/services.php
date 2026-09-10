<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'line' => [
        'login_channel_id' => env('LINE_LOGIN_CHANNEL_ID'),
        'login_secret' => env('LINE_LOGIN_SECRET'),
        'messaging_token' => env('LINE_BOT_TOKEN'),
        'messaging_secret' => env('LINE_BOT_CHANNEL_SECRET'),
        'official_account_id' => env('LINE_OFFICIAL_ACCOUNT_ID'),
        'redirect_uri' => env('LINE_LOGIN_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/line/callback'),
    ],

    'docling' => [
        // ระบุพาธเต็มได้ใน .env หาก PHP/Apache หา python จาก PATH ไม่เจอ
        // ถ้าไม่ได้แยกค่าเฉพาะ Docling ให้ใช้ Python ตัวเดียวกับ stamper.py
        'python' => env('PYTHON_EXECUTABLE') ?: env('PYTHON_COMMAND', 'python'),
    ],

    'typhoon' => [
        'api_key' => env('TYPHOON_API_KEY'),
        'model' => env('TYPHOON_MODEL', 'typhoon-v2.5-30b-a3b-instruct'),
        'endpoint' => env('TYPHOON_ENDPOINT', 'https://api.opentyphoon.ai/v1/chat/completions'),
        'timeout' => env('TYPHOON_TIMEOUT', 60),
    ],

    'document_extraction' => [
        'python' => env('PYTHON_EXECUTABLE') ?: env('PYTHON_COMMAND', 'python'),
        'script' => env('DOCUMENT_EXTRACTION_SCRIPT', 'extract_doc.py'),
        'timeout' => env('DOCUMENT_EXTRACTION_TIMEOUT', 600),
    ],

    'leave_delegate' => [
        'remind_after_hours' => env('LEAVE_DELEGATE_REMIND_AFTER_HOURS', 24),
        'escalate_after_hours' => env('LEAVE_DELEGATE_ESCALATE_AFTER_HOURS', 48),
    ],

    'google_calendar' => [
        'api_key' => env('GOOGLE_API_KEY'),
    ],

];
