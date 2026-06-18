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
        var stripe_publishable_key = jQuery('#stripe_publishable_key').val();

        if(stripe_publishable_key && stripe_publishable_key != ""){
        }else{
            jQuery('#stripe_publishable_key_error').remove();
            jQuery('#publishable_key').after('<label id="stripe_publishable_key_error" class="error" for="stripe_publishable_key_error">Publishable Key is required.</label>');
            return false;
        }

        var postData = {
            action : 'save_streamline_api_key',
            publishable_key:  stripe_publishable_key,
            type : 'stripe-key'
        };

        jQuery.ajax({
            type: "POST",
            data: postData,
            url: ajax_streamline_params.ajax_url,
            dataType: "json",
            success: function (response) {
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
                console.log( data );
            }
        });
    }

    StreamlinedisconnectAPIKey(){
        const element = jQuery(event.currentTarget);
        const type = element.data('type');

        if(type == 'stripe-key'){
            jQuery('#disconnect_to_stripe').text('disconnecting..');
        }

        var postData = {
            action : 'disconnect_streamline_api_key',
            type:type,
        };

        jQuery.ajax({
            type: "POST",
            data: postData,
            url: ajax_streamline_params.ajax_url,
            dataType: "json",
            success: function (response) {
             
                if(type == 'stripe-key'){
                    jQuery('#disconnect_to_stripe').text('Disconnect');
                }
               
                if( response.success ){
                    location.reload();
                } 
            }
        }).fail(function (data) {
            if ( window.console && window.console.log ) {
                console.log( data );
            }
        });
    }

}

    // Instantiate the class when the document is ready
jQuery(document).ready(function($) {
    new StreamlineScript();
});
