$.webshims.setOptions("extendNative", false); 
$.webshims.setOptions('forms', {
	customDatalist: true,
	addValidators: true,
	waitReady: false
});
$.webshims.setOptions('geolocation', {
	confirmText: '{location} wants to know your position. You will have to enter one manually if you decline.'
});
$.webshims.setOptions('forms-ext', {
		types: 'range date time number month color',
});
$.webshims.polyfill('es5 forms forms-ext geolocation json-storage');
$.webshims.activeLang('de');

$.webshims.ready('form-validators', function(){
	//$.webshims.addCustomValidityRule(name of constraint, test-function, default error message); 
	var groupTimer = {};
	
	$.webshims.addCustomValidityRule('choose2days', function(elem, val){
		var name = elem.name;
		if(!name || elem.type !== 'checkbox' || !$(elem).hasClass('choose2days')){return;}
		var checkboxes = $( (elem.form && elem.form[name]) || document.getElementsByName(name));
		var isValid = checkboxes.filter(':checked:enabled');
		if(groupTimer[name]){
			clearTimeout(groupTimer[name]);
		}
		groupTimer[name] = setTimeout(function(){
			checkboxes
				.addClass('group-required')
				.unbind('click.groupRequired')
				.bind('click.groupRequired', function(){
					checkboxes.filter('.group-required').each(function(){
						$.webshims.refreshCustomValidityRules(this);
					});
				})
			;
		}, 9);
		
		if(isValid.length !== 2)
		{
			return true;
		} else
		{
			// [1,2] F
			// [1,7] F
			// [3,2] F
			// [1,3] T
			// [1,6] T
			var chosen = isValid.map( function() {
				return +$(this).attr('value');
			}).get();
			
			
			var forbidden_wrong = $([chosen[0] - 2, chosen[0] - 1, chosen[0], chosen[0] + 1, chosen[0] + 2]);
			var forbidden = forbidden_wrong.map( function() {
				if(this < 1) return 7 + this;
				if(this > 7) return this - 7;
				return +this;
			});
			if($.inArray(chosen[1],forbidden)!==-1)
				return true;
			else
				return false;
		}
	}, 'Du musst zwei Wochentage auswählen, die mehr als zwei Tage auseinander liegen.');
	
	
	//changing default message
	$.webshims.customErrorMessages.choose2days[''] = 'Du musst zwei Wochentage auswählen, die mehr als zwei Tage auseinander liegen.';
	//adding new languages
	$.webshims.customErrorMessages.choose2days['de'] = 'Du musst zwei Wochentage auswählen, die mehr als zwei Tage auseinander liegen.';
});
function flatStringifyGeo(geo) {
	var result = {};
	result.timestamp = geo.timestamp;
	var coords = {};
	coords.accuracy = geo.coords.accuracy;
	coords.altitude = geo.coords.altitude;
	coords.altitudeAccuracy = geo.coords.altitudeAccuracy;
	coords.heading = geo.coords.heading;
	coords.latitude = geo.coords.latitude;
	coords.longitude = geo.coords.longitude;
	coords.speed = geo.coords.speed;
	result.coords = coords;
	return JSON.stringify(result);
}

