<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

	function encode_url($string, $url_safe=TRUE) {
		
		$CI =& get_instance();
		$ret = $CI->encryption->encrypt($string);

		if ($url_safe) {
			$ret = strtr(
				$ret,
				array(
					'+' => '.',
					'=' => '-',
					'/' => '~'
				)
			);
		}

		return $ret;
	}

	function decode_url($string) {
		
        $CI =& get_instance();
		$string = strtr(
            $string,
            array(
                '.' => '+',
                '-' => '=',
                '~' => '/'
            )
        );

		return $CI->encryption->decrypt($string);
	}
?>