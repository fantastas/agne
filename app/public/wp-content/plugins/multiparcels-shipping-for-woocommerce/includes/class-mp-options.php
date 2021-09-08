<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

/**
 * Class MP_Options
 */
class MP_Options
{
    /**
     * @var array
     */
    private $options = [];

    const OPTIONS_PREFIX = 'multiparcels_';
    const OPTIONS_KEY = 'multiparcels_settings';

    /**
     * Options constructor.
     */
    public function __construct()
    {
        $this->fillOptions();
    }

	/**
	 * @param string      $option Option to get
	 * @param string|null $other_settings
	 * @param mixed       $default
	 *
	 * @return mixed|null
	 */
    public function get($option, $other_settings = null, $default = null)
    {
        if ($other_settings == null && array_key_exists($option, $this->options)) {
            $value = $this->options[$option];

            if ($value == '') {
                $value = null;
            }

            return $value;
        }

        if ($other_settings != null) {
            $options = get_option(self::OPTIONS_PREFIX . $option);

            if ( ! $options) {
                return [];
            }

            return $options;
        }

        return $default;
    }

	/**
	 * @param string $option
	 * @param mixed  $default
	 *
	 * @return mixed|null
	 */
	public function get_other( $option, $default = null ) {
		return $this->get( $option, true, $default );
	}

	/**
	 * @param string $option
	 * @param mixed  $default
	 *
	 * @return mixed|null
	 */
	public function get_other_setting( $option, $setting, $default = null ) {
	    $array=$this->get( $option, true, $default );

        if (is_array($array) && array_key_exists($setting, $array)) {
            return $array[$setting];
        }

		return $default;
	}

	/**
	 * @param string $option
	 *
	 * @return bool
	 */
    public function getBool($option)
    {
        return (bool)$this->get($option);
    }

	/**
	 * @param string $option
	 *
	 * @return array
	 */
    public function get_array($option)
    {
        $value = $this->get($option, false, []);

        if ( ! is_array($value)) {
            $value = [];
        }

        return $value;
    }

	/**
	 * @param string $option
	 *
	 * @return bool
	 */
    public function in_array($option, $needle)
    {
        $haystack = $this->get_array($option);

        return in_array($needle, $haystack);
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function all()
    {
        return $this->options;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param bool|null $other_settings
     */
    public function set($key, $value, $other_settings = false)
    {
        if ($other_settings == false) {
            $this->options[$key] = $value;

            update_option(self::OPTIONS_KEY, $this->options);
        } else {
            update_option(self::OPTIONS_PREFIX . $key, $value);
        }
    }

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set_other( $key, $value ) {
		$this->set( $key, $value, true );
	}

    /**
     * Remove the prefixes from keys
     */
    private function fillOptions()
    {
        $options = get_option(self::OPTIONS_KEY);
        if (is_array($options)) {
            $this->options = $options;
        }
    }

    /**
     * Helper for
     *
     * @return string|null
     */
    public function get_api_key()
    {
        return trim($this->get('api_key'));
    }

	public function get_sender_location( $id ) {
		$sender_locations = $this->get_other( 'sender_locations' );

		if ( ! array_key_exists( $id, $sender_locations ) ) {
			return [];
		}

		return $sender_locations[$id];
    }

	/**
	 * @return mixed|null
	 */
	public function get_sender_locations() {
		return $this->get_other( 'sender_locations', [] );
	}

	public function set_sender_location( $id, $data ) {
		$sender_locations = $this->get( 'sender_locations', true );

		$sender_locations[$id]=$data;

		$this->set_other( 'sender_locations', $sender_locations );
	}

	public function delete_sender_location( $id ) {
		$sender_locations = $this->get( 'sender_locations', true );

		unset( $sender_locations[ $id ] );

		$this->set_other( 'sender_locations', $sender_locations );
	}

	public function get_default_sender_location() {
		return MultiParcels()->options->get( 'default_sender_location' );
	}

	public function set_default_sender_location( $id ) {
		MultiParcels()->options->set( 'default_sender_location', $id );
	}
}

return new MP_Options();
