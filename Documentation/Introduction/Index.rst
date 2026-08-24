.. _introduction:

============
Introduction
============

.. _introduction-what-it-does:

What it does
============

Adobe Lightroom Classic has a built-in concept of "Publish Services" (the same
mechanism used to publish photos to Flickr, SmugMug, etc.). This project adds
a TYPO3 target for it, made of two halves shipped together in this single
extension:

#.  A TYPO3 extension (`lightroom_publish_service`) that exposes a small JSON
    API, authenticated with a static Bearer API token, for creating,
    updating, and deleting photos and collections.
#.  A Lightroom Classic plugin (`typo3_public_service`), shipped as Lua
    source under :file:`Resources/Private/Plugin/typo3_publish_service.lrplugin/`
    inside this extension, which talks to that API from within Lightroom's
    Publish Services panel.

.. _introduction-supported-operations:

Supported operations
=====================

- **Upload**: new photos in a Lightroom Published Collection are uploaded on
  first publish.
- **Update**: republishing an already-published photo replaces the file
  and/or metadata.
- **Delete**: removing a photo from a Published Collection deletes it in
  TYPO3 too.
- **Metadata sync**: title, caption, keywords, GPS coordinates and altitude,
  star rating, color label, creator, copyright, and capture date.
- **Collections**: Lightroom Published Collections and Collection Sets map to
  nestable "Collection" records in TYPO3, with rename/reparent/delete kept in
  sync.

.. _introduction-architecture:

Architecture at a glance
==========================

- Photos are stored as regular :php:`sys_file` records in a **dedicated FAL
  storage** (:file:`fileadmin/lightroom_publish_service/`), isolated from
  editor-managed site media.
- Collections live in a small custom database table
  (:sql:`tx_lightroompublishservice_domain_model_collection`); photos
  themselves (:sql:`..._photo`) only hold bookkeeping data (collection
  membership, the FAL file relation, and an internal content hash). Actual
  photo metadata (title, caption, keywords, GPS, rating, color label,
  creator, copyright, capture date) is stored in TYPO3's core
  :sql:`sys_file_metadata` table via ``typo3/cms-filemetadata``, so it is
  available to any other extension that reads file metadata, not just this
  one. GPS altitude and the color label have no native
  :sql:`sys_file_metadata` column and are added by this extension's own TCA
  override. All of this is managed through plain repository/service classes
  rather than Extbase persistence, since the API runs from a PSR-15
  middleware outside any Extbase bootstrap.
- The JSON API is implemented as a single PSR-15 middleware registered on the
  frontend request stack, intercepting only requests under
  :file:`/lightroom-publish-service/api/v1/*` -- no TYPO3 site configuration
  is required for it to work.
- A dedicated top-level backend menu section ("Lightroom Publish Service")
  provides three sub-modules: **Collection** (browse published
  collections/photos), **API** (manage API tokens), and **Test Connection**
  (verify storage/token health).

See the :ref:`developer manual <developer-manual>` for the full API
reference, and the plugin's own :file:`README.md` (shipped alongside the Lua
source) for Lightroom-side setup instructions.
