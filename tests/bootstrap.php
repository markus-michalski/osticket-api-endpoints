<?php
/**
 * PHPUnit Bootstrap for API-Endpoints Plugin
 *
 * Provides minimal osTicket mocks for testing without full osTicket installation
 */

// Prevent actual osTicket loading
define('INCLUDE_DIR', __DIR__ . '/../');

// Mock osTicket classes
if (!class_exists('Plugin')) {
    class Plugin {
        public function getName() { return 'API-Endpoints'; }
        public function getConfig() { return new ApiEndpointsConfig(); }
        public function isSingleton() { return true; }
        public function getNumInstances() { return 0; }
        public function addInstance($vars, &$errors) { return true; }
    }
}

if (!class_exists('PluginConfig')) {
    class PluginConfig {
        private $data = [];
        public function get($key) { return $this->data[$key] ?? null; }
        public function set($key, $value) { $this->data[$key] = $value; }
        public function delete() { $this->data = []; }
    }
}

if (!class_exists('PluginManager')) {
    class PluginManager {
        private static $instance;
        private $plugins = [];

        public static function getInstance() {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function getPlugin($id) {
            return $this->plugins[$id] ?? null;
        }

        public function registerPlugin($id, $plugin) {
            $this->plugins[$id] = $plugin;
        }
    }
}

if (!class_exists('Dept')) {
    class Dept {
        public static $mockData = [];
        private $id;
        private $name;
        private $active;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
            $this->active = $data['active'] ?? true;
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
        public function isActive() { return $this->active; }
    }
}

// Mock QuerySet for Ticket::objects()
if (!class_exists('MockTicketQuerySet')) {
    class MockTicketQuerySet implements IteratorAggregate {
        private $data;
        private $filters = [];
        private $orderBy = null;
        private $limitCount = null;
        private $offsetCount = 0;

        public function __construct($data) {
            $this->data = $data;
        }

        public function filter($conditions) {
            $this->filters = array_merge($this->filters, $conditions);
            return $this;
        }

        public function order_by($field) {
            $this->orderBy = $field;
            return $this;
        }

        public function limit($limit) {
            $this->limitCount = $limit;
            return $this;
        }

        public function offset($offset) {
            $this->offsetCount = $offset;
            return $this;
        }

        public function getIterator(): Traversable {
            $results = $this->data;

            // Apply filters
            foreach ($this->filters as $key => $value) {
                if ($key === 'cdata__subject__contains') {
                    $results = array_filter($results, function($ticket) use ($value) {
                        return stripos($ticket->getSubject(), $value) !== false;
                    });
                } elseif ($key === 'status_id') {
                    $results = array_filter($results, function($ticket) use ($value) {
                        return $ticket->getStatusId() === $value;
                    });
                } elseif ($key === 'dept_id') {
                    $results = array_filter($results, function($ticket) use ($value) {
                        return $ticket->getDeptId() === $value;
                    });
                }
            }

            // Apply sorting
            if ($this->orderBy) {
                $results = array_values($results); // Re-index
                usort($results, function($a, $b) {
                    switch ($this->orderBy) {
                        case '-updated':
                            return strcmp($b->getUpdateDate(), $a->getUpdateDate());
                        case 'number':
                            return strcmp($a->getNumber(), $b->getNumber());
                        case '-created':
                        default:
                            return strcmp($b->getCreateDate(), $a->getCreateDate());
                    }
                });
            }

            // Apply pagination
            $results = array_slice($results, $this->offsetCount, $this->limitCount);

            return new ArrayIterator($results);
        }
    }
}

if (!class_exists('Ticket')) {
    class Ticket {
        public static $mockData = [];
        public static $mockDataByNumber = [];

        public static function objects() {
            return new MockTicketQuerySet(array_values(self::$mockData));
        }
        private $id;
        private $number;
        private $subject;
        private $deptId;
        private $statusId;
        private $priorityId;
        private $topicId;
        private $userId;
        private $staffId;
        private $teamId;
        private $slaId;
        private $pid;
        private $isChild;
        private $created;
        private $updated;
        private $duedate;
        private $closed;
        private $isOverdue;
        private $isAnswered;
        private $source;
        private $ip;
        private $thread;
        private $dept;
        private $status;
        private $priority;
        private $topic;
        private $staff;
        private $team;
        private $sla;
        private $userName;
        private $userEmail;
        private $customData;
        public $ht = [];

        /**
         * Magic setter to emulate VerySimpleModel ORM behavior
         * Allows setting private properties like $ticket->duedate = $value
         */
        public function __set($name, $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }

        /**
         * Magic getter to emulate VerySimpleModel ORM behavior
         */
        public function __get($name) {
            if (property_exists($this, $name)) {
                return $this->$name;
            }
            return null;
        }

        public function __construct($data) {
            $this->id = $data['ticket_id'] ?? $data['id'];
            $this->number = $data['number'];
            $this->subject = $data['subject'] ?? 'Test Ticket';
            $this->deptId = $data['dept_id'] ?? 1;
            $this->statusId = $data['status_id'] ?? 1;
            $this->priorityId = $data['priority_id'] ?? 2;
            $this->topicId = $data['topic_id'] ?? 1;
            $this->userId = $data['user_id'] ?? 1;
            $this->staffId = $data['staff_id'] ?? null;
            $this->teamId = $data['team_id'] ?? null;
            $this->slaId = $data['sla_id'] ?? null;
            $this->pid = $data['pid'] ?? null;
            $this->isChild = $data['is_child'] ?? false;
            $this->created = $data['created'] ?? date('Y-m-d H:i:s');
            $this->updated = $data['updated'] ?? date('Y-m-d H:i:s');
            $this->duedate = $data['duedate'] ?? null;
            $this->closed = $data['closed'] ?? null;
            $this->isOverdue = $data['is_overdue'] ?? false;
            $this->isAnswered = $data['is_answered'] ?? false;
            $this->source = $data['source'] ?? 'API';
            $this->ip = $data['ip'] ?? '127.0.0.1';
            $this->thread = $data['thread'] ?? null;
            $this->dept = $data['dept'] ?? null;
            $this->status = $data['status'] ?? null;
            $this->priority = $data['priority'] ?? null;
            $this->topic = $data['topic'] ?? null;
            $this->staff = $data['staff'] ?? null;
            $this->team = $data['team'] ?? null;
            $this->sla = $data['sla'] ?? null;
            $this->userName = $data['user_name'] ?? 'Test User';
            $this->userEmail = $data['user_email'] ?? 'test@example.com';
            $this->customData = $data['custom_data'] ?? null;

            // Auto-load status from status_id if not explicitly provided
            if (!$this->status && $this->statusId) {
                $this->status = TicketStatus::lookup($this->statusId);
            }

            // Auto-register in mockDataByNumber for lookupByNumber() support
            if ($this->number) {
                self::$mockDataByNumber[$this->number] = $this;
            }
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public static function lookupByNumber($number) {
            return self::$mockDataByNumber[$number] ?? null;
        }

        public function getId() { return $this->id; }
        public function getNumber() { return $this->number; }
        public function getSubject() { return $this->subject; }
        public function getDeptId() { return $this->deptId; }
        public function getStatusId() { return $this->statusId; }
        public function getPriorityId() { return $this->priorityId; }
        public function getTopicId() { return $this->topicId; }
        public function getUserId() { return $this->userId; }
        public function getStaffId() { return $this->staffId; }
        public function getTeamId() { return $this->teamId; }
        public function getSLAId() { return $this->slaId; }
        public function getPid() { return $this->pid; }
        public function isChild() { return $this->isChild; }
        public function getCreateDate() { return $this->created; }
        public function getCreated() { return $this->created; } // Alias for getCreateDate()
        public function getUpdateDate() { return $this->updated; }
        public function getDueDate() { return $this->duedate; }
        public function getCloseDate() { return $this->closed; }
        public function isClosed() { return $this->closed !== null; }
        public function isOverdue() { return $this->isOverdue; }
        public function clearOverdue($save = true) {
            $this->isOverdue = false;
            if ($save) {
                $this->save();
            }
        }
        public function isAnswered() { return $this->isAnswered; }
        public function getSource() { return $this->source; }
        public function getIP() { return $this->ip; }
        public function getName() { return $this->userName; }
        public function getEmail() { return $this->userEmail; }
        public function getThread() { return $this->thread; }
        public function getDept() { return $this->dept; }
        public function getStatus() { return $this->status; }
        public function getPriority() { return $this->priority; }
        public function getTopic() { return $this->topic; }
        public function getStaff() { return $this->staff; }
        public function getTeam() { return $this->team; }
        public function getSLA() { return $this->sla; }
        public function getCustomData() { return $this->customData; }

        public function setDeptId($deptId) {
            $this->deptId = $deptId;
            return true;
        }

        public function setTopicId($topicId) {
            $this->topicId = $topicId;
            return true;
        }

        public function setPid($pid) {
            $this->pid = $pid;
            return true;
        }

        public function setPriorityId($priorityId) {
            $this->priorityId = $priorityId;
            return true;
        }

        public function setStatus($statusId) {
            $this->statusId = $statusId;
            return true;
        }

        public function setSLAId($slaId) {
            $this->slaId = $slaId;
            return true;
        }

        public function setStaffId($staffId) {
            $this->staffId = $staffId;
            return true;
        }

        public function setDueDate($duedate) {
            $this->duedate = $duedate;
            return true;
        }

        public function save() {
            // Sync ht array changes to properties (for ExtendedTicketApiController updates)
            if (isset($this->ht['topic_id'])) {
                $this->topicId = $this->ht['topic_id'];
            }
            if (isset($this->ht['dept_id'])) {
                $this->deptId = $this->ht['dept_id'];
            }
            if (isset($this->ht['status_id'])) {
                $this->statusId = $this->ht['status_id'];
                // Also reload status object
                $this->status = TicketStatus::lookup($this->statusId);
            }
            if (isset($this->ht['priority_id'])) {
                $this->priorityId = $this->ht['priority_id'];
            }
            if (isset($this->ht['sla_id'])) {
                $this->slaId = $this->ht['sla_id'];
            }
            if (isset($this->ht['staff_id'])) {
                $this->staffId = $this->ht['staff_id'];
            }
            if (isset($this->ht['team_id'])) {
                $this->teamId = $this->ht['team_id'];
            }
            if (array_key_exists('duedate', $this->ht)) {
                $this->duedate = $this->ht['duedate'];
            }
            return true;
        }

        public function delete() {
            // Remove from mock storage
            unset(self::$mockData[$this->id]);
            unset(self::$mockDataByNumber[$this->number]);

            // Delete thread entries if exists
            if ($this->thread) {
                foreach ($this->thread->getEntries() as $entry) {
                    unset(ThreadEntry::$mockData[$entry->getId()]);
                }
            }

            // Remove ticket_pid from child tickets
            foreach (self::$mockData as $ticket) {
                if ($ticket->getPid() === $this->id) {
                    $ticket->setPid(null);
                }
            }

            return true;
        }

        // Mock methods for note posting
        public $noteWasPosted = false;
        public $lastNoteContent = null;
        public $lastNoteTitle = null;

        public function postNote($vars, &$errors, $poster = false, $alert = true) {
            $this->noteWasPosted = true;
            $this->lastNoteContent = $vars['note'] ?? null;
            $this->lastNoteTitle = $vars['title'] ?? 'API Update';
            return true; // Success
        }
    }
}

if (!class_exists('API')) {
    class API {
        public static $mockData = [];
        private $id;
        private $key;
        private $permissions = [];
        private $restrictedDepartments = [];
        public $ht = []; // Match osTicket's API class structure

        public function __construct($data) {
            $this->id = $data['id'] ?? null;
            $this->key = $data['key'];
            $this->permissions = $data['permissions'] ?? [];
            $this->restrictedDepartments = $data['restricted_departments'] ?? [];

            // Store permissions in ht array to match real osTicket API class
            // Check both $data and $this->permissions for consistency
            $this->ht = [
                'apikey' => $data['key'],
                'can_create_tickets' => $this->permissions['can_create_tickets'] ?? ($data['can_create_tickets'] ?? false),
                'can_read_tickets' => $this->permissions['can_read_tickets'] ?? ($data['can_read_tickets'] ?? false),
                'can_update_tickets' => $this->permissions['can_update_tickets'] ?? ($data['can_update_tickets'] ?? false),
                'can_search_tickets' => $this->permissions['can_search_tickets'] ?? ($data['can_search_tickets'] ?? false),
                'can_delete_tickets' => $this->permissions['can_delete_tickets'] ?? ($data['can_delete_tickets'] ?? false),
                'can_read_stats' => $this->permissions['can_read_stats'] ?? ($data['can_read_stats'] ?? false),
                'can_manage_subtickets' => $this->permissions['can_manage_subtickets'] ?? ($data['can_manage_subtickets'] ?? false),
            ];
        }

        public static function lookupKey($key) {
            return self::$mockData[$key] ?? null;
        }

        public function getId() { return $this->id; }
        public function getKey() { return $this->key; }
        public function canCreateTickets() {
            return $this->permissions['can_create_tickets'] ?? false;
        }
        public function getRestrictedDepartments() {
            return $this->restrictedDepartments;
        }
        public function hasRestrictedDepartments() {
            return !empty($this->restrictedDepartments);
        }
    }
}

// Mock Topic (Help Topic) class
if (!class_exists('Topic')) {
    class Topic {
        public static $mockData = [];
        private $id;
        private $name;
        private $active;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
            $this->active = $data['active'] ?? true;
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
        public function isActive() { return $this->active; }
    }
}

// Mock Priority class
if (!class_exists('Priority')) {
    class Priority {
        public static $mockData = [];
        private $id;
        private $name;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
        public function __toString() { return $this->name; }
    }
}

// Mock TicketStatus class
if (!class_exists('TicketStatus')) {
    class TicketStatus {
        public static $mockData = [];
        private $id;
        private $name;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
        public function __toString() { return $this->name; }
    }
}

// Mock SLA class
if (!class_exists('SLA')) {
    class SLA {
        public static $mockData = [];
        private $id;
        private $name;
        private $active;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
            $this->active = $data['active'] ?? true;
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
        public function isActive() { return $this->active; }
    }
}

// Mock Staff class
if (!class_exists('Staff')) {
    class Staff {
        public static $mockData = [];
        private $id;
        private $name;
        private $active;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
            $this->active = $data['active'] ?? true;
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
        public function isActive() { return $this->active; }
    }
}

// Mock Team class
if (!class_exists('Team')) {
    class Team {
        public static $mockData = [];
        private $id;
        private $name;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->name = $data['name'];
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getName() { return $this->name; }
    }
}

// Mock Thread class
if (!class_exists('Thread')) {
    class Thread {
        public static $mockData = [];
        private $id;
        private $entries = [];

        public function __construct($entries = [], $id = null) {
            $this->entries = $entries;
            $this->id = $id;
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }

        public function getEntries() {
            return $this->entries;
        }

        public function addEntry($entry) {
            $this->entries[] = $entry;
        }
    }
}

// Mock ThreadEntry class
if (!class_exists('ThreadEntry')) {
    class ThreadEntry {
        public static $mockData = [];
        private $id;
        private $type;
        private $poster;
        private $created;
        private $body;
        private $staffId;
        private $userId;
        private $staff;
        private $ticketId;

        public function __construct($data) {
            $this->id = $data['id'];
            $this->type = $data['type'] ?? 'M';
            $this->poster = $data['poster'] ?? 'User';
            $this->created = $data['created'] ?? date('Y-m-d H:i:s');
            $this->body = $data['body'] ?? '';
            $this->staffId = $data['staff_id'] ?? null;
            $this->userId = $data['user_id'] ?? null;
            $this->staff = $data['staff'] ?? null;
            $this->ticketId = $data['ticket_id'] ?? null;
        }

        public static function lookup($id) {
            return self::$mockData[$id] ?? null;
        }

        public function getId() { return $this->id; }
        public function getType() { return $this->type; }
        public function getPoster() { return $this->poster; }
        public function getCreateDate() { return $this->created; }
        public function getBody() { return $this->body; }
        public function getStaffId() { return $this->staffId; }
        public function getUserId() { return $this->userId; }
        public function getStaff() { return $this->staff; }
        public function getTicketId() { return $this->ticketId; }
    }
}

if (!class_exists('TicketApiController')) {
    require_once __DIR__ . '/../tests/fixtures/TicketApiControllerMock.php';
}

// Mock Signal class
if (!class_exists('Signal')) {
    class Signal {
        private static $handlers = [];

        public static function connect($signal, $callback) {
            if (!isset(self::$handlers[$signal])) {
                self::$handlers[$signal] = [];
            }
            self::$handlers[$signal][] = $callback;
        }

        public static function send($signal, ...$args) {
            if (isset(self::$handlers[$signal])) {
                foreach (self::$handlers[$signal] as $handler) {
                    call_user_func_array($handler, $args);
                }
            }
        }

        public static function reset() {
            self::$handlers = [];
        }
    }
}

// Mock SubticketPlugin class
if (!class_exists('SubticketPlugin')) {
    class SubticketPlugin {
        /**
         * Static flag to simulate plugin availability
         * Set to false in tests to simulate missing/inactive plugin
         */
        public static $mockEnabled = true;

        /**
         * Static array to track method calls for testing
         * Format: ['linkTicket' => [...], 'unlinkTicket' => [...]]
         */
        public static $callLog = [];

        /**
         * Constructor - throws exception if plugin is disabled
         */
        public function __construct() {
            if (!self::$mockEnabled) {
                throw new Exception('Subticket plugin not available', 501);
            }
        }

        /**
         * Reset call log (should be called in test tearDown)
         */
        public static function resetCallLog() {
            self::$callLog = [];
        }

        /**
         * Get parent ticket for a child
         *
         * @param int $childId Child ticket ID (internal ID, not number!)
         * @return array|null Parent ticket data or null
         */
        public function getParent($childId) {
            // Get child ticket
            $childTicket = Ticket::lookup($childId);
            if (!$childTicket || !$childTicket->getPid()) {
                return null;
            }

            // Get parent ticket
            $parentTicket = Ticket::lookup($childTicket->getPid());
            if (!$parentTicket) {
                return null;
            }

            return [
                'ticket_id' => $parentTicket->getId(),
                'number' => $parentTicket->getNumber(),
                'subject' => $parentTicket->getSubject(),
                'status' => $parentTicket->getStatus() ? $parentTicket->getStatus()->getName() : 'Open',
            ];
        }

        /**
         * Get children tickets for a parent
         *
         * @param int $parentId Parent ticket ID (internal ID, not number!)
         * @return array Array of child ticket data
         */
        public function getChildren($parentId) {
            $children = [];
            foreach (Ticket::$mockData as $ticket) {
                if ($ticket->getPid() === $parentId) {
                    $children[] = [
                        'id' => $ticket->getId(),
                        'number' => $ticket->getNumber(),
                        'subject' => $ticket->getSubject(),
                        'status' => $ticket->getStatus() ? $ticket->getStatus()->getName() : 'Open',
                        'created' => $ticket->getCreated(),
                    ];
                }
            }
            return $children;
        }

        /**
         * Link child ticket to parent
         *
         * @param int $childId Child ticket ID (internal ID!)
         * @param int $parentId Parent ticket ID (internal ID!)
         * @return bool Success
         */
        public function linkTicket($childId, $parentId) {
            // Track method call
            if (!isset(self::$callLog['linkTicket'])) {
                self::$callLog['linkTicket'] = [];
            }
            self::$callLog['linkTicket'][] = [
                'childId' => $childId,
                'parentId' => $parentId,
            ];

            $childTicket = Ticket::lookup($childId);
            if (!$childTicket) {
                return false;
            }

            $childTicket->setPid($parentId);
            return true;
        }

        /**
         * Unlink child ticket from parent
         *
         * @param int $childId Child ticket ID (internal ID!)
         * @return bool Success
         */
        public function unlinkTicket($childId) {
            // Track method call
            if (!isset(self::$callLog['unlinkTicket'])) {
                self::$callLog['unlinkTicket'] = [];
            }
            self::$callLog['unlinkTicket'][] = [
                'childId' => $childId,
            ];

            $childTicket = Ticket::lookup($childId);
            if (!$childTicket) {
                return false;
            }

            $childTicket->setPid(null);
            return true;
        }
    }
}

// Mock translation function
if (!function_exists('__')) {
    function __($text) {
        return $text;
    }
}

// Autoloader für Test-Classes
spl_autoload_register(function ($class) {
    $prefix = 'Tests\\';
    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load test fixtures
require_once __DIR__ . '/fixtures/SubticketTestDataFactory.php';

// Load controller classes
require_once __DIR__ . '/../controllers/ExtendedTicketApiController.php';
require_once __DIR__ . '/../controllers/SubticketApiController.php';
