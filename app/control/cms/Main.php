<?php

namespace app\control\cms;

class Main extends \app\control\BaseControl
{
	public function index ()
	{
		\Speed\ContentManager::getInstance()->InitCall();
	}
}