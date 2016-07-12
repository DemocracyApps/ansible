<?php

// This script backs up any EBS volumes attached to the EC2 instance on which it is run that
// have a backup=true tag. The script identifies the volumes, snapshots them, and takes care
// of any snapshot management required.

function logError ($file, $type, $msg) {
  echo date("M d H:i:s  ") . "[$type] $file: $msg" . PHP_EOL;
  if ($type == "fatal") exit(1);
}

$debug = false;

/*
 * Get this EC2 VM's instance ID, then use the AWS CLI to get metadata for the VM,
 * a list of all snapshots for this account, and a list of volumes attached to the VM.
 */

$url = 'http://169.254.169.254/latest/meta-data/instance-id'; // Standard AWS IP for instance queries

// Open the Curl session. Don't return HTTP headers. Do return the contents of the call
$session = curl_init($url);
curl_setopt($session, CURLOPT_HEADER, false);
curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

// Make the call
$instanceId = curl_exec($session);
curl_close($session);

if ($debug) echo "Instance ID is $instanceId" . PHP_EOL;

// Now use the AWS CLI to get the instance metadata
$output = [];
$retval = 0;

exec ("/usr/local/bin/aws ec2 describe-instances --instance-ids $instanceId", $output, $retval);
$meta = json_decode(implode($output), true);
if ($debug) echo "META: " . json_encode($meta) . PHP_EOL;

//$meta = json_decode(shell_exec ("/usr/local/bin/aws ec2 describe-instances --instance-ids $instanceId"), true);

if (! isset($meta)) logError ('ebs_backup', "fatal", "No metadata returned for instance id $instanceId");

$instanceName = getTag($meta['Reservations'][0]['Instances'][0], 'Name');

$blockDevices = $meta['Reservations'][0]['Instances'][0]['BlockDeviceMappings'];
if (! isset($blockDevices)) {
  logError ('ebs_backup', 'fatal', "Unable to access block devices");
}
// Get a list of all the snapshots
$snaps = json_decode(shell_exec("/usr/local/bin/aws ec2 describe-snapshots --owner-ids {{ aws_owner_id}}"), true);
$snaps = $snaps['Snapshots'];

/*
 * Now we need to loop through the volumes and see if any need backing up.
 */

foreach ($blockDevices as $device) {
  if ($debug) echo "Here's the device: " . $device['DeviceName'] . PHP_EOL;
  $volId = $device['Ebs']['VolumeId'];
  $devMeta = json_decode(shell_exec("/usr/local/bin/aws ec2 describe-volumes --volume-ids $volId"), true);
//  echo "Checking volume: " . json_encode($devMeta) . PHP_EOL;
  $backupTag = getTag($devMeta['Volumes'][0], 'backup');
  //$backupTag = 'false';
  if (isset($backupTag) && strtolower($backupTag) == 'true') {
    /*
     * Yes! We have a backup. We'll call the backup routine.
     * Now let's figure out where we are in the
     * backup scheme, rotate any snapshots that are due for promotion,
     * or delete any snapshot that we should replace.
     */
    doBackupAction($instanceName . $device['DeviceName'], time(), $snaps, $volId);

  }
  else if ($debug) {
    echo "No backup on device $volId" . PHP_EOL;
  }
}


function getTag ($obj, $key) {
  $value = null;
  if (isset($obj) && isset($obj['Tags'])) {
    foreach($obj['Tags'] as $tag) {
      if ($tag['Key'] == $key) {
        $value = $tag['Value'];
      }
    }
  }
  return $value;
}

function deleteMatchingSnapshot($name, $snaps) {
  // Delete any snapshot with the name we're going to use
  foreach($snaps as $snap) {
    $nm = getTag($snap, 'Name');
    echo "Check snap " . $snap['SnapshotId'] . " with Nametag $nm". PHP_EOL;
    if ($nm == $name) {
      $sId = $snap['SnapshotId'];
      $ret = json_decode(shell_exec("/usr/local/bin/aws ec2 delete-snapshot --snapshot-id $sId"),true);
      echo "  DELETE Snap " . $snap['SnapshotId'] . PHP_EOL;
//      echo " Return value is " . json_encode($ret) . PHP_EOL;
    }
  }
}

function doSnapshot($snapName, $volId, $now) {
  echo "Doing backup on device $volId" . PHP_EOL;
  $description = date("Y-m-d") . ": EBS Backup of $volId";;
  $snapshot = json_decode(
    shell_exec('/usr/local/bin/aws ec2 create-snapshot --volume-id ' . $volId . ' --description "'.$description . '"'),
    true);
  if (!isset($snapshot)) logError('ebs_backup', 'fatal', 'Snapshot failed');
  else {
    $snapshotId = $snapshot['SnapshotId'];
    shell_exec("/usr/local/bin/aws ec2 create-tags --resources $snapshotId --tags Key=Name,Value=$snapName");
  }
  return $snapshotId;
}

function doBackupAction($name, $now, $snaps, $volId) {
  $changeDate = false;
  if ($changeDate) {
    $tmp = date("Y-m-d", $now);
    $now = strtotime($tmp . ' + 10 days');
  }
  echo "New date is " . date("Y-m-d", $now) . PHP_EOL;
  /*
   * For now, we'll just have a simple scheme. Given a base name (for the EC2 instance) of NAME:
   *
   * - Daily backups in NAME_Backup_Day_Sun, NAME_Backup_Day_Mon, ..., NAME_Backup_Day_Sat
   * - Weekly backup (on days that are divisible by 7, so max of 4) in NAME_Backup_Week_1, ..., NAME_Backup_Week_4
   * - Monthly backup on the first of each month in NAME_Month_Backup_01, ... NAME_Backup_Month_12
   */
  $dayOfMonth = date("d", $now);
  $dayOfWeek  = date("D", $now);
  $monthOfYear = date("m", $now);

  echo "Day of month: $dayOfMonth, day of week: $dayOfWeek, month of year: $monthOfYear" . PHP_EOL;

  $backupName = $name . "_Backup_Day_$dayOfWeek";
  echo "The daily name is " . $backupName . PHP_EOL;
  deleteMatchingSnapshot($backupName, $snaps);
  $snapshotId = doSnapshot($backupName, $volId, $now);
  shell_exec("/usr/local/bin/aws ec2 wait snapshot-completed --snapshot-ids $snapshotId");

  if ($dayOfMonth%7 ==0) {
    $week = $dayOfMonth/7;
    $backupName = $name . "_Backup_Week_$week";
    echo "The weekly name is " . $backupName . PHP_EOL;
    deleteMatchingSnapshot($backupName, $snaps);
    doSnapshot($backupName, $volId, $now);
  }
  if ($dayOfMonth == 1) {
    $backupName = $name . "_Backup_Month_" . $monthOfYear;
    echo "The monthly name is " . $backupName . PHP_EOL;
    deleteMatchingSnapshot($backupName, $snaps);
    doSnapshot($backupName, $volId, $now);
  }
}