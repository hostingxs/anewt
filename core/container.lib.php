<?php

/*
 * Anewt, Almost No Effort Web Toolkit, core module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */


/**
 * Generic container class to hold data.
 *
 * This class is a data container and provides a number of methods to get, set,
 * add, import and export data. See the core module documentation for more
 * information on how to use this class efficiently.
 */
class Container
{
	/**
	 * Internal storage.
	 *
	 * This array holds all the values stored in the container.
	 */
	protected $__data = array();


	/**
	 * Create a new Container instance.
	 *
	 * An optional parameter can be supplied to fill the container with initial
	 * values. This is the same as calling seed() manually. You may safely
	 * override the constructor for your own classes; there is no need to call
	 * the parent constructor.
	 *
	 * \param $data
	 *   An associative array with the initial data (optional)
	 *
	 * \see seed
	 */
	function __construct($data=null)
	{
		if (!is_null($data))
		{
			assert('is_assoc_array($data)');
			$this->seed($data);
		}
	}

	/** \{
	 * \name Methods for handling data
	 *
	 * These methods can be used to get and set data. These methods may call
	 * into special getter and setter methods. Most methods have a corresponding
	 * non-magic method that does not invoke special getter and setter methods.
	 */

	/**
	 * Return the data referenced by name.
	 *
	 * By default just returns the data referenced by $name. If you write
	 * a class that extends Container, you can write special methods to override
	 * the behaviour of the Container::get() function. In order to do this, you
	 * can write a get_foo() method which will be invoked if you use \c
	 * get('foo'). This allows for a consistent API, optional getter and setter
	 * methods and on-the-fly generation of data.
	 *
	 * This method supports basic caching of values. If you write a get_foo_()
	 * method (note the trailing underscore!) and you call \c $obj->get('foo'),
	 * the value will be cached and your special method will only be called
	 * once. Note that regular getters, eg. \c get_foo() always has precedence
	 * over the caching methods. You should provide either \c get_foo() or \c
	 * get_foo_(), not both. This type of caching is very useful if you do
	 * database queries or other computationally intensive things in your getter
	 * method and you only need to do it once per instance.
	 *
	 * If you pass multiple parameters to this method, the first one will be
	 * used as the name for the data to get. Special methods like the \c
	 * get_foo() example from above will be passed the additional parameters, so
	 * that you can write a get_foo($bar, $baz) method.
	 *
	 * \param
	 *   $name The name of the value to get.
	 *
	 * \param $params
	 *   Optional number of additional parameters.
	 *
	 * \return
	 *   The requested data.
	 *
	 * \see set
	 */
	function get($name, $params=null)
	{
		$args = func_get_args();
		count($args) >= 1 or trigger_error('Container::get() needs at least 1 parameter', E_USER_ERROR);
		$name = array_shift($args);
		$name = str_replace('-', '_', $name);
		assert('is_int($name) || is_string($name)');
		$specialfuncname = 'get_'.$name;
		$specialfuncname_cache = 'get_'.$name.'_';

		/* Special getter method available? */
		if (method_exists($this, $specialfuncname))
		{
			return call_user_func_array(array(&$this, $specialfuncname), $args);

		/* Special caching method available? */
		} elseif (method_exists($this, $specialfuncname_cache))
		{
			if (!$this->_isset($name))
			{
				$this->_set($name, call_user_func_array(array(&$this,
								$specialfuncname_cache), $args));
			}
		}

		/* Fallback to regular variable storage */
		return $this->_get($name);
	}

	/**
	 * Alias function for \c get.
	 * \param $name
	 * \see get
	 */
	function __get($name)
	{
		return $this->get($name);
	}

	/**
	 * Return the data by name, or a default value.
	 *
	 * You can specify optional additional parameters that will be passed to the
	 * getter method, but the last parameter you provide is used as the default
	 * value in case $name was not set.
	 *
	 * \param $name
	 *   The name to get.
	 *
	 * \param $params
	 *   Optional number of additional parameters.
	 *
	 * \param $default
	 *   The default value, in case $name could not be retrieved.
	 *
	 * \see Container::get
	 * \see Container::setdefault
	 * \see array_get_default
	 */
	function getdefault($name, $params=null, $default=null)
	{
		$args = func_get_args();
		count($args) >= 2 or trigger_error('Container::getdefault() needs at least 2 parameters', E_USER_ERROR);
		$name = $args[0];

		assert('is_int($name) || is_string($name)');

		$default_value = array_pop($args);
		if ($this->is_set($name))
		{
			return call_user_func_array(array(&$this, 'get'), $args);
		}

		return $default_value;
	}

