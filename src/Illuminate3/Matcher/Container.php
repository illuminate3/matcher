<?php

namespace Illuminate3\Matcher;

use Illuminate\Container\Container as IocContainer;

class Container
{
	protected $container;
	protected $defaults = array();
	protected $tags = array();
	protected $rules = array();
	protected $unique = array();
	protected $presets = array();


	/**
	 * We need the Container for instantiating the right objects.
	 * By using a IoC container, you can easily swap out classes
	 * other than the default.
	 *
	 * @param IocContainer $container
	 */
	public function __construct(IocContainer $container)
	{
		$this->container = $container;
	}

	/**
	 * Add multiple default values
	 *
	 * When an object matches a rule, we return an array with data.
	 * You can add default values to that array, so that every
	 * match will have this data.
	 *
	 * @param array $defaults
	 * @return $this
	 */
	public function defaults(Array $defaults)
	{
		$this->defaults = $defaults;
		return $this;
	}

	/**
	 * Add a single value to the defaults
	 *
	 * When an object matches a rule, we return an array with data.
	 * You can add default values to that array, so that every
	 * match will have this data.
	 *
	 * @param $key
	 * @param $value
	 * @return $this
	 */
	public function setDefault($key, $value)
	{
		$this->defaults[$key] = $value;
		return $this;
	}

	/**
	 * Get the default values
	 *
	 * When an object matches a rule, we return an array with data.
	 * You can add default values to that array, so that every
	 * match will have this data.
	 *
	 * @return array
	 */
	public function getDefaults()
	{
		return $this->defaults;
	}

	/**
	 * Add tags for every rule
	 *
	 * With tags you can have control over which rules apply.
	 * When we call the match method and provide a tag as a
	 * second argument, then the rules will only triggered
	 * if the tag matches the registered tags.
	 *
	 * @param array $tags
	 * @return $this
	 */
	public function tags(Array $tags)
	{
		$this->tags = $tags;
		return $this;
	}

	/**
	 * Set the fields that are unique and the provided data
	 * will be merged and unique by these fields.
	 *
	 * @param array $unique
	 * @return $this
	 */
	public function unique(Array $unique)
	{
		$this->unique = $unique;
		return $this;
	}

	/**
	 * Add multiple rules to the container.
	 *
	 * Rules are the logic where we match the object against.
	 * If one rule matches, then the rule returns a array of
	 * data.
	 *
	 * @param array $rules
	 * @throws Exception
	 * @return $this
	 */
	public function rules(Array $rules)
	{
		foreach($rules as $options) {

			if(!is_array($options)) {
				throw new Exception('Options must be an array');
			}

			$rule = $this->createRule();
			$this->addOptions($rule, $options);

		}

		return $this;
	}

	/**
	 * Helper method to set multiple options in a rule.
	 *
	 * @param Rule  $rule
	 * @param array $options
	 */
	protected function addOptions(Rule $rule, Array $options)
	{
		foreach($options as $method => $value) {

			if(method_exists($rule, $method)) {
				$rule->$method($value);
			}
		}
	}

	/**
	 * Fill the container with data from a config file.
	 *
	 * If we provide a config file with the right structure, we can
	 * set all container data at once.
	 *
	 * @param array $config
	 * @throws Exception
	 * @return Container
	 */
	public function fromArray(Array $config)
	{
		if(!isset($config['match'])) {
			throw new Exception('Must provide a "match" option with an array of rules');
		}

		$this->rules($config['match']);

		if(isset($config['defaults'])) {
			$this->defaults($config['defaults']);
		}

		if(isset($config['unique'])) {
			$this->unique($config['unique']);
		}

		if(isset($config['tags'])) {
			$this->tags($config['tags']);
		}

		return $this;
	}

	/**
	 * Helper method to create a Rule object
	 *
	 * @return Rule
	 */
	protected function createRule()
	{
		$rule = $this->container->make('Illuminate3\Matcher\Rule');
		$this->rules[] = $rule;

		return $rule;
	}

	/**
	 * Starting point for creating a new rule that checks the object property.
	 *
	 * This container provides a fluent interface. You can build human readable
	 * code like this:
	 *
	 * $matcher->whenProperty('title')->contains('Hi')->provide(array('foo' => 'bar'));
	 *
	 * @param string $property
	 * @return Rule
	 */
	public function whenProperty($property)
	{
		return $this->createRule()->property($property);
	}

	/**
	 * Starting point for creating a new rule that checks the object method.
	 *
	 * This container provides a fluent interface. You can build human readable
	 * code like this:
	 *
	 * $matcher->whenMethod('title')->contains('Hi')->provide(array('foo' => 'bar'));
	 *
	 * @param string $method
	 * @return Rule
	 */
	public function whenMethod($method)
	{
		return $this->createRule()->method($method);
	}

	/**
	 * Starting point for creating a new rule that checks the object class name.
	 *
	 * This will only check if the object is an instance of this class name.
	 *
	 * This container provides a fluent interface. You can build human readable
	 * code like this:
	 *
	 * $matcher->whenClass('title')->provide(array('foo' => 'bar'));
	 *
	 * @param string $class
	 * @return Rule
	 */
	public function whenClass($class)
	{
		return $this->createRule()->className($class);
	}

	/**
	 * Match an object against the registered rules and get an array
	 * with data returned.
	 *
	 * You can provide a $tag variable as a second argument. This allows you to
	 * only match objects that are also tagged the same. Handy if you want an
	 * ACL kind of functionality.
	 *
	 * @param object $object
	 * @param string $tag
	 * @throws Exception
	 * @return array
	 */
	public function match($object, $tag = null)
	{
		if(!is_object($object)) {
			throw new Exception(sprintf('$object must be an object, %s given', gettype($object)));
		}

		// Is there a tag provided? And are there tags registered
		// in this container? Then check if the tag matches the
		// registered tags. Otherwise return an empty array.
		if($tag && $this->tags && !in_array($tag, $this->tags)) {
			return array();
		}

		$this->presets = array();

		foreach($this->rules as $rule) {

			if($rule->matches($object, $tag)) {

				$defaults = $this->processDefaults($object);
				$data = array_merge($rule->getData(), $defaults);

				// Do we have to merge the data based on unique fields?
				if($this->unique) {

					$key = $this->createKey($data);

					if(isset($this->presets[$key])) {
						$data = array_merge($this->presets[$key], $data);
					}

					$this->presets[$key] = $data;
				}
				else {
					$this->presets[] = $data;
				}

			}
		}

		return $this->presets;
	}

	/**
	 * Helper method to create a unique key used in the presets array
	 *
	 * @param array $data
	 * @return string
	 */
	protected function createKey(Array $data)
	{
		$values = array_intersect_key($data, array_flip($this->unique));
		return implode('-', $values);
	}

	/**
	 * Helper method to check if the defaults has closures.
	 *
	 * @param $object
	 * @return array
	 */
	protected function processDefaults($object)
	{
		$defaults = array();
		foreach($this->defaults as $key => $value) {

			if($value instanceof \Closure) {
				$defaults[$key] = call_user_func_array($value, array($object));
			}
			else {
				$defaults[$key] = $value;
			}
		}
		return $defaults;
	}
}