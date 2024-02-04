jQuery(function ($) {
  "use strict";

  let nmi_error = {},
    card_allowed;

  /**
   * Object to handle NMI payment forms.
   */
  let wc_nmi_form = {
    /**
     * Creates all NMI elements that will be used to enter cards or IBANs.
     */
    createElements: function () {
      if (this.three_ds_interface !== undefined) {
        this.three_ds_interface.unmount();
      }

      this.form.find('[type="submit"]').removeAttr("disabled").unblock();
      this.form.find(".nmi-source-errors").html("");

      //wc_nmi_form.onCCFormChange();
      const customCss = !(
        $("#cfw-payment-method").length ||
        $(".woolentor-step--payment").length ||
        $(".avada-checkout").length ||
        $(".ro-checkout-process").length
      )
        ? {}
        : {
            height: "30px",
          };
      //console.log("customCss");
      const req = {
        country: $("#billing_country").length
          ? $("#billing_country").val()
          : wc_nmi_params.billing_country,
        currency: wc_nmi_params.currency,
        price: wc_nmi_form
          .getSelectedPaymentElement()
          .closest("li")
          .find("#nmi-payment-data")
          .attr("data-amount"),
      };
      CollectJS.configure({
        //"paymentSelector" : "#place_order",
        variant: "inline",
        country: $("#billing_country").length
          ? $("#billing_country").val()
          : wc_nmi_params.billing_country,
        currency: wc_nmi_params.currency,
        price: wc_nmi_form
          .getSelectedPaymentElement()
          .closest("li")
          .find("#nmi-payment-data")
          .attr("data-amount"),
        styleSniffer: "true",
        customCss: customCss,
        //"googleFont": "Montserrat:400",
        fields: {
          ccnumber: {
            selector: "#nmi-card-number-element",
            placeholder: "•••• •••• •••• ••••",
          },
          ccexp: {
            selector: "#nmi-card-expiry-element",
            placeholder: wc_nmi_params.placeholder_expiry,
          },
          cvv: {
            display: "required",
            selector: "#nmi-card-cvc-element",
            placeholder: wc_nmi_params.placeholder_cvc,
          },
          checkaccount: {
            selector: "#nmi-echeck-account-number-element",
            placeholder: "••••••••••",
          },
          checkaba: {
            selector: "#nmi-echeck-routing-number-element",
            placeholder: "••••••••••",
          },
          checkname: {
            selector: "#nmi-echeck-account-name-element",
          },
        },
        validationCallback: function (field, status, message) {
          if (status) {
            message = field + " is OK: " + message;
            nmi_error[field] = "";
          } else {
            nmi_error[field] = message;
          }
          console.log(message);
        },
        timeoutDuration: 20000,
        timeoutCallback: function () {
          $(document).trigger("nmiError", wc_nmi_params.timeout_error);
        },
        fieldsAvailableCallback: function () {
          wc_nmi_form.unblock();
          console.log("Collect.js loaded the fields onto the form");
        },
        callback: function (response) {
          wc_nmi_form.onNMIResponse(response);
        },
      });
    },
    createExpressElements: function () {
      if (!wc_nmi_params.googlepay_enable && !wc_nmi_params.applepay_enable) {
        console.log("NMI express payments disabled");
        return false;
      }
      if (this.three_ds_interface !== undefined) {
        this.three_ds_interface.unmount();
      }
      const req = {
        country: $("#billing_country").length
          ? $("#billing_country").val()
          : wc_nmi_params.billing_country,
        currency: wc_nmi_params.currency,
        price: wc_nmi_form
          .getSelectedPaymentElement()
          .closest("ul.payment_methods")
          .find(".payment_method_nmi #nmi-payment-data")
          .attr("data-amount"),
      };
      let googlepay = {};
      if (wc_nmi_params.googlepay_enable) {
        googlepay = {
          emailRequired: true,
          selector: "#wc-nmi-googlepay",
        };
        if (wc_nmi_params.googlepay_billing_shipping) {
          googlepay.billingAddressRequired = true;
          googlepay.billingAddressParameters = {
            phoneNumberRequired: true,
            format: "FULL",
          };
          if (wc_nmi_params.needs_shipping_address) {
            googlepay.shippingAddressRequired = true;
            googlepay.shippingAddressParameters = {
              allowedCountryCodes: wc_nmi_params.shipping_countries,
            };
          }
        }
      }
      let applepay = {};
      if (wc_nmi_params.applepay_enable) {
        applepay = {
          //'billingAddressRequired': false,
          //'emailRequired': true,
          contactFields: ["phone", "email"],
          selector: "#wc-nmi-applepay",
          //'shippingAddressRequired' : false
        };
        /*if( wc_nmi_params.googlepay_billing_shipping ) {
					applepay.billingAddressRequired = true;
					applepay.billingAddressParameters = {
						'phoneNumberRequired': true,
						'format': 'FULL'
					};
					if( wc_nmi_params.needs_shipping_address ) {
						applepay.shippingAddressRequired = true;
						applepay.shippingAddressParameters = {
							'allowedCountryCodes': wc_nmi_params.shipping_countries
						};
					}
				}*/
      }
      CollectJS.configure({
        //"paymentSelector" : "#place_order",
        variant: "inline",
        country: $("#billing_country").length
          ? $("#billing_country").val()
          : wc_nmi_params.billing_country,
        currency: wc_nmi_params.currency,
        price: wc_nmi_form
          .getSelectedPaymentElement()
          .closest("ul.payment_methods")
          .find(".payment_method_nmi #nmi-payment-data")
          .attr("data-amount"),
        //"googleFont": "Montserrat:400",
        fields: {
          googlePay: googlepay,
          applePay: applepay,
        },
        validationCallback: function (field, status, message) {
          if (status) {
            message = field + " is OK: " + message;
            nmi_error[field] = "";
          } else {
            nmi_error[field] = message;
          }
          console.log(message);
        },
        timeoutDuration: 20000,
        timeoutCallback: function () {
          $(document).trigger("nmiError", wc_nmi_params.timeout_error);
        },
        fieldsAvailableCallback: function () {
          wc_nmi_form.unblock();
          console.log("Express loaded the fields onto the form");
        },
        callback: function (response) {
          wc_nmi_form.onNMIResponse(response);
        },
      });
    },

    /**
     * Initialize event handlers and UI state.
     */
    init: function () {
      if (this.three_ds_interface !== undefined) {
        this.three_ds_interface.unmount();
      }

      // checkout page
      if ($("form.woocommerce-checkout").length) {
        this.form = $("form.woocommerce-checkout");
      }

      $("form.woocommerce-checkout").on(
        "checkout_place_order_nmi",
        this.onSubmit
      );

      $("form.woocommerce-checkout").on(
        "checkout_place_order_nmi-echeck",
        this.onSubmit
      );

      // pay order page
      if ($("form#order_review").length) {
        this.form = $("form#order_review");
      }

      $("form#order_review").on("submit", this.onSubmit);

      // add payment method page
      if ($("form#add_payment_method").length) {
        this.form = $("form#add_payment_method");
        // Payment methods
        this.form.on("click", 'input[name="payment_method"]', function () {
          $(document.body).trigger("payment_method_selected");
        });
      }

      $("form#add_payment_method").on("submit", this.onSubmit);

      $(document)
        .on("change", "#wc-nmi-cc-form :input", this.onCCFormChange)
        .on("nmiError", this.onError)
        .on("checkout_error", this.clearToken);

      if (wc_nmi_form.isNMIChosen() || wc_nmi_form.isNMIeCheckChosen()) {
        wc_nmi_form.block();
        wc_nmi_form.createElements();
      }

      // CheckoutWC and woolentor, La Forat theme
      $("body").on(
        "click",
        'a[href="#cfw-payment-method"], a[data-tab="#cfw-payment-method"], a[data-step="step--payment"], a.ro-tab-2, a.ro-btn-2',
        function () {
          // Don't re-mount if already mounted in DOM.
          wc_nmi_form.createExpressElements();
          if (wc_nmi_form.isNMIChosen() || wc_nmi_form.isNMIeCheckChosen()) {
            wc_nmi_form.block();
            wc_nmi_form.createElements();
          }
        }
      );

      /**
       * Only in checkout page we need to delay the mounting of the
       * card as some AJAX process needs to happen before we do.
       */
      if ("yes" === wc_nmi_params.is_checkout) {
        $(document.body).on("updated_checkout", function () {
          // Re-mount on updated checkout
          wc_nmi_form.createExpressElements();
          if (wc_nmi_form.isNMIChosen() || wc_nmi_form.isNMIeCheckChosen()) {
            wc_nmi_form.block();
            wc_nmi_form.createElements();
          }
        });
      }

      $(document.body).on("payment_method_selected", function () {
        console.log("payment_method_selected");
        wc_nmi_form.createExpressElements();
        // Don't re-mount if already mounted in DOM.
        if (wc_nmi_form.isNMIChosen() || wc_nmi_form.isNMIeCheckChosen()) {
          wc_nmi_form.block();
          wc_nmi_form.createElements();
        }
      });

      if (this.form !== undefined) {
        this.form.on(
          "click change",
          'input[name="wc-nmi-payment-token"]',
          function () {
            if (
              wc_nmi_form.isNMIChosen() &&
              !$("#nmi-card-number-element").children().length
            ) {
              wc_nmi_form.block();
              wc_nmi_form.createElements();
            }
          }
        );

        this.form.on(
          "click change",
          'input[name="wc-nmi-echeck-payment-token"]',
          function () {
            if (
              wc_nmi_form.isNMIeCheckChosen() &&
              !$("#nmi-echeck-account-number-element").children().length
            ) {
              wc_nmi_form.block();
              wc_nmi_form.createElements();
            }
          }
        );
      }
    },

    isNMIChosen: function () {
      return (
        0 < $("#nmi-card-number-element").length &&
        $("#payment_method_nmi").is(":checked") &&
        (!$('input[name="wc-nmi-payment-token"]:checked').length ||
          "new" === $('input[name="wc-nmi-payment-token"]:checked').val())
      );
    },

    isNMIeCheckChosen: function () {
      return (
        0 < $("#nmi-echeck-account-number-element").length &&
        $("#payment_method_nmi-echeck").is(":checked") &&
        (!$('input[name="wc-nmi-echeck-payment-token"]:checked').length ||
          "new" ===
            $('input[name="wc-nmi-echeck-payment-token"]:checked').val())
      );
    },

    hasToken: function () {
      return (
        0 < $("input.nmi_js_token").length &&
        0 < $("input.nmi_js_response").length
      );
    },

    is3DSEnabled: function () {
      return wc_nmi_params.enable_3ds && wc_nmi_params.checkout_key !== "";
    },

    block: function () {
      wc_nmi_form.form.block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });
    },

    unblock: function () {
      wc_nmi_form.form.unblock();
    },

    getSelectedPaymentElement: function () {
      return $('.payment_methods input[name="payment_method"]:checked');
    },

    onError: function (e, result) {
      //console.log(responseObject.response);
      let message = result;
      let selectedMethodElement = wc_nmi_form
        .getSelectedPaymentElement()
        .closest("li");
      let savedTokens = selectedMethodElement.find(
        ".woocommerce-SavedPaymentMethods-tokenInput"
      );
      let errorContainer;

      if (savedTokens.length) {
        // In case there are saved cards too, display the message next to the correct one.
        let selectedToken = savedTokens.filter(":checked");

        if (
          selectedToken.closest(".woocommerce-SavedPaymentMethods-new").length
        ) {
          if (wc_nmi_form.isNMIeCheckChosen()) {
            // Display the error next to the CC fields if a new card is being entered.
            errorContainer = $("#nmi-echeck-cc-form .nmi-source-errors");
          } else {
            // Display the error next to the CC fields if a new card is being entered.
            errorContainer = $("#wc-nmi-cc-form .nmi-source-errors");
          }
        } else {
          // Display the error next to the chosen saved card.
          errorContainer = selectedToken
            .closest("li")
            .find(".nmi-source-errors");
        }
      } else {
        // When no saved cards are available, display the error next to CC fields.
        errorContainer = selectedMethodElement.find(".nmi-source-errors");
      }

      wc_nmi_form.onCCFormChange();
      $(".woocommerce-NoticeGroup-checkout").remove();
      console.log(result); // Leave for troubleshooting.
      $(errorContainer).html(
        '<ul class="woocommerce_error woocommerce-error wc-nmi-error"><li /></ul>'
      );
      $(errorContainer).find("li").text(message); // Prevent XSS

      if ($(".wc-nmi-error").length) {
        $("html, body").animate(
          {
            scrollTop: $(".wc-nmi-error").offset().top - 200,
          },
          200
        );
      }
      wc_nmi_form.unblock();
    },

    onSubmit: function (e) {
      let error_message;

      if (wc_nmi_form.isNMIChosen() && !wc_nmi_form.hasToken()) {
        e.preventDefault();
        wc_nmi_form.block();

        console.log(nmi_error);

        let validCardNumber =
          document.querySelector("#nmi-card-number-element .CollectJSValid") !==
          null;
        let validCardExpiry =
          document.querySelector("#nmi-card-expiry-element .CollectJSValid") !==
          null;
        let validCardCvv =
          document.querySelector("#nmi-card-cvc-element .CollectJSValid") !==
          null;

        if (!validCardNumber) {
          error_message =
            wc_nmi_params.card_number_error +
            (nmi_error.ccnumber
              ? " " +
                wc_nmi_params.error_ref.replace("[ref]", nmi_error.ccnumber)
              : "");
          $(document.body).trigger("nmiError", error_message);
          return false;
        }

        if (!validCardExpiry) {
          error_message =
            wc_nmi_params.card_expiry_error +
            (nmi_error.ccexp
              ? " " + wc_nmi_params.error_ref.replace("[ref]", nmi_error.ccexp)
              : "");
          $(document.body).trigger("nmiError", error_message);
          return false;
        }

        if (!validCardCvv) {
          error_message =
            wc_nmi_params.card_cvc_error +
            (nmi_error.cvv
              ? " " + wc_nmi_params.error_ref.replace("[ref]", nmi_error.cvv)
              : "");
          $(document.body).trigger("nmiError", error_message);
          return false;
        }

        CollectJS.startPaymentRequest();

        // Prevent form submitting
        return false;
      }

      if (wc_nmi_form.isNMIeCheckChosen() && !wc_nmi_form.hasToken()) {
        e.preventDefault();
        wc_nmi_form.block();

        console.log(nmi_error);

        let validAccountName =
          document.querySelector(
            "#nmi-echeck-account-name-element .CollectJSValid"
          ) !== null;
        let validAccountNumber =
          document.querySelector(
            "#nmi-echeck-account-number-element .CollectJSValid"
          ) !== null;
        let validRoutingNumber =
          document.querySelector(
            "#nmi-echeck-routing-number-element .CollectJSValid"
          ) !== null;

        if (!validAccountNumber) {
          error_message =
            wc_nmi_params.echeck_account_number_error +
            (nmi_error.checkaccount
              ? " " +
                wc_nmi_params.error_ref.replace("[ref]", nmi_error.checkaccount)
              : "");
          $(document.body).trigger("nmiError", error_message);
          return false;
        }

        if (!validRoutingNumber) {
          error_message =
            wc_nmi_params.echeck_routing_number_error +
            (nmi_error.checkaba
              ? " " +
                wc_nmi_params.error_ref.replace("[ref]", nmi_error.checkaba)
              : "");
          $(document.body).trigger("nmiError", error_message);
          return false;
        }

        if (!validAccountName) {
          error_message =
            wc_nmi_params.echeck_account_name_error +
            (nmi_error.checkname
              ? " " +
                wc_nmi_params.error_ref.replace("[ref]", nmi_error.checkname)
              : "");
          $(document.body).trigger("nmiError", error_message);
          return false;
        }

        CollectJS.startPaymentRequest();

        // Prevent form submitting
        return false;
      }
    },

    onCCFormChange: function () {
      $(".wc-nmi-error, .nmi_js_token, .nmi_js_response").remove();
    },

    onNMIResponse: function (response) {
      console.log(response);

      if (response.tokenType == "inline" && response.card.type != null) {
        wc_nmi_params.allowed_card_types.forEach(function (card_type) {
          if (
            response.card.type == card_type.replace("diners-club", "diners")
          ) {
            card_allowed = true;
          }
        });

        if (!card_allowed) {
          $(document.body).trigger(
            "nmiError",
            wc_nmi_params.card_disallowed_error
          );
          return false;
        }

        if (this.is3DSEnabled() && !$("form#add_payment_method").length) {
          console.log("3ds is enabled");
          let first_name = $("#billing_first_name").length
              ? $("#billing_first_name").val()
              : wc_nmi_params.billing_first_name,
            last_name = $("#billing_last_name").length
              ? $("#billing_last_name").val()
              : wc_nmi_params.billing_last_name,
            email = $("#billing_email").length
              ? $("#billing_email").val()
              : wc_nmi_params.billing_email,
            phone = $("#billing_phone").length
              ? $("#billing_phone").val()
              : wc_nmi_params.billing_phone,
            city = $("#billing_city").length
              ? $("#billing_city").val()
              : wc_nmi_params.billing_city,
            state = $("#billing_state").length
              ? $("#billing_state").val()
              : wc_nmi_params.billing_state,
            address1 = $("#billing_address_1").length
              ? $("#billing_address_1").val()
              : wc_nmi_params.billing_address_1,
            country = $("#billing_country").length
              ? $("#billing_country").val()
              : wc_nmi_params.billing_country,
            postcode = $("#billing_postcode").length
              ? $("#billing_postcode").val()
              : wc_nmi_params.billing_postcode;

          if (first_name && last_name && email) {
            let gatewayjs = Gateway.create(wc_nmi_params.checkout_key);

            gatewayjs.on("error", function (e) {
              console.log(e);
              $(document).trigger("nmiError", e.message);
            });

            let three_ds = gatewayjs.get3DSecure();

            three_ds.on("error", function (e) {
              console.log(e);
              $(document).trigger("nmiError", e.message);
            });

            //console.log(wc_nmi_form.getSelectedPaymentElement().closest( 'li' ).find( '#nmi-payment-data' ));
            const three_ds_options = {
              paymentToken: response.token,
              challengeIndicator: "04",
              currency: wc_nmi_params.currency,
              amount: wc_nmi_form
                .getSelectedPaymentElement()
                .closest("li")
                .find("#nmi-payment-data")
                .attr("data-amount"),
              email: email,
              firstName: first_name,
              lastName: last_name,
            };
            if (city && address1 && country && postcode) {
              three_ds_options.phone = phone;
              three_ds_options.city = city;
              three_ds_options.state =
                country == "US" || country == "CA" ? state : "";
              three_ds_options.address1 = address1;
              three_ds_options.country = country;
            }
            console.log(three_ds_options);

            wc_nmi_form.three_ds_interface =
              three_ds.createUI(three_ds_options);

            wc_nmi_form.three_ds_interface.on("error", function (e) {
              wc_nmi_form.three_ds_interface.unmount();
              console.log("interface error");
              console.log(e);
              wc_nmi_form.form
                .find('[type="submit"]')
                .removeAttr("disabled")
                .unblock();
              $(document).trigger("nmiError", e.message);
            });

            wc_nmi_form.three_ds_interface.on("failure", function (e) {
              wc_nmi_form.three_ds_interface.unmount();
              console.log("interface failed");
              console.log(e);
              wc_nmi_form.form
                .find('[type="submit"]')
                .removeAttr("disabled")
                .unblock();
              $(document).trigger("nmiError", e.message);
            });

            wc_nmi_form.three_ds_interface.start("#nmi-three-ds-mount-point");

            wc_nmi_form.three_ds_interface.on("challenge", function (e) {
              console.log("Challenged");
              $(document).trigger(
                "nmiError",
                wc_nmi_params.card_3ds_challenge_message
              );
              wc_nmi_form.form
                .find('[type="submit"]')
                .attr("disabled", "disabled")
                .block({ message: null });
            });

            wc_nmi_form.three_ds_interface.on("complete", function (e) {
              const three_ds_response = {
                ...response,
                cavv: e.cavv,
                xid: e.xid,
                eci: e.eci,
                cardholder_auth: e.cardHolderAuth,
                three_ds_version: e.threeDsVersion,
                directory_server_id: e.directoryServerId,
              };
              console.log(three_ds_response);
              $("#nmi-three-ds-mount-point").slideUp(400, function () {
                $(this).html("").show();
              });
              wc_nmi_form.three_ds_interface.unmount();
              wc_nmi_form.form
                .find('[type="submit"]')
                .removeAttr("disabled")
                .unblock();
              wc_nmi_form.form.append(
                "<input type='hidden' class='nmi_js_token' name='nmi_js_token' value='" +
                  three_ds_response.token +
                  "'/>"
              );
              wc_nmi_form.form.append(
                "<input type='hidden' class='nmi_js_response' name='nmi_js_response' value='" +
                  JSON.stringify(three_ds_response) +
                  "'/>"
              );
              wc_nmi_form.form.submit();
            });
            return false;
          }
        }
      }

      if (
        (response.tokenType == "googlePay" ||
          response.tokenType == "applePay") &&
        response.wallet.cardNetwork != null
      ) {
        $("input#payment_method_nmi").prop("checked", true).trigger("click");
        $("input#wc-nmi-payment-token-new")
          .prop("checked", true)
          .trigger("click");
      }

      wc_nmi_form.form.append(
        "<input type='hidden' class='nmi_js_token' name='nmi_js_token' value='" +
          response.token +
          "'/>"
      );
      wc_nmi_form.form.append(
        "<input type='hidden' class='nmi_js_response' name='nmi_js_response' value='" +
          JSON.stringify(response) +
          "'/>"
      );
      wc_nmi_form.form.submit();
    },

    clearToken: function () {
      $(".nmi_js_token, .nmi_js_response").remove();
    },
  };

  wc_nmi_form.init();
});

// get place_order button
const placeOrderButton = document.querySelector("#place_order");

// // if clicked placeOrderButton then prevent default and display a alert message button clicled
// placeOrderButton.addEventListener("click", (event) => {
//   event.preventDefault();
//   alert("button clicked");
// });
