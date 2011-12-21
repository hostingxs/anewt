<?php
/*
 * Anewt, Almost No Effort Web Toolkit, filter module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */

/**
 * This filter makes only the first letter upper case. The rest of the
 * string is converted to lowercase.
 */
class AnewtFilterFirstUpperCase extends AnewtFilter
{
	/**
	 * Converts the first letter to upper case, the rest to lowercase.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The filtered string with the first character converted uppercase, 
	 * the remainder to lowercase.
	 */
	function filter($value) {
		assert('is_string($value)');
		return ucfirst(strtolower($value));
	}
}

/**
 * Filter to transform a string to all uppercase.
 */
class AnewtFilterUppercase extends AnewtFilter
{

	/**
	 * Convert the text to uppercase.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The filtered string with all characters converted to uppercase.
	 */
	function filter($value) {
		assert('is_string($value)');
		return strtoupper($value);
	}
}

/**
 * Filter to transform a string to all lowercase.
 */
class AnewtFilterLowercase extends AnewtFilter
{
	/**
	 * Converts the text to lowercase.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The filtered string with all characters converted to lowercase.
	 */
	function filter($value) {
		assert('is_string($value)');
		return strtolower($value);
	}
}

/**
 * Filter to put a maximum on the number of characters.
 */
class AnewtFilterMaxLength extends AnewtFilter
{
	var $howmany;

	/**
	 * Creates an AnewtMaxLengthFilter instance.
	 *
	 * \param $howmany The maximum number of characters.
	 */
	function __construct($howmany) {
		assert('is_int($howmany)');
		assert('$howmany >= 0');
		$this->howmany = $howmany;
	}

	/**
	 * Applies the filter to the passed value.
	 *
	 * \param $value String to filter.
	 *
	 * \return The filtered string with all characters above the maximum length
	 * cut off.
	 */
	function filter($value) {
		assert('is_string($value)');
		return substr($value, 0, $this->get('howmany'));
	}
}

/**
 * Filter to strip leading and trailing whitespace.
 */
class AnewtFilterStripWhitespace extends AnewtFilter
{
	var $leading;
	var $trailing;

	/**
	 * Creates an AnewtFilterStripWhitespace.
	 *
	 * \param $leading Whether to strip leading whitespace (optional, defaults
	 * to true).
	 * \param $trailing Whether to strip trailing whitespace (optional, defaults
	 * to true).
	 */
	function init($leading=true, $trailing=true) {
		if (is_null($leading)) $leading = null;
		if (is_null($trailing)) $trailing = null;

		assert('is_bool($leading)');
		assert('is_bool($trailing)');

		$this->leading = $leading;
		$this->trailing = $trailing;
	}

	/**
	 * Filters the passed value.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The filtered value.
	 */
	function filter($value) {
		assert('is_string($value)');

		if ($this->leading)
			$value = ltrim($value);

		if ($this->trailing)
			$value = rtrim($value);

		return $value;
	}
}

/**
 * Filter to transform a string to another string using preg_replace.
 */
class AnewtFilterPreg extends AnewtFilter {

	protected $regexp;
	protected $replace;

	/**
	 * Constructor.
	 *
	 * \param $regexp
	 *   The regular expression. This is the first argument to preg_replace.
	 * \param $replace
	 *   The replacement value. This is the second argument to preg_replace.
	 */
	function __construct($regexp, $replace)
	{
		assert('is_string($regexp)');
		assert('is_string($replace)');

		$this->regexp = $regexp;
		$this->replace = $replace;
	}
	/**
	 * Applies the PregFilter to the passed value.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The string filtered through preg_replace.
	 */
	function filter($value) {
		assert('is_string($value)');
		return preg_replace($this->regexp, $this->replace, $value);
	}
}

?>
