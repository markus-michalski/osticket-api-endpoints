# Refactoring TODO: Legacy API Endpoints

## Hintergrund

Während der Implementation der Subticket API Endpoints (Phase 1-7) wurden Best Practices angewendet, die auch auf bestehende Endpoints angewendet werden sollten.

## Empfohlene Refactorings

### 1. JSON-Encoding standardisieren

**Affected Files:**
- `api/tickets-statuses.php`
- `api/tickets-stats.php`
- `api/tickets-search.php`
- `api/tickets-update.php`
- `api/tickets-get.php`
- `api/tickets-delete.php`
- `api/tickets-create.php`

**Change:**
```php
// OLD:
json_encode($result)

// NEW:
json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
```

**Reason:** Bessere Error-Handling und Unicode-Support

### 2. HTTP-Status-Code Validierung

**Change:**
```php
// OLD:
$code = $e->getCode() ?: 400;

// NEW:
$code = $e->getCode();
if ($code < 100 || $code > 599) {
    $code = 400;
}
```

**Reason:** Verhindert ungültige HTTP-Status-Codes

### 3. Deployment-System dynamisch machen

**Current:** Manuelles Hinzufügen jeder API-Datei in `class.ApiEndpointsPlugin.php`

**Proposed:** Automatisches Scannen des `api/` Verzeichnisses und dynamisches Deployment

**Benefits:**
- Weniger Code-Duplikation
- Einfacheres Hinzufügen neuer Endpoints
- Weniger Fehleranfälligkeit

**Implementation Idea:**
```php
public function deployApiFiles() {
    $apiDir = __DIR__ . '/api';
    $targetDir = INCLUDE_DIR . '../api';

    foreach (glob($apiDir . '/tickets-*.php') as $file) {
        $filename = basename($file);
        // Deploy + add RewriteRule automatically
    }
}
```

### 4. XmlHelper für bestehende Endpoints

Falls XML-Support auch für andere Endpoints gewünscht ist, kann die neue `lib/XmlHelper` Klasse wiederverwendet werden.

### 5. Type Hints und PHPDoc

Füge konsistente Type Hints und PHPDoc für alle API-Funktionen hinzu.

## Priorität

**LOW** - Bestehende Endpoints funktionieren korrekt und haben Tests. Refactoring ist "nice-to-have" für Code-Konsistenz, nicht dringend erforderlich.

## Empfohlener Ansatz

1. Separates Ticket/Issue erstellen
2. Einen Endpoint als Pilot refactoren
3. Template für andere Endpoints erstellen
4. Schrittweise alle Endpoints migrieren

## Related

- Ticket #904125 (Subticket API Endpoints Implementation)
- Code Review Findings (Phase 7)
