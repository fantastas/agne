var a = {
    \u0440: "a",
    \u010D: "c",
    \u0119: "e",
    \u0117: "e",
    \u012F: "i",
    \u0161: "s",
    \u0173: "u",
    \u016B: "u",
    \u017E: "z"
};

function transliterate(word) {
    return word.split('').map(function (char) {
        return a[char] || char;
    }).join("");
}

(function ($) {
    $(document).on('updated_checkout cfw_updated_checkout', function (e, data) {
        $('#mp-wc-pickup-point-shipping').hide();

        var shipping_methods = {};
        $('select.shipping_method, input[name^="shipping_method"][type="radio"]:checked, input[name^="shipping_method"][type="hidden"]').each(function () {
            shipping_methods[$(this).data('index')] = $(this).val();
        });

        var show_title = false;

        if (multiparcels.display_pickup_location_title == 'yes') {
            show_title = true;
        }

        var rey_theme = $('body').hasClass('theme-rey') && $('.rey-checkout-shipping').length && $('.rey-checkout-shipping').is(":visible");

        if (!show_title) {
            $("#mp-wc-pickup-point-shipping .mp-please-select-location").hide();
        }

        $("#preferred_delivery_time_field").hide();

        $('.multiparcels-door-code').addClass('multiparcels-door-code-invisible').removeClass('multiparcels-door-code-visible');

        if (rey_theme && $('.multiparcels-rey-theme-modified-step').length) {
            var originalText = $('.multiparcels-rey-theme-modified-step').attr('data-original-text');

            $(".multiparcels-rey-theme-modified-step")
                .removeClass('multiparcels-rey-theme-modified-step')
                .attr('href', '#')
                .text(originalText)
                .addClass('__step-fwd');
        }

        if (Object.keys(shipping_methods).length > 0) {
            var shipping_methods_keys = Object.keys(shipping_methods);
            var shipping_method = $.trim(shipping_methods[shipping_methods_keys[0]]);

            $('#mp-wc-pickup-point-shipping').addClass('multiparcels-loading');

            var terminal_shipping_fields = $("");
            if (multiparcels.hide_not_required_terminal_fields === 'yes') {
                terminal_shipping_fields = $("#billing_address_1_field, #billing_address_2_field, #billing_city_field, #billing_postcode_field, #shipping_address_1_field, #shipping_address_2_field, #shipping_city_field, #shipping_postcode_field");
                terminal_shipping_fields.show();
            }


            var local_pickup_shipping_fields = $("");
            if (multiparcels.hide_not_required_local_pickup_fields === 'yes') {
                local_pickup_shipping_fields = $("#billing_address_1_field, #billing_address_2_field, #billing_city_field, #billing_postcode_field, #shipping_address_1_field, #shipping_address_2_field, #shipping_city_field, #shipping_postcode_field");
                local_pickup_shipping_fields.show();
            }

            // is MultiParcels and pickup location
            if (
                (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_pickup_point") !== -1) ||
                (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("_terminal") !== -1) ||
                (shipping_method.substr(0, 12) === 'multiparcels' && shipping_method.indexOf("post_lv") !== -1 && shipping_method.split(':')[0].endsWith('_post'))) {
                // Reset selected
                $("#mp-selected-pickup-point-info-wrapper").hide();
                $("#mp-map-preview").hide();
                $("#mp-wc-pickup-point-shipping-select").html('');
                $(".mp-selected-pickup-point-info").html('');
                var latvian_post = false;

                if (shipping_method.indexOf("post_lv") !== -1) {
                    latvian_post = true;
                }

                if (!latvian_post) {
                    terminal_shipping_fields.hide();
                }

                if (rey_theme && $('.rey-checkoutPage-form .__step[data-step="shipping"]').is(':visible')) {
                    var step = $('.rey-checkoutPage-form .__step[data-step="shipping"]')
                        .find('.__step-footer .btn-primary');
                    var originalText = step.text();
                    step.attr('data-original-text', originalText);

                    $('.rey-checkoutPage-form .__step[data-step="shipping"]')
                        .find('.__step-footer .btn-primary')
                        .removeClass('__step-fwd')
                        .addClass('multiparcels-rey-theme-modified-step')
                        .attr('href', 'javascript:;')
                        .text(multiparcels.text.please_select_pickup_point_location);
                }

                $('#mp-wc-pickup-point-shipping').show();
                $.ajax({
                    type: 'POST',
                    url: multiparcels.ajax_url,
                    data: {
                        'action': 'multiparcels_checkout_get_pickup_points'
                    },
                    dataType: 'json',
                    success: function (points) {
                        window.multiparcels_select_points_by_identifier = points.by_identifier;

                        $('#mp-wc-pickup-point-shipping').removeClass('multiparcels-loading');

                        $('#mp-wc-pickup-point-shipping-select').html("");

                        if (typeof jQuery.fn.selectWoo === "function") {
                            $('#mp-wc-pickup-point-shipping-select').selectWoo({
                                data: points.all,
                                placeholderOption: 'first',
                                width: '100%',
                                language: {
                                    noResults: function () {
                                        return multiparcels.text.pickup_location_not_found;
                                    }
                                },
                                templateResult: function (option) {
                                    if (typeof option.first_line == 'string') {
                                        return $(
                                            '<span>' + option.first_line + ' <small>' + option.second_line + '</small></span>'
                                        );
                                    }

                                    return $('<span>' + option.text + '</span>');
                                },
                                templateSelection: function (data, container) {
                                    if (typeof data.second_line != 'undefined' && data.second_line) {
                                        return data.first_line + ' (' + data.second_line + ')';
                                    }

                                    return data.first_line;
                                }
                            });
                        } else {
                            var items = '';

                            $.each(points.all, function (key, value) {
                                if (typeof value.children === 'object') {
                                    items += "<optgroup label='" + value.text + "'>"
                                    $.each(value.children, function (key2, value2) {
                                        items += '<option value=' + value2.id + '>' + value2.text + '</option>';
                                    });
                                    items += "</optgroup>"
                                } else {
                                    items += '<option value=' + value.id + '>' + value.text + '</option>';
                                }
                            });

                            $('#mp-wc-pickup-point-shipping-select').html(items);
                        }

                        if (typeof window.multiparcels_selected_location != 'undefined' && window.multiparcels_selected_location) {
                            // check if it actually exists
                            if ($('#mp-wc-pickup-point-shipping-select').find("option[value='" + window.multiparcels_selected_location + "']").length) {
                                $('#mp-wc-pickup-point-shipping-select').val(window.multiparcels_selected_location).trigger('change');
                            }
                        }
                    }
                });
            } else if (shipping_method.substr(0, 12) === 'multiparcels') {
                if (shipping_method.indexOf("venipak") !== -1 || shipping_method.indexOf("dpd") !== -1) {
                    var cities = multiparcels.preferred_delivery_time_cities;
                    var city = transliterate($("#billing_city").val().toLowerCase());
                    var is_big_city = cities.indexOf(city) != -1;

                    if (is_big_city) {
                        $.ajax({
                            type: 'POST',
                            url: multiparcels.ajax_url,
                            data: {
                                'action': 'multiparcels_is_preferred_delivery_time_available'
                            },
                            dataType: 'json',
                            success: function (response) {
                                if (response.success == true) {
                                    var options = '';

                                    response.times.forEach(function (value, key) {
                                        options += "<option value='" + key + "'>" + value + "</option>";
                                    });

                                    $("#preferred_delivery_time_field select").html(options);
                                    $("#preferred_delivery_time_field").show();

                                    if (typeof jQuery.fn.selectWoo === "function") {
                                        $("#preferred_delivery_time").selectWoo();
                                    }

                                    if (typeof window.multiparcels_selected_delivery_time != 'undefined' && window.multiparcels_selected_delivery_time) {
                                        $('#preferred_delivery_time').val(window.multiparcels_selected_delivery_time).trigger('change');
                                    }
                                }
                            }
                        });
                    }

                    if (shipping_method.indexOf("venipak") !== -1) {
                        $('.multiparcels-door-code').addClass('multiparcels-door-code-visible').removeClass('multiparcels-door-code-invisible');
                    }

                    // Reset any previous selection
                    $('#mp-wc-pickup-point-shipping-select').val('');
                }
            } else if(shipping_method.split(':')[0] == 'local_pickup') {
                local_pickup_shipping_fields.hide();

                // Reset any previous selection
                $('#mp-wc-pickup-point-shipping-select').val('');
            } else {
                // Reset any previous selection
                $('#mp-wc-pickup-point-shipping-select').val('');
            }
        }
    });

    $(document).on('change', '#mp-wc-pickup-point-shipping-select', function () {
        var val = $('#mp-wc-pickup-point-shipping-select').val();

        $("#mp-selected-pickup-point-info-wrapper").hide();
        $("#mp-map-preview").hide();

        var show_information = false;

        if (multiparcels.display_selected_pickup_location_information == 'yes') {
            show_information = true;
        }

        // remember selected location
        window.multiparcels_selected_location = val;

        if (val == '' || !show_information) {
            $(".mp-selected-pickup-point-info").html('');
        } else {
            var location = window.multiparcels_select_points_by_identifier[val];

            // to prevent selecting location from a different carrier when switching between
            // shipping methods
            if (location) {
                $("#mp-selected-pickup-point-info-wrapper").show();

                var rey_theme = $('body').hasClass('theme-rey') && $('.rey-checkout-shipping').length && $('.rey-checkout-shipping').is(":visible");

                if (rey_theme && $('.multiparcels-rey-theme-modified-step').length) {
                    var originalText = $('.multiparcels-rey-theme-modified-step').attr('data-original-text');

                    $(".multiparcels-rey-theme-modified-step")
                        .removeClass('multiparcels-rey-theme-modified-step')
                        .attr('href', '#')
                        .text(originalText)
                        .addClass('__step-fwd');
                }

                var html = location['second_line'] + "<br/>";

                if (location.location.working_hours) {
                    html += multiparcels.text.working_hours + ": <strong>" + location.location.working_hours + "</strong><br/>";
                }

                if (location.location.comment) {
                    html += "<small>" + location.location.comment + "</small><br/>";
                }

                $(".mp-selected-pickup-point-info").html(html);

                if (typeof google === "object" && google.maps && location.location.latitude && location.location.longitude) {
                    $("#mp-map-preview").show();

                    var position = {
                        lat: parseFloat(location.location.latitude),
                        lng: parseFloat(location.location.longitude)
                    };

                    var map = new google.maps.Map(document.getElementById('mp-gmap'), {
                        center: position,
                        zoom: 15
                    });

                    new google.maps.Marker({
                        position: position,
                        map: map
                    });
                }
            }
        }
    });

    /**
     * Remember selected delivery time
     */
    $(document).on('change', '#preferred_delivery_time', function () {
        window.multiparcels_selected_delivery_time = $(this).val();
    });

    /**
     * AeroCheckout
     */
    $(document).on('change', '.wfacp_shipping_radio input', function () {
        $(document).trigger('updated_checkout');
    });
}(jQuery));
