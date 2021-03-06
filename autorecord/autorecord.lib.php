<?php

/*
 * Anewt, Almost No Effort Web Toolkit, autorecord module
 *
 * Copyright (C) 2006  Wouter Bolsterlee <uws@xs4all.nl>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA+
 */


anewt_include('database.new');


/**
 * Automatic database record object.
 *
 * AutoRecord is an advanced database wrapper class implementing the active
 * record pattern. Each class wraps a single database table, providing you with
 * a convenient search API for querying and easy to use save(), insert() and
 * delete() methods for object manipulation.
 *
 * The query API consists of several static methods:
 *
 * - AutoRecord::find_all() retrieves all records in the database
 * - AutoRecord::find_by_id() and AutoRecord::find_one_by_id() return records
 *   based on the primary key value
 * - AutoRecord::find_by_sql() and AutoRecord::find_one_by_sql() return records
 *   based on constraints expressed as SQL query parts.
 * - AutoRecord::find_by_column() and AutoRecord::find_one_by_column() return
 *   records where a specified column has the specified value.
 *
 * The data manipulation methods are instance methods that operate on object
 * instances themselves:
 *
 * - AutoRecord::save() saves the current record
 * - AutoRecord::delete() deletes the current record
 *
 * In order to create an AutoRecord subclass, you should name your own class
 * <code>Foo_</code> (with a trailing underscore), and override some of the methods
 * (_db_table() and _db_columns() are obligatory). See the documentation on the
 * methods below for more information. Right after your class definition, you
 * should register your AutoRecord subclass so that the actual magic can be put
 * into place: <code>AutoRecord::register('Foo')</code>. Now you can use the
 * <code>Foo</code> class. Example:
 * <code>$somefoo = Foo::find_one_by_id(12)</code>
 *
 * \todo
 *   find_previous() and find_next() methods (based on sort order)
 */
class AutoRecord extends Container
{
	private $__fromdb		= false;
	/** \{
	 * \name Static methods
	 */

	/**
	 * Return a reference to the default database connection. Override this
	 * method if you want to use a custom database instance.
	 *
	 * \return
	 *   A reference to a Database instance
	 */

	protected static function _db() {
		return AnewtDatabase::get_connection( "default" );
	}
	static protected function _db_table_engine() {
		return "INNODB";
	}
	/**
	 * Return the name of the table to use. You must override this method for
	 * your own classes. An example might be the following one-liner:
	 * <code>return 'person';</code>
	 *
	 * \return
	 *   An string with the table name to use
	 *
	 * \see AutoRecord::_db_columns
	 */
	protected static function _db_table()
	{
		throw new Exception('AutoRecord::_db_table() must be overridden.');
	}

	/**
	 * Return an associative array of column name => column type mappings. You
	 * must override this method for your own classes. An example might be the
	 * following one-liner:
	 * <code>
	 * return array('id' => 'int', 'name' => 'str', 'age' => 'int');
	 * </code>
	 *
	 * The complex method returns an array with the keys being the columns:
	 * <code>
	 * [TODO]
	 * </code>
	 *
	 * \return
	 *   An associative array with column name => type items
	 *
	 * \see AutoRecord::_db_table
	 */
	protected static function _db_columns()
	{
		throw new Exception('AutoRecord::_db_columns() must be overridden.');
	}

	/**
	 * \deprecated This is no longer used. All fields are skipped on insert
	 * if their values are not set. No use inserting explicit \c NULL values
	 * if they're not set.
	 * 
	 * Return an array of column names which should be skipped on insert queries
	 * when no values are given. The database is expected to fill a default
	 * value for these columns.
	 */
	protected static function _db_skip_on_insert()
	{
		return array();
	}

	/**
	 * Return an array of column names which should be skipped on update queries.
	 * These are usually read-only values which don't need to get updated
	 * every time.
	 */
	protected static function _db_skip_on_update()
	{
		return array();
	}

	/**
	 * \static \protected
	 *
	 * Return an array of column names which are read-only and should never be
	 * written. The database is expected to fill a default value for these
	 * columns.
	 *
	 * Note that like _db_skip_on_insert the values are still written on an
	 * insert when they are supplied, but they are never written on an update.
	 */
	protected static function _db_skip_on_save()
	{
		return array();
	}

	/**
	 * \static \protected
	 *
	 * Return the name of the primary key. Override this method if you don't
	 * want to use the default value 'id'.
	 *
	 * \return
	 *   The name of the primary key column
	 */
	protected static function _db_primary_key()
	{
		return 'id';
	}
	
	/**
	*	Return an array of primary keys, If false returned assume single primary key
	*
	*
	*
	*/
	protected static function _db_primary_keys()
	{
		return false;
	}

	/**
	 * Return the name of the sequence used for the primary key (PostgreSQL
	 * only). Override this function if you're using a non-standard sequence
	 * for the primary key values of your table.
	 *
	 * \return
	 *   The name of the sequence used for the primary key value
	 */
	protected static function _db_primary_key_sequence()
	{
		return null;
	}

	/**
	 * Return the name of the default sort column. This column is used to sort
	 * the records in some methods that return multiple columns. If you specify
	 * order-by parameters to methods, this value is not used, but for simple
	 * cases like find_all() it serves as the sort column. By default, the
	 * primary key value is used. Override this method if you want a custom
	 * column to be used, eg. a position or date column.
	 *
	 * It is also possible to order by multiple columns by returning an array
	 * of columns. In this case you have to override _db_sort_order() as well
	 * by letting it return an array with the same amount of elements.
	 *
	 * Furthermore you can specify table aliasses used in _db_join_one() by
	 * using "table_alias.column" syntax.
	 * 
	 * \return
	 *   The name of the default sort column or an array of columns in order
	 *   of high to low priority.
	 *
	 * \see AutoRecord::_db_sort_order
	 */
	protected static function _db_sort_column()
	{
		return null;
	}

	/**
	 * Return the default sort order. The value returned by this method should
	 * be ASC or DESC. The default is ascending sort order. Override this method
	 * if you want to change it.
	 *
	 * If you have overridden _db_sort_column() to return multiple column names,
	 * then override this method as well to return the same amount of elements.
	 *
	 * \return
	 *   The default sort order (ASC or DESC) or an array of sort orders.
	 *
	 * \see AutoRecord::_db_sort_column
	 */
	protected static function _db_sort_order()
	{
		return 'ASC';
	}

