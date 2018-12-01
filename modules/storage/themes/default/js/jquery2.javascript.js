deviceToFormat   = '';
deviceToUnmount  = '';
deviceToMount    = '';

$(document).ready(function() {

    $(document).bind('reveal.facebox', function(el) {

        if(deviceToFormat!='') {
            dev = deviceToFormat;
        }
        if(deviceToUnmount!='') {
            dev = deviceToUnmount;
        }
        if(deviceToMount!='') {
            dev = deviceToMount;
        }

        header = $('#fheader','#facebox').val(); 
        $('.title','#facebox').html(header+' '+dev);
    });

    $(document).bind('close.facebox', function(el) {
        deviceToFormat  = '';
        deviceToMount   = '';
        deviceToUnmount = '';
    });

});

function showFormat(dev) {
    deviceToFormat=dev;
    deviceToUnmount='';
    deviceToMount='';
    jQuery.facebox({ div: '#formatdialog' });
}

function showUmount(dev) {
    deviceToFormat='';
    deviceToMount='';
    deviceToUnmount=dev;
    jQuery.facebox({ div: '#umountdialog' });
}

function showMount(dev) {
    deviceToFormat='';
    deviceToUnmount='';
    deviceToMount=dev;
    jQuery.facebox({ div: '#mountdialog' });
}

function formatDevice() {
    event.preventDefault();
    if(deviceToFormat=='') {
        jQuery(document).trigger('close.facebox');
    } else {
        dev = deviceToFormat;
        format = $('#facebox').find('#format').val()
        jQuery(document).trigger('close.facebox');
        $('#targetdevice').val(dev);
        $('#targetaction').val('format');
       
        $('#format').val(format);
        $('#formdevice').submit();
    }
}

function umountDevice() {
    event.preventDefault();
    if(deviceToUnmount=='') {
        jQuery(document).trigger('close.facebox');
    } else {
        dev = deviceToUnmount;
        jQuery(document).trigger('close.facebox');
        $('#targetdevice').val(dev);
        $('#targetaction').val('unmount');
        $('#formdevice').submit();
    }
}

function mountDevice() {
    event.preventDefault();
    if(deviceToMount=='') {
        jQuery(document).trigger('close.facebox');
    } else {
        dev = deviceToMount;
        jQuery(document).trigger('close.facebox');
        $('#targetdevice').val(dev);
        $('#targetaction').val('mount');
        $('#formdevice').submit();
    }
}
