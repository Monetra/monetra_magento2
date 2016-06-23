#Monetra Module for Magento 2

The Monetra Module for Magento 2 allows you to easily configure your Magento 2 instance to process payments through a
Monetra server. The module utilizes the Monetra Post (Client Ticket Request) protocol, sending payment information directly to Monetra
so that sensitive data like credit card numbers are never sent to your Magento server.

## How It Works

The basic process of the Client Ticket Request payment method (provided by this module) is as follows:

1. When the payment form is submitted, the sensitive card data in the form is posted directly to the Monetra server via AJAX (nothing is sent to Magento yet).
2. The Monetra server responds with a single-use "ticket" associated with the submitted card data.
3. This ticket is submitted along with the rest of the payment form data (except for the sensitive card data) to Magento.
4. Magento sends a `sale` transaction to the Monetra server, providing the ticket instead of actual card data.

The module does this work for you. All you need to do is install and configure it.

## Installation

The module can be installed with Composer. From the root directory of your Magento 2 installation, run the following commands:
```
composer require monetra/monetra_magento2
php bin/magento setup:upgrade
```

## Configuration

Once the Monetra Module is installed on your Magento instance, you will need to provide some configuration values for it
in the Magento admin. Navigate to Stores => Configuration => Sales => Payment Methods. You should see "Monetra Client Ticket Request"
in the list of payment methods. Click on the arrow icon to expand the configuration option list.

- **Enabled**: Must be set to "Yes" in order to use the Monetra Client Ticket Request payment method.

- **Payment Action**: If this is set to "Authorize Only", initial placement of an order will only authorize (not capture) the provided card. In other words, when an order is placed, the `sale` transaction sent to Monetra will include `capture=no`. If this option is set to "Authorize and Capture", the `sale` transaction will omit the `capture` parameter (which defaults to `yes`).

- **Title**: The name for the payment method that will be displayed on the user-facing checkout page.

- **New Order Status**: The default status that will be assigned to newly placed orders.

- **Monetra Host**: Hostname (FQDN) of the Monetra server that your Magento instance will be sending transactions to.

- **Monetra Port**: Port number on the Monetra server that transactions will be sent to. Usually this will be 8665.

- **Monetra Username**: The username of the Monetra user (for authentication on the Monetra server).

- **Monetra Password**: The password of the Monetra user (for authentication on the Monetra server). Stored encrypted via `Magento\Config\Model\Config\Backend\Encrypted`

- **Credit Card Types**: The credit card types that will be accepted by this payment method.

- **Credit Card Verification**: If "Yes", payment form will include a field for card verification value (CVV), which will be sent to the Monetra server along with the other payment fields to generate the ticket (CVV will not be sent to your Magento server).

- **Sort Order**: Determines where this payment method will appear in relation to other payment methods on the checkout page. For example, if the sort order for this method is set to 1, and the sort order for another payment method is set to 2, this one will appear above the other one on the list of payment method options.