	/**
	 * \todo document this
	 */
	protected static function _db_has_many()
	{
		return array();
	}

	/**
	 * \todo document this
	 */
	protected static function _db_has_one()
	{
		return array();
	}

	/**
	 * Override this method if you want the default queries to contain
	 * joins with other tables, specified by other AutoRecord classes.
	 * 
	 * The format is an nummeric array of associative arrays. The
	 * associative arrays can have the following keys:
	 * 	foreign_class	- The AutoRecord class which corresponds to the
	 * 			  other table.
	 *	foreign_key	- (optional) The column name on the foreign
	 *			  side of the join. Defaults to the primary key
	 *			  of the foreign class.
	 * 	own_key		- (optional) The column name on this side of
	 *			  the join. Defaults to $foreign_key.
	 * 	foreign_alias	- (optional) The alias name of the other table.
	 *			  All columns are prefixed with $foreign_alias.
	 *			  Use this if you want the same table joined
	 *			  multiple times or just to avoid name clashes.
	 * 	own_alias	- (optional) Use this if 'own_key' is not part
	 * 			  of the table specified by this class; must be
	 * 			  a table name, not a class name.
	 * 	join_type	- (optional) the type of join. Defaults to
	 * 			  'left', but can be 'right' or 'inner' or
	 * 			  anything which can go before the "JOIN"
	 * 			  keyword in the SQL syntax.
	 *	multi		- (optional) If true a *:N join is assumed. If
	 *			  false or unset a *:1 join is assumed.
	 *	child_name	- (optional) The name of the key such that
	 *			  $item->get($key) returns an object or list
	 *			  of objects (with multi) of $foreign_class.
	 *	columns		- (optional) An array of columns if you don't
	 *			  want all the columns. Defaults to the column
	 *			  list of $foreign_class.
	 * \return
	 *	A nummeric array of associative arrays.
	 */
	protected static function _db_join_one()
	{
		return array();
	}

	/**
	 * Same as _db_join_one, only 'multi' is implied.
	 *
	 * \see _db_join_one
	 */
	protected static function _db_join_many() {
		return array();
	}
	/**
	*	[TODO] Backward Compatibility
	*
	*/
	/**
	 * Register a class as an AutoRecord. This does some evil voodoo magic to
	 * get things to work in a decent way. Your own class name should be called
	 * Foo_ (with a trailing underscore) and should extend AutoRecord; this
	 * method will dynamically create a class Foo extending your class with all
	 * the static methods in place.
	 *
	 * \param $class
	 *   The name of the class to register as an "active record" class (without
	 *   the trailing underscore)
	 */
	public static function register($class)
	{
		assert('is_string($class)');

		/* Extreme precautions because eval() is used */
		if (!preg_match('/^[a-z0-9_]+$/i', $class))
			trigger_error(sprintf(
				'AutoRecord::register(): illegal class name \'%s\'',
				$class), E_USER_ERROR);

		/* There should be a base class with an underscore at the end */
		if (!class_exists($class . '_'))
			trigger_error(sprintf(
				'AutoRecord::register(): class name \'%s_\' does not exist.',
				$class), E_USER_ERROR);

		/* Some useful variables */
		$class_ = $class . '_';

		/* Nasty hack to get some static methods in place, providing a nice API.
		 * Too bad there is no way to retrieve the current class name when
		 * calling static methods from derived classes (debug_backtrace() can be
		 * used in PHP4, but this doesn't work for PHP5). */

		$class_code = array();
		$class_code[] = 'class @@CLASS@@ extends @@CLASS@@_ {';
		$class_code[] = '}';

		/* Replace placeholders with actual values */
		$class_code = str_replace('@@CLASS@@', $class, join(NL, $class_code));

		/* Actually define the class */
		eval($class_code);
	}

	/**
	 * Create a SQL query part with columns to select. The SELECT keyword is not
	 * included.
	 *
	 * \param $class
	 *   The class name
	 *
	 * \param $table_alias
	 *   Optional string parameter to use as a table alias. If specified, this
	 *   string is prepended to all column names. This is useful if you do
	 *   selects from multiple tables (and identical column names) and you want
	 *   to select all columns from an AutoRecord table (eg. combined with
	 *   a join).
	 *
	 * \param $db
	 *   Reference to the database object instance.
	 *
	 * \return
	 *   String with comma-separated escaped column names. This string can be
	 *   used directly (unescaped) in the SELECT part of an SQL query.
	 */
	protected static function __db_select_clause( $table_alias=null, $joins=null)
	{
		$class		= get_called_class();
		$db		= static::_db();
		assert('is_string($class)');
		assert('$db instanceof AnewtDatabaseConnectionMySQL');

		$column_spec = array();
		$column_data = array();

		$columns 	= static::_db_columns();

		if (is_null($table_alias))
			$table_alias = static::_db_table();

		foreach(array_keys($columns) as $column)
		{
			$column_spec[] = "?table?.?column?";
			$column_data[] = $table_alias;
			$column_data[] = $column;
		}

		if (is_null($joins)) {
			$joins 		= static::_db_join_one();
			$joins_many 	= static::_db_join_many();
			foreach($joins_many as $join) {
				$join['multi'] = true;
				$joins[] = $join;
			}
		}
		foreach($joins as $join)
		{
			$foreign_class = $join['foreign_class'];
			$has_alias = false;
			assert('class_exists($foreign_class); // '.$foreign_class);
			$multi = array_get_default($join, 'multi', false);
			if (array_has_key($join, 'foreign_key'))
				$skip_key = $join['foreign_key'];
			else
				$skip_key = call_user_func(array(($multi ? $class : $foreign_class), '_db_primary_key'));

			if (array_has_key($join, 'foreign_alias'))
			{
				$foreign_alias = $join['foreign_alias'];
				$has_alias = true;
			} else
				$foreign_alias = call_user_func(array($foreign_class, '_db_table'));

			if (array_has_key($join, 'columns')) {
				$columns = $join['columns'];
			} else {
				$columns = array_keys(call_user_func(array($foreign_class, '_db_columns')));
			}
			assert('is_array($columns)');
			foreach($columns as $column)
			{
				if ($has_alias)
				{
					$column_spec[] = "?table?.?column? AS ?column?";
					$column_data[] = $foreign_alias;
					$column_data[] = $column;
					$column_data[] = sprintf("%s_%s", $foreign_alias, $column);
				} else {
					if ($column != $skip_key) {
						$column_spec[] = "?table?.?column?";
						$column_data[] = $foreign_alias;
						$column_data[] = $column;
					}
				}
			}
		}
		$tpl = new AnewtDatabaseSQLTemplate(join(",\n  ", $column_spec), $db);
		return $tpl->fill($column_data);
	}

