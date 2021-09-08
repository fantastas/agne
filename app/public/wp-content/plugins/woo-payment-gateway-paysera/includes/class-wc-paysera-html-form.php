<?php

defined('ABSPATH') or exit;

class Wc_Paysera_Html_Form
{
    const EMPTY_CODE = '';
    const FIELD_SELECTED = 'selected';
    const CODE_OTHER = 'other';

    /**
     * @return self
     */
    public static function create()
    {
        return new self();
    }

    /**
     * @param array $countries
     * @param string $billingCountryCode
     * @return string
     */
    public function buildCountriesList($countries, $billingCountryCode)
    {
        $defaultLangCode = $this->getDefaultLangCode($countries, $billingCountryCode);
        $selectionField = '<select id="paysera_country" class="payment-country-select" >';

        foreach ($countries as $country) {
            if ($country['code'] === $defaultLangCode) {
                $selected = self::FIELD_SELECTED;
            } else {
                $selected = self::EMPTY_CODE;
            }

            $selectionField .= '<option value="' . $country['code'] . '" ' . $selected . '>';
            $selectionField .= $country['title'];
            $selectionField .= '</option>';
        }

        $selectionField .= '</select>';

        return $selectionField;
    }

    /**
     * @param array $countries
     * @param bool $gridViewIsActive
     * @param string $billingCountryCode
     * @return string
     */
    public function buildPaymentsList($countries, $gridViewIsActive, $billingCountryCode)
    {
        $paymentsCode = self::EMPTY_CODE;

        foreach ($countries as $country) {
            $paymentsCode .= '<div id="' . $country['code'] . '"';

            if ($gridViewIsActive === true) {
                $paymentsCode .= ' class="payment-countries paysera-payments grid"';
            } else {
                $paymentsCode .= ' class="payment-countries paysera-payments"';
            }

            $paymentsCode .= ' style="display:';

            if (($country['code'] === $this->getDefaultLangCode($countries, $billingCountryCode))) {
                $paymentsCode .= 'block';
            } else {
                $paymentsCode .= 'none';
            }

            $paymentsCode .= '">';
            $paymentsCode .= $this->buildGroupList($country['groups'], $country['code']);
            $paymentsCode .= '</div>';
        }

        return $paymentsCode;
    }

    /**
     * @param array $methods
     * @param string $countryCode
     * @return string
     */
    protected function buildMethodsList($methods, $countryCode)
    {
        $paymentMethodCode = self::EMPTY_CODE;

        foreach ($methods as $method) {
            $paymentMethodCode .= '<div id="' . $method->getKey() . '" class="payment">';
            $paymentMethodCode .= '<label>';
            $paymentMethodCode .= '<input class="rd_pay" ';
            $paymentMethodCode .= 'type="radio" ';
            $paymentMethodCode .= 'rel="r' . $countryCode . $method->getKey() . '" ';
            $paymentMethodCode .= 'name="payment[pay_type]" ';
            $paymentMethodCode .= 'value="' . $method->getKey() . '"/> ';
            $paymentMethodCode .= '<span class="paysera-text">';
            $paymentMethodCode .= $method->getTitle();
            $paymentMethodCode .= '</span>';
            $paymentMethodCode .= '<div class="paysera-image">';
            $paymentMethodCode .= '<img src="' . $method->getLogoUrl() . '" ';
            $paymentMethodCode .= 'alt="' . $method->getTitle() . '"/>';
            $paymentMethodCode .= '</div>';
            $paymentMethodCode .= '</label>';
            $paymentMethodCode .= '</div>';
        }

        return $paymentMethodCode;
    }

    /**
     * @param array  $groups
     * @param string $countryCode
     * @return string
     */
    protected function buildGroupList($groups, $countryCode)
    {
        $paymentGroupCode = self::EMPTY_CODE;

        foreach ($groups as $group) {
            $paymentGroupCode .= '<div class="payment-group-wrapper">';
            $paymentGroupCode .= '<div class="payment-group-title">';
            $paymentGroupCode .= $group->getTitle();
            $paymentGroupCode .= '</div>';
            $paymentGroupCode .= $this->buildMethodsList($group->getPaymentMethods(), $countryCode);
            $paymentGroupCode .= '</div>';
        }

        return $paymentGroupCode;
    }

    /**
     * @param array $countries
     * @param string $countryCode
     * @return string
     */
    protected function getDefaultLangCode($countries, $countryCode)
    {
        $countryCodes = [];

        foreach ($countries as $country) {
            $countryCodes[] = $country['code'];
        }

        if (in_array($countryCode, $countryCodes) === true) {
            $defaultLang = $countryCode;
        } elseif (in_array(self::CODE_OTHER, $countryCodes) === true) {
            $defaultLang = self::CODE_OTHER;
        } else {
            $defaultLang = reset($countries)['code'];
        }

        return $defaultLang;
    }
}
