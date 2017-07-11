<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Forms;

use Nette;


/**
 * Container for form controls.
 *
 * @property   Nette\Utils\ArrayHash $values
 * @property-read \Iterator $controls
 * @property-read Form|NULL $form
 */
class Container extends Nette\ComponentModel\Container implements \ArrayAccess
{
	/** @var callable[]  function (Container $sender); Occurs when the form is validated */
	public $onValidate;

	/** @var ControlGroup|NULL */
	protected $currentGroup;

	/** @var bool */
	private $validated;


	/********************* data exchange ****************d*g**/


	/**
	 * Fill-in with default values.
	 * @param  iterable
	 * @param  bool
	 * @return static
	 */
	public function setDefaults($values, $erase = FALSE)
	{
		$form = $this->getForm(FALSE);
		if (!$form || !$form->isAnchored() || !$form->isSubmitted()) {
			$this->setValues($values, $erase);
		}
		return $this;
	}


	/**
	 * Fill-in with values.
	 * @param  iterable
	 * @param  bool
	 * @return static
	 * @internal
	 */
	public function setValues($values, $erase = FALSE)
	{
		if ($values instanceof \Traversable) {
			$values = iterator_to_array($values);

		} elseif (!is_array($values)) {
			throw new Nette\InvalidArgumentException(sprintf('First parameter must be an array, %s given.', gettype($values)));
		}

		foreach ($this->getComponents() as $name => $control) {
			if ($control instanceof IControl) {
				if (array_key_exists($name, $values)) {
					$control->setValue($values[$name]);

				} elseif ($erase) {
					$control->setValue(NULL);
				}

			} elseif ($control instanceof self) {
				if (array_key_exists($name, $values)) {
					$control->setValues($values[$name], $erase);

				} elseif ($erase) {
					$control->setValues([], $erase);
				}
			}
		}
		return $this;
	}


	/**
	 * Returns the values submitted by the form.
	 * @param  bool
	 * @return Nette\Utils\ArrayHash|array
	 */
	public function getValues($asArray = FALSE)
	{
		$values = $asArray ? [] : new Nette\Utils\ArrayHash;
		foreach ($this->getComponents() as $name => $control) {
			if ($control instanceof IControl && !$control->isOmitted()) {
				$values[$name] = $control->getValue();

			} elseif ($control instanceof self) {
				$values[$name] = $control->getValues($asArray);
			}
		}
		return $values;
	}


	/********************* validation ****************d*g**/


	/**
	 * Is form valid?
	 * @return bool
	 */
	public function isValid()
	{
		if (!$this->validated) {
			if ($this->getErrors()) {
				return FALSE;
			}
			$this->validate();
		}
		return !$this->getErrors();
	}


	/**
	 * Performs the server side validation.
	 * @param  IControl[]
	 * @return void
	 */
	public function validate(array $controls = NULL)
	{
		foreach ($controls === NULL ? $this->getComponents() : $controls as $control) {
			if ($control instanceof IControl || $control instanceof self) {
				$control->validate();
			}
		}
		if ($this->onValidate !== NULL) {
			if (!is_array($this->onValidate) && !$this->onValidate instanceof \Traversable) {
				throw new Nette\UnexpectedValueException('Property Form::$onValidate must be array or Traversable, ' . gettype($this->onValidate) . ' given.');
			}
			foreach ($this->onValidate as $handler) {
				$params = Nette\Utils\Callback::toReflection($handler)->getParameters();
				$values = isset($params[1]) ? $this->getValues($params[1]->isArray()) : NULL;
				Nette\Utils\Callback::invoke($handler, $this, $values);
			}
		}
		$this->validated = TRUE;
	}


	/**
	 * Returns all validation errors.
	 * @return array
	 */
	public function getErrors()
	{
		$errors = [];
		foreach ($this->getControls() as $control) {
			$errors = array_merge($errors, $control->getErrors());
		}
		return array_unique($errors);
	}


	/********************* form building ****************d*g**/


	/**
	 * @return static
	 */
	public function setCurrentGroup(ControlGroup $group = NULL)
	{
		$this->currentGroup = $group;
		return $this;
	}


	/**
	 * Returns current group.
	 * @return ControlGroup|NULL
	 */
	public function getCurrentGroup()
	{
		return $this->currentGroup;
	}


