<?php

/*
 * LibreNMS
 *
 * Copyright (c) 2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

$tmp_devices = array();
if (!empty($device['hostname'])) {
    $sql = ' WHERE `devices`.`hostname`=?';
    $sql_array = array($device['hostname']);
} else {
    $sql = ' WHERE 1';
}

$sql .= ' AND `local_device_id` != 0 AND `remote_device_id` != 0';

$tmp_ids = array();
foreach (dbFetchRows("SELECT DISTINCT least(`devices`.`device_id`, `remote_device_id`) AS `remote_device_id`, GREATEST(`remote_device_id`,`devices`.`device_id`) AS `local_device_id` FROM `links` LEFT JOIN `ports` ON `local_port_id`=`ports`.`port_id` LEFT JOIN `devices` ON `ports`.`device_id`=`devices`.`device_id` $sql", $sql_array) as $link_devices) {
    if (!in_array($link_devices['local_device_id'], $tmp_ids) && device_permitted($link_devices['local_device_id'])) {
        $link_dev = dbFetchRow("SELECT * FROM `devices` WHERE `device_id`=?",array($link_devices['local_device_id']));
        $tmp_devices[] = array('id'=>$link_devices['local_device_id'],'label'=>$link_dev['hostname'],'title'=>generate_device_link($link_dev,'',array(),'','','',0),'group'=>$link_dev['location'],'shape'=>'box');
    }
    if (!in_array($link_devices['remote_device_id'], $tmp_ids) && device_permitted($link_devices['remote_device_id'])) {
        $link_dev = dbFetchRow("SELECT * FROM `devices` WHERE `device_id`=?",array($link_devices['remote_device_id']));
        $tmp_devices[] = array('id'=>$link_devices['remote_device_id'],'label'=>$link_dev['hostname'],'title'=>generate_device_link($link_dev,'',array(),'','','',0),'group'=>$link_dev['location'],'shape'=>'box');
    }
    array_push($tmp_ids,$link_devices['local_device_id']);
    array_push($tmp_ids,$link_devices['remote_device_id']);
}

$tmp_exp_ids = implode(',',$tmp_ids);

$port_ids = array();
foreach (dbFetchRows("SELECT DISTINCT LEAST(`M`.`port_id`, `P`.`port_id`) AS `remote_port_id`, GREATEST(`P`.`port_id`,`M`.`port_id`) AS `local_port_id` FROM `ipv4_mac` AS `M` LEFT JOIN `ports` AS `P`  ON `M`.`mac_address` = `P`.`ifPhysAddress` WHERE `P`.`port_id` IS NOT NULL AND `M`.`port_id` IS NOT NULL", array()) as $macs) {
    if (!in_array($macs['local_port_id'], $port_ids) && port_permitted($macs['local_port_id'])) {
        $port_dev = dbFetchRow("SELECT * FROM `devices` WHERE `device_id`=?", array($macs['local_port_id']));
        $port_det = dbFetchRow("SELECT * FROM `ports` WHERE `port_id`=?", array($macs['local_port_id']));
        if (!in_array($port_dev['device_id'],$tmp_ids)) {
            $mac_devices[] = array('id'=>$port_dev['device_id'], 'label'=>$port_dev['hostname'], 'title'=>generate_device_link($port_dev,'',array(),'','','',0),'group'=>$port_dev['location'], 'shape'=>'box');
            array_push($port_ids, $port_dev['device_id']);
            $port_data = $port_det;
            $from = $port_dev['device_id'];
            $port = shorten_interface_type($port_det['ifName']);
            $speed = $port_det['ifSpeed']/1000/1000;
            if ($speed == 100) {
                $width = 3;
            } elseif ($speed == 1000) {
                $width = 5;
            } elseif ($speed == 10000) {
                $width = 10;
            } elseif ($speed == 40000) {
                $width = 15;
            } elseif ($speed == 100000) {
                $width = 20;
            } else {
                $width = 1;
            }
            $link_in_used = ($port_det['ifInOctets_rate'] * 8) / $port_det['ifSpeed'] * 100;
            $link_out_used = ($port_det['ifOutOctets_rate'] * 8) / $port_det['ifSpeed'] * 100;
            if ($link_in_used > $link_out_used) {
                $link_used = $link_in_used;
            } else {
                $link_used = $link_out_used;
            }
            $link_used = round($link_used, -1);
            if ($link_used > 100) {
                $link_used = 100;
            }
            $link_color = $config['map_legend'][$link_used];
        }
    }
    if (!in_array($macs['remote_port_id'], $port_ids) && port_permitted($macs['remote_port_id'])) {
        $port_dev = dbFetchRow("SELECT * FROM `devices` WHERE `device_id`=?", array($macs['remote_port_id']));
        $port_det = dbFetchRow("SELECT * FROM `ports` WHERE `port_id`=?", array($macs['remote_port_id']));
        if (!in_array($port_dev['device_id'],$tmp_ids)) {
            $mac_devices[] = array('id'=>$port_dev['device_id'], 'label'=>$port_dev['hostname'], 'title'=>generate_device_link($port_dev,'',array(),'','','',0),'group'=>$port_dev['location'], 'shape'=>'box');
            array_push($port_ids, $port_dev['device_id']);
            $to = $port_dev['device_id'];
            $port .= ' > ' . shorten_interface_type($port_det['ifName']);
        }
    }
    $tmp_links[] = array('from'=>$from,'to'=>$to,'label'=>$port,'title'=>generate_port_link($port_data, "<img src='graph.php?type=port_bits&amp;id=".$port_data['port_id']."&amp;from=".$config['time']['day']."&amp;to=".$config['time']['now']."&amp;width=100&amp;height=20&amp;legend=no&amp;bg=".str_replace("#","", $row_colour)."'>",'',0,1),'width'=>$width,'color'=>$link_color);
}

$node_devices = array_merge($tmp_devices,$mac_devices);
$nodes = json_encode($node_devices);
 
if (is_array($tmp_devices[0])) {
    $tmp_links = array();
    foreach (dbFetchRows("SELECT local_device_id, remote_device_id, `remote_hostname`,`ports`.*, `remote_port` FROM `links` LEFT JOIN `ports` ON `local_port_id`=`ports`.`port_id` LEFT JOIN `devices` ON `ports`.`device_id`=`devices`.`device_id` WHERE (`local_device_id` IN ($tmp_exp_ids) AND `remote_device_id` IN ($tmp_exp_ids))") as $link_devices) {
        foreach ($tmp_devices as $k=>$v) {
            if ($v['id'] == $link_devices['local_device_id']) {
                $from = $v['id'];
                $port = shorten_interface_type($link_devices['ifName']);
                $port_data = $link_devices;
            }
            if ($v['id'] == $link_devices['remote_device_id']) {
                $to = $v['id'];
                $port .= ' > ' .shorten_interface_type($link_devices['remote_port']);
            }
        }
        $speed = $link_devices['ifSpeed']/1000/1000;
        if ($speed == 100) {
            $width = 3;
        } elseif ($speed == 1000) {
            $width = 5;
        } elseif ($speed == 10000) {
            $width = 10;
        } elseif ($speed == 40000) {
            $width = 15;
        } elseif ($speed == 100000) {
            $width = 20;
        } else {
            $width = 1;
        }
        $link_in_used = ($link_devices['ifInOctets_rate'] * 8) / $link_devices['ifSpeed'] * 100;
        $link_out_used = ($link_devices['ifOutOctets_rate'] * 8) / $link_devices['ifSpeed'] * 100;
        if ($link_in_used > $link_out_used) {
            $link_used = $link_in_used;
        } else {
            $link_used = $link_out_used;
        }
        $link_used = round($link_used, -1);
        if ($link_used > 100) {
            $link_used = 100;
        }
        $link_color = $config['map_legend'][$link_used];

        $tmp_links[] = array('from'=>$from,'to'=>$to,'label'=>$port,'title'=>generate_port_link($port_data, "<img src='graph.php?type=port_bits&amp;id=".$port['port_id']."&amp;from=".$config['time']['day']."&amp;to=".$config['time']['now']."&amp;width=100&amp;height=20&amp;legend=no&amp;bg=".str_replace("#","", $row_colour)."'>",'',0,1),'width'=>$width,'color'=>$link_color);
    }
 
    $edges = json_encode($tmp_links);
}

 
?>
 
<div id="visualization"></div>
<script src="js/vis.min.js"></script>
<script type="text/javascript">

    // create an array with nodes
    var nodes =
<?php
echo $nodes;
?>
    ;
 
    // create an array with edges
    var edges =
<?php
echo $edges;
?>
    ;
 
    // create a network
    var container = document.getElementById('visualization');
    var data = {
            nodes: nodes,
        edges: edges,
        stabilize: false
    };
    var options = {
       physics: {
           barnesHut: {
               gravitationalConstant: -80000, springConstant: 0.001, springLength: 200
           }
       },
       tooltip: {
           color: {
               background: '#ffffff'
           }
       },
       smoothCurves: {dynamic:false, type: "continuous"},
       edges: {
           color: {
               color: '#000000'
           }
       }
    };
    var network = new vis.Network(container, data, options);
    network.on("resize", function(params) {console.log(params.width,params.height)});
    network.on('click', function (properties) {
        if (properties.nodes > 0) {
            window.location.href = "/device/device="+properties.nodes+"/tab=map/"
        }
    });
</script>
<script>
var height = $(window).height() - 100;
$('#visualization').height(height + 'px');
network.redraw();
</script>

<?php

$pagetitle[] = "Map";

?>
