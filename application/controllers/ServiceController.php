<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaHost;

class ServiceController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object && $this->object->object_type === 'apply') {
            $this->getTabs()->add('assign', array(
                'url'       => 'director/service/assign',
                'urlParams' => $this->object->getUrlParams(),
                'label'     => 'Assign'
            ));

            if ($host = $this->params->get('host')) {
                foreach ($this->getTabs()->getTabs() as $tab) {
                    $tab->getUrl()->setParam('host', $host);
                }
            }
        }
    }

    public function assignAction()
    {
        $this->getTabs()->activate('assign');
        $this->view->form = $form = $this->loadForm('icingaAssignServiceToHost');
        $form
            ->setIcingaObject($this->object)
            ->setDb($this->db())
            ->handleRequest();

        $this->view->table = $this->loadTable('icingaObjectAssignment')
            ->setObject($this->object);
        $this->view->title = 'Assign service to host';
        $this->render('object/fields', null, true); // TODO: render table
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                if ($hostname = $this->params->get('host')) {
                    $this->view->host = IcingaHost::load($hostname, $db);
                    $params['host_id'] = $this->view->host->id;
                }

                $this->object = IcingaService::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
