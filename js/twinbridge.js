function showTbStatus(){
    $.get('/ajax/twinbridge/getVpnStatus.php',function(data){
        jsonData = JSON.parse(data);
        if (jsonData.error == false){
            switch(jsonData.result){
                case "stopped":
                {
                    displayConnectForm();
                    break;
                }
                case "connecting":
                case "configuring":
                {
                    $("#twinBridgeContent").html('<h4>Connection in progress</h4><img src="/img/loading.svg">');
                    break;
                }
                case "connected":
                {
                    //TODO : show list of twined academies. Add disconnect button. Handle errors. Maybe prevent concurrency? 
                    //Maybe detect if vxlan is setup?
                    displayActionForm(jsonData.ip, jsonData.mask);
                    break;
                }
            }
        }
    });
}

function invite(id){
    var form = $("#twiningsList").find(":input");
    var formAttr = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "csrf_token"){
            formAttr[$(v).attr("name")] = $(v).val();
        }
    });
    formAttr['id'] = id;
    $.post('/ajax/twinbridge/invite.php', formAttr, function(data){
        jsonData = JSON.parse(data)
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
            return;
        }
        alert('lab created!');
        
    });
}
function listAcademies(){
    var form = $("#actionForm").find(":input");
    var formAttr = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "csrf_token"){
            formAttr[$(v).attr("name")] = $(v).val();
        }
    });
    $.post('/ajax/twinbridge/listAcademies.php', formAttr, function(data){
        jsonData = JSON.parse(data)
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
            return;
        }
        $("#twinBridgeContent").html(jsonData.response);
    });
}


function displayConnectForm(){
    $.get('/ajax/twinbridge/connectionForm.php', function(form){
        $("#twinBridgeContent").html(form);
        $("#connectTB").click(function(){
            connectTwinBridge();
        });
    });
}
function displayActionForm(){
    $.get('/ajax/twinbridge/actionForm.php', function(form){
        $("#twinBridgeContent").html(form);

        $("#createBtn").click(function(){
            listAcademies();
        });
        $("#joinBtn").click(function(){
            joinLab();
        });
    });

}

function connectTwinBridge(){
    var form = $("#connectionForm").find(":input");
    var logins = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "username" || $(v).attr("name") == "password" || $(v).attr("name") == "csrf_token"){
            logins[$(v).attr("name")] = $(v).val();
        }
    });
    $.post('/ajax/twinbridge/connectTwinBridge.php', logins, function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            alert("An error occurred!")
        }
        showTbStatus();
    });
    $("#twinBridgeContent").html('<h4>Connection in progress</h4><img src="/img/loading.svg">');

}

$().ready(function(){
    csrf = $('#csrf_token').val();
    pageCurrent = window.location.href.split("?")[1].split("=")[1];
    pageCurrent = pageCurrent.replace("#","");
    switch(pageCurrent) {
        case "twinbridge":
            showTbStatus()
        break;
    }
});

