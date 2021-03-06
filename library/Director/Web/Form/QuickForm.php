<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Exception\ProgrammingError;
use Icinga\Web\Notification;
use Icinga\Web\Request;
use Icinga\Web\Url;
use Exception;
use Zend_Form;

/**
 * QuickForm wants to be a base class for simple forms
 */
abstract class QuickForm extends Zend_Form
{
    const ID = '__FORM_NAME';

    const CSRF = '__FORM_CSRF';

    /**
     * The name of this form
     */
    protected $formName;

    /**
     * Whether the form has been sent
     */
    protected $hasBeenSent;

    /**
     * Whether the form has been sent
     */
    protected $hasBeenSubmitted;

    /**
     * The submit caption, element - still tbd
     */
    // protected $submit;

    /**
     * Our request
     */
    protected $request;

    protected $successUrl;

    protected $successMessage;

    protected $submitLabel;

    protected $submitButtonName;

    protected $fakeSubmitButtonName;

    /**
     * Whether form elements have already been created
     */
    protected $didSetup = false;

    /**
     * The Icinga module this form belongs to. Usually only set if the
     * form is initialized through the FormLoader
     */
    protected $icingaModule;

    protected $icingaModuleName;

    protected $hintCount = 0;

    protected $isApiRequest = false;

    public function __construct($options = null)
    {
        parent::__construct($this->handleOptions($options));

        $basedir = Icinga::app()
            ->getModuleManager()
            ->getModule('director')
            ->getLibDir()
            . '/Director/Web/Form';

        $this->addPrefixPaths(array(
            array(
                'prefix'    => __NAMESPACE__ . '\\Element\\',
                'path'      => $basedir . '/Element',
                'type'      => static::ELEMENT
            )
        ));

        $this->setMethod('post');
        $this->setAction(Url::fromRequest());
        $this->createIdElement();
        $this->regenerateCsrfToken();
        $this->setDecorators(
            array(
                'Description',
                array('FormErrors', array('onlyCustomFormErrors' => true)),
                'FormElements',
                'Form'
            )
        );
    }

    protected function handleOptions($options = null)
    {
        if ($options === null) {
            return $options;
        }

        if (array_key_exists('icingaModule', $options)) {
            $this->icingaModule = $options['icingaModule'];
            $this->icingaModuleName = $this->icingaModule->getName();
            unset($options['icingaModule']);
        }

        return $options;
    }

