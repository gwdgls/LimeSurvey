<?php
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 * Surveys Groups Controller
 */

use LimeSurvey\Models\Services\SurveysGroupCreator;

class SurveysGroupsController extends Survey_Common_Action
{

    /**
     * Displays a particular model.
     *
     * @param integer $id the ID of the model to be displayed
     * @return void
     */
    public function view($id)
    {
        if (!Permission::model()->hasSurveyGroupPermission($id, SurveysGroups::getMinimalPermissionRead(), 'read')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }
        $this->render('view', array(
            'model'=>$this->loadModel($id),
        ));
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     *
     * @return void
     */
    public function create()
    {
        if (!Permission::model()->hasGlobalPermission('surveysgroups', 'create')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }

        $model = new SurveysGroups();
        /* Move to SurveysGroup model init ? */
        $model->owner_id = Yii::app()->user->id;
        // Uncomment the following line if AJAX validation is needed
        // $this->performAjaxValidation($model);

        $user = Yii::app()->user;
        $request = Yii::app()->request;
        if ($request->getPost('SurveysGroups')) {
            $service = new SurveysGroupCreator(
                $request,
                $user,
                $model,
                new SurveysGroupsettings()
            );
            if ($service->save()) {
                $this->getController()->redirect(
                    App()->createUrl("admin/surveysgroups/sa/update", array('id' => $model->gsid, '#' => 'settingsForThisGroup'))
                );
            }
        }

        $aData = array(
            'model' => $model,
            'action' => App()->createUrl("admin/surveysgroups/sa/create", array('#' => 'settingsForThisGroup')),
        );
        $aData['aRigths'] = array(
            'update' => true,
            'delete' => false,
            'owner_id' => true,
        );
        $aData['fullpagebar'] = array(
            'savebutton' => array(
                'form' => 'surveys-groups-form'
            ),
            'returnbutton' => array(
                'url' => $this->getController()->createUrl('surveyAdministration/listsurveys', array("#" => 'surveygroups')),
                'text' => gT('Close'),
            )
        );
        /* User for dropdown */
        $aUserIds = getUserList('onlyuidarray');
        $userCriteria = new CDbCriteria();
        $userCriteria->select = array("uid", "users_name", "full_name");
        $userCriteria->order = "full_name";
        $userCriteria->addInCondition('uid', $aUserIds);
        $aData['oUsers'] = User::model()->findAll($userCriteria);
        $this->_renderWrappedTemplate('surveysgroups', 'create', $aData);
    }

    /**
     * Show and updates a particular model.
     * If update is successful, the browser will be redirected to the 'view' page.
     *
     * @todo : check if this function can be called with >hasSurveyGroupPermission($id, 'group', 'read')
     * @param integer $id the ID of the model to be updated
     * @return void
     */
    public function update($id)
    {
        $model = $this->loadModel($id);
        if (!empty(App()->getRequest()->getPost('SurveysGroups'))) {
            if (!Permission::model()->hasSurveyGroupPermission($id, 'group', 'update')) {
                throw new CHttpException(403, gT("You do not have permission to access this page."));
            }
            $postSurveysGroups = App()->getRequest()->getPost('SurveysGroups');
            /* Mimic survey system : only owner and superadmin can update owner … */
            /* After update : potential loose of rights on SurveysGroups */
            if($model->owner_id != Yii::app()->user->id
                && !Permission::model()->hasGlobalPermission('superadmin', 'read')
            ) {
                $postSurveysGroups['owner_id'] = $model->owner_id;
            }
            if($model->gsid == 1) {
                /* Move this to model */
                $postSurveysGroups['alwaysavailable'] = 1;
            }
            // parent_id control
            if (!empty($postSurveysGroups['parent_id'])) {
                $parentId = $postSurveysGroups['parent_id'] ;
                /* Check permission */
                $aAvailableParents = $model->getParentGroupOptions($model->gsid);
                if (!array_key_exists($parentId, $aAvailableParents)) {
                    Yii::app()->setFlashMessage(sprintf(gT("You don't have rights on Survey group"),CHtml::encode($parentId)), 'error');
                    $postSurveysGroups['parent_id'] = $model->parent_id;
                }
                /* avoid loop */
                $ParentSurveyGroup = $this->loadModel($parentId);
                $aParentsGsid = $ParentSurveyGroup->getAllParents(true);
                if ( in_array( $model->gsid, $aParentsGsid  ) ) {
                    Yii::app()->setFlashMessage(gT("A child group can't be set as parent group"), 'error');
                    $this->getController()->redirect($this->getController()->createUrl('surveyAdministration/listsurveys', array("#"=>'surveygroups')));
                }
            }
            $model->attributes = $postSurveysGroups;
            if ($model->save()) {
                if (App()->request->getPost('saveandclose') !== null){
                    $this->getController()->redirect($this->getController()->createUrl('surveyAdministration/listsurveys', array("#"=>'surveygroups')));
                }
            }
        }

        $aData = array(
            'model' => $model,
            'action' => App()->createUrl("admin/surveysgroups/sa/update", array('id' => $model->gsid, '#' => 'settingsForThisGroup')),
        );
        $oSurveySearch = new Survey('search');
        $oSurveySearch->gsid = $model->gsid;
        $aData['oSurveySearch'] = $oSurveySearch;
        $aData['aRigths'] = array(
            'update' => Permission::model()->hasSurveyGroupPermission($id, 'group', 'update'),
            'delete' => Permission::model()->hasSurveyGroupPermission($id, 'group', 'delete'),
            'owner_id' => $model->owner_id == Yii::app()->user->id || Permission::model()->hasGlobalPermission('superadmin', 'read')
        );

        /* User for dropdown */
        $aUserIds = getUserList('onlyuidarray');
        if(!in_array($model->owner_id,$aUserIds)) {
            $aUserIds[] =$model->owner_id;
        }
        $userCriteria = new CDbCriteria;
        $userCriteria->select = array("uid", "users_name", "full_name");
        $userCriteria->order = "full_name";
        $userCriteria->addInCondition('uid',$aUserIds);
        $aData['oUsers'] = User::model()->findAll($userCriteria);

        $oTemplateOptions           = new TemplateConfiguration();
        $oTemplateOptions->scenario = 'surveygroup';
        $aData['templateOptionsModel'] = $oTemplateOptions;
        // Page size
        if (Yii::app()->request->getParam('pageSize')) {
            Yii::app()->user->setState('pageSizeTemplateView', (int) Yii::app()->request->getParam('pageSize'));
        }
        $aData['pageSize'] = Yii::app()->user->getState('pageSizeTemplateView', Yii::app()->params['defaultPageSize']); // Page size

        $this->_renderWrappedTemplate('surveysgroups', 'update', $aData);
    }

    /**
     * Show the survey settings menue for a particular group
     * @param integer $id group id, used for permission control
     * @todo camelCase here and globalsettings->surveysettingmenues
     * @return void
     */
    public function surveysettingmenues($id) {
        if (!Permission::model()->hasSurveyGroupPermission($id, 'surveysettings', 'read')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }
        /* Can not call gloalsettings contoller fuinction sice _construct check access … */
        $menues = Surveymenu::model()->getMenuesForGlobalSettings();
        Yii::app()->getController()->renderPartial('super/_renderJson', ['data' => $menues[0]]);
    }

    /**
     * Updates a particular model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @todo : find where it shown
     * @todo : fix $_POST call
     * @param integer $id the ID of the model to be updated
     */
    public function surveySettings($id)
    {
        $bRedirect = 0;
        /** @var SurveysGroups $model */
        $model = $this->loadModel($id);
        if (!Permission::model()->hasSurveyGroupPermission($id, 'surveysettings', 'read')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }
        $aData = array(
            'model' => $model
        );

        $sPartial = Yii::app()->request->getParam('partial', '_generaloptions_panel');

        /** @var SurveysGroupsettings $oSurvey */
        $oSurvey = SurveysGroupsettings::model()->findByPk($model->gsid);
        $oSurvey->setOptions(); //this gets the "values" from the group that inherits to this group ...
        $oSurvey->owner_id = $model->owner_id;

        if (App()->getRequest()->isPostRequest && !Permission::model()->hasSurveyGroupPermission($id, 'surveysettings', 'update')) {
            throw new CHttpException(403, gT("You do not have permission to update survey settings."));
        }
        //every $_POST checked here is one of the switchers(On|Off|Inherit) names
        // Name of sidemenulink   => name of input field
        // "General settings"     => 'template'
        // "Presentation"         => 'showxquestions'
        // "Pariticipant setting" => 'anonymized'
        // "Notification & data"  => 'datestamp'
        // "Publication & access" => 'listpublic'
        if(isset($_POST['template']) || isset($_POST['showxquestions']) || isset($_POST['anonymized'])
            || isset($_POST['datestamp']) || isset($_POST['listpublic'])){
            $oSurvey->attributes = $_POST;

            if(isset($_POST['listpublic'])){
                //what is usecaptcha used for? see saveTranscribeCaptchaOptions method description ...
                // in default group this is set to 'N' ... (this means 'none' no captcha for survey access, regigstration
                // and 'save&load'
                $oSurvey->usecaptcha = Survey::saveTranscribeCaptchaOptions();
            }
            if ($oSurvey->save()) {
                $bRedirect = 1;
            }
        }

        $users = getUserList();
        $aData['users'] = array();
        $inheritOwner = empty($oSurvey['ownerLabel']) ? $oSurvey['owner_id'] : $oSurvey['ownerLabel'];
        $aData['users']['-1'] = gT('Inherit').' ['. $inheritOwner . ']';
        foreach ($users as $user) {
            $aData['users'][$user['uid']] = $user['user'].($user['full_name'] ? ' - '.$user['full_name'] : '');
        }
        // Sort users by name
        asort($aData['users']);

        $aData['oSurvey'] = $oSurvey;

        if ($bRedirect && App()->request->getPost('saveandclose') !== null){
            $this->getController()->redirect($this->getController()->createUrl('surveyAdministration/listsurveys', array("#"=>'surveygroups')));
        }

        // Page size
        if (Yii::app()->request->getParam('pageSize')) {
            Yii::app()->user->setState('pageSizeTemplateView', (int) Yii::app()->request->getParam('pageSize'));
        }
        $aData['pageSize'] = Yii::app()->user->getState('pageSizeTemplateView', Yii::app()->params['defaultPageSize']); // Page size

        Yii::app()->clientScript->registerPackage('bootstrap-switch', LSYii_ClientScript::POS_BEGIN);
        Yii::app()->clientScript->registerPackage('globalsidepanel');

        $aData['aDateFormatDetails'] = getDateFormatData(Yii::app()->session['dateformat']);
        $aData['jsData'] = [
            'sgid' => $id,
            'baseLinkUrl' => 'admin/surveysgroups/sa/surveysettings/id/'.$id,
            'getUrl' => Yii::app()->createUrl(
                'admin/surveysgroups/sa/surveysettingmenues',
                array('id' => $id)
            ),
            'i10n' => [
                'Survey settings' => gT('Survey settings')
            ]
        ];
        $aData['buttons'] = array(
            'closebutton'=>array(
                'url' => App()->createUrl('surveyAdministration/listsurveys', array('#' => 'surveygroups')),
            ),
        );
        if (Permission::model()->hasSurveyGroupPermission($id, 'surveysettings', 'update')) {
            $aData['buttons']['savebutton'] = array(
                'form' => 'survey-settings-options-form'
            );
            $aData['buttons']['saveandclosebutton'] = array(
                'form' => 'survey-settings-options-form'
            );
        }
        $aData['partial'] = $sPartial;

        $this->_renderWrappedTemplate('surveysgroups', 'surveySettings', $aData);
    }

    /**
     * Deletes a particular model.
     * If deletion is successful, the browser will be redirected to the 'admin' page.
     * @param integer $id the ID of the model to be deleted
     */
    public function delete($id)
    {
        $oGroupToDelete = $this->loadModel($id);
        if (!Permission::model()->hasSurveyGroupPermission($id, 'group', 'delete')) {
            throw new CHttpException(403, gT("You do not have permission to access this page."));
        }
        $sGroupTitle = $oGroupToDelete->title;
        $returnUrl = App()->getRequest()->getPost('returnUrl', array('surveyAdministration/listsurveys', '#' => 'surveygroups'));
        if ($oGroupToDelete->hasSurveys) {
            Yii::app()->setFlashMessage(gT("You can't delete a group if it's not empty!"), 'error');
            $this->getController()->redirect($returnUrl);
        } elseif ($oGroupToDelete->hasChildGroups) {
            Yii::app()->setFlashMessage(gT("You can't delete a group because one or more groups depend on it as parent!"), 'error');
            $this->getController()->redirect($returnUrl);
        } else {
            $oGroupToDelete->delete();
            // if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
            if (!App()->getRequest()->getQuery('ajax')) {
                Yii::app()->setFlashMessage(sprintf(gT("The survey group '%s' was deleted."), CHtml::encode($sGroupTitle)), 'success');
                $this->getController()->redirect($returnUrl);
            }
        }
    }

    /**
     * Lists all models
     * Only list SurveysGroup according to Permission, user must just be loggued.
     * @return void
     */
    public function index()
    {
        $model = new SurveysGroups('search');
        $aData = array(
            'model' => $model
        );
        $this->_renderWrappedTemplate('surveysgroups', 'index', $aData);
    }

    /**
     * Manages all models.
     * @TODO : Remove
     */
    public function admin()
    {
        /* @see next comment : throw 500 error */
        throw new CHttpException(400, gT("Invalid action"));

        $model = new SurveysGroups('search'); // @todo : fix this : need update permission
        $model->unsetAttributes(); // clear any default values
        if (!empty(App()->getRequest()->getParam('SurveysGroups'))) {
            $model->attributes = App()->getRequest()->getParam('SurveysGroups');
        }
        /* Throw : SurveysGroupsController and its behaviors do not have a method or closure named "render". */
        $this->render('admin', array(
            'model'=>$model,
        ));
    }


    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return SurveysGroups the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = SurveysGroups::model()->findByPk($id);
        if ($model === null) {
            throw new CHttpException(404, 'The requested page does not exist.');
        }
        return $model;
    }

    /**
     * Performs the AJAX validation.
     * @param SurveysGroups $model the model to be validated
     */
    protected function performAjaxValidation($model)
    {
        if (App()->getRequest()->getPost('ajax') === 'surveys-groups-form') {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }
}
