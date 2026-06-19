"use strict";
let stripe;

jQuery(document).ready(function($) {
  $(".proceed_to_book").prop("disabled", true);
  const postData = {
    action: 'vrb_process_checkout_stripe_key',
    security: vrb_checkout_page_global_vars.checkout_nonce, // eslint-disable-line camelcase -- name matches the wp_localize_script object.
  };

  $.ajax({
    type: "POST",
    data: postData,
    url: vrb_frontend_global_vars.ajaxurl, // eslint-disable-line camelcase -- name matches the wp_localize_script object.
    dataType: "json",
    success(response) {
      if (response.success && response.data.key) {
        // If the key is found, initialize Stripe
        initializeStripe(response.data.key);
      } else {
        // eslint-disable-next-line no-console -- intentional debug logging.
        console.log('Stripe key not found or invalid.');
      }
    }
  }).fail(function(data) {
    if (window.console && window.console.log) {
      // eslint-disable-next-line no-console -- intentional debug logging.
      console.log(data);
    }
  });
});

function initializeStripe(publishableKey) { 
  
  stripe = Stripe(publishableKey);

  const elements = stripe.elements({
    fonts: [
      {
        cssSrc: "https://fonts.googleapis.com/css?family=Quicksand",
      },
    ],
    locale: window.__exampleLocale,
  });

  const elementStyles = {
    base: {
      color: "#162843",
      fontWeight: 600,
      fontFamily: "Quicksand, Open Sans, Segoe UI, sans-serif",
      fontSize: "16px",
      fontSmoothing: "antialiased",
  
      ":focus": {
        color: "#424770",
      },
  
      "::placeholder": {
        color: "#9BACC8",
      },
  
      ":focus::placeholder": {
        color: "#CFD7DF",
      },
    },
    invalid: {
      color: "#162843",
      ":focus": {
        color: "#FA755A",
      },
      "::placeholder": {
        color: "#FFCCA5",
      },
    },
  };
  
  const elementClasses = {
    focus: "focus",
    empty: "empty",
    invalid: "invalid",
  };

  const cardNumber = elements.create("cardNumber", {
    style: elementStyles,
    classes: elementClasses,
    showIcon: true,
  });
  cardNumber.mount("#card-number");

  const cardExpiry = elements.create("cardExpiry", {
    style: elementStyles,
    classes: elementClasses,
  });
  cardExpiry.mount("#card-expiry");

  const cardCvc = elements.create("cardCvc", {
    style: elementStyles,
    classes: elementClasses,
  });
  cardCvc.mount("#card-cvc");

  registerElements([cardNumber, cardExpiry, cardCvc], "property-checkout-stripe");

  function registerElements(stripeElements, divClassName) {
    const formClass = "." + divClassName;
    const frmClassObj = document.querySelector(formClass);
    const form = frmClassObj.querySelector("form");
    const error = form.querySelector(".error");
    const errorMessage = error.querySelector(".message");
  
    function enableInputs() {
      Array.prototype.forEach.call(
        form.querySelectorAll(
          "input[type='text'], input[type='email'], input[type='tel']"
        ),
        function (input) {
          input.removeAttribute("disabled");
        }
      );
    }
  
    function disableInputs() {
      Array.prototype.forEach.call(
        form.querySelectorAll(
          "input[type='text'], input[type='email'], input[type='tel']"
        ),
        function (input) {
          input.setAttribute("disabled", "true");
        }
      );
    }
  
    function triggerBrowserValidation() {
      // The only way to trigger HTML5 form validation UI is to fake a user submit
      // event.
      const submit = document.createElement("input");
      submit.type = "submit";
      submit.style.display = "none";
      form.appendChild(submit);
      // submit.click();
      submit.remove();
    }

    // Listen for errors from each Element, and show error messages in the UI.
    const savedErrors = {};
  //   jQuery('.proceed_to_book').prop('disabled', true);
    stripeElements.forEach(function (element, idx) {
      element.on("change", function (event) {
        if (event.error) {
          error.classList.add("visible");
          savedErrors[idx] = event.error.message;
          errorMessage.innerText = event.error.message;
          jQuery(".proceed_to_book_property").prop("disabled", true);
        } else {
          jQuery(".proceed_to_book_property").prop("disabled", false);
          savedErrors[idx] = null;
  
          // Loop over the saved errors and find the first one, if any.
          const nextError = Object.keys(savedErrors)
            .sort()
            .reduce(function (maybeFoundError, key) {
              return maybeFoundError || savedErrors[key];
            }, null);
  
          if (nextError) {
            // Now that they've fixed the current error, show another one.
            errorMessage.innerText = nextError;
          } else {
            // The user fixed the last error; no more errors.
            error.classList.remove("visible");
            // Check rental aggrerement check or not
            jQuery(".proceed_to_book_property").prop("disabled", false);
          }
        }
      });
    });

    
    // Listen on the form's 'submit' handler...
    form.addEventListener("submit", function (e) {
      e.preventDefault();
  
      // Trigger HTML5 validation UI on the form if any of the inputs fail
      // validation.
      let plainInputsValid = true;
      Array.prototype.forEach.call(
        form.querySelectorAll("input"),
        function (input) {
          if (input.checkValidity && !input.checkValidity()) {
            plainInputsValid = false;
          }
        }
      );
  
      if (!plainInputsValid) {
        triggerBrowserValidation();
        return;
      }
  
      // Show a loading screen...
      frmClassObj.classList.add("submitting");
  
      // Disable all inputs.
      disableInputs();
  
      // Gather additional customer data we may have collected in our form.
      const address1 = jQuery("#address1").val();
      const address2 = jQuery("#address2").val();
      const city = jQuery("#city").val();
      const state = jQuery("#state").val();
      const zip = jQuery("#postal_code").val();
      const country = jQuery("#country").val();
      const nameOfCard = jQuery('#name_on_card').val();

      const additionalData = {
        billing_details: {
          name: nameOfCard,
          address: {
            line1: address1,
            line2: address2,
            city,
            state,
            postal_code: zip,
            country
          }
        },
      };
  
      stripe.createPaymentMethod('card', cardNumber, additionalData).then(function (result) {
        frmClassObj.classList.remove("submitting");
        if (result.paymentMethod) {

          // eslint-disable-next-line no-console -- intentional debug logging.
          console.log(result.paymentMethod);

          frmClassObj.querySelector(".stripe_token").value = result.paymentMethod.id;
          chekoutPayment();
        } else {
          enableInputs();
          jQuery('.checkout-error').text(result.error.message).show();
        }
      });
  
    });
  }

}