	/**
	 * Adds the specified component to the IContainer.
	 * @param  Nette\ComponentModel\IComponent
	 * @param  string|int
	 * @param  string
	 * @return static
	 * @throws Nette\InvalidStateException
	 */
	public function addComponent(Nette\ComponentModel\IComponent $component, $name, $insertBefore = NULL)
	{
		parent::addComponent($component, $name, $insertBefore);
		if ($this->currentGroup !== NULL) {
			$this->currentGroup->add($component);
		}
		return $this;
	}


	/**
	 * Iterates over all form controls.
	 * @return \Iterator
	 */
	public function getControls()
	{
		return $this->getComponents(TRUE, IControl::class);
	}


	/**
	 * Returns form.
	 * @param  bool
	 * @return Form|NULL
	 */
	public function getForm($throw = TRUE)
	{
		return $this->lookup(Form::class, $throw);
	}


	/********************* control factories ****************d*g**/


	/**
	 * Adds single-line text input control to the form.
	 * @param  string
	 * @param  string|object
	 * @param  int
	 * @param  int
	 * @return Controls\TextInput
	 */
	public function addText($name, $label = NULL, $cols = NULL, $maxLength = NULL)
	{
		return $this[$name] = (new Controls\TextInput($label, $maxLength))
			->setHtmlAttribute('size', $cols);
	}


	/**
	 * Adds single-line text input control used for sensitive input such as passwords.
	 * @param  string
	 * @param  string|object
	 * @param  int
	 * @param  int
	 * @return Controls\TextInput
	 */
	public function addPassword($name, $label = NULL, $cols = NULL, $maxLength = NULL)
	{
		return $this[$name] = (new Controls\TextInput($label, $maxLength))
			->setHtmlAttribute('size', $cols)
			->setHtmlType('password');
	}


	/**
	 * Adds multi-line text input control to the form.
	 * @param  string
	 * @param  string|object
	 * @param  int
	 * @param  int
	 * @return Controls\TextArea
	 */
	public function addTextArea($name, $label = NULL, $cols = NULL, $rows = NULL)
	{
		return $this[$name] = (new Controls\TextArea($label))
			->setHtmlAttribute('cols', $cols)->setHtmlAttribute('rows', $rows);
	}


	/**
	 * Adds input for email.
	 * @param  string
	 * @param  string|object
	 * @return Controls\TextInput
	 */
	public function addEmail($name, $label = NULL)
	{
		return $this[$name] = (new Controls\TextInput($label))
			->setRequired(FALSE)
			->addRule(Form::EMAIL);
	}


	/**
	 * Adds input for integer.
	 * @param  string
	 * @param  string|object
	 * @return Controls\TextInput
	 */
	public function addInteger($name, $label = NULL)
	{
		return $this[$name] = (new Controls\TextInput($label))
			->setNullable()
			->setRequired(FALSE)
			->addRule(Form::INTEGER);
	}


	/**
	 * Adds control that allows the user to upload files.
	 * @param  string
	 * @param  string|object
	 * @param  bool
	 * @return Controls\UploadControl
	 */
	public function addUpload($name, $label = NULL, $multiple = FALSE)
	{
		return $this[$name] = new Controls\UploadControl($label, $multiple);
	}


	/**
	 * Adds control that allows the user to upload multiple files.
	 * @param  string
	 * @param  string|object
	 * @return Controls\UploadControl
	 */
	public function addMultiUpload($name, $label = NULL)
	{
		return $this[$name] = new Controls\UploadControl($label, TRUE);
	}


	/**
	 * Adds hidden form control used to store a non-displayed value.
	 * @param  string
	 * @param  string
	 * @return Controls\HiddenField
	 */
	public function addHidden($name, $default = NULL)
	{
		return $this[$name] = (new Controls\HiddenField)
			->setDefaultValue($default);
	}


	/**
	 * Adds check box control to the form.
	 * @param  string
	 * @param  string|object
	 * @return Controls\Checkbox
	 */
	public function addCheckbox($name, $caption = NULL)
	{
		return $this[$name] = new Controls\Checkbox($caption);
	}


	/**
	 * Adds set of radio button controls to the form.
	 * @param  string
	 * @param  string|object
	 * @return Controls\RadioList
	 */
	public function addRadioList($name, $label = NULL, array $items = NULL)
	{
		return $this[$name] = new Controls\RadioList($label, $items);
	}


