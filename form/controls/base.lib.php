<?php

/*
 * Anewt, Almost No Effort Web Toolkit, form module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */


/**
 * Base form control class.
 *
 * All form controls must extend this class.
 *
 * Do not instantiate this class directly, use one of its descendants instead.
 */
abstract class AnewtFormControl extends Container
{
	/**
	 * Reference to containing AnewtForm instance.
	 *
	 * \see _set_form
	 */
	protected $_form;

	/**
	 * List of validators.
	 *
	 * \see add_validator
	 */
	protected $_validators;

	/**
	 * List of validator error messages.
	 *
	 * \see add_validator
	 */
	protected $_validator_errors;

	/**
	 * List of filters.
	 *
	 * \see add_filter
	 */
	protected $_filters;

	/**
	 * List of filter error messages.
	 *
	 * \see add_filter
	 */
	protected $_filter_errors;

	/**
	 * Create a new form control instance.
	 *
	 * If a subclass overrides the AnewtFormControl constructor, the parent
	 * constructor must be invoked as well.
	 *
	 * \param $name
	 *   The name of this control. It must be unique across this form.
	 */
	function __construct($name)
	{
		assert('is_string($name); // form control name must be a string');
		/* Default to bare minimum of properties */
		$this->_seed(array(

			/* Identifiers */
			'name'            => $name, 
			'id'              => null,

			/* Labels and descriptions */
			'label'           => null,
			'secondary-label' => null,
			'description'     => null,
			'help'            => null,

			/* The actual value */
			'value'           => null,

			/* Validation */
			'error'           => null,

			/* Some additional modes */
			'readonly'        => false,
			'required'        => true,
			'disabled'        => false,
			'can-be-filled'   => true,

			/* Composite widgets */
			'composite'       => false,
			'composite-for'   => false,
			
			/* Styling */
			'class'           => null,
			'extra_attributes' => null,
		));

		$this->_validators = array();
		$this->_validator_errors = array();

		$this->_filters = array();
		$this->_filter_errors = array();
	}


	/** \{
	 * \name Getter and setter methods
	 */

	/**
	 * Fill the form control from the given values.
	 *
	 * This only sets the value for controls for which the \c can-be-filled
	 * property is enabled.
	 *
	 * The return value of this function indicates whether the control was
	 * successfully filled with a value. For some control types such as
	 * checkboxes this always succeeds since both presence and absence of the
	 * value from \c $values are meaningful. For other controls such as text
	 * inputs this is only true if the value was actually present in the \c
	 * $values parameters.
	 *
	 * \param $values
	 *   An associative array from which the form control should be filled.
	 *
	 * \return
	 *   \c true if filling was succesfull, \c false otherwise.
	 *
	 * \see AnewtForm::fill()
	 */
	function fill($values)
	{
		if (!$this->_get('can-be-filled'))
			return true;

		/* If this control is disabled it cannot be filled either. The value can
		 * only be set directly. */
		if ($this->_get('disabled'))
			return true;

		$name = $this->get('name');
		if (array_key_exists($name, $values))
		{
			$this->set('value', $values[$name]);
			return true;
		}

		return false;
	}

	/**
	 * Return the form id. This method falls back to the name value if no id was
	 * explicitly provided.
	 */
	function get_id() {
		$id = $this->_get('id');

		if (is_null($id))
		{
			$name_parts = $this->get('name-parts');
			$id = join('-', $name_parts);
		}

		return $id;
	}

	/**
	 * Returns a list of name parts. Mostly, this is an array with all
	 * the prefixes from the form, followed by the name of this contro.
	 */
	function get_name_parts() {
		if (isset($this->_form)) {
			$parts = $this->_form->get('prefix');
		} else {
			$parts = array();
		}
		$parts[] = $this->get('name');

		return $parts;
	}

	/**
	 * Returns the rendered name as html. This is the name with an optional prefix.
	 */
	function get_rendered_name() {
		$parts = $this->get('name-parts');

		$name = array_shift($parts);
		foreach ($parts as $part)
			$name .= sprintf('[%s]', $part);

		return $name;
	}

