<?php
/**
 * Test Data Factory for Integration Tests
 *
 * Provides reusable factory methods for:
 * - API keys (with/without permissions)
 * - Tickets (parent/child configurations)
 * - Mock plugin instances
 * - Department restrictions
 *
 * Usage:
 *   $apiKey = SubticketTestDataFactory::createApiKeyWithPermission();
 *   $ticket = SubticketTestDataFactory::createTicket(['subject' => 'Test']);
 *   $plugin = SubticketTestDataFactory::createActivePlugin();
 */
class SubticketTestDataFactory {

    /**
     * Counter for unique ticket IDs
     * @var int
     */
    private static $ticketCounter = 1000;

    /**
     * Counter for unique API key identifiers
     * @var int
     */
    private static $apiKeyCounter = 1;

    /**
     * Reset all counters (useful for test isolation)
     */
    public static function reset(): void {
        self::$ticketCounter = 1000;
        self::$apiKeyCounter = 1;
    }

    /**
     * Create API key WITH subticket permission
     *
     * @return API API key with can_manage_subtickets enabled
     */
    public static function createApiKeyWithPermission(): API {
        self::$apiKeyCounter++;

        $key = new API([
            'id' => self::$apiKeyCounter,
            'key' => sprintf('integration-test-key-%d', self::$apiKeyCounter),
            'isactive' => true,
            'can_create_tickets' => true,
            'can_manage_subtickets' => true,
        ]);

        // Register in mock data
        API::$mockData[$key->getId()] = $key;

        return $key;
    }

    /**
     * Create API key WITHOUT subticket permission
     *
     * @return API API key with can_manage_subtickets disabled
     */
    public static function createApiKeyWithoutPermission(): API {
        self::$apiKeyCounter++;

        $key = new API([
            'id' => self::$apiKeyCounter,
            'key' => sprintf('no-permission-key-%d', self::$apiKeyCounter),
            'isactive' => true,
            'can_create_tickets' => true,
            'can_manage_subtickets' => false,
        ]);

        API::$mockData[$key->getId()] = $key;

        return $key;
    }

    /**
     * Create API key with department restrictions
     *
     * @param array $allowedDepartments Array of department IDs [1, 3]
     * @return API API key with department restrictions
     */
    public static function createApiKeyWithDepartmentRestrictions(array $allowedDepartments): API {
        self::$apiKeyCounter++;

        $key = new API([
            'id' => self::$apiKeyCounter,
            'key' => sprintf('dept-restricted-key-%d', self::$apiKeyCounter),
            'isactive' => true,
            'can_manage_subtickets' => true,
            'restricted_departments' => $allowedDepartments,
        ]);

        API::$mockData[$key->getId()] = $key;

        return $key;
    }

    /**
     * Create active subticket plugin mock
     *
     * This plugin maintains parent-child relationships in memory and provides
     * all methods required by SubticketApiController.
     *
     * @return object Active plugin instance with relationship tracking
     */
    public static function createActivePlugin(): object {
        return new class {
            /**
             * Relationship storage: childId => parentId
             * @var array
             */
            private $relationships = [];

            /**
             * Check if plugin is active
             * @return bool Always true for active plugin
             */
            public function isActive(): bool {
                return true;
            }

            /**
             * Get parent ticket of a child
             *
             * @param Ticket $child Child ticket
             * @return Ticket|null Parent ticket or null if no parent
             */
            public function getParent(Ticket $child): ?Ticket {
                $parentId = $this->relationships[$child->getId()] ?? null;
                return $parentId ? Ticket::lookup($parentId) : null;
            }

            /**
             * Get list of child ticket IDs for a parent
             *
             * @param Ticket $parent Parent ticket
             * @return array Array of child ticket IDs
             */
            public function getChildren(Ticket $parent): array {
                $childIds = [];
                foreach ($this->relationships as $childId => $parentId) {
                    if ($parentId === $parent->getId()) {
                        $childIds[] = $childId;
                    }
                }
                return $childIds;
            }

            /**
             * Create parent-child relationship
             *
             * @param Ticket $parent Parent ticket
             * @param Ticket $child Child ticket
             */
            public function createLink(Ticket $parent, Ticket $child): void {
                $this->relationships[$child->getId()] = $parent->getId();
            }

            /**
             * Check if ticket has a parent
             *
             * @param Ticket $child Child ticket
             * @return bool True if child has parent
             */
            public function hasParent(Ticket $child): bool {
                return isset($this->relationships[$child->getId()]);
            }

            /**
             * Remove parent-child relationship
             *
             * @param Ticket $child Child ticket to unlink
             */
            public function removeLink(Ticket $child): void {
                unset($this->relationships[$child->getId()]);
            }

            /**
             * Get all relationships (for debugging)
             *
             * @return array All relationships [childId => parentId]
             */
            public function getRelationships(): array {
                return $this->relationships;
            }
        };
    }

