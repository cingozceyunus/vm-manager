<?php
/*
 * Copyright (C) 2006 - 2014 FOSS-Group
 *                    Germany
 *                    http://www.foss-group.de
 *                    support@foss-group.de
 *
 * Authors:
 *  Christian Wittkowski <wittkowski@devroom.de>
 *
 * Licensed under the EUPL, Version 1.1 or higher - as soon they
 * will be approved by the European Commission - subsequent
 * versions of the EUPL (the "Licence");
 * You may not use this work except in compliance with the
 * Licence.
 * You may obtain a copy of the Licence at:
 *
 * https://joinup.ec.europa.eu/software/page/eupl
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
 * VmPoolController class file.
 *
 * @author: Christian Wittkowski <wittkowski@devroom.de>
 * @version: 1.0
 */

class VmPoolController extends Controller
{
	public function beforeAction($action) {
		$retval = parent::beforeAction($action);
		if ($retval) {
			$this->activesubmenu = 'vmpool';

			if ('update' === $action->id || 'create' === $action->id) {
				$cs=Yii::app()->clientScript;
				$cs->scriptMap['jquery.js'] = false;
				$cs->scriptMap['jquery.min.js'] = false;
				
				Yii::app()->getclientScript()->registerCssFile($this->cssBase . '/jquery/osbd/jquery-ui.custom.css');
				Yii::app()->clientScript->registerScriptFile('jquerynew.js', CClientScript::POS_BEGIN);
				Yii::app()->clientScript->registerScriptFile('jqueryuinew.js', CClientScript::POS_BEGIN);
			}
		}
		return $retval;
	}

