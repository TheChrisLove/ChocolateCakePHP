<?php
ob_start();
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright	  Copyright 2005-2010, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link		  http://cakephp.org CakePHP(tm) Project
 * @package		  cake
 * @subpackage	  cake.cake.libs.controller
 * @since		  CakePHP(tm) v 0.2.9
 * @license		  MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * This is a placeholder class.
 * Create the same file in app/app_controller.php
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		  cake
 * @subpackage	  cake.cake.libs.controller
 * @link http://book.cakephp.org/view/957/The-App-Controller
 */
class AppController extends Controller
{
	public $helpers		= array('Session', 'NeursForm','NeursHtml', 'Form', 'Html', 'Ajax', 'Cdn', 'Permission');
	public $uses		= array('Account', 'Menu', 'Notification', 'Log', 'Config');
	public $components	= array('AutoLogin', 'Auth', 'Session', 'Notifications', 'Logs', 'RequestHandler', 'Transactions',
		'Cookie' => array(
			'name'	=> 'NEURS'
		),
		'DebugKit.Toolbar' => array(
			'autoRun'		=> false,
			'forceEnable'	=> false
		)
	);

	public function beforeFilter()
	{
		$debug = Configure::read('debug');

		//clearCache(); Cache::clear();

		$this->Session->host	= 'neurs.com';
		$this->layout			= empty($_GET['layout']) && $this->RequestHandler->ext !== 'json' ? 'flat' : 'default';

		if (!empty($_REQUEST['json']) && $_REQUEST['json'] == 'true')
		{
			$this->layout = 'js/json';
		}

		if ($debug > 0 && $this->RequestHandler->isAjax())
		{
			Configure::write('debug', 0);
		}

		if ($this->Session->check('InternalMessages'))
		{
			 $this->set('internal_messages', $this->Session->read('InternalMessages'));
			 $this->Session->delete('InternalMessages');
		}

		$menus = $this->Menu->find('all', array(
			'fields'		=> array('id', 'parent_id', 'link', 'image', 'text'),
			'conditions'	=> array(
				'Menu.deleted'		=> 0,
				'Menu.parent_id !='	=> -1
			),
			'order'			=> array('Menu.parent_id' => 'ASC', 'Menu.id' => 'ASC'),
			'recursive'		=> -1,
			'cache'			=> 60
		));

		$this->set('menu', $this->_tree($menus, 'Menu'));

		$this->set('config', $this->config = $this->Config->find('list', array(
			'fields'	=> array('Config.name', 'Config.value'),
			'cache'		=> 30
		)));

		$this->set('advert', array(
			'link' => '',
			'image' => '',
			'image_alt' => ''
		));

		$this->AutoLogin->cookieName	= 'rm';
		$this->AutoLogin->expires		= '+2 weeks';

		$this->Auth->userModel		= 'Account';
		$this->Auth->authorize		= 'controller';
		$this->Auth->autoRedirect	= false;
		$this->Auth->userScope		= array('Account.active' => true, 'Account.membership_plan_id >' => 0);
		$this->Auth->fields			= array('username' => 'email', 'password' => 'password');
		$this->Auth->loginRedirect	= array('controller' => 'home', 'action' => 'index');
		$this->Auth->logoutRedirect	= $debug < 2 ? 'https://neurs.com/account/logout' : '/logout';
		$this->Auth->loginAction	= $debug < 2 ? 'https://neurs.com/account/login' : '/login';
		$this->Auth->loginError		= __('AUTH_LOGIN_ERROR', true);
		$this->Auth->authError		= __('AUTH_UNAUTHORIZED', true);

		if ($this->Auth->user())
		{
			$this->Account->id		= $this->Auth->user('id');
			$this->Account->data	= $this->Account->find('first', array(
				'fields' => array(
					'id', 'parent_id', 'market_id', 'country_id', 'location_id', 'membership_plan_id', 'email', 'profile_image', 'credits',
					'first_name', 'last_name', 'name', 'language', 'gender', 'timezone_id', 'last_billing_date', 'active', 'created', 'deleted'
				),
				'conditions' => array(
					'Account.id' => $this->Account->id
				),
				'recursive' => -1
			));

			$this->set('user', $this->Account->data);

			if (!$this->Account->data['Account']['active'])
			{
				$this->cakeError('error404');
			}

			Configure::write('Config.language', $this->Account->data['Account']['language']);

			if (false /* $this->Account->data['MembershipPlan']['price'] > 0 && time() - strtotime($this->Account->data['Account']['last_billing_date']) > 2595600 */)
			{
				$this->redirect('/account/billing');
			}
			else if (!empty($this->Account->data['MembershipPlan']['name']) && strpos($this->Account->data['MembershipPlan']['name'], '_TRIAL') && strtotime($this->Account->data['Account']['created']) > time())
			{
				$this->redirect('/account/upgrade');
			}
		}

		if (!empty($this->config['maintenance']))
		{
			if ($this->Auth->user())
			{
				if ($this->isAuthorized('maintenance'))
				{
					// Allow through (Admins, etc)
				}
				else
				{
					$this->redirect($this->Auth->logout());
				}
			}
			else if ($this->name == 'Accounts' && $this->action == 'login')
			{
				if ($this->data && $this->Auth->login($this->data))
				{
					// Login to see if the user is authorized
					if (!$this->isAuthorized('maintenance'))
					{
						$this->redirect($this->Auth->logout());
					}
				}
				else
				{
					$this->render('/elements/maintenance/' . $this->config['maintenance'], 'maintenance');
				}
			}
			else if ($this->name == 'Uploads' && $this->action == 'index' && !empty($this->params['form']['token']) && is_int(strpos($_SERVER['HTTP_USER_AGENT'], 'Flash')))
			{
				// Allow uploads to go through (they get purged via Cron after a long time anyways...)
			}
			else
			{
				$this->render('/elements/maintenance/' . $this->config['maintenance'], 'maintenance');
			}
		}

		$this->set('audiences', $this->Account->audience);
		$this->set('categories', $this->Account->category);
		$this->set('providerTypes', $this->Account->provider_type);
		$this->set('industries', $this->Account->industry);
	}

