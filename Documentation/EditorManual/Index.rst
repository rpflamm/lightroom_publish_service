.. _editor-manual:

=============
Editor manual
=============

The **Lightroom Publish Service** section in the backend's main menu gives
editors read-only insight into what has been published from Lightroom
Classic. Collections and photos are managed exclusively from the Lightroom
plugin -- its **Collection** sub-module does not let you create or edit
them directly.

.. _editor-manual-collections:

Collections
============

The **Collections** view lists every published collection, its type
(`collection` or `collection_set`), its parent (for nested Collection Sets),
and how many photos it contains. Selecting **View photos** on a collection
opens its photo grid.

.. _editor-manual-photos:

Photos
======

The **Photos** view shows a thumbnail grid for one collection, together with
each photo's title, rating, color label, and creator. Use this to verify
that a Lightroom publish run arrived correctly before wiring the images into
pages elsewhere in TYPO3 (this extension does not render photos on the
frontend itself; that step is a manual editorial task using the images from
the dedicated FAL storage).

.. _editor-manual-connection-test:

Connection test
================

The **Connection test** page reports whether the dedicated FAL storage is
available and writable, how many active API tokens exist, and the exact
server URL to enter into the Lightroom plugin's Plugin Manager settings.
Creating and revoking API tokens themselves is covered in the
:ref:`administrator manual <administrator-manual>`.