	/**
	 * Create a SQL query part with tables to select from. The FROM keyword
	 * is not included.
	 *
	 * \param $class
	 *   The class name
	 *
	 * \param $table_alias
	 *   Optional string parameter to use as a table alias.
	 *
	 * \param $db
	 *   Reference to the database object instance.
	 *
	 * \return
	 *   String with comma-separated escaped table names with join
	 *   conditions. This string can be used directly (unescaped) in the
	 *   FROM part of an SQL query.
	 */
	protected static function __db_from_clause($table_alias=null, $joins=null)
	{
		$class		= get_called_class();
		$db		= static::_db();
		$from_clause = $db->escape_table_name(static::_db_table());

		if (is_null($table_alias))
			$table_alias = $db->escape_table_name(static::_db_table());
		else
			$from_clause = sprintf('%s %s', $from_clause, $table_alias);

		if (is_null($joins)) {
			$joins 		= static::_db_join_one();
			$joins_many 	= static::_db_join_many();
			foreach($joins_many as $join) {
				$join['multi'] = true;
				$joins[] = $join;
			}
		}

		foreach ($joins as $join)
		{
			$foreign_class = $join['foreign_class'];
			assert('class_exists($foreign_class)');
			$multi = array_get_default($join, 'multi', false);
			$join_type = strtoupper(array_get_default($join, 'join_type', 'left'));
			$foreign_alias = array_get_default($join, 'foreign_alias');
			$own_alias = array_get_default($join, 'own_alias', $table_alias);

			$foreign_table = $db->escape_table_name(call_user_func(array($foreign_class, '_db_table')));

			if (array_has_key($join, 'foreign_key'))
				$foreign_key = $join['foreign_key'];
			else
				$foreign_key = call_user_func(array(($multi ? $class : $foreign_class), '_db_primary_key'));
			$own_key = array_get_default($join, 'own_key', $foreign_key);

			if (is_null($foreign_alias))
				$foreign_alias = $foreign_table;
			else
				$foreign_table = sprintf('%s %s', $foreign_table, $foreign_alias);

			$from_clause = sprintf(
				"%s\n  %s JOIN %s ON (%s.%s = %s.%s)",
				$from_clause,
				$join_type,
				$foreign_table,
				$own_alias,
				$own_key,
				$foreign_alias,
				$foreign_key);
		}

		return $from_clause;
	}

	/**
	 * Creates the order by part of an SQL query. The ORDER BY keyword is
	 * not included.
	 *
	 * \param $class
	 *   The class name
	 *
	 * \param $table_alias
	 *   Optional string parameter to use as a table alias.
	 *
	 * \param $db
	 *   Reference to the database object instance.
	 *
	 * \return
	 *   String with comma-separated escaped order elements.  This string
	 *   can be used directly (unescaped) in the FROM part of an SQL query.
	 */
	protected static function __db_order_clause($table_alias=null)
	{
		$class		= get_called_class();
		$db		= static::_db();
		if(is_null($table_alias))
			$table_alias = $db->escape_table_name(static::_db_table());

		$sort_column = static::_db_sort_column();
		if (is_null($sort_column) && static::_db_primary_keys()) {
			$sort_column = static::_db_primary_keys();
			if( $sort_column && is_array( $sort_column )) {
				$sort_column = array_shift($sort_column);
			} else { $sort_column	= null; }
		}
			
		
		if (is_null($sort_column))
			$sort_column = static::_db_primary_key();

		if (!is_array($sort_column))
			$sort_column = array($sort_column);

		$sort_order = static::_db_sort_order();
		if (!is_array($sort_order))
			$sort_order = array($sort_order);

		$order_elements = array();
		foreach(array_keys($sort_column) as $key)
		{
			assert('($sort_order[$key] === "ASC") || ($sort_order[$key] === "DESC")');
			assert('is_string($sort_column[$key])');
			$parts = explode(".", $sort_column[$key], 2);
			if (count($parts) > 1)
			{
				$table = $db->escape_table_name($parts[0]);
				$column = $db->escape_column_name($parts[1]);
			} else {
				$table = $table_alias;
				$column = $db->escape_column_name($sort_column[$key]);
			}
			$order_elements[] = $table . "." . $column . " " . $sort_order[$key];
		}

		return implode(', ', $order_elements);
	}

	/**
	 * Converts a result row into an object instance.
	 *
	 * \param $class
	 *   The class name.
	 *
	 * \param $row
	 *   The row with data (or false).
	 *
	 * \return
	 *   A reference to a new instance or null if no data was provided
	 *
	 * \see AutoRecord::_db_objects_from_arrays
	 */
	protected static function __db_object_from_array($class, $row)
	{
		if ($row === false)
			return null;

		assert('is_assoc_array($row)');
		$instance = new $class();
		$instance->_seed($row);
		$instance->__fromdb	= true;
		return $instance;
	}

	/**
	 * Convert result rows into object instances.
	 *
	 * \param $class
	 *   The class name.
	 *
	 * \param $rows
	 *   The rows with data (or an empty array).
	 *
	 * \return
	 *   An array with references to new instances or an empty array no data was provided
	 *
	 * \see AutoRecord::__db_object_from_array
	 */
	protected static function __db_objects_from_arrays($class, $rows)
	{
		assert('is_numeric_array($rows)');

		$result = array();
		foreach ($rows as $row)
			$result[] = AutoRecord::__db_object_from_array($class, $row);

		return $result;
	}

