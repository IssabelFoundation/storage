<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 4.0                                                  |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2018 Issabel Foundation                                |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Initial Developer of the Original Code is Issabel Foundation     |
  +----------------------------------------------------------------------+
  $Id: index.php, Sat 01 Dec 2018 04:11:09 PM EST, nicolas@issabel.com
*/

include_once "libs/paloSantoConfig.class.php";
include_once "libs/paloSantoGrid.class.php";

function _moduleContent(&$smarty, $module_name) {
    //include issabel framework
    include_once "libs/misc.lib.php";

    //include module files
    include_once "modules/$module_name/configs/default.conf.php";

    load_language_module($module_name);

    //global variables
    global $arrConf;
    global $arrConfModule;
    $arrConf = array_merge($arrConf,$arrConfModule);

    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $accion = getAction();

    $content = "";
    switch($accion)
    {
        case 'format_drive': 
            $device = $_POST['targetdevice'];
            $format = $_POST['format'];
            $content = format_storage_device($smarty, $module_name, $local_templates_dir, $device, $format);
            break;
        case 'mount': 
            $device = $_POST['targetdevice'];
            $content = mount_storage_device($smarty, $local_templates_dir, $device);
            break;
        case 'secure_umount': 
            $device = $_POST['targetdevice'];
            $content = umount_storage_device($smarty, $local_templates_dir, $device);
            break;
        default:
            $content = list_storage_devices($smarty, $module_name, $local_templates_dir);
            break;
    }

    return $content;
}

function scan_drives() {

    $outdevices = `/usr/bin/issabel-helper storage -list`;
    $devices = preg_split("/\n/",$outdevices);

    $final_devices = array();
    $partitions    = array();
    $devpartitions = array();

    // common device names
    $devname = array();
    $devname['mmcblk0'] = 'Micro SD';
    $devname['mmcblk1'] = 'Internal Storage';
    foreach (range('a', 'z') as $letter) {
       $devname['sd'.$letter]="USB Drive (sd$letter)";
    }

    foreach($devices as $device) {
        if(trim($device)=='') { continue; }
        $partes = preg_split("/\s+/",$device);

        $ftype    = $partes[0];
        $name     = $partes[1];
        $capacity = $partes[4];
        $type     = $partes[6];
        $mpoint   = isset($partes[7])?$partes[7]:'';

        if($type=='disk') {
            $final_devices[$name]=$capacity;
        } else if($type=='part') {
            if($mpoint<>'') {
                $used = `df -PBK $mpoint | tail -n 1 | awk '{print $3}' `;
                $used = substr($used,0,-2)."000";
            } else { 
                $used = -1;
            }

            $partitions[$name]="$capacity^$mpoint^$used^$ftype"; 
        }
    }

    foreach($final_devices as $name=>$nada) {
        foreach($partitions as $part=>$nada) {
            if(preg_match("/^$name/",$part)) {
                $devpartitions[$name][$part]=$partitions[$part];
            }
        }
    }

    return array($final_devices, $devpartitions, $devname);

}

