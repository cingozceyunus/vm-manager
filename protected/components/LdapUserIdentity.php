<?php
/*
 * Copyright (C) 2012 FOSS-Group
 *                    Germany
 *                    http://www.foss-group.de
 *                    support@foss-group.de
 *
 * Authors:
 *  Christian Wittkowski <wittkowski@devroom.de>
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
 * LdapUserIdentity class file.
 *
 * @author: Christian Wittkowski <wittkowski@devroom.de>
 * @version: 0.4
 */

/**
 * LdapUserIdentity represents the data needed to identity a user.
 * It contains the authentication method that checks if the provided
 * data can identity the user.
 */
class LdapUserIdentity extends CUserIdentity
{
	const ERROR_REALM_INVALID = 3;

	/**
	 * @var string realm
	 */
	public $realm;

	/**
	 * Constructor.
	 * @param string $username username
	 * @param string $password password
	 */
	public function __construct($username, $password, $realm)
	{
		parent::__construct($username, $password);
		$this->realm = $realm;
	}

	/**
	 * Authenticates a user.
	 * @return boolean whether authentication succeeds.
	 */
	public function authenticate()
	{
		$server = CLdapServer::getInstance();
		$realm = CLdapRecord::model('LdapRealm')->findByAttributes(array('attr'=>array('ou'=>$this->realm)));
		if (!is_null($realm)) {
			if ('TRUE' != $realm->sstLDAPExternalDirectory) {
				$this->authenticateIntern($realm, $realm->usersearch, $realm->userauth, $realm->usergroupsearch);
			}
			else {
				// We use an external directory
				$parts = explode(':', $realm->labeledURI);
				$hostname = $parts[0] . ':' . $parts[1];
				$port = $parts[2];
				$connection = @ldap_connect($hostname, $port) or die('LDAP connect failed!');
				if ($connection === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_connect to {server} failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection),'{server}'=>$realm->labeledURI)), ldap_errno($connection));
				}
				ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapbind = @ldap_bind($connection, $realm->sstLDAPBindDn, $realm->sstLDAPBindPassword);
				if ($ldapbind === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_bind to {server} failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection),'{server}'=>$realm->labeledURI)), ldap_errno($connection));
				}

				$usersearch = $realm->usersearch;
				$branchDn = $usersearch->sstLDAPBaseDn;
				$filter = sprintf($usersearch->sstLDAPFilter, $this->username);
//echo "branchDn: $branchDn; filter: $filter<br/>";
				$result = @ldap_search($connection, $branchDn, $filter);
				if ($result === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_search failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection))), ldap_errno($connection));
				}
				$entries = @ldap_get_entries($connection, $result);
				if ($entries === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_get_entries failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection))), ldap_errno($connection));
				}
//echo '<pre>' . print_r($entries, true) . '</pre>';

				if (1 == $entries['count']) {
					$staticAttrName = $usersearch->sstLDAPForeignStaticAttribute;
					//echo $staticAttrName . '; ';
					if (isset($entries[0][$staticAttrName])) {
						if (is_array($entries[0][$staticAttrName])) {
							$staticAttr = $entries[0][$staticAttrName][0];
						}
						else {
							$staticAttr = $entries[0][$staticAttrName];
						}
					}
					else {
						throw new CLdapException(Yii::t('LdapComponent.server', 'usersearch: sstLDAPForeignStaticAttribute ({attr}) not found!',
							array('{attr}'=>$staticAttrName)));
					}
					//echo $staticAttr . '<br/>';
					$mapping = array();
					foreach($usersearch->sstLDAPInternalForeignMapping as $intext) {
						list($intern, $foreign) = split(':', $intext);
						if ('' == $foreign) {
							$mapping[$intern] = '';
						}
						else {
							$mapping[$intern] = $entries[0][strtolower($foreign)][0];
						}
					}
//echo '<pre>' . print_r($mapping, true) . '</pre>';

					$userauth = $realm->userauth;
					$authOk = $server->authorizeUserExtern($realm->labeledURI, $userauth, $staticAttr, $this->password);
					if (!$authOk) {
						$this->errorCode = self::ERROR_PASSWORD_INVALID;
					}
					else {
						$usergroupsearch = $realm->usergroupsearch;
						$branchDn = $usergroupsearch->sstLDAPBaseDn;
						$filter = sprintf($usergroupsearch->sstLDAPFilter, $staticAttr);
//echo "branchDn: $branchDn; filter: $filter<br/>";

						$result = @ldap_search($connection, $branchDn, $filter);
						if ($result === false) {
							throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_search failt ({errno}): {message}',
								array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection))), ldap_errno($connection));
						}
						$entries = @ldap_get_entries($connection, $result);
						if ($entries === false) {
							throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_get_entries failt ({errno}): {message}',
								array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection))), ldap_errno($connection));
						}
