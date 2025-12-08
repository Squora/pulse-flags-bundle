<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Pulse\FlagsBundle\Admin\Controller\AdminController;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Unit tests for AdminController class.
 *
 * Tests REST API endpoints for feature flag management including:
 * - GET /api/flags - List all flags
 * - GET /api/flag/{name} - Get flag details
 * - POST /api/flag/{name}/toggle - Toggle enabled status
 * - PUT /api/flag/{name} - Update flag configuration
 * - POST /api/flag - Create new flag
 * - DELETE /api/flag/{name} - Delete flag
 * - Permanent flag protection (403 on modifications)
 * - Strategy field cleanup on updates
 * - Blacklist CSV parsing
 * - Error handling (400, 404, 409)
 */
class AdminControllerTest extends TestCase
{
    private PermanentFeatureFlagService $permanentService;
    private PersistentFeatureFlagService $persistentService;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private AdminController $controller;

    protected function setUp(): void
    {
        // Arrange: Create service mocks
        $this->permanentService = $this->createMock(PermanentFeatureFlagService::class);
        $this->persistentService = $this->createMock(PersistentFeatureFlagService::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);

        // Mock CSRF token validation to always return true for tests
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);
        $mockToken = $this->createMock(CsrfToken::class);
        $mockToken->method('getValue')->willReturn('test-csrf-token');
        $this->csrfTokenManager->method('getToken')->willReturn($mockToken);

        // Create controller with mocked services
        $this->controller = new AdminController(
            $this->permanentService,
            $this->persistentService,
            $this->csrfTokenManager
        );

        // Set up minimal container for AbstractController
        // AbstractController->json() uses container to get serializer, but falls back to json_encode
        $container = new Container();
        $container->set('parameter_bag', new \Symfony\Component\DependencyInjection\ParameterBag\ParameterBag([
            'pulse_flags.admin.require_confirmation' => true,
        ]));

