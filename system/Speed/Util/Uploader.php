<?php

namespace Speed\Util;

//
// image uploader
// @author Nelis Elorm Duhadzi
//
// initializing the uploader
///////////////////////////////////////////
/*
	$uploader = new $merge->Plugins->Uploader();
	$uploader->source = 'image';
*/

// setting up params, uploading, resizing and cropping at once
/////////////////////////////////////////////////////////////////
// file saving params
/*
	$uploader->savedir = 'assets/images/';
	$uploader->filename = 'ad1';
*/
// resizing params
/*
	$uploader->width = 150;
	$uploader->height = 100;
*/
// specify auto adjust side
// eg: auto,width,height
/*
	$uploader->auto_adjust = 'auto';
*
// cropping extra
/*
	$uploader->crop = true;
*/
// upload and process image
/*
	$uploader->upload();
*/

// save copy as new image
// this example demonstrates the step by step approach.
// the file is uploaded, then resized
/////////////////////////////////////////////////////////////
// file saving params
/*
	$uploader->savedir = 'assets/images/temp/';
*/
// note the missing $uploader->filename. it will auto pick the name
// from the submitted form
/*
	$uploader->upload();
*/
// new resizing params
/*
	$uploader->width = 250;
	$uploader->height = 200;
*/
// specify auto adjust side
// eg: auto,width,height
/*
	$uploader->auto_adjust = 'auto';
*
// cropping extra
/*
	$uploader->crop = true;
*/
// resize and crop
/*
	$uploader->resize();
*/
class Uploader {
	// image upload name as in form
	public $source;

	// where the new file should be saved
	public $savedir;

	// destination path including new name.
	// default is the name of the original file.
	// if the file already exists, it will be overwritten
	public $filename;

	// new width of uploaded image
	// 0 means auto width
	public $width = 0;

	// new height of uploaded image
	// 0 means auto height
	public $height = 0;

	// resized width
	public $resized_width;

	// resized height
	public $resized_height;

	// auto adjust settings for resizing
	// eg: auto,width,height
	public $auto_adjust = '';

	// whether to force resize on smaller images
	public $force_resize = false;

	// extension of the image
	public $extension;

	// crop excedent after resize
	// this is used only when one of
	// auto_width or auto_height is present
	public $crop = false;

	// if image upload was successful or not
	public $uploaded = false;

	// store processed image in string for next use
	// possibly we will want to save a thumbnail of this
	// in another folder. that's why we need this property
	public $processed = '';

	// image function and extension maps
	private $_map = array(
		'.jpg' => array('imagecreatefromjpeg','imagejpeg'),
		'.jpeg' => array('imagecreatefromjpeg','imagejpeg'),
		/*'.bmp' => array('imagecreatefromwbmp ','imagewbmp'),
		'.xbm' => array('imagecreatefromxbm','imagexbm'),
		'.webp' => array('imagecreatefromwebp ','imagewebp'),*/
		'.gif' => array('imagecreatefromgif','imagegif'),
		'.png' => array('imagecreatefrompng','imagepng')
	);

	// image compression values
	public $compression = 0;
	private $compression_values = array (
		'.jpg' => 100, // 0 = worse, 100 = best
		'.jpeg' => 100, // 0 = worse, 100 = best
		//'.gif' => 0, gif doesn't support compression
		'.png' => 9 // 0 = best, 9 = worse
	);

	public function __construct () {

	}

	// upload an image
	public function upload ($source_name='', $savedir='') {
		if ($source_name) $this->source = $source_name;
		if ($savedir) $this->savedir = $savedir;

		if ($this->source) {
			$this->uploaded = $this->_upload();
		}
	}

	// resize image
	public function resize ($file_src='', $multi_file=false) {
		if ($multi_file) {
			if (!is_file($file_src)) return;
			$this->filename = strrchr(stripslashes($file_src), '/');
		}

		if ($file_src) {
			$this->processed = $file_src;
			$this->extension = strrchr($file_src, '.');
		}

		if ((0 < $this->width) or (0 < $this->height) and isset($this->_map[$this->extension])) {
			if (!$this->width) $this->width = $this->height;
			if (!$this->height) $this->height = $this->width;

			$this->_initdir();
			return $this->_resize();
		}
		return false;
	}

	private function _initdir () {
		if ($this->savedir and !is_dir($this->savedir)) {
			mkdir($this->savedir);
		}
	}

