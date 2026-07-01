<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - Functions
 *
 * Renamed from: includes/functions/country-functions.php
 * New location:  includes/functions/functions.php
 *
 * @version 3.0.0
 * @since   2.0.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'alg_checkout_fees_get_country_set_countries' ) ) {
	/**
	 * Set country codes.
	 *
	 * @version 2.5.0
	 * @since   2.4.0
	 */
	function alg_checkout_fees_get_country_set_countries() {
		return array(
			'EU'                    => array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HU', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' ),
			'Europe'                => array( 'AD', 'AL', 'AT', 'AX', 'BA', 'BE', 'BG', 'BY', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FO', 'FR', 'FX', 'GB', 'GG', 'GI', 'GR', 'HR', 'HU', 'IE', 'IM', 'IS', 'IT', 'JE', 'LI', 'LT', 'LU', 'LV', 'MC', 'MD', 'ME', 'MK', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'RS', 'RU', 'SE', 'SI', 'SJ', 'SK', 'SM', 'TR', 'UA', 'VA' ),
			'Europe-excluding-EU'   => array( 'AD', 'AL', 'AX', 'BA', 'BY', 'CH', 'FO', 'FX', 'GG', 'GI', 'IM', 'IS', 'JE', 'LI', 'MC', 'MD', 'ME', 'MK', 'NO', 'RS', 'RU', 'SJ', 'SM', 'TR', 'UA', 'VA' ),
			'Eurozone'              => array( 'AD', 'AT', 'BE', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'MC', 'NL', 'PT', 'SM', 'SI', 'SK', 'ES', 'VA' ),
			'Africa'                => array( 'AO', 'BF', 'BI', 'BJ', 'BW', 'CD', 'CF', 'CG', 'CI', 'CM', 'CV', 'DJ', 'DZ', 'EG', 'EH', 'ER', 'ET', 'GA', 'GH', 'GM', 'GN', 'GQ', 'GW', 'YT', 'KE', 'KM', 'LY', 'LR', 'LS', 'MA', 'MG', 'ML', 'MR', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG', 'RE', 'RW', 'SC', 'SD', 'SH', 'SL', 'SN', 'SO', 'ST', 'SZ', 'TD', 'TG', 'TN', 'TZ', 'UG', 'ZA', 'ZM', 'ZW' ),
			'Asia'                  => array( 'AE', 'AF', 'AM', 'AP', 'AZ', 'BD', 'BH', 'BN', 'BT', 'CC', 'CY', 'CN', 'CX', 'GE', 'HK', 'ID', 'IL', 'IN', 'IO', 'IQ', 'IR', 'YE', 'JO', 'JP', 'KG', 'KH', 'KP', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LK', 'MY', 'MM', 'MN', 'MO', 'MV', 'NP', 'OM', 'PH', 'PK', 'PS', 'QA', 'SA', 'SG', 'SY', 'TH', 'TJ', 'TL', 'TM', 'TW', 'UZ', 'VN' ),
			'Australia-and-Oceania' => array( 'AS', 'AU', 'CK', 'FJ', 'FM', 'GU', 'KI', 'MH', 'MP', 'NC', 'NF', 'NR', 'NU', 'NZ', 'PF', 'PG', 'PN', 'PW', 'SB', 'TK', 'TO', 'TV', 'UM', 'VU', 'WF', 'WS' ),
			'Central-America'       => array( 'AG', 'AI', 'AN', 'AW', 'BB', 'BL', 'BM', 'BS', 'BZ', 'CR', 'CU', 'DM', 'DO', 'GD', 'GL', 'GP', 'GT', 'HN', 'HT', 'JM', 'KY', 'KN', 'LC', 'MF', 'MQ', 'MS', 'NI', 'PA', 'PM', 'PR', 'SV', 'TC', 'TT', 'VC', 'VG', 'VI' ),
			'North-America'         => array( 'CA', 'MX', 'US' ),
			'South-America'         => array( 'AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PE', 'PY', 'SR', 'UY', 'VE' ),
		);
	}
}

if ( ! function_exists( 'alg_checkout_fees_get_countries_sets' ) ) {
	/**
	 * Get all countries sets array.
	 *
	 * @version 2.4.0
	 * @since   2.4.0
	 * @return  array
	 */
	function alg_checkout_fees_get_countries_sets() {
		return array(
			'Africa'                => __( 'Africa', 'checkout-fees-for-woocommerce' ),
			'Asia'                  => __( 'Asia', 'checkout-fees-for-woocommerce' ),
			'Australia-and-Oceania' => __( 'Australia & Oceania', 'checkout-fees-for-woocommerce' ),
			'Central-America'       => __( 'Central America', 'checkout-fees-for-woocommerce' ),
			'EU'                    => __( 'European Union', 'checkout-fees-for-woocommerce' ),
			'Europe'                => __( 'Europe', 'checkout-fees-for-woocommerce' ),
			'Europe-excluding-EU'   => __( 'Europe excluding EU', 'checkout-fees-for-woocommerce' ),
			'Eurozone'              => __( 'Eurozone', 'checkout-fees-for-woocommerce' ),
			'North-America'         => __( 'North America', 'checkout-fees-for-woocommerce' ),
			'South-America'         => __( 'South America', 'checkout-fees-for-woocommerce' ),
		);
	}
}

if ( ! function_exists( 'alg_checkout_fees_get_countries' ) ) {
	/**
	 * Get all countries array.
	 *
	 * @version 2.4.0
	 * @return  array
	 */
	function alg_checkout_fees_get_countries() {
		return array(
			'AF' => __( 'Afghanistan', 'checkout-fees-for-woocommerce' ),
			'AX' => __( '&#197;land Islands', 'checkout-fees-for-woocommerce' ),
			'AL' => __( 'Albania', 'checkout-fees-for-woocommerce' ),
			'DZ' => __( 'Algeria', 'checkout-fees-for-woocommerce' ),
			'AD' => __( 'Andorra', 'checkout-fees-for-woocommerce' ),
			'AO' => __( 'Angola', 'checkout-fees-for-woocommerce' ),
			'AI' => __( 'Anguilla', 'checkout-fees-for-woocommerce' ),
			'AQ' => __( 'Antarctica', 'checkout-fees-for-woocommerce' ),
			'AG' => __( 'Antigua and Barbuda', 'checkout-fees-for-woocommerce' ),
			'AR' => __( 'Argentina', 'checkout-fees-for-woocommerce' ),
			'AM' => __( 'Armenia', 'checkout-fees-for-woocommerce' ),
			'AW' => __( 'Aruba', 'checkout-fees-for-woocommerce' ),
			'AU' => __( 'Australia', 'checkout-fees-for-woocommerce' ),
			'AT' => __( 'Austria', 'checkout-fees-for-woocommerce' ),
			'AZ' => __( 'Azerbaijan', 'checkout-fees-for-woocommerce' ),
			'BS' => __( 'Bahamas', 'checkout-fees-for-woocommerce' ),
			'BH' => __( 'Bahrain', 'checkout-fees-for-woocommerce' ),
			'BD' => __( 'Bangladesh', 'checkout-fees-for-woocommerce' ),
			'BB' => __( 'Barbados', 'checkout-fees-for-woocommerce' ),
			'BY' => __( 'Belarus', 'checkout-fees-for-woocommerce' ),
			'BE' => __( 'Belgium', 'checkout-fees-for-woocommerce' ),
			'PW' => __( 'Belau', 'checkout-fees-for-woocommerce' ),
			'BZ' => __( 'Belize', 'checkout-fees-for-woocommerce' ),
			'BJ' => __( 'Benin', 'checkout-fees-for-woocommerce' ),
			'BM' => __( 'Bermuda', 'checkout-fees-for-woocommerce' ),
			'BT' => __( 'Bhutan', 'checkout-fees-for-woocommerce' ),
			'BO' => __( 'Bolivia', 'checkout-fees-for-woocommerce' ),
			'BQ' => __( 'Bonaire, Saint Eustatius and Saba', 'checkout-fees-for-woocommerce' ),
			'BA' => __( 'Bosnia and Herzegovina', 'checkout-fees-for-woocommerce' ),
			'BW' => __( 'Botswana', 'checkout-fees-for-woocommerce' ),
			'BV' => __( 'Bouvet Island', 'checkout-fees-for-woocommerce' ),
			'BR' => __( 'Brazil', 'checkout-fees-for-woocommerce' ),
			'IO' => __( 'British Indian Ocean Territory', 'checkout-fees-for-woocommerce' ),
			'VG' => __( 'British Virgin Islands', 'checkout-fees-for-woocommerce' ),
			'BN' => __( 'Brunei', 'checkout-fees-for-woocommerce' ),
			'BG' => __( 'Bulgaria', 'checkout-fees-for-woocommerce' ),
			'BF' => __( 'Burkina Faso', 'checkout-fees-for-woocommerce' ),
			'BI' => __( 'Burundi', 'checkout-fees-for-woocommerce' ),
			'KH' => __( 'Cambodia', 'checkout-fees-for-woocommerce' ),
			'CM' => __( 'Cameroon', 'checkout-fees-for-woocommerce' ),
			'CA' => __( 'Canada', 'checkout-fees-for-woocommerce' ),
			'CV' => __( 'Cape Verde', 'checkout-fees-for-woocommerce' ),
			'KY' => __( 'Cayman Islands', 'checkout-fees-for-woocommerce' ),
			'CF' => __( 'Central African Republic', 'checkout-fees-for-woocommerce' ),
			'TD' => __( 'Chad', 'checkout-fees-for-woocommerce' ),
			'CL' => __( 'Chile', 'checkout-fees-for-woocommerce' ),
			'CN' => __( 'China', 'checkout-fees-for-woocommerce' ),
			'CX' => __( 'Christmas Island', 'checkout-fees-for-woocommerce' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'checkout-fees-for-woocommerce' ),
			'CO' => __( 'Colombia', 'checkout-fees-for-woocommerce' ),
			'KM' => __( 'Comoros', 'checkout-fees-for-woocommerce' ),
			'CG' => __( 'Congo (Brazzaville)', 'checkout-fees-for-woocommerce' ),
			'CD' => __( 'Congo (Kinshasa)', 'checkout-fees-for-woocommerce' ),
			'CK' => __( 'Cook Islands', 'checkout-fees-for-woocommerce' ),
			'CR' => __( 'Costa Rica', 'checkout-fees-for-woocommerce' ),
			'HR' => __( 'Croatia', 'checkout-fees-for-woocommerce' ),
			'CU' => __( 'Cuba', 'checkout-fees-for-woocommerce' ),
			'CW' => __( 'Cura&Ccedil;ao', 'checkout-fees-for-woocommerce' ),
			'CY' => __( 'Cyprus', 'checkout-fees-for-woocommerce' ),
			'CZ' => __( 'Czech Republic', 'checkout-fees-for-woocommerce' ),
			'DK' => __( 'Denmark', 'checkout-fees-for-woocommerce' ),
			'DJ' => __( 'Djibouti', 'checkout-fees-for-woocommerce' ),
			'DM' => __( 'Dominica', 'checkout-fees-for-woocommerce' ),
			'DO' => __( 'Dominican Republic', 'checkout-fees-for-woocommerce' ),
			'EC' => __( 'Ecuador', 'checkout-fees-for-woocommerce' ),
			'EG' => __( 'Egypt', 'checkout-fees-for-woocommerce' ),
			'SV' => __( 'El Salvador', 'checkout-fees-for-woocommerce' ),
			'GQ' => __( 'Equatorial Guinea', 'checkout-fees-for-woocommerce' ),
			'ER' => __( 'Eritrea', 'checkout-fees-for-woocommerce' ),
			'EE' => __( 'Estonia', 'checkout-fees-for-woocommerce' ),
			'ET' => __( 'Ethiopia', 'checkout-fees-for-woocommerce' ),
			'FK' => __( 'Falkland Islands', 'checkout-fees-for-woocommerce' ),
			'FO' => __( 'Faroe Islands', 'checkout-fees-for-woocommerce' ),
			'FJ' => __( 'Fiji', 'checkout-fees-for-woocommerce' ),
			'FI' => __( 'Finland', 'checkout-fees-for-woocommerce' ),
			'FR' => __( 'France', 'checkout-fees-for-woocommerce' ),
			'GF' => __( 'French Guiana', 'checkout-fees-for-woocommerce' ),
			'PF' => __( 'French Polynesia', 'checkout-fees-for-woocommerce' ),
			'TF' => __( 'French Southern Territories', 'checkout-fees-for-woocommerce' ),
			'GA' => __( 'Gabon', 'checkout-fees-for-woocommerce' ),
			'GM' => __( 'Gambia', 'checkout-fees-for-woocommerce' ),
			'GE' => __( 'Georgia', 'checkout-fees-for-woocommerce' ),
			'DE' => __( 'Germany', 'checkout-fees-for-woocommerce' ),
			'GH' => __( 'Ghana', 'checkout-fees-for-woocommerce' ),
			'GI' => __( 'Gibraltar', 'checkout-fees-for-woocommerce' ),
			'GR' => __( 'Greece', 'checkout-fees-for-woocommerce' ),
			'GL' => __( 'Greenland', 'checkout-fees-for-woocommerce' ),
			'GD' => __( 'Grenada', 'checkout-fees-for-woocommerce' ),
			'GP' => __( 'Guadeloupe', 'checkout-fees-for-woocommerce' ),
			'GT' => __( 'Guatemala', 'checkout-fees-for-woocommerce' ),
			'GG' => __( 'Guernsey', 'checkout-fees-for-woocommerce' ),
			'GN' => __( 'Guinea', 'checkout-fees-for-woocommerce' ),
			'GW' => __( 'Guinea-Bissau', 'checkout-fees-for-woocommerce' ),
			'GY' => __( 'Guyana', 'checkout-fees-for-woocommerce' ),
			'HT' => __( 'Haiti', 'checkout-fees-for-woocommerce' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'checkout-fees-for-woocommerce' ),
			'HN' => __( 'Honduras', 'checkout-fees-for-woocommerce' ),
			'HK' => __( 'Hong Kong', 'checkout-fees-for-woocommerce' ),
			'HU' => __( 'Hungary', 'checkout-fees-for-woocommerce' ),
			'IS' => __( 'Iceland', 'checkout-fees-for-woocommerce' ),
			'IN' => __( 'India', 'checkout-fees-for-woocommerce' ),
			'ID' => __( 'Indonesia', 'checkout-fees-for-woocommerce' ),
			'IR' => __( 'Iran', 'checkout-fees-for-woocommerce' ),
			'IQ' => __( 'Iraq', 'checkout-fees-for-woocommerce' ),
			'IE' => __( 'Republic of Ireland', 'checkout-fees-for-woocommerce' ),
			'IM' => __( 'Isle of Man', 'checkout-fees-for-woocommerce' ),
			'IL' => __( 'Israel', 'checkout-fees-for-woocommerce' ),
			'IT' => __( 'Italy', 'checkout-fees-for-woocommerce' ),
			'CI' => __( 'Ivory Coast', 'checkout-fees-for-woocommerce' ),
			'JM' => __( 'Jamaica', 'checkout-fees-for-woocommerce' ),
			'JP' => __( 'Japan', 'checkout-fees-for-woocommerce' ),
			'JE' => __( 'Jersey', 'checkout-fees-for-woocommerce' ),
			'JO' => __( 'Jordan', 'checkout-fees-for-woocommerce' ),
			'KZ' => __( 'Kazakhstan', 'checkout-fees-for-woocommerce' ),
			'KE' => __( 'Kenya', 'checkout-fees-for-woocommerce' ),
			'KI' => __( 'Kiribati', 'checkout-fees-for-woocommerce' ),
			'KW' => __( 'Kuwait', 'checkout-fees-for-woocommerce' ),
			'KG' => __( 'Kyrgyzstan', 'checkout-fees-for-woocommerce' ),
			'LA' => __( 'Laos', 'checkout-fees-for-woocommerce' ),
			'LV' => __( 'Latvia', 'checkout-fees-for-woocommerce' ),
			'LB' => __( 'Lebanon', 'checkout-fees-for-woocommerce' ),
			'LS' => __( 'Lesotho', 'checkout-fees-for-woocommerce' ),
			'LR' => __( 'Liberia', 'checkout-fees-for-woocommerce' ),
			'LY' => __( 'Libya', 'checkout-fees-for-woocommerce' ),
			'LI' => __( 'Liechtenstein', 'checkout-fees-for-woocommerce' ),
			'LT' => __( 'Lithuania', 'checkout-fees-for-woocommerce' ),
			'LU' => __( 'Luxembourg', 'checkout-fees-for-woocommerce' ),
			'MO' => __( 'Macao S.A.R., China', 'checkout-fees-for-woocommerce' ),
			'MK' => __( 'Macedonia', 'checkout-fees-for-woocommerce' ),
			'MG' => __( 'Madagascar', 'checkout-fees-for-woocommerce' ),
			'MW' => __( 'Malawi', 'checkout-fees-for-woocommerce' ),
			'MY' => __( 'Malaysia', 'checkout-fees-for-woocommerce' ),
			'MV' => __( 'Maldives', 'checkout-fees-for-woocommerce' ),
			'ML' => __( 'Mali', 'checkout-fees-for-woocommerce' ),
			'MT' => __( 'Malta', 'checkout-fees-for-woocommerce' ),
			'MH' => __( 'Marshall Islands', 'checkout-fees-for-woocommerce' ),
			'MQ' => __( 'Martinique', 'checkout-fees-for-woocommerce' ),
			'MR' => __( 'Mauritania', 'checkout-fees-for-woocommerce' ),
			'MU' => __( 'Mauritius', 'checkout-fees-for-woocommerce' ),
			'YT' => __( 'Mayotte', 'checkout-fees-for-woocommerce' ),
			'MX' => __( 'Mexico', 'checkout-fees-for-woocommerce' ),
			'FM' => __( 'Micronesia', 'checkout-fees-for-woocommerce' ),
			'MD' => __( 'Moldova', 'checkout-fees-for-woocommerce' ),
			'MC' => __( 'Monaco', 'checkout-fees-for-woocommerce' ),
			'MN' => __( 'Mongolia', 'checkout-fees-for-woocommerce' ),
			'ME' => __( 'Montenegro', 'checkout-fees-for-woocommerce' ),
			'MS' => __( 'Montserrat', 'checkout-fees-for-woocommerce' ),
			'MA' => __( 'Morocco', 'checkout-fees-for-woocommerce' ),
			'MZ' => __( 'Mozambique', 'checkout-fees-for-woocommerce' ),
			'MM' => __( 'Myanmar', 'checkout-fees-for-woocommerce' ),
			'NA' => __( 'Namibia', 'checkout-fees-for-woocommerce' ),
			'NR' => __( 'Nauru', 'checkout-fees-for-woocommerce' ),
			'NP' => __( 'Nepal', 'checkout-fees-for-woocommerce' ),
			'NL' => __( 'Netherlands', 'checkout-fees-for-woocommerce' ),
			'AN' => __( 'Netherlands Antilles', 'checkout-fees-for-woocommerce' ),
			'NC' => __( 'New Caledonia', 'checkout-fees-for-woocommerce' ),
			'NZ' => __( 'New Zealand', 'checkout-fees-for-woocommerce' ),
			'NI' => __( 'Nicaragua', 'checkout-fees-for-woocommerce' ),
			'NE' => __( 'Niger', 'checkout-fees-for-woocommerce' ),
			'NG' => __( 'Nigeria', 'checkout-fees-for-woocommerce' ),
			'NU' => __( 'Niue', 'checkout-fees-for-woocommerce' ),
			'NF' => __( 'Norfolk Island', 'checkout-fees-for-woocommerce' ),
			'KP' => __( 'North Korea', 'checkout-fees-for-woocommerce' ),
			'NO' => __( 'Norway', 'checkout-fees-for-woocommerce' ),
			'OM' => __( 'Oman', 'checkout-fees-for-woocommerce' ),
			'PK' => __( 'Pakistan', 'checkout-fees-for-woocommerce' ),
			'PS' => __( 'Palestinian Territory', 'checkout-fees-for-woocommerce' ),
			'PA' => __( 'Panama', 'checkout-fees-for-woocommerce' ),
			'PG' => __( 'Papua New Guinea', 'checkout-fees-for-woocommerce' ),
			'PY' => __( 'Paraguay', 'checkout-fees-for-woocommerce' ),
			'PE' => __( 'Peru', 'checkout-fees-for-woocommerce' ),
			'PH' => __( 'Philippines', 'checkout-fees-for-woocommerce' ),
			'PN' => __( 'Pitcairn', 'checkout-fees-for-woocommerce' ),
			'PL' => __( 'Poland', 'checkout-fees-for-woocommerce' ),
			'PT' => __( 'Portugal', 'checkout-fees-for-woocommerce' ),
			'QA' => __( 'Qatar', 'checkout-fees-for-woocommerce' ),
			'RE' => __( 'Reunion', 'checkout-fees-for-woocommerce' ),
			'RO' => __( 'Romania', 'checkout-fees-for-woocommerce' ),
			'RU' => __( 'Russia', 'checkout-fees-for-woocommerce' ),
			'RW' => __( 'Rwanda', 'checkout-fees-for-woocommerce' ),
			'BL' => __( 'Saint Barth&eacute;lemy', 'checkout-fees-for-woocommerce' ),
			'SH' => __( 'Saint Helena', 'checkout-fees-for-woocommerce' ),
			'KN' => __( 'Saint Kitts and Nevis', 'checkout-fees-for-woocommerce' ),
			'LC' => __( 'Saint Lucia', 'checkout-fees-for-woocommerce' ),
			'MF' => __( 'Saint Martin (French part)', 'checkout-fees-for-woocommerce' ),
			'SX' => __( 'Saint Martin (Dutch part)', 'checkout-fees-for-woocommerce' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'checkout-fees-for-woocommerce' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'checkout-fees-for-woocommerce' ),
			'SM' => __( 'San Marino', 'checkout-fees-for-woocommerce' ),
			'ST' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'checkout-fees-for-woocommerce' ),
			'SA' => __( 'Saudi Arabia', 'checkout-fees-for-woocommerce' ),
			'SN' => __( 'Senegal', 'checkout-fees-for-woocommerce' ),
			'RS' => __( 'Serbia', 'checkout-fees-for-woocommerce' ),
			'SC' => __( 'Seychelles', 'checkout-fees-for-woocommerce' ),
			'SL' => __( 'Sierra Leone', 'checkout-fees-for-woocommerce' ),
			'SG' => __( 'Singapore', 'checkout-fees-for-woocommerce' ),
			'SK' => __( 'Slovakia', 'checkout-fees-for-woocommerce' ),
			'SI' => __( 'Slovenia', 'checkout-fees-for-woocommerce' ),
			'SB' => __( 'Solomon Islands', 'checkout-fees-for-woocommerce' ),
			'SO' => __( 'Somalia', 'checkout-fees-for-woocommerce' ),
			'ZA' => __( 'South Africa', 'checkout-fees-for-woocommerce' ),
			'GS' => __( 'South Georgia/Sandwich Islands', 'checkout-fees-for-woocommerce' ),
			'KR' => __( 'South Korea', 'checkout-fees-for-woocommerce' ),
			'SS' => __( 'South Sudan', 'checkout-fees-for-woocommerce' ),
			'ES' => __( 'Spain', 'checkout-fees-for-woocommerce' ),
			'LK' => __( 'Sri Lanka', 'checkout-fees-for-woocommerce' ),
			'SD' => __( 'Sudan', 'checkout-fees-for-woocommerce' ),
			'SR' => __( 'Suriname', 'checkout-fees-for-woocommerce' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'checkout-fees-for-woocommerce' ),
			'SZ' => __( 'Swaziland', 'checkout-fees-for-woocommerce' ),
			'SE' => __( 'Sweden', 'checkout-fees-for-woocommerce' ),
			'CH' => __( 'Switzerland', 'checkout-fees-for-woocommerce' ),
			'SY' => __( 'Syria', 'checkout-fees-for-woocommerce' ),
			'TW' => __( 'Taiwan', 'checkout-fees-for-woocommerce' ),
			'TJ' => __( 'Tajikistan', 'checkout-fees-for-woocommerce' ),
			'TZ' => __( 'Tanzania', 'checkout-fees-for-woocommerce' ),
			'TH' => __( 'Thailand', 'checkout-fees-for-woocommerce' ),
			'TL' => __( 'Timor-Leste', 'checkout-fees-for-woocommerce' ),
			'TG' => __( 'Togo', 'checkout-fees-for-woocommerce' ),
			'TK' => __( 'Tokelau', 'checkout-fees-for-woocommerce' ),
			'TO' => __( 'Tonga', 'checkout-fees-for-woocommerce' ),
			'TT' => __( 'Trinidad and Tobago', 'checkout-fees-for-woocommerce' ),
			'TN' => __( 'Tunisia', 'checkout-fees-for-woocommerce' ),
			'TR' => __( 'Turkey', 'checkout-fees-for-woocommerce' ),
			'TM' => __( 'Turkmenistan', 'checkout-fees-for-woocommerce' ),
			'TC' => __( 'Turks and Caicos Islands', 'checkout-fees-for-woocommerce' ),
			'TV' => __( 'Tuvalu', 'checkout-fees-for-woocommerce' ),
			'UG' => __( 'Uganda', 'checkout-fees-for-woocommerce' ),
			'UA' => __( 'Ukraine', 'checkout-fees-for-woocommerce' ),
			'AE' => __( 'United Arab Emirates', 'checkout-fees-for-woocommerce' ),
			'GB' => __( 'United Kingdom (UK)', 'checkout-fees-for-woocommerce' ),
			'US' => __( 'United States (US)', 'checkout-fees-for-woocommerce' ),
			'UY' => __( 'Uruguay', 'checkout-fees-for-woocommerce' ),
			'UZ' => __( 'Uzbekistan', 'checkout-fees-for-woocommerce' ),
			'VU' => __( 'Vanuatu', 'checkout-fees-for-woocommerce' ),
			'VA' => __( 'Vatican', 'checkout-fees-for-woocommerce' ),
			'VE' => __( 'Venezuela', 'checkout-fees-for-woocommerce' ),
			'VN' => __( 'Vietnam', 'checkout-fees-for-woocommerce' ),
			'WF' => __( 'Wallis and Futuna', 'checkout-fees-for-woocommerce' ),
			'EH' => __( 'Western Sahara', 'checkout-fees-for-woocommerce' ),
			'WS' => __( 'Western Samoa', 'checkout-fees-for-woocommerce' ),
			'YE' => __( 'Yemen', 'checkout-fees-for-woocommerce' ),
			'ZM' => __( 'Zambia', 'checkout-fees-for-woocommerce' ),
			'ZW' => __( 'Zimbabwe', 'checkout-fees-for-woocommerce' ),

			'FX' => __( 'France, Metropolitan', 'checkout-fees-for-woocommerce' ),
			'AP' => __( 'African Regional Industrial Property Organization', 'checkout-fees-for-woocommerce' ),
			'AS' => __( 'American Samoa', 'checkout-fees-for-woocommerce' ),
			'GU' => __( 'Guam', 'checkout-fees-for-woocommerce' ),
			'MP' => __( 'Northern Mariana Islands', 'checkout-fees-for-woocommerce' ),
			'UM' => __( 'United States Minor Outlying Islands', 'checkout-fees-for-woocommerce' ),
			'PR' => __( 'Puerto Rico', 'checkout-fees-for-woocommerce' ),
			'VI' => __( 'Virgin Islands, U.S.', 'checkout-fees-for-woocommerce' ),
		);
	}
}

if ( ! function_exists( 'alg_checkout_fees_card_scheme' ) ) {
	/**
	 * Get all card schemes array.
	 *
	 * @version 2.4.0
	 * @return  array
	 */
	function alg_checkout_fees_card_scheme() {
		return apply_filters(
			'alg_wc_checkout_fees_card_schemes',
			array(
				'any'              => 'Any',
				'visa'             => 'Visa',
				'mastercard'       => 'MasterCard',
				'american_express' => 'American Express',
				'discover'         => 'Discover',
				'diners'           => 'Diners Club',
				'jcb'              => 'JCB',
				'unionpay'         => 'UnionPay',
				'rupay'            => 'RuPay',
				'maestro'          => 'Maestro',
				'interac'          => 'Interac',
				'elo'              => 'Elo',
				'hipercard'        => 'Hipercard',
				'cabal'            => 'Cabal',
				'mir'              => 'Mir',
				'troy'             => 'Troy',
				'verve'            => 'Verve',
				'uzcard'           => 'Uzcard',
				'belkart'          => 'Belkart',
				'bancontact'       => 'Bancontact',
				'cartes'           => 'Cartes Bancaires',
				'girocard'         => 'Girocard',
				'zimswitch'        => 'Zimswitch',
			)
		);
	}
}
