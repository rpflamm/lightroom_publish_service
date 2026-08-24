<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use RPflamm\LightroomPublishService\Domain\Repository\ApiTokenRepository;
use RPflamm\LightroomPublishService\Service\TokenService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend module: manage API tokens used by Lightroom Classic plugin instances
 * to authenticate against the JSON API. Plaintext tokens are only ever shown
 * once, immediately after creation, via a one-time backend-user-session value.
 */
final class TokenController extends ActionController
{
    private const SESSION_KEY = 'lightroom_publish_service_new_token';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly TokenService $tokenService,
    ) {}

    public function listAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assignMultiple([
            'tokens' => $this->apiTokenRepository->findAll(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Token/List');
    }

    public function newAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        return $moduleTemplate->renderResponse('Backend/Token/New');
    }

    public function createAction(string $name): ResponseInterface
    {
        $name = trim($name);
        if ($name === '') {
            $this->addFlashMessage('A name is required to create an API token.', '', ContextualFeedbackSeverity::ERROR);

            return $this->redirect('new');
        }

        $backendUserUid = (int)($this->getBackendUser()->user['uid'] ?? 0);
        $plaintextToken = $this->tokenService->create($name, $backendUserUid);
        $this->getBackendUser()->setAndSaveSessionData(self::SESSION_KEY, $plaintextToken);

        return $this->redirect('reveal');
    }

    public function revealAction(): ResponseInterface
    {
        $plaintextToken = $this->getBackendUser()->getSessionData(self::SESSION_KEY);
        $this->getBackendUser()->setAndSaveSessionData(self::SESSION_KEY, null);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assign('plaintextToken', $plaintextToken ?: null);

        return $moduleTemplate->renderResponse('Backend/Token/Reveal');
    }

    public function revokeAction(int $tokenUid): ResponseInterface
    {
        $this->apiTokenRepository->revoke($tokenUid);
        $this->addFlashMessage('The API token has been revoked.', '', ContextualFeedbackSeverity::OK);

        return $this->redirect('list');
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
