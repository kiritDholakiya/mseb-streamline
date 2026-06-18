"use strict";
var stripe;

jQuery(document).ready(function($) {
  $(".proceed_to_book").prop("disabled", true);
  var postData = {
    action: 'vrb_process_checkout_stripe_key',
    security: vrb_checkout_page_global_vars.checkout_nonce,
  };

  $.ajax({
    type: "POST",
    data: postData,
    url: vrb_frontend_global_vars.ajaxurl,
    dataType: "json",
    success: function(response) {
      if (response.success && response.data.key) {
        // If the key is found, initialize Stripe
        initializeStripe(response.data.key);
      } else {
        console.log('Stripe key not found or invalid.');
      }
    }
  }).fail(function(data) {
    if (window.console && window.console.log) {
      console.log(data);
    }
  });
});

function initializeStripe(publishableKey) { 
  
  stripe = Stripe(publishableKey);

  var elements = stripe.elements({
    fonts: [
      {
        cssSrc: "https://fonts.googleapis.com/css?family=Quicksand",
      },
    ],
    locale: window.__exampleLocale,
  });

  var elementStyles = {
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
  
  var elementClasses = {
    focus: "focus",
    empty: "empty",
    invalid: "invalid",
  };
  
  var cardNumber = elements.create("cardNumber", {
    style: elementStyles,
    classes: elementClasses,
    showIcon: true,
  });
  cardNumber.mount("#card-number");
  
  var cardExpiry = elements.create("cardExpiry", {
    style: elementStyles,
    classes: elementClasses,
  });
  cardExpiry.mount("#card-expiry");
  
  var cardCvc = elements.create("cardCvc", {
    style: elementStyles,
    classes: elementClasses,
  });
  cardCvc.mount("#card-cvc");
  
  registerElements([cardNumber, cardExpiry, cardCvc], "property-checkout-stripe");

  function registerElements(elements, divClassName) {
    var formClass = "." + divClassName;
    var frmClassObj = document.querySelector(formClass);
    var form = frmClassObj.querySelector("form");
    var error = form.querySelector(".error");
    var errorMessage = error.querySelector(".message");
  
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
      var submit = document.createElement("input");
      submit.type = "submit";
      submit.style.display = "none";
      form.appendChild(submit);
      // submit.click();
      submit.remove();
    }
  
    // Listen for errors from each Element, and show error messages in the UI.
    var savedErrors = {};
  //   jQuery('.proceed_to_book').prop('disabled', true);
    elements.forEach(function (element, idx) {
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
          var nextError = Object.keys(savedErrors)
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
      var plainInputsValid = true;
      Array.prototype.forEach.call(
        form.querySelectorAll("input"),
        function (input) {
          if (input.checkValidity && !input.checkValidity()) {
            plainInputsValid = false;
            return;
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
      var name = jQuery("#first_name").val() +' '+jQuery("#last_name").val();
      var address1 = jQuery("#address1").val();
      var address2 = jQuery("#address2").val();
      var city = jQuery("#city").val();
      var state = jQuery("#state").val();
      var zip = jQuery("#postal_code").val();
      var country = jQuery("#country").val();
      var name_of_card = jQuery('#name_on_card').val();
  
      var additionalData = {
        billing_details: {
          name: name_of_card,
          address: {
            line1: address1,
            line2: address2,
            city: city,
            state: state,
            postal_code: zip,
            country: country
          }
        },
      };
  
      stripe.createPaymentMethod('card', cardNumber, additionalData).then(function (result) {
        frmClassObj.classList.remove("submitting");
        console.log("EWferf");
        if (result.paymentMethod) {

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

function chekoutPayment(params) {
  var $ = jQuery;

  var postData = {
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
    onClose : function() {}
  });

  jQuery( ".proceed_to_book_property" ).prop( "disabled", true );
  
   jQuery.ajax({
    type: "POST",
    data: postData,
    url: vrb_frontend_global_vars.ajaxurl,
    dataType: "json",
    success: function (response) {
      if (response.success) {
         window.location.href = response.data.redirect;
         jQuery('.checkout-infomaton-side, .checkout-payment-side').waitMe("hide");
      } else {
        $('.checkout-error').text(response.data.message).show();
        $('.checkout-payment-wrap').waitMe("hide");
        jQuery( ".proceed_to_book_property" ).prop( "disabled", false );
      }
    },
  }).fail(function (data) {
    jQuery('.checkout-infomaton-side, .checkout-payment-side').waitMe("hide");
  });

  
}
