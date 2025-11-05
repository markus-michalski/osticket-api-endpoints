# Integration Tests for API Endpoints

Diese Integration Tests decken die beiden neuen API Endpoints ab:

## 1. GET /api/tickets-get.php/{number}.json

**Test-Datei:** `GetTicketEndpointTest.php`

**Abgedeckte Szenarien:**

- Successful ticket retrieval by NUMBER (primary lookup)
- Ticket retrieval by ID (fallback wenn lookupByNumber() fehlschlägt)
- 404 error wenn Ticket nicht gefunden wird
- 401 error wenn API-Key keine READ permission hat
- Backward compatibility mit canCreateTickets() permission
- Thread entries werden korrekt inkludiert (WICHTIG: kein getFormat() call!)
- Tickets mit allen optionalen Feldern (staff, team, SLA, etc.)
- Tickets ohne Thread (empty array)
- Tickets mit internal notes (type = 'N')
- Lookup priority: Number vor ID

**Gefundene Bugs die verhindert werden:**

- Falscher Lookup (lookup() statt lookupByNumber())
- Non-existent getFormat() call auf ThreadEntry

## 2. PATCH/PUT /api/tickets-update.php/{number}.json

**Test-Datei:** `UpdateTicketEndpointTest.php`

**Abgedeckte Szenarien:**

- Department update mit Validation (inactive departments rejected)
- Status update mit Validation
- Help Topic update mit Validation (inactive topics rejected)
- Priority update mit Validation
- SLA assignment mit Validation (inactive SLAs rejected)
- Staff assignment mit Validation (inactive staff rejected)
- Parent ticket assignment (subticket creation)
- Parent validation (parent kann nicht selbst child sein)
- 404 error wenn Ticket nicht gefunden wird
- 401 error wenn API-Key keine UPDATE permission hat
- Backward compatibility mit canCreateTickets() permission
- Multiple properties gleichzeitig updaten
- Idempotency (update mit gleichem Wert schlägt nicht fehl)
- Parent ticket lookup: Number vor ID

## Test-Infrastruktur

Die Tests nutzen Mock-Klassen aus `tests/bootstrap.php`:

- `Ticket` mit vollständigen Mock-Daten
- `API` mit can_read_tickets und can_update_tickets permissions
- `Dept`, `Topic`, `Priority`, `TicketStatus`, `SLA`, `Staff`, `Team`
- `Thread` und `ThreadEntry` für Message-Tests

## Tests ausführen

```bash
# Nur Integration Tests
composer test:integration

# Alle Tests (Unit + Integration)
composer test

# Mit Coverage Report
composer test:coverage
```

## Test-Statistiken

- **GET Endpoint:** 10 Tests, 55 Assertions
- **UPDATE Endpoint:** 21 Tests, 69 Assertions
- **Gesamt:** 31 Tests, 124 Assertions
- **Status:** ✅ Alle Tests grün

## Wichtige Erkenntnisse aus den Tests

### 1. Lookup Priority ist kritisch

Beide Endpoints müssen ZUERST `lookupByNumber()` verwenden und nur als Fallback `lookup()` by ID nutzen. Dies ist wichtig weil Ticket-Numbers wie "123" auch als IDs interpretiert werden könnten.

### 2. ThreadEntry hat KEIN getFormat()

Ein häufiger Bug war der Aufruf von `$entry->getFormat()` auf ThreadEntry-Objekten. Diese Methode existiert nicht! Nur das Format-Attribut im Body könnte vorhanden sein.

### 3. Permission Fallback

Die Endpoints unterstützen zwei Permission-Arten:
- Neu: `can_read_tickets` und `can_update_tickets` (direkte Properties)
- Alt: `canCreateTickets()` (backward compatibility)

### 4. Parent Ticket ID vs Number

Bei parent ticket assignments ist wichtig:
- User gibt TICKET NUMBER an (z.B. "500500")
- Controller muss diese zur INTERNEN ID konvertieren (z.B. 500)
- `setPid()` erwartet die INTERNE ID, nicht die Number!

### 5. Validation ist streng

Alle Validierungen prüfen:
- Existenz des Objekts (404 wenn nicht gefunden)
- Active-Status bei Dept, Topic, SLA, Staff (400 wenn inactive)
- Spezielle Regeln (z.B. parent kann nicht selbst child sein)

## Wartung der Tests

Bei Änderungen am Controller:

1. Zuerst Tests erweitern/anpassen (TDD!)
2. Tests müssen ROT sein (failing)
3. Code anpassen bis Tests GRÜN sind
4. Refactoring mit grünen Tests als Safety Net
