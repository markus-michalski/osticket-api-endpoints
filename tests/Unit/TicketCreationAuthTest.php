<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression: POST /tickets.json must authenticate before anything else
 *
 * The plugin's route replaces osTicket's own ticket-creation route and calls
 * create() with the key check skipped. handleTicketCreation() never checked
 * the key itself, so an unauthenticated request created a ticket, and its
 * validation errors told the caller which departments exist.
 */
class TicketCreationAuthTest extends TestCase {

    private ApiEndpointsPlugin $plugin;

    protected function setUp(): void {
        require_once __DIR__ . '/../../class.ApiEndpointsPlugin.php';

        API::$mockData = [];
        Ticket::$mockData = [];
        Dept::$mockData = [];
        Http::reset();
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];

        $this->plugin = new ApiEndpointsPlugin();
    }

    protected function tearDown(): void {
        API::$mockData = [];
        $_POST = [];
    }

    private function giveKey(bool $canCreate): void {
        API::$mockData['test-key'] = new API([
            'key' => 'test-key',
            'permissions' => ['can_create_tickets' => $canCreate],
        ]);
    }

    private function assertRejected(): void {
        try {
            $this->plugin->handleTicketCreation('json');
            $this->fail('Request without a valid key was not rejected');
        } catch (Exception $e) {
            $this->assertSame(401, $e->getCode());
        }
    }

    public function testRequestWithoutKeyCreatesNoTicket(): void {
        $_POST = ['subject' => 'x', 'name' => 'a', 'email' => 'a@example.com', 'message' => 'm'];

        $this->assertRejected();
        $this->assertCount(0, Ticket::$mockData);
    }

    public function testKeyWithoutCreatePermissionIsRejected(): void {
        $this->giveKey(false);
        $_POST = ['subject' => 'x', 'name' => 'a', 'email' => 'a@example.com', 'message' => 'm'];

        $this->assertRejected();
        $this->assertCount(0, Ticket::$mockData);
    }

    public function testValidationErrorsAreNotShownWithoutKey(): void {
        $_POST = ['subject' => 'x', 'departmentId' => 999];

        $this->assertRejected();
        $this->assertNull(Http::$lastResponse, 'A validation response leaked before authentication');
    }

    public function testValidKeyReachesValidation(): void {
        $this->giveKey(true);
        $_POST = ['subject' => 'x', 'departmentId' => 999];

        $this->plugin->handleTicketCreation('json');

        $this->assertSame(400, Http::$lastResponse['code']);
    }
}