    protected function addSubmitButtonIfSet()
    {
        if (false !== ($label = $this->getSubmitLabel())) {
            $el = $this->createElement('submit', $label)->setLabel($label)->setDecorators(array('ViewHelper'));
            $this->submitButtonName = $el->getName();
            $this->addElement($el);

            $fakeEl = $this->createElement('submit', '_FAKE_SUBMIT')
                ->setLabel($label)
                ->setDecorators(array('ViewHelper'));
            $this->fakeSubmitButtonName = $fakeEl->getName();
            $this->addElement($fakeEl);
        }

        $this->addDisplayGroup(
            array($this->fakeSubmitButtonName),
            'fake_button',
            array(
                'decorators' => array('FormElements'),
                'order' => 1,
            )
        );

        $grp = array(
            $this->submitButtonName,
            $this->deleteButtonName
        );
        $this->addDisplayGroup($grp, 'buttons', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'DtDdWrapper',
            ),
            'order' => 1000,
        ));
    }

    protected function createIdElement()
    {
        $this->detectName();
        $this->addHidden(self::ID, $this->getName());
        $this->getElement(self::ID)->setIgnore(true);
    }

    public function getSentValue($name, $default = null)
    {
        $request = $this->getRequest();
        if ($request->isPost() && $this->hasBeenSent()) {
            return $request->getPost($name);
        } else {
            return $default;
        }
    }

    public function getSubmitLabel()
    {
        if ($this->submitLabel === null) {
            return $this->translate('Submit');
        }

        return $this->submitLabel;
    }

    public function setSubmitLabel($label)
    {
        $this->submitLabel = $label;
        return $this;
    }

    public function setApiRequest($isApiRequest = true)
    {
        $this->isApiRequest = $isApiRequest;
        return $this;
    }

    public function isApiRequest()
    {
        return $this->isApiRequest;
    }

    protected function loadForm($name, Module $module = null)
    {
        if ($module === null) {
            $module = $this->icingaModule;
        }

        return FormLoader::load($name, $module);
    }

    public function regenerateCsrfToken()
    {
        if (! $element = $this->getElement(self::CSRF)) {
            $this->addHidden(self::CSRF, CsrfToken::generate());
            $element = $this->getElement(self::CSRF);
        }
        $element->setIgnore(true);

        return $this;
    }

    public function removeCsrfToken()
    {
        $this->removeElement(self::CSRF);
        return $this;
    }

    public function addHidden($name, $value = null)
    {
        $this->addElement('hidden', $name);
        $el = $this->getElement($name);
        $el->setDecorators(array('ViewHelper'));
        if ($value !== null) {
            $this->setDefault($name, $value);
            $el->setValue($value);
        }
    
        return $this;
    }

    public function addHtmlHint($html, $options = array())
    {
        return $this->addHtml('<div class="hint">' . $html . '</div>', $options);
    }

    public function addHtml($html, $options = array())
    {
        $name = '_HINT' . ++$this->hintCount;
        $this->addElement('simpleNote', $name, $options);
        $this->getElement($name)
            ->setValue($html)
            ->setIgnore(true)
            ->setDecorators(array('ViewHelper'));

        return $this;
    }

    public function optionalEnum($enum)
    {
        return array(
            null => $this->translate('- please choose -')
        ) + $enum;
    }

    public function setSuccessUrl($url, $params = null)
    {
        if (! $url instanceof Url) {
            $url = Url::fromPath($url);
        }
        if ($params !== null) {
            $url->setParams($params);
        }
        $this->successUrl = $url;
        return $this;
    }

    public function getSuccessUrl()
    {
        $url = $this->successUrl ?: $this->getAction();
        if (! $url instanceof Url) {
            $url = Url::fromPath($url);
        }

        return $url;
    }

    public function setup()
    {
    }

    protected function onSetup()
    {
    }

    public function setAction($action)
    {
        if ($action instanceof Url) {
            $action = $action->getAbsoluteUrl('&');
        }

        return parent::setAction($action);
    }

    public function setIcingaModule(Module $module)
    {
        $this->icingaModule = $module;
        return $this;
    }

    public function hasBeenSubmitted()
    {
        if ($this->hasBeenSubmitted === null) {
            $req = $this->getRequest();
            if ($req->isPost()) {
                if (! $this->hasSubmitButton()) {
                    return $this->hasBeenSubmitted = $this->hasBeenSent();
                }

                $this->hasBeenSubmitted = $this->pressedButton(
                    $this->fakeSubmitButtonName,
                    $this->getSubmitLabel()
                ) || $this->pressedButton(
                    $this->submitButtonName,
                    $this->getSubmitLabel()
                );
            } else {
                $this->hasBeenSubmitted === false;
            }
        }

        return $this->hasBeenSubmitted;
    }

    protected function hasSubmitButton()
    {
        return $this->submitButtonName !== null;
    }

    protected function pressedButton($name, $label)
    {
        $req = $this->getRequest();
        if (! $req->isPost()) {
            return false;
        }

        $req = $this->getRequest();
        $post = $req->getPost();

        return array_key_exists($name, $post)
            && $post[$name] === $label;
    }

    protected function beforeValidation($data = array())
    {
    }

    public function prepareElements()
    {
        if (! $this->didSetup) {
            $this->setup();
            $this->addSubmitButtonIfSet();
            $this->onSetup();
            $this->didSetup = true;
        }

        return $this;
    }

    public function handleRequest(Request $request = null)
    {
        if ($request !== null) {
            $this->setRequest($request);
        }

        if ($this->hasBeenSent()) {
            $post = $this->getRequest()->getPost();
            if ($this->hasBeenSubmitted()) {
                $this->beforeValidation($post);
                if ($this->isValid($post)) {
                    try {
                        $this->onSuccess();
                    } catch (Exception $e) {
                        $this->addError($e->getMessage());
                        $this->onFailure();
                    }
                } else {
                    $this->onFailure();
                }
            } else {
                $this->setDefaults($post);
            }
        } else {
            // Well...
        }

        return $this;
    }

    public function translate($string)
    {
        if ($this->icingaModuleName === null) {
            return t($string);
        } else {
            return mt($this->icingaModuleName, $string);
        }
    }

    public function onSuccess()
    {
        $this->redirectOnSuccess();
    }

    public function setSuccessMessage($message)
    {
        $this->successMessage = $message;
        return $this;
    }

    public function getSuccessMessage($message = null)
    {
        if ($message !== null) {
            return $message;
        }
        if ($this->successMessage === null) {
            return t('Form has successfully been sent');
        }
        return $this->successMessage;
    }

    public function redirectOnSuccess($message = null)
    {
        if ($this->isApiRequest()) {
            // TODO: Set the status line message?
            $this->successMessage = $this->getSuccessMessage($message);
            return;
        }

        $url = $this->getSuccessUrl();
        $this->notifySuccess($this->getSuccessMessage($message));
        $this->redirectAndExit($url);
    }

    public function onFailure()
    {
    }

    public function notifySuccess($message = null)
    {
        if ($message === null) {
            $message = t('Form has successfully been sent');
        }
        Notification::success($message);
        return $this;
    }

    public function notifyError($message)
    {
        Notification::error($message);
        return $this;
    }

    protected function redirectAndExit($url)
    {
        Icinga::app()->getFrontController()->getResponse()->redirectAndExit($url);
    }

    protected function setHttpResponseCode($code)
    {
        Icinga::app()->getFrontController()->getResponse()->setHttpResponseCode($code);
        return $this;
    }

    protected function onRequest()
    {
    }

    public function setRequest(Request $request)
    {
        if ($this->request !== null) {
            throw new ProgrammingError('Unable to set request twice');
        }

        $this->request = $request;
        $this->prepareElements();
        $this->onRequest();
        return $this;
    }

    public function getRequest()
    {
        if ($this->request === null) {
            $this->setRequest(Icinga::app()->getFrontController()->getRequest());
        }
        return $this->request;
    }

    public function hasBeenSent()
    {
        if ($this->hasBeenSent === null) {
            $req = $this->getRequest();
            if ($req->isPost()) {
                $post = $req->getPost();
                $this->hasBeenSent = array_key_exists(self::ID, $post) &&
                    $post[self::ID] === $this->getName();
            } else {
                $this->hasBeenSent === false;
            }
        }

        return $this->hasBeenSent;
    }

    protected function detectName()
    {
        if ($this->formName !== null) {
            $this->setName($this->formName);
        } else {
            $this->setName(get_class($this));
        }
    }
}
