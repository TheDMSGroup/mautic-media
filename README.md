# Mautic Media [![Latest Stable Version](https://poser.pugx.org/thedmsgroup/mautic-media-bundle/v/stable)](https://packagist.org/packages/thedmsgroup/mautic-media-bundle) [![License](https://poser.pugx.org/thedmsgroup/mautic-media-bundle/license)](https://packagist.org/packages/thedmsgroup/mautic-media-bundle) [![Build Status](https://travis-ci.com/TheDMSGroup/mautic-media.svg?branch=master)](https://travis-ci.com/TheDMSGroup/mautic-media)
![marketing cost by Iris Li from the Noun Project](./Assets/img/media.png)

Pulls cost data from media advertising services for campaign correlation.
Currently fills a table called media_account_stats with this data for use by other plugins.

## Installation & Usage

Currently being used with Mautic `2.15.x`.
If you have success/issues with other versions please report.

1. Install by running `composer require thedmsgroup/mautic-media-bundle`
2. Go to `/s/plugins/reload`
3. Click "Media" and enable the plugin.
4. The "Media" menu item will show up on the left, go there and create your first Media Account.

## Providers Supported/Planned

* Facebook Ads - Supported. You need to configure your own Facebook App via the developer portal to get API credentials.
* Google Ads - Supported. You need to get your oauth tokens manually.
* Snapchat Ads - Supported, with oauth login.
* Bing Ads - Supported, with oauth login.
* Media Alpha Ads - TBD

## Cron task

Pull/update the last 24 hours of data, every hour:

0 * * * * php /path/to/mautic/app/console mautic:media:pull
