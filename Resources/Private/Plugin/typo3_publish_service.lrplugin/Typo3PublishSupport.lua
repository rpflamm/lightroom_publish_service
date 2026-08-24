--[[----------------------------------------------------------------------------

Typo3PublishSupport.lua
Publish-specific portions of the TYPO3 Publish Service plug-in: everything
related to Published Collections / Collection Sets rather than individual
photo uploads (which live in Typo3PublishServiceProvider.lua's
processRenderedPhotos).

------------------------------------------------------------------------------]]

local LrApplication = import 'LrApplication'
local LrErrors = import 'LrErrors'

require 'Typo3API'

local publishServiceProvider = {}

--------------------------------------------------------------------------------
-- Collection Set sync
--
-- Lightroom never gives Collection Sets a remoteId on creation (there is no
-- "createPublishedCollectionSet" callback, and unlike Collections, Sets never
-- go through processRenderedPhotos). So a Set's remote counterpart is created
-- lazily, the first time something underneath it actually needs its parent's
-- remoteId -- exactly mirroring how a Collection itself is only created
-- remotely on its first publish.
--
-- parentsInfo is the `parents` array from a publishedCollectionInfo/info table
-- (top-level ancestor first, immediate parent last -- see LrPublishedCollectionInfo).
-- Returns true, remoteId (of the immediate parent, or nil if parentsInfo is
-- empty) on success, or false, errorMessage on failure.
local function ensureRemoteCollectionSetChain( publishSettings, parentsInfo )
	if not parentsInfo or #parentsInfo == 0 then
		return true, nil
	end

	local catalog = LrApplication.activeCatalog()
	local parentRemoteId = nil

	for _, parentInfo in ipairs( parentsInfo ) do
		if parentInfo.remoteId then
			parentRemoteId = parentInfo.remoteId
		else
			local ok, newRemoteId = Typo3API.createCollectionSet( publishSettings, parentInfo.name, parentRemoteId )
			if not ok then
				return false, newRemoteId
			end

			-- Record the new remoteId on the live Set object so it isn't
			-- re-created on the next publish. Only the catalog write itself
			-- needs the write-access gate; the HTTP call above must stay
			-- outside it.
			local publishedSet = catalog:getPublishedCollectionByLocalIdentifier( parentInfo.localCollectionId )
			if publishedSet then
				catalog:withWriteAccessDo( 'Typo3PublishService_RecordCollectionSetRemoteId', function()
					publishedSet:setRemoteId( newRemoteId )
				end )
			end

			parentRemoteId = newRemoteId
		end
	end

	return true, parentRemoteId
end

-- Exposed separately (not merged into exportServiceProvider) so
-- Typo3PublishServiceProvider.lua's processRenderedPhotos can reuse it too.
Typo3CollectionSetSync = {
	ensureRemoteChain = ensureRemoteCollectionSetChain,
}

--------------------------------------------------------------------------------
-- Collection behavior / terminology

function publishServiceProvider.getCollectionBehaviorInfo( publishSettings )
	return {
		defaultCollectionName = LOC "$$$/Typo3PublishService/DefaultCollectionName=Published Photos",
		defaultCollectionCanBeDeleted = true,
		canAddCollection = true,
		maxCollectionSetDepth = nil, -- unlimited nesting
	}
end

publishServiceProvider.titleForPublishedCollection = LOC "$$$/Typo3PublishService/Collection=Collection"
publishServiceProvider.titleForPublishedCollectionSet = LOC "$$$/Typo3PublishService/CollectionSet=Collection Set"

--------------------------------------------------------------------------------
-- Collection lifecycle

function publishServiceProvider.validatePublishedCollectionName( proposedName )
	if proposedName == nil or proposedName == '' then
		return false, LOC "$$$/Typo3PublishService/Error/EmptyName=The collection name must not be empty."
	end
	return true
end

function publishServiceProvider.renamePublishedCollection( publishSettings, info )
	if info.remoteId then
		local ok, err = Typo3API.renameCollection( publishSettings, info.remoteId, info.name )
		if not ok then
			LrErrors.throwUserError( tostring( err ) )
		end
	end
end

function publishServiceProvider.reparentPublishedCollection( publishSettings, info )
	if info.remoteId then
		local chainOk, newParentRemoteId = ensureRemoteCollectionSetChain( publishSettings, info.parents )
		if not chainOk then
			LrErrors.throwUserError( tostring( newParentRemoteId ) )
		end

		local ok, err = Typo3API.reparentCollection( publishSettings, info.remoteId, newParentRemoteId )
		if not ok then
			LrErrors.throwUserError( tostring( err ) )
		end
	end
end

function publishServiceProvider.deletePublishedCollection( publishSettings, info )
	if info.remoteId then
		local ok, err = Typo3API.deleteCollection( publishSettings, info.remoteId, true )
		if not ok then
			LrErrors.throwUserError( tostring( err ) )
		end
	end
end

--------------------------------------------------------------------------------
-- Photo deletion

--- Called when photos are removed from a Published Collection (but not necessarily
-- from the Lightroom catalog). Must call deletedCallback(photoId) once per photo
-- that was successfully deleted remotely.
function publishServiceProvider.deletePhotosFromPublishedCollection( publishSettings, arrayOfPhotoIds, deletedCallback, localCollectionId )
	for _, remotePhotoId in ipairs( arrayOfPhotoIds ) do
		local ok = Typo3API.deletePhoto( publishSettings, remotePhotoId )
		if ok then
			deletedCallback( remotePhotoId )
		end
	end
end

--------------------------------------------------------------------------------
-- Navigation

function publishServiceProvider.goToPublishedCollection( publishSettings, info )
	local LrHttp = import 'LrHttp'
	local serverUrl = import( 'LrPrefs' ).prefsForPlugin().serverUrl or ''
	LrHttp.openUrlInBrowser( string.gsub( serverUrl, '/+$', '' ) .. '/typo3/module/web/lightroom-publish-service' )
end

--------------------------------------------------------------------------------

Typo3PublishSupport = publishServiceProvider
