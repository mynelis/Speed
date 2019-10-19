//////////////////////////////////////////////////////////////////////////
//	Ajax plugin for f7 Activate
//////////////////////////////////////////////////////////////////////////
//
// 	PROVIDERS
// 	-	Loads remote data into memory
// 	-	Broadcasts received data for receivers
// 	-	Data loaded is broadcast immediately if no event is specified
// 	-	DOM is notified after all providers have finished loading data
// 	-	Providers could also be parsed like receivers if the data-parse 
// 		attribute is set to true
// 		
// 		ATTRIBUTES
// 		-	data-source		URL to send request to
// 		-	data-event		Even that triggers remote operation
// 		-	data-cache		Whether to cache data or once or load fresh
// 							data anytime the associated event is triggered 
// 	
// 	RECEIVERS
// 	-	Receive data broadcast from providers
// 	-	Data used by received will be picked from its provider by
// 		by referencing its provider by CSS selector or by the data-provider 
// 		attribute
// 	-	Broadascts data to children with the data-node attribute
// 	
// 		ATTRIBUTES
// 		-	data-provider	Reference to data provider (CSS or source@node)
// 		-	data-event 		Event to trigger parsing of receiver
// 							(defaults to immediate) 
//
//////////////////////////////////////////////////////////////////////////

