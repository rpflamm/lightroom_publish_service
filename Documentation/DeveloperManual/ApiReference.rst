.. _developer-manual-api-reference:

=============
API reference
=============

.. _api-reference-auth:

Authentication
================

Every request (including :http:get:`/ping`) requires a Bearer token:

.. code-block:: http

    GET /lightroom-publish-service/api/v1/ping HTTP/1.1
    Authorization: Bearer lrps_<selector>_<verifier>

Tokens are created and revoked in the backend module -- see the
:ref:`administrator manual <administrator-manual-tokens>`. Requests with a
valid token are never rate-limited (a single Lightroom publish run can
easily fire far more requests than a low limit would allow). Only *failed*
authentication attempts are rate-limited per IP address, to slow down
brute-forcing/guessing a token; exceeding that limit returns
:http:statuscode:`429` with a :mailheader:`Retry-After` header.

.. _api-reference-envelope:

Response envelope
===================

Every response is a JSON object shaped as either:

.. code-block:: json

    { "success": true, "data": { } }

or, on error:

.. code-block:: json

    { "success": false, "error": { "code": "...", "message": "..." } }

.. _api-reference-routes:

Routes
=======

.. list-table::
    :header-rows: 1
    :widths: 10 30 60

    * - Method
      - Path
      - Purpose
    * - GET
      - :file:`/ping`
      - Connection test (also verifies the token)
    * - GET
      - :file:`/collections`
      - List all collections
    * - POST
      - :file:`/collections`
      - Create a collection or collection set
    * - GET
      - :file:`/collections/{id}`
      - Fetch one collection
    * - PATCH
      - :file:`/collections/{id}`
      - Rename and/or reparent
    * - DELETE
      - :file:`/collections/{id}?cascade=1`
      - Delete (409 if non-empty and `cascade` not set)
    * - GET
      - :file:`/collections/{id}/photos`
      - List photos in a collection
    * - POST
      - :file:`/collections/{id}/photos`
      - Upload a new photo (multipart)
    * - GET
      - :file:`/photos/{id}`
      - Fetch one photo
    * - PUT
      - :file:`/photos/{id}`
      - Replace file + metadata (multipart; see below for the method-override note)
    * - PATCH
      - :file:`/photos/{id}`
      - Update metadata only (JSON body)
    * - DELETE
      - :file:`/photos/{id}`
      - Delete a photo
    * - GET
      - :file:`/photos?updatedSince=&collection=`
      - Bulk listing for reconciliation

.. note::
    :php:`LrHttp.postMultipart` in the Lightroom SDK cannot send a true HTTP
    :http:method:`PUT` request. The plugin instead sends
    :http:method:`POST` with an :mailheader:`X-HTTP-Method-Override: PUT`
    header, which :php:`ApiRequestResolver` treats as an alias for
    :http:method:`PUT` on the :file:`/photos/{id}` route.

.. _api-reference-upload:

Uploading a photo
===================

.. code-block:: http

    POST /collections/12/photos HTTP/1.1
    Authorization: Bearer lrps_...
    Content-Type: multipart/form-data; boundary=...

    --boundary
    Content-Disposition: form-data; name="file"; filename="mountain-lake.jpg"
    Content-Type: image/jpeg

    <binary data>
    --boundary
    Content-Disposition: form-data; name="metadata"

    {
        "title": "Mountain lake at sunrise",
        "caption": "Taken from the eastern ridge trail",
        "keywords": ["landscape", "mountains", "sunrise"],
        "gps": { "latitude": 46.5197, "longitude": 6.6323 },
        "gpsAltitude": 1820.0,
        "rating": 5,
        "colorLabel": "green",
        "creator": "Jane Photographer",
        "copyright": "© 2026 Jane Photographer",
        "dateCreated": "2026-06-12T18:42:00Z"
    }
    --boundary--

Response (:http:statuscode:`201`):

.. code-block:: json

    {
        "success": true,
        "data": {
            "id": "873",
            "url": "fileadmin/lightroom_publish_service/collections/12/873_mountain-lake.jpg",
            "collection": "12",
            "contentHash": "sha256:..."
        }
    }

.. _api-reference-metadata-fields:

Metadata fields
=================

All fields are optional; omitted fields are left unchanged on
:http:method:`PATCH`, or stored empty/null on create.

.. list-table::
    :header-rows: 1
    :widths: 20 20 60

    * - Field
      - Type
      - Notes
    * - `title`
      - string
      -
    * - `caption`
      - string
      -
    * - `keywords`
      - array of string
      -
    * - `gps`
      - object or null
      - :code:`{ "latitude": number, "longitude": number }`
    * - `gpsAltitude`
      - number
      - Meters
    * - `rating`
      - integer
      - 0-5
    * - `colorLabel`
      - string
      - One of `red`, `yellow`, `green`, `blue`, `purple`, or empty/omitted
    * - `creator`
      - string
      -
    * - `copyright`
      - string
      -
    * - `dateCreated`
      - string
      - ISO-8601 date/time

.. _api-reference-errors:

Error codes
=============

.. list-table::
    :header-rows: 1
    :widths: 15 15 70

    * - HTTP status
      - Code
      - Meaning
    * - 401
      - `invalid_token`
      - Missing, malformed, unknown, or revoked token
    * - 404
      - `not_found`
      - Unknown collection/photo, or unmatched route
    * - 409
      - `not_empty`
      - Deleting a non-empty collection without `?cascade=1`
    * - 409
      - `duplicate_name`
      - A collection with this name already exists under the same parent
    * - 422
      - `missing_file`
      - Photo upload/replace without a `file` part
    * - 422
      - (various)
      - Metadata validation failures (see
        :php:`RPflamm\LightroomPublishService\Service\MetadataMappingService`)
    * - 429
      - `rate_limited`
      - Too many *failed* authentication attempts from this IP address
        (never triggered by requests with a valid token)
