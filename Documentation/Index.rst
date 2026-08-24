.. _start:

=========================
Lightroom Publish Service
=========================

:Extension key:
    lightroom_publish_service

:Package name:
    rpflamm/lightroom-publish-service

:Version:
    1.0.0

:Language:
    en

:Author:
    RPflamm

:License:
    This document is published under the
    `CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>`__
    license.

:Rendered:
    |today|

----

This extension exposes a token-authenticated JSON API that lets the companion
Adobe Lightroom Classic "TYPO3 Publish Service" plugin publish photos directly
into TYPO3: uploading, updating, and deleting photos, and keeping metadata
(title, caption, keywords, GPS, rating, color label, creator, copyright,
capture date) in sync. Photos are stored in a dedicated FAL storage, separate
from editor-managed site media, and organized in Collections that mirror
Lightroom's Published Collections and Collection Sets.

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    .. card:: :ref:`Introduction <introduction>`

        What this extension does and how the two halves (TYPO3 extension and
        Lightroom plugin) fit together.

    .. card:: :ref:`Installation <installation>`

        Installing the extension via Composer and setting up the FAL storage.

    .. card:: :ref:`Configuration <configuration>`

        Extension configuration options (upload size limits, rate limiting).

    .. card:: :ref:`Editor manual <editor-manual>`

        Using the backend module to browse collections and photos.

    .. card:: :ref:`Administrator manual <administrator-manual>`

        Managing API tokens and configuring the Lightroom Classic plugin.

    .. card:: :ref:`Developer manual <developer-manual>`

        The full JSON API reference for building alternative clients.

.. toctree::
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    EditorManual/Index
    AdministratorManual/Index
    DeveloperManual/Index