;(function ($) {

	'use strict';

	// =======================================================================================================
	//var $providers = {}, $receivers = [];
	var $dom = {providers: [], receivers: []};
	// =======================================================================================================

	// Parse a string with marker tokens mased or given regular expression.
	// A temporal timestamp marker is attached to the given object to keep
	// track of calls to the function.
	var parse_string = function parse_string (str, re, vars) {
		vars.__time = (new Date()).getTime();
		return str.replace(re, (o, m) => vars[m]);
	};

	// Handy function for reversing any given string.
	// I wish there was an original way of doing this, so we don't have to
	// create a temporal array, reverse it and join back.. not cool.
	var reverse_string = function reverse_string (str) {
		return str.split('').reverse().join('');
	};

	// Splitting strings always produces extra parts, thus behaves in an 
	// undesirable way. This handy method removes any empty strings from
	// the beginning and ending of the resultant array.
	var extract_parts = function extract_parts (str, re) {
		return str.split(re).filter(s => s);
	};

	// Convert a string to an integer.
	// We achieve this by simply passing the given string to the number constructor.
	/*String.prototype.toNumber = function string_to_number () {
		//return Number(this);
		return parseInt(this);
	};*/

	// Check if an array contains only one element and that entity is a 
	// scalar value. For this to pass, the array length must be 1 and it
	// must contain either a string or number
	var is_scalar = function array_is_scalar (arr) {
		return (1 == arr.length && (-1 < ['string', 'number'].indexOf(typeof arr[0])));
	};

	// Polyfill for object.length. 
	// Since there is currently no straight forward way of determining the
	// length of an object, we can have this handy method mimicking the array length.
	/*var object_length = function object_length (obj) {
		return Object.keys(obj).length;
	};*/

	// Extend jQuery to have the activate capability.
	// In this case we're not transforming elements based on their data-source
	// attribute, but actually picking the element by either a CSS selector or
	// an actual DOM object, then attaching the data-source to it and creating
	// a new provider out of it.
	$.extend($.fn, {
		activate: function (opts) {
			$(this).each(function () {
				if (!opts || (opts && undefined == opts.source)) return $(this);
				var provider = new DataProvider($(this).reset_data(opts).addClass('f7dpr'));
				$dom.providers.push(provider);
				return $(this);
			});
		}
	});

	// At a point we would want to clear all custom data attributes we have 
	// attached to a DOM element. This function is meant to take care of that
	// using given key value pairs of data.
	$.extend($.fn, {
		reset_data: function reset_data (data) {
			$.each(data, (k, v) => this.removeAttr('data-'+k).data(k, v));
			return this.data(data);
		}
	});

	// Handy function for filtering out a particular node from a given
	// dataset using a dot-delimited key string. Its a simple algorithm
	// of splitting the key into an array and iterating until the last
	// key in the array matches a node in the dataset.
	var value_of = function value_of (obj, key) {
		if (undefined == key) return null;

		var keys = key.split('.');
		$.each(keys, (i, k) => obj = (undefined != obj[k]) ? obj[k] : obj);

		/*for (var i in keys) {
			var key = keys[i];
			if (undefined != obj[key]) obj = obj[key];
		}*/

		return obj;
	};

	// Generate a unique ID and namespace for storing providers in a global
	// scope. This function depends on the presence of a global $provider 
	// object, for determining the ID value (since we're essentially counging
	// the number of providers created thus far).
	var get_uid = function get_uid (el) {
		var id = $dom.providers.length + 1, data = el.data();
		//console.log(id, el[0]);
		return {id: id, ns: reverse_string(data.source.replace(/[\/|\.]/g, '_') + id + 'prv')};
	};

	// Find a provider from the global $dom registry using its ns as key
	var get_provider = function get_provider (ns) {
		return $dom.providers.filter((p) => p.ns == ns);
	};

	// Get a list of all providers found in the DOM, identifiable with the
	// data-source attribute, creating a DataProvider obkect from each  
	// using the jQuery.fn.activate extension.
	var init_providers = function init_providers () {
		$('[data-source]').each((i, el) => $(el).activate($(el).data()));
	};

	// Initialize all receiver in the DOM. We do this by searching the entire
	// DOM for elements with a data-provider attribute referencing a data-source 
	// element either by a CSS selector or the provider's data-source value.
	var init_receivers = function init_receivers () {
		$('[data-provider]').each(function () {
			var $this = $(this), d = $this.data();
			
			$this.reset_data(d).addClass('f7drc');

			// When data-source is a valid CSS selector
			try {
				if ($(d.provider).length) {
					$this._provider = $(d.provider);
					$dom.receivers.push($this);
				}
			}
			// Invalid CSS selector assumed to be reference to a provider
			catch (e) {				
				if ($('[data-source="'+d.provider+'"]').length) {
					$this._provider = $('[data-source="'+d.provider+'"]');
					$dom.receivers.push($this);
				}
			}
		});
	};

	// Prepare the parameters for making a remote call.
	// We first need to check if any of the permitted methods and data formats
	// are present, by examining the endpoint. Then we try fetching data from
	// cache and proceeding to make a fresh request if cace is disabled or we
	// do not find a cache yet (first time call). 
	var init_request = function init_request (provider) {
		var ep = extract_parts(provider.data.source, /.*(get|post)\.(xml|json).*/g), method = ep[0], format = ep[1];
		var params = $.extend({}, Array.prototype.sort.apply(provider.data));
		if (!method || !format) return false;

		var cache = provider.getData();
		if (cache) return provider.dataReceived(cache);
		return make_request(provider, params, method, format);
	};

	// Make a remote call with given method and format, in a specific provider
	// context. The response from this call is set as cache, then the received
	// event is fired for the given provider.
	var make_request = function make_request (provider, vars, method, format) {
		return undefined != $[method] && $[method].call(provider.el, provider.data.source, vars, function (response) {
			provider.setData(response);
			provider.dataReceived(response);
		}, format);
	};

	// Upon receiving data from a remote call, the provider should notify all
	// receivers dependent of the data received. 
	var notify_receivers = function notify_receivers (provider, response) {
		$.each(provider.getReceivers(), (i, rcv) => rcv.dataReceived(response));
	};

	// Crawl the DOM and attach all found receivers to their respetive providers.
	// We may encounter exceptions while trying to find a provider using CSS 
	// selectors, since the same data-provider attribute could be an actual 
	// CSS selector or an invalid CSS selector, especially in cases where the
	// attribute value is the data-source value of the provider and not an 
	// actual or valid CSS selector.
	var add_receivers = function add_receivers (provider) {
		$dom.receivers.filter((rcv) => rcv._provider.is(provider.el) && provider.addReceiver(rcv));
	};

	// =======================================================================================================
	var DataProvider = function (el) {
		var data = el.data(), dataset = null, receivers = [], provider, uid = get_uid(el);
		provider = {
			id: uid.id, ns: uid.ns, el: el, data: data,
			get: () => get_provider(uid.ns),
			getConfig: () => data,
			getReceivers: () => receivers,
			setData: (d) => dataset = d,
			getData: () => false != data.cache && dataset,
			addReceiver: (rcv) => receivers.push(new DataReceiver($(rcv), provider)),
			dataReceived: (response) => notify_receivers(provider, response)
		};	

		add_receivers(provider);
		
		if (undefined == data.event) {
			init_request(provider);			
		}
		else {
			el.on(data.event, data, function (e) {
				e.preventDefault();
				init_request(provider);
			});	
		}

		return provider;
	};

	// =======================================================================================================
	var DataReceiver = function (el, provider) {
		var data = el.data(), dataset = null, receiver, id = provider.ns + provider.getReceivers().length;

		var parse_received = function parse_received () {
			if (undefined == data.event) {
				receiver.parse();
			}
			else {
				el.on(data.event, () => receiver.parse());
			}
		};

		el.reset_data(data).addClass('f7drc');
		var template = el.find('template').length ? el.find('template').html() : el.html();
		var shadow = el.clone(true);//[0].outerHTML;

		receiver = {
			id: id, el: shadow, data: data,
			getData: () => dataset,
			getProvider: () => el._provider,// get_provider(provider.ns),
			dataReceived: function (response) {
				var result = value_of(response, data.select);
				if ('array' !== $.type(result)) result = [result];
				dataset = result;
				parse_received();
				el.trigger('received.active_data', el);
			},
			parse: function () {
				var rows = dataset.reverse();

				if (is_scalar(rows)) {
					el.html(rows[0]);
				}
				else {
					for (var i = 0; i < rows.length; i++) this.parseRow(rows[i]);
					el.remove();
				}
			},
			parseRow: function (row) {
				var snippet = el.clone(true).insertAfter(el).trigger('mounted.active_data', row);
				snippet.html(parse_string(template, /@(\w+)/gim, row));
			}
		};

		el.on('received.active_data', function (e) {
			e.stopPropagation();
		});

		return receiver;
	};
	// =======================================================================================================

	init_receivers();
	init_providers();

})(jQuery);



