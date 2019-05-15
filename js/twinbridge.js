function showTbStatus(){
    var deferred = $.Deferred();
    $.get('/ajax/twinbridge/scripts/getVpnStatus.php',function(data){
        jsonData = JSON.parse(data);
        if (jsonData.error != false){
            //displayError(jsonData.reason); //synchronous
            deferred.reject(jsonData.reason);
            return;
        }
        switch(jsonData.result){
            case "stopped":
            {
                var promise = displayConnectForm(); //Asynchronous - Does not return any error
                relayPromise(promise, deferred);
                break;
            }
            case "connecting":
            case "configuring":
            {
                var promise = showLoading(); //Does not return any error
                relayPromise(promise, deferred);
                break;
            }
            case "connected":
            {
                
                $.get('/ajax/twinbridge/scripts/getStatus.php', function(data){
                    jsonData = JSON.parse(data)
                    if(jsonData.error != false){
                        deferred.reject(jsonData.reason)
                        //displayError(jsonData.reason);
                        return;
                    }
                    switch(jsonData.response.status){
                        case "free":
                            var promise = displayActionForm();
                            relayPromise(promise, deferred);
                            break;
                        case "hosting":
                        case "invited":
                            var promise = showLab(jsonData.response.status, jsonData.response.lab);
                            relayPromise(promise, deferred);
                            break;
                    }
                });
                break;
            }
        }
    });
    return deferred.promise();
}

function relayPromise(from, to){
    from.done(function(data){
        to.resolve(data);
    });
    from.fail(function(data){
        to.reject(data);
    })
}

function displayError(errorMsg){
    var errorBanner = '<div class="alert alert-danger alert-dismissable">' + errorMsg + '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button></div>';
    $("#twinBridgeContent").prepend(errorBanner)
    
}

 function showLab(status, lab){
    var deferred = $.Deferred();

    var params = {};
    params['status'] = status;
    params['lab'] = lab;
    $.post('/ajax/twinbridge/showLab.php', params, function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            deferred.reject(jsonData.reason);
            //displayError(jsonData.reason);
            return;
        }
        $("#twinBridgeContent").html(jsonData.response);
        $("#menu").click(function(){
            var displayPromise = displayActionForm();
            displayPromise.fail(function(data){
                displayError(data);
            });
        });
        deferred.resolve();
    });
    return deferred.promise()
}

function showLoading(){
    var deferred = $.Deferred();
    $.get('/ajax/twinbridge/showLoading.php', function(form){
        $("#twinBridgeContent").html(form);
        return deferred.resolve();
    });
    return deferred.promise();
}

 function displayAcademiesListForm(){
    var deferred = $.Deferred();
    var form = $("#actionForm").find(":input");
    var formAttr = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "csrf_token"){
            formAttr[$(v).attr("name")] = $(v).val();
        }
    });
    $.post('/ajax/twinbridge/forms/listAcademiesForm.php', formAttr,  function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            deferred.reject(jsonData.reason);
            //displayError(jsonData.reason);
            return;
        }
        $("#twinBridgeContent").html(jsonData.response);
        deferred.resolve();
    });
    return deferred.promise();
}


 function displayConnectForm(){
    var deferred = $.Deferred();
    $.get('/ajax/twinbridge/forms/connectionForm.php',  function(form){
        $("#twinBridgeContent").html(form);
        $("#connectTB").click(function(){
            var connectPromise = connectTwinBridge();
            connectPromise.fail(function(data){
                var showPromise = showTbStatus();
                showPromise.done(function(){
                    displayError(data);
                })
                showPromise.fail(function(showData){
                    displayError(showData);
                })
            });
            
            

            
        });
        deferred.resolve();
    });
    return deferred.promise();
}

