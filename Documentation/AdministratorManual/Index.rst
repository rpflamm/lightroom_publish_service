.. _administrator-manual:

=====================
Administrator manual
=====================

.. _administrator-manual-tokens:

Managing API tokens
=====================

Every Lightroom Classic installation that should be able to publish into
this TYPO3 site needs its own API token.

#.  Open the **Lightroom Publish Service** section in the backend's main
    menu and go to **API**.
#.  Click **Create new token** and give it a descriptive name (e.g. the
    computer or user it belongs to).
#.  Click **Create token**. The full plaintext token is shown **exactly
    once**, immediately after creation.
#.  Copy it now and paste it into the Lightroom plugin's Plugin Manager
    settings (see the plugin's own :file:`README.md`, shipped under
    :file:`Resources/Private/Plugin/typo3_publish_service.lrplugin/`).

.. warning::
    If you navigate away before copying the token, it cannot be retrieved
    again. Revoke it and create a new one instead of trying to recover it.

.. _administrator-manual-revoking:

Revoking a token
==================

In the **API tokens** list, click **Revoke** next to any active token. This
is a soft revoke: the row stays for audit purposes (name, creation date,
last-used timestamp/IP) but the token immediately stops authenticating.
Revoked tokens cannot be un-revoked -- create a new one if needed.

.. _administrator-manual-security:

Security notes
================

- Treat an API token like a password: anyone holding it can create, update,
  and delete photos and collections through the JSON API.
- Tokens are stored hashed (HMAC, keyed with the TYPO3 installation's
  encryption key) -- the plaintext is never persisted anywhere after the
  one-time reveal.
- The dedicated FAL storage
  (:file:`fileadmin/lightroom_publish_service/`) is managed exclusively by
  this extension's API. Do not rename, move, or edit files inside it
  manually through the Filelist module -- doing so will desync Lightroom's
  own "already published" bookkeeping for those photos.
- The API is rate-limited per IP address (see
  :ref:`configuration <configuration>`) to reduce the impact of a leaked
  token being brute-forced or abused.
