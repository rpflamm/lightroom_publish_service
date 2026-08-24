<?php

declare(strict_types=1);

namespace RPflamm\LightroomPublishService\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use RPflamm\LightroomPublishService\Domain\Repository\CollectionRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend module: read-only overview of Lightroom-published collections.
 * Collections themselves are managed exclusively via the JSON API from
 * the Lightroom plugin, not edited here.
 */
final class CollectionController extends ActionController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly CollectionRepository $collectionRepository,
    ) {}

    public function listAction(): ResponseInterface
    {
        $collections = $this->collectionRepository->findAll();
        $photoCounts = [];
        foreach ($collections as $collection) {
            $photoCounts[$collection->uid] = $this->collectionRepository->countPhotos($collection->uid);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assignMultiple([
            'collections' => $collections,
            'photoCounts' => $photoCounts,
        ]);

        return $moduleTemplate->renderResponse('Backend/Collection/List');
    }
}