	/**
	 * Splits a list of objects into parent objects with children objects.
	 * Mainly used for *:N joins where each item in the left table appears
	 * multiple times for each item on the right side.
	 *
	 * \param $child_name
	 *   The name in the parent object to group the children into
	 *
	 * \param $class
	 *   The class of the children
	 *
	 * \param $split_columns
	 *   The columns to split off the parent into the children.
	 *
	 * \param $testcol
	 *   If column $testcol of the child is NULL, it will be assumed that
	 *   there is no child for that parent. Should usually be the primary
	 *   key of the child.
	 *
	 * \param $parent_identifier
	 *   The column to group the parents with. Should usually be the
	 *   primary key of the parent.
	 *
	 * \param $objects
	 *   The list of objects to split.
	 *
	 * \param $only_one
	 *   Use for *:1 joins, so that the child will be a single item, and
	 *   not a list.
	 *
	 * \return
	 *   A list of all the parent items. Each parent has a
	 *   $parent->get($child_name) of which the return value depends on
	 *   $only_one.
	 */
	public static function db_split_objects($child_name, $class, $split_columns, $testcol, $parent_identifier, $objects, $only_one=false, $foreign_alias=false) {
		$ret = array();
		foreach ($objects as $object) {
			$subobject = new $class;
			if( $foreign_alias ) {
				$sc	= $split_columns;
				$split_columns = array();
				foreach( $sc as $s ) {
					$split_columns[]	= $s;
					$subobject -> set( $s , $object -> get(sprintf( "%s_%s" , $foreign_alias , $s )));
				}
			}

			$key = $object->get($parent_identifier);

			if (!isset($ret[$key])) {
				foreach ($split_columns as $column) {
					if ($column != $parent_identifier)
						$object->delete($column);
				}
				$ret[$key] = $object;
			}

			# If $testcol is not set, then there is probably the result of a LEFT JOIN, meaning there is no data.
			if (!is_null($testcol) && is_null($subobject->get($testcol))) {
				$ret[$key]->set($child_name, ($only_one ? null : array()));
			} else {
				if ($only_one)
					$ret[$key]->set($child_name, $subobject);
				else
					$ret[$key]->add($child_name, $subobject);
			}
		}
		return $ret;
	}

	/**
	 * Find one or more records by id. Don't use this method directly, use
	 * find_by_id or find_one_by_id on the class itself.
	 *
	 * \param $class
	 *   The class name.
	 *
	 * \param $just_one_result
	 *   Whether to return just one instance (or null) or an array of instances
	 *   (possibly empty)
	 *
	 * \param $values
	 *   The values to search for (primary key values)
	 *
	 * \param $db
	 *   Reference to the database object instance.
	 *
	 * \return
	 *   A single instance (or null) or an array of instances (or empty array)
	 */
	protected static function __db_find_by_id( $just_one_result, $values )
	{
		$class		= get_called_class();
		$db		= static::_db();
		assert('is_string($class)');
		assert('is_bool($just_one_result)');
		assert('is_numeric_array($values)');
		assert('$db instanceof AnewtDatabaseConnectionMySQL');
		
		// validate that find by id is impossible on AR classes which have multiple primary keys
		if( static::_db_primary_keys() ) {
			trigger_error( "find by id is not possible on an autorecord object with multiple primary keys" );
		}

		return static::__db_find_by_sql( 
			$just_one_result 
			, ( $just_one_result 
				? sprintf( "WHERE %s.%s = %s" , static::_db_table() , static::_db_primary_key() , $values[0])
				: sprintf( "WHERE %s.%s IN (%s)" , static::_db_table() , static::_db_primary_key() , implode( "," , $values))
			)
			, NULL
		);
	}