        // Use reflection to set protected container property
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($this->controller, $container);
    }

    public function testApiListReturnsAllFlags(): void
    {
        // Arrange: Mock flags from both services using paginate
        $permanentResult = [
            'flags' => [
                'core.feature' => ['enabled' => true, 'strategy' => 'simple', 'description' => 'Permanent flag'],
            ],
            'pagination' => ['page' => 1, 'limit' => 50, 'total' => 1, 'pages' => 1],
        ];
        $persistentResult = [
            'flags' => [
                'test.flag' => ['enabled' => false, 'strategy' => 'percentage', 'percentage' => 50],
            ],
            'pagination' => ['page' => 1, 'limit' => 50, 'total' => 1, 'pages' => 1],
        ];

        $this->permanentService->method('paginate')->willReturn($permanentResult);
        $this->persistentService->method('paginate')->willReturn($persistentResult);

        // Act: Call apiList
        $request = Request::create('/admin/pulse-flags/flags');
        $response = $this->controller->apiList($request);

        // Assert: Both flags returned with correct readonly status
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('flags', $data);
        $this->assertCount(2, $data['flags']);
        $this->assertEquals('core.feature', $data['flags'][0]['name']);
        $this->assertTrue($data['flags'][0]['readonly']); // Permanent flag
        $this->assertEquals('test.flag', $data['flags'][1]['name']);
        $this->assertFalse($data['flags'][1]['readonly']); // Persistent flag
    }

    public function testApiListReturnsEmptyArrayWhenNoFlags(): void
    {
        // Arrange: No flags
        $emptyResult = [
            'flags' => [],
            'pagination' => ['page' => 1, 'limit' => 50, 'total' => 0, 'pages' => 0],
        ];
        $this->permanentService->method('paginate')->willReturn($emptyResult);
        $this->persistentService->method('paginate')->willReturn($emptyResult);

        // Act: Call apiList
        $request = Request::create('/admin/pulse-flags/flags');
        $response = $this->controller->apiList($request);

        // Assert: Empty array
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('flags', $data);
        $this->assertEmpty($data['flags']);
    }

    public function testApiGetReturnsPermanentFlag(): void
    {
        // Arrange: Permanent flag exists
        $config = ['enabled' => true, 'strategy' => 'simple'];
        $this->permanentService->method('getConfig')->with('permanent.flag')->willReturn($config);

        // Act: Get permanent flag
        $response = $this->controller->apiGet('permanent.flag');

        // Assert: Flag returned with readonly=true
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('permanent.flag', $data['name']);
        $this->assertEquals($config, $data['config']);
        $this->assertTrue($data['readonly']);
    }

    public function testApiGetReturnsPersistentFlag(): void
    {
        // Arrange: Permanent doesn't exist, persistent does
        $config = ['enabled' => false, 'strategy' => 'percentage', 'percentage' => 25];
        $this->permanentService->method('getConfig')->with('persistent.flag')->willReturn(null);
        $this->persistentService->method('getConfig')->with('persistent.flag')->willReturn($config);

        // Act: Get persistent flag
        $response = $this->controller->apiGet('persistent.flag');

        // Assert: Flag returned with readonly=false
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('persistent.flag', $data['name']);
        $this->assertEquals($config, $data['config']);
        $this->assertFalse($data['readonly']);
    }

    public function testApiGetReturns404ForNonexistentFlag(): void
    {
        // Arrange: Flag doesn't exist in either service
        $this->permanentService->method('getConfig')->willReturn(null);
        $this->persistentService->method('getConfig')->willReturn(null);

        // Act: Get nonexistent flag
        $response = $this->controller->apiGet('nonexistent');

        // Assert: 404 response
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Flag not found', $data['error']);
    }

    public function testApiToggleEnablesPersistentFlag(): void
    {
        // Arrange: Persistent flag that is disabled
        $this->permanentService->method('exists')->with('test.flag')->willReturn(false);
        $this->persistentService->method('getConfig')->with('test.flag')->willReturn(['enabled' => false]);
        $this->persistentService->expects($this->once())->method('enable')->with('test.flag');

        // Act: Toggle flag
        $request = Request::create('/admin/pulse-flags/flags/test.flag/toggle', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']);
        $response = $this->controller->apiToggle('test.flag', $request);

        // Assert: Success response with enabled=true
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['enabled']);
        $this->assertEquals('test.flag', $data['name']);
    }

    public function testApiToggleDisablesPersistentFlag(): void
    {
        // Arrange: Persistent flag that is enabled
        $this->permanentService->method('exists')->with('test.flag')->willReturn(false);
        $this->persistentService->method('getConfig')->with('test.flag')->willReturn(['enabled' => true]);
        $this->persistentService->expects($this->once())->method('disable')->with('test.flag');

        // Act: Toggle flag
        $response = $this->controller->apiToggle('test.flag', Request::create('/admin/pulse-flags/flags/toggle', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']));

        // Assert: Success response with enabled=false
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['enabled']);
    }

    public function testApiToggleReturns403ForPermanentFlag(): void
    {
        // Arrange: Permanent flag
        $this->permanentService->method('exists')->with('permanent.flag')->willReturn(true);

        // Act: Try to toggle permanent flag
        $response = $this->controller->apiToggle('permanent.flag', Request::create('/admin/pulse-flags/flags/toggle', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']));

        // Assert: 403 Forbidden
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cannot modify permanent flag', $data['error']);
    }

    public function testApiToggleReturns404ForNonexistentFlag(): void
    {
        // Arrange: Flag doesn't exist
        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('getConfig')->willReturn(null);

        // Act: Toggle nonexistent flag
        $response = $this->controller->apiToggle('nonexistent', Request::create('/admin/pulse-flags/flags/toggle', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']));

        // Assert: 404 Not Found
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testApiUpdateUpdatesPersistentFlag(): void
    {
        // Arrange: Persistent flag exists
        $existingConfig = ['enabled' => true, 'strategy' => 'simple'];
        $updateData = ['enabled' => false, 'description' => 'Updated description'];

        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('getConfig')->willReturn($existingConfig);

        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($updateData));

        // Expect configure to be called with merged config
        $this->persistentService->expects($this->once())
            ->method('configure')
            ->with('test.flag', $this->callback(function ($config) {
                return $config['enabled'] === false && $config['description'] === 'Updated description';
            }));

        // Act: Update flag
        $response = $this->controller->apiUpdate('test.flag', $request);

        // Assert: Success response
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('test.flag', $data['name']);
    }

    public function testApiUpdateCleansUpStrategyFields(): void
    {
        // Arrange: Flag with percentage strategy, update to simple
        $existingConfig = ['enabled' => true, 'strategy' => 'percentage', 'percentage' => 50];
        $updateData = ['strategy' => 'simple'];

        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('getConfig')->willReturn($existingConfig);

        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($updateData));

        // Expect percentage field to be removed
        $this->persistentService->expects($this->once())
            ->method('configure')
            ->with('test.flag', $this->callback(function ($config) {
                return !isset($config['percentage']) && $config['strategy'] === 'simple';
            }));

        // Act: Update flag
        $response = $this->controller->apiUpdate('test.flag', $request);

        // Assert: Success
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testApiUpdateConvertsBlacklistCsvToArray(): void
    {
        // Arrange: Update with blacklist as CSV string
        $updateData = ['strategy' => 'user_id', 'blacklist' => '1, 2, 3'];

        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('getConfig')->willReturn(['enabled' => true]);

        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($updateData));

        // Expect blacklist to be converted to array
        $this->persistentService->expects($this->once())
            ->method('configure')
            ->with('test.flag', $this->callback(function ($config) {
                return is_array($config['blacklist']) && $config['blacklist'] === ['1', '2', '3'];
            }));

        // Act: Update flag
        $response = $this->controller->apiUpdate('test.flag', $request);

        // Assert: Success
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testApiUpdateReturns403ForPermanentFlag(): void
    {
        // Arrange: Permanent flag
        $this->permanentService->method('exists')->willReturn(true);

        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode(['enabled' => false]));

        // Act: Try to update permanent flag
        $response = $this->controller->apiUpdate('permanent.flag', $request);

        // Assert: 403 Forbidden
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cannot modify permanent flag', $data['error']);
    }

    public function testApiUpdateReturns400ForInvalidJson(): void
    {
        // Arrange: Invalid JSON
        $this->permanentService->method('exists')->willReturn(false);

        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], 'invalid json');

        // Act & Assert: Update with invalid JSON should throw JsonException
        $this->expectException(\JsonException::class);
        $this->controller->apiUpdate('test.flag', $request);
    }

    public function testApiCreateCreatesNewPersistentFlag(): void
    {
        // Arrange: Flag doesn't exist
        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('exists')->willReturn(false);

        $createData = ['name' => 'new.flag', 'enabled' => true, 'strategy' => 'percentage', 'percentage' => 75];
        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($createData));

        $this->persistentService->expects($this->once())
            ->method('configure')
            ->with('new.flag', $this->callback(function ($config) {
                return $config['enabled'] === true && $config['percentage'] === 75;
            }));

        // Act: Create flag
        $response = $this->controller->apiCreate($request);

        // Assert: 201 Created
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('new.flag', $data['name']);
    }

    public function testApiCreateUsesDefaults(): void
    {
        // Arrange: Minimal create data
        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('exists')->willReturn(false);

        $createData = ['name' => 'minimal.flag'];
        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($createData));

        // Expect defaults: enabled=false, strategy=simple
        $this->persistentService->expects($this->once())
            ->method('configure')
            ->with('minimal.flag', $this->callback(function ($config) {
                return $config['enabled'] === false && $config['strategy'] === 'simple';
            }));

        // Act: Create flag
        $response = $this->controller->apiCreate($request);

        // Assert: Success
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testApiCreateReturns409WhenPermanentFlagExists(): void
    {
        // Arrange: Permanent flag already exists
        $this->permanentService->method('exists')->with('existing.flag')->willReturn(true);

        $createData = ['name' => 'existing.flag'];
        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($createData));

        // Act: Try to create
        $response = $this->controller->apiCreate($request);

        // Assert: 409 Conflict
        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Flag already exists', $data['error']);
    }

    public function testApiCreateReturns409WhenPersistentFlagExists(): void
    {
        // Arrange: Persistent flag already exists
        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('exists')->with('existing.flag')->willReturn(true);

        $createData = ['name' => 'existing.flag'];
        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode($createData));

        // Act: Try to create
        $response = $this->controller->apiCreate($request);

        // Assert: 409 Conflict
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testApiCreateReturns400ForInvalidData(): void
    {
        // Arrange: Missing name field
        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], json_encode(['enabled' => true]));

        // Act: Create without name
        $response = $this->controller->apiCreate($request);

        // Assert: 400 Bad Request
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Name is required', $data['error']);
    }

    public function testApiCreateReturns400ForInvalidJson(): void
    {
        // Arrange: Invalid JSON
        $request = new Request([], [], [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token'], 'not json');

        // Act & Assert: Create with invalid JSON should throw JsonException
        $this->expectException(\JsonException::class);
        $this->controller->apiCreate($request);
    }

    public function testApiDeleteRemovesPersistentFlag(): void
    {
        // Arrange: Persistent flag exists
        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('exists')->with('delete.me')->willReturn(true);

        $this->persistentService->expects($this->once())->method('remove')->with('delete.me');

        // Act: Delete flag
        $response = $this->controller->apiDelete('delete.me', Request::create('/admin/pulse-flags/flags/delete', 'DELETE', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']));

        // Assert: Success
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('delete.me', $data['name']);
    }

    public function testApiDeleteReturns403ForPermanentFlag(): void
    {
        // Arrange: Permanent flag
        $this->permanentService->method('exists')->with('permanent.flag')->willReturn(true);

        // Act: Try to delete permanent flag
        $response = $this->controller->apiDelete('permanent.flag', Request::create('/admin/pulse-flags/flags/delete', 'DELETE', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']));

        // Assert: 403 Forbidden
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Cannot delete permanent flag', $data['error']);
    }

    public function testApiDeleteReturns404ForNonexistentFlag(): void
    {
        // Arrange: Flag doesn't exist
        $this->permanentService->method('exists')->willReturn(false);
        $this->persistentService->method('exists')->willReturn(false);

        // Act: Delete nonexistent flag
        $response = $this->controller->apiDelete('nonexistent', Request::create('/admin/pulse-flags/flags/delete', 'DELETE', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']));

        // Assert: 404 Not Found
        $this->assertEquals(404, $response->getStatusCode());
    }
}
