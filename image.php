<?php

define('ROOT', '');
require 'lib/init.php';

// Auto-render resized image based on URL parameter presence
\Speed\ImageLibrary\ImageMagick::RenderResized();