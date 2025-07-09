#!/bin/bash

# Test script for REST endpoint
# Usage: ./test-rest-endpoint.sh [post-id]

POST_ID="${1:-331}"
SITE_URL="https://childrensliteracy.ddev.site"
ENDPOINT="${SITE_URL}/wp-json/pikari-gutenberg-modals/v1/modal-content/${POST_ID}"

echo "Testing REST endpoint for post ID: ${POST_ID}"
echo "Endpoint: ${ENDPOINT}"
echo "---"

# Make the request and save to file
curl -s "${ENDPOINT}" | jq '.' > modal-response.json

# Check if styles are present
if jq -e '.styles != ""' modal-response.json > /dev/null; then
  echo "✅ Styles field is populated!"
  echo ""
  echo "Styles preview (first 500 chars):"
  jq -r '.styles' modal-response.json | head -c 500
  echo ""
  echo "..."
  echo ""
  echo "Checking for block support classes:"
  if jq -r '.styles' modal-response.json | grep -q "wp-container-"; then
    echo "✅ Found wp-container classes!"
    jq -r '.styles' modal-response.json | grep -o "\.wp-container-[^{]*" | sort | uniq
  else
    echo "❌ No wp-container classes found"
  fi
else
  echo "❌ Styles field is empty"
fi

echo ""
echo "Full response saved to: modal-response.json"