	/**
	 * Store a value in the container.
	 *
	 * You can write special methods like \c set_foo() accepting a variable
	 * number of parameters to override the default behaviour of this methods.
	 * See the documentation for Container::get() for more information.
	 *
	 * \param $name
	 *   The name of the data to store (int or string).
	 *
	 * \param $value
	 *   The value of the data to store.
	 *
	 * \see get
	 */
	function set($name, $value)
	{
		$args = func_get_args();
		count($args) >= 2 or trigger_error('Container::set() needs at least 2 parameters', E_USER_ERROR);
		$name = array_shift($args);
		$name = str_replace('-', '_', $name);
		assert('is_int($name) || is_string($name)');
		$specialfuncname = 'set_'.$name;
		if (method_exists($this, $specialfuncname))
		{
			// call the special purpose function to set the value
			call_user_func_array(array(&$this, $specialfuncname), $args);
		} else {
			// fallback to regular variable storage
			$value = $args[0];
			$this->_set($name, $value);
		}
	}

	/**
	 * Alias function for \c set.
	 * \param $name
	 * \param $value
	 * \see set
	 */
	function __set($name, $value)
	{
		return $this->set($name, $value);
	}

	/**
	 * Set a default value if no value was previously set.
	 *
	 * This method checks if the variable referenced by \c $name is set. If not,
	 * it sets the default value. If the variable is already set, this method
	 * does nothing at all.
	 *
	 * \param $name
	 *   The name to set.
	 *
	 * \param $default
	 *   The default value to store, in case \c $name was not set before.
	 *   Multiple arguments may be specified; this is useful for custom setters
	 *   that accept multiple arguments.
	 *
	 * \see Container::set
	 * \see Container::getdefault
	 * \see array_set_default
	 */
	function setdefault($name, $default)
	{
		$args = func_get_args();
		count($args) >= 2 or trigger_error('Container::setdefault() needs at least 2 parameters', E_USER_ERROR);
		$name = $args[0];

		assert('is_int($name) || is_string($name)');

		/* Stop if the variable was already set */
		if ($this->is_set($name))
			return;

		call_user_func_array(array(&$this, 'set'), $args);
	}

	/**
	 * Add data to a list.
	 *
	 * You can write special methods like \c add_foo() accepting a variable
	 * number of parameters in classes extending Container. See the
	 * documentation for Container::get() for more information.
	 *
	 * \param $name
	 *   The name of the data to store (int or string).
	 *
	 * \param $value
	 *   The value of the data to add.
	 *
	 * \see Container::get
	 * \see Container::set
	 */
	function add($name, $value)
	{
		$args = func_get_args();
		count($args) >= 2 or trigger_error('Container::add() needs at least 2 parameters', E_USER_ERROR);
		$name = array_shift($args);
		$name = str_replace('-', '_', $name);
		assert('is_int($name) || is_string($name)');
		$specialfuncname = 'add_'.$name;
		if (method_exists($this, $specialfuncname))
		{
			// call the special purpose function to add the value
			call_user_func_array(array(&$this, $specialfuncname), $args);
		} else {
			// fallback to regular variable storage
			$value = $args[0];
			$this->_add($name, $value);
		}
	}

	/**
	 * Delete a value from the container.
	 *
	 * This method is only useful for simple data fields (i.e. without custom
	 * getter/setter methods) and for those with cached get methods. In the last
	 * case, unsetting the saved data will make sure the data is 'invalidated',
	 * so the get-method gets re-executed the next time the value is needed.
	 *
	 * \param $name
	 *   The name of the data to unset (int or string).
	 *
	 * \see Container::get
	 * \see Container::set
	 */
	function delete($name=null)
	{
		assert('is_int($name) || is_string($name)');

		$name = str_replace('-', '_', $name);
		if (array_key_exists($name, $this->__data))
			unset($this->__data[$name]);

		/* Silently return if the key did not exist */
	}

