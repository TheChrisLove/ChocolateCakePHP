<?php
class HomeController extends AppController
{
	public $uses = array();
	
	public function beforeFilter()
	{
		$this->Auth->allow('*');
		$this->Auth->deny('index', 'view');
		parent::beforeFilter();
	}

	public function index()
	{
		if ($this->Session->read('intro_video'))
		{
			switch (Configure::read('Config.language'))
			{
				case 'spa':
				case 'es_es':
					$this->set('video_url', '89673819');
				break;

				default:
					$this->set('video_url', '89673671');
				break;
			}
		}
	}

	public function feed()
	{
		$this->loadModel('Feed');

		if (!empty($_GET['filter']))
		{
			$feeds = $this->paginate('Feed', array(
				'Feed.feed_topic_id =' => $_GET['filter']
			));
		}
		else
		{
			$feeds = $this->paginate('Feed');
		}

		$this->set('data', $feeds);
		$this->render('/elements/json');
	}

	public function feed_topics()
	{
		$this->loadModel('Feed');
		
		$feedTopics = $this->Feed->FeedTopic->find('all');

		$this->set('data', $feedTopics);
		$this->render('/elements/json');
	}
	
	public function view($slug = null) {
		$this->loadModel('Feed');
		if (!$slug) {
			$this->Session->setFlash(__('Invalid feed', true));
			$this->redirect(array('action' => 'index'));
		}
		$this->Feed->recursive = 1;
		$this->set('feed', $this->Feed->findBySlug($slug));
	}
}
