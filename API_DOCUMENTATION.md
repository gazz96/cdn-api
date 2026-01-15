# CDN API Documentation

## Overview

CDN API adalah internal Content Delivery Network berbasis CodeIgniter 3 yang menyediakan layanan upload dan distribusi file dengan authentication API key dan rate limiting.

## Base URL

```
Development: http://localhost/cdn-api
Production: https://cdn-api.example.com
```

## Authentication

Semua endpoint yang dilindungi membutuhkan API key di header:

```
X-API-KEY: your-api-key-here
```

## API Endpoints

### 1. Status & Health Check

#### Get API Status
```http
GET /api/v1/status
```

**Response:**
```json
{
  "status": "success",
  "message": "CDN API Status",
  "data": {
    "api_version": "v1",
    "status": "online",
    "timestamp": "2026-01-14 09:51:00",
    "uptime": {
      "load_1min": 0.5,
      "load_5min": 0.3,
      "load_15min": 0.2
    },
    "memory_usage": {
      "current": "32 MB",
      "peak": "48 MB"
    },
    "storage_info": {
      "storage_path": "/path/to/storage/",
      "public_folder_exists": true,
      "private_folder_exists": true
    },
    "database_status": {
      "status": "ok",
      "message": "Database connection successful"
    }
  }
}
```

#### Health Check
```http
GET /api/v1/health
```

### 2. File Management

#### Upload File
```http
POST /api/v1/files/upload
Content-Type: multipart/form-data
X-API-KEY: your-api-key
```

**Form Data:**
- `file` (required) - File to upload
- `folder` (optional) - Custom folder name
- `visibility` (optional) - `public` or `private` (default: public)
- `expires_at` (optional) - Expiration date (Y-m-d H:i:s)

**Response:**
```json
{
  "status": "success",
  "message": "File uploaded successfully",
  "data": {
    "file_id": "12345678-1234-1234-1234-123456789abc",
    "original_name": "document.pdf",
    "size": 2048576,
    "mime": "application/pdf",
    "visibility": "public",
    "url": "http://localhost/cdn-api/files/2026/01/uuid-generated-name.pdf",
    "expires_at": null
  }
}
```

#### List Files
```http
GET /api/v1/files
X-API-KEY: your-api-key
```

**Query Parameters:**
- `page` (optional) - Page number (default: 1)
- `limit` (optional) - Items per page, max 100 (default: 20)
- `visibility` (optional) - Filter by `public` or `private`
- `search` (optional) - Search in original file names

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "files": [
      {
        "file_id": "12345678-1234-1234-1234-123456789abc",
        "original_name": "document.pdf",
        "size": 2048576,
        "mime": "application/pdf",
        "visibility": "public",
        "url": "http://localhost/cdn-api/files/2026/01/uuid-generated-name.pdf",
        "created_at": "2026-01-14 09:51:00",
        "expires_at": null
      }
    ],
    "page": 1,
    "limit": 20,
    "total": 1
  }
}
```

#### Get File Info
```http
GET /api/v1/files/{file_id}
X-API-KEY: your-api-key
```

#### Delete File
```http
DELETE /api/v1/files/{file_id}
X-API-KEY: your-api-key
```

### 3. Private File Access

#### Generate Signed URL
```http
GET /api/v1/files/{file_id}/signed-url?expires_in=300
X-API-KEY: your-api-key
```

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "file_id": "12345678-1234-1234-1234-123456789abc",
    "signed_url": "http://localhost/cdn-api/api/v1/files/private/12345678-1234-1234-1234-123456789abc?expires=1642389000&signature=abc123",
    "expires_in": 300,
    "expires_at": "2026-01-14 10:00:00"
  }
}
```

#### Access Private File via Signed URL
```http
GET /api/v1/files/private/{file_id}?expires=1642389000&signature=abc123
```

### 4. Public File Access

#### Direct Public File Access
```http
GET /files/{relative_path}
```

Example:
```http
GET /files/2026/01/uuid-generated-name.jpg
```

## Error Responses

All error responses follow this format:

```json
{
  "status": "error",
  "code": 401,
  "message": "Invalid API Key",
  "data": {}
}
```