	protected function createMenu() {
		parent::createMenu();
		$action = '';
		if (!is_null($this->action)) {
			$action = $this->action->id;
		}
		if ('view' == $action) {
			$this->submenu['vmpool']['items']['vmpool']['items'][] = array(
				'label' => Yii::t('menu', 'View'),
				'itemOptions' => array('title' => Yii::t('menu', 'VM Pool View Tooltip')),
				'active' => true,
			);
		}
	}

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',
				'actions'=>array('index', 'getVmPools'),
				'users'=>array('@'),
				'expression'=>'Yii::app()->user->hasRight(\'vmPool\', COsbdUser::$RIGHT_ACTION_ACCESS, COsbdUser::$RIGHT_VALUE_ALL)'
			),
			array('allow',
				'actions'=>array('create', 'getDynData'),
				'users'=>array('@'),
				'expression'=>'Yii::app()->user->hasRight(\'vmPool\', COsbdUser::$RIGHT_ACTION_CREATE, COsbdUser::$RIGHT_VALUE_ALL)'
			),
			array('allow',
				'actions'=>array('update', 'getDynData'),
				'users'=>array('@'),
				'expression'=>'Yii::app()->user->hasRight(\'vmPool\', COsbdUser::$RIGHT_ACTION_EDIT, COsbdUser::$RIGHT_VALUE_ALL)'
			),
			array('allow',
				'actions'=>array('delete'),
				'users'=>array('@'),
				'expression'=>'Yii::app()->user->hasRight(\'vmPool\', COsbdUser::$RIGHT_ACTION_DELETE, COsbdUser::$RIGHT_VALUE_ALL)'
			),
			array('allow',
				'actions'=>array('getUserGui', 'saveUserAssign'),
				'users'=>array('@'),
				'expression'=>'Yii::app()->user->hasRight(\'user\', COsbdUser::$RIGHT_ACTION_MANAGE, COsbdUser::$RIGHT_VALUE_ALL)'
			),
			array('allow',
				'actions'=>array('getGroupGui', 'saveGroupAssign'),
				'users'=>array('@'),
				'expression'=>'Yii::app()->user->hasRight(\'group\', COsbdUser::$RIGHT_ACTION_MANAGE, COsbdUser::$RIGHT_VALUE_ALL)'
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	public function actionIndex() {
		$model=new LdapVmPool('search');
		if(isset($_GET['LdapVmPool'])) {
			$model->attributes = $_GET['LdapVmPool'];
		}
		$this->render('index', array(
			'model' => $model,
		));
	}

	public function actionView() {
		if(isset($_GET['dn']))
			$model = CLdapRecord::model('LdapVmPool')->findbyDn($_GET['dn']);
		else if (isset($_GET['node']))
			$model = CLdapRecord::model('LdapVmPool')->findByAttributes(array('attr'=>array('sstNode' => $_GET['vmpool'])));
		if($model === null)
			throw new CHttpException(404,'The requested page does not exist.');
		$this->render('view',array(
			'model' => $model,
		));
	}

	/**
	 * Performs the AJAX validation.
	 * @param CModel the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='vmpool-form')
		{
			$this->disableWebLogRoutes();
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}

	/**
	 * Ajax functions for JqGrid
	 */
	public function actionGetVmPools() {
		$this->disableWebLogRoutes();
		$page = $_GET['page'];

		// get how many rows we want to have into the grid - rowNum parameter in the grid
		$limit = $_GET['rows'];

		// get index row - i.e. user click to sort. At first time sortname parameter -
		// after that the index from colModel
		$sidx = $_GET['sidx'];

		// sorting order - at first time sortorder
		$sord = $_GET['sord'];

		// if we not pass at first time index use the first column for the index or what you want
		if(!$sidx) $sidx = 1;

		$attr = array();
		if (isset($_GET['sstDisplayName'])) {
			$attr['sstDisplayName'] = '*' . $_GET['sstDisplayName'] . '*';
		}
		if (Yii::app()->user->hasRight('vmPool', COsbdUser::$RIGHT_ACTION_VIEW, COsbdUser::$RIGHT_VALUE_ALL)) {
			$pools = LdapVmPool::model()->findAll(array('attr' => $attr));
		}
		else if (Yii::app()->user->hasRight('vmPool', COsbdUser::$RIGHT_ACTION_VIEW, COsbdUser::$RIGHT_VALUE_OWNER)) {
			$pools = LdapVmPool::getAssignedPools();
			$pools = array_values($pools);
		}
		else {
			$pools = array();
		}
		$count = count($pools);
		$total_pages = ceil($count / $limit);

		$s = '<?xml version="1.0" encoding="utf-8"?>';
		$s .=  '<rows>';
		$s .= '<page>' . $page . '</page>';
		$s .= '<total>' . $total_pages . '</total>';
		$s .= '<records>' . $count . '</records>';

		$start = $limit * ($page - 1);
		$start = $start > $count ? 0 : $start;
		$end = $start + $limit;
		$end = $end > $count ? $count : $end;
		for ($i=$start; $i<$end; $i++) {
			$pool = $pools[$i];
			$hasVms = 0 < count($pool->vms);
			$storagepooldns = '';
			$storagepools = '';
			//echo '<pre>StoragePools ' . print_r($pool->storagepools, true) . '</pre>';
			foreach($pool->storagepools as $tmppool) {
				$storagepool = CLdapRecord::model('LdapStoragePool')->findByAttributes(array('attr'=>array('sstStoragePool'=>$tmppool->ou)));
				//echo '<pre>' . print_r($storagepool, true) . '</pre>';
				$storagepooldns .= $storagepool->dn;
				$storagepools .= $storagepool->sstDisplayName;
				break;
			}
			$nodedns = '';
			$nodes = '';
			//echo '<pre>StoragePools ' . print_r($pool->storagepools, true) . '</pre>';
			foreach($pool->nodes as $tmpnode) {
				$node = CLdapRecord::model('LdapNode')->findByAttributes(array('attr'=>array('sstNode'=>$tmpnode->ou)));
				//echo '<pre>' . print_r($storagepool, true) . '</pre>';
				if ('' != $nodedns) {
					$nodedns .= '|';
				}
				$nodedns .= $node->dn;
				if ('' != $nodes) {
					$nodes .= '|';
				}
				$nodes .= $node->sstNode;
			}
			$s .= '<row id="' . $i . '">';
			$s .= '<cell>'. ($i+1) ."</cell>\n";
			$s .= '<cell>' . ($hasVms ? 'true' : 'false') . "</cell>\n";
			$s .= '<cell>'. $pool->dn ."</cell>\n";
			$s .= '<cell>'. $pool->sstVirtualMachinePoolType ."</cell>\n";
			$s .= '<cell>'. $pool->sstDisplayName ."</cell>\n";
			$s .= '<cell>'. $pool->description ."</cell>\n";
			$s .= '<cell>'. $nodedns ."</cell>\n";
			$s .= '<cell>'. $nodes ."</cell>\n";
			$s .= '<cell>'. $storagepooldns ."</cell>\n";
			$s .= '<cell>'. $storagepools ."</cell>\n";
			$s .= "<cell></cell>\n";
			$s .= "</row>\n";
		}
		$s .= '</rows>';

		header('Content-Type: application/xml');
		header('Content-Length: ' . strlen($s));
		echo $s;
	}

	public function actionGetDynData() {
		$this->disableWebLogRoutes();
		$type = $_GET['type'];
		$retval = array();
		$retval['type'] = $type;
		$storagepools = CLdapRecord::model('LdapStoragePool')->findAll(array('attr'=>array('sstStoragePoolType'=>$type)));
		$retval['storagepools'] = $this->createDropdownFromLdapRecords($storagepools, 'sstStoragePool', 'sstDisplayName');
		$retval['ranges'] = $this->getRangesByType($retval['type'], array());

		$config = CLdapRecord::model('LdapVmPoolDefinition')->findByAttributes(array('attr'=>array('ou'=>$type)));
		if ($config->hasAttribute('sstBrokerMaximalNumberOfVirtualMachines')) {
			$retval['brokerMin'] = $config->sstBrokerMinimalNumberOfVirtualMachines;
			$retval['brokerMax'] = $config->sstBrokerMaximalNumberOfVirtualMachines;
			$retval['brokerPreStart'] = $config->sstBrokerPreStartNumberOfVirtualMachines;
		}
		if ($config->hasAttribute('sstBrokerPreStartInterval')) {
			$retval['brokerPreStartInterval'] = $config->sstBrokerPreStartInterval;
		}
		if ($config->hasAttribute('sstNumberOfScreens')) {
			$retval['screens'] = $config->sstNumberOfScreens;
		}
		
		$this->sendJsonAnswer($retval);
	}

	public function actionGetUserGui() {
		$this->disableWebLogRoutes();
		$uarray = array();
		$users = LdapUser::model()->findAll(array('attr'=>array()));
		foreach ($users as $user) {
			$uarray[$user->uid] = array('name' => $user->getName() . ($user->isAdmin() ? ' (Admin)' : ' (User)') . ($user->isForeign() ? '(E)' : '') );
			if ($user->isAssignedToVmPool($_GET['dn'])) {
				$uarray[$user->uid]['selected'] = true;
			}
		}
		$vmpool = CLdapRecord::model('LdapVmPool')->findByDn($_GET['dn']);
		ob_start();
		echo '<div class="ui-widget-header ui-corner-all" style="padding: 0.4em 1em; margin-bottom: 0.7em;"><span class="">' . Yii::t('vmpool', 'Assign users to VM Pool') . ' \'' . $vmpool->sstDisplayName . '\'</span></div>';
		$dual = $this->createWidget('ext.zii.CJqDualselect', array(
			'id' => 'userAssignment',
			'values' => $uarray,
			'size' => 6,
			'options' => array(
				'sorted' => true,
				'leftHeader' => Yii::t('vmpool', 'Users'),
				'rightHeader' => Yii::t('vmpool', 'Assigned users'),
			),
			'theme' => 'osbd',
			'themeUrl' => $this->cssBase . '/jquery',
			'cssFile' => 'dualselect.css',
		));
		$dual->run();
?>
		<button id="saveUserAssignment" style="margin-top: 10px; float: left;"></button>
		<div id="errorUserAssignment" class="ui-state-error ui-corner-all" style="display: none; margin-top: 10px; margin-left: 20px; padding: 0pt 0.7em; float: right;">
			<p style="margin: 0.3em 0pt ; "><span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-alert"></span><span id="errorUserMsg"></span></p>
		</div>
		<div id="infoUserAssignment" class="ui-state-highlight ui-corner-all" style="display: none; margin-top: 10px; margin-left: 20px; padding: 0pt 0.7em; float: right;">
			<p style="margin: 0.3em 0pt ; "><span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-info"></span><span id="infoUserMsg"></span></p>
		</div>
<?php
		$dual = ob_get_contents();
		ob_end_clean();
		echo $dual;
	}

	public function actionSaveUserAssign() {
		$this->disableWebLogRoutes();
		$vmpool = CLdapRecord::model('LdapVmPool')->findByDn($_GET['dn']);
		$userDn = 'ou=people,' . $_GET['dn'];
		$server = CLdapServer::getInstance();
		$server->delete($userDn, true, true);
		$getusers = explode(',', $_GET['users']);
		foreach($getusers as $uid) {
			$user = LdapUser::model()->findByDn('uid=' . $uid . ',ou=people');
			if (!is_null($user)) {
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
				$data['ou'] = $uid;
				$data['description'] = array('This entry links to the user ' . $user->getName() . '.');
				$data['labeledURI'] = array('ldap:///' . $user->dn);
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=' . $uid . ',' . $userDn;
				$server->add($dn, $data);
			}
		}
		$json = array('err' => false, 'msg' => Yii::t('vmpool', 'Assignment saved!'));
		$this->sendJsonAnswer($json);
	}

	public function actionGetGroupGui() {
		$this->disableWebLogRoutes();
		$garray = array();
		$groups = LdapGroup::model()->findAll(array('attr'=>array()));
		foreach ($groups as $group) {
			$garray[$group->uid] = array('name' => $group->sstDisplayName);
			if ($group->isAssignedToVmPool($_GET['dn'])) {
				$garray[$group->uid]['selected'] = true;
			}
		}
		$vmpool = CLdapRecord::model('LdapVmPool')->findByDn($_GET['dn']);
		ob_start();
		echo '<div class="ui-widget-header ui-corner-all" style="padding: 0.4em 1em; margin-bottom: 0.7em;"><span class="">' . Yii::t('vmpool', 'Assign groups to VM Pool') . ' \'' . $vmpool->sstDisplayName . '\'</span></div>';
		$dual = $this->createWidget('ext.zii.CJqDualselect', array(
			'id' => 'groupAssignment',
			'values' => $garray,
			'size' => 5,
			'options' => array(
				'sorted' => true,
				'leftHeader' => Yii::t('vmpool', 'Groups'),
				'rightHeader' => Yii::t('vmpool', 'Assigned groups'),
			),
			'theme' => 'osbd',
			'themeUrl' => $this->cssBase . '/jquery',
			'cssFile' => 'dualselect.css',
		));
		$dual->run();
?>
		<button id="saveGroupAssignment" style="margin-top: 10px; float: left;"></button>
		<div id="errorGroupAssignment" class="ui-state-error ui-corner-all" style="display: none; margin-top: 10px; margin-left: 20px; padding: 0pt 0.7em; float: right;">
			<p style="margin: 0.3em 0pt ; "><span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-alert"></span><span id="errorGroupMsg"></span></p>
		</div>
		<div id="infoGroupAssignment" class="ui-state-highlight ui-corner-all" style="display: none; margin-top: 10px; margin-left: 20px; padding: 0pt 0.7em; float: right;">
			<p style="margin: 0.3em 0pt ; "><span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-info"></span><span id="infoGroupMsg"></span></p>
		</div>
<?php
		$dual = ob_get_contents();
		ob_end_clean();
		echo $dual;
	}

	public function actionSaveGroupAssign() {
		$this->disableWebLogRoutes();
		$vmpool = CLdapRecord::model('LdapVmPool')->findByDn($_GET['dn']);
		$groupDn = 'ou=groups,' . $_GET['dn'];
		$server = CLdapServer::getInstance();
		$server->delete($groupDn, true, true);
		$getgroups = explode(',', $_GET['groups']);
		foreach($getgroups as $uid) {
			$group = LdapGroup::model()->findByDn('uid=' . $uid . ',ou=groups');
			if (!is_null($group)) {
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
				$data['ou'] = $uid;
				$data['description'] = array('This entry links to the group ' . $group->sstDisplayName . '.');
				$data['labeledURI'] = array('ldap:///' . $group->dn);
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=' . $uid . ',' . $groupDn;
				$server->add($dn, $data);
			}
		}
		$json = array('err' => false, 'msg' => Yii::t('vmpool', 'Assignment saved!'));
		$this->sendJsonAnswer($json);
	}

	public function actionDelete() {
		if (isset($_POST['oper']) && 'del' == $_POST['oper']) {
			$dn = urldecode(Yii::app()->getRequest()->getPost('dn'));
			$pool = CLdapRecord::model('LdapVmPool')->findByDn($dn);
			if (!is_null($pool)) {
				$pool->delete(true);
			}
			else {
				$this->sendAjaxAnswer(array('error' => 1, 'message' => 'VM Pool \'' . $_POST['dn'] . '\' not found!'));
			}
		}
	}

	public function actionCreate() {
		$model = new VmPoolForm('create');
		$hasError = false;
		
		$this->performAjaxValidation($model);

		if(isset($_POST['VmPoolForm'])) {
			$model->attributes = $_POST['VmPoolForm'];

			$libvirt = CPhpLibvirt::getInstance();

			$storagepool = CLdapRecord::model('LdapStoragePool')->findByAttributes(array('attr'=>array('sstStoragePool'=>$model->storagepool)));
			$basepath = substr($storagepool->sstStoragePoolURI, 7);
			foreach($model->nodes as $nodename) {
				$node = CLdapRecord::model('LdapNode')->findByAttributes(array('attr'=>array('sstNode'=>$nodename)));
				if (false === $libvirt->assignStoragePoolToNode($node->getLibvirtUri(), $storagepool->sstStoragePool, $basepath)) {
					$hasError = true;
					$model->addError('dn', Yii::t('vmpool', 'Unable to assign StoragePool to node {node}!',
						array(
							'{node}' => $nodename,
						)
					));
				}
			}
			
			if (!$hasError) {
				$pool = new LdapVmPool();
				$pool->sstVirtualMachinePool = CPhpLibvirt::getInstance()->generateUUID();
				$pool->sstDisplayName = $model->displayName;
				$pool->description = $model->description;
				$pool->sstNumberOfScreens = $model->sstNumberOfScreens;
				$pool->sstVirtualMachinePoolType = $storagepool->sstStoragePoolType;
				if ('dynamic' === $storagepool->sstStoragePoolType) {
					$pool->sstBrokerMinimalNumberOfVirtualMachines = $model->brokerMin;
					$pool->sstBrokerMaximalNumberOfVirtualMachines = $model->brokerMax;
					$pool->sstBrokerPreStartNumberOfVirtualMachines = $model->brokerPreStart;
					$pool->sstBrokerPreStartInterval = $model->brokerPreStartInterval;
				}
				else {
					$pool->removeAttributesByObjectClass('sstVirtualMachinePoolDynamicObjectClass');
				}
				$pool->sstBelongsToCustomerUID = Yii::app()->user->customerUID;
				$pool->sstBelongsToResellerUID = Yii::app()->user->resellerUID;
				$pool->save();
	
				$globalbackup = LdapConfigurationBackup::model()->findByDn('ou=backup,ou=configuration,ou=virtualization,ou=services');
				$poolbackup = new LdapConfigurationBackup();
				//$poolbackup->attributes = $globalbackup->attributes;
				$poolbackup->branchDn = $pool->getDn();
				$poolbackup->setOverwrite(true);
				$poolbackup->ou = 'backup';
				$poolbackup->description = 'This sub tree contains the backup plan of the pool \'' . $pool->sstDisplayName . '\'';
				
				$saveattrs = array();
				if ('TRUE' === $model->poolBackupActive) {
					$poolbackup->sstBackupNumberOfIterations = $model->sstBackupNumberOfIterations;
					$poolbackup->sstVirtualizationVirtualMachineForceStart = $model->sstVirtualizationVirtualMachineForceStart;
					$saveattrs[] = 'sstBackupNumberOfIterations';
					$saveattrs[] = 'sstVirtualizationVirtualMachineForceStart';
				}
				
				if ('GLOBAL' !== $model->poolCronActive) {
					$poolbackup->sstCronActive = $model->poolCronActive;
					if ('TRUE' === $model->poolCronActive) {
						list($hour, $minute) = explode(':', $model->cronTime);
						$poolbackup->sstCronMinute = (int) $minute;
						$poolbackup->sstCronHour = (int) $hour;
						$poolbackup->sstCronDayOfWeek = $model->sstCronDayOfWeek;
						$poolbackup->sstCronDay = '*';
						$poolbackup->sstCronMonth = '*';
						$saveattrs[] = 'sstCronMinute';
						$saveattrs[] = 'sstCronHour';
						$saveattrs[] = 'sstCronDayOfWeek';
						$saveattrs[] = 'sstCronDay';
						$saveattrs[] = 'sstCronMonth';
						$saveattrs[] = 'sstCronActive';
					}
					else {
						$poolbackup->sstCronDay = '*';
						$poolbackup->sstCronMonth = '*';
						$poolbackup->sstCronDayOfWeek = '*';
						$poolbackup->sstCronMinute = 0;
						$poolbackup->sstCronHour = 0;
	
						$saveattrs[] = 'sstCronMinute';
						$saveattrs[] = 'sstCronHour';
						$saveattrs[] = 'sstCronDayOfWeek';
						$saveattrs[] = 'sstCronDay';
						$saveattrs[] = 'sstCronMonth';
						$saveattrs[] = 'sstCronActive';
					}
				}
				else {
					$poolbackup->removeAttributesByObjectClass('sstCronObjectClass');
				}

				if (0 < count($saveattrs)) {
					$poolbackup->save(false);
				}

				if ('dynamic' === $pool->sstVirtualMachinePoolType && 'TRUE' === $model->poolShutdownActive) {
					$poolshutdown = new LdapConfigurationShutdown();
					$poolshutdown->branchDn = $pool->getDn();
					$poolshutdown->ou = 'shutdown';
					$poolshutdown->setOverwrite(true);
					$poolshutdown->description = 'This sub tree contains the shutdown plan of the pool \'' . $pool->sstDisplayName . '\'';
					
					$poolshutdown->sstCronActive = $model->poolShutdownActive;
					list($hour, $minute) = explode(':', $model->poolShutdownTime);
					$poolshutdown->sstCronMinute = (int) $minute;
					$poolshutdown->sstCronHour = (int) $hour;
					$poolshutdown->sstCronDayOfWeek = $model->poolShutdownDayOfWeek;
					$poolshutdown->sstCronDay = '*';
					$poolshutdown->sstCronMonth = '*';
					$poolshutdown->save(false);
				}
	
				$settings = new LdapVmPoolConfigurationSettings();
				$settings->setBranchDn($pool->dn);
				$settings->ou = "settings";
				$settings->save();
	
				if (1 == $model->poolSound) {
					$poolSound = new LdapConfigurationSetting();
					$poolSound->branchDn = $settings->getDn();
					$poolSound->ou = 'sound';
					$poolSound->sstAllowSound = 1 == $model->allowSound ? 'TRUE' : 'FALSE';
					$poolSound->save();
				}
					
				if (1 == $model->poolUsb) {
					$poolUsb = new LdapConfigurationSetting();
					$poolUsb->branchDn = 'ou=settings,' . $pool->getDn();
					$poolUsb->ou = 'usb';
					$poolUsb->sstAllowUsb = 1 == $model->allowUsb ? 'TRUE' : 'FALSE';
					$poolUsb->save();
				}
					
				$server = CLdapServer::getInstance();
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'sstRelationship');
				$data['ou'] = array('nodes');
				$data['description'] = array('This is the Nodes subtree.');
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=nodes,' . $pool->dn;
				$server->add($dn, $data);
	
				$basepath = substr($storagepool->sstStoragePoolURI, 7);
				foreach($model->nodes as $nodename) {
					$data = array();
					$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
					$data['ou'] = $nodename;
					$node = CLdapRecord::model('LdapNode')->findByAttributes(array('attr'=>array('sstNode'=>$nodename)));
	
					$data['description'] = array('This entry links to the node ' . $nodename . '.');
					$data['labeledURI'] = array('ldap:///' . $node->dn);
					$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
					$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
					$dn2 = 'ou=' . $nodename . ',' . $dn;
					$server->add($dn2, $data);
				}
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'sstRelationship');
				$data['ou'] = array('ranges');
				$data['description'] = array('This is the Ranges subtree.');
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=ranges,' . $pool->dn;
				$server->add($dn, $data);
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
				$data['ou'] = $model->range;
				$range = CLdapRecord::model('LdapDhcpRange')->findByAttributes(array('attr'=>array('cn'=>$model->range), 'depth'=>true));
	
				$data['description'] = array('This entry links to the range ' . $model->range . '.');
				$data['labeledURI'] = array('ldap:///' . $range->dn);
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn2 = 'ou=' . $model->range . ',' . $dn;
				$server->add($dn2, $data);
	
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'sstRelationship');
				$data['ou'] = array('storage pools');
				$data['description'] = array('This is the StoragePool subtree.');
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=storage pools,' . $pool->dn;
				$server->add($dn, $data);
	
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
				$data['ou'] = $model->storagepool;
				$storagepool = CLdapRecord::model('LdapStoragePool')->findByAttributes(array('attr'=>array('sstStoragePool'=>$model->storagepool)));
				$data['description'] = array('This entry links to the storagepool ' . $model->storagepool . '.');
				$data['labeledURI'] = array('ldap:///' . $storagepool->dn);
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn2 = 'ou=' . $model->storagepool . ',' . $dn;
				$server->add($dn2, $data);
	
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'sstRelationship');
				$data['ou'] = array('groups');
				$data['description'] = array('This is the assigned groups subtree.');
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=groups,' . $pool->dn;
				$server->add($dn, $data);
	
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'sstRelationship');
				$data['ou'] = array('people');
				$data['description'] = array('This is the assigned people subtree.');
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn = 'ou=people,' . $pool->dn;
				$server->add($dn, $data);
	
				//echo '<pre>' . print_r($pool, true) . '</pre>';
				$this->redirect(array('index'));
			}
		}
		if (!isset($_POST['VmPoolForm']) || $hasError) {
/*
			$pools = CLdapRecord::model('LdapStoragePool')->findAll(array('attr'=>array()));
			$storagepools = $this->createDropdownFromLdapRecords($pools, 'sstStoragePool', 'sstDisplayName');
*/
			$ldapnodes = CLdapRecord::model('LdapNode')->findAll(array('attr'=>array()));
			$nodes = array();
			foreach($ldapnodes as $node) {
				if ($node->isType('VM-Node')) {
					$nodes[$node->sstNode] = false;
				}
			}

			$model->allowSound = null;
			$model->poolSound = false;

			$model->allowUsb = null;
			$model->poolUsb = false;

			$model->poolBackupActive = 'FALSE';
			$model->poolCronActive = 'GLOBAL';
			$backup = LdapConfigurationBackup::model()->findByDn('ou=backup,ou=configuration,ou=virtualization,ou=services');

			$model->sstBackupNumberOfIterations = $backup->sstBackupNumberOfIterations;
			$model->sstVirtualizationVirtualMachineForceStart = $backup->sstVirtualizationVirtualMachineForceStart;
			
			$model->sstCronMinute = $backup->sstCronMinute;
			$model->sstCronHour = $backup->sstCronHour;
			$model->sstCronDayOfWeek = $backup->sstCronDayOfWeek;
			$model->cronTime = $model->sstCronHour . ':' . $model->sstCronMinute;
/*
			$allRanges = array();
			$subnets = CLdapRecord::model('LdapDhcpSubnet')->findAll(array('attr'=>array()));
			foreach($subnets as $subnet) {
				$ranges = array();
				foreach($subnet->ranges as $range) {
					if ($range->sstNetworkType == 'static') {
						$ranges[$range->cn] = $range->getRangeAsString();
					}
				}
				$allRanges[$subnet->cn . '/' . $subnet->dhcpNetMask] = $ranges;
			}
*/
			$globalSettings = LdapConfigurationSettings::model()->findByDn('ou=settings,ou=configuration,ou=virtualization,ou=services');
			
			$this->render('create',array(
				'model' => $model,
				'storagepools' => array(),
				'nodes' => $nodes,
				'ranges' => array(),
				'types' => array('dynamic'=>'dynamic', 'persistent'=>'persistent', 'template'=>'template'),
				'globalSound' => $globalSettings->isSoundAllowed(),
				'globalUsb' => $globalSettings->isUsbAllowed(),
				'screens' => array(),
			));
		}
	}

	public function actionUpdate() {
		$model = new VmPoolForm('update');

		$this->performAjaxValidation($model);

		if(isset($_POST['VmPoolForm'])) {
			$model->attributes = $_POST['VmPoolForm'];

			$libvirt = CPhpLibvirt::getInstance();

			$storagepool = CLdapRecord::model('LdapStoragePool')->findByAttributes(array('attr'=>array('sstStoragePool'=>$model->storagepool)));

			$pool = CLdapRecord::model('LdapVmPool')->findByDn($_POST['VmPoolForm']['dn']);
			$pool->setOverwrite(true);
			$pool->sstDisplayName = $model->displayName;
			$pool->description = $model->description;
			$pool->sstNumberOfScreens = $model->sstNumberOfScreens;
			if ('dynamic' == $storagepool->sstStoragePoolType) {
				$pool->sstBrokerMinimalNumberOfVirtualMachines = $model->brokerMin;
				$pool->sstBrokerMaximalNumberOfVirtualMachines = $model->brokerMax;
				$pool->sstBrokerPreStartNumberOfVirtualMachines = $model->brokerPreStart;
				if ('' === $model->brokerPreStartInterval) {
					$pool->sstBrokerPreStartInterval = array();
				}
				else {
					$pool->sstBrokerPreStartInterval = $model->brokerPreStartInterval;
				}
			}
			elseif ($pool->hasObjectClass('sstVirtualMachinePoolDynamicObjectClass')) {
				$pool->removeAttributesByObjectClass('sstVirtualMachinePoolDynamicObjectClass');
			}
			$pool->save();
			$oldnodes = array();
			foreach($pool->nodes as $node) {
				$oldnodes[] = $node->ou;
			}
			$newnodes = array();
			foreach($model->nodes as $nodename) {
				$newnodes[] = $nodename;
			}
			//$pool->deleteNodes();
			foreach($pool->nodes as $nodenameless) {
				$key = array_search($nodenameless->ou, $newnodes);
				if($key !== false) {
					unset($newnodes[$key]);
				}
				else {
					$lastpool = true;
					$vmpools = LdapVmPool::model()->findAll(array('attr'=>array()));
					foreach($vmpools as $vmpool) {
						if ($pool->getDn() == $vmpool->getDn()) continue;
						foreach($vmpool->storagepools as $stpools) {
							if ($stpools->ou == $storagepool->sstStoragePool) {
								foreach($vmpool->nodes as $nodes) {
									if ($nodenameless->ou == $nodes->ou) {
										$lastpool = false;
										break;
									}
								}
								if (!$lastpool) break;
							}
						}
						if (!$lastpool) break;
					}
					if ($lastpool) {
						$node = LdapNode::model()->findByAttributes(array('attr'=>array('sstNode'=>$nodenameless->ou)));
						if (false === $libvirt->removeStoragePoolToNodeAssignment($node->getLibvirtUri(), $storagepool->sstStoragePool)) {
							echo "ERRORRRRRRRRRRRRRRRRR 1";
						}
					}
					$nodenameless->delete();
				}
			}

			$server = CLdapServer::getInstance();
			$dn = 'ou=nodes,' . $pool->dn;
			$basepath = substr($storagepool->sstStoragePoolURI, 7);
			foreach($newnodes as $nodename) {
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
				$data['ou'] = $nodename;
				$node = CLdapRecord::model('LdapNode')->findByAttributes(array('attr'=>array('sstNode'=>$nodename)));

				$data['description'] = array('This entry links to the node ' . $nodename . '.');
				$data['labeledURI'] = array('ldap:///' . $node->dn);
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn2 = 'ou=' . $nodename . ',' . $dn;
				$server->add($dn2, $data);

				if (false === $libvirt->assignStoragePoolToNode($node->getLibvirtUri(), $storagepool->sstStoragePool, substr($storagepool->sstStoragePoolURI, 7))) {
					echo "ERRORRRRRRRRRRRRRRRRR 2";
				}
			}

			if (!is_null($model->range)) {
				$pool->deleteRanges();
				$dn = 'ou=ranges,' . $pool->dn;
				$data = array();
				$data['objectClass'] = array('top', 'organizationalUnit', 'labeledURIObject', 'sstRelationship');
				$data['ou'] = $model->range;
				$range = CLdapRecord::model('LdapDhcpRange')->findByAttributes(array('attr'=>array('cn'=>$model->range), 'depth' => true));
				$data['description'] = array('This entry links to the range ' . $model->range . '.');
				$data['labeledURI'] = array('ldap:///' . $range->dn);
				$data['sstBelongsToCustomerUID'] = array(Yii::app()->user->customerUID);
				$data['sstBelongsToResellerUID'] = array(Yii::app()->user->resellerUID);
				$dn2 = 'ou=' . $model->range . ',' . $dn;
				$server->add($dn2, $data);
			}

			$globalbackup = LdapConfigurationBackup::model()->findByDn('ou=backup,ou=configuration,ou=virtualization,ou=services');
			$poolbackupfound = true;
			$poolbackup = $pool->backup;
			if (is_null($poolbackup)) {
				$poolbackupfound = false;
				$poolbackup = new LdapConfigurationBackup();
				$poolbackup->branchDn = $pool->getDn();
				$poolbackup->ou = 'backup';
				$poolbackup->description = 'This sub tree contains the backup plan of the pool \'' . $pool->sstDisplayName . '\'';
			}
			else {
				$poolbackup->setAsNew();
				// old one will be deleted before save this one
			}
			$poolbackup->setOverwrite(true);
				
			$saveattrs = array();
			if ('TRUE' === $model->poolBackupActive) {
				$poolbackup->sstBackupNumberOfIterations = $model->sstBackupNumberOfIterations;
				$poolbackup->sstVirtualizationVirtualMachineForceStart = $model->sstVirtualizationVirtualMachineForceStart;
			}
			else {
				$poolbackup->sstBackupNumberOfIterations = '';
				$poolbackup->sstVirtualizationVirtualMachineForceStart = '';
			}
			$saveattrs[] = 'sstBackupNumberOfIterations';
			$saveattrs[] = 'sstVirtualizationVirtualMachineForceStart';
			
			if ('GLOBAL' !== $model->poolCronActive) {
				$poolbackup->addObjectClass('sstCronObjectClass');
				$poolbackup->sstCronActive = $model->poolCronActive;
				$saveattrs[] = 'sstCronActive';
				if ('TRUE' === $model->poolCronActive) {
					list($hour, $minute) = explode(':', $model->cronTime);
					$poolbackup->sstCronMinute = (int) $minute;
					$poolbackup->sstCronHour = (int) $hour;
					if ('TRUE' == $model->everyDay) {
						$poolbackup->sstCronDayOfWeek = '*';
					}
					else {
						$poolbackup->sstCronDayOfWeek = implode(',', $model->sstCronDayOfWeek);
					}
				}
				else {
					$poolbackup->sstCronMinute = 0;
					$poolbackup->sstCronHour = 0;
					$poolbackup->sstCronDayOfWeek = '*';
				}
				$poolbackup->sstCronDay = '*';
				$poolbackup->sstCronMonth = '*';

				$saveattrs[] = 'sstCronMinute';
				$saveattrs[] = 'sstCronHour';
				$saveattrs[] = 'sstCronDayOfWeek';
				$saveattrs[] = 'sstCronDay';
				$saveattrs[] = 'sstCronMonth';
			}
			else {
				$poolbackup->removeAttributesByObjectClass('sstCronObjectClass', true);
			}

					
			if (0 < count($saveattrs)) {
// 				if (!in_array('sstCronActive', $saveattrs)) {
// 					// only sstVirtualizationBackupObjectClass needed
// 					$poolbackup->removeAttributesByObjectClass('sstCronObjectClass');
// 				}
// 				if (!in_array('sstBackupNumberOfIterations', $saveattrs)) {
// 					// only sstCronObjectClass needed
// 					$poolbackup->removeAttributesByObjectClass('sstVirtualizationBackupObjectClass');
// 				}
				if ($poolbackupfound) {
					CLdapServer::getInstance()->delete($poolbackup->getDn());
				}
				$poolbackup->save(false);
			}
			else if ($poolbackupfound) {
				$poolbackup->delete();
			}
			
			$poolshutdown = $pool->shutdown;
			if (is_null($poolshutdown)) {
				$poolshutdown = new LdapConfigurationShutdown();
				$poolshutdown->ou = 'shutdown';
				$poolshutdown->sstCronActive = $model->poolShutdownActive;
				$poolshutdown->sstCronDay = '*';
				$poolshutdown->sstCronMonth = '*';
				
				$poolshutdown->branchDn = $pool->getDn();
				$poolshutdown->description = 'This sub tree contains the shutdown plan of the pool \'' . $pool->sstDisplayName . '\'';
			}

			$poolshutdown->setOverwrite(true);
			$poolshutdown->sstCronActive = $model->poolShutdownActive;
			if ('TRUE' === $model->poolShutdownActive) {
				list($hour, $minute) = explode(':', $model->poolShutdownTime);
				$poolshutdown->sstCronMinute = (int) $minute;
				$poolshutdown->sstCronHour = (int) $hour;
				if ('TRUE' == $model->poolShutdownEveryDay) {
					$poolshutdown->sstCronDayOfWeek = '*';
				}
				else {
					$poolshutdown->sstCronDayOfWeek = implode(',', $model->poolShutdownDayOfWeek);
				}
				$poolshutdown->save(false);
			}
			else if (!$poolshutdown->isNewEntry()) {
				$poolshutdown->delete();
			}
			
			$poolSound = $pool->settings->getSoundSetting();
			if (0 == $model->poolSound) {
				if (!is_null($poolSound)) {
					$poolSound->delete();
				}
			}
			else {
				if (!is_null($poolSound)) {
					$poolSound->setOverwrite(true);
				}
				else {
					$poolSound = new LdapConfigurationSetting();
					$poolSound->branchDn = 'ou=settings,' . $pool->getDn();
					$poolSound->ou = 'sound';
				}
				$poolSound->sstAllowSound = 1 == $model->allowSound ? 'TRUE' : 'FALSE';
				$poolSound->save();
			}
			
			$poolUsb = $pool->settings->getUsbSetting();
			if (0 == $model->poolUsb) {
				if (!is_null($poolUsb)) {
					$poolUsb->delete();
				}
			}
			else {
				if (!is_null($poolUsb)) {
					$poolUsb->setOverwrite(true);
				}
				else {
					$poolUsb = new LdapConfigurationSetting();
					$poolUsb->branchDn = 'ou=settings,' . $pool->getDn();
					$poolUsb->ou = 'usb';
				}
				$poolUsb->sstAllowUsb = 1 == $model->allowUsb ? 'TRUE' : 'FALSE';
				$poolUsb->save();
			}

			//echo '<pre>' . print_r($pool, true) . '</pre>';
			$this->redirect(array('index'));
		}
		else {
			if(isset($_GET['dn'])) {
				$pool = CLdapRecord::model('LdapVmPool')->findbyDn($_GET['dn']);
			}
			if($pool === null)
				throw new CHttpException(404,'The requested page does not exist.');

			$model->dn = $pool->dn;
			$model->type = $pool->sstVirtualMachinePoolType;
			$model->displayName = $pool->sstDisplayName;
			$model->description = $pool->description;
			$model->sstNumberOfScreens = $pool->sstNumberOfScreens;
			//echo '<pre>' . print_r($pool->ranges, true) . '</pre>';
			if (1 == count($pool->ranges)) {
				$model->range = $pool->ranges[0]->ou;
			}

			$allRanges = array();
			if (0 < count($pool->storagepools)) {
				//echo '<pre>' . print_r($pool->storagepools, true) . '</pre>';
				//$model->storagepool = $pool->storagepools[0]->ou;
				//$storagepool = CLdapRecord::model('LdapStoragePool')->findByAttributes(array('attr'=>array('sstStoragePool'=>$model->storagepool)));
				$model->storagepool = $pool->storagepools[0]->ou;
				$storagepool = $pool->getStoragePool();
				$type = $storagepool->sstStoragePoolType;
				$allRanges = $this->getRangesByType($type, $pool->ranges);
			}
			$model->nodes = array();
			foreach($pool->nodes as $node) {
				$model->nodes[] = $node->ou;
			}
			if (0 < count($pool->ranges)) {
				//echo '<pre>' . print_r($pool->ranges, true) . '</pre>';
				$model->range = $pool->ranges[0]->ou;
				//echo $pool->ranges[0]->ou;
			}
			if ('dynamic' == $type) {
				$model->brokerMin = $pool->sstBrokerMinimalNumberOfVirtualMachines;
				$model->brokerMax = $pool->sstBrokerMaximalNumberOfVirtualMachines;
				$model->brokerPreStart = $pool->sstBrokerPreStartNumberOfVirtualMachines;
				$model->brokerPreStartInterval = $pool->sstBrokerPreStartInterval;
				$model->poolShutdown = true;
			}

			//echo '<pre>GLOBAL:SOUND ' . var_export($pool->settings->defaultSettings->isSoundAllowed(), true) . '</pre>';
			$model->allowSound = $pool->settings->isSoundAllowed();
			$model->poolSound = true;
			//echo '<pre>POOL:SOUND ' . var_export($model->allowSound, true) . ', ' . $pool->settings->getSoundLocation() . '</pre>';
			if ('global' === $pool->settings->getSoundLocation()) {
				$model->allowSound = null;
				$model->poolSound = false;
			}

			//echo '<pre>GLOBAL:USB ' . var_export($pool->settings->defaultSettings->isUsbAllowed(), true) . '</pre>';
			$model->allowUsb = $pool->settings->isUsbAllowed();
			$model->poolUsb = true;
			//echo '<pre>POOL:USB ' . var_export($model->allowUsb, true) . ', ' . $pool->settings->getUsbLocation() . '</pre>';
			if ('global' === $pool->settings->getUsbLocation()) {
				$model->allowUsb = null;
				$model->poolUsb = false;
			}

			$model->poolBackupActive = 'TRUE';
			$model->poolCronActive = 'TRUE';
			$backup = $pool->backup;
			if (is_null($backup)) {
				$model->poolBackupActive = 'FALSE';
				$model->poolCronActive = 'GLOBAL';
				$backup = LdapConfigurationBackup::model()->findByDn('ou=backup,ou=configuration,ou=virtualization,ou=services');
			}
			else {
				if (isset($backup->sstCronActive)) {
					$model->poolCronActive = $backup->sstCronActive;
				}
				else {
					$model->poolCronActive = 'GLOBAL';
				}
				if (!isset($backup->sstBackupNumberOfIterations)) {
					$model->poolBackupActive = 'FALSE';
				}
			}

			$model->sstBackupNumberOfIterations = $backup->sstBackupNumberOfIterations;
			$model->sstVirtualizationVirtualMachineForceStart = $backup->sstVirtualizationVirtualMachineForceStart;
			
			$model->sstCronMinute = $backup->sstCronMinute;
			$model->sstCronHour = $backup->sstCronHour;
			$model->sstCronDayOfWeek = explode(',', $backup->sstCronDayOfWeek);
			if ('*' == $backup->sstCronDayOfWeek) {
				$model->everyDay = 'TRUE';
			}
			else {
				$model->everyDay = 'FALSE';
			}
			$model->cronTime = $model->sstCronHour . ':' . $model->sstCronMinute;
			
			$model->poolShutdownActive = 'FALSE';
			$shutdown = $pool->shutdown;
			if (!is_null($shutdown)) {
				$model->poolShutdownActive = 'TRUE';
				if (isset($shutdown->sstCronActive)) {
					$model->poolShutdownActive = $shutdown->sstCronActive;
				}
			
				$model->poolShutdownMinute = $shutdown->sstCronMinute;
				$model->poolShutdownHour = $shutdown->sstCronHour;
				$model->poolShutdownDayOfWeek = explode(',', $shutdown->sstCronDayOfWeek);
				if ('*' == $shutdown->sstCronDayOfWeek) {
					$model->poolShutdownEveryDay = 'TRUE';
				}
				else {
					$model->poolShutdownEveryDay = 'FALSE';
				}
				$model->poolShutdownTime = $model->poolShutdownHour . ':' . $model->poolShutdownMinute;
			}			
				
			$pools = CLdapRecord::model('LdapStoragePool')->findAll(array('attr'=>array()));
			$storagepools = $this->createDropdownFromLdapRecords($pools, 'sstStoragePool', 'sstDisplayName');

			$ldapnodes = LdapNode::model()->findAll(array('attr'=>array()));
			$nodes = array();
			foreach($ldapnodes as $node) {
				if ($node->isType('VM-Node')) {
					$vms = LdapVm::model()->findAll(array('attr'=>array('sstNode' => $node->sstNode, 'sstVirtualMachinePool' => $pool->sstVirtualMachinePool)));
					$nodes[$node->sstNode] = 0 < count($vms);
				}
			}
			
			$screens = array();
			$config = CLdapRecord::model('LdapVmPoolDefinition')->findByAttributes(array('attr'=>array('ou'=>$pool->sstVirtualMachinePoolType)));
			for($i=1; $i<=$config->sstNumberOfScreens; $i++) {
				$screens[$i] = $i;
			}

			$this->render('update',array(
				'model' => $model,
				'storagepools' => $storagepools,
				'nodes' => $nodes,
				'ranges' => $allRanges,
				'types' => array('dynamic'=>'dynamic', 'persistent'=>'persistent', 'template'=>'template'),
				'vmcount' => count($pool->vms),
				'globalSound' => $pool->settings->defaultSettings->isSoundAllowed(),
				'globalUsb' => $pool->settings->defaultSettings->isUsbAllowed(),
				'screens' => $screens,
			));
		}
	}

	private function getRangesByType($type, $ownRanges) {
		$allRanges = array();
		$subnets = CLdapRecord::model('LdapDhcpSubnet')->findAll(array('attr'=>array()));
		foreach($subnets as $subnet) {
			$ranges = array();
			foreach($subnet->ranges as $range) {
				if ($range->sstNetworkType == $type && (!$range->isUsed() || (0 < count($ownRanges) && $ownRanges[0]->ou == $range->cn))) {
					$ranges[$range->cn] = $range->getRangeAsString();
				}
			}
			if (0 < count($ranges)) {
				$allRanges[$subnet->cn . '/' . $subnet->dhcpNetMask] = $ranges;
			}
		}
		return $allRanges;
	}
}