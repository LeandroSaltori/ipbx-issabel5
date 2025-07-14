(function ($) {
$(document).ready(function(){
	
		var sd_json = localStorage.getItem("sd_json");
		if(sd_json == null || sd_json == ''){
		sd_json = {};  localStorage.setItem("sd_json", JSON.stringify(sd_json));
		}else{
		sd_json =  JSON.parse(sd_json);
		}
		
		var sd_jsontype = typeof(sd_json);
		if (sd_jsontype !== 'object'){
		sd_json = {};   localStorage.setItem("sd_json", JSON.stringify(sd_json));
		}	
	
	$("#callnum").focus();
	
	
	var server_result = localStorage.getItem("serverresult");
	
	
	console.log(server_result);
	if (server_result)
	{
		$("#server_result").html(server_result);
	}else{
		$("#server_result").html("<span>Nenhum resultado<span>");
	}
	
	
	function dialnum(itmc,number){
	if(number != ''){$(itmc).click(function(){
	$("#callnum").val(number);	
	$("#theform").submit(); });}
		}
	
	function lastdialed(){
	var lastcallednumber = localStorage.getItem("lastcallednumber");	
	if(lastcallednumber && lastcallednumber != ''){
	$("#lastdiled").html("Último número discado: <span title='CLICK PARA DISCAR' id='redial' class='clink'>"+lastcallednumber+"</span>");}
	if($("#redial").html()){
	var redialnum = $("#redial").html();
	dialnum("#redial",redialnum);
	}	
	}
 lastdialed();
	
	//ADD old storage to new array
	
	function addold(add_sd_name, add_sd_num){
	var sdnument = Object.keys(sd_json).length;
 	var addent = {"name": add_sd_name, "num": add_sd_num};
	 if(add_sd_name != '' && add_sd_num != '' && add_sd_name != null && add_sd_num != null){
	 sd_json[sdnument] = addent;
	 console.log(sd_json);
	 localStorage.setItem("sd_json", JSON.stringify(sd_json));
	 }}
	
	var sdn1 = localStorage.getItem("sdn1");
	var sdnum1 = localStorage.getItem("sdnum1");
	addold(sdn1, sdnum1);
	localStorage.removeItem("sdn1");
	localStorage.removeItem("sdnum1");
	
	
	var sdn2 = localStorage.getItem("sdn2");
	var sdnum2 = localStorage.getItem("sdnum2");
	addold(sdn2, sdnum2);
	localStorage.removeItem("sdn2");
	localStorage.removeItem("sdnum2");
	
	var sdn3 = localStorage.getItem("sdn3");
	var sdnum3 = localStorage.getItem("sdnum3");
	addold(sdn3, sdnum3);
	localStorage.removeItem("sdn3");
	localStorage.removeItem("sdnum3");



var sd_json = localStorage.getItem("sd_json");
		sd_json =  JSON.parse(sd_json);
	$.each(sd_json,function( inx , valx ) {
			if(valx.name != null){$("#sp1").append("<span class='clink' id='number"+inx+"'>"+valx.name+":"+valx.num+"</span><br>" );dialnum("#number"+inx,valx.num);}
			});	


$("#theform").submit(function(){
	var server_url = localStorage.getItem("serverurl");
	var server_ext = localStorage.getItem("serverext");
	var callnumber = $("#callnum").val(); 
	callnumber = callnumber.replace('.', '');
	callnumber = callnumber.replace('(', '');
	callnumber = callnumber.replace(')', '');
	callnumber = callnumber.replace(' ', '');
	callnumber = callnumber.replace(/-/g,"").replace(/ /g, "").replace(/\D/g,'');;
	
	localStorage.setItem("lastcallednumber", callnumber);
	
	 lastdialed();
	
		
 $("#server_result").load(server_url+"?exten="+server_ext+"&phone="+callnumber);
localStorage.setItem("serverresult", $("#server_result").val());
 
return false;
});	
});
})(jQuery); 