	public function isAuthorized($permission = false, $return = false)
	{
		$authorized = false;

		if (!$this->Auth->user())
		{
			return false;
		}

		if (is_array($permission))
		{
			$controller	= count($permission) === 1 ? low($this->name) : $permission[0];
			$action		= count($permission) === 1 ? $permission[0] : $permission[1];
		}
		else if (is_string($permission))
		{
			if ($permission[0] === '/')
			{
				$parsed		= Router::parse($permission);
				$controller	= $parsed['controller'];
				$action		= $parsed['action'];
			}
			else if (strpos($permission, ':')) // absolute url (ie. http://neurs.tv)
			{
				return true;
			}
			else
			{
				$controller	= '_';
				$action		= $permission;
			}
		}
		else
		{
			$controller	= low($this->name);
			$action		= low($this->action);
		}

		if (in_array($action, array('login', 'logout')))
		{
			return true;
		}

		if (empty($this->permissions))
		{
			$this->permissions = array();

			$perms = $this->Account->MembershipPlan->MembershipPermission->find('all', array(
				'fields'		=> array('controller', 'action', 'allowed'),
				'conditions'	=> array(
					'membership_plan_id'	=> $this->Account->data['Account']['membership_plan_id']
				),
				'recursive'	=> -1,
				'order'		=> array('MembershipPermission.id' => 'ASC'),
				'cache'		=> 10
			));

			foreach ($perms as $perm)
			{
				if (empty($this->permissions[$perm['MembershipPermission']['controller']]))
				{
					$this->permissions[$perm['MembershipPermission']['controller']] = array();
				}

				$this->permissions[$perm['MembershipPermission']['controller']][$perm['MembershipPermission']['action']] = $perm['MembershipPermission']['allowed'] === '0' ? false : (int) $perm['MembershipPermission']['allowed'];
			}

			$perms = $this->Account->AccountPermission->find('all', array(
				'fields'		=> array('action', 'allowed'),
				'conditions'	=> array(
					'account_id'	=> $this->Account->id
				),
				'recursive'	=> -1,
				'order'		=> array('AccountPermission.id' => 'ASC')
			));

			foreach ($perms as $perm)
			{
				if (empty($this->permissions['_']))
				{
					$this->permissions['_'] = array();
				}

				$this->permissions['_'][$perm['AccountPermission']['action']] = $perm['AccountPermission']['allowed'] === '0' ? false : (int) $perm['AccountPermission']['allowed'];
			}

			$this->set('_permissions', $this->permissions);
		}

		$perms = $this->permissions;

		if (!empty($perms[$controller]) || !empty($perms['*']))
		{
			if (isset($perms[$controller][$action]))
			{
				if ($controller === '_')
				{
					$authorized = $perms[$controller][$action] > 0 ? $perms[$controller][$action] : ($perms[$controller][$action] === -1);
				}
				else
				{
					$authorized = $perms[$controller][$action];
				}
			}
			else if (isset($perms[$controller]['*']))
			{
				$authorized = $perms[$controller]['*'];
			}
			else if (isset($perms['*']['*']))
			{
				$authorized = $perms['*']['*'];
			}
		}

		if (!$authorized)
		{
			//$this->Logs->log(2, array('referer' => $this->referer(), 'page' => $this->params['url']), true, 'UNAUTHORIZED_PAGE_ACCESS');

			if ($return)
			{
				return false;
			}

			$this->cakeError('error401');
		}

		return $authorized;
	}

