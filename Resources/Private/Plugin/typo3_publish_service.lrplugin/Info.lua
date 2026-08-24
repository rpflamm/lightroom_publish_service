--[[----------------------------------------------------------------------------

Info.lua
Summary information for the TYPO3 Publish Service plug-in.

Publishes photos from Adobe Lightroom Classic into a TYPO3 installation
running the "lightroom_publish_service" extension.

------------------------------------------------------------------------------]]

return {

	LrSdkVersion = 13.0,
	LrSdkMinimumVersion = 6.0, -- minimum SDK version required by this plug-in

	LrToolkitIdentifier = 'de.rpflamm.typo3publishservice',
	LrPluginName = LOC "$$$/Typo3PublishService/PluginName=TYPO3 Publish Service",

	LrExportServiceProvider = {
		title = LOC "$$$/Typo3PublishService/Title=TYPO3",
		file = 'Typo3PublishServiceProvider.lua',
	},

	LrPluginInfoProvider = 'PluginInfoProvider.lua',

	VERSION = { major = 1, minor = 0, revision = 0 },

}
