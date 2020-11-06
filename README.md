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

- **Monetra Port**: (*Only appears if the Payment Server option is set to Custom*) Port number on the Monetra server that transactions will be sent to. Usually this will be 8665.

- **Separate users for ticket request and payment (POST) request**: If you would like to use separate subusers for the ticket request (when the payment data is submitted to the payment server to generate a ticket) and the payment POST request (when the ticket and all other payment data is submitted to the payment server for processing), select Yes here. Otherwise, select No.

- **Monetra Username**: (*Only appears if the Separate Users option is set to No*) The username of the Monetra user (for authentication on the Monetra server).

- **Monetra Password**: (*Only appears if the Separate Users option is set to No*) The password of the Monetra user (for authentication on the Monetra server). Stored encrypted via `Magento\Config\Model\Config\Backend\Encrypted`

- **Monetra Ticket Username**: (*Only appears if the Separate Users option is set to Yes*) The username of the Monetra user used for the ticket request.

- **Monetra Ticket Password**: (*Only appears if the Separate Users option is set to Yes*) The password of the Monetra user used for the ticket request. Stored encrypted via `Magento\Config\Model\Config\Backend\Encrypted`

- **Monetra POST Username**: (*Only appears if the Separate Users option is set to Yes*) The username of the Monetra user used for the payment (POST) request.

- **Monetra POST Password**: (*Only appears if the Separate Users option is set to Yes*) The password of the Monetra user used for the payment (POST) request. Stored encrypted via `Magento\Config\Model\Config\Backend\Encrypted`

- **Expiration Date Format**: Format of the expiration date input on the payment form.

- **Auto-reload**: If "Yes", automatically reload the checkout page every 15 minutes to avoid payment form timing out. Defaults to "Yes".

- **Autocomplete**: If "Yes", enable browser autocomplete for payment form fields. Defaults to "No".

- **CSS Path**: Path to CSS file that will be used to style the payment form. Must be hosted on same domain as Magento store. If empty, no custom CSS will be applied.

- **User-Facing Payment Denial Message**: The message that the user will see during the checkout process if their provided credit card is denied for any reason.

- **User-Facing Payment Error Message**: The message that the user will see during the checkout process if an internal error within the module prevents the sale from successfully completing.

- **Sort Order**: Determines where this payment method will appear in relation to other payment methods on the checkout page. For example, if the sort order for this method is set to 1, and the sort order for another payment method is set to 2, this one will appear above the other one on the list of payment method options.

### Monetra Account Vault Configuration

- **Enabled**: Must be set to "Yes" in order to allow customers to store and reuse payment cards.

- **Title**: The payment method name under which the customer's stored cards will appear. This only applies when creating orders in Magento's admin backend. In the customer-facing checkout process, stored cards will appear under a general "Payment Method" heading.

## More Information

Your Magento store **must** be configured to use HTTPS for the Monetra PaymentFrame payment method to work.

For details on how the Monetra PaymentFrame feature works, please see the Monetra PaymentFrame Guide available at [https://www.monetra.com/developers](https://www.monetra.com/developers).
