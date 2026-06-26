/**
 * BIN API Frontend JavaScript - Combined Implementation
 * Handles both automatic BIN detection and manual card details selection
 *
 * @version 2.19.0
 * @since   2.18.0
 */

(function($) {
    'use strict';

    var AlgBinApi = {
        
        // Configuration
        config: {
            cardNumberSelectors: [],
            debounceDelay: 500,
            minBinLength: 6,
            cache: {},
            lastProcessedBin: null,
            isProcessing: false,
            currentPaymentMethod: null,
            isClearing: false,
            hasCardData: false,
            isUpdatingFees: false,
            cardPaymentGateways: [],
            manualOverride: false, // Flag to track if user manually changed fields
            autoDetectedData: null, // Store auto-detected data
            debounceTimer: null
        },

        // Initialize the module
        init: function() {
            // Get card payment gateways from params
            this.config.cardPaymentGateways = alg_bin_api_params.card_payment_gateways || [];
            this.config.cardNumberSelectors = alg_bin_api_params.card_number_selectors || [];
            this.bindEvents();
            this.debug('Combined BIN API module initialized');
            
            // Initialize payment fields visibility
            setTimeout(() => this.togglePaymentFields(), 500);
        },

        // Bind all events
        bindEvents: function() {
            var self = this;
            
            // Remove existing listeners to prevent duplicates
            $(document.body).off('.binapi');
            
            // Handle payment method changes
            $(document.body).on('change.binapi', 'input[name="payment_method"]', function() {
                var newPaymentMethod = $(this).val();
                self.debug('Payment method changed to: ' + newPaymentMethod);
                self.handlePaymentMethodChange(newPaymentMethod);
                self.togglePaymentFields();
            });
            
            // Handle checkout updates
            $(document.body).on('updated_checkout.binapi', function() {
                self.debug('Checkout updated');
                setTimeout(function() {
                    self.refreshCardListeners();
                    self.togglePaymentFields();
                }, 200);
            });

            // Handle payment method selection events
            $(document.body).on('payment_method_selected.binapi', function() {
                self.debug('Payment method selected event');
                setTimeout(function() {
                    self.refreshCardListeners();
                }, 100);
            });

            // Handle manual card detail changes
            $(document).on('change.binapi', '#pgbf_card_scheme, #pgbf_card_type, #pgbf_bank_name, #pgbf_card_location', function( event, isAutoDetected ) {
                self.debug('Manual card field changed');
                //if (!isAutoDetected) {
                    self.config.manualOverride = true;
                    self.handleManualFieldChange();
                //}
            });

            // Initial setup
            this.refreshCardListeners();
        },

        // Handle payment method change
        handlePaymentMethodChange: function(newPaymentMethod) {
            var self = this;
            
            if (this.config.currentPaymentMethod !== newPaymentMethod) {
                this.debug('Payment method switched from ' + this.config.currentPaymentMethod + ' to ' + newPaymentMethod);
                
                // Clear current card data
                this.clearCardData(function() {
                    self.config.currentPaymentMethod = newPaymentMethod;
                    self.config.lastProcessedBin = null;
                    self.config.manualOverride = false;
                    self.config.autoDetectedData = null;
                    
                    // Refresh listeners for new payment method
                    setTimeout(function() {
                        self.refreshCardListeners();
                    }, 200);
                });
            }
        },

        // Toggle payment fields visibility based on selected payment method
        togglePaymentFields: function() {
            var selectedPayment = $("input[name=\"payment_method\"]:checked").val();
            
            // Prevent unnecessary calls if payment method hasn't changed
            if (this.config.currentPaymentMethod === selectedPayment) {
                return;
            }
            
            this.debug("Selected Payment Method: " + selectedPayment);
            this.config.currentPaymentMethod = selectedPayment;
            
            var showFields = this.config.cardPaymentGateways.includes(selectedPayment);
            var isCurrentlyVisible = $("#payment-fees-section").is(":visible");
            
            // Only toggle if state actually needs to change
            if (showFields && !isCurrentlyVisible) {
                $("#payment-fees-section").slideDown(200);
                $("#pgbf_card_scheme, #pgbf_card_type, #pgbf_bank_name, #pgbf_card_location").attr("required", true);
            } else if (!showFields && isCurrentlyVisible) {
                $("#payment-fees-section").slideUp(200);
                $("#pgbf_card_scheme, #pgbf_card_type, #pgbf_bank_name, #pgbf_card_location").attr("required", false);
                // Clear values and fees only if switching away from card payment
                this.clearManualFields();
                this.clearPaymentFeesDebounced();
            }
        },

        // Handle manual field changes
        handleManualFieldChange: function() {
            var self = this;
            clearTimeout(this.config.debounceTimer);
            this.config.debounceTimer = setTimeout(function() {
                self.updatePaymentFees();
            }, 300);
        },

        // Update payment fees based on current field values
        updatePaymentFees: function() {
            if (this.config.isUpdatingFees) return;
            
            var selectedPayment = $("input[name=\"payment_method\"]:checked").val();
            
            var self = this;
            // Only proceed if a card payment gateway is selected
            /* if (!this.config.cardPaymentGateways.includes(selectedPayment)) {
                return;
            } */
            
            var scheme = $("#pgbf_card_scheme").val();
            var type = $("#pgbf_card_type").val();
            var bank = $("#pgbf_bank_name").val();
            var location = $("#pgbf_card_location").val();
            
            this.config.isUpdatingFees = true;
            this.debug('Updating payment fees with:', {scheme, type, bank, location});
            
            $.ajax({
                url: alg_bin_api_params.ajax_url,
                type: "POST",
                data: {
                    action: "update_payment_fees",
                    card_scheme: scheme,
                    card_type: type,
                    bank_name: bank,
                    card_location: location,
                    security: alg_bin_api_params.nonce
                },
                success: function(response) {
                    setTimeout(function() {
                        $("body").trigger("update_checkout");
                        self.triggerUpdateFees( selectedPayment );
                    }, 100);
                },
                error: function(xhr, status, error) {
                    self.debug('Error updating payment fees:', error);
                },
                complete: function() {
                    setTimeout(function() {
                        self.config.isUpdatingFees = false;
                    }, 200);
                }
            });
        },
        triggerUpdateFees: function( defaultPaymentMethod = null) {

            if (typeof pgf_checkout_order_id === "undefined" || typeof pgf_checkout_params === "undefined") {
                return; // do nothing
            }

            const order_id = (pgf_checkout_order_id.order_id) ? pgf_checkout_order_id.order_id : referrerArr[3];
        
            $('#place_order').prop('disabled', true);
        
            let paymentMethod = $('input[name="payment_method"]:checked').val();
            let currentPaymentMethod = paymentMethod;
        
            // Get Payment Title and strip out all html tags.
            let paymentMethodTitle = $(`label[for="payment_method_${paymentMethod}"]`).text().replace(/[\t\n]+/g, '').trim();
        
            // On visiting Pay for order page, take the payment method and payment title which are present in the order.
            if ('' !== pgf_checkout_order_id.payment_method) {
              paymentMethod = pgf_checkout_order_id.payment_method;
              paymentMethodTitle = $(`label[for="payment_method_${paymentMethod}"]`).text().replace(/[\t\n]+/g, '').trim();
            }
        
            const data = {
              payment_method: paymentMethod,
              payment_method_title: paymentMethodTitle,
              order_id: order_id,
              card_type: $('#pgbf_card_type').val() || '', // 👈 pass your select value too
              security: pgf_checkout_params.update_payment_method_nonce
            };
        
            // Reset payment method so changes are detected again.
            pgf_checkout_order_id.payment_method = '';
        
            $.post('?wc-ajax=update_fees', data, (response) => {
              $('#place_order').prop('disabled', false);
        
              if (response && response.fragments) {
                var tempDiv = $('<div>').html(response.fragments);
                var shopTableHtml = tempDiv.find('table.shop_table').prop('outerHTML');
                $('#order_review table.shop_table').html(shopTableHtml);
        
                $(`input[name="payment_method"][value=${paymentMethod}]`).prop('checked', true);
                $(`.payment_method_${paymentMethod}`).css('display', 'block');
                $(`div.payment_box:not(".payment_method_${paymentMethod}")`).filter(':visible').slideUp(0);
        
                // Fix for WooCommerce Square Payment Issue #114.
                if ('square_credit_card' === currentPaymentMethod && window.wc_square_credit_card_payment_form_handler && pgf_checkout_params && pgf_checkout_params.alg_wc_square_card_payment_args) {
                  window.wc_square_credit_card_payment_form_handler = new WC_Square_Payment_Form_Handler(pgf_checkout_params.alg_wc_square_card_payment_args);
                }
        
                $(document.body).trigger('updated_checkout');
              }
            });
        },

        // Clear payment fees (debounced)
        clearPaymentFeesDebounced: function() {
            var self = this;
            clearTimeout(this.config.debounceTimer);
            this.config.debounceTimer = setTimeout(function() {
                if (self.config.isUpdatingFees) return;
                
                self.config.isUpdatingFees = true;
                
                $.ajax({
                    url: alg_bin_api_params.ajax_url,
                    type: "POST",
                    data: {
                        action: "update_payment_fees",
                        card_scheme: "",
                        card_type: "",
                        bank_name: "",
                        card_location: "",
                        security: alg_bin_api_params.nonce
                    },
                    success: function(response) {
                        setTimeout(function() {
                            $("body").trigger("update_checkout");
                        }, 100);
                    },
                    complete: function() {
                        setTimeout(function() {
                            self.config.isUpdatingFees = false;
                        }, 200);
                    }
                });
            }, 300);
        },

        // Refresh card listeners (improved cleanup)
        refreshCardListeners: function() {
            var self = this;
            
            // Remove existing card input listeners
            this.removeCardListeners();
            
            // Set up new listeners
            setTimeout(function() {
                self.attachCardListeners();
            }, 100);
        },

        // Remove existing card listeners
        removeCardListeners: function() {
            $(this.config.cardNumberSelectors.join(', ')).off('.binapi');
        },

        // Attach card number input listeners
        attachCardListeners: function() {
            var self = this;
            var selectedPaymentMethod = $('.woocommerce-checkout input[name="payment_method"]:checked').val();
            
            // Check if current payment method has card rules and is a card payment gateway
            /* if (!this.hasCardRules(selectedPaymentMethod) || 
                !this.config.cardPaymentGateways.includes(selectedPaymentMethod)) {
                this.debug('No card rules or not a card gateway: ' + selectedPaymentMethod);
                return;
            } */

            this.debug('Card rules found for gateway: ' + selectedPaymentMethod);

            // Find active card number inputs
            var $cardInputs = this.findCardNumberInputs();
            
            if ($cardInputs.length > 0) {
                this.debug('Found ' + $cardInputs.length + ' card number input(s)');
                var selectedPayment = $("input[name=\"payment_method\"]:checked").val();
                $cardInputs.each(function() {
                    var $input = $(this);
                    
                    // Remove existing listeners first
                    $input.off('.binapi');
                    
                    // Bind input events with debouncing
                    $input.on('input.binapi keyup.binapi paste.binapi', self.debounce(function() {
                        var cardNumber = self.sanitizeCardNumber($input.val());
                        if( self.hasCardRules(selectedPayment) ){
                            self.handleCardInput(cardNumber);
                        }
                       
                    }, self.config.debounceDelay));
                    
                    // Also check current value
                    var currentValue = self.sanitizeCardNumber($input.val());
                    if (currentValue && currentValue.length >= self.config.minBinLength) {

                        if( self.hasCardRules(selectedPayment) ){
                            self.handleCardInput(currentValue);
                        }
                    }
                });
            } else {
                this.debug('No card number inputs found');
            }
        },

        // Check if payment method has card rules
        hasCardRules: function(paymentMethod) {
            return alg_bin_api_params &&
                   alg_bin_api_params.card_rules &&
                   alg_bin_api_params.card_rules[paymentMethod] &&
                   alg_bin_api_params.card_rules[paymentMethod].length > 0;
        },

        // Find card number inputs using various selectors
        findCardNumberInputs: function() {
            var $inputs = $();
            
            // Try each selector
            for (var i = 0; i < this.config.cardNumberSelectors.length; i++) {
                var selector = this.config.cardNumberSelectors[i];
                var $found = $(selector);
                
                if ($found.length > 0) {
                    $inputs = $inputs.add($found);
                    this.debug('Found input with selector: ' + selector);
                }
            }
            
            return $inputs.filter(':visible');
        },

        // Sanitize card number (remove spaces, dashes, etc.)
        sanitizeCardNumber: function(cardNumber) {
            if (!cardNumber) return '';
            return cardNumber.toString().replace(/\D/g, '');
        },

        // Handle card number input
        handleCardInput: function(cardNumber) {
            var self = this;
            
            // Prevent multiple simultaneous processing
            if (this.config.isProcessing) {
                return;
            }

            if (!cardNumber || cardNumber.length < this.config.minBinLength) {
                this.clearAutoDetectedData();
                return;
            }

            var bin = cardNumber.substring(0, 6);
            
            // Skip if same BIN as last processed
            if (bin === this.config.lastProcessedBin) {
                return;
            }

            this.config.lastProcessedBin = bin;
            this.config.isProcessing = true;
            this.debug('Processing BIN: ' + bin);

            // Check cache first
            if (this.config.cache[bin]) {
                this.debug('Using cached data for BIN: ' + bin);
                this.processCardData(this.config.cache[bin]).finally(function() {
                    self.config.isProcessing = false;
                });
                return;
            }

            // Fetch from server
            this.fetchBinData(bin);
        },

        // Fetch BIN data from server
        fetchBinData: function(bin) {
            var self = this;
            
            this.debug('Fetching BIN data for: ' + bin);
            this.showLoadingIndicator(true);

            $.ajax({
                url: alg_bin_api_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_bin_data',
                    gateways: $('.woocommerce-checkout input[name="payment_method"]:checked').val(),
                    card_number: bin + '000000',
                    nonce: alg_bin_api_params.nonce
                },
                timeout: 10000,
                success: function(response) {
                    self.showLoadingIndicator(false);
                    
                    if (response.success && response.data) {
                        self.debug('BIN data received:', response.data);
                        
                        // Cache the result
                        self.config.cache[bin] = response.data;
                        // Process the data
                        self.processCardData(response.data).finally(function() {
                            self.config.isProcessing = false;
                        });
                    } else {
                        $('#payment-fees-section').slideDown(200);
                        self.debug('BIN lookup failed:', response);
                        self.handleBinLookupError();
                        self.config.isProcessing = false;
                    }
                },
                error: function(xhr, status, error) {
                    self.showLoadingIndicator(false);
                    self.debug('AJAX error:', error);
                    self.handleBinLookupError();
                    self.config.isProcessing = false;
                }
            });
        },

        // Process card data and update checkout
        processCardData: function(cardData) {
            var self = this;
            this.debug('Processing card data:', cardData);
            this.config.hasCardData = true;
            this.config.autoDetectedData = cardData;
            
            return new Promise(function(resolve, reject) {
                // Store card data in session via AJAX
                //self.storeCardDataInSession(cardData).then(function() {
                    // Update manual fields only if user hasn't manually overridden them
                    self.updateFieldsWithCardData(cardData);
                    
                    // Display card info to user
                    self.displayCardInfo(cardData);
                    
                    // Trigger checkout update to recalculate fees with a slight delay
                    setTimeout(function() {
                        self.debug('Triggering checkout update after BIN detection');
                        $('body').trigger('update_checkout');
                        resolve();
                    }, 100);
                /* }).catch(function(error) {
                    self.debug('Error storing card data:', error);
                    reject(error);
                }); */
            });
        },

        // Update manual fields with auto-detected card data
        updateFieldsWithCardData: function(cardData) {

            var self = this;
            // Only update if user hasn't manually changed the fields
            if (this.config.manualOverride) {
                this.debug('Skipping auto-fill - user has manually overridden fields');
                return;
            }

            // Map card data to form fields
            var fieldMappings = {
                '#pgbf_card_scheme': cardData.scheme,
                '#pgbf_card_type': cardData.type,
                '#pgbf_bank_name': cardData.bank_name,
                '#pgbf_card_location': cardData.country_name || cardData.country_alpha2
            };

            // Update fields with auto-detected data
            $.each(fieldMappings, function(selector, value) {
                var $field = $(selector);
                if ($field.length && value && value !== 'UNKNOWN BANK') {
                    // Check if the value exists as an option (for select fields)
                    if ($field.is('select')) {
                        var $option = $field.find('option').filter(function() {
                            return $(this).text().toLowerCase().includes(value.toLowerCase()) ||
                                   $(this).val().toLowerCase() === value.toLowerCase();
                        });
                        
                        if ($option.length > 0) {
                            $field.val($option.val()).trigger('change', {autoDetected: true});
                            self.debug('Auto-filled ' + selector + ' with: ' + $option.val());
                        }
                    } else {
                        $field.val(value).trigger('change');
                        self.debug('Auto-filled ' + selector + ' with: ' + value);
                    }
                }
            });
        },

        // Store card data in session
        storeCardDataInSession: function(cardData) {
            var self = this;
            
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: alg_bin_api_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'store_card_data',
                        card_data: JSON.stringify(cardData),
                        nonce: alg_bin_api_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.debug('Card data stored in session');
                            resolve(response);
                        } else {
                            self.debug('Failed to store card data:', response);
                            reject(response);
                        }
                    },
                    error: function(xhr, status, error) {
                        self.debug('AJAX error storing card data:', error);
                        reject(error);
                    }
                });
            });
        },

        // Display card information to user
        displayCardInfo: function(cardData) {
            var cardInfo = '';
            
            if (cardData.scheme) {
                cardInfo += '<strong>Detected Card:</strong> ' + this.capitalizeFirst(cardData.scheme);
            }
            
            if (cardData.bank_name && cardData.bank_name !== 'UNKNOWN BANK') {
                cardInfo += '<br><strong>Bank:</strong> ' + cardData.bank_name;
            }
            
            if (cardData.country_name) {
                cardInfo += '<br><strong>Country:</strong> ' + cardData.country_name;
            }

            if (cardInfo) {
                // Remove existing card info
                $('.alg-card-info').remove();
                
                // Add new card info after payment method
                var $paymentBox = $('.wc_payment_method input[type="radio"]:checked').closest('.wc_payment_method');
                if ($paymentBox.length > 0) {
                    var infoHtml = '<div class="alg-card-info" style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px;">' + 
                                   cardInfo + 
                                   '</div>';
                    $paymentBox.after(infoHtml);
                }
            }
        },

        // Clear auto-detected data
        clearAutoDetectedData: function() {
            this.config.autoDetectedData = null;
            this.config.lastProcessedBin = null;
            $('.alg-card-info').remove();
            $('.alg-bin-loading').remove();
            
            // If no manual override, clear the fields
            if (!this.config.manualOverride && this.config.hasCardData) {
                this.clearManualFields();
            }
        },

        // Clear manual fields
        clearManualFields: function() {
            $("#pgbf_card_scheme, #pgbf_card_type, #pgbf_bank_name, #pgbf_card_location").val("");
        },

        // Clear card data (improved with callback)
        clearCardData: function(callback) {
            var self = this;
    
            // Prevent multiple simultaneous clear operations
            if (this.config.isClearing) {
                if (typeof callback === 'function') {
                    callback();
                }
                return;
            }
            
            this.config.isClearing = true;
            this.config.lastProcessedBin = null;
            this.config.manualOverride = false;
            this.config.autoDetectedData = null;
            $('.alg-card-info').remove();
            $('.alg-bin-loading').remove();
            
            // Clear manual fields
            this.clearManualFields();
            
            // Clear session data
            $.ajax({
                url: alg_bin_api_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'clear_card_data',
                    nonce: alg_bin_api_params.nonce
                },
                complete: function() {
                    self.debug('Card data cleared from session');
                    self.config.isClearing = false;
                    self.config.hasCardData = false;
                    
                    if (typeof callback === 'function') {
                        callback();
                    }
                    
                    // Only trigger checkout update if we're not already in an update cycle
                    if (!self.config.isProcessing && !self.config.isUpdatingFees) {
                        setTimeout(function() {
                            $('body').trigger('update_checkout');
                        }, 100);
                    }
                }
            });
        },

        // Handle BIN lookup errors
        handleBinLookupError: function() {
            this.debug('BIN lookup failed - manual selection still available');
            // Don't clear manual fields on error, just remove auto-detected info
            $('.alg-card-info').remove();
            $('.alg-bin-loading').remove();
            
            // Show a subtle message that manual selection is available
            var $paymentBox = $('.wc_payment_method input[type="radio"]:checked').closest('.wc_payment_method');
            if ($paymentBox.length > 0) {
                $paymentBox.after('<div class="alg-card-info alg-manual-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 8px; margin: 10px 0; border-radius: 4px; font-size: 12px; color: #856404;">Card details could not be auto-detected. Please select them manually in card payment details section.</div>');
                
                // Remove the notice after a few seconds
                setTimeout(function() {
                    $('.alg-manual-notice').fadeOut();
                }, 5000);
            }
        },

        // Show/hide loading indicator
        showLoadingIndicator: function(show) {
            $('.alg-bin-loading').remove();
            
            if (show) {
                var $paymentBox = $('.wc_payment_method input[type="radio"]:checked').closest('.wc_payment_method');
                if ($paymentBox.length > 0) {
                    $paymentBox.after('<div class="alg-bin-loading" style="text-align: center; padding: 5px;"><small>Detecting card details...</small></div>');
                }
            }
        },

        // Utility: Debounce function
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Utility: Capitalize first letter
        capitalizeFirst: function(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        },

        // Debug logging
        debug: function(message, data) {
            if (alg_bin_api_params.debug && console) {
                if (data) {
                    console.log('[ALG BIN API] ' + message, data);
                } else {
                    console.log('[ALG BIN API] ' + message);
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AlgBinApi.init();
    });

    // Expose to global scope for debugging
    window.AlgBinApi = AlgBinApi;

})(jQuery);