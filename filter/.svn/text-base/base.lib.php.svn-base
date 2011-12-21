<?php

/*
 * Anewt, Almost No Effort Web Toolkit, filter module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */


/**
 * \protected
 *
 * Base class for filter.
 *
 * This class does nothing by default. Real filter implementations should
 * subclass this class and implement at least the filter() method.
 */
abstract class AnewtFilter extends Container
{
	/**
	 * Create a new filter instance.
	 *
	 * This method can be overridden in subclasses, e.g. to handle some values
	 * that influence the filter's behaviour.
	 *
	 * Make sure to call the parent constructor from your custom contructor!
	 */
	function __construct()
	{
		/* Do nothing. */
	}

	/**
	 * The filter() method should perform a transformation on the passed
	 * parameter and return its result.
	 *
	 * \param $value The value to filter.
	 *
	 * \return The filtered value.
	 */
	abstract function filter($value);
}

?>