### Common Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 400 | Bad Request | Invalid request parameters |
| 401 | Unauthorized | Missing or invalid API key |
| 403 | Forbidden | API key inactive/expired or access denied |
| 404 | Not Found | File or endpoint not found |
| 409 | Conflict | File already exists |
| 410 | Gone | File has expired |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

## Rate Limiting

- Default rate limit: 60 requests per hour per API key
- Rate limit headers included in responses:
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Remaining requests
  - `X-RateLimit-Reset`: Unix timestamp when limit resets
- When rate limited: `Retry-After` header included

## File Storage

### Public Files
- Accessible via direct URL
- No authentication required
- Example: `http://cdn.example.com/files/2026/01/filename.jpg`

### Private Files
- Require API key + signed URL
- Signed URLs expire after 5 minutes (configurable)
- Temporary access token based

### Folder Structure
```
storage/
├── public/
│   └── 2026/01/  # Year/month folders
└── private/
    └── 2026/01/
```

## Security Features

- API key authentication
- Rate limiting per API key
- Signed URLs for private files
- File expiration
- CORS support
- Security headers
- .htaccess protection against script execution

## Configuration

Key configuration options in `application/config/cdn.php`:

```php
$config['cdn'] = [
    'max_file_size' => 10485760,        // 10MB
    'allowed_mime' => [
        'image/jpeg', 'image/png', 'application/pdf'
    ],
    'signed_url_expire' => 300,           // 5 minutes
    'default_rate_limit' => 60,           // per hour
    'cors_enabled' => true,
    'base_url' => 'http://localhost/cdn-api'
];
```

## Database Schema

### Tables

1. **api_keys** - API key management
2. **files** - File metadata
3. **rate_limits** - Rate limiting

Import schema: `mysql cdn_api < database_schema.sql`

## Example Usage

### cURL Example

```bash
# Upload file
curl -X POST http://localhost/cdn-api/api/v1/files/upload \
  -H "X-API-KEY: your-api-key" \
  -F "file=@document.pdf" \
  -F "visibility=private"

# List files
curl http://localhost/cdn-api/api/v1/files \
  -H "X-API-KEY: your-api-key"

# Delete file
curl -X DELETE http://localhost/cdn-api/api/v1/files/12345678-1234-1234-1234-123456789abc \
  -H "X-API-KEY: your-api-key"
```

### JavaScript Example

```javascript
// Upload file
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('visibility', 'public');

fetch('/api/v1/files/upload', {
  method: 'POST',
  headers: {
    'X-API-KEY': 'your-api-key'
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### PHP Example

```php
// Upload file using Guzzle
$client = new \GuzzleHttp\Client([
    'base_uri' => 'http://localhost/cdn-api/'
]);

$response = $client->post('api/v1/files/upload', [
    'headers' => [
        'X-API-KEY' => 'your-api-key'
    ],
    'multipart' => [
        [
            'name' => 'file',
            'contents' => fopen('/path/to/file.pdf', 'r')
        ],
        [
            'name' => 'visibility',
            'contents' => 'private'
        ]
    ]
]);

$data = json_decode($response->getBody(), true);
```

## Monitoring

- Status endpoint: `/api/v1/status`
- Health check: `/api/v1/health`
- Application logs: `application/logs/`
- Web server logs: Apache/Nginx error logs

## Deployment

### Requirements
- PHP 7.4+ (8.3+ recommended)
- MySQL 5.7+ / MariaDB 10.2+
- Web server (Apache/Nginx)
- Required PHP extensions: mysqli, curl, json, gd, fileinfo

### Setup Steps
1. Configure web server (see `apache.conf` or `nginx.conf`)
2. Create database and import schema
3. Update database configuration
4. Set proper permissions on storage folder
5. Configure CDN settings
6. Generate API keys
7. Test with provided test scripts

## Troubleshooting

### Common Issues

1. **401 Unauthorized**: Check API key in headers
2. **404 Not Found**: Check URL and routing
3. **429 Too Many Requests**: Rate limit exceeded
4. **Upload Failed**: Check file size, MIME type, permissions
5. **Database Error**: Check connection and schema

### Debug Mode
Set `ENVIRONMENT` to `'development'` in `index.php` for detailed error messages.

## Support

For issues and questions:
1. Check application logs
2. Test with provided scripts: `./test_api.sh` (Linux/Mac) or `test_api.bat` (Windows)
3. Verify database connection
4. Check web server configuration
5. Review API key status