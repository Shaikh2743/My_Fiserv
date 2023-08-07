## Fiserv Payment Gateway for Magento 2

This extension allows you to use Fiserv as payment gateway in your Magento 2 store.

## Installing via [Composer](https://getcomposer.org/)

```bash
composer require Fiserv/Fiserv-magento-2
php bin/magento module:enable Fiserv_Gateway --clear-static-content
php bin/magento setup:upgrade
```

Enable and configure Fiserv in Magento Admin under `Stores -> Configuration -> Payment Methods -> Fiserv Payment Gateway`.

For any issue send us an email to support@Fiserv.com and share the `gateway.log` file. The location of `gateway.log` file is `var/log/gateway.log`.