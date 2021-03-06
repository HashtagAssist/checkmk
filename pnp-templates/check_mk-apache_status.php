<?php
# +------------------------------------------------------------------+
# |             ____ _               _        __  __ _  __           |
# |            / ___| |__   ___  ___| | __   |  \/  | |/ /           |
# |           | |   | '_ \ / _ \/ __| |/ /   | |\/| | ' /            |
# |           | |___| | | |  __/ (__|   <    | |  | | . \            |
# |            \____|_| |_|\___|\___|_|\_\___|_|  |_|_|\_\           |
# |                                                                  |
# | Copyright Mathias Kettner 2014             mk@mathias-kettner.de |
# +------------------------------------------------------------------+
#
# This file is part of Check_MK.
# The official homepage is at http://mathias-kettner.de/check_mk.
#
# check_mk is free software;  you can redistribute it and/or modify it
# under the  terms of the  GNU General Public License  as published by
# the Free Software Foundation in version 2.  check_mk is  distributed
# in the hope that it will be useful, but WITHOUT ANY WARRANTY;  with-
# out even the implied warranty of  MERCHANTABILITY  or  FITNESS FOR A
# PARTICULAR PURPOSE. See the  GNU General Public License for more de-
# tails. You should have  received  a copy of the  GNU  General Public
# License along with GNU Make; see the file  COPYING.  If  not,  write
# to the Free Software Foundation, Inc., 51 Franklin St,  Fifth Floor,
# Boston, MA 02110-1301 USA.

# Modded: Thomas Zyska (tzyska@testo.de)
$RRD = array();
foreach ($NAME as $i => $n) {
    $RRD[$n] = "$RRDFILE[$i]:$DS[$i]:MAX";
    $WARN[$n] = $WARN[$i];
    $CRIT[$n] = $CRIT[$i];
    $MIN[$n]  = $MIN[$i];
    $MAX[$n]  = $MAX[$i];
    $ACT[$n]  = $ACT[$i];
}

$total_slots = intval($ACT['TotalSlots']);

#
# First graph with all data
#
$i=1;
$ds_name[$i] = "Apache Status";
$def[$i]  = "";
$opt[$i]  = " --vertical-label 'Connections' --title '$hostname: $servicedesc' -l 0";

$def[$i] .= "DEF:varTotal=${RRD['TotalSlots']} ";
$def[$i] .= "DEF:varOpen=${RRD['OpenSlots']} ";
$def[$i] .= "HRULE:${ACT['TotalSlots']}#000000:\"Total Slots\\: ${total_slots}\\n\" ";
$def[$i] .= "COMMENT:\" \\n\" ";

foreach ($this->DS as $KEY=>$VAL) {
    if(preg_match('/^State_/', $VAL['NAME'])) {
        $def[$i] .= "DEF:var${KEY}=${VAL['RRDFILE']}:${DS[$VAL['DS']]}:AVERAGE ";
        $def[$i] .= "AREA:var${KEY}".rrd::color($KEY).":\"".rrd::cut(substr($VAL['NAME'],6),16) ."\":STACK ";
        $def[$i] .= "GPRINT:var${KEY}:LAST:\"Last %5.1lf\" ";
        $def[$i] .= "GPRINT:var${KEY}:MAX:\"Max %5.1lf\" ";
        $def[$i] .= "GPRINT:var${KEY}:AVERAGE:\"Average %5.1lf\" ";
        $def[$i] .= "COMMENT:\"\\n\" ";
   }
}

# get UsedSlots
$def[$i] .= "CDEF:usedslots=varTotal,varOpen,- ";
$def[$i] .= "LINE:usedslots#ffffff:\"UsedSlots \t    \" ";
$def[$i] .= "GPRINT:usedslots:LAST:\"Last %5.1lf\" ";
$def[$i] .= "GPRINT:usedslots:MAX:\"Max %5.1lf\" ";
$def[$i] .= "GPRINT:usedslots:AVERAGE:\"Average %5.1lf\\n\" ";
# $def[$i] .= "GPRINT:usedslots:LAST:\"Used            %5.0lf of ${total_slots}\" ";
$def[$i] .= "COMMENT:\"\\n\" ";

#
# Requests per Second
#
$i++;
if (isset($RRD["ReqPerSec"])) {
    $def[$i]     = "";
    $opt[$i]     = " --title '$hostname: $servicedesc Requests/sec' -l 0";
	$ds_name[$i] = "Requests/sec";
    $color = '#000000';
    foreach ($this->DS as $KEY=>$VAL) {
        if($VAL['NAME'] == 'ReqPerSec') {
            $def[$i]    .= rrd::def     ("var".$KEY, $VAL['RRDFILE'], $VAL['DS'], "AVERAGE");
            $def[$i]    .= rrd::line1   ("var".$KEY, $color, rrd::cut($VAL['NAME'],16), 'STACK' );
            $def[$i]    .= rrd::gprint  ("var".$KEY, array("LAST","MAX","AVERAGE"), "%6.1lf/s");
        }
    }
}
#
# Bytes per Second
#
$i++;
if (isset($RRD["BytesPerSec"])) {
    $def[$i]     = "";
    $opt[$i]     = " --title '$hostname: $servicedesc Bytes/sec' -l 0";
	$ds_name[$i] = "Bytes/sec";
    foreach ($this->DS as $KEY=>$VAL) {
        if($VAL['NAME'] == 'BytesPerSec') {
            $def[$i]    .= rrd::def     ("var".$KEY, $VAL['RRDFILE'], $VAL['DS'], "AVERAGE");
            $def[$i]    .= rrd::line1   ("var".$KEY, rrd::color($KEY),rrd::cut($VAL['NAME'],16), 'STACK' );
            $def[$i]    .= rrd::gprint  ("var".$KEY, array("LAST","MAX","AVERAGE"), "%6.1lf %sb/s");
        }
    }
}
#
# all other graphs
#
$i++;
foreach ($this->DS as $KEY=>$VAL) {
	if(!preg_match('/(^State_)|(^ReqPerSec)|(^BytesPerSec)|(^Uptime)/', $VAL['NAME'])) {
		$def[$i]     = "";
		$opt[$i]     = " --title '$hostname: Apache - ".$VAL['NAME']." ' -l 0";
		$ds_name[$i] = $VAL['NAME'];
		$def[$i]    .= rrd::def     ("var".$KEY, $VAL['RRDFILE'], $VAL['DS'], "AVERAGE");
		$def[$i]    .= rrd::line1   ("var".$KEY, rrd::color($KEY),rrd::cut($VAL['NAME'],16), 'STACK' );
		$def[$i]    .= rrd::gprint  ("var".$KEY, array("LAST","MAX","AVERAGE"), "%6.1lf");
		$i++;
	}
}

#
# Uptime Graph
#
$opt[$i]  = "--vertical-label 'Uptime (d)' -l0 --title \"Uptime (time since last reboot)\" ";
$def[$i]  = "";
$def[$i] .= rrd::def("sec", $RRDFILE[1], $DS[1], "MAX");
$ds_name[$i] = $LABEL[1];
$def[$i] .= "CDEF:uptime=sec,86400,/ ";
$def[$i] .= "AREA:uptime#80f000:\"Uptime (days)\" ";
$def[$i] .= "LINE:uptime#408000 ";
$def[$i] .= "GPRINT:uptime:LAST:\"%7.2lf %s LAST\" ";
$def[$i] .= "GPRINT:uptime:MAX:\"%7.2lf %s MAX\" ";

?>