$(document).ready(function() {
	$('.geolocator').click(function()
	{
		var real_loc = $(this).closest('.controls').find('input[type=hidden]');
		var enter_loc = $(this).closest('.controls').find('input[type=text]');

		enter_loc.attr('placeholder','You can also enter your location manually');
		enter_loc.prop('readonly',false);
		
		navigator.geolocation.getCurrentPosition(
			function(pos) {
				real_loc.val(flatStringifyGeo(pos) );
				enter_loc.val("lat:"+ pos.coords.latitude +"/long:" + pos.coords.longitude );
				enter_loc.prop('readonly',true); // fixme: for some reason, if there is user entered text, FF doesn't show new JS-set text
			},
			function(err)
			{
				// error handling - this isn't called in firefox, when the user clicks "Not now".
			}
			/*
			todo: would be a nice options thing for geoloc
 interface PositionOptions {
	attribute boolean enableHighAccuracy;
	attribute long timeout;
	attribute long maximumAge;
	};*/
		);
		return false;
	}).each(function()
	{
		$(this).closest('.btn-group.hidden').removeClass('hidden');
	});
	$('.range_list_output').each(function () {
		var output = $('output', this);
//		console.log(output);	
		var change = function () {
			output.text($(this).prop('value') || '');
		};
		$('input[type="range"]', this)
			.on('input', change)
			.each(change);
	});
	// fixme: FOUCs for btnratings etc in IE8
	$('div.btn-radio button.btn').off('click').click(function(event){
		var $btn = $(this);
		$('#'+$btn.attr('data-for')).attr('checked',true); // couple with its radio button
		var all_buttons = $btn.closest('div.btn-group').find('button.btn'); // find all buttons
		all_buttons.removeClass('btn-checked'); // uncheck all
		$btn.addClass('btn-checked'); // check this one
		return false;
	}).each(function() {
		var $btn = $(this);
		$btn.closest('div.btn-group').removeClass('hidden'); // show special buttons
		$btn.closest('.controls').find('label[class!=keep-label]').addClass('hidden'); // hide normal radio buttons
	});
	
	$('div.btn-checkbox button.btn').off('click').click(function(event){
		var $btn = $(this);
		var checked = $('#'+$btn.attr('data-for')).attr('checked');
		$('#'+$btn.attr('data-for')).attr('checked',!checked); // couple with its radio button
		$btn.toggleClass('btn-checked',!checked); // check this one
		return false;
	}).each(function() {
		var $btn = $(this);
		$btn.closest('div.btn-group').removeClass('hidden'); // show special buttons
		$btn.closest('.controls').find('label').addClass('hidden'); // hide normal radio buttons
	});
	
	$('div.btn-check button.btn').off('click').click(function(event){
		var $btn = $(this);
		var checked = $('#'+$btn.attr('data-for')).attr('checked');
		$btn.find('i').toggleClass('icon-check',!checked).toggleClass('icon-check-empty',checked);
		$('#'+$btn.attr('data-for')).attr('checked',!checked); // couple with its radio button
		$btn.toggleClass('btn-checked',!checked); // check this one
		return false;
	}).each(function() {
		var $btn = $(this);
		$btn.closest('div.btn-group').removeClass('hidden'); // show special buttons
		$btn.closest('.controls').find('label').addClass('hidden'); // hide normal radio buttons
	});
	
	$('label.btn-remove').off('click').click(function(event){
		var $btn = $(this);
		var checked = $btn.find('input').attr('checked');
//		console.log(!checked);
		$btn.find('input').attr('checked',!checked); // couple with its radio button
		$btn.toggleClass('btn-checked',!checked); // check this one
		return false;
	}).each(function() {
		var $btn = $(this);
		$btn.addClass('btn'); // make buttons
		$btn.find('input').addClass('hidden'); // hide normal radio buttons
	});
	
	var pathArray = location.href.split( '/' );
	var protocol = pathArray[0];
	var host = pathArray[2];
	if(host==='localhost:8888') host = host + "/zwang";
	var url = protocol + '//' + host + "/";
	
	$("select.select2zone").select2();
	$("input.select2add").each(function(i,elm)
	{
		var slct = $(elm); 
		var slctdata = $.parseJSON(slct.attr('data-select2add'));
		slct.select2({
			createSearchChoice:function(term, data)
			{ 
				if ($(data).filter(function() { 
					return this.text.localeCompare(term)==0; 
				}).length===0) 
				{
					return {id:term, text:term};
				}
			},
			initSelection:function(element, callback)
			{
				var data = {id: element.val(), text: element.val()};
				$.each(slctdata, function(k, v) {
					if(v.id ==	element.val()) {
						data = v;
						return false;
					} 
				});
				callback(data);
			},
			maximumSelectionSize: slct.attr('data-select2maximumSelectionSize'),
			maximumInputLength: slct.attr('data-select2maximumInputLength'),
			data: slctdata, 
			multiple: !!slct.prop('multiple'), 
			allowClear: true,
		});
	});
	$('.select2place').select2({
		ajax: {
			url: url + "places/search",
			dataType: 'json',
			quietMillis: 100,
			data: function (term, page) { // page is the one-based page number tracked by Select2
				return {
					term: term, //search term
					page: page, // page number
				};
			},
			results: function (data, page) {
				var more = (page * 10) < data.total; // whether or not there are more results available

				// notice we return the value of more so Select2 knows if more results can be loaded
				return {results: data.places, more: more};
			}
		},
		initSelection: function(element, callback) {
			// the input tag has a value attribute preloaded that points to a preselected movie's id
			// this function resolves that id attribute to an object that select2 can render
			// using its formatResult renderer - that way the movie name is shown preselected
			var id=$(element).val();
			if (id!=="") {
				$.ajax(url + "places/get/"+id, {
					dataType: "json"
				}).done(function(data) { 
					callback(data[0]); 
				}).fail(ajaxErrorHandling);
			}
		},
		minimumInputLength: 3,
		formatInputTooShort: function (term, minLength) {
			return "Bitte geben Sie mindestens 3 Zeichen ein.";
		},
		formatNoMatches: function (term) {
			return "Ort nicht gefunden, bitte geben Sie den nächstgelegenen größeren Ort ein.";				
		}
	});
	
	$('*[title]').tooltip({
		container: 'body'
	});
});
function bootstrap_alert(message,bold) 
{
	var $alert = $('<div class="alert alert-error"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>' + (bold ? bold:'Problem' ) + '</strong> ' + message + '</div>');
	$alert.insertAfter( $('nav') );
	$alert[0].scrollIntoView(false);
}

function ajaxErrorHandling (e, x, settings, exception) 
{
	var message;
	var statusErrorMap = 
	{
		'400' : "Server understood the request but request content was invalid.",
		'401' : "You don't have access.",
		'403' : "You were logged out while coding, please open a new tab and login again. This way no data will be lost.",
		'404' : "Page not found.",
		'500' : "Internal Server Error.",
		'503' : "Server can't be reached."
	};
	if (e.status) 
	{
		message =statusErrorMap[e.status];
		if(!message)
			message= (typeof e.statusText !== 'undefined' && e.statusText !== 'error') ? e.statusText : 'Unknown error. Check your internet connection.';
	}
	else if(e.statusText==='parsererror')
		message="Parsing JSON Request failed.";
	else if(e.statusText==='timeout')
		message="The attempt to save timed out. Are you connected to the internet?";
	else if(e.statusText==='abort')
		message="The request was aborted by the server.";
	else
		message= (typeof e.statusText !== 'undefined' && e.statusText !== 'error') ? e.statusText : 'Unknown error. Check your internet connection.';

	bootstrap_alert(message, 'Fehler.');
}