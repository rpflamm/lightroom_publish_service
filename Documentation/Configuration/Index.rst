.. _configuration:

=============
Configuration
=============

Configure these via the Install Tool's Extension Configuration screen, or the
CLI:

.. code-block:: bash

    vendor/bin/typo3 configuration:set EXTENSIONS/lightroom_publish_service/<key> <value>

(Running under DDEV: prefix with :code:`ddev exec`.)

.. confval:: storageUid
    :name: confval-storageuid
    :type: int
    :Default: 0
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lightroom_publish_service']['storageUid']

    UID of the dedicated "Lightroom Publish Service" FAL storage. This is
    populated automatically the first time the storage is provisioned (see
    :ref:`installation <installation-schema>`); only override it if you
    created the storage manually yourself.

.. confval:: maxUploadSizeMb
    :name: confval-maxuploadsizemb
    :type: int
    :Default: 100
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lightroom_publish_service']['maxUploadSizeMb']

    Maximum accepted photo upload size in megabytes.

.. confval:: rateLimitRequests
    :name: confval-ratelimitrequests
    :type: int
    :Default: 30
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lightroom_publish_service']['rateLimitRequests']

    Maximum number of **failed** authentication attempts allowed per
    rate-limit window and IP address (missing, malformed, unknown, or
    revoked tokens). Requests with a valid token are never rate-limited, no
    matter how many are made -- a single Lightroom publish run of many
    photos would otherwise easily exceed a low limit, since every
    upload/update/delete is one request. This setting exists purely to slow
    down brute-forcing/guessing of a token.

.. confval:: rateLimitInterval
    :name: confval-ratelimitinterval
    :type: string
    :Default: "5 minutes"
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['lightroom_publish_service']['rateLimitInterval']

    The rate-limit window for failed authentication attempts, passed
    directly to Symfony's RateLimiter component (e.g. :code:`"5 minutes"`,
    :code:`"1 hour"`).
