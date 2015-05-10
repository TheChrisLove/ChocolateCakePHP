<?php
/************************
* Developed by Chris Love
* Made in SoBe
*/

class PageController extends Controller
{

    /**
     * About us page
     */
    public function actionAboutUs() {
    // the required data has been passed to the view directly
    $this->pageTitle = 'Welcome to ... | Home';
    $this->render('about-us', array(
    'aboutus' => Page::model()->getAboutUs(),
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

