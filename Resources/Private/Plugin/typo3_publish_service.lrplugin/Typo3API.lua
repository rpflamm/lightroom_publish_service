--[[----------------------------------------------------------------------------

Typo3API.lua
REST client for the Lightroom Publish Service JSON API, using LrHttp.

IMPORTANT: LrHttp calls yield internally while waiting for the network
response. Lua cannot yield across a C call boundary such as pcall(), so
NONE of the functions below ever wrap an LrHttp.* call in pcall() -- doing
so fails at runtime with "Yielding is not allowed within a C or metamethod
call". Instead, every public function in this module returns an
`ok, resultOrErrorMessage[, extra]` tuple and never throws, so callers never
need (and must never use) pcall() around these calls either.

------------------------------------------------------------------------------]]

local LrHttp = import 'LrHttp'
local LrPasswords = import 'LrPasswords'
local LrPathUtils = import 'LrPathUtils'
local LrPrefs = import 'LrPrefs'

require 'PluginInit'
require 'JSON'

Typo3API = {}

--------------------------------------------------------------------------------
-- Internal helpers

-- The TYPO3 server connection (server URL + API token) is configured once,
-- globally, via the plug-in's Plugin Manager settings (see PluginInfoProvider.lua)
-- rather than per Publish Service, since this plug-in targets a single TYPO3 site.
-- Returns url, nil on success, or nil, errorMessage on failure. Never throws.
local function apiUrl( settings, path )
	local serverUrl = ( settings and settings.serverUrl ) or LrPrefs.prefsForPlugin().serverUrl or ''

	-- Trim stray whitespace (e.g. from copy/paste) before validating.
	serverUrl = string.gsub( serverUrl, '^%s+', '' )
	serverUrl = string.gsub( serverUrl, '%s+$', '' )

	if serverUrl == '' then
		return nil, 'Server URL is not configured. Enter it in the Plugin Manager under "TYPO3 Publish Service".'
	end

	-- A URL without a scheme (e.g. "lightroom.ddev.site" instead of
	-- "https://lightroom.ddev.site") is not a valid absolute URL and makes
	-- LrHttp fail with an opaque "badURL" network error, so default to
	-- https:// rather than surfacing that confusing error to the user.
	if not string.match( serverUrl, '^https?://' ) then
		serverUrl = 'https://' .. serverUrl
	end

	serverUrl = string.gsub( serverUrl, '/+$', '' )
	return serverUrl .. PluginInit.apiPath .. path
end