	/**
	 * Find one or more records by providing a part of the SQL query. Don't use
	 * this method directly; use find_by_sql or find_one_by_sql on the instance
	 * itself.
	 *
	 * \param $class
	 *   The class name.
	 *
	 * \param $just_one_result
	 *   Whether to return just one instance (or null) or an array of instances
	 *
	 * \param $sql
	 *   The constraints of the SQL query. This can be either null (no
	 *   constraints, selects all records), a string (the part of the WHERE
	 *   clause up to the end of the query) or an associative array (with
	 *   join, where, order-by, limit and offset indices, all optional).
	 *   You can use ?str?-style placholders for the data provided in
	 *   $values
	 *
	 * \param $values
	 *   The values to be substituted for the placeholders your provided with
	 *   your constraints.
	 *
	 * \param $db
	 *   Reference to the database object instance.
	 */
	protected static function __db_find_by_sql($just_one_result=false, $sql=null, $values=null)
	{
		$class		= get_called_class();
		$db		= static::_db();
		/* Input sanitizing */
		if (is_null($values))
			$values = array();

		assert('is_string($class)');
		assert('is_bool($just_one_result)');
		assert('is_null($sql) || is_string($sql) || is_assoc_array($sql)');
		assert('is_array($values)');
		assert('$db instanceof AnewtDatabaseConnectionMySQL');

		/* Get basic database settings */
		$table 			= static::_db_table();
		$primary_key 		= static::_db_primary_key();

		/* Basic clauses */
		if (is_array($sql))
			$joins = array_get_default($sql, 'join', null);
		else
			$joins = null;

		$select_clause = static::__db_select_clause( null, $joins);

		$from_clause = static::__db_from_clause( null, $joins);

		$order_clause = static::__db_order_clause( null);

		/* Find all records when no sql was specified*/
		if (is_null($sql))
		{
			$pq = $db->prepare("SELECT\n  ?raw?\nFROM\n  ?raw?\nORDER BY ?raw?");
			$rs = $pq->execute($select_clause, $from_clause, $order_clause);

		/* Plain SQL */
		} elseif (is_string($sql))
		{
			$constraints_tpl = new AnewtDatabaseSQLTemplate($sql, $db);
			$constraints_sql = $constraints_tpl->fill($values);

			$pq = $db->prepare("SELECT\n  ?raw?\nFROM\n  ?raw?\n?raw?");
			$rs = $pq->execute($select_clause, $from_clause, $constraints_sql);

		/* Associative array with constraints.
		 * These may contain both literal values and placeholder strings. It's
		 * just a fancy way of calling this method with a string parameters
		 * (useful when using user input). Note that all constraints are treated
		 * as plain strings. The parameters to be filled in for placeholders in
		 * these strings should be specified in the $values array. */
		} else {
			$constraints = array();

			/* Extra columns */
			$extra_columns = array_get_default($sql, 'extra-columns', '');
			if ($extra_columns) {
				$select_clause .= ",\n  $extra_columns";
			}

			/* Where. Nothing fancy, just use it. */
			$where = array_get_default($sql, 'where', null);
			if (!is_null($where))
				$constraints[] = sprintf('WHERE %s', $where);

			/* Order by. Both 'order-by' and 'order' keys are recognized. */
			$order_by = array_get_default($sql, 'order-by', null);
			if (is_null($order_by))
				$order_by = array_get_default($sql, 'order', null);

			if (is_null($order_by))
				$constraints[] = sprintf("\nORDER BY %s", $order_clause);
			else
				$constraints[] = sprintf("\nORDER BY %s", $order_by);

			/* Limit. "Optimizing" this depending on the value of
			 * $just_one_result is impossible since it may contain a placeholder
			 * string and not a literal value. We take care of $just_one_result
			 * when fetching the result rows. */
			$limit = array_get_default($sql, 'limit', null);
			if (!is_null($limit))
				$constraints[] = sprintf("\nLIMIT %s", $limit);

			/* Offset. How many rows to skip? */
			$offset = array_get_default($sql, 'offset', null);
			if (!is_null($offset))
				$constraints[] = sprintf("\nOFFSET %s", $offset);

			$constraints_tpl = new AnewtDatabaseSQLTemplate(join(' ', $constraints), $db);
			$constraints_sql = $constraints_tpl->fill($values);

			$pq = $db->prepare("SELECT\n  ?raw?\nFROM\n  ?raw?\n?raw?");
			$rs = $pq->execute($select_clause, $from_clause, $constraints_sql);
		}

		$rows = $rs->fetch_all();
		$instances = AutoRecord::__db_objects_from_arrays($class, $rows);
		# Search all multi-join definitions
		$multi_joins = array();
		if ($joins)
		{
			foreach ($joins as $join)
			{
				if (array_get_default($join, 'multi', false))
					$multi_joins[] = $join;
			}
		}
		$multi_joins = array_merge($multi_joins, static::_db_join_many());

		$pkey = $primary_key;
		foreach($multi_joins as $join)
		{
			$foreign_class = $join['foreign_class'];
			$fpkeys		= call_user_func( array($foreign_class , "_db_primary_keys"));
			if( $fpkeys ) {
				trigger_error( "cannot join with an autorecord class which has multiple primary keys" );
			}
			if (array_has_key($join, 'child_name')) {
				$name = $join['child_name'];
			} else {
				$name = strtolower($foreign_class) . '_list';
			}
			if (array_has_key($join, 'columns')) {
				$columns = $join['columns'];
			} else {
				$columns = array_keys(call_user_func(array($foreign_class, '_db_columns')));
			}
			$fpkey = call_user_func(array($foreign_class, '_db_primary_key'));
			$instances = AutoRecord::db_split_objects($name, $foreign_class, $columns, $fpkey, $pkey, $instances, !array_get_default($join, 'multi', false) , array_get_default( $join , "foreign_alias" , $name));
		}
		if ($just_one_result)
		{
			if ($instances)
				return array_shift($instances);
			else
				return null;
		} else
		{
			return $instances;
		}
	}


	/**
	 * Find one or more records by one column value. Don't use this method
	 * directly, use find_by_column or find_one_by_column on the class itself.
	 *
	 * \param $class
	 *   The class name
	 *
	 * \param $just_one_result
	 *   Whether to return just one instance (or null) or an array of instances
	 *   (possibly empty)
	 *
	 * \param $column
	 *   The column to match
	 *
	 * \param $value
	 *   The value to match
	 *
	 * \param $db
	 *   Reference to the database object instance.
	 */
	protected static function __db_find_by_column( $just_one_result, $column, $value)
	{
		$class		= get_called_class();
		$db		= static::_db();
		/* Input sanitizing */
		assert('is_string($class)');
		assert('is_bool($just_one_result)');
		assert('is_string($column)');
		assert('$db instanceof AnewtDatabaseConnectionMySQL');

		$table 		= static::_db_table();

		/* Find out the column type */
		$columns 	= static::_db_columns();
		if (!array_has_key($columns, $column))
			trigger_error(sprintf("Column %s not found in column list of %s", $column, $class));

		/* The array form of __db_find_by_sql is used, so that the default sort
		 * column is used. If a plain sql string is provided, no sorting will
		 * be done at all. */
		if (is_null($value))
		{
			$where_clause = '?table?.?column? IS NULL';
			$placeholder_values = array($table, $column);
		} else
		{
			$where_clause = sprintf('?table?.?column? = ?%s?', self::isComplex($columns) ? $columns[$column]['type'] : $columns[$column] );
			$placeholder_values = array($table, $column, $value);
		}

		$result = static::__db_find_by_sql(
				$just_one_result,
				array('where' => $where_clause),
				$placeholder_values);
		return $result;
	}


	/**
	 * Convert a list of instances to a hash, based on a unique key of the
	 * passed AutoRecord instances.
	 *
	 * \param $objects
	 *   A list of AutoRecord instances
	 *
	 * \param $column
	 *   The name of the (unique) column to use as the associative array.
	 *
	 * \return
	 *   An associative array with the (unique) column value as the key and the
	 *   object itself as the value
	 *
	 * \see AutoRecord::convert_to_primary_key_hash
	 */
	public static function convert_to_key_hash($objects, $column)
	{
		assert('is_numeric_array($objects)');
#		assert('is_string($column)');

		/* Handle empty lists */
		if (count($objects) == 0)
			return array();

		/* Now iterate over objects and put into hash */
		$r = array();
		foreach (array_keys($objects) as $object_key)
		{
			assert('$objects[$object_key] instanceof AutoRecord;');
			if( !is_array($column) ) {
				$r[$objects[$object_key]->_get($column)] = $objects[$object_key];
			} else {
				$ky				= "";
				foreach( $column as $c ) {
					$ky			.= $objects[$object_key] -> _get($c);
				}
				$ky				= md5( $ky );
				$r[$ky]			= $objects[$object_key];
			}
		}
		return $r;
	}

