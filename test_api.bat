@echo off
REM CDN API Test Script for Windows
REM This script tests all API endpoints to verify functionality

set BASE_URL=http://localhost/cdn-api
set API_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef

echo === CDN API Test Script ===
echo Base URL: %BASE_URL%
echo API Key: %API_KEY:~0,8%...
echo.

REM Function to test endpoint would go here, but batch files don't have functions
REM We'll test endpoints directly

echo Creating test file...
echo This is a test file for CDN API upload. > test_upload.txt
echo.

echo Testing: GET /api/v1/status
echo Description: Get API status
echo ---
curl -s "%BASE_URL%/api/v1/status" | python -m json.tool
echo.
echo ---
echo.

echo Testing: GET /api/v1/health
echo Description: Health check
echo ---
curl -s "%BASE_URL%/api/v1/health" | python -m json.tool
echo.
echo ---
echo.

echo Testing: POST /api/v1/files/upload
echo Description: Upload a text file
echo ---
curl -s -X POST "%BASE_URL%/api/v1/files/upload" ^
    -H "X-API-KEY: %API_KEY%" ^
    -F "file=@test_upload.txt" | python -m json.tool
echo.
echo ---
echo.

echo Testing: GET /api/v1/files
echo Description: List all files
echo ---
curl -s "%BASE_URL%/api/v1/files" ^
    -H "X-API-KEY: %API_KEY%" | python -m json.tool
echo.
echo ---
echo.

echo Testing with invalid API key
echo Description: Test authentication
echo ---
curl -s "%BASE_URL%/api/v1/files" ^
    -H "X-API-KEY: invalid-key-1234567890123456789012345678901234567890" | python -m json.tool
echo.

echo Cleaning up test files...
del test_upload.txt

echo === Test Script Complete ===
echo.
echo Note: Some tests may fail if:
echo 1. Database is not set up
echo 2. Web server is not running
echo 3. Base URL is incorrect
echo 4. PHP/MySQL extensions are missing
echo.
echo To set up the database:
echo 1. Create database: CREATE DATABASE cdn_api;
echo 2. Import schema: mysql cdn_api ^< database_schema.sql
echo 3. Update database.php with correct credentials

pause