function displayActionForm(){
    var deferred = $.Deferred();
    $.get('/ajax/twinbridge/forms/actionForm.php', function(form){
        $("#twinBridgeContent").html(form);

        $("#createBtn").click(function(){
            var displayPromise = displayAcademiesListForm();
            displayPromise.fail(function(data){
                displayError(data);
            });
        });
        $("#joinBtn").click(function(){
            var joinPromise = displayJoinForm();
            joinPromise.fail(function(data){
                displayError(data);
            });
        });
        deferred.resolve();
    });
    return deferred.promise();
}

 function displayJoinForm(){
    var deferred = $.Deferred();
    $.get('/ajax/twinbridge/forms/joinLabForm.php', function(form){
        $("#twinBridgeContent").html(form);
        $('#join').click(function(){
            var joinPromise = joinLab();
            joinPromise.fail(function(data){
                displayError(data);
            });
        });
        deferred.resolve();
    });
    return deferred.promise();
}

 function joinLab(){
    var deferred = $.Deferred();
    var form = $("#joinForm").find(":input");
    var params = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "pin" || $(v).attr("name") == "csrf_token"){
            params[$(v).attr("name")] = $(v).val();
        }
    });
    $.post('/ajax/twinbridge/scripts/joinLab.php', params, function(data){
        jsonData = JSON.parse(data)
        if(jsonData.error != false){
            deferred.reject(jsonData.reason);
            return;
        }
        var promise = showTbStatus();
        relayPromise(promise, deferred);
        
    });
    return deferred.promise();
}
 function connectTwinBridge(){
    var deferred = $.Deferred();
    var form = $("#connectionForm").find(":input");
    var logins = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "username" || $(v).attr("name") == "password" || $(v).attr("name") == "csrf_token"){
            logins[$(v).attr("name")] = $(v).val();
        }
    });
    showLoading();
    $.post('/ajax/twinbridge/scripts/connectTwinBridge.php', logins, function(data){
        jsonData = JSON.parse(data)
        if(jsonData.error != false){
           //alert("error : " + jsonData.reason);
            //showTbStatus();
           //displayError(jsonData.reason);
           deferred.reject(jsonData.reason);
           return;
        }
        var promise = showTbStatus();
        relayPromise(promise, deferred);
    });
    return deferred.promise();

}

 function killVPN(){
    var deferred = $.Deferred();
    var csrf = $('#kill').attr("csrf");
    var params = {};
    params['csrf_token'] = csrf;
    $.post('/ajax/twinbridge/scripts/killVPN.php', params, function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            deferred.reject(jsonData.reason)
            return;
        }
        var promise = showTbStatus();
        relayPromise(promise, deferred);

    });

    var deferredPromise = deferred.promise();
    deferredPromise.fail(function(data){
        displayError(data);
    });
    
}

 function invite(id){
    var deferred = $.Deferred();
    var form = $("#twiningsList").find(":input");
    var formAttr = {};
    $.each(form, function(k, v){
        if($(v).attr("name") == "csrf_token"){
            formAttr[$(v).attr("name")] = $(v).val();
        }
    });
    formAttr['id'] = id;
    $.post('/ajax/twinbridge/scripts/invite.php', formAttr, function(data){
        jsonData = JSON.parse(data);
        if(jsonData.error != false){
            //alert("error : " + jsonData.reason);
            //displayError(jsonData.reason);
            deferred.reject(jsonData.reason);
            return;
        }
        var promise = showTbStatus();
        relayPromise(promise, deferred);
    });
    return deferred.promise();
}

function deleteRemote(id){
    $('#'+id).parent().parent().remove();
}

$().ready(function(){
    csrf = $('#csrf_token').val();
    pageCurrent = window.location.href.split("?")[1].split("=")[1];
    pageCurrent = pageCurrent.replace("#","");
    switch(pageCurrent) {
        case "twinbridge":
            var promise = showTbStatus();
            promise.fail(function(data){
                displayError(data);
            });
            $("#remotespoiler").click(function(){
                $expandedText = "Hide advanced settings";
                $hiddenText = "Show advanced settings";
                if ($("#remotespoiler").html().trim() === $hiddenText){
                    $("#remotespoiler").html($expandedText);
                } else {
                    $("#remotespoiler").html($hiddenText);                   
                }
            });
            $("#addremote").click(function(){
                var ids = $(".remote").map(function() {
                    return this.id;
                }).get();
                var newId = Math.max.apply(Math,ids) + 1;
                var remoteRow = `	<div class="row">
                                        <div class="col-md-3 form-group" >
                                            <div class="input-group remote" id="` + newId + `">
                                                <span class="input-group-addon">remote</span>
                                                <input type="number" name="remote` + newId + `-port" id="remote` + newId + `-port"  class="form-control" style="display: inline-block;width: 65%; text-align:right;" placeholder="port" />
                                                <select class="form-control" name="remote` + newId + `-protocol" id="remote` + newId + `protocol" style="display: inline-block;width: 25%;" >
                                                    <option value="udp" selected >UDP</option>
                                                    <option value="tcp">TCP</option>
                                                </select> 
                                                <a class="btn btn-danger form-control" onclick="deleteRemote(` + newId+ `)" style="display: inline-block;width: 10%;">
                                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>`;
                $("#addremote").parent().parent().before(remoteRow);
            });
            $("#autoDetectBtn").click(function(){
                detectPorts();
            });
        break;
    }
});

function detectPorts(){
    $("#colProgress").show();
    var retry = $("#connect-retry").val();
    var timeout = $("#connect-timeout").val();
    var source = new EventSource('/ajax/twinbridge/scripts/detectPorts.php?retry='+retry + '&timeout='+timeout);
    source.onmessage = function (event) {
        if(event.data === ""){
            return;
        }
        jsonEvent = JSON.parse(event.data);
        if(jsonEvent.type == 'done'){
            console.log('done');
            source.close();
            setTimeout(window.location = window.location.href, 1500);
        } else{
            console.log(jsonEvent.data)
            $("#detectProgress")
            .css("width", jsonEvent.data + "%")
            .attr("aria-valuenow", jsonEvent.data)
            .text(jsonEvent.data + "% Complete");
        }
    }; 
}