	public function profile_picture($user = false, $name = 'default')
	{
		$env = Configure::read('debug');
	 	$cdn = Configure::read('CDN')[$env];

	 	if (is_numeric($user) && $this->Account->id === (int) $user)
	 	{
		 	$user = $this->Account->data;
	 	}
		else if (!is_array($user))
		{
		 	$user = $this->Account->find('first', array(
 				'attributes' => array('id', 'profile_image', 'gender'),
 				'conditions' => array(
 					'id' => $user
 				),
 				'recursive' => -1
 			));
 		}

 		$external	= $cdn . '/images/users/' . $user['Account']['id'] . '/';
 		$internal	= $cdn . '/images/icons/';
 		$image		= array(
 			'male'		=> $internal . 'Dude@2x.png',
 			'female'	=> $internal . 'Girl@2x.png',
 		);

 		if ($name !== 'unknown' && $user['Account']['profile_image'])
 		{
 			$image = $external . $user['Account']['profile_image'];
 		} 
 		else if ($user['Account']['gender'] === 'female')
 		{	
 			$image = $image['female'];
 		}
 		else
 		{
 			$image = $image['male'];
 		}

 		return $image;
	}

	protected function _tree($data, $alias, $parent_id = 0)
	{
		$tree = array();

		for ($i = 0, $c = count($data); $i < $c; $i++)
		{
			$id		= (int) $data[$i][$alias]['id'];
			$parent	= (int) $data[$i][$alias]['parent_id'];

			if ($parent === $parent_id)
			{
				$subdata					= $data[$i][$alias];
				$subdata[$alias]			= $data[$i][$alias];
				$subdata['Child' . $alias]	= $this->_tree($data, $alias, $id);

				$tree[]						= $subdata;
			}
		}

		return $tree;
	}

	protected function stdout($message)
	{
		if (!defined('STDOUT'))
		{
			define('STDOUT', fopen('php://stdout', 'w'));
		}

		fwrite(STDOUT, $message);
	}
}
