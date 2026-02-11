<?php
namespace OCA\NextDiary\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
    private $l;
    private $urlGenerator;

    public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }

    public function getID(): string {
        return 'nextdiary';
    }

    public function getName(): string {
        return $this->l->t('Diary');
    }

    public function getPriority(): int {
        return 90;
    }

    public function getIcon(): string {
        return $this->urlGenerator->imagePath('nextdiary', 'diary.svg');
    }
}
