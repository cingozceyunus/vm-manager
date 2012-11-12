<?php
/*
 * Copyright (C) 2012 FOSS-Group
 *                    Germany
 *                    http://www.foss-group.de
 *                    support@foss-group.de
 *
 * Authors:
 *  Christian Wittkowski <wittkowski@devroom.de>
 *  Axel Westhagen <axel.westhagen@limbas.com>
 *
 * Licensed under the EUPL, Version 1.1 or – as soon they
 * will be approved by the European Commission - subsequent
 * versions of the EUPL (the "Licence");
 * You may not use this work except in compliance with the
 * Licence.
 * You may obtain a copy of the Licence at:
 *
 * http://www.osor.eu/eupl
 *
 * Unless required by applicable law or agreed to in
 * writing, software distributed under the Licence is
 * distributed on an "AS IS" basis,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied.
 * See the Licence for the specific language governing
 * permissions and limitations under the Licence.
 *
 *
 */

/**
 * CPhpLibvirt class file.
 *
 * @author: Christian Wittkowski <wittkowski@devroom.de>
 * @version: 0.4
 */

/**
 * CPhpLibvirt
 *
 * CPhpLibvirt Interface to libvirt.
 *
 * The used design pattern is Singleton. To get the one and
 * only instance of this class call CPhpLibvirt::getInstance().
 *
 * @author Christian Wittkowski <wittkowski@devroom.de>
 * @version $Id: $
 * @since 0.4
 */
class CPhpLibvirt {
	private static $_instance = null;

	public static $VIR_DOMAIN_NOSTATE	= 	0; // no state
	public static $VIR_DOMAIN_RUNNING	= 	1; // the domain is running
	public static $VIR_DOMAIN_BLOCKED	= 	2; // the domain is blocked on resource
	public static $VIR_DOMAIN_PAUSED	= 	3; // the domain is paused by user
	public static $VIR_DOMAIN_SHUTDOWN	= 	4; // the domain is being shut down
	public static $VIR_DOMAIN_SHUTOFF	= 	5; // the domain is shut off
	public static $VIR_DOMAIN_CRASHED	= 	6; // the domain is crashed

	public static $VIR_MIGRATE_LIVE            =   1; // live migration
	public static $VIR_MIGRATE_PEER2PEER       =   2; // direct source -> dest host control channel Note the less-common spelling that we're stuck with: VIR_MIGRATE_TUNNELLED should be VIR_MIGRATE_TUNNELED
	public static $VIR_MIGRATE_TUNNELLED       =   4; // tunnel migration data over libvirtd connection
	public static $VIR_MIGRATE_PERSIST_DEST    =   8; // persist the VM on the destination
	public static $VIR_MIGRATE_UNDEFINE_SOURCE =  16; // undefine the VM on the source
	public static $VIR_MIGRATE_PAUSED          =  32; // pause on remote side
	public static $VIR_MIGRATE_NON_SHARED_DISK =  64; // migration with non-shared storage with full disk copy
	public static $VIR_MIGRATE_NON_SHARED_INC  = 128; // migration with non-shared storage with incremental copy (same base image shared between source and destination)

	private $connections = array();

	private function __construct() {
	}

	/**
	 * Starts a Vm.
	 *
	 * $data is an array with key value pairs.
	 *
	 * @throws CPhpLibvirtException
	 * @param array $data necessary paramters to start a Vm
	 * @return boolean
	 */
	public function startVm($data) {
		$con = $this->getConnection($data['libvirt']);
		Yii::log('startVm: libvirt_domain_create_xml(' . $data['libvirt'] . ', ' . $this->getXML($data) . ')', 'profile', 'phplibvirt');
		return libvirt_domain_create_xml($con, $this->getXML($data));
	}

