
chrome.contextMenus.create({
	title: "Discar: '%s'",
	contexts:["selection"],
	id:'sat'
});

chrome.contextMenus.onClicked.addListener(function(info, tab){
	//console.log(info);
	if(info.menuItemId = 'sat') {
		var selection = info.selectionText;
		var server_url = localStorage.getItem("serverurl");
		var server_ext = localStorage.getItem("serverext");
		if (!server_url)
		{
			alert("To use this extention please first set Server URL and Ext in the options menu!");
		}else{
			
			selection = selection.replace('.', '');
			selection = selection.replace('(', '');
			selection = selection.replace(')', '');
			selection = selection.replace(' ', '');
			selection = selection.replace('-', '');
			localStorage.setItem("lastcallednumber", selection);
			/*if(isNaN(selection)){
				alert("Selection must be a number!");
				}else{*/
			$.ajax({
				type: "GET",
				url: server_url+"?exten="+server_ext+"&",
				data: {phone: selection}
			}).done(function(text) {
				localStorage.setItem("serverresult", text);
				//alert("Calling: "+selection);
			});
			/*}*/
		}
	}
	
});


