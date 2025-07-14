(function ($) {
$(document).ready(function(){
	
		var server_url = localStorage.getItem("serverurl");
		var server_ext = localStorage.getItem("serverext");
		
		if(server_url != ''){$("#url_val").val(server_url);}
		if(server_ext != ''){$("#ext").val(server_ext);}
		
		   
	 
		var sd_json = localStorage.getItem("sd_json");
		console.log(sd_json);
		if(sd_json == null || sd_json == ''){
		sd_json = {};  localStorage.setItem("sd_json", JSON.stringify(sd_json));
		}else{
		sd_json =  JSON.parse(sd_json);
		}
		
		var sd_jsontype = typeof(sd_json);
		if (sd_jsontype !== 'object'){
		sd_json = {};   localStorage.setItem("sd_json", JSON.stringify(sd_json));
		}
		
	
	
	 
	 	
	function rlspeeddial(){
		var sdnument = Object.keys(sd_json).length;
		$("#speeddialhere").html("<span class='sdcount'>"+sdnument+" Números de discagem rápida armazenados.</span><br>");
		$.each(sd_json,function( inx , valx ) {
			$("#speeddialhere").append("<p style='margin:3px 0;'><span class='sd_ename'>"+valx.name+":</span><span class='sd_enum'>"+valx.num+"</span>"
			+" <input name='del_sd' indexname=\""+inx+"\" type='button' class='btnsm delbtn' id='del_sd' value='REMOVER'></p>");
			});	
			$(".delbtn").click(function(){
	 var delid = $(this).attr('indexname');
	 delete sd_json[delid];
	 localStorage.setItem("sd_json", JSON.stringify(sd_json));
	  rlspeeddial();
	 });
			
			console.log(sd_json);
		}	
		
		
		rlspeeddial();
	
 

 $("#addtosdfrm").submit(function(){
	var sdnument = Object.keys(sd_json).length;
	  sdnument = sdnument + 1
	 if(sd_json[sdnument]){sdnument = sdnument + 1;}
	 add_sd_name = $("#add_sd_name").val();
	 add_sd_num = $("#add_sd_num").val();
	 var addent = {"name": add_sd_name, "num": add_sd_num};
	 if(add_sd_name != '' && add_sd_num != ''){
	 sd_json[sdnument] = addent;
	 console.log(sd_json);
	 rlspeeddial();
	 }
	 $("#add_sd_name").val("");
	 $("#add_sd_num").val("");
	 localStorage.setItem("sd_json", JSON.stringify(sd_json));
	 return false;
	});
	
	 



	$("#set_url").click(function(){
		if ($("#url_val").val())
		{
			localStorage.setItem("serverurl", $("#url_val").val());
			localStorage.setItem("serverext", $("#ext").val());
			
	 	$("#set_url").css("background-color", "#B3B3B3");	
		$("#set_url").val("ALTERAÇÕES SALVAS");	
		
		setTimeout(
			function(){
			$("#set_url").val("OPÇOES SALVAS");	
			$("#set_url").css({ transition: 'background-color 1s ease-in-out',  "background-color": "#8CC63F" });}
			,500)
			
			
		}
	});
	
	 $("#celbtn").click(function(){	
	window.close();
	});
		
		

	function configChanged() {
		chrome.tabs.getSelected(null,function(tab)
		{
			c = new Object();
			c.from_language = localStorage["from_lang"];
			c.to_language = localStorage["to_lang"];
			c.ks = localStorage["ks"];
			c.ks_mod = localStorage["ks_mod"];

			chrome.tabs.sendMessage(tab.id, { name:"configChanged", data:c }, function(response) {
				//console.log(response); // Get The response
			});
		});
	}
});
})(jQuery);