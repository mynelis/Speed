<?php

namespace Speed\Security;

class Cryptor
{
	private $cipher = 'AES-128-CBC';
	private $hash = 'sha256';
	private $key = '';
	private $option = OPENSSL_RAW_DATA;

	public function __construct ($key = '', $cipher = '', $hash = '', $option = null)
	{
		if ($cipher) $this->cipher = $cipher;
		if ($option) $this->option = $option;
		if ($hash) $this->hash = $hash;
		if ($key) $this->key = $key;
	}

	public final function encrypt ($data)
	{
		$text = serialize($data);
		$ivlen = openssl_cipher_iv_length($this->cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);

		$ciphertext_raw = openssl_encrypt($text, $this->cipher, $this->key, $this->option, $iv);
		$hmac = hash_hmac($this->hash, $ciphertext_raw, $this->key, true);

		return base64_encode($iv.$hmac.$ciphertext_raw);
	}

	public final function decrypt ($encrypted)
	{
		$c = base64_decode($encrypted);
		$ivlen = openssl_cipher_iv_length($this->cipher);
		$iv = substr($c, 0, $ivlen);
		$hmac = substr($c, $ivlen, 32);

		$ciphertext_raw = substr($c, $ivlen + 32);
		$original_plaintext = openssl_decrypt($ciphertext_raw, $this->cipher, $this->key, $this->option, $iv);
		$calcmac = hash_hmac($this->hash, $ciphertext_raw, $this->key, true);
		
		if (hash_equals($hmac, $calcmac)) {
		    return unserialize($original_plaintext);
		}
	}
}