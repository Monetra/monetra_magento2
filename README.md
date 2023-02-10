# Monetra Module for Magento 2

The Monetra Module for Magento 2 allows you to easily configure your Magento 2 instance to process payments through a Monetra server. The module utilizes the Monetra PaymentFrame feature, receiving payment information in an iframe provided by the Monetra server. This ensures that sensitive payment data is never entered into your website's front end or sent to your Magento server. It also provides the option for customers to save payment methods so they can use them in the future without re-entering card details (sensitive card data is not stored on your Magento server).

## Installation

The module can be installed with Composer. From the root directory of your Magento 2 installation, run the following commands:
```
composer require monetra/monetra_magento2
bin/magento-cli setup:upgrade
```

## Configuration

Once the Monetra Module is installed on your Magento instance, you will need to provide some configuration values for it in the Magento admin. Navigate to Stores => Configuration => Sales => Payment Methods. You should see "Monetra PaymentFrame" and "Monetra Account Vault" in the list of payment methods. Click on the arrow icon to expand the configuration option list.

### Monetra PaymentFrame Configuration

- **Enabled**: Must be set to "Yes" in order to use the Monetra PaymentFrame payment method.

- **Payment Action**: If this is set to "Authorize Only", initial placement of an order will only authorize (not capture) the provided card. In other words, when an order is placed, the `sale` transaction sent to Monetra will include `capture=no`. If this option is set to "Authorize and Capture", the `sale` transaction will omit the `capture` parameter (which defaults to `yes`).

- **Title**: The name for the payment method that will be displayed on the user-facing checkout page.

- **New Order Status**: The default status that will be assigned to newly placed orders.

- **Payment Server**: If you are using the TranSafe payment gateway, select either TranSafe Test Server or TranSafe Live/Production Server. Otherwise, select custom, and fill in the two fields below.

- **Monetra Host**: (*Only appears if the Payment Server option is set to Custom*) Hostname (FQDN) of the Monetra server that your Magento instance will be sending transactions to.

- **Monetra Port**: (*Only appears if the Payment Server option is set to Custom*) Port number on the Monetra server that transactions will be sent to. Usually this will be 443 or 8665.

- **Generate API Key**: This button allows you to generate an API key that can be used for Monetra authentication. See [Generating an API Key](#generating-api-key) for more information.

- **Monetra API Key ID**: API key ID used to authenticate with Monetra. Can be entered manually or auto-generated using the "Generate API Key" configuration tool. See [Generating an API Key](#generating-api-key) for more information.

- **Monetra API Key Secret**: API key secret used to authenticate with Monetra. Can be entered manually or auto-generated using the "Generate API Key" configuration tool. See [Generating an API Key](#generating-api-key) for more information. Stored encrypted via `Magento\Config\Model\Config\Backend\Encrypted`

- **Expiration Date Format**: Format of the expiration date input on the payment form.

- **Auto-reload**: If "Yes", automatically reload the checkout page every 15 minutes to avoid payment form timing out. Defaults to "Yes".

- **Autocomplete**: If "Yes", enable browser autocomplete for payment form fields. Defaults to "No".

- **CSS Path**: Path to CSS file that will be used to style the payment form. Must be hosted on same domain as Magento store. If empty, no custom CSS will be applied.

- **User-Facing Payment Denial Message**: The message that the user will see during the checkout process if their provided credit card is denied for any reason.

- **User-Facing Payment Error Message**: The message that the user will see during the checkout process if an internal error within the module prevents the sale from successfully completing.

- **Sort Order**: Determines where this payment method will appear in relation to other payment methods on the checkout page. For example, if the sort order for this method is set to 1, and the sort order for another payment method is set to 2, this one will appear above the other one on the list of payment method options.

### Generating an API Key<a name="generating-api-key"></a>

Starting with version 3.0.0, the Monetra module for Magento 2 uses Monetra API key authentication instead of username/password authentication. This provides several security benefits. API keys can be revoked in case an application is compromised, and they remove the need to store a username and password in your Magento configuration. This also means that Monetra password changes or resets will no longer require a configuration update in Magento.

The easiest way to generate an API key to use with your Magento integration is to use the "Generate Key" button on the admin configuration page. This will open a popup window where you can enter your Monetra credentials (and select a profile, if necessary) to generate a key.

Once your key has been generated, the popup window will close. **You must click the "Save Config" button to store the key in your Magento configuration.**

**Note:** If your Magento instance already has a Monetra username and password configured, it will continue to work as it did previously. However, as of version 3.0.0 you will be unable to set a new username or password.

### Monetra Account Vault Configuration

- **Enabled**: Must be set to "Yes" in order to allow customers to store and reuse payment cards.

- **Title**: The payment method name under which the customer's stored cards will appear. This only applies when creating orders in Magento's admin backend. In the customer-facing checkout process, stored cards will appear under a general "Payment Method" heading.

## More Information

Your Magento store **must** be configured to use HTTPS for the Monetra PaymentFrame payment method to work.

For details on how the Monetra PaymentFrame feature works, please see the Monetra PaymentFrame Guide available at [https://www.monetra.com/developers](https://www.monetra.com/developers).
