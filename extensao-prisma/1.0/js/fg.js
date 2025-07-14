var ks = 84; // t key
var ks_mod = "alt"; // alt modifier key

getConfig();

if (window == top) {
	window.addEventListener('keyup', doKeyPress, false); //add the keyboard handler
}

function getConfig() {
	/*chrome.extension.sendMessage({name: "getConfig"},function(response){
		//console.log(response);
		ks = response.ks;
		ks_mod = response.ks_mod;
	});*/
}

function doKeyPress(e){
	//Removed
	/*//console.log(e);
	//console.log("dkp:"+ks_mod+"/"+ks);
	if((ks_mod == 'alt' && e.altKey) || (ks_mod == 'ctrl' && e.ctrlKey)) {
		//console.log("Event keycode:"+e.keyCode+" setting keycode:"+ks);
		//console.log("Event key:"+String.fromCharCode(e.keyCode)+" setting key:"+String.fromCharCode(ks));
		//console.log(ks.charCodeAt(0));
		var code = (e.keyCode ? e.keyCode : e.which);
		if ((code) == ks) {
			var selection = window.getSelection().toString();
			chrome.extension.sendMessage({name: "reactOnHotkey",data: selection});
		}
	}
	//e.preventDefault();*/
}

chrome.extension.onMessage.addListener(function(request, sender, sendResponse){
	//console.log(sender.tab ? "from a content script:" + sender.tab.url : "from the extension");
	switch(request.name) {
		case "configChanged":
			ks = request.data.ks;
			ks_mod = request.data.ks_mod;
			//console.log("om:"+ks_mod+"/"+ks);
			sendResponse({});
		break;
		default:
			sendResponse({});
	}
});