function list_storage_devices($smarty, $module_name, $local_templates_dir, $error='')
{

    list ($final_devices, $devpartitions, $devname) = scan_drives();

    if(is_readable("/usr/share/issabel/storage/error")) {
        $error = file_get_contents('/usr/share/issabel/storage/error');
        unlink('/usr/share/issabel/storage/mount');
        unlink('/usr/share/issabel/storage/umount');
        unlink('/usr/share/issabel/storage/error');
    }

    // Paginacion
    $limit = 100;
    $total = count($final_devices);
    $oGrid = new paloSantoGrid($smarty);
    $oGrid->setLimit($limit);
    $oGrid->setTotal($total);
    $offset = $oGrid->calculateOffset();
    $end    = $oGrid->getEnd();
    //Fin Paginacion

    // find out if we have one mounted already in /var/spool/asterisk
    $already_mounted=0;
    foreach($devpartitions as $name=>$nada) {
        foreach($devpartitions[$name] as $partname=>$data) {
            $prt = preg_split("/\^/",$data);
            if($prt[1]=='/var/spool/asterisk') { $already_mounted=1; }
        }
    }

    $arrData = null;
    if(is_array($final_devices) && $total>0){
        foreach($final_devices as $name => $capacity) {

            $arrTmp[0] = isset($devname[$name])?_tr($devname[$name]):_tr($name);
            $arrTmp[1] = $capacity;

            $used=-1;
            $mpoint='';
            $ftype=array();

            foreach($devpartitions[$name] as $partname=>$data) {
                $prt = preg_split("/\^/",$data);
                $mpoint = $prt[1];
                $used += $prt[2]; 
                $ftype[]=$prt[3];
            }

            if($used<0) {
                $used = 'not mounted';
            } else {
                $used = humanFileSize($used);
            }

            $arrTmp[2] = _tr($used);

            $partname = trim($partname);

            $arrTmp[3] = join(",",$ftype);

            $arrTmp[4] = '';
                if($used=='not mounted') {
                $arrTmp[4] = '<a class="btn btn-danger" href="#formatdialog" onclick=\'showFormat("'.$name.'")\'>'._tr('Format').'</a>';
                if(count($devpartitions[$name])==1 && $already_mounted==0) {
                    // only lets us mount drives with one partition
                    $arrTmp[4] .= '&nbsp; <a class="btn btn-primary" href="#mountdialog" onclick=\'showMount("'.$partname.'")\'>'._tr('Mount').'</a>';
                }
            } else {
                if($mpoint=='/var/spool/asterisk') {
                    $arrTmp[4] .= '<a class="btn btn-primary" href="#umountdialog" onclick=\'showUmount("'.$partname.'")\'>'._tr('Unmount').'</a>';
                } else {
                    $arrTmp[4].= "($mpoint)";
                }
            }
            $arrData[] = $arrTmp;
        }
    }

    $arrGrid = array("title"    => _tr('Storage Devices'),
            "url"      => array('menu' => $module_name),
            "icon"     => "/modules/$module_name/images/storage.png",
            "width"    => "99%",
            "start"    => ($total==0) ? 0 : $offset + 1,
            "end"      => $end,
            "total"    => $total,
            "columns"  => array(
                0 => array("name"      => _tr('Device')),
                1 => array("name"      => _tr('Capacity')),
                2 => array("name"      => _tr('Used')),
                3 => array("name"      => _tr('Type')),
                4 => array("name"      => _tr('Action')),
                )
            );

    $contenidoModulo = '';

    if($error<>'') {
        $contenidoModulo .= "<div class='alert alert-danger'>$error</div>";
    }

    $contenidoModulo .= $oGrid->fetchGrid($arrGrid, $arrData);

    $contenidoModulo .= "<div id='formatdialog' style='display:none;'>";
    $contenidoModulo .= "
        <form method='post' id='formdevice'>
        <input type=hidden name='targetdevice' id='targetdevice'>
        <input type=hidden name='targetaction' id='targetaction'>
        <input type=hidden name='fheader' id='fheader' value='"._tr('Format and partition')."'>
        <h2 class='title'></h2></br>
        "._tr("Type").": <select name='format' id='format'><option value='vfat'>FAT</option><option value='ext4'>EXT4</option></select>
        </br></br>
        <button class='btn btn-danger' onclick='formatDevice()'>"._tr('FORMAT AND ERASE ALL DATA')."</button>
        <button class='btn btn-primary' onclick='event.preventDefault();jQuery(document).trigger(\"close.facebox\")'>"._tr('Cancel')."</button>
        </form>
        ";
    $contenidoModulo .= "</div>";

    $contenidoModulo .= "<div id='umountdialog' style='display:none;'>";
    $contenidoModulo .= "

        <form method='post' id='formdeviceumount'>
        <input type=hidden name='targetdevice' id='targetdevice'>
        <input type=hidden name='targetaction' id='targetaction'>
        <input type=hidden name='fheader' id='fheader' value='"._tr('Unmount')."'>
        <h2 class='title'></h2><br/>
        <button class='btn btn-primary' onclick='umountDevice()'>"._tr("Safely Remove")."</button>
        </form>
        ";
    $contenidoModulo .= "</div>";

    $contenidoModulo .= "<div id='mountdialog' style='display:none;'>";
    $contenidoModulo .= "

        <form method='post' id='formdevicemount'>
        <input type=hidden name='targetdevice' id='targetdevice'>
        <input type=hidden name='targetaction' id='targetaction'>
        <input type=hidden name='fheader' id='fheader' value='"._tr('Mount')."'>
        <h2 class='title'></h2><br/>
        <button class='btn btn-primary' onclick='mountDevice()'>"._tr("Mount as Spool/Recordings")."</button>
        </form>
        ";
    $contenidoModulo .= "</div>";

    return $contenidoModulo;
}

