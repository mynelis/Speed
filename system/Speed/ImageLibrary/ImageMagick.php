<?php

namespace Speed\ImageLibrary;

class ImageMagick
{
	private static $IMAGE_BASEDIR = 'assets/images/';

	private static function CreateImage ($path)
	{
		$image = new \Imagick();
		$image->clear();
		$image->readImage(realpath($path));

		$image->width = $image->getImageWidth();
		$image->height = $image->getImageHeight();

		return $image;
	}

	private static function EncodeImageVars ($vars)
	{
		return base64_encode(http_build_query($vars));
	}

	private static function DecodeImageVars ($encoded)
	{
		parse_str(base64_decode($encoded), $vars);
		return (object) $vars;
	}

	private static function CropToFit (\Imagick $image, $w, $h)
	{
		if (($image->width / $w) < ($image->height / $h)) {
			$ratio = $image->width / $w;
		    $image->cropImage($image->width, floor($h * $ratio), 0, (($image->height - ($h * $ratio)) / 2));
		}
		else {
			$ratio = $image->height / $h;
		    $image->cropImage(ceil($w * $ratio), $image->height, (($image->width - ($w * $ratio)) / 2), 0);
		}
	}

	private static function RenderImage (\Imagick $image)
	{
		$mime = str_replace('x-', '', $image->getImageMimeType());
		header('Content-Type: '.$mime);
		exit($image->getImageBlob());
	}

	public static function CreateResizeSRC ($path, $w = 0, $h = 0, $crop = true)
	{
		if (!preg_match('/\//', $path)) {
			$path = self::$IMAGE_BASEDIR.$path;
		}
		$vars = (object) array('s' => $path, 'w' => $w, 'h' => $h, 'c' => $crop);
		return $path.'?r=' . self::EncodeImageVars($vars);
	}

	public static function RenderResized ()
	{
		$get = (object) $_GET;

		/*print_r($get);
		exit;*/

		if (isset($get->s) and isset($get->w) and isset($get->h)) {
			$w = (int)$get->w;
			$h = (int)$get->h;
			$s = htmlentities($get->s);

			if ($s and $w and $h) {
				$image = self::CreateImage($s);
				self::CropToFit($image, $w, $h);
				$image->thumbnailImage($w, $h, true);
				self::RenderImage($image);
			}
		}
		elseif (isset($get->v)) {
			$vars = self::DecodeImageVars($get->v);
			if (isset($vars->s)) {
				$image = self::CreateImage($vars->s);
				if ($vars->c) {
					self::CropToFit($image, $vars->w, $vars->h);
				}
				$image->thumbnailImage($vars->w, $vars->h, true);
				self::RenderImage($image);
			}
		}
	}
}
