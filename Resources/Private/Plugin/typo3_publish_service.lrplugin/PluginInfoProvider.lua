--[[----------------------------------------------------------------------------

PluginInfoProvider.lua
Plugin Manager settings panel: TYPO3 server URL + API token, and a connection test.

This is where the plug-in's single, global connection to a TYPO3 site is
configured (server URL persisted via LrPrefs, API token stored securely via
LrPasswords) rather than per Publish Service, since this plug-in targets one
TYPO3 site per Lightroom catalog.

------------------------------------------------------------------------------]]

local LrView = import 'LrView'
local LrDialogs = import 'LrDialogs'
local LrPrefs = import 'LrPrefs'
local LrPasswords = import 'LrPasswords'
local LrFunctionContext = import 'LrFunctionContext'

require 'PluginInit'
require 'Typo3API'

local bind = LrView.bind

--------------------------------------------------------------------------------

--- Persists the current field values to LrPrefs / LrPasswords immediately.
-- Called eagerly on every keystroke (via observers) rather than relying
-- solely on endDialog, since endDialog is only documented to fire when the
-- plug-in is *deselected* in the Plug-in Manager, not on every dialog close.
local function persistSettings( propertyTable )
	local prefs = LrPrefs.prefsForPlugin()
	prefs.serverUrl = propertyTable.serverUrl
	LrPasswords.store( PluginInit.passwordKey, propertyTable.apiToken or '', nil, nil )
end

local function startDialog( propertyTable )
	local prefs = LrPrefs.prefsForPlugin()
	propertyTable.serverUrl = prefs.serverUrl or ''
	propertyTable.apiToken = LrPasswords.retrieve( PluginInit.passwordKey, nil, nil ) or ''
	propertyTable.connectionStatus = ''

	propertyTable:addObserver( 'serverUrl', function()
		persistSettings( propertyTable )
	end )
	propertyTable:addObserver( 'apiToken', function()
		persistSettings( propertyTable )
	end )
end

local function endDialog( propertyTable, why )
	-- Backstop in case the observers above did not fire for some reason.
	persistSettings( propertyTable )
end

local function testConnection( propertyTable )
	LrFunctionContext.postAsyncTaskWithContext( 'Typo3PublishService.testConnection', function( context )
		-- Without this, any error thrown below is silently discarded and the
		-- button appears to do nothing at all (see FlickrUser.lua for the
		-- same pattern in the SDK's own Flickr sample plugin).
		LrDialogs.attachErrorDialogToFunctionContext( context )

		persistSettings( propertyTable )

		propertyTable.connectionStatus = LOC "$$$/Typo3PublishService/PluginManager/Testing=Testing connection\226\128\166"

		-- Use exactly what is currently shown in the fields, rather than
		-- relying on the LrPrefs/LrPasswords round trip above having already
		-- completed -- this way the test always reflects what the user sees,
		-- even if persistence is delayed for some reason.
		local ok, result = Typo3API.ping( { serverUrl = propertyTable.serverUrl, apiToken = propertyTable.apiToken } )
		if ok then
			local message = LOC( "$$$/Typo3PublishService/PluginManager/Success=Connected (token: ^1)", tostring( result.tokenName ) )
			propertyTable.connectionStatus = message
			LrDialogs.message( LOC "$$$/Typo3PublishService/PluginManager/SuccessTitle=Connection successful", message, 'info' )
		else
			local message = LOC( "$$$/Typo3PublishService/PluginManager/Failure=Connection failed: ^1", tostring( result ) )
			propertyTable.connectionStatus = message
			LrDialogs.message( LOC "$$$/Typo3PublishService/PluginManager/FailureTitle=Connection failed", message, 'critical' )
		end
	end )
end

--------------------------------------------------------------------------------

local function sectionsForTopOfDialog( f, propertyTable )
	return {
		{
			title = LOC "$$$/Typo3PublishService/PluginManager/Title=TYPO3 Publish Service",

			f:row {
				spacing = f:control_spacing(),
				f:static_text {
					title = LOC "$$$/Typo3PublishService/PluginManager/ServerUrl=Server URL:",
					width = LrView.share 'labelWidth',
				},
				f:edit_field {
					-- Explicit bind_to_object rather than relying on the
					-- documented-but-apparently-unreliable automatic default
					-- binding for LrPluginInfoProvider sections.
					value = bind { key = 'serverUrl', bind_to_object = propertyTable },
					fill_horizontal = 1,
					placeholder_string = 'https://example.com',
					-- Without this, the typed value only commits to
					-- propertyTable on focus loss, so clicking "Test
					-- Connection" right after typing (without clicking
					-- elsewhere first) would still see the old/empty value.
					immediate = true,
				},
			},

			f:row {
				spacing = f:control_spacing(),
				f:static_text {
					title = LOC "$$$/Typo3PublishService/PluginManager/ApiToken=API token:",
					width = LrView.share 'labelWidth',
				},
				f:password_field {
					value = bind { key = 'apiToken', bind_to_object = propertyTable },
					fill_horizontal = 1,
					placeholder_string = 'lrps_...',
					immediate = true,
				},
			},

			f:row {
				f:push_button {
					title = LOC "$$$/Typo3PublishService/PluginManager/TestButton=Test Connection",
					action = function()
						testConnection( propertyTable )
					end,
				},
				f:static_text {
					title = bind { key = 'connectionStatus', bind_to_object = propertyTable },
					fill_horizontal = 1,
				},
			},

			f:row {
				f:static_text {
					title = LOC "$$$/Typo3PublishService/PluginManager/Hint=Create an API token in the TYPO3 backend module \226\128\156Lightroom Publish Service \226\134\146 API tokens\226\128\157 and paste it above.",
					fill_horizontal = 1,
				},
			},
		},
	}
end

--------------------------------------------------------------------------------

return {

	startDialog = startDialog,
	endDialog = endDialog,
	sectionsForTopOfDialog = sectionsForTopOfDialog,

}
