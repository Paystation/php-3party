# Paystation 3-party demo

This demonstrates how to get a payment URL from our API and show it to a user. You will need your own Paystation credentials to run this demo. You can get an account set up by contacting our support team.

To run this demo:
- put your Paystation credentials in a file called `php-3party/config.json` [see config.sample.json](//github.com/Paystation/php-3party/blob/master/config.sample.json)
- set `php-3party/web` as the web root.
- make sure the user running PHP has read/write access to `php-3party/temp`

The payment journey for a user looks something like this:
- User goes through your checkout and clicks a pay button.
- Your website sends a request with the amount they need to pay to our API, and our API sends back a payment link.
- The user opens that link and makes a payment, while your website polls our API to see when they've completed the payment.
- When the payment is complete you'll get a successful or failed transaction 

The reason for sending them to our page to make the payment is because, without being PCI compliant, credit card data cannot pass through your website.

[View our documentation here](https://docs.paystation.co.nz/#payment-page-customization) if you're framing or customising the payment page.
