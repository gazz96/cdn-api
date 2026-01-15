1. Overview
1.1 Latar Belakang

Aplikasi utama membutuhkan service upload & distribusi file terpisah yang dapat diakses oleh beberapa aplikasi lain secara aman dan terkontrol.
Service ini akan berfungsi sebagai internal CDN berbasis CodeIgniter 3 dengan API only.

2. Tujuan

Menyediakan API upload & serve file

Menjadi centralized file storage

Mendukung API Key, rate limit, dan private file

Mudah diintegrasikan dengan aplikasi existing

3. Scope
In Scope

Upload file

Public & private file access

API Key authentication

Rate limiting

File expiration

Metadata file

Out of Scope (v1)

UI dashboard

Image processing

Cloud storage

Video streaming

4. Architecture Overview

Client App
  │
  │ HTTP + API Key
  ▼
CDN API (CodeIgniter 3)
  │
  ├── Local Storage (Filesystem)
  └── MySQL (Metadata)

5. Folder Structure (CodeIgniter 3)

application/
├── config/
│ ├── cdn.php
│ ├── routes.php
│ └── database.php
│
├── controllers/
│ └── api/
│ └── v1/
│ └── Files.php
│
├── core/
│ └── MY_Controller.php
│
├── helpers/
│ ├── api_key_helper.php
│ ├── rate_limit_helper.php
│ └── signed_url_helper.php
│
├── models/
│ ├── Api_key_model.php
│ ├── File_model.php
│ └── Rate_limit_model.php
│
├── logs/

storage/
├── public/
│ └── 2026/01/
└── private/
└── 2026/01/

6. API Flow & Middleware
6.1 Request Flow (Protected Endpoint)

Incoming Request
→ MY_Controller
→ Validate API Key
→ Check Expired / Active
→ Rate Limit Check
→ Controller Action
→ Response

6.2 Middleware Implementation Strategy

Semua controller API extends MY_Controller

Middleware dijalankan di __construct()

Flow Detail:

Ambil X-API-KEY dari header

Validasi ke database

Check status & expiry

Check rate limit

Reject jika gagal

6.3 API Key Validation Logic

API key tidak ditemukan → 401

API key tidak aktif → 403

API key expired → 403

6.4 Rate Limit Flow

Request masuk
→ Check limit (per API Key + endpoint)
→ Jika melebihi → 429
→ Jika aman → increment counter

7. API Specification
7.1 Upload File

Endpoint
POST /api/v1/files/upload

Header
X-API-KEY: your_api_key

Body (multipart/form-data)

Field	Type	Required
file	file	Yes
folder	string	No
visibility	public / private	No
expires_at	datetime	No

Response

{
  "status": "success",
  "file_id": "uuid",
  "url": "https://cdn.domain.com/files/xxx.jpg"
}

7.2 Public File Access

GET /files/{path}

Tanpa API Key

Hanya file public

Tidak expired

7.3 Private File Access

GET /api/v1/files/private/{id}?token=xxxxx

Signed URL

Auto expired

7.4 Delete File

DELETE /api/v1/files/{id}

8. Database Schema (SQL)
8.1 api_keys

CREATE TABLE api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_key VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(100),
  rate_limit INT DEFAULT 60,
  is_active TINYINT(1) DEFAULT 1,
  expired_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

8.2 files

CREATE TABLE files (
  id CHAR(36) PRIMARY KEY,
  api_key_id INT,
  original_name VARCHAR(255),
  stored_name VARCHAR(255),
  path VARCHAR(255),
  mime VARCHAR(100),
  size BIGINT,
  visibility ENUM('public','private') DEFAULT 'public',
  expires_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
);

8.3 rate_limits

CREATE TABLE rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_key_id INT,
  endpoint VARCHAR(100),
  request_count INT DEFAULT 0,
  window_start DATETIME
);

9. Storage Rules

Filename menggunakan UUID

Folder otomatis berdasarkan tahun/bulan

Public & private terpisah

Direct access hanya untuk public

10. Configuration (application/config/cdn.php)

$config['cdn'] = [
  'max_file_size' => 10485760,
  'allowed_mime' => [
    'image/jpeg',
    'image/png',
    'application/pdf'
  ],
  'storage_path' => FCPATH . '../storage/',
  'signed_url_expire' => 300
];

11. Error Format
{
  "status": "error",
  "code": 401,
  "message": "Invalid API Key"
}

12. Non-Functional Requirements
Area	Requirement
Performance	Upload < 3s (10MB)
Security	API Key + Signed URL
Compatibility	PHP 7.4
Logging	Upload, download, error
13. Future Enhancements

Redis rate limit

S3 / GCS adapter

Image optimization

Chunk upload

14. Success Metrics

Upload success rate ≥ 99%

Error rate ≤ 1%

Average download latency < 200ms