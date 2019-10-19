;(function ($) {

	'use strict';

	const request_functions = {
		post: function post_request (endpoint, vars, success, format) {
			return $.post(endpoint, vars, function (response, request, xhr) {
				success.call(xhr, response, request);
			}, format || 'json');
		},
		get: function get_request (endpoint, vars, success, format) {
			return $.get(endpoint, vars, function (response, request, xhr) {
				success.call(xhr, response, request);
			}, format || 'json');
		}
	};

	var scripts = [
		'system/f7/vendor/bootstrap/js/bootstrap.min',
		'system/f7/vendor/bootstrap/js/bootstrap-select.min',
		'system/f7/vendor/tablesorter/js/tablesorter.min',
		'system/f7/vendor/nicedit/js/nicedit',
		'system/f7/js/jutils',
		'system/f7/js/snippets',
		'system/f7/js/cms'
	],
	loaded = 0,
	last,
	plugins = {};

	var init = function init () {
		$(document).trigger('f7loaded', this);
		$('input[type=expand]').expand();

		if (undefined != this.cms) {
			this.cms.MapAccessKey(118);

			var
				loc = $(location).attr('href'),
				re = /\?cms_login&t=.{32}$/;

			if (re.test(loc)) this.cms.GetForm('login');

			var utk = $.cookie && $.cookie.get('utk');
			if (utk) this.cms.Initialize(utk);
		}
	};

	var addScript = function addScript (script) {
		if ('string' == typeof script) script = [script];
		for (var i in script) scripts.push(script[i]);
		return f7;
	};

	var importSingleScript = function importSingleScript (script, callback) {
		$.getScript(script + '.js', function (body, status, response) {
			if (loaded == last) {
				if ('undefined' != typeof CMS) {
					f7.cms = CMS;
					init.call(f7);
					if (callback) callback.call(f7, scripts);
				}
			}
			loaded++;
		});
	};

	var loadScripts = function loadScripts (callback) {
		last = scripts.length - 1;
		for (var i in scripts) {
			importSingleScript(scripts[i], callback);
		}
		return f7;
	};

	var loadStyleSheets = function loadStyleSheets (file) {
		//console.log(file, file.is_file());

		file.is_file(true, function (f) {
		//if (file.is_file()) {
	    	var link = $('<link/>').attr({
	    		rel: 'stylesheet',
	    		href: file
	    	});
	        $('head').append(link);
	    //}
		}, function (f, c, s) {
			//console.log(f, this, c, s);
		});
	};

	var loadPlugin = function loadPlugin (name, css) {
		if ('string' === typeof name) {
			addScript('system/f7/ext/' + name + '/js/' + name);

			if (true === css) {
				loadStyleSheets('system/f7/ext/' + name + '/css/' + name + '.css');
			}
		}
	};

	var hasPlugin = function hasPlugin (name) {
		return (undefined !== plugins[name]);
	};

	var f7 = {
		load: loadScripts,
		loadStyle: loadStyleSheets,
		addScript: addScript,
		request: request_functions,
		use: loadPlugin,
		has: hasPlugin
	};

	window.f7 = f7;

})(jQuery);