	public function rebootVm($data) {
		$con = $this->getConnection($data['libvirt']);
		Yii::log('rebootVm: libvirt_domain_lookup_by_name(' . $data['libvirt'] . ', ' . $data['name'] . ')', 'profile', 'phplibvirt');
		$domain = libvirt_domain_lookup_by_name($con, $data['name']);
		Yii::log('rebootVm: libvirt_domain_reboot(' . $data['name'] . ')', 'profile', 'phplibvirt');
		libvirt_domain_reboot($domain);
		return true;
	}

	public function shutdownVm($data) {
		$con = $this->getConnection($data['libvirt']);
		Yii::log('shutdownVm: libvirt_domain_lookup_by_name(' . $data['libvirt'] . ', ' . $data['name'] . ')', 'profile', 'phplibvirt');
		$domain = libvirt_domain_lookup_by_name($con, $data['name']);
		Yii::log('shutdownVm: libvirt_domain_shutdown(' . $data['name'] . ')', 'profile', 'phplibvirt');
		return libvirt_domain_shutdown($domain);
	}

	public function destroyVm($data) {
		$con = $this->getConnection($data['libvirt']);
		Yii::log('destroyVm: libvirt_domain_lookup_by_name(' . $data['libvirt'] . ', ' . $data['name'] . ')', 'profile', 'phplibvirt');
		$domain = libvirt_domain_lookup_by_name($con, $data['name']);
		Yii::log('destroyVm: libvirt_domain_destroy(' . $data['name'] . ')', 'profile', 'phplibvirt');
		return libvirt_domain_destroy($domain);
	}

	public function migrateVm($data) {
//		echo print_r($data, true) . "\n";
		$con = $this->getConnection($data['libvirt']);
		Yii::log('migrateVm: libvirt_domain_lookup_by_name(' . $data['libvirt'] . ', ' . $data['name'] . ')', 'profile', 'phplibvirt');
		$domain = libvirt_domain_lookup_by_name($con, $data['name']);
		$flags = self::$VIR_MIGRATE_LIVE | self::$VIR_MIGRATE_UNDEFINE_SOURCE | self::$VIR_MIGRATE_PEER2PEER | self::$VIR_MIGRATE_TUNNELLED;
		Yii::log('migrateVm: libvirt_domain_migrate_to_uri(' . $data['libvirt'] . ', ' . $data['newlibvirt'] . ', ' . $flags . ', ' . $data['name'] . ',0)', 'profile', 'phplibvirt');
		return libvirt_domain_migrate_to_uri($domain, $data['newlibvirt'], $flags, $data['name'], 0);
	}

	public function getVmStatus($data) {
		$retval = array('active' => false);
		try {
			$con = $this->getConnection($data['libvirt']);
			Yii::log('getVmStatus: libvirt_domain_lookup_by_name(' . $data['libvirt'] . ', ' . $data['name'] . ')', 'profile', 'phplibvirt');
			$domain = @libvirt_domain_lookup_by_name($con, $data['name']);
			if (false !== $domain) {
				//Yii::log('getVmStatus: libvirt_node_get_info (' . $data['libvirt']  . ')', 'profile', 'phplibvirt');
				//$nodeinfo = libvirt_node_get_info($con);
				Yii::log('getVmStatus: libvirt_domain_is_active (' . $data['name']  . ')', 'profile', 'phplibvirt');
				$retval['active'] = libvirt_domain_is_active($domain);
				Yii::log('getVmStatus: libvirt_domain_get_info (' . $data['name'] . ')', 'profile', 'phplibvirt');
				$domaininfo = libvirt_domain_get_info($domain);
				$retval = array_merge($retval, $domaininfo);
				$cpuPercentage = 0;
				$actTime = $this->getUTime();
				if (isset($_SESSION['libvirt'][$data['name']]['lasttime'])) {
					$cpudiff = $retval['cpuUsed'] - $_SESSION['libvirt'][$data['name']]['lastcpu'];
					$timediff = $actTime - $_SESSION['libvirt'][$data['name']]['lasttime'];
					$cpuPercentage = number_format(abs(100 * $cpudiff / ($timediff * $retval['nrVirtCpu'] * 1000000000.0)), 2);

					//error_log($retval['cpuTime'] . ', ' . $cpudiff . '; ' . $timediff . ', ' . $cpuPercentage);
				}
				$_SESSION['libvirt'][$data['name']]['lasttime'] = $actTime;
				$_SESSION['libvirt'][$data['name']]['lastcpu'] = $retval['cpuUsed'];
				$retval['actTime'] = $actTime;
				$retval['cpuTimeOrig'] = $retval['cpuUsed'];
				$retval['cpuTime'] = round($cpuPercentage);
			}
			else {
				// nothing to do, active is already false
			}
		}
		catch(Exception $e) {
			Yii::log('getVmStatus: Exception: ' . $e->getTraceAsString(), 'profile', 'phplibvirt');
			//echo '<pre>Exception: ' . print_r($e, true) . '</pre>';
			if (VIR_ERR_NO_DOMAIN != $e->getCode()) {
				throw $e;
			}
			// nothing to do, active is already false
		}
		Yii::log('getVmStatus: return: ' . print_r($retval, true), 'profile', 'phplibvirt');
		return $retval;
	}

