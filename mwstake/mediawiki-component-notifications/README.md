## MediaWiki Stakeholders Group - Components
# Notifications for MediaWiki

**This code is meant to be executed within the MediaWiki application context. No standalone usage is intended.**

## Use in a MediaWiki extension

Add `"mwstake/mediawiki-component-notifications": "~2.0"` to the `require` section of your `composer.json` file.

Since 2.0 explicit initialization is required. This can be achived by
- either adding `"callback": "mwsInitComponents"` to your `extension.json`/`skin.json`
- or calling `mwsInitComponents();` within you extensions/skins custom `callback` method

See also [`mwstake/mediawiki-componentloader`](https://github.com/hallowelt/mwstake-mediawiki-componentloader).
