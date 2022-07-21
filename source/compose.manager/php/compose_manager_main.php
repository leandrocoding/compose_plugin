<?PHP

require_once("/usr/local/emhttp/plugins/compose.manager/php/defines.php");
require_once("/usr/local/emhttp/plugins/compose.manager/php/util.php");

$vars = parse_ini_file("/var/local/emhttp/var.ini");

$stackstate = shell_exec($plugin_root."/scripts/compose.sh -c list");
$stackstate = json_decode($stackstate, TRUE);

$composeProjects = @array_diff(@scandir($compose_root),array(".",".."));
if ( ! is_array($composeProjects) ) {
  $composeProjects = array();
}
foreach ($composeProjects as $script) {
  if ( ( ! is_file("$compose_root/$script/docker-compose.yml") ) &&
       ( ! is_file("$compose_root/$script/indirect") ) ) {
    continue;
  }

  $scriptName = $script;
  if ( is_file("$compose_root/$script/name") ) {
    $scriptName = trim(file_get_contents("$compose_root/$script/name"));
  }
  $id = str_replace(".","-",$script);
  $id = str_replace(" ","",$id);

  $isrunning = FALSE;
  $isexited = FALSE;
  $ispaused = FALSE;
  $isup = FALSE; 
  foreach ( $stackstate as $entry )
  {
    if ( strcasecmp($entry["Name"], sanitizeStr($scriptName)) == 0 ) {
      $isup = TRUE; 
      if ( strpos($entry["Status"], 'running') !== false ) {
        $isrunning = TRUE;
      }

      if ( strpos($entry["Status"], 'exited') !== false ) {
        $isexited = TRUE;
      }

      if ( strpos($entry["Status"], 'paused') !== false ) {
        $ispaused = TRUE;
      }
    }
  }

  if ( is_file("$compose_root/$script/description") ) {
    $description = @file_get_contents("$compose_root/$script/description");
    $description = str_replace("\r","",$description);
    $description = str_replace("\n","<br>",$description);
  } else {
    $description = $variables['description'] ? $variables['description'] : "No description<br>($compose_root/$script)";
  }

  $autostart = '';
  if ( is_file("$compose_root/$script/autostart") ) {
    $autostarttext = @file_get_contents("$compose_root/$script/autostart");
    if ( strpos($autostarttext, 'true') !== false ) {
      $autostart = 'checked';
    }
  }

  $o .= "<tr><td width='30%' style='text-align:initial'>";
  $o .= "<font size='2'><span class='ca_nameEdit' id='name$id' data-nameName='$scriptName' data-isup='$isup' data-scriptName=".escapeshellarg($script)." style='font-size:1.9rem;cursor:pointer;color:#ff8c2f;'><i class='fa fa-gear'></i></span>&nbsp;&nbsp;<b><span style='color:#ff8c2f;'>$scriptName</span>&nbsp;</b></font>";
  if ( $isup ) {
    if (  $isexited && !$isrunning) {
        $o .= "<i class='fa fa-square stopped red-text' style='margin-left: 5px;'></i>";
    }
    else {
      if ( $isrunning && !$isexited && !$ispaused) {
        $o .= "<i class='fa fa-play started green-text' style='margin-left: 5px;'></i>";
      }
      elseif( $ispaused && !$isexited && !$isrunning )
      {
        $o .= "<i class='fa fa-pause started orange-text' style='margin-left: 5px;'></i>";
      }
      elseif( $ispaused && !$isexited )
      {
        $o .= "<i class='fa fa-play started orange-text' style='margin-left: 5px;'></i>";
      }
      else
      {
        $o .= "<i class='fa fa-play started red-text' style='margin-left: 5px;'></i>";
      }
    }
  }
  $o .= "<br>";
  $o .= "<span class='ca_descEdit' data-scriptName=".escapeshellarg($script)." id='desc$id'>$description</span>";
  $o .= "</td>";
  $o .= "<td width=25%></td>";
  $o .= "<td width=5%><input type='button' value='Compose Up'   class='up$id' id='$id' onclick='ComposeUp(&quot;$compose_root/$script&quot;);'></td>";
  $o .= "<td width=5%><input type='button' value='Compose Down' class='down$id' id='$id' onclick='ComposeDown(&quot;$compose_root/$script&quot;);'></td>";
  $o .= "<td width=5%><input type='button' value='Compose Pull' class='pull$id' id='$id' onclick='ComposePull(&quot;$compose_root/$script&quot;);'></td>";
  $o .= "<td width=5%><input type='checkbox' class='auto_start' data-scriptName=".escapeshellarg($script)." id='$id' style='display:none' $autostart></td>";
}
?>

