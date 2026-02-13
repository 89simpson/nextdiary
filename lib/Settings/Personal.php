<?php
namespace OCA\NextDiary\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Personal implements ISettings {
    private $config;
    private $userSession;

    public function __construct(IConfig $config, IUserSession $userSession) {
        $this->config = $config;
        $this->userSession = $userSession;
    }

    public function getForm(): TemplateResponse {
        $user = $this->userSession->getUser();
        $userId = $user ? $user->getUID() : '';
        $settings = [];
        $keys = ['show_mood', 'show_wellbeing', 'show_tags', 'show_symptoms', 'show_medications'];
        foreach ($keys as $key) {
            $settings[$key] = $this->config->getUserValue($userId, 'nextdiary', $key, 'true') === 'true';
        }
        return new TemplateResponse('nextdiary', 'personal', ['settings' => $settings]);
    }

    public function getSection(): string {
        return 'nextdiary';
    }

    public function getPriority(): int {
        return 10;
    }
}
