(function ($) {

	'use strict';

	// Object helper functions
	String.prototype.toWord = function toWord (flag) {
		var value = this.replace(/\W+/g, '');
		value = ('lowercase' == flag) ? value.toLowerCase() : ('uppercase' == flag) ? value.toUpperCase() : value;
		return value;
	};
	String.prototype.cleanLink = function cleanLink () {
		return this.replace(/\W+/g, '-').toLowerCase();
	};
	//

	var snippet_functions = [];
	
	var add_snippet_function = function add_snippet_function () {
		for (var i in arguments) {
			snippet_functions.push(arguments[i]);
		}
	};

	var call_snippet_functions = function call_snippet_functions () {
		for (var i in snippet_functions) {
			//console.log(i, snippet_functions[i]);
			snippet_functions[i].call();
		}
	};

	// Actual snippet functions
	var word = function word () {
		$('.word').on('keyup blur', function (e) {
			var flag = $(this).attr('case') || null;
			var value = $(this).val().toWord(flag);
			$(this).val(value);
		})
		.trigger('keyup');
	};
	var cleanlink = function cleanlink () {
		$('.cleanlink').on('keyup blur', function () {
			var value = $(this).val();
			if ("#" != value) {
				$(this).val(value.cleanLink());
			}
		})
		.trigger('keyup');
	};
	//

	// Make it possible to add a snippet function or run all
	// snippet functions from anywhere the window object is
	// accessible.
	$.extend(window, {
		call_snippet_functions: call_snippet_functions,
		add_snippet_function: add_snippet_function
	});


	// Add the word input handler
	add_snippet_function(word, cleanlink);

})(jQuery);