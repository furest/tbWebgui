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
                    displayLoading();
                    break;
                }
                case "connected":
                {

                    $.get('/ajax/twinbridge/getStatus.php', function(data){
                        jsonData = JSON.parse(data)
                        if(jsonData.error != false){
                            alert("error : " + jsonData.reason);
                            return;
                        }
                        switch(jsonData.response.status){
                            case "free":
                                displayActionForm();
                                break;
                            case "hosting":
                            case "invited":
                                showLab(jsonData.response.status, jsonData.response.lab);
                        }
                    })
                    
                    break;
                }
            }
        }
    });
}

function showLab(status, lab){
    var params = {};
    params['status'] = status;
    params['lab'] = lab;
    $.post('/ajax/twinbridge/showLab.php', params, function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
            return;
        }
        $("#twinBridgeContent").html(jsonData.response);
    })
}

function displayLoading(){
    $.get('/ajax/twinbridge/showLoading.php', function(form){
        $("#twinBridgeContent").html(form);
    });
}

function killVPN(){
    var csrf = $('#kill').attr("csrf");
    var params = {};
    params['csrf_token'] = csrf;
    $.post('/ajax/twinbridge/killVPN.php', params, function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
        }
        showTbStatus();
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
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
            return;
        }
        showTbStatus();
        
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
        jsonData = JSON.parse(data);
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
            displayJoinForm();
        });
    });

}

function displayJoinForm(){
    $.get('/ajax/twinbridge/joinLabForm.php', function(form){
        $("#twinBridgeContent").html(form);
    });
}

function joinLab(){
    var form = $("#joinForm").find(":input");
    var params = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "pin" || $(v).attr("name") == "csrf_token"){
            params[$(v).attr("name")] = $(v).val();
        }
    });
    $.post('/ajax/twinbridge/joinLab.php', params, function(data){
        jsonData = JSON.parse(data)
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
            return;
        }
        showTbStatus();
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
    displayLoading();
    $.post('/ajax/twinbridge/connectTwinBridge.php', logins, function(data){
        jsonData = JSON.parse(data)
        if(jsonData.error != false){
            alert("error : " + jsonData.reason);
            return;
        }
        showTbStatus();
    });

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

