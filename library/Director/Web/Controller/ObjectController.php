<?php

namespace Icinga\Module\Director\Web\Controller;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Url;

abstract class ObjectController extends ActionController
{
    protected $object;

    protected $isApified = true;

    public function init()
    {
        parent::init();

        $type = $this->getType();

        if ($object = $this->loadObject()) {

            $params = $object->getUrlParams();

            $tabs = $this->getTabs()->add('modify', array(
                'url'       => sprintf('director/%s', $type),
                'urlParams' => $params,
                'label'     => $this->translate(ucfirst($type))
            ));

            $tabs->add('render', array(
                'url'       => sprintf('director/%s/render', $type),
                'urlParams' => $params,
                'label'     => $this->translate('Preview'),
            ))->add('history', array(
                'url'       => sprintf('director/%s/history', $type),
                'urlParams' => $params,
                'label'     => $this->translate('History')
            ));

            if ($object->hasBeenLoadedFromDb()
                && $object->supportsFields()
                && ($object->isTemplate() || $type === 'command')
            ) {
                $tabs->add('fields', array(
                    'url'       => sprintf('director/%s/fields', $type),
                    'urlParams' => $params,
                    'label'     => $this->translate('Fields')
                ));
            }
        } else {
            $this->getTabs()->add('add', array(
                'url'       => sprintf('director/%s/add', $type),
                'label'     => sprintf($this->translate('Add %s'), ucfirst($type)),
            ));
        }
    }

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            try {
                return $this->handleApiRequest();
            } catch (Exception $e) {
                $response = $this->getResponse();
                if ($response->getHttpResponseCode() === 200) {
                    $response->setHttpResponseCode(500);
                }

                return $this->sendJson((object) array('error' => $e->getMessage()));
            }
        }

        return $this->editAction();
    }

    public function renderAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('render');
        $object = $this->object;

        if ($this->params->shift('resolved')) {
            $this->view->object = $object::fromPlainObject(
                $object->toPlainObject(true),
                $object->getConnection()
            );

            if ($object->imports()->count() > 0) {
                $this->view->actionLinks = $this->view->qlink(
                    $this->translate('Show normal'),
                    $this->getRequest()->getUrl()->without('resolved'),
                    null,
                    array('class' => 'icon-resize-small state-warning')
                );
            }
        } else {
            $this->view->object = $object;

            if ($object->supportsImports() && $object->imports()->count() > 0) {
                $this->view->actionLinks = $this->view->qlink(
                    $this->translate('Show resolved'),
                    $this->getRequest()->getUrl()->with('resolved', true),
                    null,
                    array('class' => 'icon-resize-full')
                );
            }
        }

        $this->view->title = sprintf(
            $this->translate('Config preview: %s'),
            $object->object_name
        );
        $this->render('object/show', null, true);
    }

    public function editAction()
    {
        $object = $this->object;
        $this->getTabs()->activate('modify');
        $ltype = $this->getType();
        $type = ucfirst($ltype);

        $formName = 'icinga' . $type;
        $this->view->form = $form = $this->loadForm($formName)->setDb($this->db());
        $form->setObject($object);

        $this->view->title = sprintf($this->translate('Modify %s'), ucfirst($ltype));
        $this->view->form->handleRequest();

        $this->view->actionLinks = $this->view->qlink(
            sprintf($this->translate('Clone'), $this->translate(ucfirst($ltype))),
            'director/' . $ltype .'/clone',
            array('name' => $object->object_name),
            array('class' => 'icon-paste')
        );

        $this->render('object/form', null, true);
    }

    public function addAction()
    {
        $this->getTabs()->activate('add');
        $type = $this->getType();
        $ltype = strtolower($type);

        $url = sprintf('director/%ss', $ltype);
        $form = $this->view->form = $this->loadForm('icinga' . ucfirst($type))
            ->setDb($this->db())
            ->setSuccessUrl($url);

        $this->view->title = sprintf(
            $this->translate('Add new Icinga %s'),
            ucfirst($ltype)
        );

        $form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function cloneAction()
    {
        $type = $this->getType();
        $ltype = strtolower($type);
        $this->getTabs()->activate('modify');

        $this->view->form = $form = $this->loadForm(
            'icingaCloneObject'
        )->setObject($this->object);

        $this->view->title = sprintf(
            $this->translate('Clone Icinga %s'),
            ucfirst($type)
        );
        $this->view->form->handleRequest();

        $this->view->actionLinks = $this->view->qlink(
            sprintf($this->translate('back'), $this->translate(ucfirst($ltype))),
            'director/' . $ltype,
            array('name'  => $this->object->object_name),
            array('class' => 'icon-left-big')
        );

        $this->render('object/form', null, true);
    }

    public function fieldsAction()
    {
        $object = $this->object;
        $type = $this->getType();

        $this->getTabs()->activate('fields');
        $title = $this->translate('%s template "%s": custom fields');
        $this->view->title = sprintf(
            $title,
            $this->translate(ucfirst($type)),
            $object->object_name
        );

        $form = $this->view->form = $this
            ->loadForm('icingaObjectField')
            ->setDb($this->db)
            ->setIcingaObject($object);

        if ($id = $this->params->get('field_id')) {
            $form->loadObject(array(
                $type . '_id' => $object->id,
                'datafield_id' => $id
            ));

            $this->view->actionLinks = $this->view->qlink(
                $this->translate('back'),
                $this->getRequest()->getUrl()->without('field_id'),
                null,
                array('class' => 'icon-left-big')
            );

        }

        $form->handleRequest();

        $this->view->table = $this
            ->loadTable('icingaObjectDatafield')
            ->setObject($object);

        $this->render('object/fields', null, true);
    }

    public function historyAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('history');
        $this->view->title = $this->translate('Activity Log');
        $this->view->table = $this->applyPaginationLimits(
            $this->loadTable('activityLog')->setConnection($this->db())
            ->filterObject('icinga_' . $type, $this->object->object_name)
        );
        $this->render('object/history', null, true);
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/'),
            array('Group', 'Period', 'Argument', 'ApiUser'),
            $this->getRequest()->getControllerName()
        );
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $this->object = IcingaObject::loadByType(
                    $this->getType(),
                    $name,
                    $this->db()
                );
            } elseif ($id = $this->params->get('id')) {
                $this->object = IcingaObject::loadByType(
                    $this->getType(),
                    (int) $id,
                    $this->db()
                );
            } elseif ($this->getRequest()->isApiRequest()) {
                $this->getResponse()->setHttpResponseCode(422);

                throw new InvalidPropertyException(
                    'Cannot load object, missing parameters'
                );
            }

            $this->view->undeployedChanges = $this->countUndeployedChanges();
            $this->view->totalUndeployedChanges = $this->db()
                ->countActivitiesSinceLastDeployedConfig();
        }

        return $this->object;
    }

    protected function handleApiRequest()
    {
        $request = $this->getRequest();
        $db = $this->db();

        switch ($request->getMethod()) {
            case 'DELETE':
                $this->requireObject();
                $name = $this->object->object_name;
                $obj = $this->object->toPlainObject(false, true);
                $form = $this->loadForm(
                    'icingaDeleteObject'
                )->setObject($this->object)->setRequest($request)->onSuccess();

                return $this->sendJson($obj);

            case 'POST':
            case 'PUT':
                $type = $this->getType();
                $data = json_decode($request->getRawBody());

                if ($data === null) {
                    $this->getResponse()->setHttpResponseCode(400);
                    throw new IcingaException(
                        'Invalid JSON: %s' . $request->getRawBody(),
                        $this->getLastJsonError()
                    );
                } else {
                    $data = (array) $data;
                }
                if ($object = $this->object) {
                    if ($request->getMethod() === 'POST') {
                        $object->setProperties($data);
                    } else {
                        $data = array_merge(
                            array(
                                'object_type' => $object->object_type,
                                'object_name' => $object->object_name
                            ),
                            $data
                        );
                        $object->replaceWith(
                            IcingaObject::createByType($type, $data, $db)
                        );
                    }
                } else {
                    $object = IcingaObject::createByType($type, $data, $db);
                }

                $response = $this->getResponse();
                if ($object->hasBeenModified()) {
                    $status = $object->hasBeenLoadedFromDb() ? 200 : 201;
                    $object->store();
                    $response->setHttpResponseCode($status);
                } else {
                    $response->setHttpResponseCode(304);
                }

                return $this->sendJson($object->toPlainObject(false, true));

            case 'GET':
                $this->requireObject();
                return $this->sendJson(
                    $this->object->toPlainObject(
                        $this->params->shift('resolved'),
                        ! $this->params->shift('withNull'),
                        $this->params->shift('properties')
                    )
                );

            default:
                $request->getResponse()->setHttpResponseCode(400);
                throw new Exception('Unsupported method ' . $request->getMethod());
        }
    }

    protected function countUndeployedChanges()
    {
        if ($this->object === null) {
            return 0;
        }

        return $this->db()->countActivitiesSinceLastDeployedConfig($this->object);
    }

    protected function requireObject()
    {
        if (! $this->object) {
            $this->getResponse()->setHttpResponseCode(404);
            if (! $this->params->get('name')) {
                throw new NotFoundError('You need to pass a "name" parameter to access a specific object');
            } else {
                throw new NotFoundError('No such object available');
            }
        }
    }
}