	/**
	 * Convert a list of instances to a hash, based on the primary key of the
	 * passed AutoRecord instances.
	 *
	 * \param $objects
	 *   A list of AutoRecord instances
	 *
	 * \return
	 *   An associative array with the primary key as the key and the object
	 *   itself as the value
	 *
	 * \see AutoRecord::convert_to_key_hash
	 */
	public static function convert_to_primary_key_hash($objects)
	{
		assert('is_numeric_array($objects)');

		/* Handle empty lists */
		if (count($objects) == 0)
			return array();

		/* Find out the primary key column by looking in the first object */
		if( $objects[0]->_db_primary_keys()) {
			$primary_key_column = $objects[0]->_db_primary_keys();
		} else {
			$primary_key_column = $objects[0]->_db_primary_key();
		}

		$r = AutoRecord::convert_to_key_hash($objects, $primary_key_column);
		return $r;
	}

	/** \} */


	/** \{
	 * \name Instance methods
	 */

	/**
	 * Inserts the data as a new record in the database. Don't use this method
	 * directly, use save() instead.
	 *
	 * \param $skip_primary_key
	 *	Whether to skip the primary key in the column list.
	 *
	 * \see AutoRecord::save
	 */
	protected function __db_insert($skip_primary_key=true)
	{
		$table 		= $this->_db_table();
		$columns 	= $this->_db_columns();
		$db 		= $this->_db();
		$primary_keys	= $this->_db_primary_keys();
		$primary_key 	= $this->_db_primary_key();

		if( $skip_primary_key !== static::_db_skip_primary_key() ) {
			$skip_primary_key	= static::_db_skip_primary_key();
		}
		
		$this->before_insert();

		/* Loop over the columns and build an INSERT query, which contains two
		 * lists: one list of column names and one list of values to be inserted */
		$number_of_columns = 0;
		$names = array();
		$values = array();
		foreach ($columns as $name => $opts)
		{
			if( static::isComplex()){
				$type		= $opts['type'];
			} else {
				$type		= $opts;
			}
			assert('is_string($name)');
			assert('is_string($type)');

			/* Skip the primary key */
			if (!$primary_keys && $name === $primary_key && $skip_primary_key)
				continue;
			/* Skip columns which are not set, they will be set by the database defaults */
			if (!$this->is_set($name))
				continue;

			$number_of_columns++;
			$names[] = $name;
			$value_types[] = sprintf('?%s:%s?', $type, $name); /* placeholder for real values */
			$values[$name] = $this->getdefault($name,  isset($opts['default']) ? $opts['default'] : null );
		}
		if( $number_of_columns == 0 ) {
			throw new Exception( "Cannot insert, 0 columns to fill" );
		}
		/* Create SQL for the list of column names */
		$columns_tpl = new AnewtDatabaseSQLTemplate(
				join(', ', array_fill(0, $number_of_columns, '?column?')),
			   	$db);
		$columns_sql = $columns_tpl->fill($names);

		/* Create SQL for the list of column values */
		$values_tpl = new AnewtDatabaseSQLTemplate(
				join(', ', $value_types),
				$db);
		$values_sql = $values_tpl->fill($values);


		/* Prepare and execute the query */
		$query = 'INSERT INTO ?table? (?raw?) VALUES (?raw?)';
		$pq = $db->prepare($query);
		$rs = $pq->execute($table, $columns_sql, $values_sql);

		if (!$primary_keys && $skip_primary_key && ($columns[$primary_key]['type'] == "integer" || $columns[$primary_key]['type'] == "int"))
		{
			/* Find out the new primary key value */
			switch ($db->type)
			{
				/* MySQL has a custom SQL function */
				case 'mysql':
					$row = $db->prepare_execute_fetch(
							'SELECT LAST_INSERT_ID() AS id');
					$this->_set($primary_key, $row['id']);
					break;

				/* SQLite has a special function */
				case 'sqlite':
					$this->_set($primary_key, sqlite_last_insert_rowid($db->backend->handle));
					break;

				/* PostgreSQL uses sequences */
				case 'postgresql':
					$primary_key_sequence = $this->_db_primary_key_sequence();
					if (is_null($primary_key_sequence))
					{
						/* Try to use PostgreSQL defaults */
						$primary_key_sequence = sprintf(
								'%s_%s_seq',
								$table,
								$primary_key);
					}
					assert('is_string($primary_key_sequence)');
					$row = $db->prepare_execute_fetch(
							'SELECT currval(?string?) AS id',
							$primary_key_sequence);
					$this->_set($primary_key, $row['id']);
					break;

				/* Fallback for unsupported backends */
				default:
					$row = $db->prepare_execute_fetch(
							'SELECT MAX(?column?) AS id FROM ?table?',
							$primary_key, $table);
					$this->_set($primary_key, $row['id']);
					break;
			}
			// after receiving the AI value, we accept this item as "from DB" so it will not be saved but updated next time on method ::save.
			$this -> __fromdb	= true;
		}

		$this->after_insert();
	}

