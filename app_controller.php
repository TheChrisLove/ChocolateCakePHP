<?php
/****************************
* Developed by The Chris Love
* Made in SoBe
*/

class SiteController extends Controller
{

    /**
     * Declares class-based actions.
     */
    public function actions() {
        return array(
            // captcha action renders the CAPTCHA image displayed on the contact page
            'captcha' => array(
                //'class'=>'CCaptchaAction',
                //'backColor'=>0xFFFFFF,
                'class' => 'application.extensions.captchaExtended.CaptchaExtendedAction',
            ),
            // page action renders "static" pages stored under 'protected/views/site/pages'
            // They can be accessed via: index.php?r=site/page&view=FileName
            'page' => array(
                'class' => 'CViewAction',
            ),
        );
    }

    /**
     * This is the default 'index' action that is invoked
     * when an action is not explicitly requested by users.
     * @sitemap changefreq=daily priority=0.3 lastmod=2012-08-15
     */
    public function actionIndex() {
    // the required data has been passed to the viiew directly
    $this->pageTitle = 'Welcome to ... | Home';
    $this->render(
    'index', array(
    	'banners' => Banner::model()->getHomePageBanners(),
    	'featuredArtist' => Profile::model()->getFeaturedArtist(),
    	'blogs'=> Blog::model()->getBlogTitle(),
    	'featuredProject' => Project::model()->featuredProject(),
    	'projectFanCount' => ProjectFan::model()->projectFanCount(),
    	'projectCount' => Project::model()->projectCount(),
    	'events' => Event::model()->getEventTitle()
    ));
    }
    
    public function actionSearch() {
       $this->pageTitle = 'Search | Search query';
        $this->render('search');    
    }

    /**
     * This is the action to handle external exceptions.
     */
    public function actionError() {
        if ($error = Yii::app()->errorHandler->error) {
            if (Yii::app()->request->isAjaxRequest)
                echo $error['message'];
            else
                $this->render('error', $error);
        }
    }

    /**
     * Displays the contact page
     * @sitemap changefreq=yearly priority=0.3 lastmod=2012-08-15
     */
   public function actionContact() {
        $model = new ContactForm;
        if (isset($_POST['ContactForm'])) {
            $model->attributes = $_POST['ContactForm'];
            if ($model->validate()) {
                $name = '=?UTF-8?B?' . base64_encode($model->name) . '?=';
                $subject = '=?UTF-8?B?' . base64_encode($model->subject) . '?=';
                $headers = "From: $name <{$model->email}>\r\n" .
                        "Reply-To: {$model->email}\r\n" .
                        "MIME-Version: 1.0\r\n" .
                        "Content-type: text/plain; charset=UTF-8";

                mail(Yii::app()->params['adminEmail'], $subject, $model->body, $headers);
                Yii::app()->user->setFlash('contact', 'Thank you for contacting us. We will respond to you as soon as possible.');
                $this->refresh();
            }
        }
        $this->render('contact', array('model' => $model));
    }

    //Log out and direct to home page
    public function actionLogout() {
        Yii::app()->user->logout();
        $this->redirect(Yii::app()->createUrl('//site/index'));
    }
    
}