	public function getLastError() {
		$retval = libvirt_get_last_error();
		Yii::log('getVmStatus: libvirt_get_last_error (): ' . $retval, 'profile', 'phplibvirt');
		return $retval;
	}

	private static $xmlTemplate = '
<domain type=\"{$data[\'sstType\']}\">
	<name>{$data[\'sstName\']}</name>
	<uuid>{$data[\'sstUuid\']}</uuid>
	<memory>{$data[\'sstMemory\']}</memory>
	<vcpu>{$data[\'sstVCPU\']}</vcpu>
	<os>
		<type arch=\"{$data[\'sstOSArchitecture\']}\" machine=\"{$data[\'sstOSMachine\']}\">{$data[\'sstOSType\']}</type>
		<boot dev=\"{$data[\'sstOSBootDevice\']}\"/>
	</os>
	<features>
		{$features}
	</features>
	<clock offset=\"{$data[\'sstClockOffset\']}\"/>
	<on_poweroff>{$data[\'sstOnPowerOff\']}</on_poweroff>
	<on_reboot>{$data[\'sstOnReboot\']}</on_reboot>
	<on_crash>{$data[\'sstOnCrash\']}</on_crash>
	<devices>
		<emulator>{$data[\'devices\'][\'sstEmulator\']}</emulator>
		<graphics type=\"spice\" port=\"{$data[\'devices\'][\'graphics\'][\'spiceport\']}\" tlsPort=\"0\" autoport=\"no\" listen=\"0.0.0.0\" passwd=\"{$data[\'devices\'][\'graphics\'][\'spicepassword\']}\">
{$spiceparams}		</graphics>
		<channel type=\"spicevmc\">
			<target type=\"virtio\" name=\"com.redhat.spice.0\"/>
		</channel>
		<video>
			<model type=\"qxl\" vram=\"65536\" heads=\"1\"/>
		</video>
		<input type=\"tablet\" bus=\"usb\"/>
		<sound model=\"ac97\"/>
{$devices}
	</devices>
</domain>
';

	public function getXML($data) {
		$data['sstMemory'] = floor($data['sstMemory'] / 1024);
		$features = '';
		foreach($data['sstFeature'] as $feature) {
			$features .= "<$feature/>";
		}
		$devices = '';
		foreach($data['devices']['disks'] as $disk) {
			$devices .= '		<disk type="' . $disk['sstType'] . '" device="' . $disk['sstDevice'] . '">' . "\n";
			if (isset($disk['sstDriverName']) && isset($disk['sstDriverType'])) {
				$devices .= '			<driver name="' . $disk['sstDriverName'] . '" type="' . $disk['sstDriverType'] .
					(isset($disk['sstDriverCache']) && '' != $disk['sstDriverCache'] ? '" cache="' . $disk['sstDriverCache'] : '') . '"/>' . "\n";
			}
			$devices .= '			<source file="' . $disk['sstSourceFile'] . '"/>' . "\n";
			$devices .= '			<target dev="' . $disk['sstDisk'] . '" bus="' . $disk['sstTargetBus'] . '"/>' . "\n";
			if (isset($disk['sstReadonly']) && 'TRUE' == $disk['sstReadonly']) {
				$devices .= '			<readOnly/>' . "\n";
			}
			$devices .= '		</disk>' . "\n";
		}
		foreach($data['devices']['interfaces'] as $interface) {
			$devices .= '		<interface type="' . $interface['sstType'] . '">' . "\n";
			$devices .= '			<source bridge="' . $interface['sstSourceBridge'] . '"/>' . "\n";
			$devices .= '			<mac address="' . $interface['sstMacAddress'] . '"/>' . "\n";
			$devices .= '			<model type="' . $interface['sstModelType'] . '"/>' . "\n";
			$devices .= '		</interface>' . "\n";
		}
		if ($data['devices']['graphics']['spiceacceleration']) {
			$spiceparams = '			<image compression="off"/><jpeg compression="never"/><zlib compression="never"/><streaming mode="off"/>' . "\n";
		}

		$template = CPhpLibvirt::$xmlTemplate;
		if (false === eval("\$retval = \"$template\";")) {
			echo "EVAL ERROR!";
		}
		return $retval;
	}

	public function generateUUID() {
		return sprintf('%08x-%04x-4%03x-%04x-%04x%04x%04x',
			0xFFFFFFFF & time(),
			mt_rand(0, 0xFFFF),
			mt_rand(0, 0x0FFF),
			mt_rand(0, 0xFFFF) & 0xBFFF,
			mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
		);
	}

	public function generateMacAddress() {
		return sprintf('%02x:%02x:%02x:%02x:%02x:%02x', 0x52, 0x54, 0x00, rand(0, 0xFF), rand(0, 0xFF), rand(0, 0xFF));
	}

	public function generateSpicePassword() {
		$dummy	= array_merge(range('0', '9'), range('a', 'z'), range('A', 'Z')/*, array('#','&','@','$','_','%','?','+')*/);

		mt_srand((double)microtime() * 1000000);

		for ($i = 1; $i <= (count($dummy)*2); $i++)
		{
			$swap = mt_rand(0, count($dummy) - 1);
			$tmp = $dummy[$swap];
			$dummy[$swap] = $dummy[0];
			$dummy[0] = $tmp;
		}

		return htmlentities(substr(implode('',$dummy), 0, 12));
	}

	public function nextSpicePort($node) {
		$port = 5899;
		$server = CLdapServer::getInstance();
		$result = $server->search('ou=virtualization,ou=services', '(&(objectClass=sstSpice)(sstNode=' . $node . '))', array('sstSpicePort'));
		for($i=0; $i<$result['count']; $i++) {
			$port = max($port, $result[$i]['sstspiceport'][0]);
		}

		return $port + 1;
	}

	private static $xmlPoolTemplate = '
<pool type=\"dir\">
	<name>{$data[\'name\']}</name>
	<uuid>{$data[\'uuid\']}</uuid>
	<target>
		<path>{$data[\'path\']}</path>
	</target>
</pool>
';

	public function getStoragePoolXML($data) {
		$template = CPhpLibvirt::$xmlPoolTemplate;
		if (false === eval("\$retval = \"$template\";")) {
			echo "EVAL ERROR!";
		}
		return $retval;
	}

	public function createStoragePool($host, $basepath) {
		$data = array();
		$data['uuid'] = $this->generateUUID();
		$data['name'] = $data['uuid'];

		$data['path'] = $basepath . '/' . $data['uuid'];
		$xml = $this->getStoragePoolXML($data);
		Yii::log('createStoragePool: libvirt_storagepool_define_xml(' . $host . ', ' . $xml . ')', 'profile', 'phplibvirt');

		$retval = false;
		$con = $this->getConnection($host);
		if (!is_null($con)) {
			Yii::log('createStoragePool: connection ok', 'profile', 'phplibvirt');
			$pool = libvirt_storagepool_define_xml($con, $xml, 0);
			if (!is_null($pool)) {
				Yii::log('createStoragePool: pool defined', 'profile', 'phplibvirt');
				if (libvirt_storagepool_build($pool)) {
					Yii::log('createStoragePool: pool build', 'profile', 'phplibvirt');
					if (libvirt_storagepool_create($pool)) {
						Yii::log('createStoragePool: pool created', 'profile', 'phplibvirt');
						if (libvirt_storagepool_set_autostart($pool, true)) {
							Yii::log('createStoragePool: pool set autostart', 'profile', 'phplibvirt');
							$retval = true;
							Yii::log('createStoragePool: pool created', 'profile', 'phplibvirt');
						}
					}
				}
			}
		}
		if (!$retval) {
			Yii::log('createStoragePool: error: ' . libvirt_get_last_error(), 'profile', 'phplibvirt');
		}
		return $retval;
	}

	public function deleteStoragePool($host, $basepath, $uuid) {
		Yii::log('deleteStoragePool: ' . $host . ', ' . $basepath . ', ' . $uuid, 'profile', 'phplibvirt');

		$con = $this->getConnection($host);
		$pool = libvirt_storagepool_lookup_by_uuid_string($con, $uuid);
		$retval = false;
		if (!is_null($pool)) {
			if (0 == libvirt_storagepool_set_autostart($pool, 0)) { // make sure the pool is not autostarted again, just in case we get interrupted
				if (libvirt_storagepool_destroy($pool)) { // stops the pool (but it is still defined)
					$this->rmdir($basepath . '/' . $data['uuid']);
					if (0 == libvirt_storagepool_undefine($pool)) {
						$retval = true;
						Yii::log('deleteStoragePool: pool created', 'profile', 'phplibvirt');
					}
				}
			}
		}
		return $retval;
	}

	private static $xmlVolumeTemplate = '
<volume>
	<name>{$data[\'name\']}</name>
	<allocation>0</allocation>
	<capacity>{$data[\'capacity\']}</capacity>
	<target>
		<format type=\"qcow2\"/>
		<permissions>
			<owner>0</owner>
			<group>3000</group>
			<mode>0660</mode>
		</permissions>
	</target>
</volume>
';

	public function getVolumeXML($data) {
		$template = CPhpLibvirt::$xmlVolumeTemplate;
		if (false === eval("\$retval = \"$template\";")) {
			echo "EVAL ERROR!";
		}
		return $retval;

	}

	public function createVolumeFile($templatesdir, $pooluuid, $host, $capacity) {
		$volumename = $this->generateUUID();

		$path = $templatesdir;
		if (!file_exists($path)) {
			mkdir($path, 0770);
		}
		$con = $this->getConnection($host);
		Yii::log('createVolumeFile: ' . $host . ', ' . $path . ',' . $pooluuid, 'profile', 'phplibvirt');
		$pool = libvirt_storagepool_lookup_by_uuid_string($con, $pooluuid);
		$retval = false;
		if (!is_null($pool)) {
			Yii::log('createVolumeFile: pool found', 'profile', 'phplibvirt');
			$data['name'] = $volumename . '.qcow2';
			$data['capacity'] = $capacity;
			Yii::log('createVolumeFile: ' . $this->getVolumeXML($data), 'profile', 'phplibvirt');
			$volume = libvirt_storagevolume_create_xml($pool, $this->getVolumeXML($data));
			if (!is_null($volume)) {
				Yii::log('createVolumeFile: volume created', 'profile', 'phplibvirt');
				$sourcefile = $path . '/' . $volumename . '.qcow2';

				$retval = array('VolumeName' => $volumename, 'SourceFile' => $sourcefile);
			}
		}
		if (false === $retval) {
			Yii::log('createVolumeFile: error: ' . libvirt_get_last_error(), 'profile', 'phplibvirt');
		}
		return $retval;
	}

	public function copyVolumeFile($persistentdir, $disk) {
		$volumename = $this->generateUUID();

		$path = $persistentdir;
		if (!file_exists($path)) {
			mkdir($path, 0770);
		}
		$sourcefile = $path . '/' . $volumename . '.qcow2';
		$pidfile = $path . '/' . $volumename . '.pid';
		Yii::log('copyVolumeFile: ' . $disk->sstSourceFile . ' => ' . $sourcefile, 'profile', 'phplibvirt');

		//exec(sprintf("cp %s %s > /dev/null 2>&1 & echo $! >> %s", $disk->sstSourceFile, $sourcefile, $pidfile));
		//$cmd = sprintf("cp %s %s > /dev/null 2>&1 & echo $! >> %s", $disk->sstSourceFile, $sourcefile, $sourcefile, $pidfile);
		//$cmd = sprintf('{ echo $$ > "%s" ; cp "%s" "%s" > /dev/null 2>&1 && chmod 660 "%s" ; echo $? > "%s" ; } &', $pidfile, $disk->sstSourceFile, $sourcefile, $sourcefile, $returnvaluefile);
		$cmd = sprintf('{ echo $$ > "%s" ; cp "%s" "%s" > /dev/null 2>&1 && chmod 660 "%s" ; } &', $pidfile, $disk->sstSourceFile, $sourcefile, $sourcefile);
		//$cmd = escapeshellcmd($cmd);
		error_log($cmd);
		exec($cmd);
		//copy($disk->sstSourceFile, $sourcefile);
		sleep(2);
		$pid = file($pidfile);
		unlink($pidfile);

		return array('VolumeName' => $volumename, 'SourceFile' => $sourcefile, 'pid' => (int) rtrim($pid[0]));
	}

	public function deleteVolumeFile($file) {
		if (is_file($file)) {
			if(!is_writeable($file)) {
				chmod($file,0666);
			}
			return unlink($file);
		}
		return true;
	}

	private static $xmlBackingStoreVolumeTemplate = '
<volume>
	<name>{$data[\'name\']}</name>
	<allocation>0</allocation>
	<capacity>{$data[\'capacity\']}</capacity>
	<backingStore>
		<path>{$data[\'goldenimagepath\']}</path>
		<format type=\"qcow2\"/>
	</backingStore>
	<target>
		<format type=\"qcow2\"/>
		<permissions>
			<owner>0</owner>
			<group>3000</group>
			<mode>0660</mode>
		</permissions>
	</target>
</volume>';

	public function getBackingStoreVolumeXML($data) {
		$template = CPhpLibvirt::$xmlBackingStoreVolumeTemplate;
		if (false === eval("\$retval = \"$template\";")) {
			echo "EVAL ERROR!";
		}
		return $retval;
	}

	public function createBackingStoreVolumeFile($templatesdir, $pooluuid, $goldenimagepath, $host, $capacity) {
		$volumename = $this->generateUUID();
		$path = $templatesdir;

		Yii::log('createBackingStoreVolumeFile: ' . $host . ', ' . $path . ',' . $pooluuid . ',' . $goldenimagepath, 'profile', 'phplibvirt');

		$retval = false;
		$con = $this->getConnection($host);
		if (!is_null($con)) {
			Yii::log('createBackingStoreVolumeFile: connection ok', 'profile', 'phplibvirt');
			$pool = libvirt_storagepool_lookup_by_uuid_string($con, $pooluuid);
			if (!is_null($pool)) {
				Yii::log('createBackingStoreVolumeFile: pool found', 'profile', 'phplibvirt');
//				$goldenimagevolume = libvirt_storagevolume_lookup_by_name($pooluuid, $goldenuuid);
//				if (!is_null($goldenimagevolume)) {
//					$goldenimagepath = libvirt_storagevolume_get_path($goldenimagevolume);
//					if (!is_null($goldenimagepath)) {
						$data['name'] = $volumename . '.qcow2';
						$data['capacity'] = $capacity;
						$data['goldenimagepath'] = $goldenimagepath; //$goldenimagepath;
						Yii::log('createBackingStoreVolumeFile: ' . $this->getBackingStoreVolumeXML($data), 'profile', 'phplibvirt');
						$volume = libvirt_storagevolume_create_xml($pool, $this->getBackingStoreVolumeXML($data));
						if (!is_null($volume)) {
							Yii::log('createBackingStoreVolumeFile: volume created', 'profile', 'phplibvirt');
							$sourcefile = $path . '/' . $volumename . '.qcow2';

							$retval = array('VolumeName' => $volumename, 'SourceFile' => $sourcefile);
						}
//					}
//				}
			}
		}
		if (false === $retval) {
			Yii::log('createBackingStoreVolumeFile: error: ' . libvirt_get_last_error(), 'profile', 'phplibvirt');
		}
		return $retval;
	}

	public function copyIsoFile($source, $dest) {
		$pidfile = $dest . '.pid';
		Yii::log('copyIsoFile: ' . $source . ' => ' . $dest, 'profile', 'phplibvirt');
		exec(sprintf("cp %s %s > /dev/null 2>&1 & echo $! >> %s", $source, $dest, $pidfile));
		//copy($disk->sstSourceFile, $sourcefile);
		sleep(2);
		$pid = file($pidfile);
		unlink($pidfile);

		return array('pid' => (int) rtrim($pid[0]));
	}

	public function deleteIsoFile($file) {
		if (is_file($file)) {
			if(!is_writeable($file)) {
				chmod($file,0666);
			}
			return unlink($file);
		}
		return true;
	}

	public function checkPid($pid){
		try{
			$result = shell_exec(sprintf("ps %d", $pid));
			Yii::log('checkPid: ' . $pid . ': ' . print_r($result, true), 'profile', 'phplibvirt');
			if(count(preg_split("/\n/", $result)) > 2) {
				return true;
			}
		}catch(Exception $e){}

		return false;
	}

	protected function rmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != '.' && $object != '..') {
					if (filetype($dir . '/' . $object) == 'dir') {
						//$this->rmdir($dir . '/' . $object);
					}
					else {
						unlink($dir . '/' . $object);
					}
				}
			}
			//reset($objects);
			rmdir($dir);
		}
	}

	protected function getUTime() {
		list($usec, $sec) = explode(" ",microtime());
		return ((float)$usec + (float)$sec);
	}

	protected function getConnection($connection) {
		if (!isset($this->connections[$connection])) {
			$this->connections[$connection] = libvirt_connect($connection, false);
			Yii::log('getConnection: libvirt_connect (' . $connection . '): ' . $this->connections[$connection], 'profile', 'phplibvirt');
			if (false === $this->connections[$connection]) {
				Yii::log('getConnection: libvirt_connect failed!', 'profile', 'phplibvirt');
			}
		}
		return $this->connections[$connection];
	}

	/*
	 * get singleton instance of CPhpLibvirt
	 */
	public static function getInstance() {
		if (is_null(self::$_instance)) {
			if (isset(Yii::app()->params['useLibvirtDummy']) && Yii::app()->params['useLibvirtDummy']) {
				self::$_instance = new CPhpLibvirtDummy();
			}
			else {
				self::$_instance = new CPhpLibvirt();
			}
		}
		return self::$_instance;
	}

	/*
	 * Don't allow clone from outside
	 */
	private function __clone() {}
}

class CPhpLibvirtException extends CException {
}