	/**
	 * Alias function for \c delete.
	 * \param $name
	 * \see delete
	 */
	function __unset($name)
	{
		return $this->delete($name);
	}

	/**
	 * Check whether a value for the name specified exists.
	 *
	 * This methods checks whether a value exists for the specified \c name, or
	 * whether a special getter function for it exists. Use this method if you
	 * want to be sure you can call <code>get($name)</code> without errors later
	 * on.
	 *
	 * \param $name
	 *   The name of the data to check for (int or string).
	 *
	 * \return
	 *   True if the data is available, false otherwise.
	 *
	 * \see Container::_isset
	 */
	function is_set($name)
	{
		assert('is_int($name) || is_string($name)');
		$name = str_replace('-', '_', $name);
		$specialfuncname = 'get_'.$name;
		$specialfuncname_cache = 'get_'.$name.'_';
		return $this->_isset($name)
			|| method_exists($this, $specialfuncname)
			|| method_exists($this, $specialfuncname_cache);
	}

	/**
	 * Alias function for \c is_set.
	 * \param $name
	 * \see is_set
	 */
	function __isset($name)
	{
		return $this->is_set($name);
	}

	/**
	 * Return the length of a stored list.
	 *
	 * By default just returns the length of the list referenced by \c $name. If
	 * you use custom methods to override Container::get() behaviour, you also
	 * need a custom length function if the data returned is an array.
	 *
	 * For example, if this method is invoked as length('foo'), you should
	 * create a custom length function called length_foo().
	 *
	 * \param $name
	 *   The name of the data to count (int or string).
	 *
	 * \return
	 *   The length of the data.
	 */
	function length($name)
	{
		assert('is_int($name) || is_string($name)');
		assert('$this->is_set($name)');

		$name = str_replace('-', '_', $name);
		$specialfuncname = 'length_'.$name;
		if (method_exists($this, $specialfuncname))
		{
			// call the special purpose function to count the values
			$args = func_get_args();
			array_shift($args); // remove the first element (the name)
			return call_user_func_array(array(&$this, $specialfuncname), $args);
		} else {
			// fallback to regular variable storage
			$value = $this->get($name);
			assert('is_array($value)');
			return count($value);
		}
	}

	/**
	 * Seed the container with data.
	 *
	 * This method lets you populate the container instance with data from an
	 * associative array, e.g. a database result or a hardcoded array of initial
	 * values.
	 *
	 * \param $data
	 *   An array or object containing data. Both numeric, associative and mixed
	 *   arrays are supported, as well as Container objects (or anything that
	 *   provides a valid to_array() method.
	 *
	 * \param $keys
	 *   Optional parameter that is used to selectively seed this container with
	 *   data. If used in combination with an array of $data, this parameter is
	 *   treated as a list of keys that should be used. If used in combination
	 *   with an $data object, the parameter is passed to $obj->to_array()
	 *   unmodified.
	 *
	 * \see Container::to_array
	 */
	function seed($data, $keys=null)
	{
		assert('is_object($data) || is_array($data)');

		if (is_array($data))
		{
			/* Array parameter given. If a list of keys is specified, these are
			 * used. If not, all*/

			if (is_array($keys))
			{
				/* Treat $keys as a list of keys */
				foreach ($keys as $key)
				{
					assert('array_key_exists($key, $data);'." // $key");
					$this->set($key, $data[$key]);
				}
			} else {
				/* Ignore $keys parameter */
				foreach ($data as $n => $v)
				{
					$this->set($n, $v);
				}
			}


		} else {
			/* Object parameter given. Just call to_array() which does the real
			 * work and call this function again, but now with an array
			 * parameter (instead of an object) */
			$this->seed($data->to_array($keys));
		}
	}

	/**
	 * Invalidate a cached value.
	 *
	 * This is an alias for the Container::delete method.
	 *
	 * \param $name
	 *   The name of the data to invalidate
	 *
	 * \see Container::delete
	 */
	function invalidate($name)
	{
		assert('is_string($name)');
		$name = str_replace('-', '_', $name);
		$this->delete($name);
	}

