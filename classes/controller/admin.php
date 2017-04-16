<?php

namespace Foolz\FoolFrame\Controller\Admin\Plugins;

use Foolz\FoolFrame\Model\Validation\ActiveConstraint\Trim;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SpamGuard extends \Foolz\FoolFrame\Controller\Admin
{
    protected $purge_service;

    public function before()
    {
        parent::before();

        $this->param_manager->setParam('controller_title', 'Spam Guard');
    }

    public function security()
    {
        return $this->getAuth()->hasAccess('maccess.mod');
    }

    function structure()
    {
        return [
            'open' => [
                'type' => 'open',
            ],
            'foolfuuka.plugins.spam_guard.akismet_key' => [
                'preferences' => true,
                'type' => 'input',
                'label' => _i('Akismet API Key'),
                'class' => 'span3',
                'validation' => [new Trim()]
            ],
            'foolfuuka.plugins.spam_guard.akismet_url' => [
                'preferences' => true,
                'type' => 'input',
                'label' => _i('Hostname'),
                'class' => 'span3',
                'validation' => [new Trim()]
            ],
            'foolfuuka.plugins.spam_guard.enable_akismet' => [
                'preferences' => true,
                'type' => 'checkbox',
                'help' => _i('Check the comment data against Akismet.')
            ],
            'foolfuuka.plugins.spam_guard.enable_stopforumspam' => [
                'preferences' => true,
                'type' => 'checkbox',
                'help' => _i('Check the comment data against StopForumSpam.')
            ],
            'foolfuuka.plugins.spam_guard.enable_spooky' => [
                'preferences' => true,
                'type' => 'checkbox',
                'help' => _i('Enforce Captcha on first ghost post.')
            ],
            'foolfuuka.plugins.spam_guard.ban_ranges' => [
                'preferences' => true,
                'type' => 'textarea',
                'label' => _i('Banned IP ranges'),
                'help' => _i('Addressed in these ranges will not be allowed to post on any board.'),
                'class' => 'span8',
                'validation' => [new Trim()]
            ],
            'foolfuuka.plugins.spam_guard.tor_limits' => [
                'preferences' => true,
                'label' => _i('Tor limits'),
                'help' => _i('Limit posting for users coming from Tor connection? This might not always work but will never limit non-Tor users.'),
                'type' => 'select',
                'default_value' => 'none',
                'options' => ['none' => _i('No limits'), 'captcha' => _i('Always ask for captcha solution'), 'noimage' => _i('Disable image posting'), 'nopost' => _i('Disable all posting')]
            ],
            'separator-3' => [
                'type' => 'separator'
            ],
            'submit' => [
                'type' => 'submit',
                'class' => 'btn-primary',
                'value' => _i('Submit')
            ],
            'close' => [
                'type' => 'close'
            ],
        ];
    }

    function action_manage()
    {
        $this->param_manager->setParam('method_title', 'Manage');

        $data['form'] = $this->structure();

        $this->preferences->submit_auto($this->getRequest(), $data['form'], $this->getPost());
        $this->builder->createPartial('body', 'form_creator')->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }
}
