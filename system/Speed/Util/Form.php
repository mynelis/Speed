<?php

namespace Speed\Util;

use \Speed\Templater\Layout;
use \Speed\Security\TokenFactory;

class Form
{
	private $identity;
	private $token_id;
	private $token;
	private $token_valid = false;
	private $form_data = false;
	private $fill_data;
	private $method;
	private $submitted = false;

	public $refill; 

	// No filling after form submission
	const REFILL_NONE = 'NONE';

	// Refill with prefill data after submission
	const REFILL_FROM_PREFILL = 'PREFILL';

	// Refill with submitted data after submission
	const REFILL_FROM_FORM = 'FORM';

	public function __construct ($identity, $refill = self::REFILL_NONE, $method = 'post', $token_id = null)
	{
		if (null == $token_id) $token_id = $identity.'_token';

		$this->identity = $identity;
		$this->token_id = $token_id;
		$this->token = TokenFactory::GetToken($token_id);
		$this->refill = $refill;
		$this->method = $method;
		$this->form_data = function_exists($method) ? $method() : false;

		Layout::register($this->identity, [(object) [$this->token_id => $this->token]]); 

		$after_submit = 'if'.$this->method;
	    $after_submit ($this->token_id, function () {
			$this->token_valid = TokenFactory::ValidateToken($this->form_data->{$this->token_id}, $this->token_id);
			$this->submitted = true;
	    });
	}

	public function fill ($data = null)
	{
	    settype($data, 'object');

	    $this->fill_data = $data;
	    // $this->token = $this->get_token();
	    // $before_submit = 'ifn'.$this->method;

    	// $data = $this->get_data();
    	Layout::register($this->identity, [$this->get_data()]); 

	    /*$before_submit ($this->token_id, function () use ($data) {
	    	// $data = $this->get_data();
        	// Layout::register($this->identity, [$data]); 
	    });*/

	    /*$after_submit = 'if'.$this->method;
	    $after_submit ($this->token_id, function () use ($data) {
			$this->token_valid = TokenFactory::ValidateToken($this->form_data->{$this->token_id}, $this->token_id);

	    	// $data = $this->get_data();
        	// Layout::register($this->identity, [$data]); 
	    });*/

	    return $this;
	}

	// public function before_submit ($callback)
	// {
	// 	$callback($this);
	// 	return $this;
	// }

	public function after_submit ($callback)
	{
		if (true === $this->submitted) {
			$callback($this);
    		Layout::register($this->identity, [$this->get_data()]); 
	    }

		return $this;
	}

	/*public function get_token ()
	{
		// if ($this->token) return $this->token;
		return TokenFactory::GetToken($this->token_id);
	}*/

	/*public function token_valid ()
	{
		return $this->token_valid;
	}*/

	/*private function check_token ()
	{
		// $form = $this->form_data;
		$this->token_valid = TokenFactory::ValidateToken($this->form_data->{$this->token_id}, $this->token_id);
	}*/

	/*private function submitted_values ()
	{
		$method = $this->method;
		return $method();
	}*/

	/*private function refill_from_none ()
	{
		return self::REFILL_NONE === $this->refill;
	}*/

	/*private function refill_from_prefill ()
	{
		return self::REFILL_FROM_PREFILL === $this->refill;
	}*/

	/*private function refill_from_form ()
	{
		return self::REFILL_FROM_FORM === $this->refill;
	}*/

	private function get_data ()
	{
		$data = [
			'method' => $this->method,
			$this->token_id => $this->token
		];

		$form = $this->form_data;
		$form_empty = empty((array)$form);
		
		if (self::REFILL_NONE === $this->refill) {
			if ($form_empty) $data = array_merge($data, (array) $this->fill_data);
		}

		if (self::REFILL_FROM_PREFILL === $this->refill) {
			$data = array_merge($data, (array) $this->fill_data);
		}

		if (self::REFILL_FROM_FORM === $this->refill) {
			
			if ($form_empty) {
				$data = array_merge($data, (array) $this->fill_data);
			}
			else {
				unset($data[$this->token_id]);
				$data = array_merge($data, (array) $form);
			}
		}

		return (object) $data;
	}

	public function collect ($key = null, $callback = null)
	{
		if (true !== $this->submitted) return false;

		Layout::register($this->identity, [$this->get_data()]); 

		// $this->check_token();

		// if (!$this->token_valid) return false;

		if (is_callable($key)) {
			$callback = $key;
			$key = null;
		}

		unset($this->form_data->{$this->token_id});

		if ($this->form_data && is_callable($callback)) {
			$form_data = $this->token_valid ? $this->form_data : false;
			return $callback($form_data, $this->fill_data);
		}

		if (!$this->token_valid) return false;
		return $this->form_data;
	}
}

