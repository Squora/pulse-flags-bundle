<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Admin\Controller;

use Exception;
use JsonException;
use Pulse\FlagsBundle\Constants\Pagination;
use Pulse\FlagsBundle\Enum\FlagStatus;
use Pulse\FlagsBundle\Enum\FlagStrategy;
use Pulse\FlagsBundle\Service\PermanentFeatureFlagService;
use Pulse\FlagsBundle\Service\PersistentFeatureFlagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Admin panel controller for feature flag management.
 *
 * Provides both web UI and REST API endpoints for managing feature flags.
 * Supports full CRUD operations on persistent flags while protecting
 * permanent (read-only) flags from modification.
 *
 * Routes:
 * - GET /admin/pulse-flags - Admin panel UI
 * - GET /admin/pulse-flags/flags - List all flags (JSON)
 * - GET /admin/pulse-flags/flags/{name} - Get flag details (JSON)
 * - POST /admin/pulse-flags/flags/{name}/toggle - Toggle flag enabled status
 * - PUT/PATCH /admin/pulse-flags/flags/{name} - Update flag configuration
 * - POST /admin/pulse-flags/flags - Create new flag
 * - DELETE /admin/pulse-flags/flags/{name} - Delete flag
 */
#[Route('/admin/pulse-flags', name: 'pulse_flags_admin_')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly PermanentFeatureFlagService $permanentFlagService,
        private readonly PersistentFeatureFlagService $persistentFlagService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    private function validateCsrfToken(Request $request): bool
    {
        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->get('_csrf_token')
            ?? $request->query->get('_csrf_token');

        if (!$token) {
            return false;
        }

        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken('pulse_flags_admin', (string) $token)
        );
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(Pagination::MIN_PAGE, (int) $request->query->get('page', (string)Pagination::DEFAULT_PAGE));
        $limit = min(Pagination::MAX_LIMIT, max(Pagination::MIN_LIMIT, (int) $request->query->get('limit', (string)Pagination::DEFAULT_LIMIT)));

        $permanentResult = $this->permanentFlagService->paginate($page, $limit);
        $persistentResult = $this->persistentFlagService->paginate($page, $limit);

        $transformedFlags = [];
        foreach ($permanentResult['flags'] as $name => $config) {
            $transformedFlags[] = [
                'name' => $name,
                'description' => $config['description'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'strategy' => $config['strategy'] ?? 'simple',
                'readonly' => true,
                'config' => $config,
            ];
        }

        foreach ($persistentResult['flags'] as $name => $config) {
            $transformedFlags[] = [
                'name' => $name,
                'description' => $config['description'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'strategy' => $config['strategy'] ?? 'simple',
                'readonly' => false,
                'config' => $config,
            ];
        }

        $totalFlags = $permanentResult['pagination']['total'] + $persistentResult['pagination']['total'];
        $totalPages = (int) ceil($totalFlags / $limit);

        $csrfToken = $this->csrfTokenManager->getToken('pulse_flags_admin')->getValue();

        return $this->render('@PulseFlagsAdmin/index.html.twig', [
            'flags_data' => $transformedFlags,
            'require_confirmation' => $this->getParameter('pulse_flags.admin.require_confirmation'),
            'csrf_token' => $csrfToken,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFlags,
                'pages' => $totalPages,
            ],
        ]);
    }

    #[Route('/flags', name: 'list', methods: ['GET'])]
    public function apiList(Request $request): JsonResponse
    {
        $page = max(Pagination::MIN_PAGE, (int) $request->query->get('page', (string)Pagination::DEFAULT_PAGE));
        $limit = min(Pagination::MAX_LIMIT, max(Pagination::MIN_LIMIT, (int) $request->query->get('limit', (string)Pagination::DEFAULT_LIMIT)));

        $permanentResult = $this->permanentFlagService->paginate($page, $limit);
        $persistentResult = $this->persistentFlagService->paginate($page, $limit);

        $result = [];

        foreach ($permanentResult['flags'] as $name => $config) {
            $result[] = [
                'name' => $name,
                'description' => $config['description'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'strategy' => $config['strategy'] ?? 'simple',
                'readonly' => true,
                'config' => $config,
            ];
        }

        foreach ($persistentResult['flags'] as $name => $config) {
            $result[] = [
                'name' => $name,
                'description' => $config['description'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'strategy' => $config['strategy'] ?? 'simple',
                'readonly' => false,
                'config' => $config,
            ];
        }

        $totalFlags = $permanentResult['pagination']['total'] + $persistentResult['pagination']['total'];
        $totalPages = (int) ceil($totalFlags / $limit);

        return $this->json([
            'flags' => $result,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFlags,
                'pages' => $totalPages,
            ],
        ]);
    }

    #[Route('/flags/{name}', name: 'get', methods: ['GET'])]
    public function apiGet(string $name): JsonResponse
    {
        $config = $this->permanentFlagService->getConfig($name);
        if ($config !== null) {
            return $this->json([
                'name' => $name,
                'config' => $config,
                'readonly' => true,
            ]);
        }

        $config = $this->persistentFlagService->getConfig($name);
        if ($config !== null) {
            return $this->json([
                'name' => $name,
                'config' => $config,
                'readonly' => false,
            ]);
        }

        return $this->json(['error' => 'Flag not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/flags/{name}/toggle', name: 'toggle', methods: ['POST'])]
    public function apiToggle(string $name, Request $request): JsonResponse
    {
        if (!$this->validateCsrfToken($request)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->permanentFlagService->exists($name)) {
            return $this->json([
                'error' => 'Cannot modify permanent flag. This flag is read-only from configuration.',
            ], Response::HTTP_FORBIDDEN);
        }

        $config = $this->persistentFlagService->getConfig($name);

        if (null === $config) {
            return $this->json(['error' => 'Flag not found'], Response::HTTP_NOT_FOUND);
        }

        $newState = !($config['enabled'] ?? false);

        if ($newState) {
            $this->persistentFlagService->enable($name);
        } else {
            $this->persistentFlagService->disable($name);
        }

        return $this->json([
            'success' => true,
            'enabled' => $newState,
            'name' => $name,
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Route('/flags/{name}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function apiUpdate(string $name, Request $request): JsonResponse
    {
        if (!$this->validateCsrfToken($request)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->permanentFlagService->exists($name)) {
            return $this->json([
                'error' => 'Cannot modify permanent flag. This flag is read-only from configuration.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $config = $this->persistentFlagService->getConfig($name) ?? [];
            $config = array_merge($config, $data);

            $strategy = $config['strategy'] ?? FlagStrategy::SIMPLE->value;
            $strategyFields = [
                'percentage' => ['percentage'],
                'user_id' => ['whitelist', 'blacklist'],
                'date_range' => ['start_date', 'end_date'],
            ];

            foreach ($strategyFields as $fields) {
                foreach ($fields as $field) {
                    if (array_key_exists($field, $config)) {
                        unset($config[$field]);
                    }
                }
            }

            if (isset($strategyFields[$strategy])) {
                foreach ($strategyFields[$strategy] as $field) {
                    if (array_key_exists($field, $data)) {
                        $config[$field] = $data[$field];
                    }
                }
            }

            if (isset($config['blacklist']) && is_string($config['blacklist'])) {
                $config['blacklist'] = array_map('trim', explode(',', $config['blacklist']));
            }

            $this->persistentFlagService->configure($name, $config);

            return $this->json([
                'success' => true,
                'name' => $name,
                'description' => $config['description'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'strategy' => $config['strategy'] ?? 'simple',
                'readonly' => false,
                'config' => $config,
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/flags', name: 'create', methods: ['POST'])]
    public function apiCreate(Request $request): JsonResponse
    {
        if (!$this->validateCsrfToken($request)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || empty($data['name'])) {
            return $this->json(['error' => 'Invalid data. Name is required.'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'];
        unset($data['name']);

        if ($this->permanentFlagService->exists($name) || $this->persistentFlagService->exists($name)) {
            return $this->json(['error' => 'Flag already exists'], Response::HTTP_CONFLICT);
        }

        try {
            $config = array_merge([
                'enabled' => FlagStatus::DISABLED->toBool(),
                'strategy' => FlagStrategy::SIMPLE->value,
            ], $data);

            $this->persistentFlagService->configure($name, $config);

            return $this->json([
                'success' => true,
                'name' => $name,
                'description' => $config['description'] ?? null,
                'enabled' => $config['enabled'] ?? false,
                'strategy' => $config['strategy'] ?? 'simple',
                'readonly' => false,
                'config' => $config,
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/flags/{name}', name: 'delete', methods: ['DELETE'])]
    public function apiDelete(string $name, Request $request): JsonResponse
    {
        if (!$this->validateCsrfToken($request)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($this->permanentFlagService->exists($name)) {
            return $this->json([
                'error' => 'Cannot delete permanent flag. This flag is read-only from configuration.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->persistentFlagService->exists($name)) {
            return $this->json(['error' => 'Flag not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->persistentFlagService->remove($name);

            return $this->json([
                'success' => true,
                'name' => $name,
            ]);
        } catch (Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
