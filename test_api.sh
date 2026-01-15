#!/bin/bash

# CDN API Test Script
# This script tests all API endpoints to verify functionality

BASE_URL="http://localhost/cdn-api"
API_KEY="0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"

echo "=== CDN API Test Script ==="
echo "Base URL: $BASE_URL"
echo "API Key: ${API_KEY:0:8}..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo -e "${YELLOW}Testing: $method $endpoint${NC}"
    echo "Description: $description"
    echo "---"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL$endpoint" \
            -H "X-API-KEY: $API_KEY")
    elif [ "$method" = "POST" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL$endpoint" \
            -H "X-API-KEY: $API_KEY" \
            -H "Content-Type: multipart/form-data" \
            $data)
    elif [ "$method" = "DELETE" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "$BASE_URL$endpoint" \
            -H "X-API-KEY: $API_KEY")
    fi
    
    # Extract HTTP code and response body
    http_code=$(echo "$response" | tail -n1 | cut -d: -f2)
    body=$(echo "$response" | sed '$d')
    
    # Output results
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ Status: $http_code${NC}"
    else
        echo -e "${RED}✗ Status: $http_code${NC}"
    fi
    
    echo "Response:"
    echo "$body" | python3 -m json.tool 2>/dev/null || echo "$body"
    echo ""
    echo "---"
    echo ""
    
    # Store file_id for later tests
    if [[ $endpoint == *"upload"* ]] && [ "$http_code" -eq 200 ]; then
        FILE_ID=$(echo "$body" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    if 'data' in data and 'file_id' in data['data']:
        print(data['data']['file_id'])
except:
    pass")
        echo "File ID extracted: $FILE_ID"
    fi
}

# Create test file
echo "Creating test file..."
echo "This is a test file for CDN API upload." > test_upload.txt
echo ""

# Test 1: API Status (no auth required)
test_endpoint "GET" "/api/v1/status" "" "Get API status"

# Test 2: Health Check (no auth required)
test_endpoint "GET" "/api/v1/health" "" "Health check"

# Test 3: Upload File
test_endpoint "POST" "/api/v1/files/upload" "-F file=@test_upload.txt" "Upload a text file"

# Test 4: List Files
test_endpoint "GET" "/api/v1/files" "" "List all files"

# Test 5: Get File Info
if [ ! -z "$FILE_ID" ]; then
    test_endpoint "GET" "/api/v1/files/$FILE_ID" "" "Get file information"
    
    # Test 6: Generate Signed URL
    test_endpoint "GET" "/api/v1/files/$FILE_ID/signed-url" "" "Generate signed URL for private access"
    
    # Test 7: Delete File
    test_endpoint "DELETE" "/api/v1/files/$FILE_ID" "" "Delete the file"
else
    echo -e "${YELLOW}Skipping file-specific tests - no file ID available${NC}"
fi

# Test 8: Invalid API Key
echo -e "${YELLOW}Testing: Invalid API Key${NC}"
echo "Description: Test with invalid API key"
echo "---"
response=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/api/v1/files" \
    -H "X-API-KEY: invalid-key-1234567890123456789012345678901234567890")
http_code=$(echo "$response" | tail -n1 | cut -d: -f2)
body=$(echo "$response" | sed '$d')

if [ "$http_code" -eq 401 ]; then
    echo -e "${GREEN}✓ Status: $http_code (Expected: 401)${NC}"
else
    echo -e "${RED}✗ Status: $http_code (Expected: 401)${NC}"
fi
echo "Response: $body"
echo ""

# Cleanup
echo "Cleaning up test files..."
rm -f test_upload.txt

echo -e "${GREEN}=== Test Script Complete ===${NC}"
echo ""
echo "Note: Some tests may fail if:"
echo "1. Database is not set up"
echo "2. Web server is not running"
echo "3. Base URL is incorrect"
echo "4. PHP/MySQL extensions are missing"
echo ""
echo "To set up the database:"
echo "1. Create database: CREATE DATABASE cdn_api;"
echo "2. Import schema: mysql cdn_api < database_schema.sql"
echo "3. Update database.php with correct credentials"