<?php 


function vrb_app_auth_callback($args)
{
	$meta_vlaue = get_option('streamline_token_key');
	
	if ($meta_vlaue == '') {
		$html = '<a href="javascript:void(0);" name="streamline_auth_btn" id="streamline_auth_btn" class="streamline_auth_btn button button-primary">'.$args['name'].'</a>';
	} else {
		$html= '<a href="javascript:void(0);" name="streamline_revoke_auth" id="streamline_revoke_auth" class="streamline_revoke_auth button button-primary"> Disconnect App</a>';
	}
	
	$html.='<!-- Contents of first window --> 
	<style>.white-popup {
		position: relative;
		background: #FFF;
		padding: 40px;
		width: auto;
		max-width: 500px;
		margin: 20px auto;
		text-align: center;
	}
	</style>
	
	';

	echo apply_filters( 'vrb_after_setting_output', $html, $args );
}


handle_auth_call_back();
// handle_auth_call_back
function handle_auth_call_back()
{
	if (isset($_GET['token_key']) && isset($_GET['token_secret']) && isset($_GET['token_url']) && isset($_GET['token_time'])) {

		$code = base64_decode($_GET['token_key']);
		$token_secret = base64_decode($_GET['token_secret']);
		$token_url = base64_decode($_GET['token_url']);
		$token_end_time = base64_decode($_GET['token_time']);
		$type = $_GET['type'];

		update_token_and_redirect($code, $token_secret, $token_url, $token_end_time, $type);
	} 
}

function update_token_and_redirect($code, $token_secret, $token_url, $token_end_time, $type)
{
	
	if ($type == 'streamline') {
		if ($code == '101') {
			update_option('streamline_end_point', '');
			update_option('streamline_token_key','');
			update_option('streamline_token_secret','');
			update_option('streamline_expires_time', '');

		} else if ($code == '202') {
			sleep(5);
			$admin_url = admin_url( 'admin.php?page=vrb-settings#streamline', 'https' );
			header("Location: ".$admin_url);
			exit();
		} else {
			
			// update_option('api_key',$app_id);
			update_option('streamline_end_point', $token_url);
			update_option('streamline_token_key', $code);
			update_option('streamline_token_secret',$token_secret);
			update_option('streamline_expires_time', $token_end_time);	
		}
	
		sleep(5);
		$admin_url = admin_url( 'admin.php?page=vrb-settings#streamline', 'https' );
		header("Location: ".$admin_url);
		exit();
	}	
}


function vrb_stripe_key_callback( $args ) {
    // Retrieve Stripe keys from WordPress options
    $encrypted_publishable_key  = get_option('stripe_publishable_key');

    // Default button attributes
    $class_name = 'connect_to_stripe';
    $id_name = 'connect_to_stripe';
    $name = 'Connect';
    $secret_key_last4 = '';
    $publishable_key_last4 = '';
    if ($encrypted_publishable_key && $encrypted_publishable_key != "") { 
        $class_name = 'disconnect_to_stripe';
        $id_name = 'disconnect_to_stripe';
        $name = 'Disconnect';

        $stripe_publishable_key = base64_decode($encrypted_publishable_key);

        // Get the last 4 digits of the keys
        $publishable_key_last4 = substr($stripe_publishable_key, -4);
    }

    $html = '
    <button type="button"  data-type="stripe-key" id="' . $id_name . '" class="' . $class_name . ' button button-primary">' . $name . '</button>';
    if ($encrypted_publishable_key) {
        $html .= '<p>Stripe Publishable Key: **** **** **** ' . $publishable_key_last4 . '</p>';
    }
    $html .= '<div id="custom-modal" class="custom-modal">
        <div class="custom-modal-dialog">
            <div class="custom-modal-content">
                <span class="close-modal">X</span>
                <div class="custom-modal-body">
                    <div class="custom-modal-inner">
                        <div class="model_title">
                            <h2> Connect key to Stripe </h2>
                        </div>
                        <hr />
                        <form method="POST" id="stripe_to_connect" >
                            <div class="form-group row">
                                <label for="stripe_publishable_key" class="col-sm-2 col-form-label">Publishable Key</label>
                                <div class="col-sm-10" id="publishable_key">
                                    <input type="text" class="form-control" id="stripe_publishable_key" placeholder="Publishable Key">
                                </div>
                            </div>
                            <br />
                            <div>
                                <button id="save_stripe_key" class="save_stripe_key button button-primary" type="button"> Submit </button>
                            </div>
                            <p class="stripe_message"></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    echo apply_filters( 'vrb_after_setting_output', $html, $args );
}