	/**
	 * Updates an existing record in the database with the current instance
	 * data. Don't use this method directly, use save() instead.
	 *
	 * \see AutoRecord::save
	 */
	protected function __db_update()
	{
		$table = $this->_db_table();
		$columns = $this->_db_columns();
		$db = $this->_db();
		$skip_on_update = $this->_db_skip_on_update();
		$skip_on_save = $this->_db_skip_on_save();
		$skip_on_update = array_merge($skip_on_update, $skip_on_save);
		$primary_keys	= $this -> _db_primary_keys();
		$primary_key = $this->_db_primary_key();
		$primary_key_value = $this->get($primary_key);

		$this->before_update();

		/* Loop over the columns and build an UPDATE query */
		$placeholders = array();
		$values = array();
		foreach ($columns as $name => $opts)
		{	
			if( static::isComplex() ){
				$type		= $opts['type'];
			} else {
				$type		= $opts;
			} 
			assert('is_string($name)');
			assert('is_string($type)');

			/* Skip the primary key */
			if (($primary_keys && in_array($name,$primary_keys)) || (!$primary_keys && $name === $primary_key))
				continue;

			/* Skip read-only values */
			if (in_array($name, $skip_on_update))
				continue;

			$placeholders[] = sprintf('%s = ?%s:%s?', $db->escape_column_name($name), $type, $name);
			$values[$name] = $this->getdefault($name, isset($opts['default']) ? $opts['default'] : null);
		}

		if(count($placeholders))
		{
			/* Create SQL for the list of column names */
			$update_tpl = new AnewtDatabaseSQLTemplate(join(', ', $placeholders), $db);
			$update_sql = $update_tpl->fill($values);
	
			/* Prepare and execute the query */

			list( $whr , $params )		= $this -> prepareWhere();
			$params["table"]		= $table;
			$params["raw"]			= $update_sql;
			$pdopm = $db->prepare(sprintf('UPDATE ?table:table? SET ?raw:raw? WHERE %s',$whr));
			$rs = $pdopm->executev( $params );
		}

		$this->after_update();
	}
	/**
	 * Prepare a where based on multiple primary keys
	 */
	private function prepareWhere() {
		$whr				= array();
		$params				= array();
		$columns 			= $this->_db_columns();
		if( $this -> _db_primary_keys() ) {
			$whr			= array();
			foreach( $this -> _db_primary_keys() as $pk ) {
				$whr[]								= sprintf( "%s = ?string:%s_value?" , $pk ,$pk );
				$params[sprintf("%s_value",$pk)]				= $this -> get($pk);
			}
			$whr									= implode( " AND " , $whr );
		} else {
			$whr									= sprintf( "%s = ?%s:%s_value?" , $this -> _db_primary_key() 
														, static::isComplex() ? $columns[$this -> _db_primary_key()]['type'] : $columns[$this -> _db_primary_key()]
														, $this -> _db_primary_key() );
			$params[sprintf("%s_value",$this -> _db_primary_key())]						= $this -> get($this -> _db_primary_key());
		}
		return array( $whr , $params );
	}
	/**
	*	Tries to see whether the _db_columns is a Complex type
	*/
	final public static function isComplex($columns=false) {

		if( !$columns ) {
			$columns			= static::_db_columns();
		}
		
		assert('is_array($columns)');
		if(is_assoc_array( $columns ) && is_array( array_shift($columns)) && array_has_key( array_shift($columns),"type")) {
			return true;
		}
		return false;
	}
	/**
	 * Save this record in the database. If the record was previously unsaved
	 * (no primary key value was set), an INSERT query is performed. Otherwise,
	 * an UPDATE on the existing row is done.
	 *
	 * \param new
	 *   Whether or not we should insert a new row. Leave empty to check on the
	 *   primary key.
	 */
	public function save($new=NULL)
	{
		$this->before_save();
		if (is_null($new))
		{
			/* Default behaviour */
			$primary_keys	= $this -> _db_primary_keys();
			$primary_key 	= $this->_db_primary_key();
			if ($this->__fromdb) {
				$this->__db_update();
			} else {
				$this->__db_insert();
			}
		} else
		{
			/* Forced new/existing record */
			if ($new)
				$this->__db_insert(false);
			else
				$this->__db_update();
		}

		$this->after_save();
	}

	/**
	 * Delete this record from the database. If the record was previously
	 * unsaved, this is a no-op. Note that this method overrides
	 * Container::delete() (and Container::invalidate()). If you provide
	 * a parameter, the call is handed off to Container::delete() instead of
	 * deleting the record from the database.
	 *
	 * \param $name
	 *   Do not specify this! It's for code compatibility with Container only.
	 *
	 * \fixme See bug 173044
	 */
	public function delete($name=null)
	{
		/* If there are any parameters, we propagate the call to Container,
		 * instead of deleting the record from the database. This is evil and
		 * should be solved in a clean way. See bug 173044. */
		if (!is_null($name))
			return Container::delete($name);

		$this->before_delete();
		$primary_keys	= $this -> _db_primary_keys();
		$primary_key 	= $this -> _db_primary_key();
		
		list( $whr , $params ) = $this -> prepareWhere();

		if( $primary_keys ) {
			foreach( $primary_keys as $pk ) {
				if( !$this -> is_set( $pk ) ) {
					return false;
				}
			}
		} elseif( $primary_key && $this -> is_set($primary_key) ) {
			
		} else {
			return false;
		}

		$db = $this->_db();
		$table = $this->_db_table();
		$params["table"]	= $table;
		$db->prepare_executev(
			sprintf('DELETE FROM ?table:table? WHERE %s',$whr),
			$params
		);

		$this->after_delete();
	}

	/**
	 * Toggle a boolean value in this record. If the value was previously unset
	 * (SQL NULL), the value is initialized to true.
	 *
	 * \param $column
	 *   The name of the column to toggle.
	 */
	public function toggle($column)
	{
		assert('is_string($column)');
		$columns = $this->_db_columns();
		assert('array_has_key($columns, $column)');
		assert('AnewtDatabaseSQLTemplate::column_type_from_string($columns[$column]) == ANEWT_DATABASE_TYPE_BOOLEAN;');

		$current_value = $this->getdefault($column, null);
		if (is_null($current_value))
			$new_value = true;

		else
		{

			/* Handle strings and integers, because some databases don't support
			 * boolean column types (MySQL) and others don't support column
			 * types at all (SQLite). */
			if (is_int($current_value))
				$current_value = (bool) $current_value;
			elseif (is_string($current_value))
				$current_value = ($current_value !== '0');

			assert('is_bool($current_value)');
			$new_value = !$current_value;
		}
		$this->set($column, $new_value);
	}

	/** \} */


	/**
	 * \{
	 * \name Callback methods
	 *
	 * Callback methods that can be overridden to add specific functionality.
	 *
	 * Autorecord instances call some special callback methods when certain
	 * actions are about to take place (e.g. AutoRecord::before_save()) or have
	 * just taken place (e.g. AutoRecord::after_delete). These methods do
	 * nothing by default, but you can override one or more of them in your own
	 * classes if you want to do specific things, e.g. to fix up values before
	 * they enter the database (but you should really consider using
	 * Container::get() and Container::set() for this purpose) or to break
	 * foreign key references.
	 */

