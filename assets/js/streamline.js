class VRBPropertyForm {
    constructor() {
        this.init();
        document.addEventListener("DOMContentLoaded", () => {
            this.form = document.forms.availability_form;
        });
        
    }

    // Initialize event listeners
    init() {
        const $ = jQuery;

        $(document.body).on('vrb_check_availability', (e, dataResponse) => {
            this.handleCheckAvailability(dataResponse);
        });

        $(document).ready(() => {
            this.setupFormHandler();
        });

    }

    // Handle the vrb_check_availability event
    handleCheckAvailability(dataResponse) {
        if (dataResponse.total_amount > 0) {
            jQuery("#total_payment").val(dataResponse.total_amount);
        }
        if (typeof dataResponse.total_payable_amount !== 'undefined') {
            jQuery("#total_payable_amount").val(dataResponse.total_payable_amount);
        }
    }

    // Set up the form submit handler
    setupFormHandler() {
        if (this.form) {
            this.form.addEventListener('submit', (event) => this.handleFormSubmit(event));
        }
    }

    // Validate the availability dates
    validateAvailabilityDates() {
        const $ = jQuery;
        const arrivalField = $('#CheckIn');

        if (arrivalField.datepicker('getDate') === null) {
            arrivalField.datepicker("show");
            return false;
        }

        const departureField = $('#CheckOut');
        if (departureField.datepicker('getDate') === null) {
            departureField.datepicker("show");
            return false;
        }
        return true;
    }

    // Handle form submission
    handleFormSubmit(event) {
        event.preventDefault();
        if (this.validateAvailabilityDates()) {
            const formData = new FormData(this.form);

            const markedCheckbox = document.querySelectorAll('input[type="checkbox"]:checked');
            const extraAddon = Array.from(markedCheckbox).map((checkbox) => checkbox.id);
            const extraFee = extraAddon.join('|');

            const queryString = new URLSearchParams(formData).toString() + "&fees_ids=" + extraFee;

            const couponBox = jQuery('input[name=coupon_box]').length > 0 ? jQuery('input[name=coupon_box]').val() : '';

            const searchParameter = {
                'check_in': jQuery('#CheckIn').val(),
                'check_out': jQuery('#CheckOut').val(),
                'guests': '',
                'bathrooms': '',
                'bedrooms': '',
                'amenities': '',
                'types': '',
                'sortby': '',
                'property_id': jQuery('input[name=property_id]').val(),
                'unit_id': jQuery('input[name=unit_id]').val(),
                'adults': jQuery('#select_adults').val(),
                'children': jQuery('#select_children').val(),
                'fees_ids': extraFee,
                'coupon_box': couponBox
            };

            this.setCookie('STYXKEY_search_parameter', JSON.stringify(searchParameter), 1);

            const url = vrb_frontend_single_page_global_vars.checkout_page_url; // eslint-disable-line camelcase -- name matches the wp_localize_script object.
            const finalURL = url + "?" + queryString;
            window.location.href = finalURL;
        } else {
            this.resetAvailabilityContent();
        }
    }

    // Reset availability content
    resetAvailabilityContent() {
        // eslint-disable-next-line no-console -- intentional debug logging.
        console.log("Resetting availability content...");
        // Add your reset logic here
    }

    // Set a cookie
    setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

}

// Instantiate the class
new VRBPropertyForm();
