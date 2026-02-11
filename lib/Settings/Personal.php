<?php
namespace OCA\NextDiary\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {
    private $config;
    private $userId;

    public function __construct(IConfig $config, string $userId) {
        $this->config = $config;
        $this->userId = $userId;
    }

    public function getForm(): TemplateResponse {
        $settings = [];
        $keys = ['show_mood', 'show_wellbeing', 'show_tags', 'show_symptoms', 'show_medications'];
        foreach ($keys as $key) {
            $settings[$key] = $this->config->getUserValue($this->userId, 'nextdiary', $key, 'true') === 'true';
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