	/** Callback before saving */
	public function before_save() {}

	/** Callback after saving */
	public function after_save() {}

	/** Callback before inserting */
	public function before_insert() {}

	/** Callback after inserting */
	public function after_insert() {}

	/** Callback before updating */
	public function before_update() {}

	/** Callback after updating */
	public function after_update() {}

	/** Callback before deletion */
	public function before_delete() {}

	/** Callback after deletion */
	public function after_delete() {}

	/** \} */




	/**
	 * Returns an array of additional static methods to be included in the new class
	 * on registering.
	 *
	 * The format of the array elements is $method_name => $method_code, where
	 * $method_name is the name of the method, en $method_code is a string containing
	 * the code of the method, including the "function" keyword.
	 *
	 * Every instance of @@CLASS@@ in $method_code will be substituted for the class
	 * name of the registring function.
	 */
	protected static function _autorecord_extra_methods() { return array(); }

	/** \} */
	
	final public static function _table_install() {
		$class		= get_called_class();
		// check for complex type; non-Complex autorecords cannot be installed
		if( !static::isComplex($class::_db_columns()) ) {
			throw new Exception( $class . " is not a complex AutoRecord" );
			return false;
		}
		// check whether table is already existing in the database
		if( static::_table_installed() ) {
			throw new Exception( $class . " table is already installed" );
			return false;
		}
		// read and create table cq columns
		$columns	= static::_db_columns();
		if( !$columns || !count($columns)) {
			throw new Exception( $class . " has no method _db_columns" );
			return false;
		}
		$columnsql	= "";
		foreach( $columns as $name => $column ) {
			if(!isset($ai) && isset($column["ai"])) {
				$ai	= (int) $column["ai"];
			}
			$columnsql .= sprintf("
			`%s`			%s%s%s%s%s,"
				, $name
				, ( isset($column["ctype"]) 					? $column["ctype"] 					: $column["type"] )
				, ( isset($column["ai"]) 					? " AUTO_INCREMENT" 					: false )
				, ( isset($column["default"]) && !isset($column["ai"])		? sprintf(" DEFAULT '%s'",$column["default"])		: false )
				, ( isset($column["null"]) && !isset($column["ai"])		? " NULL "						: " NOT NULL " )
				, ( isset($column["comment"])					? sprintf(" COMMENT '%s'",$column["comment"])		: false )
			);
		}
		if( static::_db_primary_keys() ) {
			$key	= implode( "," , static::_db_primary_keys() );
		} elseif( $class::_db_primary_key() ) {
			$key	= static::_db_primary_key();
		} else { 
			$key	= false;
		}
		$sql		= sprintf("
		CREATE TABLE IF NOT EXISTS `%s` (
			%s
			%s
		) ENGINE=%s DEFAULT CHARSET=utf8%s;\r\n"
			, static::_db_table()
			, $columnsql
			, ($key ? sprintf( "PRIMARY KEY (%s)" , $key) : false )
			, static::_db_table_engine()
			, (isset($ai) ? sprintf( " AUTO_INCREMENT=%d",$ai) : false )
		);
		
		$db		= static::_db();
		$pq		= $db -> prepare( $sql );
		$rs		= $pq -> execute();
		
		return true;
	}
	final public static function _table_installed() {
		$class		= get_called_class();
		$db		= static::_db();

		$pq		= $db -> prepare( "SHOW TABLES LIKE ?string:table?" );
		$rs		= $pq -> executev( array( "table" => static::_db_table()) );
		
		return (bool) $rs -> count();
	}
	protected static function _db_select_clause($table_alias=null, $joins=null) {
		return static::__db_select_clause( $table_alias, $joins);
	}

	/* From clause with all joins */
	function _db_from_clause($table_alias=null, $joins=null) {
		return static::__db_from_clause( $table_alias, $joins );
	}

	/* Create instances from arrays (e.g. database records) */
	protected static function _db_object_from_array($arr) {
		return static::__db_object_from_array($arr);
	}
	protected static function _db_objects_from_arrays($arrs) {
		return static::__db_objects_from_arrays($arrs);
	}

	/* Find all */
	public static function find_all() {
		return static::find_by_sql();
	}

	/* Find by id */
	public static function find_by_id($values) {
		$args = func_get_args();
		$num_args = func_num_args();
		/* Accept both multiple parameters and a single array */
		if (($num_args == 1) && is_array($args[0])) {
			$args = $args[0];
		}
		return static::__db_find_by_id(false, $args);
	}

	public static function find_one_by_id($value) {
		assert('is_int($value)');
		/* Check for just one single parameter. This helps finding
			* bugs where find_by_id() was meant to be used */
		$num_args = func_num_args();
		assert('$num_args === 1');
		return static::__db_find_by_id(true, array($value));
	}

	/* Find by SQL */
	public static function find_by_sql($sql=null, $values=null) {
		$args = func_get_args();
		$sql = array_shift($args);
		if (count($args) == 1 && is_array($args[0])) $args = $args[0];
		return static::__db_find_by_sql(false, $sql, $args);
	}
	public static function find_one_by_sql($sql=null, $values=null) {
		$args = func_get_args();
		$sql = array_shift($args);
		if (count($args) == 1 && is_array($args[0])) $args = $args[0];
		return static::__db_find_by_sql(true, $sql, $args);
	}

	/* Find by column */
	public static function find_by_column($column, $value) {
		return static::__db_find_by_column(false, $column, $value);
	}
	public static function find_one_by_column($column, $value) {
		return static::__db_find_by_column(true, $column, $value);
	}
	protected static function _db_skip_primary_key() {
		return true;
	}
	final public function __sleep() {
		$cols = array_keys(static::_db_columns());
		if( get_called_class() == "SystemUser" ) { 
			_debug( array_keys($this -> __data) );
		}
		if( $this->__fromdb || $this -> _db_primary_keys() ) {
			return array_keys($this -> __data);
		}
		
		$ret	= array();
		foreach( $cols as $col ) {
			if( $col != $this -> _db_primary_key() ) {
				$ret[]	= $col;
			}
		}
		return $ret;
	}

}