    /**
     * Create inactive subticket plugin mock
     *
     * @return object Inactive plugin instance
     */
    public static function createInactivePlugin(): object {
        return new class {
            public function isActive(): bool {
                return false;
            }
        };
    }

    /**
     * Create ticket for testing
     *
     * @param array $overrides Override default values ['id' => 1, 'subject' => 'Test', 'dept_id' => 1]
     * @return Ticket Ticket instance registered in mock data
     */
    public static function createTicket(array $overrides = []): Ticket {
        self::$ticketCounter++;

        $defaults = [
            'ticket_id' => self::$ticketCounter,
            'number' => sprintf('T-%06d', self::$ticketCounter),
            'subject' => 'Integration Test Ticket',
            'dept_id' => 1,
            'status_id' => 1,
        ];

        $data = array_merge($defaults, $overrides);
        $ticket = new Ticket($data);

        // Store in mock registry
        Ticket::$mockData[$ticket->getId()] = $ticket;
        Ticket::$mockDataByNumber[$ticket->getNumber()] = $ticket;

        return $ticket;
    }

    /**
     * Create ticket with status object
     *
     * @param string $statusName Status name (e.g., 'Open', 'Closed')
     * @param array $overrides Additional ticket data overrides
     * @return Ticket Ticket with status object attached
     */
    public static function createTicketWithStatus(string $statusName = 'Open', array $overrides = []): Ticket {
        // Create status object
        $statusId = count(TicketStatus::$mockData) + 1;
        $status = new TicketStatus([
            'id' => $statusId,
            'name' => $statusName,
            'state' => 'open',
        ]);
        TicketStatus::$mockData[$statusId] = $status;

        // Create ticket with status
        $ticket = self::createTicket(array_merge($overrides, [
            'status_id' => $statusId,
        ]));

        return $ticket;
    }

    /**
     * Create parent-child relationship (pre-configured)
     *
     * Creates two tickets and links them via the plugin.
     *
     * @param object|null $plugin Plugin instance (or null to create new)
     * @return array ['parent' => Ticket, 'child' => Ticket, 'plugin' => object]
     */
    public static function createLinkedPair(?object $plugin = null): array {
        if ($plugin === null) {
            $plugin = self::createActivePlugin();
        }

        $parent = self::createTicket(['subject' => 'Parent Ticket']);
        $child = self::createTicket(['subject' => 'Child Ticket']);

        // Create link
        $plugin->createLink($parent, $child);

        return [
            'parent' => $parent,
            'child' => $child,
            'plugin' => $plugin,
        ];
    }

    /**
     * Create parent with multiple children (pre-configured)
     *
     * @param int $childCount Number of children to create
     * @param object|null $plugin Plugin instance (or null to create new)
     * @return array ['parent' => Ticket, 'children' => [Ticket, ...], 'plugin' => object]
     */
    public static function createParentWithChildren(int $childCount = 3, ?object $plugin = null): array {
        if ($plugin === null) {
            $plugin = self::createActivePlugin();
        }

        $parent = self::createTicket(['subject' => 'Parent Ticket']);
        $children = [];

        for ($i = 1; $i <= $childCount; $i++) {
            $child = self::createTicket(['subject' => sprintf('Child Ticket %d', $i)]);
            $plugin->createLink($parent, $child);
            $children[] = $child;
        }

        return [
            'parent' => $parent,
            'children' => $children,
            'plugin' => $plugin,
        ];
    }
}