local function authHeaders( settings, extra )
	local token = ( settings and settings.apiToken ) or LrPasswords.retrieve( PluginInit.passwordKey, nil, nil )
	local headers = {
		{ field = 'Authorization', value = 'Bearer ' .. ( token or '' ) },
	}
	if extra then
		for _, header in ipairs( extra ) do
			headers[ #headers + 1 ] = header
		end
	end
	return headers
end

--- Parses a Lightroom Publish Service JSON envelope response.
-- Returns true, data on success, or false, errorMessage on failure. Never throws.
local function parseEnvelope( body, responseHeaders )
	-- LrHttp returns a nil body and a headers table with an "error" entry
	-- (instead of "status") when the request never reached the server at
	-- all, e.g. DNS failure, connection refused, or an untrusted/invalid
	-- TLS certificate. Without this check, that case surfaced as the
	-- confusing "unexpected response (HTTP nil)".
	if responseHeaders and responseHeaders.error then
		local networkError = responseHeaders.error
		local detail = networkError.name or networkError.errorCode or networkError.nativeCode or 'unknown error'
		return false, string.format(
			'Could not reach the TYPO3 server (%s). Check the Server URL and that the site is reachable '
				.. 'and its TLS certificate is trusted from this computer.',
			tostring( detail )
		)
	end

	local status = responseHeaders and responseHeaders.status

	if body == nil or body == '' then
		return false, string.format( 'TYPO3 server returned an empty response (HTTP %s).', tostring( status ) )
	end

	local decodeOk, decoded = pcall( JSON.decode, body )
	if not decodeOk or type( decoded ) ~= 'table' then
		return false, string.format(
			'TYPO3 server returned an unexpected response (HTTP %s): %s',
			tostring( status ),
			tostring( body ):sub( 1, 200 )
		)
	end

	if decoded.success == false then
		local errorInfo = decoded.error or {}
		return false, errorInfo.message or 'Unknown error from TYPO3 Publish Service API.'
	end

	if not status or status < 200 or status >= 300 then
		return false, string.format( 'TYPO3 server returned HTTP %s.', tostring( status ) )
	end

	return true, decoded.data
end

--------------------------------------------------------------------------------
-- Connection test

--- Returns true, data on success, or false, errorMessage on failure. Never throws.
function Typo3API.ping( settings )
	local url, urlError = apiUrl( settings, '/ping' )
	if not url then
		return false, urlError
	end

	local body, responseHeaders = LrHttp.get( url, authHeaders( settings ) )
	return parseEnvelope( body, responseHeaders )
end

--------------------------------------------------------------------------------
-- Collections

--- Returns true, remoteId on success, or false, errorMessage on failure.
-- collectionType defaults to 'collection'; pass 'collection_set' to create a
-- Collection Set (see Typo3API.createCollectionSet).
function Typo3API.createCollection( settings, name, parentRemoteId, collectionType )
	local url, urlError = apiUrl( settings, '/collections' )
	if not url then
		return false, urlError
	end

	local payload = JSON.encode( { name = name, type = collectionType or 'collection', parent = parentRemoteId } )
	local headers = authHeaders( settings, { { field = 'Content-Type', value = 'application/json' } } )

	local body, responseHeaders = LrHttp.post( url, payload, headers, 'POST' )
	local ok, data = parseEnvelope( body, responseHeaders )
	if not ok then
		return false, data
	end
	return true, data.id
end

--- Returns true, remoteId on success, or false, errorMessage on failure.
function Typo3API.createCollectionSet( settings, name, parentRemoteId )
	return Typo3API.createCollection( settings, name, parentRemoteId, 'collection_set' )
end

--- Returns true on success, or false, errorMessage on failure.
function Typo3API.renameCollection( settings, remoteId, newName )
	local url, urlError = apiUrl( settings, '/collections/' .. tostring( remoteId ) )
	if not url then
		return false, urlError
	end

	local payload = JSON.encode( { name = newName } )
	local headers = authHeaders( settings, { { field = 'Content-Type', value = 'application/json' } } )

	local body, responseHeaders = LrHttp.post( url, payload, headers, 'PATCH' )
	local ok, err = parseEnvelope( body, responseHeaders )
	return ok, err
end

--- Returns true on success, or false, errorMessage on failure.
function Typo3API.reparentCollection( settings, remoteId, newParentRemoteId )
	local url, urlError = apiUrl( settings, '/collections/' .. tostring( remoteId ) )
	if not url then
		return false, urlError
	end

	local payload = JSON.encode( { parent = newParentRemoteId } )
	local headers = authHeaders( settings, { { field = 'Content-Type', value = 'application/json' } } )

	local body, responseHeaders = LrHttp.post( url, payload, headers, 'PATCH' )
	local ok, err = parseEnvelope( body, responseHeaders )
	return ok, err
end

--- Returns true on success, or false, errorMessage on failure.
function Typo3API.deleteCollection( settings, remoteId, cascade )
	local url, urlError = apiUrl( settings, '/collections/' .. tostring( remoteId ) .. ( cascade and '?cascade=1' or '' ) )
	if not url then
		return false, urlError
	end

	local body, responseHeaders = LrHttp.post( url, '', authHeaders( settings ), 'DELETE' )
	local ok, err = parseEnvelope( body, responseHeaders )
	return ok, err
end

--------------------------------------------------------------------------------
-- Photos

--- Uploads a new photo into the given collection.
-- Returns true, remoteId, remoteUrl on success, or false, errorMessage on failure.
function Typo3API.uploadPhoto( settings, collectionRemoteId, filePath, metadata )
	local url, urlError = apiUrl( settings, '/collections/' .. tostring( collectionRemoteId ) .. '/photos' )
	if not url then
		return false, urlError
	end

	local mimeChunks = {
		{ name = 'metadata', value = JSON.encode( metadata ) },
		{ name = 'file', fileName = LrPathUtils.leafName( filePath ), filePath = filePath, contentType = 'image/jpeg' },
	}

	local body, responseHeaders = LrHttp.postMultipart( url, mimeChunks, authHeaders( settings ) )
	local ok, data = parseEnvelope( body, responseHeaders )
	if not ok then
		return false, data
	end
	return true, data.id, data.url
end

--- Replaces the binary and metadata of an already-published photo (full republish).
-- Returns true, remoteId, remoteUrl on success, or false, errorMessage on failure.
-- LrHttp.postMultipart cannot send a true HTTP PUT, so an X-HTTP-Method-Override
-- header is used, which the server-side middleware recognizes as an alias for PUT.
function Typo3API.updatePhoto( settings, photoRemoteId, filePath, metadata )
	local url, urlError = apiUrl( settings, '/photos/' .. tostring( photoRemoteId ) )
	if not url then
		return false, urlError
	end

	local mimeChunks = {
		{ name = 'metadata', value = JSON.encode( metadata ) },
		{ name = 'file', fileName = LrPathUtils.leafName( filePath ), filePath = filePath, contentType = 'image/jpeg' },
	}

	local headers = authHeaders( settings, { { field = 'X-HTTP-Method-Override', value = 'PUT' } } )
	local body, responseHeaders = LrHttp.postMultipart( url, mimeChunks, headers )
	local ok, data = parseEnvelope( body, responseHeaders )
	if not ok then
		return false, data
	end
	return true, data.id, data.url
end

--- Updates only the metadata of an already-published photo, without touching the file.
-- Returns true on success, or false, errorMessage on failure.
function Typo3API.updatePhotoMetadata( settings, photoRemoteId, metadata )
	local url, urlError = apiUrl( settings, '/photos/' .. tostring( photoRemoteId ) )
	if not url then
		return false, urlError
	end

	local payload = JSON.encode( metadata )
	local headers = authHeaders( settings, { { field = 'Content-Type', value = 'application/json' } } )

	local body, responseHeaders = LrHttp.post( url, payload, headers, 'PATCH' )
	local ok, err = parseEnvelope( body, responseHeaders )
	return ok, err
end

--- Returns true on success, or false, errorMessage on failure.
function Typo3API.deletePhoto( settings, photoRemoteId )
	local url, urlError = apiUrl( settings, '/photos/' .. tostring( photoRemoteId ) )
	if not url then
		return false, urlError
	end

	local body, responseHeaders = LrHttp.post( url, '', authHeaders( settings ), 'DELETE' )
	local ok, err = parseEnvelope( body, responseHeaders )
	return ok, err
end
