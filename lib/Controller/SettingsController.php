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

    private const JSON_SETTINGS_KEYS = [
        'sidebar_order' => '["tags","symptoms","medications"]',
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
        foreach (self::JSON_SETTINGS_KEYS as $key => $default) {
            $raw = $this->config->getUserValue($this->userId, 'nextdiary', $key, $default);
            $settings[$key] = json_decode($raw, true);
        }
        return new DataResponse($settings);
    }

    /**
     * @NoAdminRequired
     */
    public function updateSettings(string $key, $value): DataResponse {
        if (array_key_exists($key, self::JSON_SETTINGS_KEYS)) {
            $validKeys = ['tags', 'symptoms', 'medications'];
            if (!is_array($value) || count($value) !== 3 || array_diff($validKeys, $value) || array_diff($value, $validKeys)) {
                return new DataResponse(['error' => 'Invalid sidebar order'], 400);
            }
            $this->config->setUserValue($this->userId, 'nextdiary', $key, json_encode($value));
            return new DataResponse(['status' => 'ok']);
        }
        if (!array_key_exists($key, self::SETTINGS_KEYS)) {
            return new DataResponse(['error' => 'Invalid setting key'], 400);
        }
        $this->config->setUserValue($this->userId, 'nextdiary', $key, $value ? 'true' : 'false');
        return new DataResponse(['status' => 'ok']);
    }
}
