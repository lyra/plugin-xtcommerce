# PayZen for xt:Commerce

PayZen for xt:Commerce is an open source plugin that links e-commerce websites based on xt:Commerce to PayZen secure payment gateway developed by [Lyra Network](https://www.lyra.com/).

## Installation & Upgrade

To update the payment plugin, you must first uninstall and delete the previous version. Make sure you saved the parameters of your plugin before deleting it.

To uninstall the payment module go to the left side panel of xt:Commerce Back Office:

- Click on `Plugins > plugins installed`,
- Select `PayZen` under `modul class: payment` section,
- Click on `delete` icon,
- Confirm by clicking on `Yes`.

To install the plugin, follow these steps:

- Through FTP, copy the ly_payzen folder into the xt:Commerce `/plugins` directory,
- Go to the left side panel of xt:Commerce: `Plugins > plugins uninstalled`,
- Select `PayZen` under `modul class: payment` section,
- Click on `Run` icon,
- Confirm by clicking on `Yes`.

Once the installation is complete, do the following:

- Go to `Plugins > plugins installed`,
- In `modul class: payment` section, select `PayZen` by checking its box,
- Click on `enable selection`.

## Configuration

To enable `PayZen` payment method:

- Go to `configuration > method of payment`,
- Select `PayZen` and check its box,
- Click on `enable selection`,
- Confirm by clicking on `Yes`. 

To configure the payment plugin, in the xt:Commerce Back Office:

- Go to `configuration > method of payment` and select `PayZen` by checking its box,
- Then click on `edit` button.

The payment plugin configuration interface is composed of several sections. Enter your gateway credentials in "PAYMENT GATEWAY ACCESS" section.

## License

Each PayZen payment module for xt:Commerce source file included in this distribution is licensed under the The MIT License (MIT).

Please see LICENSE.txt for the full text of the MIT license. It is also available through the world-wide-web at this URL: https://opensource.org/licenses/mit-license.html.