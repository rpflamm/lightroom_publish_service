.. _developer-manual:

=================
Developer manual
=================

This section documents the JSON API for anyone building an alternative
client against it (the shipped Lightroom Classic plugin is one such client;
see :file:`Classes/Http/Middleware/ApiRequestResolver.php` for the
server-side entry point).

.. toctree::
    :maxdepth: 2

    ApiReference

.. _developer-manual-architecture:

Implementation notes
======================

- The API is implemented as a single PSR-15 middleware
  (:php:`RPflamm\LightroomPublishService\Http\Middleware\ApiRequestResolver`)
  registered on the **frontend** request stack, before site resolution. It
  only intercepts requests whose path starts with
  :file:`/lightroom-publish-service/api/v1/`; everything else passes through
  untouched, so no TYPO3 site configuration is required for the API to work.
- Domain data access uses plain :php:`TYPO3\CMS\Core\Database\ConnectionPool`
  -based repositories rather than Extbase persistence, since the API runs
  outside any Extbase bootstrap. TCA is still defined for all tables
  (reference index integration, Filelist "used by" tracking), but writes go
  through :php:`Classes/Domain/Repository/*Repository` classes directly.
- Photo files land in a dedicated FAL storage provisioned by
  :php:`RPflamm\LightroomPublishService\Service\StorageProvisioningService`,
  under :file:`/collections/{collectionUid}/{photoUid}_{filename}`.
- :php:`sys_file_reference` rows linking a photo to its file are written
  directly (not via DataHandler), since there is no backend user session in
  this context.
- :sql:`tx_lightroompublishservice_domain_model_photo` only holds
  bookkeeping data (collection FK, FAL file relation, content hash). All
  actual photo metadata is stored in the file's :sql:`sys_file_metadata` row
  instead, via :php:`RPflamm\LightroomPublishService\Service\MetadataMappingService`,
  which wraps :php:`TYPO3\CMS\Core\Resource\Index\MetaDataRepository`. GPS
  altitude and the color label are added to :sql:`sys_file_metadata` by this
  extension's own :file:`Configuration/TCA/Overrides/sys_file_metadata.php`,
  since ``typo3/cms-filemetadata`` has no equivalent columns for them.
