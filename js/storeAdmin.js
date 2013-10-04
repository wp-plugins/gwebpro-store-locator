// JavaScript Document
jQuery(document).ready(function($) {
	initiate(jQuery('input[name="list-city"]')[0],'cities');
	jQuery('#post').submit(function() {
		//console.log(jQuery(".required[value='']").length);
		if (jQuery("#title").val()=="") 
		{
			jQuery('.spinner').hide();
			jQuery('#publish').removeClass('button-primary-disabled');
			jQuery("#title").addClass('validation-failed');
			return false;
		}
		else if(val_ck()==0) {
			jQuery(".required").filter(function() {
				return this.value.length == 0;
			}).addClass("validation-failed");
			jQuery('.spinner').hide();
			jQuery('#publish').removeClass('button-primary-disabled');
			return false;
		}
		else
		{	
			jQuery(".required").removeClass('validation-failed');
			return true;
		}
		return false;
	});
	jQuery(":input").blur(function() {
		if(this.value.length > 0) {
			jQuery(this).removeClass("validation-failed");
		}
	});
});

function val_ck()
{
	var c=0,i=0;
	jQuery('.required').each(function (index) {
        if (jQuery(this).val() != '') {
            i++;
        }
		c++;
    });
	if(i==c) return 1;
	else return 0;
}

document.write('<scr' + 'ipt type="text/javascript" src="http://maps.googleapis.com/maps/api/js?libraries=places&sensor=false"></scr' + 'ipt>');
function getLatLong()
{
	if(jQuery('input[name="list-address1"]').val()=="") alert("Please enter your proper address.");
	else if(jQuery('input[name="list-city"]').val()=="") alert("Please enter city.");
	else
	{
		jQuery('.loading_lat').css('display', 'inline-block');
		var address=jQuery('input[name="list-address1"]').val()+","+jQuery('input[name="list-city"]').val();
		var geocoder = new google.maps.Geocoder();
		geocoder.geocode({'address': address}, function postcodesearch(results, status) 
		{   
		  //console.log(results);
		  jQuery('.loading_lat').hide();
		  if (status == google.maps.GeocoderStatus.OK) 
		  {
			var lat = results[0].geometry.location.lat();
			var lng = results[0].geometry.location.lng();
			jQuery('input[name="list-lat"]').val(lat);
			jQuery('input[name="list-long"]').val(lng);
			placePin(lat,lng);
		  }
		  else {
			address=jQuery('input[name="list-city"]').val();
			geocoder.geocode({'address': address}, function postcodesearch(results, status) 
			{   
			  if (status == google.maps.GeocoderStatus.OK) 
			  {
				var lat = results[0].geometry.location.lat();
				var lng = results[0].geometry.location.lng();
				jQuery('input[name="list-lat"]').val(lng);
				jQuery('input[name="list-long"]').val(lat);
				placePin(lat,lng);
			  }
			  else {
				alert("Latituide and Longituide not found.");
			  }
			});
		  }
		});
	}
}

function initiate(input,type) 
{
	var autocomplete = new google.maps.places.Autocomplete(input); 
	autocomplete.setTypes(['('+type+')']);
}

function placePin(lat,long) 
{
	var latlng = new google.maps.LatLng(lat,long);
	var myOptions = {
		zoom: 14,
		center: latlng,
		panControl: true,
		zoomControl: true,
		scaleControl: true,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}
	document.getElementById("map_canvas").style.display="block";
	map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
	marker = new google.maps.Marker({
		map: map,
		position: latlng
	});
}