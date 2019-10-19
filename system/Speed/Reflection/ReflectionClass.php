<?php

namespace Speed\Reflection;

class ReflectionClass extends \ReflectionClass
{
	public static function HasPublicProperty ($class, $property)
	{
		$reflection = new ReflectionClass($class);
		return property_exists($class, $property) and $reflection->getProperty($property)->isPublic();
	}
}