	private function _upload () {
		if (!isset($_FILES[$this->source])) {
			trigger_error('file not in temp');
			return false;
		}
		if (0 == $_FILES[$this->source]['size']) {
			trigger_error('empty file (no size)');
			return false;
		}
		if (''!=$_FILES[$this->source]['error']) {
			trigger_error('temp image has error');
			return false;
		}

		$this->_initdir();

		$this->extension = strrchr($_FILES[$this->source]['name'], '.');
		if (is_uploaded_file($_FILES[$this->source]['tmp_name'])) {
			if (!$this->filename) {
				$_filename = $_FILES[$this->source]['name'];
				$_filename = preg_replace('/\s+/', '-', $_filename);
				$_filename = str_replace('&', '', $_filename);
				$_filename = str_replace('#', '', $_filename);
				$_filename = str_replace('@', 'at', $_filename);
				$this->filename = $_filename;
			}
			else {
				$this->filename .= $this->extension;
			}
			$this->filename = stripslashes($this->filename);

			if (move_uploaded_file($_FILES[$this->source]['tmp_name'], $this->savedir.$this->filename)) {
				$this->processed = $this->savedir.$this->filename;
				$this->uploaded = true;
				if ($this->width or $this->height and isset($this->_map[$this->extension])) {
					$this->resize();
				}
				return true;
			}
		}
		return false;
	}

	private function _resize () {
		list($width, $height) = getimagesize($this->processed);

		$this->resized_width = $this->width;
		$this->resized_height = $this->height;
		$cropx = 0;
		$cropy = 0;

		switch ($this->auto_adjust) {
			// auto adjust image dimensions, keeping aspect ratio
			case 'auto':
				// image is wider than tall
				// keep height and adjust width (with extra on width)
				if ($width > $height) {
					$this->resized_height = $this->height;
					$this->resized_width = ($this->height/$height)*$width;
					$cropx = $this->resized_width-$this->width;
				}
				// image is shorter than wide
				// keep width and sdjust height (with extra on height)
				else {
					$this->resized_width = $this->width;
					$this->resized_height = ($this->width/$width)*$height;
					$cropy = $this->resized_height-$this->height;
				}
				break;

			// adjust image width, keeping height as specified
			case 'width':
				$this->resized_width = ($this->height/$height)*$width;
				if ($this->resized_width > $this->width) {
					$cropx = $this->resized_width-$this->width;
				}
				break;

			// adjust image height, keeping width as specified
			case 'height':
				$this->resized_height = ($this->width/$width)*$height;
				if ($this->resized_height > $this->height) {
					$cropy = $this->resized_height-$this->height;
				}
				break;

			// default is none
			default:
				break;
		}

		// if both sides of the image we want to resize is smaller than the new
		// dimensions, abort resize
		if (!$this->force_resize) {
			if ($width < $this->resized_width and $height < $this->resized_height) {
				return false;
			}
		}

		// create image handles for manipulating the image
		$srcimage = call_user_func($this->_map[$this->extension][0], $this->processed);
		if ($this->crop and (0<$cropx or 0<$cropy)) {
			$dstimage = imagecreatetruecolor($this->width, $this->height);
		}
		else {
			$dstimage = imagecreatetruecolor($this->resized_width, $this->resized_height);
		}

		// Preserve transparency
		$transparent_index = imagecolortransparent($srcimage);
		if ($transparent_index >= 0) {	// GIF
			imagepalettecopy($srcimage, $dstimage);
			imagefill($dstimage, 0, 0, $transparent_index);
			imagecolortransparent($dstimage, $transparent_index);
			imagetruecolortopalette($dstimage, true, 256);
		}
		else {	// PNG
			imagealphablending($dstimage, false);
			imagesavealpha($dstimage, true);
			$transparent = imagecolorallocatealpha($dstimage, 255, 255, 255, 127);
			imagefill($dstimage, 0, 0, $transparent);
		}

		// resize and crop the image
		imagecopyresampled(
			$dstimage, $srcimage,
			0, 0, $cropx, $cropy,
			$this->resized_width, $this->resized_height, $width, $height
		);

		$this->_compress($dstimage, $this->compression);

		// destroy image handlers
		imagedestroy($dstimage);
		imagedestroy($srcimage);

		return true;
	}

	private function _compress ($dstimage, $cmp) {

		// save image for types that support compression
		if (isset ($this->compression_values[$this->extension])) {
			$cval = $this->compression_values[$this->extension];
			$cmp = 100-$cmp; // so that 0% compression gives 100% quality and 100% compression gives 0% quality
			$cmp = $cval*($cmp/100);

			// This will handle the quality/compression issue for png
			if ('.png' == strtolower($this->extension)) {
				$cmp = $cval-$cmp;
			}

			if (0 > $cmp) $cmp = 0;
			if (100 < $cmp) $cmp = 100;

			call_user_func(
				$this->_map[$this->extension][1],
				$dstimage,
				$this->savedir.$this->filename,
				$cmp
			);
		}
		// save image for types that do not support compression
		else {
			call_user_func(
				$this->_map[$this->extension][1],
				$dstimage,
				$this->savedir.$this->filename
			);
		}
	}

}

?>