//echo '<pre>' . print_r($entries, true) . '</pre>';

						$groups = $this->handleGroupData($entries, $usergroupsearch->sstLDAPReturnAttribute);
//echo '<pre>' . print_r($groups, true) . '</pre>';

						$user = LdapUser::model()->findByAttributes(array('attr'=>array('sstLDAPForeignStaticAttribute'=>$staticAttr)));
//echo '<pre>User (sstLDAPForeignStaticAttribute=' . $staticAttr . '; ' . print_r($user, true) . '</pre>';

						if (is_null($user)) {
							$user = new LdapUser();
							$user->setBranchDn('ou=people');
							$uid = Yii::app()->controller->getNextUid();
							while (is_null($uid)) {
								sleep(2);
								$uid = Yii::app()->controller->getNextUid();
							}

							$user->uid = $uid;
							$user->sstLDAPForeignStaticAttribute = $staticAttr;
							$user->givenName = '';
							$user->sn = '';
							$user->mail = '';
							$user->sstGender = '?';
							$user->cn = $this->username;
							$user->userPassword = LdapUser::encodePassword(Utils::generatePassword(), strtolower($server->getEncryptionType()));
							//$user->telephoneNumber = $model->telephone;
							//$user->mobile = $model->mobile;
							$user->preferredLanguage = 'en-GB';
							$user->sstTimeZoneOffset = 'UTC+01';
							$user->sstBelongsToCustomerUID = $realm->sstBelongsToCustomerUID;
							$user->sstBelongsToResellerUID = $realm->sstBelongsToResellerUID;
							if (0 < count($groups)) {
								$user->sstGroupUID = $groups;
							}
							$user->setOverwrite(true);
							foreach($mapping as $attr => $value) {
								$user->$attr = $value;
							}
							$user->save();

							$userrole = new LdapUserRole();
							$userrole->setBranchDn($user->dn);
							$userrole->sstProduct = '0';
							$userrole->sstRole = 'User';
							$userrole->save();
						}

						$this->errorCode = self::ERROR_NONE;
						Yii::app()->user->setState('__uid', $user->uid);
						Yii::app()->user->setState('__groupuids', $groups);
						Yii::app()->user->setState('__realm', $this->realm);
						Yii::app()->user->setState('__admin', false);
						Yii::app()->user->setState('__customeruid', $realm->sstBelongsToCustomerUID);
						Yii::app()->user->setState('__reselleruid', $realm->sstBelongsToResellerUID);

						ldap_unbind($connection);
					}
				}
				else {
					// let's check if the user exists in the internal directory
					$usersearch = LdapNameless::model()->findByDn('ou=User Search,ou=internal searches,ou=configuration,ou=virtualization,ou=services');
					$userauth = LdapNameless::model()->findByDn('ou=User Authentication,ou=internal searches,ou=configuration,ou=virtualization,ou=services');
					$usergroupsearch = LdapNameless::model()->findByDn('ou=User Group Search,ou=internal searches,ou=configuration,ou=virtualization,ou=services');
//					$criteria = array(
//						'branchDn' => 'ou=people',
//						'filter' => '(&(objectClass=sstPerson)(cn=' . $this->username . ')(!(sstLDAPForeignStaticAttribute=*)))',
//					);
					$this->authenticateIntern($realm, $usersearch, $userauth, $usergroupsearch);
				}
			}
		}
		else {
			$this->errorCode = self::ERROR_REALM_INVALID;
		}
		return !$this->errorCode;
	}

	private function authenticateIntern($realm, $usersearch, $userauth, $usergroupsearch)
	{
		$server = CLdapServer::getInstance();
		//$usersearch = $realm->usersearch;
		$criteria = array(
			'branchDn' => $usersearch->sstLDAPBaseDn,
			'filter' => sprintf($usersearch->sstLDAPFilter, $this->username)
		);
		$result = LdapNameless::model()->findByAttributes($criteria);
		if (!is_null($result)) {
			//echo '<pre>' . print_r($result->getAttributes(), true) . '</pre>';
			$staticAttr = $usersearch->sstLDAPForeignStaticAttribute;
			//echo $staticAttr . '; ';
			$staticAttr = $result->$staticAttr;
			//echo $staticAttr . '<br/>';
			//$userauth = $realm->userauth;
			$authOk = $server->authorizeUser($userauth, $staticAttr, $this->password);
			if (!$authOk) {
				$this->errorCode = self::ERROR_PASSWORD_INVALID;
			}
			else {
				//$usergroupsearch = $realm->usergroupsearch;
				$criteria = array(
					'branchDn' => $usergroupsearch->sstLDAPBaseDn,
					'filter' => sprintf($usergroupsearch->sstLDAPFilter, $staticAttr)
				);
				$results = LdapNameless::model()->findAll($criteria);
				$result = $results[0];
				$returnAttr = $usergroupsearch->sstLDAPReturnAttribute;
				$groups = array();
				if (isset($result->$returnAttr)) {
					$resultgroups = $result->$returnAttr;
					if (!is_array($resultgroups)) {
						$groups = array($resultgroups);
					}
					else {
						$groups = $resultgroups;
					}
				}
				$model = LdapUser::model()->findByDn(sprintf($userauth->sstLDAPAuthUserBindDn, $staticAttr));
				if (is_null($model)) {
					$this->errorCode = self::ERROR_USERNAME_INVALID;
				}
				else {
					// User is OK; Let's check if the user is assigned to the realm
					if ($model->sstBelongsToCustomerUID == $realm->sstBelongsToCustomerUID) {
						$this->errorCode = self::ERROR_NONE;
						Yii::app()->user->setState('__uid', $model->uid);
						Yii::app()->user->setState('__groupuids', $groups);
						Yii::app()->user->setState('__realm', $this->realm);
						Yii::app()->user->setState('__admin', $model->isAdmin());
						Yii::app()->user->setState('__foreign', $model->isForeign());
						Yii::app()->user->setState('__customeruid', $model->sstBelongsToCustomerUID);
						Yii::app()->user->setState('__reselleruid', $model->sstBelongsToResellerUID);
					}
					else {
						$this->errorCode = self::ERROR_REALM_INVALID;
					}
				}
			}
		}
		else {
			$this->errorCode = self::ERROR_USERNAME_INVALID;
		}
	}

	/**
	 * Handle the group data coming from the "User Group Search".
	 * @return array uid's of internal groups matching sstGroupName == $returnAttr
	 */
	private function handleGroupData($entries, $returnAttr)
	{
		$groupuids = array();
//echo $returnAttr . '; ' . $entries['count'];
		for($i=0; $i<$entries['count']; $i++) {
			$attr = $entries[$i][strtolower($returnAttr)];
			if (is_array($attr)) {
				for($j=0; $j<$attr['count']; $j++) {
//echo $attr[$j];
					$group = LdapGroup::model()->findByAttributes(array('attr'=>array('sstGroupName'=>$attr[$j])));
					if (!is_null($group)) {
						$groupuids[] = $group->uid;
//echo ' ' . $group->uid;
					}
//echo '<br/>';
				}
			}
			else {
//echo $attr;
				$group = LdapGroup::model()->findByAttributes(array('attr'=>array('sstGroupName'=>$attr)));
				if (!is_null($group)) {
					$groupuids[] = $group->uid;
//echo ' ' . $group->uid;
				}
//echo '<br/>';
			}
		}
		return $groupuids;
	}

	/**
	 * Authenticates a user.
	 * @return boolean whether authentication succeeds.
	 */
	public function authenticateOld()
	{
		$server = CLdapServer::getInstance();
		$realm = CLdapRecord::model('LdapRealm')->findByAttributes(array('attr'=>array('ou'=>$this->realm)));
		if (!is_null($realm)) {
			$username = $realm->sstLDAPAuthUserNameAttribute;
			$criteria['attr'][$username] = $this->username;
			$model = CLdapRecord::model('LdapUser')->findByAttributes($criteria);
			if (is_null($model)) {
				$this->errorCode = self::ERROR_USERNAME_INVALID;
			}
			else {
				$authOk = false;
				if ('false' == strtolower($realm->sstLDAPAuthUserBind)) {
					$authOk = $model->checkPassword($realm->sstLDAPAuthUserPasswordAttribute, $this->password);
				}
				else {
					$uid = $realm->sstLDAPStaticAttribute;
					$authOk = $server->authorizeUser($model->$uid, $this->password);
				}
				if (!$authOk) {
					$this->errorCode = self::ERROR_PASSWORD_INVALID;
				}
				else {
					// User is OK; Let's check if the user is assigned to the realm
					if ($model->sstBelongsToCustomerUID == $realm->sstBelongsToCustomerUID) {
						$this->errorCode = self::ERROR_NONE;
						Yii::app()->user->setState('__uid', $model->uid);
						Yii::app()->user->setState('__realm', $this->realm);
						Yii::app()->user->setState('__admin', $model->isAdmin());
						Yii::app()->user->setState('__foreign', $model->isForeign());
						Yii::app()->user->setState('__customeruid', $model->sstBelongsToCustomerUID);
						Yii::app()->user->setState('__reselleruid', $model->sstBelongsToResellerUID);
					}
					else {
						$this->errorCode = self::ERROR_REALM_INVALID;
					}
				}
			}
		}
		else {
			$this->errorCode = self::ERROR_REALM_INVALID;
		}
		return !$this->errorCode;
	}

	public static function checkRealm($realm)
	{
		Yii::log("checkRealm: $realm", 'profile', 'ext.ldaprecord.CLdapServer');

		$server = CLdapServer::getInstance();
		$realm = CLdapRecord::model('LdapRealm')->findByAttributes(array('attr'=>array('ou'=>$realm)));
		return !is_null($realm);
	}

	public static function checkUser($username, $realm)
	{
		Yii::log("checkUser: $username, $realm", 'profile', 'ext.ldaprecord.CLdapServer');

		$server = CLdapServer::getInstance();
		$realm = CLdapRecord::model('LdapRealm')->findByAttributes(array('attr'=>array('ou'=>$realm)));
		if (!is_null($realm)) {
			if ('TRUE' != $realm->sstLDAPExternalDirectory) {
				$usersearch = $realm->usersearch;
				$criteria = array(
					'branchDn' => $usersearch->sstLDAPBaseDn,
					'filter' => sprintf($usersearch->sstLDAPFilter, $username)
				);
				$result = LdapNameless::model()->findByAttributes($criteria);
				return !is_null($result);
			}
			else {
				// We use an external directory
				$parts = explode(':', $realm->labeledURI);
				$hostname = $parts[0] . ':' . $parts[1];
				$port = $parts[2];
				$connection = @ldap_connect($hostname, $port) or die('LDAP connect failed!');
				if ($connection === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_connect to {server} failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection),'{server}'=>$realm->labeledURI)), ldap_errno($connection));
				}
				ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
				$ldapbind = @ldap_bind($connection, $realm->sstLDAPBindDn, $realm->sstLDAPBindPassword);
				if ($ldapbind === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_bind to {server} failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection),'{server}'=>$realm->labeledURI)), ldap_errno($connection));
				}

				$usersearch = $realm->usersearch;
				$branchDn = $usersearch->sstLDAPBaseDn;
				$filter = sprintf($usersearch->sstLDAPFilter, $username);
//echo "branchDn: $branchDn; filter: $filter<br/>";
				$result = @ldap_search($connection, $branchDn, $filter);
				if ($result === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_search failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection))), ldap_errno($connection));
				}
				$entries = @ldap_get_entries($connection, $result);
				if ($entries === false) {
					throw new CLdapException(Yii::t('LdapComponent.server', 'ldap_get_entries failt ({errno}): {message}',
						array('{errno}'=>ldap_errno($connection), '{message}'=>ldap_error($connection))), ldap_errno($connection));
				}
//echo '<pre>' . print_r($entries, true) . '</pre>';
				ldap_unbind($connection);

				return 1 == $entries['count'];
			}
		}
	}
}