	/**
	 * Adds set of checkbox controls to the form.
	 * @param  string
	 * @param  string|object
	 * @return Controls\CheckboxList
	 */
	public function addCheckboxList($name, $label = NULL, array $items = NULL)
	{
		return $this[$name] = new Controls\CheckboxList($label, $items);
	}


	/**
	 * Adds select box control that allows single item selection.
	 * @param  string
	 * @param  string|object
	 * @param  array
	 * @param  int
	 * @return Controls\SelectBox
	 */
	public function addSelect($name, $label = NULL, array $items = NULL, $size = NULL)
	{
		return $this[$name] = (new Controls\SelectBox($label, $items))
			->setHtmlAttribute('size', $size > 1 ? (int) $size : NULL);
	}


	/**
	 * Adds select box control that allows multiple item selection.
	 * @param  string
	 * @param  string|object
	 * @param  array
	 * @param  int
	 * @return Controls\MultiSelectBox
	 */
	public function addMultiSelect($name, $label = NULL, array $items = NULL, $size = NULL)
	{
		return $this[$name] = (new Controls\MultiSelectBox($label, $items))
			->setHtmlAttribute('size', $size > 1 ? (int) $size : NULL);
	}


	/**
	 * Adds button used to submit form.
	 * @param  string
	 * @param  string|object
	 * @return Controls\SubmitButton
	 */
	public function addSubmit($name, $caption = NULL)
	{
		return $this[$name] = new Controls\SubmitButton($caption);
	}


	/**
	 * Adds push buttons with no default behavior.
	 * @param  string
	 * @param  string|object
	 * @return Controls\Button
	 */
	public function addButton($name, $caption = NULL)
	{
		return $this[$name] = new Controls\Button($caption);
	}


	/**
	 * Adds graphical button used to submit form.
	 * @param  string
	 * @param  string  URI of the image
	 * @param  string  alternate text for the image
	 * @return Controls\ImageButton
	 */
	public function addImage($name, $src = NULL, $alt = NULL)
	{
		return $this[$name] = new Controls\ImageButton($src, $alt);
	}


	/**
	 * Adds naming container to the form.
	 * @param  string|int
	 * @return self
	 */
	public function addContainer($name)
	{
		$control = new self;
		$control->currentGroup = $this->currentGroup;
		if ($this->currentGroup !== NULL) {
			$this->currentGroup->add($control);
		}
		return $this[$name] = $control;
	}


	/********************* extension methods ****************d*g**/


	public function __call($name, $args)
	{
		if ($callback = Nette\Utils\ObjectMixin::getExtensionMethod(__CLASS__, $name)) {
			return Nette\Utils\Callback::invoke($callback, $this, ...$args);
		}
		return parent::__call($name, $args);
	}


	public static function extensionMethod($name, $callback = NULL)
	{
		if (strpos($name, '::') !== FALSE) { // back compatibility
			list(, $name) = explode('::', $name);
		}
		Nette\Utils\ObjectMixin::setExtensionMethod(__CLASS__, $name, $callback);
	}


	/********************* interface \ArrayAccess ****************d*g**/


	/**
	 * Adds the component to the container.
	 * @param  string|int
	 * @param  Nette\ComponentModel\IComponent
	 * @return void
	 */
	public function offsetSet($name, $component)
	{
		$this->addComponent($component, $name);
	}


	/**
	 * Returns component specified by name. Throws exception if component doesn't exist.
	 * @param  string|int
	 * @return Nette\ComponentModel\IComponent
	 * @throws Nette\InvalidArgumentException
	 */
	public function offsetGet($name)
	{
		return $this->getComponent($name, TRUE);
	}


	/**
	 * Does component specified by name exists?
	 * @param  string|int
	 * @return bool
	 */
	public function offsetExists($name)
	{
		return $this->getComponent($name, FALSE) !== NULL;
	}


	/**
	 * Removes component from the container.
	 * @param  string|int
	 * @return void
	 */
	public function offsetUnset($name)
	{
		$component = $this->getComponent($name, FALSE);
		if ($component !== NULL) {
			$this->removeComponent($component);
		}
	}


	/**
	 * Prevents cloning.
	 */
	public function __clone()
	{
		throw new Nette\NotImplementedException('Form cloning is not supported yet.');
	}
}
