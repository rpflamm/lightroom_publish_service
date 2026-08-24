<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use RPflamm\LightroomPublishService\Domain\Repository\ApiTokenRepository;
use RPflamm\LightroomPublishService\Domain\Repository\CollectionRepository;
use RPflamm\LightroomPublishService\Exception\CollectionNotFoundException;
use RPflamm\LightroomPublishService\Service\PhotoService;
use RPflamm\LightroomPublishService\Service\StorageProvisioningService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class PhotoController extends ActionController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly CollectionRepository $collectionRepository,
        private readonly PhotoService $photoService,
        private readonly StorageProvisioningService $storageProvisioningService,
        private readonly ApiTokenRepository $apiTokenRepository,
    ) {}

    public function listAction(int $collection): ResponseInterface
    {
        $collectionObject = $this->collectionRepository->findByUid($collection);
        if ($collectionObject === null) {
            throw new CollectionNotFoundException(sprintf('Collection %d does not exist.', $collection));
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assignMultiple([
            'collection' => $collectionObject,
            'photos' => $this->photoService->listByCollection($collection),
        ]);

        return $moduleTemplate->renderResponse('Backend/Photo/List');
    }

    public function connectionTestAction(): ResponseInterface
    {
        $normalizedParams = $this->request->getAttribute('normalizedParams');
        $baseUrl = $normalizedParams instanceof NormalizedParams
            ? rtrim($normalizedParams->getSiteUrl(), '/')
            : '';

        try {
            $storage = $this->storageProvisioningService->getOrCreateStorage();
            $storageOk = $storage->getUid() > 0;
            $storageMessage = sprintf('Storage "%s" (uid %d) is available and writable.', $storage->getName(), $storage->getUid());
        } catch (\Throwable $exception) {
            $storageOk = false;
            $storageMessage = $exception->getMessage();
        }

        $activeTokenCount = count(array_filter($this->apiTokenRepository->findAll(), static fn($token) => !$token->revoked));

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assignMultiple([
            'apiBaseUrl' => $baseUrl . '/lightroom-publish-service/api/v1',
            'storageOk' => $storageOk,
            'storageMessage' => $storageMessage,
            'activeTokenCount' => $activeTokenCount,
        ]);

        if (!$storageOk) {
            $this->addFlashMessage($storageMessage, 'Storage problem', ContextualFeedbackSeverity::ERROR);
        }

        return $moduleTemplate->renderResponse('Backend/Photo/ConnectionTest');
    }
}
