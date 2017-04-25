<?php

# V1.1
# Andy @2017
# https://www.vmlink.cc

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once "stripe1/Stripe.php";
//use Illuminate\Database\Capsule\Manager as Capsule;


function cc_config() {
//global $CONFIG;
return [
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Stripe V2',
        ],
        // a password field type allows for masked text input
        'testSecretKey' => [
            'FriendlyName' => 'Test Secret Key',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => '',
            'Description'  => '',
        ],
        // a text field type allows for single line text input
        'testPublicKey' => [
            'FriendlyName' => 'Test Public Key',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => '',
            'Description'  => '',
        ],
        // a password field type allows for masked text input
        'liveSecretKey' => [
            'FriendlyName' => 'Live Secret Key',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => '',
            'Description'  => '',
        ],
        // a text field type allows for single line text input
        'livePublicKey' => [
            'FriendlyName' => 'Live Public Key',
            'Type'         => 'text',
            'Size'         => '40',
            'Default'      => '',
            'Description'  => '',
        ],
        // the yesno field type displays a single checkbox option
        'testMode' => [
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable test mode',
        ],
    ];
}



function cc_link($params) {
	//global $CONFIG;

	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params["description"];
	$amount = $params['amount']; # Format: ##.##
	$currency = $params['currency']; # Currency Code
	//echo $currency;
	
	// Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];
	
	
	# System Variables
	$companyname 		= $params['companyname'];
	$systemurl 			= $params['systemurl'];
	
	if ($params['testMode'] == "on") {
		Stripe::setApiKey($params['testSecretKey']);
		$pubkey = $params['testPublicKey'];
	} else {
		Stripe::setApiKey($params['liveSecretKey']);
		$pubkey = $params['livePublicKey'];
	}


	if(isset($_POST['stripeToken']))
	{
		$gatewaymodule = "cc"; # Enter your gateway module name here replacing template
		$GATEWAY = getGatewayVariables($gatewaymodule);

		$amount_cents = $amount*100;  // Chargeble amount
		//$invoiceid = "14526321";                      // Invoice ID
		$description = $invoiceid;

		try {
			$charge = Stripe_Charge::create(array(		 
				  "amount" => $amount_cents,
				  "currency" => $currency,
				  "source" => $_POST['stripeToken'],
				  "description" => $description)			  
			);

			if ($charge->card->address_zip_check == "fail") {
				throw new Exception("zip_check_invalid");
			} else if ($charge->card->address_line1_check == "fail") {
				throw new Exception("address_check_invalid");
			} else if ($charge->card->cvc_check == "fail") {
				throw new Exception("cvc_check_invalid");
			}
			// Payment has succeeded, no exceptions were thrown or otherwise caught				

			# Get Returned Variables
			$transid = $charge->id;       //
			$amount = $charge->amount /100;       //
			$fee = 0;
			
			$result = "success";

		} catch(Stripe_CardError $e) {			

			$error = $e->getMessage();
				$result = "declined";

			} catch (Stripe_InvalidRequestError $e) {
				$result = "declined";		  
			} catch (Stripe_AuthenticationError $e) {
				$result = "declined";
			} catch (Stripe_ApiConnectionError $e) {
				$result = "declined";
			} catch (Stripe_Error $e) {
				$result = "declined";
			} catch (Exception $e) {

				if ($e->getMessage() == "zip_check_invalid") {
					$result = "declined";
				} else if ($e->getMessage() == "address_check_invalid") {
					$result = "declined";
				} else if ($e->getMessage() == "cvc_check_invalid") {
					$result = "declined";
				} else {
					$result = "declined";
				}		  
			}
			
		if($result=="success")
		{
			$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
			checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does
			addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule);
			logTransaction($GATEWAY["name"],$charge,"Successful");
			//echo "Success";
			header('Location: '.$_SERVER['REQUEST_URI']);	
		}else
		{
			logTransaction($GATEWAY["name"],$_POST,$e->getMessage());	
		}
	}
	else
	{
		$code='
		<form action="" method="POST">
		  <script
			src="https://checkout.stripe.com/checkout.js" class="stripe-button"
			data-key="'.$pubkey.'"
			data-amount="'.$amount_cents.'"
			data-name="'.$companyname.'"
			data-description="'.$description.'"
			data-image="images/stripe.png"
			data-locale="auto"
			data-zip-code="true">
		  </script>
		</form>
		
		';	
		return $code;
	}	
}