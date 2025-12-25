# API Endpoints - Complete Endpoint Reference

This document provides detailed technical documentation for all API endpoints provided by the **API Endpoints Plugin for osTicket**.

**For general plugin information, see:** [README.md](./README.md)

---

## Table of Contents

1. [Authentication](#authentication)
2. [POST /tickets - Create Ticket](#post-tickets---create-ticket)
3. [PATCH /tickets/:number - Update Ticket](#patch-ticketsnumber---update-ticket)
4. [GET /tickets/:number - Get Ticket Details](#get-ticketsnumber---get-ticket-details)
5. [GET /tickets/search - Search Tickets](#get-ticketssearch---search-tickets)
6. [DELETE /tickets/:number - Delete Ticket](#delete-ticketsnumber---delete-ticket)
7. [GET /tickets-stats - Ticket Statistics](#get-tickets-stats---ticket-statistics)
8. [API Key Permissions](#api-key-permissions)
9. [Validation Rules](#validation-rules)

---

## Authentication

All endpoints use osTicket's standard API authentication with the `X-API-Key` header.

**Example:**

```bash
curl -X POST https://yourdomain.com/api/tickets.json \
  -H "X-API-Key: YOUR_API_KEY" \
  -d "..."
```

**API keys are managed in:** Admin Panel → Manage → API Keys

### NGINX Configuration

**Important:** This plugin automatically configures Apache `.htaccess` rewrite rules. If you're using **NGINX**, add the following to your server block:

```nginx
# Endpoints with path info
location ~ ^/api/tickets-(update|get|delete)\.php/ {
    fastcgi_split_path_info ^(/api/tickets-(update|get|delete)\.php)(/.+)$;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php-fpm.sock;
}

# Endpoints without path info
location ~ ^/api/tickets-(search|stats)\.php$ {
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php-fpm.sock;
}
```

**See [README.md](./README.md#problem-using-nginx-instead-of-apache) for complete NGINX setup instructions.**

---

## POST /tickets - Create Ticket

**Endpoint:** `/api/tickets.json`
**Method:** `POST`
**Permissions Required:** `can_create_tickets` (default osTicket permission)

### Extended Parameters

In addition to osTicket's default parameters (`name`, `email`, `subject`, `message`, `topicId`), this plugin adds:

| Parameter            | Type       | Description                                   | Example            |
| -------------------- | ---------- | --------------------------------------------- | ------------------ |
| `format`             | string     | Message format: `text`, `html`, or `markdown` | `"markdown"`       |
| `departmentId`       | int/string | Department ID or name (overrides topic)       | `5` or `"Support"` |
| `parentTicketNumber` | string     | Parent ticket number for subtickets           | `"191215"`         |

### Request Examples

#### Basic Ticket

```bash
curl -X POST "https://yourdomain.com/api/tickets.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "subject": "Issue with login",
    "message": "Cannot log in to my account",
    "topicId": 1
  }'
```

#### Markdown Ticket

```bash
curl -X POST "https://yourdomain.com/api/tickets.json" \
  -H "X-API-Key": "YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Developer",
    "email": "dev@example.com",
    "subject": "Bug Report",
    "message": "# Bug Description\n\n## Steps to Reproduce\n1. Login\n2. Click button\n\n```php\n$code = \"example\";\n```",
    "format": "markdown",
    "topicId": 2
  }'
```

#### Department Override

```bash
curl -X POST "https://yourdomain.com/api/tickets.json" \
  -H "X-API-Key": "YOUR_API_KEY" \
  -H "Content-Type": "application/json" \
  -d '{
    "name": "Premium Customer",
    "email": "premium@example.com",
    "subject": "VIP Support Request",
    "message": "Need immediate assistance",
    "topicId": 1,
    "departmentId": "VIP Support"
  }'
```

### Response

**Success (200 OK):**

```
191215
```

Returns the created ticket number as plain text.

**Error (400 Bad Request):**

```json
{
  "error": "Department 'NonExistent' not found"
}
```

### Validation Rules

- **format:** Must be one of: `text`, `html`, `markdown` (case-insensitive)
- **departmentId:** Must reference active department (by ID or name)
- **parentTicketNumber:** Must reference existing ticket that is not itself a child

---

## PATCH /tickets/:number - Update Ticket

**Endpoint:** `/api/tickets-update.php/{number}.json`
**Method:** `PATCH`
**Permissions Required:** `can_update_tickets` (or `can_create_tickets` fallback for backward compatibility)

### Updateable Properties

| Parameter            | Type        | Description                                                       | Example                                   |
| -------------------- | ----------- | ----------------------------------------------------------------- | ----------------------------------------- |
| `departmentId`       | int/string  | Move ticket to department                                         | `5` or `"Sales"`                          |
| `statusId`           | int/string  | Change ticket status                                              | `1` or `"Open"`                           |
| `topicId`            | int         | Change help topic                                                 | `3`                                       |
| `slaId`              | int         | Assign SLA plan                                                   | `1`                                       |
| `staffId`            | int         | Assign to staff member                                            | `5`                                       |
| `dueDate`            | string/null | Set due date (ISO 8601), null to clear                            | `"2025-01-31"` or `"2025-01-31T17:30:00"` |
| `parentTicketNumber` | string      | Make ticket a subticket                                           | `"123456"`                                |
| `note`               | string      | Add internal note (staff only)                                    | `"Investigated issue, found root cause"` |
| `noteTitle`          | string      | Title for internal note (optional)                                | `"Bug Investigation"`                     |
| `noteFormat`         | string      | Format for note: `text`, `html`, `markdown` (default: `markdown`) | `"markdown"`                              |

### Request Examples

#### Update Department

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "departmentId": "Sales"
  }'
```

#### Update Status

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "statusId": "Resolved"
  }'
```

#### Assign Staff

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "staffId": 5
  }'
```

#### Make Subticket

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "parentTicketNumber": "123456"
  }'
```

#### Update Multiple Properties

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "departmentId": "Development",
    "staffId": 3,
    "statusId": "Open"
  }'
```

#### Add Internal Note

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "Investigated issue, found root cause in authentication module"
  }'
```

#### Add Internal Note with Custom Title and Markdown

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "## Performance Investigation\n\n- Database query optimization completed\n- Response time improved by **40%**\n\n```sql\nSELECT * FROM tickets WHERE id = ?\n```",
    "noteTitle": "Performance Investigation",
    "noteFormat": "markdown"
  }'
```

#### Combine Status Change with Internal Note

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "statusId": "Resolved",
    "note": "Issue resolved via API. Customer notified via email."
  }'
```

#### Set Due Date

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "dueDate": "2025-01-31"
  }'
```

#### Set Due Date with Time

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "dueDate": "2025-01-31T17:30:00"
  }'
```

#### Clear Due Date

```bash
curl -X PATCH "https://yourdomain.com/api/tickets-update.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "dueDate": null
  }'
```

### Response

**Success (200 OK):**

```json
{
  "id": 123,
  "number": "191215",
  "subject": "Issue with login",
  "status": "Open",
  "department": "Sales",
  "priority": "High",
  "created": "2025-01-15 10:30:00",
  "updated": "2025-01-15 14:25:00"
}
```

**Error (404 Not Found):**

```json
{
  "error": "Ticket not found"
}
```

**Error (401 Unauthorized):**

```json
{
  "error": "API key not authorized for ticket updates"
}
```

### Validation Rules

- **departmentId:** Must reference active department
- **statusId:** Must be valid ticket status ID or name
- **staffId:** Must be active staff member
- **slaId:** Must be active SLA plan
- **parentTicketNumber:** Parent cannot be a child ticket itself
- **noteFormat:**
  - `markdown` requires [Markdown Support Plugin](https://github.com/markus-michalski/osticket-plugins/tree/main/markdown-support) to be installed and enabled
  - Without the plugin, notes are saved as `html` (osTicket default)
  - `text` and `html` work without additional plugins

---

## GET /tickets/:number - Get Ticket Details

**Endpoint:** `/api/tickets-get.php/{number}.json`
**Method:** `GET`
**Permissions Required:** `can_read_tickets`

### Request

```bash
curl -X GET "https://yourdomain.com/api/tickets-get.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY"
```

### Response (200 OK)

```json
{
  "id": 123,
  "number": "191215",
  "subject": "Issue with login",
  "status": "Open",
  "department": "Support",
  "priority": "Normal",
  "topic": "General Inquiry",
  "staff": "John Doe",
  "team": null,
  "sla": "Standard Support",
  "user": {
    "name": "Jane Customer",
    "email": "jane@example.com"
  },
  "created": "2025-01-15 10:30:00",
  "updated": "2025-01-15 14:25:00",
  "duedate": "2025-01-16 10:30:00",
  "closed": null,
  "isOverdue": false,
  "isAnswered": true,
  "source": "API",
  "ip": "192.168.1.100",
  "thread": [
    {
      "id": 1,
      "type": "M",
      "poster": "Jane Customer",
      "title": "Issue with login",
      "body": "Cannot log in to my account",
      "created": "2025-01-15 10:30:00"
    },
    {
      "id": 2,
      "type": "R",
      "poster": "John Doe",
      "title": "Re: Issue with login",
      "body": "Please try resetting your password",
      "created": "2025-01-15 11:15:00"
    }
  ]
}
```

### Error Responses

**404 Not Found:**

```json
{
  "error": "Ticket not found"
}
```

**401 Unauthorized:**

```json
{
  "error": "API key not authorized to read tickets"
}
```

### Notes

- **Thread entries:** Includes all messages, responses, and notes
- **Lookup priority:** Searches by ticket number first, falls back to ID
- **Null values:** Fields like `closed`, `staff`, `team` are null if not set

---

## GET /tickets/search - Search Tickets

**Endpoint:** `/api/tickets-search.php`
**Method:** `GET`
**Permissions Required:** `can_search_tickets` (or `can_read_tickets` fallback)

### Query Parameters

| Parameter    | Type            | Description                                                     | Example                    |
| ------------ | --------------- | --------------------------------------------------------------- | -------------------------- |
| `query`      | string          | Search in ticket subject (case-insensitive)                     | `"login issue"`            |
| `status`     | int/string      | Filter by status ID or name                                     | `1` or `"Open"`            |
| `department` | int/string/path | Filter by department (ID, name, or path)                        | `"Development / osTicket"` |
| `limit`      | int             | Results per page (max: 100, default: 20)                        | `50`                       |
| `offset`     | int             | Pagination offset (default: 0)                                  | `20`                       |
| `sort`       | string          | Sort order: `created`, `updated`, `number` (default: `created`) | `"updated:desc"`           |

### Request Examples

#### Search by Query

```bash
curl -X GET "https://yourdomain.com/api/tickets-search.php?query=login" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Filter by Status

```bash
curl -X GET "https://yourdomain.com/api/tickets-search.php?status=Open" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Filter by Department

```bash
curl -X GET "https://yourdomain.com/api/tickets-search.php?department=Support" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Department Path (Hierarchical)

```bash
curl -X GET "https://yourdomain.com/api/tickets-search.php?department=Development%20%2F%20osTicket" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Combined Filters

```bash
curl -X GET "https://yourdomain.com/api/tickets-search.php?query=bug&status=Open&department=Development&limit=50&sort=created:desc" \
  -H "X-API-Key: YOUR_API_KEY"
```

#### Pagination

```bash
# Page 1
curl -X GET "https://yourdomain.com/api/tickets-search.php?limit=20&offset=0" \
  -H "X-API-Key: YOUR_API_KEY"

# Page 2
curl -X GET "https://yourdomain.com/api/tickets-search.php?limit=20&offset=20" \
  -H "X-API-Key: YOUR_API_KEY"
```

### Response (200 OK)

```json
{
  "total": 42,
  "count": 20,
  "offset": 0,
  "limit": 20,
  "tickets": [
    {
      "id": 123,
      "number": "191215",
      "subject": "Login issue",
      "status": "Open",
      "department": "Support",
      "priority": "Normal",
      "staff": "John Doe",
      "created": "2025-01-15 10:30:00",
      "updated": "2025-01-15 14:25:00"
    },
    {
      "id": 124,
      "number": "191216",
      "subject": "Password reset problem",
      "status": "Resolved",
      "department": "Support",
      "priority": "Low",
      "staff": null,
      "created": "2025-01-15 11:00:00",
      "updated": "2025-01-15 15:00:00"
    }
  ]
}
```

### Error Responses

**401 Unauthorized:**

```json
{
  "error": "API key not authorized to search tickets"
}
```

### Notes

- **Empty results:** Returns empty array `[]` if no matches
- **SQL injection protection:** All parameters are escaped
- **Case-insensitive:** All text searches are case-insensitive
- **Department paths:** Support hierarchical paths with `/` separator

---

## DELETE /tickets/:number - Delete Ticket

**Endpoint:** `/api/tickets-delete.php/{number}.json`
**Method:** `DELETE`
**Permissions Required:** `can_delete_tickets` (strict - no fallback)

**⚠️ WARNING:** This is a destructive operation. Deleted tickets cannot be recovered!

### What Gets Deleted (Cascading)

1. Ticket record from `ost_ticket` table
2. All thread entries (messages, responses, notes) from `ost_thread_entry`
3. Custom form data from `ost_ticket__cdata`
4. Parent references: removes `ticket_pid` from child tickets if this ticket is a parent

### Request

```bash
curl -X DELETE "https://yourdomain.com/api/tickets-delete.php/191215.json" \
  -H "X-API-Key: YOUR_API_KEY"
```

### Response

**Success (200 OK):**

```
191215
```

Returns the deleted ticket number as plain text.

**Error (404 Not Found):**

```
Ticket not found
```

**Error (401 Unauthorized):**

```
API key not authorized to delete tickets
```

### Important Notes

- **Audit trail:** All deletions are logged with ticket number, ID, subject, and API key
- **Idempotent:** Returns 404 if ticket doesn't exist (not an error if already deleted)
- **Permission required:** `can_delete_tickets` must be explicitly granted (disabled by default)
- **No undo:** Deletion is permanent and cannot be reversed

### Use Cases

- **Data cleanup:** Remove test tickets from development/staging environments
- **GDPR compliance:** Delete user data upon request
- **Ticket archival:** Remove old tickets after exporting to external systems
- **Automation workflows:** Automatically clean up resolved tickets after retention period

---

## GET /tickets-stats - Ticket Statistics

**Endpoint:** `/api/tickets-stats.php`
**Method:** `GET`
**Permissions Required:** `can_read_stats` (or `can_read_tickets` fallback)

### Request

```bash
curl -X GET "https://yourdomain.com/api/tickets-stats.php" \
  -H "X-API-Key: YOUR_API_KEY"
```

### Response (200 OK)

```json
{
  "total": 150,
  "open": 45,
  "closed": 100,
  "overdue": 5,
  "by_department": {
    "Billing": {
      "total": 30,
      "open": 10,
      "closed": 18,
      "overdue": 2
    },
    "Sales": {
      "total": 50,
      "open": 15,
      "closed": 32,
      "overdue": 3
    },
    "Support": {
      "total": 70,
      "open": 20,
      "closed": 50,
      "overdue": 0
    }
  },
  "by_staff": [
    {
      "staff_id": 3,
      "staff_name": "Alice Johnson",
      "total": 25,
      "departments": {
        "Sales": {
          "open": 8,
          "closed": 12,
          "overdue": 0
        },
        "Support": {
          "open": 3,
          "closed": 2,
          "overdue": 0
        }
      }
    },
    {
      "staff_id": 2,
      "staff_name": "Jane Smith",
      "total": 40,
      "departments": {
        "Billing": {
          "open": 10,
          "closed": 18,
          "overdue": 2
        },
        "Support": {
          "open": 5,
          "closed": 5,
          "overdue": 0
        }
      }
    }
  ]
}
```

### Statistics Breakdown

#### Global Statistics

- **total:** Total number of tickets in the system
- **open:** Number of tickets that are not closed (closed field is null)
- **closed:** Number of tickets that are closed (closed field is not null)
- **overdue:** Number of tickets that are overdue

#### By Department (`by_department`)

- Object with department names as keys
- Each department contains: `total`, `open`, `closed`, `overdue`
- **Sorted alphabetically** by department name
- Only includes departments that have tickets

#### By Staff (`by_staff`)

- Array of staff members (only staff with assigned tickets)
- **Sorted alphabetically** by staff name
- Each staff member contains:
  - `staff_id` - Staff member ID
  - `staff_name` - Staff member full name
  - `total` - Total number of tickets assigned to this staff member
  - `departments` - Object with department breakdown
    - Department name as key
    - Each department contains: `open`, `closed`, `overdue` for this staff member

### Error Responses

**401 Unauthorized:**

```json
{
  "error": "API key not authorized to read ticket statistics"
}
```

### Important Notes

- **All tickets:** Returns stats for all tickets in the system (no filtering by date/status)
- **Unassigned tickets:** Tickets without assigned staff are excluded from `by_staff` stats
- **Orphaned tickets:** Tickets without department object are excluded from `by_department` stats
- **Global counts:** Unassigned/orphaned tickets are included in global `total`/`open`/`closed`/`overdue` counts
- **Alphabetical sorting:** Ensures consistent output for UI rendering

### Use Cases

- **Dashboard displays:** Real-time ticket metrics for management dashboards
- **Team performance tracking:** Monitor staff workload and ticket distribution
- **Department analytics:** Analyze ticket volume and status by department
- **SLA monitoring:** Track overdue tickets across teams and departments
- **Capacity planning:** Identify bottlenecks and resource allocation needs
- **Reporting:** Generate periodic reports on ticket system health

---

## API Key Permissions

The plugin extends osTicket's API key table with granular permissions:

| Permission           | Grants Access To                 | Default                  |
| -------------------- | -------------------------------- | ------------------------ |
| `can_create_tickets` | POST /tickets (create)           | ✅ Yes (osTicket default) |
| `can_update_tickets` | PATCH /tickets/:number (update)  | ❌ No                     |
| `can_read_tickets`   | GET /tickets/:number (retrieve)  | ❌ No                     |
| `can_search_tickets` | GET /tickets/search (search)     | ❌ No                     |
| `can_delete_tickets` | DELETE /tickets/:number (delete) | ❌ No                     |
| `can_read_stats`     | GET /tickets-stats (statistics)  | ❌ No                     |

### Permission Hierarchy

**Search Permissions:**

- `can_search_tickets` grants search access
- If not granted, falls back to `can_read_tickets`

**Stats Permissions:**

- `can_read_stats` grants statistics access
- If not granted, falls back to `can_read_tickets`

### Configure in Admin Panel

1. Navigate to: **Admin Panel → Manage → API Keys**
2. Click **"Configure"** next to API Endpoints Plugin
3. Check the permission checkboxes for each API key
4. Click **"Save Changes"**

**Important:** After plugin updates, you may need to disable and re-enable the plugin to apply database schema changes.

---

## Validation Rules

### Format Parameter

**Accepted values:** `text`, `html`, `markdown` (case-insensitive, whitespace-trimmed)

**Validation:**

- Must be one of the three accepted values
- Case-insensitive: `Markdown`, `MARKDOWN`, `markdown` all accepted
- Leading/trailing whitespace is automatically trimmed
- Empty or null values default to `text`

**Markdown Plugin Check:**

- If `format=markdown` is provided:
  - Plugin checks if Markdown Support Plugin is active
  - If not active and `Require Markdown Plugin` setting is enabled → Error 400
  - If not active and setting disabled → Warning in logs, processes as plain text

### Department Parameter

**Accepted formats:**

- **ID (numeric):** `5`
- **Name (string):** `"Support"`
- **Path (hierarchical):** `"Development / osTicket"`

**Validation:**

- Department must exist and be active
- If inactive department is provided → Error 400
- Path format supports nested departments with ` / ` separator
- Case-insensitive name matching

### Parent Ticket Parameter

**Accepted formats:**

- **Ticket number:** `"191215"` (recommended)
- **Ticket ID:** `123` (fallback)

**Validation:**

- Parent ticket must exist
- Parent ticket cannot itself be a child ticket (prevents circular dependencies)
- Lookup priority: ticket number first, then ID

---

## Examples

### Integration Example: Zapier/Make.com

```javascript
// Zapier Webhook POST request
{
  "url": "https://yourdomain.com/api/tickets.json",
  "method": "POST",
  "headers": {
    "X-API-Key": "YOUR_API_KEY",
    "Content-Type": "application/json"
  },
  "body": {
    "name": "{{customer_name}}",
    "email": "{{customer_email}}",
    "subject": "{{form_subject}}",
    "message": "{{form_message}}",
    "format": "markdown",
    "departmentId": "{{department_mapping}}"
  }
}
```

### Integration Example: Bug Tracker → osTicket

```bash
#!/bin/bash
# Create subticket for critical bugs

PARENT_TICKET="191215"
BUG_TITLE="Critical: Database Connection Timeout"
BUG_DESCRIPTION="# Stack Trace\n\n\`\`\`\n$STACK_TRACE\n\`\`\`"

curl -X POST "https://yourdomain.com/api/tickets.json" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Bug Tracker Bot\",
    \"email\": \"bugs@company.com\",
    \"subject\": \"$BUG_TITLE\",
    \"message\": \"$BUG_DESCRIPTION\",
    \"format\": \"markdown\",
    \"parentTicketNumber\": \"$PARENT_TICKET\",
    \"departmentId\": \"Development\"
  }"
```

### Integration Example: Automated Ticket Cleanup

```bash
#!/bin/bash
# Delete resolved tickets older than 90 days

# Get resolved tickets
TICKETS=$(curl -X GET "https://yourdomain.com/api/tickets-search.php?status=Resolved&limit=100" \
  -H "X-API-Key: $API_KEY")

# Loop through and delete old tickets
echo "$TICKETS" | jq -r '.tickets[] | select(.updated < "'$(date -d '90 days ago' +%Y-%m-%d)'") | .number' | while read TICKET; do
  echo "Deleting ticket: $TICKET"
  curl -X DELETE "https://yourdomain.com/api/tickets-delete.php/${TICKET}.json" \
    -H "X-API-Key: $API_KEY"
done
```

---

## Error Codes

| Code | Description           | Common Causes                                             |
| ---- | --------------------- | --------------------------------------------------------- |
| 400  | Bad Request           | Invalid parameter format, validation error                |
| 401  | Unauthorized          | Missing/invalid API key, insufficient permissions         |
| 404  | Not Found             | Ticket, department, or resource not found                 |
| 405  | Method Not Allowed    | Using wrong HTTP method (e.g., GET on POST-only endpoint) |
| 500  | Internal Server Error | Server-side exception, check error logs                   |

---

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history and release notes.