	/**
	 * Remove all data from the container.
	 *
	 * This removes all values from the internal storage. Magic getter methods
	 * may still yield results afterwards, but the internal storage will be
	 * empty. Usually you don't need this method: use a new instance if you want
	 * to work with a different set of data.
	 *
	 * \see Container::delete()
	 */
	function clear()
	{
		$this->__data = array();
	}

	/**
	 * Return a list of all defined names.
	 *
	 * This method includes both keys in the internal storage and the name of
	 * values available only through getter methods.
	 *
	 * \return
	 *   A numeric array with all keys.
	 *
	 * \see Container::_keys
	 */
	function keys()
	{
		/* All internal keys... */
		$result = $this->_keys();

		/* ... and all keys found in getter methods */
		foreach (get_class_methods($this) as $method_name)
		{
			if (str_has_prefix($method_name, 'get_'))
			{
				/* Remove 'get_' prefix, handle caching getters and normalize */
				$name = substr($method_name, 4);
				$name = str_strip_suffix($name, '_');
				$name = str_replace('_', '-', $name);

				$result[] = $name;
			}
		}

		return $result;
	}

	/**
	 * Export all available data to an associative array.
	 *
	 * This method tries its best to get all the available data. It returns all
	 * the variables stored in the internal __data storage and calls all get_*
	 * methods too (this is off by default). Advanced getter methods that take
	 * parameters should provide a default value, otherwise the conversion is
	 * not going to work. Note that if both internal storage and getters are
	 * available for the same data, the latter has precedence, eg. the internal
	 * storage is ignored.
	 *
	 * \param $keys
	 *   A numeric array or a boolean value. If a numeric array is supplied it
	 *   will be used as a list of keys (passed to the get() method of the
	 *   $container). If boolean false is supplied, only the internal data of
	 *   the container is returned and special getter methods are not invoked.
	 *   If a boolean true is supplied, all data including the data returned by
	 *   special getter methods on the container is returned. Note that using
	 *   boolean true might result in a very expensive operation (all getter
	 *   methods are invoked!), so it should be used with care. If omitted (or
	 *   null), this parameter defaults to boolean false (only internal
	 *   container storage is returned).
	 *
	 * \return
	 *   An associative array containing all the data from this container.
	 *
	 * \see Container::_to_array
	 */
	function to_array($keys=null)
	{
		if (is_null($keys))
			$keys = false;

		if (is_bool($keys))
		{
			/* Export all data. $keys indicates whether get_* methods should be
			 * invoked. */

			if ($keys)
			{
				/* Find all keys and add the values they return when called.
				 * Getter methods will be called. */
				foreach ($this->keys() as $name)
				{
					$data[$name] = $this->get($name);
				}
			} else {
				/* Just copy the internal storage */
				$data = $this->_to_array();
			}

		} else {

			/* A list of keys is supplied */
			assert('is_numeric_array($keys)');

			$data = array();
			foreach ($keys as $key)
			{
				$data[$key] = $this->get($key);
			}
		}

		return $data;
	}

	/** \} */


	/** \{
	 * \name Non-magic methods for handling data
	 *
	 * These methods have roughly the same API as their magic counterparts, but
	 * don't call special getter and setter methods, so they work for simple
	 * values only. This is considerably faster if you're e.g. looping over many
	 * Container instances and extracting some values.
	 */

	/**
	 * Return the data referenced by name (non-magic).
	 *
	 * \param $name
	 *   The name to get.
	 *
	 * \return
	 *   Associated value.
	 *
	 * \see Container::get
	 */
	function _get($name)
	{
		assert('is_int($name) || is_string($name)');

		$name = str_replace('-', '_', $name);

		if (array_key_exists($name, $this->__data))
			return $this->__data[$name];
		
		return false;
		/* Key unavailable */
		trigger_error(sprintf('%s is not specified.', $name), E_USER_ERROR);
	}

	/**
	 * Return the data by name, or a default value (non-magic).
	 *
	 * \param $name
	 *   The name to get.
	 *
	 * \param $default
	 *   The default value to return if no value for $name was stored before.
	 *
	 * \return
	 *   The associated value or the default value supplied.
	 *
	 * \see Container::getdefault
	 * \see Container::_get
	 * \see Container::get
	 */
	function _getdefault($name, $default=null)
	{
		assert('is_int($name) || is_string($name)');

		$name = str_replace('-', '_', $name);

		if (array_key_exists($name, $this->__data))
			return $this->__data[$name];

		return $default;
	}

