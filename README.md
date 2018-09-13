# Mautic Media [![Latest Stable Version](https://poser.pugx.org/thedmsgroup/mautic-media/v/stable)](https://packagist.org/packages/thedmsgroup/mautic-media-bundle) [![License](https://poser.pugx.org/thedmsgroup/mautic-media-bundle/license)](https://packagist.org/packages/thedmsgroup/mautic-media-bundle) [![Build Status](https://travis-ci.com/TheDMSGroup/mautic-media.svg?branch=master)](https://travis-ci.com/TheDMSGroup/mautic-media)
![marketing cost by Iris Li from the Noun Project](./Assets/img/media.png)

Pulls cost data from media advertising services.

## Installation & Usage

Currently being used with Mautic `2.14.x`.
If you have success/issues with other versions please report.

1. Install by running `composer require thedmsgroup/mautic-media-bundle`
   (or by extracting this repo to `/plugins/MauticMediaBundle`)
2. Go to `/s/plugins/reload`
3. Click "Dashboard Warmer" and configure as desired.

## Cron task

Pull media data every hour:

0 * * * * php /path/to/mautic/app/console mautic:media:pull
