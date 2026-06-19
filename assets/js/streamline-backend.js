class StreamlineScript {
    constructor() {
        this.init();
    }

    init() {
        // Attach click event to the save button
        jQuery('#connect_to_stripe').on('click', this.StreamlinestripeAPIModel.bind(this));
        jQuery('#save_stripe_key').on('click', this.StreamlinestripeAPIkey.bind(this));
        jQuery('#disconnect_to_stripe').on('click', this.StreamlinedisconnectAPIKey.bind(this));
        jQuery(document.body).on('click', '.close-modal', function() {
			jQuery('body').removeClass('modal-open');
		});
    }

    StreamlinestripeAPIModel(){
        jQuery('body').addClass('modal-open');
    }

    StreamlinestripeAPIkey(){
        const stripePublishableKey = jQuery('#stripe_publishable_key').val();

        if(stripePublishableKey && stripePublishableKey !== ""){
        }else{
            jQuery('#stripe_publishable_key_error').remove();
            jQuery('#publishable_key').after('<label id="stripe_publishable_key_error" class="error" for="stripe_publishable_key_error">Publishable Key is required.</label>');
            return false;
        }

        const postData = {
            action : 'save_streamline_api_key',
            publishable_key:  stripePublishableKey,
            type : 'stripe-key',
            security: ajax_streamline_params.ajax_nonce // eslint-disable-line camelcase -- name matches the wp_localize_script object.
        };

        jQuery.ajax({
            type: "POST",
            data: postData,
            url: ajax_streamline_params.ajax_url, // eslint-disable-line camelcase -- name matches the wp_localize_script object.
            dataType: "json",
            success(response) {
                if( response.success ){
                    jQuery('.stripe_message').text('Key saved successfully in encrypted format.');
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {

                }
            }
        }).fail(function (data) {
            this.prop('disabled', false);
            if ( window.console && window.console.log ) {
                // eslint-disable-next-line no-console -- intentional debug logging on AJAX failure.
                console.log( data );
            }
        });
    }

    StreamlinedisconnectAPIKey(){
        const element = jQuery(event.currentTarget);
        const type = element.data('type');

        if(type === 'stripe-key'){
            jQuery('#disconnect_to_stripe').text('disconnecting..');
        }

        const postData = {
            action : 'disconnect_streamline_api_key',
            type,
            security: ajax_streamline_params.ajax_nonce // eslint-disable-line camelcase -- name matches the wp_localize_script object.
        };

        jQuery.ajax({
            type: "POST",
            data: postData,
            url: ajax_streamline_params.ajax_url, // eslint-disable-line camelcase -- name matches the wp_localize_script object.
            dataType: "json",
            success(response) {

                if(type === 'stripe-key'){
                    jQuery('#disconnect_to_stripe').text('Disconnect');
                }

                if( response.success ){
                    location.reload();
                }
            }
        }).fail(function (data) {
            if ( window.console && window.console.log ) {
                // eslint-disable-next-line no-console -- intentional debug logging on AJAX failure.
                console.log( data );
            }
        });
    }

}

    // Instantiate the class when the document is ready
jQuery(document).ready(function() {
    new StreamlineScript();
});