// testing

// $('*').on('received.active_data', function (e, data) {
/*$('.auto_ajax_receiver')
.on('received.active_data', function (e, response, change, request) {
	//$(this).find('a[href]').text(response.status.message +'.. '+ response.data);
	//console.log(this, e, response);
	//console.log(response, change);
	//console.log('HTML version called');
});

$('.live_input')
.on('received.active_data', function (e, response, change, request) {
	//$(this).find('a[href]').text(response.status.message +'.. '+ response.data);
	//console.log(this, e, response, request, change);
	//console.log(response, change);
	//console.log('INPUT version called');
});*/

/*$('#manual_ajax').activate({
	source: 'user/list.post.json',
	event: 'click'
});*/

/*.on('received.active_data', function (e, response, change, request) {
	//$(this).text(response.status.message);
	//console.log(this, e, response);
	console.log(response, change);
	//console.log('JS version called');
});*/

// $('*').on('received.active_data', function (e, response) {
// 	$(this).text(response.status.message +'.. '+ response.data);
// });

/*$('.event-immediate').on('loaded.active_data', function (e, response) {
	//this.fade();
	//console.log(this);
});*/

/*$(document).on('immediates_loaded.active_data', function (e, providers) {
	//console.log(providers);
	//console.log('all immediates loaded');
	//$('body')[0].fade();
});*/