(function () {
  "use strict";
})();

function chekoutPayment() {
  const $ = jQuery;

  const postData = {
    action : 'vrb_book_property',
    property_data : $('#availability_form').serialize(),
    customer_data : $('#customer_data').serialize(),
    payment_data : $('#payment_data').serialize()
  };

  $('.checkout-payment-wrap').waitMe({
    effect : 'bounce',
    text : 'Please wait while we are booking your property...',
    bg : 'rgba(255,255,255,0.7)',
    color : '#000000',
    maxSize : '',
    waitTime : -1,
    textPos : 'vertical',
    fontSize : '',
    source : '',
    onClose() {}
  });

  jQuery( ".proceed_to_book_property" ).prop( "disabled", true );

   jQuery.ajax({
    type: "POST",
    data: postData,
    url: vrb_frontend_global_vars.ajaxurl, // eslint-disable-line camelcase -- name matches the wp_localize_script object.
    dataType: "json",
    success(response) {
      if (response.success) {
         window.location.href = response.data.redirect;
         jQuery('.checkout-infomaton-side, .checkout-payment-side').waitMe("hide");
      } else {
        $('.checkout-error').text(response.data.message).show();
        $('.checkout-payment-wrap').waitMe("hide");
        jQuery( ".proceed_to_book_property" ).prop( "disabled", false );
      }
    },
  }).fail(function () {
    jQuery('.checkout-infomaton-side, .checkout-payment-side').waitMe("hide");
  });

}
