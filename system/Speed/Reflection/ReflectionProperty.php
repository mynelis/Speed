<?php

namespace Speed\Reflection;

class ReflectionProperty extends \ReflectionProperty
{
	public function GetPublicProperties ($obj)
	{
		$props = (new ReflectionObject($obj))->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach ($props as $key => $value) {
			$props[$key] = $value->name;
		}
		return $props;
	}
}