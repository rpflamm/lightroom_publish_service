--[[----------------------------------------------------------------------------

Typo3PublishServiceProvider.lua
The service definition script for the TYPO3 Publish Service plug-in: the base
export contract (processRenderedPhotos) plus the publish-only callbacks merged
in from Typo3PublishSupport.lua.

------------------------------------------------------------------------------]]

local LrErrors = import 'LrErrors'
local LrFileUtils = import 'LrFileUtils'

require 'Typo3API'
require 'Typo3PublishSupport'

--------------------------------------------------------------------------------

local exportServiceProvider = {}

-- Mark this as a publish (not just export) service.
exportServiceProvider.supportsIncrementalPublish = true

-- We upload directly to TYPO3; no local export destination is needed.
exportServiceProvider.hideSections = { 'exportLocation' }

-- v1 scope: only JPEG/sRGB uploads (documented limitation).
exportServiceProvider.allowFileFormats = { 'JPEG' }
exportServiceProvider.allowColorSpaces = { 'sRGB' }

exportServiceProvider.canExportVideo = false

exportServiceProvider.exportPresetFields = {
	-- No per-connection fields: server URL/API token are configured once,
	-- globally, via the Plugin Manager (see PluginInfoProvider.lua).
}

--------------------------------------------------------------------------------
-- Metadata mapping (Lightroom -> TYPO3 JSON API "metadata" object)

local function buildMetadataTable( photo )
	local metadata = {}

	-- NOTE: despite what the SDK reference's raw-metadata key list appears to
	-- say, "title" and other text/IPTC fields below throw "Unknown key" from
	-- getRawMetadata() in practice (confirmed at runtime) -- they are only
	-- valid via getFormattedMetadata().
	metadata.title = photo:getFormattedMetadata( 'title' ) or ''
	metadata.caption = photo:getFormattedMetadata( 'caption' ) or ''

	local keywordTags = photo:getFormattedMetadata( 'keywordTagsForExport' )
	if keywordTags and keywordTags ~= '' then
		local keywords = {}
		for rawKeyword in string.gmatch( keywordTags, '[^,]+' ) do
			local trimmedKeyword = string.gsub( rawKeyword, '^%s+', '' )
			trimmedKeyword = string.gsub( trimmedKeyword, '%s+$', '' )
			if trimmedKeyword ~= '' then
				keywords[ #keywords + 1 ] = trimmedKeyword
			end
		end
		metadata.keywords = keywords
	end

	local gps = photo:getRawMetadata( 'gps' )
	if gps and gps.latitude and gps.longitude then
		metadata.gps = { latitude = gps.latitude, longitude = gps.longitude }
	end

	local gpsAltitude = photo:getRawMetadata( 'gpsAltitude' )
	if gpsAltitude then
		metadata.gpsAltitude = gpsAltitude
	end

	local rating = photo:getRawMetadata( 'rating' )
	if rating then
		metadata.rating = rating
	end

	-- Use a whitelist rather than excluding 'none', since getRawMetadata()
	-- has been observed to return values here (e.g. differently-cased, or
	-- otherwise unexpected) that the server-side validation then rejects.
	local validColorLabels = { red = true, yellow = true, green = true, blue = true, purple = true }
	local colorLabel = photo:getRawMetadata( 'colorNameForLabel' )
	if colorLabel and validColorLabels[ colorLabel ] then
		metadata.colorLabel = colorLabel
	end

	metadata.creator = photo:getFormattedMetadata( 'creator' ) or ''
	metadata.copyright = photo:getFormattedMetadata( 'copyright' ) or ''

	local dateCreated = photo:getFormattedMetadata( 'dateCreated' )
	if not dateCreated or dateCreated == '' then
		dateCreated = photo:getRawMetadata( 'dateTimeOriginalISO8601' )
	end
	if dateCreated and dateCreated ~= '' then
		metadata.dateCreated = dateCreated
	end

	return metadata
end

--- Enables metadata-driven "Modified Photos to Re-Publish" detection for exactly
-- the fields synced by buildMetadataTable() above.
function exportServiceProvider.metadataThatTriggersRepublish( publishSettings )
	return {
		default = false,
		title = true,
		caption = true,
		keywords = true,
		gps = true,
		gpsAltitude = true,
		rating = true,
		label = true,
		creator = true,
		copyright = true,
		dateCreated = true,
	}
end

--------------------------------------------------------------------------------
-- Core upload/update loop

function exportServiceProvider.processRenderedPhotos( functionContext, exportContext )

	local exportSession = exportContext.exportSession
	local exportSettings = assert( exportContext.propertyTable )

	local nPhotos = exportSession:countRenditions()

	local progressScope = exportContext:configureProgress {
		title = nPhotos > 1
			and LOC( "$$$/Typo3PublishService/Publish/Progress=Publishing ^1 photos to TYPO3", nPhotos )
			or LOC "$$$/Typo3PublishService/Publish/Progress/One=Publishing one photo to TYPO3",
	}

	local publishedCollectionInfo = exportContext.publishedCollectionInfo
	local isDefaultCollection = publishedCollectionInfo.isDefaultCollection
	local collectionRemoteId = publishedCollectionInfo.remoteId

	if not collectionRemoteId then
		local chainOk, parentRemoteId = Typo3CollectionSetSync.ensureRemoteChain( exportSettings, publishedCollectionInfo.parents )
		if not chainOk then
			LrErrors.throwUserError( tostring( parentRemoteId ) )
		end

		local ok, idOrError = Typo3API.createCollection( exportSettings, publishedCollectionInfo.name, parentRemoteId )
		if not ok then
			LrErrors.throwUserError( tostring( idOrError ) )
		end
		collectionRemoteId = idOrError
		exportSession:recordRemoteCollectionId( collectionRemoteId )
	end

	for i, rendition in exportContext:renditions { stopIfCanceled = true } do

		progressScope:setPortionComplete( ( i - 1 ) / nPhotos )

		local photo = rendition.photo
		local remotePhotoId = rendition.publishedPhotoId

		if not rendition.wasSkipped then

			local success, pathOrMessage = rendition:waitForRender()

			progressScope:setPortionComplete( ( i - 0.5 ) / nPhotos )

			if progressScope:isCanceled() then break end

			if success then

				local metadata = buildMetadataTable( photo )
				local ok, newRemoteId, newRemoteUrl
				if remotePhotoId then
					ok, newRemoteId, newRemoteUrl = Typo3API.updatePhoto( exportSettings, remotePhotoId, pathOrMessage, metadata )
				else
					ok, newRemoteId, newRemoteUrl = Typo3API.uploadPhoto( exportSettings, collectionRemoteId, pathOrMessage, metadata )
				end

				LrFileUtils.delete( pathOrMessage )

				if ok then
					rendition:recordPublishedPhotoId( newRemoteId )
					rendition:recordPublishedPhotoUrl( newRemoteUrl )
				else
					rendition:uploadFailed( tostring( newRemoteId ) )
				end

			end

		else

			-- Keep the skipped photo out of the "to republish" bin.
			rendition:recordPublishedPhotoId( rendition.publishedPhotoId )

		end

	end

	progressScope:done()

end

--------------------------------------------------------------------------------
-- Merge in the publish-only callbacks (collection lifecycle, photo deletion, ...).

for name, value in pairs( Typo3PublishSupport ) do
	exportServiceProvider[ name ] = value
end

--------------------------------------------------------------------------------

return exportServiceProvider
