#!/bin/bash
# =============================================================================
# CURL Test Suite for osTicket API Endpoints Plugin
# =============================================================================
#
# USAGE:
#   1. Set your API key and base URL below
#   2. Create test tickets in osTicket and note their numbers
#   3. Run: bash TEST_CURL_COMMANDS.sh
#
# =============================================================================

# Configuration
API_KEY="CE206076F0B32E58AB3EBBF8CBD2DD29"
# IMPORTANT: Most endpoints require DIRECT API access (not via wildcard)
# Only /api/wildcard/tickets.json is supported by the Wildcard Plugin
BASE_URL="https://stage.tickets.markus-michalski.net/api"  # Direct API access

# Test data - REPLACE WITH REAL TICKET NUMBERS FROM YOUR SYSTEM
PARENT_TICKET="456846"   # Existing ticket to use as parent
CHILD_TICKET="237948"    # Existing ticket to use as child
TICKET_TO_UPDATE="237948" # Existing ticket for update tests
TICKET_TO_DELETE="197101" # Existing ticket for delete tests (WARNING: will be deleted!)

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print section header
section() {
    echo -e "\n${BLUE}===================================================================${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}===================================================================${NC}\n"
}

# Function to print test header
test_header() {
    echo -e "${GREEN}[TEST]${NC} $1"
    echo -e "${YELLOW}Command:${NC}"
}

# Function to execute curl command
run_curl() {
    echo "$1"
    echo -e "\n${YELLOW}Response:${NC}"
    eval "$1"
    echo -e "\n"
}

# =============================================================================
# 1. LEGACY ENDPOINTS (Refactored with Security Fixes)
# =============================================================================

section "1. LEGACY ENDPOINTS - tickets-get.php"

test_header "1.1 Get Ticket Details (JSON)"
run_curl "curl -X GET \"${BASE_URL}/tickets-get.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "1.2 Get Ticket Details (XML)"
run_curl "curl -X GET \"${BASE_URL}/tickets-get.php/${PARENT_TICKET}.xml\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s"

test_header "1.3 Get Non-Existent Ticket (404 Error)"
run_curl "curl -X GET \"${BASE_URL}/tickets-get.php/999999.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

# =============================================================================

section "2. LEGACY ENDPOINTS - tickets-search.php"

test_header "2.1 Search Tickets by Query"
run_curl "curl -X GET \"${BASE_URL}/tickets-search.php?query=test&limit=10\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "2.2 Search Tickets with Status Filter"
run_curl "curl -X GET \"${BASE_URL}/tickets-search.php?status=Open&limit=5\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "2.3 Search Tickets with Department Filter"
run_curl "curl -X GET \"${BASE_URL}/tickets-search.php?department=Support&limit=5\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "2.4 Search Tickets with Pagination"
run_curl "curl -X GET \"${BASE_URL}/tickets-search.php?query=bug&limit=10&offset=0\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "2.5 Search Tickets (XML format)"
run_curl "curl -X GET \"${BASE_URL}/tickets-search.php/.xml?query=test&limit=5\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s"

# =============================================================================

section "3. LEGACY ENDPOINTS - tickets-update.php"

test_header "3.1 Update Ticket Status"
run_curl "curl -X PATCH \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"statusId\": 3}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "3.2 Update Ticket Department"
run_curl "curl -X PATCH \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"departmentId\": \"Support\"}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "3.3 Update Ticket Topic (Help Topic)"
run_curl "curl -X PATCH \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"topicId\": 2}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "3.4 Update Multiple Properties Simultaneously"
run_curl "curl -X PATCH \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"statusId\": 1, \"departmentId\": 2, \"topicId\": 3}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "3.5 Update with Invalid JSON (400 Error)"
run_curl "curl -X PATCH \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"statusId\": INVALID}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "3.6 Update with Wrong HTTP Method (405 Error)"
run_curl "curl -X GET \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

# =============================================================================

section "4. LEGACY ENDPOINTS - tickets-stats.php"

test_header "4.1 Get Ticket Statistics (JSON)"
run_curl "curl -X GET \"${BASE_URL}/tickets-stats.php/.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "4.2 Get Ticket Statistics (XML)"
run_curl "curl -X GET \"${BASE_URL}/tickets-stats.php/.xml\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s"

# =============================================================================

section "5. LEGACY ENDPOINTS - tickets-statuses.php"

test_header "5.1 Get All Ticket Statuses (JSON)"
run_curl "curl -X GET \"${BASE_URL}/tickets-statuses.php/.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "5.2 Get All Ticket Statuses (XML)"
run_curl "curl -X GET \"${BASE_URL}/tickets-statuses.php/.xml\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s"

# =============================================================================

section "6. LEGACY ENDPOINTS - tickets-delete.php"

echo -e "${RED}WARNING: This will DELETE ticket ${TICKET_TO_DELETE}!${NC}"
read -p "Press Enter to continue or Ctrl+C to abort..."