	/**
	 * Store a value in the container (non-magic).
	 *
	 * \param $name
	 *   The name of the data to store.
	 *
	 * \param $value
	 *   The value of the data to store.
	 *
	 * \see Container::set
	 */
	function _set($name, $value)
	{
		assert('is_int($name) || is_string($name)');
		$name = str_replace('-', '_', $name);
		$this->__data[$name] = $value;
	}

	/**
	 * Add data to a list (non-magic).
	 *
	 * \param $name
	 *   The name of the list to add to.
	 *
	 * \param $value
	 *   The value of the data to add.
	 *
	 * \see Container::add
	 */
	function _add($name, $value)
	{
		assert('is_int($name) || is_string($name)');

		$name = str_replace('-', '_', $name);
		if (!$this->_isset($name))
			$this->_set($name, array());

		assert('is_array($this->_get($name))');
		array_push($this->__data[$name], $value);
	}

	/**
	 * Check whether a value for the name specified exists (non-magic).
	 *
	 * \param $name
	 *   Name to check for.
	 *
	 * \return
	 *   True if set, false otherwise.
	 *
	 * \see Container::is_set
	 */
	function _isset($name)
	{
		assert('is_int($name) || is_string($name)');
		$name = str_replace('-', '_', $name);
		return array_key_exists($name, $this->__data);
	}

	/**
	 * Populates the internal array from an array (non-magic).
	 *
	 * \param $data An associative array with data.
	 */
	function _seed($data)
	{
		foreach ($data as $name => $value)
		{
			$this->_set($name, $value);
		}
	}

	/**
	 * Return a list of all defined names (non-magic).
	 *
	 * \return
	 *   A numeric array with all internal keys.
	 *
	 * \see Container::keys
	 */
	function _keys()
	{
		return array_keys($this->__data);
	}

	/**
	 * Export all name-value pairs to an associative array (non-magic).
	 *
	 * This method does not call into getter methods.
	 *
	 * \return
	 *   Array (copy) containing the internal storage.
	 *
	 * \see Container::to_array
	 */
	function _to_array()
	{
		return $this->__data;
	}

	/** \} */


	/** \{
	 * \name Methods for handling references
	 *
	 * These methods can be used to handle references to existing objects.
	 * Magic getter and setter methods will be not be invoked.
	 */

	/**
	 * Return a reference to the data referenced by $name.
	 *
	 * This method does no magic at all and just returns a reference to the data
	 * in the internal storage.
	 *
	 * \param $name
	 *   The name to get.
	 *
	 * \return
	 *   A reference to the requested data.
	 *
	 * \see Container::setref
	 */
	function &getref($name)
	{
		assert('is_int($name) || is_string($name)');
		assert('$this->_isset($name)');
		$name = str_replace('-', '_', $name);
		return $this->__data[$name];
	}

	/**
	 * Store a reference to data.
	 *
	 * This method does no magic at all and just stores a reference in the
	 * internal storage.
	 *
	 * \param $name
	 *   The name of the data to store (int or string).
	 *
	 * \param $value
	 *   The data to store (this must be a variable, literals are not allowed).
	 *
	 * \see Container::getref
	 */
	function setref($name, &$value)
	{
		assert('is_int($name) || is_string($name)');
		$name = str_replace('-', '_', $name);
		$this->__data[$name] = &$value;
	}

	/**
	 * Add a reference to data to a list.
	 *
	 * This method does no magic at all and just adds a reference in the
	 * internal storage.
	 *
	 * \param $name
	 *   The name of the data to store (int or string).
	 *
	 * \param $value
	 *   The data to store (this must be a variable, literals are not allowed).
	 *
	 * \see Container::setref
	 */
	function addref($name, &$value)
	{
		assert('is_int($name) || is_string($name)');

		$name = str_replace('-', '_', $name);
		if (!$this->_isset($name))
			$this->_set($name, array());

		assert('is_array($this->_get($name))');
		$this->__data[$name][] = &$value;
	}

	/** \} */
}

?>
