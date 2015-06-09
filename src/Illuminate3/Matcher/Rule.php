<?php

namespace Illuminate3\Matcher;

use Str;

class Rule
{
	const LOGIC_EQUALS 		= 'equals';
	const LOGIC_STARTS_WITH = 'startsWith';
	const LOGIC_ENDS_WITH 	= 'endsWith';
	const LOGIC_CONTAINS 	= 'contains';

	protected $classname;
	protected $method;
	protected $property;
	protected $logic = self::LOGIC_EQUALS;
	protected $value;
	protected $data = array();
	protected $tags = array();

	/**
	 * @param $class
	 * @return $this
	 */
	public function classname($class)
	{
		$this->classname = $class;
		return $this;
	}

	public function method($method)
	{
		$this->method = $method;
		return $this;
	}

	public function property($property)
	{
		$this->property = $property;
		return $this;
	}

	public function tags(Array $tags)
	{
		$this->tags = $tags;
		return $this;
	}

	public function tag($tag)
	{
		$this->tags[] = $tag;
		return $this;
	}

	public function startsWith($value)
	{
		$this->logic = self::LOGIC_STARTS_WITH;
		$this->value = $value;
		return $this;
	}

	public function endsWith($value)
	{
		$this->logic = self::LOGIC_ENDS_WITH;
		$this->value = $value;
		return $this;
	}

	public function equals($value)
	{
		$this->logic = self::LOGIC_EQUALS;
		$this->value = $value;
		return $this;
	}

	public function contains($value)
	{
		$this->logic = self::LOGIC_CONTAINS;
		$this->value = $value;
		return $this;
	}

	public function provide(Array $data)
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * @return array
	 */
	public function getTags()
	{
		return $this->tags;
	}

	/**
	 * @param mixed $object
	 * @param string $tag
	 * @throws Exception
	 * @return bool
	 */
	public function matches($object, $tag = null)
	{
		if(!is_object($object)) {
			throw new Exception(sprintf('$object must be an object, %s given', gettype($object)));
		}

		if(!$this->value) {
			throw new Exception('There is no value to match against in this rule');
		}

		// Is there a tag provided? And are there tags registered
		// in this rule? Then check if the tag matches the registered
		// tags. Otherwise return an empty array.
		if($tag && $this->getTags() && !in_array($tag, $this->getTags())) {
			return false;
		}

		// Is there a classname present? If the classname is not the same
		// as the object, then return false.
		if($this->classname && !$object instanceof $this->classname) {
			return false;
		}

		if(is_array($this->value)) {
			foreach($this->value as $value) {
				if($this->matchesValue($object, $value)) {
					return true;
				}
			}

			return false;
		}

		return $this->matchesValue($object, $this->value);
	}

	/**
	 * @param $object
	 * @param $value
	 * @return bool
	 */
	protected function matchesValue($object, $value)
	{
		if($this->method) {
			return $this->matchesMethod($object, $this->method, $value);
		}

		if($this->property) {
			return $this->matchesProperty($object, $this->property, $value);
		}

		return false;
	}

	/**
	 * @param $object
	 * @param $method
	 * @param $value
	 * @return bool
	 */
	protected function matchesMethod($object, $method, $value)
	{
		if(!method_exists($object, $this->method)) {
			return false;
		}

		return $this->matchLogic($this->method, $value);
	}

	/**
	 * @param $object
	 * @param $property
	 * @param $value
	 * @return bool
	 */
	protected function matchesProperty($object, $property, $value)
	{
		if(!isset($object->{$this->property})) {
			return false;
		}

		$target = $object->{$this->property};
		return $this->matchLogic($target, $value);
	}

	/**
	 * @param $target
	 * @param $value
	 * @return bool
	 */
	protected function matchLogic($target, $value)
	{
		switch($this->logic) {

			case self::LOGIC_EQUALS:
				return $target == $value;

			case self::LOGIC_STARTS_WITH:
				return Str::startsWith($target, $value);

			case self::LOGIC_ENDS_WITH:
				return Str::endsWith($target, $value);

			case self::LOGIC_CONTAINS:
				return Str::contains($target, $value);

			default:
				return false;
		}

	}
}