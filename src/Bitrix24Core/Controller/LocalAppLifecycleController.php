<?php

/**
 * This file is part of the b24sdk examples package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Bitrix24Core\Controller;

use App\Bitrix24Core\Bitrix24ServiceBuilderFactory;
use Bitrix24\SDK\Application\Requests\Events\OnApplicationInstall\OnApplicationInstall;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Services\RemoteEventsFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Bitrix24\Lib\Bitrix24Accounts;
use Bitrix24\Lib\Bitrix24Accounts\ValueObjects\Domain;

class LocalAppLifecycleController extends AbstractController
{
    public function __construct(
        private Bitrix24Accounts\UseCase\InstallStart\Handler $installStartHandler,
        private Bitrix24ServiceBuilderFactory $bitrix24ServiceBuilderFactory,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/b24/without-ui/install', name: 'b24_without_ui_install', methods: ['POST'])]
    public function installWithoutUi(Request $request): Response
    {
        $this->logger->debug('LocalAppLifecycleController.installWithoutUi.start', [
            'request' => $request->request->all(),
            'baseUrl' => $request->getBaseUrl(),
        ]);

        try {
            // check is this request are valid bitrix24 event request?
            if (!RemoteEventsFactory::isCanProcess($request)) {
                $this->logger->error('LocalAppLifecycleController.installWithoutUi.unknownRequest', [
                    'request' => $request->request->all()
                ]);
                throw new InvalidArgumentException('LocalAppLifecycleController can process only install event requests from bitrix24');
            }
            //todo check incoming events
            $b24Event = RemoteEventsFactory::init($this->logger)->createEvent($request, null);
            $this->logger->debug('LocalAppLifecycleController.installWithoutUi.eventRequest', [
                'eventClassName' => $b24Event::class,
                'eventCode' => $b24Event->getEventCode(),
                'eventPayload' => $b24Event->getEventPayload(),
            ]);

            if (!$b24Event instanceof OnApplicationInstall) {
                throw new InvalidArgumentException('LocalAppLifecycleController can process only install events from bitrix24');
            }

            // now we receive OnApplicationInstall event from Bitrix24
            $b24ServiceBuilder = $this->bitrix24ServiceBuilderFactory->createFromIncomingEvent($b24Event);


            //todo hide account id into command?
            $b24AccountId = Uuid::v7();
            // save auth tokens and application token


            $this->installStartHandler->handle(
                new Bitrix24Accounts\UseCase\InstallStart\Command(
                    $b24AccountId,
                    $b24ServiceBuilder->getMainScope()->main()->getCurrentUserProfile()->getUserProfile()->ID,
                    $b24ServiceBuilder->getMainScope()->main()->getCurrentUserProfile()->getUserProfile()->ADMIN,
                    $b24Event->getAuth()->member_id,
                    new Domain($b24Event->getAuth()->domain),
                    $b24Event->getAuth()->authToken,
                    (int)$b24Event->getEventPayload()['data']['VERSION'],
                    $b24Event->getAuth()->scope,
                )
            );
            //todo add install finish handler
            // fix master account problem
            // fix auth_token_expires_in



            $response = new Response('OK', 200);
            $this->logger->debug('LocalAppLifecycleController.installWithoutUi.finish', [
                'response' => $response->getContent(),
                'statusCode' => $response->getStatusCode(),
            ]);
            return $response;
        } catch (Throwable $throwable) {
            $this->logger->error('LocalAppLifecycleController.installWithoutUi.error', [
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);
            return new Response(sprintf('error on placement request processing: %s', $throwable->getMessage()), 500);
        }
    }
}