	/**
	 * \private
	 *
	 * Make the control reference its containing form.
	 *
	 * \param $form
	 *   A reference to an AnewtForm instance.
	 */
	function _set_form($form)
	{
		assert('$form instanceof AnewtForm;');
		$this->_form = $form;
	}

	/** \} */


	/** \{
	 * \name Validation methods
	 */

	/**
	 * Check whether this control is to be considered 'empty'.
	 *
	 * This method is invoked when validating a form. When a control is optional
	 * and empty, no validators are applied to it. The base implementation just
	 * checks whether the <code>value</code> property is an empty string, but
	 * subclasses may do more sensible checks.
	 *
	 * \return
	 *   True if empty, false otherwise.
	 */
	function is_empty()
	{
		$value = $this->get('value');
		return is_null($value) || ($value === '');
	}

	/**
	 * Check whether this form control is valid. The default is to apply all
	 * filters and validators added to this control. Subclasses could also
	 * override this method to do something more sensible than applying
	 * validators, since some controls don't accept validators, e.g.
	 * checkboxes.
	 *
	 * \return
	 *   True if valid, false otherwise.
	 *
	 * \see add_validator
	 * \see add_filter
	 */
	function is_valid()
	{
		/* Empty optional controls are always valid */
		if (!$this->get('required') && $this->is_empty())
		{
			return true;
		}

		$value = $this->get('value');
		foreach (array_keys($this->_filters) as $idx)
		{
			$newvalue = $this->_filters[$idx]->filter($value);
			$this->set('value', $newvalue);
			if (isset($this->_filter_errors[$idx]) && $newvalue != $value)
			{
				$this->_set('error', $this->_filter_errors[$idx]);
				return false;
			}
			$value = $newvalue;
		}

		foreach (array_keys($this->_validators) as $idx)
		{
			if (!$this->_validators[$idx]->is_valid($value))
			{
				$this->_set('error', $this->_validator_errors[$idx]);
				return false;
			}
		}

		return true;
	}


	/**
	 * Add a validator instance to this form control.
	 *
	 * The (optional) error message will be shown when the validator fails to
	 * validate the form control.
	 *
	 * \param $validator
	 *   A validator instance, e.g. AnewtValidatorLength or another
	 *   AnewtValidator subclass.
	 *
	 * \param $error_message
	 *   An optional error message to be shown when this validator fails. Leave
	 *   empty to not show an error.
	 */
	function add_validator($validator, $error_message=null)
	{
		assert('$validator instanceof AnewtValidator;');
		$this->_validators[] = $validator;
		$this->_validator_errors[] = $error_message;
	}

	/**
	 * Add a filter instance to this form control. Filtering will happen
	 * during validation.
	 *
	 * When an error message is supplied, the result of the filter will be
	 * checked for changes. If a change is found, then the error message
	 * will be shown and validation will fail.
	 *
	 * If no error message is supplied, the value will be silently
	 * converted.
	 *
	 * Filters will be run before validators.
	 *
	 * \param $filter
	 *   A filter instance, e.g. AnewtFilterUppercase or another
	 *   AnewtFilter subclass.
	 *
	 * \param $error_message
	 *   The error message to show when the filter changes the value.
	 */
	function add_filter($filter, $error_message=null)
	{
		assert('$filter instanceof AnewtFilter');
		$this->_filters[] = $filter;
		$this->_filter_errors[] = $error_message;
	}
	/** \} */


	/** \{
	 * \name Rendering methods
	 */

	/**
	 * Render the minimal HTML needed for this control to work.
	 *
	 * Form control implementations should implement this method, e.g. for
	 * a checkbox a simple <code>input</code> HTML element should be returned.
	 *
	 * \return
	 *   XHTML element with the minimal HTML markup needed for this control to
	 *   function properly.
	 */
	abstract function build_widget();

	/** \} */
}

?>