<script src="/plugins/compose.manager/javascript/ace/ace.js" type= "text/javascript"></script>
<!-- <script src="/plugins/compose.manager/javascript/sweetalert/sweetalert2.min.js" type= "text/javascript"></script> -->
<script>
var compose_root=<?php echo json_encode($compose_root); ?>;
var caURL = "/plugins/compose.manager/php/exec.php";
var compURL = "/plugins/compose.manager/php/compose_util.php";
var aceTheme=<?php echo (in_array($theme,['black','gray']) ? json_encode('ace/theme/tomorrow_night') : json_encode('ace/theme/tomorrow')); ?>;

if (typeof swal2 === "undefined") {
		$.getScript( '/plugins/compose.manager/javascript/sweetalert/sweetalert2.min.js');
}

function basename( path ) {
  return path.replace( /\\/g, '/' ).replace( /.*\//, '' );
}

function dirname( path ) {
  return path.replace( /\\/g, '/' ).replace( /\/[^\/]*$/, '' );
}

$(function() {
  var editor = ace.edit("itemEditor");
  editor.setTheme(aceTheme);
  editor.setShowPrintMargin(false);
})

$(function() {
	$(".tipsterallowed").show();
	$('.ca_nameEdit').tooltipster({
		trigger: 'custom',
		triggerOpen: {click:true,touchstart:true,mouseenter:true},
		triggerClose:{click:true,scroll:false,mouseleave:true},
		delay: 1000,
		contentAsHTML: true,
		animation: 'grow',
		interactive: true,
		viewportAware: true,
		functionBefore: function(instance,helper) {
			var origin = $(helper.origin);
			var myID = origin.attr('id');
			var name = $("#"+myID).html();
      var disabled = $("#"+myID).attr('data-isup') == "1" ? "disabled" : "";
      var notdisabled = $("#"+myID).attr('data-isup') == "1" ? "" : "disabled";
			var stackName = $("#"+myID).attr("data-scriptname");
			// instance.content(stackName + "<br><center><input type='button' value='Edit Name' onclick='editName(&quot;"+myID+"&quot;);' "+disabled+"><input type='button' value='Edit Description' onclick='editDesc(&quot;"+myID+"&quot;);'><input type='button' onclick='editStack(&quot;"+myID+"&quot;);' value='Edit Stack'><input type='button' onclick='editEnv(&quot;"+myID+"&quot;);' value='Edit ENV'><input type='button' onclick='deleteStack(&quot;"+myID+"&quot;);' value='Delete Stack' "+disabled+"><input type='button' onclick='ComposeLogs(&quot;"+myID+"&quot;);' value='Logs' "+notdisabled+"></center>");
      instance.content(stackName + "<br><center><input type='button' value='Edit Name' onclick='editName(&quot;"+myID+"&quot;);' "+disabled+"><input type='button' value='Edit Description' onclick='editDesc(&quot;"+myID+"&quot;);'><input type='button' onclick='editStack(&quot;"+myID+"&quot;);' value='Edit Stack'><input type='button' onclick='deleteStack(&quot;"+myID+"&quot;);' value='Delete Stack' "+disabled+"><input type='button' onclick='ComposeLogs(&quot;"+myID+"&quot;);' value='Logs' "+notdisabled+"></center>");
		}
	});
  $('.auto_start').switchButton({labels_placement:'right', on_label:"On", off_label:"Off"});
  $('.auto_start').change(function(){
      var script = $(this).attr("data-scriptname");
      var auto = $(this).prop('checked');
      $.post(caURL,{action:'updateAutostart',script:script,autostart:auto});
    });
});

function addStack() {
  var form = document.createElement("div");
  // form.classList.add("swal-content");
  form.innerHTML = `<input type="text" id="stack_name" class="swal-content__input" placeholder="stack_name">
                    <br>
                    <details>
                      <summary style="text-align: left">Advanced</summary>
                      <br>
                      <div class="swal-text">Stack Directory</div>
                      <input type="text" id="stack_path" class="swal-content__input" pattern="\/mnt\/.*\/.*" oninput="this.reportValidity()" title="A path under /mnt/user/ or mnt/cache/ or mnt/pool/" style="margin-top: 20px" placeholder="default">
                      <div style="display:none;">
                        <div class="swal-text">Pull From Github</div>
                        <input type="url" id="git_url" class="swal-content__input" style="margin-top: 20px" placeholder="https://github.com/example/repo.git">
                      </div>
                    </details>`;
  swal2({
    title: "Add New Compose Stack",
    text: "Enter in the name for the stack",
    content: form,
    buttons: true,
  }).then((inputValue) => {
    if (inputValue) {
      var new_stack_name = document.getElementById("stack_name").value;
      var new_stack_dir = document.getElementById("stack_path").value;
      var git_url = document.getElementById("git_url").value;
      if (!new_stack_name) {
        swal2({
          title: "Failed to create stack.",
          text: "Stack name unspecified.",
          icon: "error",
        })
      }
      else {
        $.post(
          caURL,
          {action:'addStack',stackName:new_stack_name,stackPath:new_stack_dir},
          function(data) {
            var title = "Failed to create stack.";
            var message = "";
            var icon = "error";
            if (data) {
              var response = jQuery.parseJSON(data);
              if (response.result == "success") {
                title = "Success";
              }
              message = response.message;
              icon = response.result;
            }
            swal2({
              title: title,
              text: message,
              icon: icon,
            }).then(() => {
              location.reload();
            });
          }
        );        
      }
    }
  });
}

function deleteStack(myID) {
  var stackName = $("#"+myID).attr("data-scriptname");
  var script = $("#"+myID).attr("data-namename");
  var element = document.createElement("div")
  element.innerHTML = "Are you sure you want to delete <font color='red'><b>"+script+"</b></font> (<font color='green'>"+compose_root+"/"+stackName+"</font>)?"; 
  swal2({
    content: element,
    title: "Delete Stack?",
    icon: "warning",
    buttons: true,
    dangerMode: true,
  }).then((willDelete) => {
    if (willDelete) {
      $.post(caURL,{action:'deleteStack',stackName:stackName},function(data) {
        if (data) {
          var response = jQuery.parseJSON(data);
          if (response.result == "warning") {
            title = "Success";
            swal2({
              title: "Files remain on disk.",
              text: response.message,
              icon: "warning",
            }).then(() => {
              location.reload();
            });
          } else {
            location.reload();
          }
        } else {
            location.reload();
        }
      });
    }
  });
}

function stripTags(string) {
	return string.replace(/(<([^>]+)>)/ig,"");
}

function editName(myID) {
	// console.log(myID);
  var currentName = $("#"+myID).attr("data-namename");
  $("#"+myID).attr("data-originalName",currentName);
  $("#"+myID).html("<input type='text' id='newName"+myID+"' value='"+currentName+"'><br><font color='red' size='4'><i class='fa fa-times' aria-hidden='true' style='cursor:pointer' onclick='cancelName(&quot;"+myID+"&quot;);'></i>&nbsp;&nbsp;<font color='green' size='4'><i style='cursor:pointer' onclick='applyName(&quot;"+myID+"&quot;);' class='fa fa-check' aria-hidden='true'></i></font>");
  $("#"+myID).tooltipster("close");
  $("#"+myID).tooltipster("disable");
}

function editDesc(myID) {
  var origID = myID;
  $("#"+myID).tooltipster("close");
  myID = myID.replace("name","desc");
  var currentDesc = $("#"+myID).html();
  $("#"+myID).attr("data-originaldescription",currentDesc);
  $("#"+myID).html("<textarea id='newDesc"+myID+"' cols='40' rows='5'>"+currentDesc+"</textarea><br><font color='red' size='4'><i class='fa fa-times' aria-hidden='true' style='cursor:pointer' onclick='cancelDesc(&quot;"+myID+"&quot;);'></i>&nbsp;&nbsp;<font color='green' size='4'><i style='cursor:pointer' onclick='applyDesc(&quot;"+myID+"&quot;); ' class='fa fa-check' aria-hidden='true'></i></font>");
  $("#"+origID).tooltipster("enable");
}

function applyName(myID) {
  var newName = $("#newName"+myID).val();
  var script = $("#"+myID).attr("data-scriptname");
  $("#"+myID).html(newName);
  $("#"+myID).tooltipster("enable");
  $("#"+myID).tooltipster("close");
  $.post(caURL,{action:'changeName',script:script,newName:newName},function(data) {
		window.location.reload();
	});
}

function cancelName(myID) {
  var oldName = $("#"+myID).attr("data-originalName");
  $("#"+myID).html(oldName);
  $("#"+myID).tooltipster("enable");
  $("#"+myID).tooltipster("close");
	window.location.reload();
}

function cancelDesc(myID) {
  var oldName = $("#"+myID).attr("data-originaldescription");
  $("#"+myID).html(oldName);
  $("#"+myID).tooltipster("enable");
  $("#"+myID).tooltipster("close");
}

function applyDesc(myID) {
  var newDesc = $("#newDesc"+myID).val();
  newDesc = newDesc.replace(/\n/g, "<br>");
  var script = $("#"+myID).attr("data-scriptname");
  $("#"+myID).html(newDesc);
  $.post(caURL,{action:'changeDesc',script:script,newDesc:newDesc});
}

function editStack(myID) {
  var buttonsList = {};

  buttonsList["compose_file"] = { text: "Compose File" };
  buttonsList["env_file"] = { text: "ENV File" };

  buttonsList["Cancel"] = { text: "Cancel", value: null, };
  swal2({
    title: "Select Stack File to Edit",
    className: 'edit-stack-form',
    buttons: buttonsList,
  }).then((result) => {
    if (result) {
      switch(result) {
        case 'compose_file':
          editComposeFile(myID);
          break;
        case 'env_file':
          editEnv(myID);
          break;

        default:
          return;
      }
    }
  });

}

function editComposeFile(myID) {
  var origID = myID;
  $("#"+myID).tooltipster("close");
  var script = $("#"+myID).attr("data-scriptname");
  $.post(caURL,{action:'getYml',script:script},function(data) {
    if (data) {
      var response = jQuery.parseJSON(data);
      var editor = ace.edit("itemEditor");
      editor.getSession().setValue(response.content);
      editor.getSession().setMode("ace/mode/yaml");

      $('#editorFileName').data("stackname", script);
      $('#editorFileName').data("stackfilename", "docker-compose.yml")
      $('#editorFileName').html(response.fileName)
      $(".editing").show();
			window.scrollTo(0, 0);
    }
  });
}

function editEnv(myID) {
  var origID = myID;
  $("#"+myID).tooltipster("close");
  var script = $("#"+myID).attr("data-scriptname");
  $.post(caURL,{action:'getEnv',script:script},function(data) {
    if (data) {
      var response = jQuery.parseJSON(data);
      var editor = ace.edit("itemEditor");
      editor.getSession().setValue(response.content);
      editor.getSession().setMode("ace/mode/text");

      $('#editorFileName').data("stackname", script);
      $('#editorFileName').data("stackfilename", ".env")
      $('#editorFileName').html(response.fileName)
      $(".editing").show();
			window.scrollTo(0, 0);
    }
  });
}

function cancelEdit() {
  $(".editing").hide();
}

function saveEdit() {
  var script = $("#editorFileName").data("stackname");
  var fileName = $("#editorFileName").data("stackfilename");
  var editor = ace.edit("itemEditor");
  var scriptContents = editor.getValue();
  var actionStr = null

  switch(fileName) {
    case 'docker-compose.yml':
      actionStr = 'saveYml'
      break;

    case '.env':
      actionStr = 'saveEnv'
      break;

    default:
      $(".editing").hide();
      return;
  }

  $.post(caURL,{action:actionStr,script:script,scriptContents:scriptContents},function(data) {
    if (data) {
      $(".editing").hide();
    }
  });

}

function ComposeUp(path) {
  var height = 800;
  var width = 1200;
  
  $.post(compURL,{action:'composeUp',path:path},function(data) {
    if (data) {
      openBox(data,"Stack "+basename(path)+" Up",height,width,true);
    }
  })
}

function ComposeDown(path) {
  var height = 800;
  var width = 1200;

  $.post(compURL,{action:'composeDown',path:path},function(data) {
    if (data) {
      openBox(data,"Stack "+basename(path)+" Down",height,width,true);
    }
  })
}

function ComposePull(path) {
  var height = 800;
  var width = 1200;

  $.post(compURL,{action:'composePull',path:path},function(data) {
    if (data) {
      openBox(data,"Stack "+basename(path)+" Pull",height,width,true);
    }
  })
}

function ComposeLogs(myID) {
  var height = 800;
  var width = 1200;
  $("#"+myID).tooltipster("close");
  var script = $("#"+myID).attr("data-scriptname");
  var path = compose_root + "/" + script;
  console.log(path);
  $.post(compURL,{action:'composeLogs',path:path},function(data) {
    if (data) {
      openBox(data,"Stack "+basename(path)+" Logs",height,width,true);
    }
  })
}
</script>

<HTML>
<HEAD>
<style type="text/css">
.edit-stack-form .swal-footer {
  display: table;
  margin-left: auto;
  margin-right: auto;
}
.edit-stack-form .swal-footer .swal-button-container {
   	display: table-row;
}
.edit-stack-form .swal-footer .swal-button-container .swal-button{
   	width: 150px;
}
</style>
</HEAD>
<BODY>

<div class='editing' hidden>
<!-- <center><b>Editing <?=$compose_root?>/<span id='editStackName'></span>/<span id='editStackFileName'></span></b><br> -->
<center><b>Editing <span id='editorFileName' data-stackname="" data-stackfilename=""></span></b><br>
<input type='button' value='Cancel' onclick='cancelEdit();'><input type='button' onclick='saveEdit();' value='Save Changes'><br>
<!-- <textarea class='editing' id='editStack' style='width:90%; height:500px; border-color:red; font-family:monospace;' ></textarea> -->
<div id='itemEditor' style='width:90%; height:500px; position: relative;'></div>
</center>
</div>

<span class='tipsterallowed' hidden></span><br>
<table>
<thead><tr><th style="text-align:left">Stack</th><th></th><th style="text-align:left" colspan="3">Commands</th><th style="text-align:left">Auto Start</th></tr></thead>
<?=$o?>
</table>
<br>
<span class='tipsterallowed' hidden><input type='button' value='Add New Stack' onclick='addStack();'><span><br>

</BODY>
</HTML>