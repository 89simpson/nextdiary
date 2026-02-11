<?php
namespace OCA\NextDiary\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {
    private $userId;
    private $config;

    private const SETTINGS_KEYS = [
        'show_mood' => 'true',
        'show_wellbeing' => 'true',
        'show_tags' => 'true',
        'show_symptoms' => 'true',
        'show_medications' => 'true',
    ];

    public function __construct($AppName, IRequest $request, $UserId, IConfig $config) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->config = $config;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getSettings(): DataResponse {
        $settings = [];
        foreach (self::SETTINGS_KEYS as $key => $default) {
            $settings[$key] = $this->config->getUserValue($this->userId, 'nextdiary', $key, $default) === 'true';
        }
        return new DataResponse($settings);
    }

    /**
     * @NoAdminRequired
     */
    public function updateSettings(string $key, $value): DataResponse {
        if (!array_key_exists($key, self::SETTINGS_KEYS)) {
            return new DataResponse(['error' => 'Invalid setting key'], 400);
        }
        $this->config->setUserValue($this->userId, 'nextdiary', $key, $value ? 'true' : 'false');
        return new DataResponse(['status' => 'ok']);
    }
}
