<?php

/*
 * Anewt, Almost No Effort Web Toolkit, form module
 *
 * This code is copyrighted and distributed under the terms of the GNU LGPL.
 * See the README file for more information.
 */


/**
 * Controls containing a subform.
 *
 * This control can render a subform. The #build_widget() will render the entire
 * form without the <form> tags. Its controls will render with prefixed names
 * such that name clashes between controls of multiple subforms are avoided.
 * Of course, the name of this control must be unique in the main form.
 *
 * To do normal processing within this form, it is necessary that #process()
 * be called in the \c process() of the main form.
 */
class AnewtFormControlSubform extends AnewtFormControl
{
	protected $_subform;		/**< The subform */

	/**
	 * Create a new subform control
	 *
	 * \param $name
	 *   The name of this control.
	 * \param $form
	 *   The subform
	 * \param $renderer
	 *   A renderer for the form.
	 */
	function __construct($name, $subform, $renderer = null)
	{
		parent::__construct($name);
		$this->seed(array(
			'renderer'   => $renderer,
			'class'      => null,
		));
		assert('$subform instanceof AnewtForm');
		$this->_subform = $subform;
	}

	function set_prefix()
	{
		$prefix = $this->get('name-parts');
		$this->_subform->set('prefix', $prefix);
	}

	function build_widget()
	{
		$renderer = $this->get('renderer');

		$this->set_prefix();

		if (is_null($renderer)) {
			$renderer = new AnewtFormRendererDefault();
			$renderer->set_form($this->_subform);
			$this->set('renderer', $renderer);
		}

		$attr = array(
			'class' => 'form-subform',
			'id' => $this->get('id'),
		);

		$div = new AnewtXHTMLDiv(null, $attr);

		$class = $this->_get('class');
		if (!is_null($class))
			$div->add_class($class);

		$div->append_child($renderer->render_hidden_controls());
		$div->append_child($renderer->render_base());

		return $div;
	}

	function process() {
		return $this->_subform->process();
	}

	function is_valid()
	{
		return $this->_subform->is_valid();
	}

	function get_value() {
		return $this->_subform->get_control_values();
	}

	function set_value($value) {
		$this->_subform->fill($value);
	}

	function get_subform() {
		return $this->_subform;
	}
}

?>
