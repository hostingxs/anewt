<?php
/*
 * Anewt, Almost No Effort Web Toolkit, filter module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */

/**
 * This filter changes the value to an integer. Use this filter before other integer filters.
 */
class AnewtFilterInteger extends AnewtFilter
{
	/**
	 * Converts the value to its abolute value.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The value casted to an integer.
	 */
	function filter($value)
	{
		return (int)$value;
	}
}

/**
 * This filter changes the value to a float. Use this filter before other float filters.
 */
class AnewtFilterfloat extends AnewtFilter
{
	/**
	 * Converts the value to its abolute value.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The value casted to a float.
	 */
	function filter($value)
	{
		return (int)$value;
	}
}

/**
 * This filter changes a value to its absolute value.
 */
class AnewtFilterAbsolute extends AnewtFilter
{
	/**
	 * Converts the value to its abolute value.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The absolute value.
	 */
	function filter($value)
	{
		assert('is_int($value) || is_float($value)');
		return abs($value);
	}
}

?>
