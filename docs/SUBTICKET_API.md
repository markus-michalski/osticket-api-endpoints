# Subticket API Endpoints

API-Endpunkte zur Verwaltung von Parent-Child-Beziehungen zwischen Tickets (Subtickets).

## Voraussetzungen

- API-Key mit `can_manage_subtickets` Permission
- [Subticket Manager Plugin](https://github.com/clonemeagain/plugin-subticket) installiert und aktiv

## Endpunkte

### 1. GET Parent Ticket

Gibt das Parent-Ticket eines Child-Tickets zurück.

**URL:** `GET /api/tickets-subtickets-parent.php/{child_id}.json`

**Response (Parent existiert):**
```json
{
  "parent": {
    "ticket_id": 100,
    "number": "ABC100",
    "subject": "Parent Ticket",
    "status": "Open"
  }
}
```

**Response (Kein Parent):**
```json
{
  "parent": null
}
```

**Error Codes:**
- `400` - Invalid ticket ID
- `403` - API key not authorized
- `404` - Child ticket not found
- `501` - Subticket plugin not available

---

### 2. GET Children List

Gibt alle Child-Tickets eines Parent-Tickets zurück.

**URL:** `GET /api/tickets-subtickets-list.php/{parent_id}.json`

**Response (Mit Children):**
```json
{
  "children": [
    {
      "ticket_id": 123,
      "number": "ABC123",
      "subject": "Child Ticket 1",
      "status": "Open"
    },
    {
      "ticket_id": 124,
      "number": "ABC124",
      "subject": "Child Ticket 2",
      "status": "Closed"
    }
  ]
}
```

**Response (Keine Children):**
```json
{
  "children": []
}
```

**Error Codes:**
- `400` - Invalid ticket ID
- `403` - API key not authorized
- `404` - Parent ticket not found
- `501` - Subticket plugin not available

---

### 3. POST Create Link

Erstellt eine Parent-Child-Beziehung zwischen zwei Tickets.

**URL:** `POST /api/tickets-subtickets-create.php`

**Request Body:**
```json
{
  "parent_id": 100,
  "child_id": 200
}
```

**Response:**
```json
{
  "success": true,
  "message": "Subticket relationship created successfully",
  "parent": {
    "ticket_id": 100,
    "number": "ABC100",
    "subject": "Parent Ticket",
    "status": "Open"
  },
  "child": {
    "ticket_id": 200,
    "number": "ABC200",
    "subject": "Child Ticket",
    "status": "Open"
  }
}
```

**Error Codes:**
- `400` - Invalid ticket IDs or self-link attempt
- `403` - API key not authorized (permission or department access)
- `404` - Parent or child ticket not found
- `409` - Child already has a parent
- `501` - Subticket plugin not available

**Validation:**
- Parent und Child dürfen nicht identisch sein
- Child darf noch keinen Parent haben
- Beide Tickets müssen existieren
- API-Key benötigt Zugriff auf beide Ticket-Departments

---

### 4. DELETE Unlink Child

Entfernt die Parent-Child-Beziehung eines Child-Tickets.

**URL:** `DELETE /api/tickets-subtickets-unlink.php`

**Alternative:** `POST /api/tickets-subtickets-unlink.php` (für Legacy-Support)

**Request Body:**
```json
{
  "child_id": 200
}
```

**Response:**
```json
{
  "success": true,
  "message": "Subticket relationship removed successfully",
  "child": {
    "ticket_id": 200,
    "number": "ABC200",
    "subject": "Child Ticket",
    "status": "Open"
  }
}
```

**Error Codes:**
- `400` - Invalid child ticket ID
- `403` - API key not authorized (permission or department access)
- `404` - Child ticket not found or child has no parent
- `501` - Subticket plugin not available

---

## Beispiele

### cURL

```bash
# Get Parent
curl -X GET "https://your-osticket.com/api/tickets-subtickets-parent.php/200.json" \
  -H "X-API-Key: YOUR_API_KEY"

# Get Children
curl -X GET "https://your-osticket.com/api/tickets-subtickets-list.php/100.json" \
  -H "X-API-Key: YOUR_API_KEY"

# Create Link
curl -X POST "https://your-osticket.com/api/tickets-subtickets-create.php" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"parent_id": 100, "child_id": 200}'

# Unlink Child
curl -X DELETE "https://your-osticket.com/api/tickets-subtickets-unlink.php" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"child_id": 200}'
```

### PHP

```php
<?php
$apiKey = 'YOUR_API_KEY';
$apiUrl = 'https://your-osticket.com/api';

// Create Link
$ch = curl_init("$apiUrl/tickets-subtickets-create.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "X-API-Key: $apiKey",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'parent_id' => 100,
        'child_id' => 200
    ])
]);
$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Link created: {$result['parent']['number']} → {$result['child']['number']}";
}
```

### JavaScript (Fetch API)

```javascript
// Get Children
fetch('https://your-osticket.com/api/tickets-subtickets-list.php/100.json', {
  headers: {
    'X-API-Key': 'YOUR_API_KEY'
  }
})
.then(response => response.json())
.then(data => {
  console.log(`Parent has ${data.children.length} children`);
  data.children.forEach(child => {
    console.log(`- ${child.number}: ${child.subject}`);
  });
});

// Unlink Child
fetch('https://your-osticket.com/api/tickets-subtickets-unlink.php', {
  method: 'DELETE',
  headers: {
    'X-API-Key': 'YOUR_API_KEY',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ child_id: 200 })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    console.log('Unlinked:', data.child.number);
  }
});
```

## Workflow-Beispiel

```bash
# 1. Erstelle Parent-Ticket
parent_id=$(curl -X POST "$apiUrl/tickets.json" \
  -H "X-API-Key: $apiKey" \
  -d '...' | jq -r '.id')

# 2. Erstelle Child-Ticket
child_id=$(curl -X POST "$apiUrl/tickets.json" \
  -H "X-API-Key: $apiKey" \
  -d '...' | jq -r '.id')

# 3. Verknüpfe Tickets
curl -X POST "$apiUrl/tickets-subtickets-create.php" \
  -H "X-API-Key: $apiKey" \
  -H "Content-Type: application/json" \
  -d "{\"parent_id\": $parent_id, \"child_id\": $child_id}"

# 4. Prüfe Children
curl -X GET "$apiUrl/tickets-subtickets-list.php/$parent_id.json" \
  -H "X-API-Key: $apiKey"

# 5. Prüfe Parent
curl -X GET "$apiUrl/tickets-subtickets-parent.php/$child_id.json" \
  -H "X-API-Key: $apiKey"

# 6. Entferne Verknüpfung
curl -X DELETE "$apiUrl/tickets-subtickets-unlink.php" \
  -H "X-API-Key: $apiKey" \
  -H "Content-Type: application/json" \
  -d "{\"child_id\": $child_id}"
```

## Department-Level Authorization

Wenn API-Keys mit Department-Einschränkungen konfiguriert sind:

- API-Key benötigt Zugriff auf **beide** Ticket-Departments (Parent und Child)
- Bei `createLink()`: Zugriff auf Parent-Department UND Child-Department erforderlich
- Bei `unlinkChild()`: Zugriff auf Child-Department erforderlich
- Bei fehlender Berechtigung: `403 Access denied to ticket department`

## XML-Format (GET-Endpoints)

GET-Endpoints unterstützen auch XML-Format:

```bash
curl -X GET "https://your-osticket.com/api/tickets-subtickets-list.php/100.xml" \
  -H "X-API-Key: YOUR_API_KEY"
```

**Response:**
```xml
<?xml version="1.0"?>
<response>
  <children>
    <item>
      <ticket_id>123</ticket_id>
      <number>ABC123</number>
      <subject>Child Ticket 1</subject>
      <status>Open</status>
    </item>
  </children>
</response>
```

## Rate Limiting

⚠️ **Hinweis:** Aktuell kein Rate Limiting implementiert. Für Production-Umgebungen wird empfohlen, Rate Limiting auf Webserver-Ebene (z.B. Nginx `limit_req`) zu implementieren.

## Security

- **API-Key:** Über `X-API-Key` Header authentifizieren
- **Permissions:** `can_manage_subtickets` erforderlich
- **Department Access:** Automatische Prüfung bei restricted API-Keys
- **Input Validation:** Alle IDs werden validiert und type-casted
- **JSON Injection:** JSON-Decoding mit Error-Checks
- **XML Injection:** XML-Output mit `htmlspecialchars()` escaped

## Troubleshooting

### "Subticket plugin not available" (501)

**Ursache:** Subticket Manager Plugin nicht installiert oder inaktiv

**Lösung:**
1. Plugin installieren: https://github.com/clonemeagain/plugin-subticket
2. Plugin aktivieren: Admin Panel → Manage → Plugins
3. Prüfen mit `GET /api/tickets-subtickets-parent.php/1.json`

### "API key not authorized for subticket operations" (403)

**Ursache:** API-Key hat keine `can_manage_subtickets` Permission

**Lösung:**
1. Admin Panel → Manage → API Keys
2. API-Key bearbeiten
3. Haken bei "Subtickets verwalten" setzen
4. Speichern

### "Child already has a different parent" (409)

**Ursache:** Child-Ticket ist bereits mit anderem Parent verknüpft

**Lösung:**
1. Zuerst unlinken: `DELETE /api/tickets-subtickets-unlink.php`
2. Dann neu verknüpfen: `POST /api/tickets-subtickets-create.php`

## Support

- GitHub Issues: https://github.com/markus-michalski/osticket-api-endpoints/issues
- Ticket System: #904125