test_header "6.1 Delete Ticket"
run_curl "curl -X DELETE \"${BASE_URL}/tickets-delete.php/${TICKET_TO_DELETE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "6.2 Delete Non-Existent Ticket (404 Error)"
run_curl "curl -X DELETE \"${BASE_URL}/tickets-delete.php/999999.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

# =============================================================================
# 2. NEW SUBTICKET ENDPOINTS
# =============================================================================

section "7. SUBTICKET ENDPOINTS - tickets-subtickets-parent.php"

test_header "7.1 Get Parent Ticket (JSON)"
run_curl "curl -X GET \"${BASE_URL}/tickets-subtickets-parent.php/${CHILD_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "7.2 Get Parent Ticket (XML)"
run_curl "curl -X GET \"${BASE_URL}/tickets-subtickets-parent.php/${CHILD_TICKET}.xml\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s"

test_header "7.3 Get Parent of Ticket Without Parent (404 Error)"
run_curl "curl -X GET \"${BASE_URL}/tickets-subtickets-parent.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

# =============================================================================

section "8. SUBTICKET ENDPOINTS - tickets-subtickets-list.php"

test_header "8.1 Get List of Child Tickets (JSON)"
run_curl "curl -X GET \"${BASE_URL}/tickets-subtickets-list.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "8.2 Get List of Child Tickets (XML)"
run_curl "curl -X GET \"${BASE_URL}/tickets-subtickets-list.php/${PARENT_TICKET}.xml\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s"

test_header "8.3 Get Children of Ticket Without Children (Empty Array)"
run_curl "curl -X GET \"${BASE_URL}/tickets-subtickets-list.php/${CHILD_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

# =============================================================================

section "9. SUBTICKET ENDPOINTS - tickets-subtickets-create.php"

test_header "9.1 Create Subticket Link"
run_curl "curl -X POST \"${BASE_URL}/tickets-subtickets-create.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"childId\": ${CHILD_TICKET}}' \
  -s | jq ."

test_header "9.2 Create Subticket Link (Already Linked - 409 Error)"
run_curl "curl -X POST \"${BASE_URL}/tickets-subtickets-create.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"childId\": ${CHILD_TICKET}}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "9.3 Create Subticket Link with Missing childId (400 Error)"
run_curl "curl -X POST \"${BASE_URL}/tickets-subtickets-create.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "9.4 Create Subticket Link with Non-Existent Parent (404 Error)"
run_curl "curl -X POST \"${BASE_URL}/tickets-subtickets-create.php/999999.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"childId\": ${CHILD_TICKET}}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "9.5 Create Subticket Link with Non-Existent Child (404 Error)"
run_curl "curl -X POST \"${BASE_URL}/tickets-subtickets-create.php/${PARENT_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '{\"childId\": 999999}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

# =============================================================================

section "10. SUBTICKET ENDPOINTS - tickets-subtickets-unlink.php"

test_header "10.1 Unlink Subticket"
run_curl "curl -X DELETE \"${BASE_URL}/tickets-subtickets-unlink.php/${CHILD_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | jq ."

test_header "10.2 Unlink Subticket (Already Unlinked - 404 Error)"
run_curl "curl -X DELETE \"${BASE_URL}/tickets-subtickets-unlink.php/${CHILD_TICKET}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "10.3 Unlink Non-Existent Ticket (404 Error)"
run_curl "curl -X DELETE \"${BASE_URL}/tickets-subtickets-unlink.php/999999.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

# =============================================================================

section "11. SECURITY TESTS (Should Fail)"

test_header "11.1 Test XXE Protection (XML External Entity Attack)"
run_curl "curl -X GET \"${BASE_URL}/tickets-get.php/${PARENT_TICKET}.xml\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s | grep -q '<!DOCTYPE' && echo 'VULNERABLE: XXE possible!' || echo 'SAFE: No DOCTYPE in response'"

test_header "11.2 Test JSON Bomb Protection (Deep Nesting)"
DEEP_JSON=$(python3 -c "print('{' * 1000 + '}' * 1000)")
run_curl "curl -X PATCH \"${BASE_URL}/tickets-update.php/${TICKET_TO_UPDATE}.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -H \"Content-Type: application/json\" \
  -d '${DEEP_JSON}' \
  -s -w \"\\nHTTP Status: %{http_code}\\n\""

test_header "11.3 Test Invalid HTTP Status Code Injection"
run_curl "curl -X GET \"${BASE_URL}/tickets-get.php/INVALID_TICKET.json\" \
  -H \"X-API-Key: ${API_KEY}\" \
  -s -w \"\\nHTTP Status: %{http_code}\\n\" | grep -q '^[1-5][0-9][0-9]$' && echo 'SAFE: Valid HTTP status' || echo 'VULNERABLE: Invalid HTTP status'"

# =============================================================================

section "TEST SUITE COMPLETED"

echo -e "${GREEN}All tests completed!${NC}"
echo -e "${YELLOW}Note:${NC} Some tests are expected to fail (404, 409, etc.) - this is normal."
echo -e "${YELLOW}Review the responses above to verify correct behavior.${NC}"
