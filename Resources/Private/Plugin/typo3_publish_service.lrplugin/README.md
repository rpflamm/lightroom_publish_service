# TYPO3 Publish Service — Lightroom Classic Plugin

Publishes photos from Adobe Lightroom Classic directly into a TYPO3 installation running the `lightroom_publish_service` extension.

## Requirements

- Adobe Lightroom Classic (recent version; developed against the Lightroom Classic 15.3 SDK)
- A TYPO3 site with the `lightroom_publish_service` extension installed and set up
- An API token created in the TYPO3 backend module ("Lightroom Publish Service")

## Installation

1. Copy (or symlink, for development) this `typo3_publish_service.lrplugin` folder into a location of your choice.
2. In Lightroom Classic, open **File → Plug-in Manager**.
3. Click **Add**, then select the `typo3_publish_service.lrplugin` folder.
4. The plugin should appear in the list as "TYPO3 Publish Service" with a green status dot.

## Configuration

1. In TYPO3, log into the backend and open the **Lightroom Publish Service** module (under the **Web** menu).
2. Go to **API tokens → Create new token**, give it a name (e.g. the name of your computer), and click **Create token**.
3. **Copy the token immediately** — it is shown only once and cannot be retrieved again afterwards.
4. Back in Lightroom's Plug-in Manager, select "TYPO3 Publish Service" in the list.
5. Enter your TYPO3 site's base URL (e.g. `https://example.com`) in **Server URL**.
6. Paste the token into **API token**.
7. Click **Test Connection** to verify everything is working.

The server URL and API token are shared by all Publish Service connections created with this plugin — configure them once here.

## Publishing photos

1. In Lightroom's **Library** module, open the **Publish Services** panel.
2. Next to "TYPO3", click **Set Up...** (or use the existing connection if already configured).
3. Create a new **Published Collection** (or Collection Set for nested organization) — this becomes a "Collection" in TYPO3.
4. Drag photos into the Published Collection, then click **Publish**.
5. Photos appear in the TYPO3 backend module under **Collections → View photos**, and the underlying files land in a dedicated FAL storage (`fileadmin/lightroom_publish_service/`), separate from your other site media.

## Supported operations

- **Upload**: new photos in a Published Collection are uploaded on first publish.
- **Update**: republishing an already-published photo (after editing metadata or the develop settings) replaces the file and/or metadata on the server.
- **Delete**: removing a photo from a Published Collection deletes it from TYPO3 too.
- **Metadata sync**: title, caption, keywords, GPS coordinates + altitude, star rating, color label, creator, copyright, and capture date are transferred. Editing any of these fields marks the photo "Modified" in Lightroom so you know to republish.
- **Collections**: renaming, reparenting (moving into/out of Collection Sets), and deleting a Published Collection are reflected in TYPO3.

## Limitations (v1)

- Only JPEG files in the sRGB color space are exported. RAW/TIFF/original-file publishing is not yet supported.
- The server URL and API token are global to the plugin (one TYPO3 site per Lightroom installation), not configurable per Publish Service connection.

## Troubleshooting

- **"Connection failed" in Plugin Manager**: double-check the server URL (no trailing slash needed) and that the token was pasted correctly. Test the same URL + token with `curl`:
  ```
  curl -H "Authorization: Bearer <your-token>" https://your-site.example.com/lightroom-publish-service/api/v1/ping
  ```
- **"Connection failed: Missing Authorization header."**: the plugin does send the `Authorization: Bearer <token>` header, but the web server strips it before it reaches PHP. This is common on shared hosting and Plesk. Fix it on the server:
  - **nginx + PHP-FPM** (in Plesk: *Domains → your domain → Apache & nginx Settings → Additional nginx directives*):
    ```nginx
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    ```
  - **Apache** (in `.htaccess` in the web root, or in Plesk under *Additional Apache directives*):
    ```apache
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    ```
  After the change, the `curl` test above should answer with a token error (or success) instead of "Missing Authorization header." Also make sure the server URL does not redirect (e.g. `http` → `https` or non-`www` → `www`), since Lightroom does not resend the header on redirects.
- **A token stopped working**: it may have been revoked in the TYPO3 backend module. Create a new one and update the Plugin Manager settings.
- **Photos fail to upload**: check the file format/color space restrictions above, and check the TYPO3 backend module's Connection Test page for storage/configuration problems.
