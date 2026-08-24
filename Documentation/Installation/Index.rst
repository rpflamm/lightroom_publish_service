.. _installation:

============
Installation
============

.. _installation-composer:

Composer installation
=======================

Install the extension like any other Composer-based TYPO3 package:

.. code-block:: bash

    composer require rpflamm/lightroom-publish-service

If you are developing this extension from source instead of a released
package, add it as a `Composer path repository
<https://getcomposer.org/doc/05-repositories.md#path>`__ pointing at its
directory, then require it with a :code:`@dev` constraint.

.. _installation-schema:

Database schema and storage setup
===================================

After installing, apply the extension's database schema and let it activate:

.. code-block:: bash

    vendor/bin/typo3 extension:setup

This creates three tables (`tx_lightroompublishservice_domain_model_collection`,
`..._photo`, `..._apitoken`).

The extension also needs a dedicated FAL storage
(:file:`fileadmin/lightroom_publish_service/`). This is provisioned
automatically by an upgrade wizard the first time it runs:

.. code-block:: bash

    vendor/bin/typo3 upgrade:run lightroomPublishServiceCreateStorage

You only need to run this once; it is idempotent and safe to re-run.

.. _installation-verify:

Verifying the installation
============================

Confirm the extension is active and the storage was created:

.. code-block:: bash

    vendor/bin/typo3 extension:list | grep lightroom_publish_service

Then open the **Lightroom Publish Service** section in the backend's main
menu and check its **Test Connection** page -- it should report "Storage OK".

.. note::
    Running under DDEV? Prefix Composer commands with `ddev` (e.g.
    :code:`ddev composer require ...`) and CLI commands with :code:`ddev exec`
    (e.g. :code:`ddev exec vendor/bin/typo3 extension:setup`).

Next, continue with :ref:`configuration <configuration>` and creating your
first :ref:`API token <administrator-manual>`.
