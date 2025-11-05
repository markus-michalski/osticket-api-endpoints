<?php
/**
 * Mock for TicketApiController
 */
class TicketApiController {
    protected $lastError = null;

    public function requireApiKey() {
        // Return mock API key
        if (isset(API::$mockData['test-key'])) {
            return API::$mockData['test-key'];
        }
        return null;
    }

    public function exerr($code, $message) {
        $this->lastError = ['code' => $code, 'message' => $message];
        throw new Exception($message, $code);
    }

    public function getRequest($format = 'json') {
        // Return $_POST data for tests
        return $_POST;
    }

    public function create($format) {
        // Parse data from $_POST
        $data = $_POST;

        // Simulate osTicket's create() which calls createTicket()
        $ticket = $this->createTicket($data, 'API');

        return $ticket;
    }

    public function createTicket($data, $source = 'API') {
        // Simulate parent::createTicket() behavior
        // This is called by extended controller's create()

        static $ticketIdCounter = 1000;
        $ticketId = $ticketIdCounter++;
        $ticketNumber = (string)$ticketId;

        // Simulate osTicket's behavior: Topic overrides department
        $deptId = isset($data['deptId']) ? $data['deptId'] : 1;

        // SIMULATE BUG: If topicId is provided, it overrides departmentId
        // Topic 10 routes to Department 1
        if (isset($data['topicId']) && $data['topicId'] == 10) {
            $deptId = 1;  // Force department 1 (this is what osTicket does)
        }

        // Create ticket
        $ticket = new Ticket([
            'id' => $ticketId,
            'number' => $ticketNumber,
            'subject' => $data['subject'] ?? 'Test Ticket',
            'dept_id' => $deptId,
            'status_id' => 1,
            'priority_id' => 2,
            'topic_id' => $data['topicId'] ?? null,
            'user_name' => $data['name'] ?? 'Test User',
            'user_email' => $data['email'] ?? 'test@example.com',
        ]);

        // Store in mock data
        Ticket::$mockData[$ticketId] = $ticket;
        Ticket::$mockDataByNumber[$ticketNumber] = $ticket;

        return $ticket;
    }

    public function getLastError() {
        return $this->lastError;
    }
}
