<?php

declare(strict_types=1);

namespace App\Controller;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class SiteController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $log
    ) {
    }

    #[Route('/', methods: ['GET'])]
    public function default(Request $request): Response
    {
        return $this->json(['message' => 'Hello World!']);
    }
    /**
     * @see https://datatracker.ietf.org/doc/html/draft-inadarei-api-health-check
     */
    #[Route('/health', methods: ['GET'])]
    public function healthCheck(Request $request): JsonResponse
    {
        $this->log->debug('healthCheck', [$request->query->all()]);
        $serviceMetadata = [
            'serviceId' => 'app',
            'description' => 'b24 application',
        ];
        $statusCode = StatusCodeInterface::STATUS_OK;
        $checks = [];

        try {
            $state = [
                'status' => 'pass',
                'checks' => $checks,
            ];
        } catch (Throwable $throwable) {
            $statusCode = StatusCodeInterface::STATUS_SERVICE_UNAVAILABLE;
            $checks['rabbitmq'] = [
                'status' => 'fail',
                'componentType' => 'datastore',
                'observedValue' => 'disconnected',
                'output' => $throwable->getMessage(),
            ];

            $state = [
                'status' => 'fail',
                'checks' => $checks,
                'output' => 'RabbitMQ connection failed',
            ];
        }

        return $this->json(array_merge($serviceMetadata, $state), $statusCode);
    }
}