function getAction() {
    if(isset($_POST["targetaction"])) { 
        if($_POST['targetaction']=='format') { 
            return "format_drive"; 
        } else if($_POST['targetaction']=='mount') {
            return "mount";
        } else { 
            return "secure_umount"; 
        }
    } else {
        return "list_storage_devices";
    }
}

function humanFileSize($size,$unit="") {
    if( (!$unit && $size >= 1<<30) || $unit == "G")
        return number_format($size/(1<<30),1)."G";
    if( (!$unit && $size >= 1<<20) || $unit == "M")
        return number_format($size/(1<<20),1)."M";
    if( (!$unit && $size >= 1<<10) || $unit == "K")
        return number_format($size/(1<<10),1)."K";
    return number_format($size)." bytes";
}

function format_storage_device($smarty, $module_name, $local_templates_dir, $device, $format) {

    list ($final_devices, $devpartitions, $devname) = scan_drives();
    exec("udevadm info --query=all --name=$device | grep ID_BUS | grep usb",$output,$return);
    if($return<>0) {
        exec("udevadm info --query=all --name=$device | grep ID_PATH_TAG | grep _sd",$output,$return);
        if($return<>0) {
            $error   = _tr("Permission denied");
            $content = list_storage_devices($smarty, $module_name, $local_templates_dir, $error);
            return $content;
            die();
        }
    }

    $sComando = "/usr/bin/issabel-helper storage -a format -d $device -f $format";

    $output = $ret = NULL;
    exec($sComando, $output, $ret);
    if ($ret != 0) {
        return "Could not format";
    }
    //return $sComando;
    header("Refresh:0");
}

function mount_storage_device($smarty, $local_templates_dir, $device) {
    list ($final_devices, $devpartitions, $devname) = scan_drives();
    exec("udevadm info --query=all --name=$device | grep ID_BUS | grep usb",$output,$return);
    if($return<>0) {
        exec("udevadm info --query=all --name=$device | grep ID_PATH_TAG | grep _sd",$output,$return);
        if($return<>0) {
            $error   = _tr("Permission denied");
            $content = list_storage_devices($smarty, $module_name, $local_templates_dir, $error);
            return $content;
            die();
        }
    }

    if(!is_dir("/usr/share/issabel/storage")) {
        mkdir("/usr/share/issabel/storage");
    }

    file_put_contents("/usr/share/issabel/storage/mount",$device);
    sleep(2);

    header("Refresh:0");

}

function umount_storage_device($smarty, $local_templates_dir, $device) {
    list ($final_devices, $devpartitions) = scan_drives();
    exec("udevadm info --query=all --name=$device | grep ID_BUS | grep usb",$output,$return);
    if($return<>0) {
        exec("udevadm info --query=all --name=$device | grep ID_PATH_TAG | grep _sd",$output,$return);
        if($return<>0) {
            $error   = _tr("Permission denied");
            $content = list_storage_devices($smarty, $module_name, $local_templates_dir, $error);
            return $content;
            die();
        }
    }

    if(!is_dir("/usr/share/issabel/storage")) {
        mkdir("/usr/share/issabel/storage");
    }

    file_put_contents("/usr/share/issabel/storage/umount",$device);
    sleep(2);
    header("Refresh:0");


}


?>
